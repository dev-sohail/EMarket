<?php

namespace App\Http\Requests\Admin;

use App\Models\Tag;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates the tag create form.
 *
 * The slug is derived from the name, never submitted. That is why uniqueness is
 * checked against the *derived* slug and not the submitted name: `tags.slug`
 * carries a unique index in the database, while `tags.name` carries none, so a
 * name-only check lets two different names through and the insert then dies on
 * the unique constraint as a 500.
 *
 *   "Summer Sale" -> summer-sale
 *   "summer sale" -> summer-sale   (different name, same slug)
 *
 * Every rule is attached to `name` rather than to a synthetic `slug` field, so
 * the message renders under the one input the user can actually edit.
 */
class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $this->slugIsUsable(),
                $this->slugIsUnique(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Another tag already uses this name or a name that produces the same URL slug.',
        ];
    }

    /**
     * The values to persist: the submitted name plus the slug derived from it.
     *
     * @return array<string, string>
     */
    public function tagAttributes(): array
    {
        $name = (string) $this->input('name');

        return [
            'name' => $name,
            'slug' => $this->slugFor($name),
        ];
    }

    /**
     * Reject names that cannot produce a URL slug at all.
     *
     * Without this, "!!!" slugifies to an empty string and is stored as such —
     * twice, since the first one is already unique — so the second insert fails
     * on the unique index with the same unhelpful 500.
     */
    protected function slugIsUsable(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $this->slugFor($value) === '') {
                $fail('The name must contain at least one letter or number, so a URL can be built from it.');
            }
        };
    }

    /**
     * Reject a name whose slug is already taken, ignoring this tag itself so
     * saving a tag without renaming it is not a conflict with itself.
     */
    protected function slugIsUnique(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $slug = $this->slugFor($value);

            if ($slug === '') {
                // Already reported by slugIsUsable().
                return;
            }

            $query = Tag::where('slug', $slug);

            if (($id = $this->ignoringTagId()) !== null) {
                $query->where(fn (Builder $q) => $q->whereKeyNot($id));
            }
            if ($query->exists()) {
                $fail($this->messages()['name.unique']);
            }
        };
    }

    /**
     * The slug generated for a name.
     *
     * `Str::slug()` already returns an ASCII-lowercased string, so this is the
     * single definition of the rule that the uniqueness check and the write
     * path both use — they cannot drift and let a collision through.
     */
    protected function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /**
     * The key of the tag being updated, or null when creating.
     */
    protected function ignoringTagId(): ?string
    {
        return null;
    }
}
