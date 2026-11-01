<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintEvidence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerComplaintsTest extends TestCase
{
    use \Tests\Support\UsesIsolatedComplaintSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
        foreach ([11 => 'seller', 22 => 'buyer', 44 => 'admin', 55 => 'seller', 66 => 'logistics', 77 => 'rider', 88 => 'superadmin'] as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'name' => $role.' '.$id, 'role' => $role, 'status' => 'active', 'email' => 'SECRET-'.$id.'@example.test', 'phone' => 'SECRET-PHONE']);
        }
        DB::table('sellers')->insert([
            ['id' => 101, 'user_id' => 11, 'name' => 'Owned Shop', 'slug' => 'owned', 'status' => 'approved'],
            ['id' => 202, 'user_id' => 55, 'name' => 'Other Shop', 'slug' => 'other', 'status' => 'approved'],
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 02:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function record(int $id = 1, int $actor = 11, int $seller = 101, string $status = 'OPEN', string $date = '2026-11-01 01:00:00'): Complaint
    {
        DB::table('orders')->insert(['id' => $id, 'buyer_id' => 22, 'order_number' => 'ORDER-'.$id, 'status' => 'DELIVERED']);
        DB::table('seller_orders')->insert(['id' => $id, 'seller_id' => $seller, 'order_id' => $id]);
        $complaint = Complaint::create(['reference' => 'CMP-OWN-'.$id, 'complainant_id' => $actor, 'complainant_role' => $actor === 22 ? 'BUYER' : 'SELLER',
            'seller_id' => $seller, 'seller_order_id' => $id, 'order_id' => $id, 'buyer_id' => 22, 'category' => 'OTHER', 'status' => $status,
            'subject' => 'Subject '.$id, 'description' => '<script>untrusted complaint</script>', 'resolution_notes' => 'PRIVATE RESOLUTION', 'context_snapshot' => ['private' => 'PRIVATE SNAPSHOT']]);
        $complaint->forceFill(['created_at' => $date])->save();

        return $complaint;
    }

    private function page(array $query = [])
    {
        return $this->actingAs(User::findOrFail(11))->get(route('seller.complaints.index', $query))->assertOk();
    }

    public function test_sidebar_layout_active_state_summary_and_newest_first_owned_scope(): void
    {
        foreach (['OPEN', 'UNDER_REVIEW', 'WAITING_RESPONSE', 'RESOLVED', 'DISMISSED'] as $index => $status) $this->record($index + 1, status: $status);
        $this->record(6, 22); // A Buyer complaint about the same store is still private.
        $this->record(7, 55, 202);
        $page = $this->page()->assertViewIs('seller.complaints.index')->assertSee('Seller Centre', false)
            ->assertSee('id="sellerSidebar"', false)->assertSee('href="'.route('seller.complaints.index').'"', false)
            ->assertDontSee('Subject 6')->assertDontSee('Subject 7');
        $this->assertMatchesRegularExpression('/<a[^>]*href="'.preg_quote(route('seller.complaints.index'), '/').'"[^>]*aria-current="page"/s', $page->getContent());
        $this->assertSame(['Total complaints' => 5, 'Open' => 1, 'In progress' => 2, 'Closed' => 2], $page->viewData('summary'));
        $this->assertSame([5, 4, 3, 2, 1], $page->viewData('complaints')->pluck('id')->all());
        if ($dir = getenv('SELLER_COMPLAINTS_CHECK_DIR')) file_put_contents($dir.'/index.html', $page->getContent());
    }

    public function test_search_filters_are_combined_literal_and_do_not_bypass_authorization(): void
    {
        $this->record(); $this->record(2, status: 'RESOLVED'); $this->record(3, 22);
        foreach (['cmp-own-1', 'order-1'] as $search) {
            $page = $this->page(['search' => $search, 'status' => 'OPEN', 'date' => 'today']);
            $this->assertSame([1], $page->viewData('complaints')->pluck('id')->all());
            $page->assertSee('Active filters')->assertSee('Clear All');
        }
        foreach (['CMP-OWN-3', '%', '_', 'missing'] as $search) $this->assertSame(0, $this->page(['search' => $search])->viewData('complaints')->total());
        $this->assertSame(2, $this->page(['status' => 'RESOLVED'])->viewData('summary')['Total complaints']);
        foreach (['status' => 'CLOSED', 'date' => 'invalid', 'page' => 0, 'search' => str_repeat('x', 101)] as $key => $value) $this->getJson(route('seller.complaints.index', [$key => $value]))->assertUnprocessable();
    }

    public function test_manila_calendar_day_boundaries(): void
    {
        foreach (['2026-10-31 15:59:59', '2026-10-31 16:00:00', '2026-11-01 15:59:59', '2026-11-01 16:00:00', '2026-10-25 16:00:00', '2026-10-25 15:59:59', '2026-10-02 16:00:00', '2026-10-02 15:59:59'] as $index => $date) $this->record($index + 1, date: $date);
        $this->assertSame([3, 2], $this->page(['date' => 'today'])->viewData('complaints')->pluck('id')->all());
        $this->assertSame([3, 2, 1, 5], $this->page(['date' => '7'])->viewData('complaints')->pluck('id')->all());
        $this->assertSame([3, 2, 1, 5, 6, 7], $this->page(['date' => '30'])->viewData('complaints')->pluck('id')->all());
    }

    public function test_pagination_preserves_selections_and_empty_states_are_truthful(): void
    {
        $this->page()->assertSee('No complaints related to your store yet.');
        for ($id = 1; $id <= 17; $id++) $this->record($id);
        $page = $this->page(['search' => 'CMP', 'status' => 'OPEN', 'date' => 'today']);
        $this->assertCount(15, $page->viewData('complaints'));
        $page->assertSee('page=2', false)->assertSee('search=CMP', false)->assertSee('status=OPEN', false)->assertSee('date=today', false)
            ->assertSee('<details class="rr-filter-panel">', false)->assertDontSee('<details class="rr-filter-panel" open', false);
        $this->assertSame([2, 1], $this->page(['search' => 'CMP', 'status' => 'OPEN', 'date' => 'today', 'page' => 2])->viewData('complaints')->pluck('id')->all());
        $this->page(['search' => 'missing'])->assertSee('No matching complaints')->assertSee('href="'.route('seller.complaints.index').'"', false);
        $this->assertSame(17, $this->page()->viewData('complaints')->total());
    }

    public function test_details_are_scoped_escape_untrusted_text_and_hide_internal_notes(): void
    {
        $complaint = $this->record(); $complaint->update(['assigned_to' => 44]);
        $buyerComplaint = $this->record(2, 22); $foreign = $this->record(3, 55, 202);
        DB::table('complaint_events')->insert(['complaint_id' => 1, 'action' => 'STATUS_CHANGED', 'new_status' => 'UNDER_REVIEW', 'notes' => 'PRIVATE ADMIN NOTE', 'metadata' => '{"private":"PRIVATE METADATA"}', 'created_at' => '2026-11-01 01:00:00']);
        $page = $this->actingAs(User::findOrFail(11))->get(route('seller.complaints.show', $complaint))->assertOk()->assertViewIs('seller.complaints.show')
            ->assertSee('&lt;script&gt;untrusted complaint&lt;/script&gt;', false)->assertSee('Status Changed')->assertSee('Message Admin')
            ->assertSee('action="'.route('complaints.messages.context.store', $complaint).'"', false)
            ->assertDontSee('PRIVATE ADMIN NOTE')->assertDontSee('PRIVATE METADATA')->assertDontSee('PRIVATE RESOLUTION')->assertDontSee('PRIVATE SNAPSHOT')
            ->assertDontSee('SECRET-22@example.test')->assertDontSee('SECRET-PHONE')->assertSee('name="_token"', false);
        foreach ([$buyerComplaint, $foreign] as $denied) $this->get(route('seller.complaints.show', $denied))->assertNotFound();
        if ($dir = getenv('SELLER_COMPLAINTS_CHECK_DIR')) file_put_contents($dir.'/show.html', $page->getContent());
        DB::table('seller_orders')->where('id', 1)->update(['seller_id' => 202]);
        $this->get(route('seller.complaints.show', $complaint))->assertNotFound();
        $this->assertSame(0, $this->page()->viewData('complaints')->total());
    }

    public function test_unlinked_order_fallback_and_unrelated_personal_cases_stay_in_generic_history(): void
    {
        $complaint = $this->record(); $complaint->update(['order_id' => null, 'seller_order_id' => null]);
        $this->page()->assertSee('Not linked to an order');
        $this->get(route('seller.complaints.show', $complaint))->assertOk()->assertSee('Not linked to an order');
        $complaint->update(['seller_id' => null]);
        $this->page()->assertDontSee('Subject 1');
        $this->get(route('complaints.mine.index'))->assertOk()->assertSee('My Complaints')->assertSee('CMP-OWN-1');
    }

    public function test_existing_evidence_upload_and_delivery_enforce_complainant_and_uploader_permissions(): void
    {
        Storage::fake('local');
        $complaint = $this->record(); $foreign = $this->record(2, 55, 202);
        $url = route('seller.complaints.show', $complaint);
        $this->actingAs(User::findOrFail(11))->from($url)->post(route('complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('seller-proof.pdf', 10, 'application/pdf'), 'description' => 'Seller explanation',
        ])->assertRedirect($url)->assertSessionHas('success');
        $file = ComplaintEvidence::sole();
        $this->get($url)->assertOk()->assertSee('seller-proof.pdf')->assertSee('Seller explanation')->assertDontSee($file->path);
        $this->get(route('complaints.evidence.download', [$complaint, $file]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $adminFile = ComplaintEvidence::create(['complaint_id' => $complaint->id, 'uploaded_by' => 44, 'disk' => 'local', 'path' => 'secret-admin.pdf', 'original_name' => 'PRIVATE-ADMIN-EVIDENCE', 'mime_type' => 'application/pdf', 'size' => 10]);
        $this->get($url)->assertOk()->assertDontSee('PRIVATE-ADMIN-EVIDENCE');
        $this->get(route('complaints.evidence.show', [$complaint, $adminFile]))->assertForbidden();
        $this->get(route('complaints.evidence.show', [$foreign, $file]))->assertForbidden();
        $this->actingAs(User::findOrFail(55))->get(route('complaints.evidence.show', [$complaint, $file]))->assertForbidden();
        $this->actingAs(User::findOrFail(11)); $complaint->update(['status' => 'RESOLVED']);
        $this->get($url)->assertOk()->assertSee('Evidence uploads are closed')->assertDontSee('<form class="cmp-evidence-form', false);
        $this->post(route('complaints.evidence.store', $complaint), ['evidence' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf')])->assertSessionHasErrors('status');
        $this->assertSame(0, DB::table('seller_wallet_entries')->count());
    }

    public function test_other_roles_are_denied_seller_routes_and_existing_route_middleware_is_unchanged(): void
    {
        $complaint = $this->record();
        foreach ([22, 44, 66, 77, 88] as $id) {
            $this->actingAs(User::findOrFail($id))->get(route('seller.complaints.index'))->assertForbidden();
            $this->get(route('seller.complaints.show', $complaint))->assertForbidden();
        }
        foreach (['index', 'show', 'create', 'store'] as $action) {
            $route = app('router')->getRoutes()->getByName('seller.complaints.'.$action);
            foreach (['web', 'auth', 'not_suspended', 'approved', 'role:seller'] as $middleware) $this->assertContains($middleware, $route->gatherMiddleware());
        }
        $this->assertSame('/complaints', '/'.app('router')->getRoutes()->getByName('complaints.mine.index')->uri());
        $seller = User::findOrFail(11); $seller->update(['is_suspended' => true]);
        $this->actingAs($seller)->get(route('seller.complaints.index'))->assertForbidden();
    }
}
