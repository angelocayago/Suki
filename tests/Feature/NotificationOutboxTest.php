<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DomainNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_rolled_back_business_transaction_leaves_no_notification_intent(): void
    {
        $user = User::factory()->create();
        try {
            DB::transaction(function () use ($user) {
                app(DomainNotificationService::class)->afterCommit($user->id, 'Rollback', 'Not delivered');
                throw new \RuntimeException('Roll back');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Roll back', $exception->getMessage());
        }
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_failed_delivery_is_durable_and_retry_is_idempotent_and_private(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $dispatcher = Notification::getFacadeRoot();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('private details must not be stored'));

        DB::transaction(function () use ($user) {
            $user->update(['name' => 'Committed action']);
            app(DomainNotificationService::class)->afterCommit($user->id, 'Update', 'A business update.', ['order_id' => 123]);
        });
        $intent = DB::table('notification_outbox')->where('user_id', $user->id)->first();
        $this->assertNotNull($intent);
        $this->assertSame('Committed action', $user->fresh()->name);
        $this->assertNull($intent->processed_at);
        $this->assertSame(1, $intent->attempts);
        $this->assertSame('RuntimeException', $intent->last_error);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(0, app(DomainNotificationService::class)->retry());

        Notification::swap($dispatcher);
        DB::table('notification_outbox')->where('id', $intent->id)->update(['available_at' => now()->subSecond()]);
        $this->artisan('notifications:retry')->assertSuccessful();
        $this->assertNotNull(DB::table('notification_outbox')->where('id', $intent->id)->value('processed_at'));
        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(123, $user->notifications()->first()->data['context']['order_id']);
        $this->actingAs($other)->postJson('/notifications/'.$intent->id.'/read')->assertNotFound();
        $this->artisan('notifications:retry')->assertSuccessful();
        $this->assertSame(1, $user->notifications()->count());
    }
}
