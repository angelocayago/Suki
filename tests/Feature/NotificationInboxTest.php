<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use App\Services\DomainNotificationService;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_and_read_operations_are_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->notify(new InboxTestNotification('Owner event'));
        $owner->notify(new InboxTestNotification('Second event'));
        $other->notify(new InboxTestNotification('Private event'));
        $ownId = $owner->notifications()->firstOrFail()->id;
        $otherId = $other->notifications()->firstOrFail()->id;

        $this->actingAs($owner)->getJson('/notifications')
            ->assertOk()->assertJsonPath('unread_count', 2)
            ->assertJsonMissing(['title' => 'Private event']);
        $this->getJson('/notifications/unread-count')->assertJsonPath('unread_count', 2);
        $this->postJson('/notifications/'.$otherId.'/read')->assertNotFound();
        $this->postJson('/notifications/'.$ownId.'/read')->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertNotNull($owner->notifications()->findOrFail($ownId)->read_at);
        $this->postJson('/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_guest_cannot_access_the_inbox(): void
    {
        $this->getJson('/notifications')->assertUnauthorized();
        $this->postJson('/notifications/read-all')->assertUnauthorized();
    }

    public function test_inbox_page_escapes_data_and_supports_normal_forms(): void
    {
        $owner = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $owner->notify(new InboxTestNotification('<script>alert(1)</script>'));
        $id = $owner->notifications()->firstOrFail()->id;
        $this->actingAs($owner)->get(route('notifications.page'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->post(route('notifications.read', $id))->assertRedirect(route('notifications.page'));
        $this->assertSame(0, $owner->unreadNotifications()->count());
    }

    public function test_notification_delivery_failure_does_not_rollback_business_transaction(): void
    {
        $user = User::factory()->create();
        NotificationFacade::shouldReceive('send')->andThrow(new \RuntimeException('Notification delivery failed'));
        DB::transaction(function () use ($user) {
            $user->update(['name' => 'Committed business action']);
            app(DomainNotificationService::class)->afterCommit($user->id, 'Update', 'A business update.');
        });
        $this->assertSame('Committed business action', $user->fresh()->name);
    }
}

class InboxTestNotification extends Notification
{
    public function __construct(private string $title) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => 'Test event', 'context' => []];
    }
}
