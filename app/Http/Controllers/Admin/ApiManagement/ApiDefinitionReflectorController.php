<?php

namespace App\Http\Controllers\Admin\ApiManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApiManagement\ReflectApiDefinitionRequest;
use App\Models\ApiDefinition;
use App\Services\Api\Reflection\DefinitionReflector;
use App\Services\Api\Reflection\ModelReflector;
use App\Services\Api\Reflection\ModelResolver;
use App\Services\Api\Reflection\PathConflictException;
use App\Services\Audit\ApiManagementAuditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

/**
 * Generates API definitions from Eloquent models.
 *
 * This is the admin-side counterpart of `php artisan api:reflect`. It exists
 * because the `source` field on a definition is not a setting an admin can
 * meaningfully choose: setting it to `reflection` by hand produces an active
 * definition with no endpoints, no parameters and no handler, which serves
 * nothing and looks like a working API. Reflection is a *generation* step, so
 * the UI has to actually perform it — pick a model, see what it would produce,
 * generate it.
 *
 * Regeneration is offered separately from generation because the two have very
 * different consequences: creating is safe, while regenerating overwrites the
 * fields the reflector owns and must be a deliberate act.
 */
class ApiDefinitionReflectorController extends Controller
{
    public function __construct(
        protected ModelResolver $models,
        protected DefinitionReflector $reflector,
        protected ApiManagementAuditor $auditor,
    ) {}

    /**
     * List every model that can be reflected, with what reflection would make
     * of it.
     *
     * The preview is what makes this screen useful rather than a guess: it
     * shows the URLs that will be created, how many filters, and which models
     * cannot be reflected and why.
     */
    public function index(): View
    {
        $this->models->flush();

        $rows = [];

        foreach ($this->models->tableMap() as $table => $class) {
            try {
                $rows[] = $this->reflector->preview($class) + [
                    'class' => $class,
                    'available' => true,
                    'problem' => null,
                ];
            } catch (Throwable $e) {
                report($e);

                $rows[] = [
                    'class' => $class,
                    'table' => $table,
                    'available' => false,
                    // ModelReflector's own InvalidArgument/Runtime messages say
                    // why a model cannot be reflected; a QueryException (a
                    // PDOException) carries SQL and must not be shown.
                    'problem' => ($e instanceof \InvalidArgumentException
                        || ($e instanceof \RuntimeException && ! $e instanceof \PDOException))
                        ? $e->getMessage()
                        : 'This model could not be inspected. See the application log for details.',
                    'paths' => [],
                    'filters' => [],
                    'relations' => [],
                    'scopes' => [],
                    'model' => $class,
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['class'], $b['class']));

        return view('admin.api-management.definitions.reflect', [
            'rows' => $rows,
            // A slug already claimed by a reflected definition, so the screen
            // can say "regenerate" instead of inviting a duplicate.
            'existingSlugs' => ApiDefinition::where('source', 'reflection')
                ->pluck('slug', 'slug')
                ->all(),
        ]);
    }

    /**
     * Generate a definition from the chosen model.
     */
    public function store(ReflectApiDefinitionRequest $request): RedirectResponse
    {
        $class = $this->models->resolveClass($request->string('model')->toString());

        // Captured before the write: for a re-run this is the state
        // regeneration is about to overwrite, which is the only evidence that a
        // hand edit was reverted. The slug comes from the blueprint rather than
        // being re-derived here, so the audit and the writer cannot disagree
        // about which definition they mean.
        $blueprint = app(ModelReflector::class)->reflect($class);
        $existing = ApiDefinition::withTrashed()->where('slug', $blueprint->path())->first();
        $before = $existing ? $this->auditor->snapshotDefinition($existing) : null;

        try {
            $definition = $this->reflector->write($class, [
                'activate' => $request->boolean('activate'),
            ]);
        } catch (PathConflictException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            // report() + fixed copy: a QueryException message carries the SQL.
            report($e);

            return back()
                ->withInput()
                ->with('error', 'Reflection failed. See the application log for details.');
        }

        $summary = $this->auditor->reflectSummary($definition);

        if ($before === null) {
            $this->auditor->definitionReflected($definition, $class, $summary);
        } else {
            $this->auditor->definitionRegenerated($definition, $class, $before, $summary);
        }

        return redirect()
            ->route('admin.api-management.definitions.edit', $definition)
            ->with('success', sprintf(
                '%s API definition "%s" with %d endpoint(s) from %s.',
                $definition->wasRecentlyCreated ? 'Generated' : 'Regenerated',
                $definition->name,
                $definition->endpoints->count(),
                $class
            ));
    }

    /**
     * Regenerate an existing reflected definition.
     *
     * Refuses a definition whose source is `manual`: regenerating that would
     * overwrite hand-written endpoints with generic ones, which is never what
     * the admin behind a "regenerate" button meant.
     */
    public function regenerate(ApiDefinition $apiDefinition): RedirectResponse
    {
        if ($apiDefinition->source !== 'reflection') {
            return redirect()
                ->route('admin.api-management.definitions.edit', $apiDefinition)
                ->with('error', 'Only a reflected definition can be regenerated. This one was built by hand.');
        }

        // The slug is the key the reflector matches on, so the model it came
        // from is recovered from the definition rather than being re-chosen.
        $class = $this->models->forTable($apiDefinition->slug);

        if ($class === null) {
            return redirect()
                ->route('admin.api-management.definitions.edit', $apiDefinition)
                ->with('error', sprintf(
                    'No model reflects the table [%s] any more, so this definition cannot be regenerated. '
                    .'The model may have been renamed or removed.',
                    $apiDefinition->slug
                ));
        }

        $before = $this->auditor->snapshotDefinition($apiDefinition);

        try {
            $definition = $this->reflector->write($class, ['activate' => $apiDefinition->is_active]);
        } catch (PathConflictException $e) {
            return redirect()
                ->route('admin.api-management.definitions.edit', $apiDefinition)
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.api-management.definitions.edit', $apiDefinition)
                ->with('error', 'Regeneration failed. See the application log for details.');
        }

        $this->auditor->definitionRegenerated(
            $definition,
            $class,
            $before,
            $this->auditor->reflectSummary($definition)
        );

        return redirect()
            ->route('admin.api-management.definitions.edit', $apiDefinition)
            ->with('success', sprintf(
                'Regenerated "%s" from %s: %d endpoint(s), %d parameter(s).',
                $apiDefinition->name,
                $class,
                $apiDefinition->endpoints()->count(),
                $apiDefinition->endpoints()->withCount('parameters')->get()->sum('parameters_count')
            ));
    }
}
