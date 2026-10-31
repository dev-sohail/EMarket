<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin listing for FAQs.
 *
 * The listing grew search, a status filter, sorting and pagination. The
 * regression worth pinning here is the ordering contract: the storefront shows
 * FAQs by ascending `sort_order`, so the admin list has to lead with that same
 * order instead of whatever `latest()` happened to return.
 */
class FaqManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create([
            'email' => 'faq-admin@example.test',
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        // faqs.* routes are mapped to faqs.view/create/update/delete in
        // config/admin.php, so the role needs them granted.
        foreach (['faqs.view', 'faqs.create', 'faqs.update', 'faqs.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'faqs',
            ])->getKey());
        }

        $this->actingAs($admin, 'admin');
    }

    // ------------------------------------------------------------------ index

    public function test_the_index_lists_faqs_with_answers_and_status(): void
    {
        Faq::create(['question' => 'How long does delivery take?', 'answer' => 'Two to four days.', 'sort_order' => 0, 'is_active' => true]);
        Faq::create(['question' => 'Do you ship abroad?', 'answer' => 'Not yet.', 'sort_order' => 1, 'is_active' => false]);

        $this->get('/admin/faqs')
            ->assertOk()
            ->assertSee('How long does delivery take?')
            ->assertSee('Two to four days.')
            ->assertSee('Published')
            ->assertSee('Hidden');
    }

    public function test_the_index_leads_with_sort_order_ascending(): void
    {
        Faq::create(['question' => 'Third', 'answer' => 'a', 'sort_order' => 30, 'is_active' => true]);
        Faq::create(['question' => 'First', 'answer' => 'a', 'sort_order' => 10, 'is_active' => true]);
        Faq::create(['question' => 'Second', 'answer' => 'a', 'sort_order' => 20, 'is_active' => true]);

        $questions = $this->get('/admin/faqs')->viewData('faqs')->pluck('question')->all();

        $this->assertSame(['First', 'Second', 'Third'], $questions);
    }

    public function test_the_index_can_be_searched_by_question_and_answer(): void
    {
        Faq::create(['question' => 'Shipping times?', 'answer' => 'Two to four days.', 'sort_order' => 0, 'is_active' => true]);
        Faq::create(['question' => 'Returns policy?', 'answer' => 'Refunds within 30 days.', 'sort_order' => 1, 'is_active' => true]);

        $byQuestion = $this->get('/admin/faqs?search=Shipping')->viewData('faqs')->pluck('question')->all();
        $this->assertSame(['Shipping times?'], $byQuestion);

        $byAnswer = $this->get('/admin/faqs?search=Refunds')->viewData('faqs')->pluck('question')->all();
        $this->assertSame(['Returns policy?'], $byAnswer);
    }

    public function test_the_index_can_be_filtered_by_status(): void
    {
        Faq::create(['question' => 'Live one', 'answer' => 'a', 'sort_order' => 0, 'is_active' => true]);
        Faq::create(['question' => 'Draft one', 'answer' => 'a', 'sort_order' => 1, 'is_active' => false]);

        $active = $this->get('/admin/faqs?status=active')->viewData('faqs')->pluck('question')->all();
        $this->assertSame(['Live one'], $active);

        $inactive = $this->get('/admin/faqs?status=inactive')->viewData('faqs')->pluck('question')->all();
        $this->assertSame(['Draft one'], $inactive);
    }

    public function test_the_index_reports_summary_counts(): void
    {
        Faq::create(['question' => 'Live one', 'answer' => 'a', 'sort_order' => 0, 'is_active' => true]);
        Faq::create(['question' => 'Live two', 'answer' => 'a', 'sort_order' => 1, 'is_active' => true]);
        Faq::create(['question' => 'Draft one', 'answer' => 'a', 'sort_order' => 2, 'is_active' => false]);

        $stats = $this->get('/admin/faqs')->viewData('stats');

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertSame(1, $stats['inactive']);
    }

    public function test_the_index_shows_an_empty_state(): void
    {
        $this->get('/admin/faqs')->assertOk()->assertSee('No FAQs yet.');
    }

    public function test_the_index_distinguishes_no_matches_from_no_faqs(): void
    {
        $this->get('/admin/faqs?search=nothing-matches')
            ->assertOk()
            ->assertSee('No FAQs match these filters.');
    }

    // ------------------------------------------------------------------ write

    public function test_a_faq_can_be_created_and_published(): void
    {
        $this->post('/admin/faqs', [
            'question' => 'Do you ship abroad?',
            'answer' => 'Not yet.',
            'sort_order' => 4,
            'is_active' => '1',
        ])->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseHas('faqs', [
            'question' => 'Do you ship abroad?',
            'sort_order' => 4,
            'is_active' => true,
        ]);
    }

    public function test_a_faq_created_without_the_active_box_is_hidden(): void
    {
        $this->post('/admin/faqs', [
            'question' => 'Draft question',
            'answer' => 'Draft answer',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(Faq::where('question', 'Draft question')->firstOrFail()->is_active);
    }

    public function test_a_faq_requires_a_question_and_answer(): void
    {
        $this->post('/admin/faqs', [])
            ->assertSessionHasErrors(['question', 'answer']);
    }

    public function test_a_faq_can_be_edited(): void
    {
        $faq = Faq::create(['question' => 'Old question', 'answer' => 'Old answer', 'sort_order' => 0, 'is_active' => true]);

        $this->put('/admin/faqs/'.$faq->getKey(), [
            'question' => 'New question',
            'answer' => 'New answer',
            'sort_order' => 2,
        ])->assertRedirect(route('admin.faqs.index'));

        $faq->refresh();

        $this->assertSame('New question', $faq->question);
        $this->assertSame(2, $faq->sort_order);
        $this->assertFalse($faq->is_active);
    }

    public function test_a_faq_can_be_toggled_from_the_listing(): void
    {
        $faq = Faq::create(['question' => 'Toggle me', 'answer' => 'a', 'sort_order' => 0, 'is_active' => true]);

        $this->patch('/admin/faqs/'.$faq->getKey().'/toggle-status')->assertRedirect();
        $this->assertFalse($faq->fresh()->is_active);

        $this->patch('/admin/faqs/'.$faq->getKey().'/toggle-status')->assertRedirect();
        $this->assertTrue($faq->fresh()->is_active);
    }

    public function test_a_faq_can_be_deleted(): void
    {
        $faq = Faq::create(['question' => 'Delete me', 'answer' => 'a', 'sort_order' => 0, 'is_active' => true]);

        $this->delete('/admin/faqs/'.$faq->getKey())
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseMissing('faqs', ['_id' => $faq->getKey()]);
    }
}
