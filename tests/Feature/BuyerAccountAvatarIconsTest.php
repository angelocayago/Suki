<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\DomainEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Blade, Http, Storage};
use Tests\Support\IsolatedStorageTestCase;

class BuyerAccountAvatarIconsTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        Storage::fake('public'); Http::preventStrayRequests();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false,
            'first_name' => 'Actual', 'last_name' => 'Buyer', 'name' => 'Actual Buyer']);
        $this->actingAs($this->buyer);
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    public function test_dropdown_icons_keep_labels_counts_and_the_exact_header_bell_artwork(): void
    {
        $this->buyer->notify(new DomainEventNotification('Fixture notification', 'Synthetic notice.'));
        $this->buyer->notify(new DomainEventNotification('Second fixture notification', 'Synthetic notice.'));
        $page = $this->get(route('buyer.account'))->assertOk(); $xpath = $this->xpath($page->getContent());
        $menu = '//header//div[contains(concat(" ",normalize-space(@class)," ")," invisible ")]';
        foreach (['buyer.vouchers.index' => ['My Vouchers', 'ticket'], 'reviews.buyer-page' => ['My reviews', 'star']] as $route => [$label, $icon]) {
            $link = $xpath->query($menu.'//a[@href="'.route($route).'"]')->item(0);
            $this->assertNotNull($link);
            $this->assertSame($label, trim($link->textContent));
            $this->assertSame(1, $xpath->query('.//i[@data-lucide="'.$icon.'" and @aria-hidden="true"]', $link)->length);
            $this->assertStringContainsString('gap-3', $link->getAttribute('class'));
        }
        $notifications = $xpath->query($menu.'//a[@href="'.route('notifications.page').'"]')->item(0);
        $this->assertSame(1, $xpath->query('.//span[@aria-label="2 unread"]', $notifications)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-buyer-notification-count or contains(@class,"suki-notification-badge")]', $notifications)->length);
        $header = $xpath->query('//details[@data-buyer-notifications]/summary/*[local-name()="svg"]')->item(0);
        $dropdown = $xpath->query('.//*[local-name()="svg"]', $notifications)->item(0);
        $this->assertNotNull($header); $this->assertNotNull($dropdown);
        foreach (['viewbox', 'fill', 'stroke', 'stroke-width', 'aria-hidden'] as $attribute) $this->assertSame($header->getAttribute($attribute), $dropdown->getAttribute($attribute));
        $this->assertSame('M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4', $xpath->query('.//*[local-name()="path"]', $dropdown)->item(0)->getAttribute('d'));
        $this->assertSame($xpath->query('.//*[local-name()="path"]', $header)->item(0)->getAttribute('d'), $xpath->query('.//*[local-name()="path"]', $dropdown)->item(0)->getAttribute('d'));
        $this->assertSame('19', $header->getAttribute('width')); $this->assertSame('16', $dropdown->getAttribute('width'));
        $this->assertSame('2', trim($xpath->query('//*[@data-buyer-notification-count]')->item(0)->textContent));
        $this->buyer->unreadNotifications()->update(['read_at' => now()]);
        $this->get(route('buyer.account'))->assertOk()->assertSee('Notifications, 0 unread')->assertDontSee('aria-label="2 unread"', false);
    }

    public function test_all_three_buyer_avatar_locations_have_square_clipped_circular_initials(): void
    {
        $xpath = $this->xpath($this->get(route('buyer.account'))->assertOk()->getContent());
        $locations = ['//header//a[@href="'.route('buyer.account').'"]//*[@aria-label="Actual Buyer avatar"]' => '7',
            '//aside[@data-buyer-account-sidebar]//*[@aria-label="Actual Buyer avatar"]' => '12',
            '//*[@data-profile-name]/../preceding-sibling::*[@aria-label="Actual Buyer avatar"]' => '16'];
        foreach ($locations as $selector => $size) {
            $nodes = $xpath->query($selector); $this->assertSame(1, $nodes->length);
            $avatar = $nodes->item(0); $classes = explode(' ', $avatar->getAttribute('class'));
            foreach (['h-'.$size, 'w-'.$size, 'aspect-square', 'shrink-0', 'overflow-hidden', 'rounded-full', 'items-center', 'justify-center'] as $class) $this->assertContains($class, $classes);
            $this->assertSame('A', trim($avatar->textContent));
        }
    }

    public function test_opted_in_existing_photo_rendering_crops_inside_a_circle_without_adding_upload_controls(): void
    {
        $html = Blade::render('<x-profile-avatar :user="$user" photo="avatars/fixture-wide.jpg" size="h-12 w-12" :circular="true" />', ['user' => $this->buyer]);
        $xpath = $this->xpath($html); $image = $xpath->query('//img')->item(0);
        $this->assertNotNull($image);
        $this->assertSame(Storage::disk('public')->url('avatars/fixture-wide.jpg'), $image->getAttribute('src'));
        $this->assertStringContainsString('object-cover', $image->getAttribute('class'));
        $this->assertStringContainsString('h-full w-full', $image->getAttribute('class'));
        $this->assertStringContainsString('overflow-hidden rounded-full', $image->parentNode->getAttribute('class'));
        $this->assertSame('Actual Buyer avatar', $image->getAttribute('alt'));
        $page = $this->get(route('buyer.account'))->assertOk()->assertDontSee('Change Photo')->assertDontSee('Remove Photo');
        $this->assertSame(0, $this->xpath($page->getContent())->query('//*[@id="editProfileModal"]//input[@type="file"]')->length);
    }

    public function test_other_portal_avatar_callers_keep_the_existing_default_image_and_initial_markup(): void
    {
        foreach (['seller', 'rider', 'logistics', 'admin'] as $role) {
            $user = User::factory()->make(['role' => $role, 'first_name' => 'Portal', 'last_name' => 'User']);
            $initial = view('components.profile-avatar', compact('user'))->render();
            $this->assertStringContainsString('>P</span>', $initial);
            $this->assertStringNotContainsString('aspect-square', $initial);
            $html = view('components.profile-avatar', ['user' => $user, 'photo' => 'existing-avatar.jpg'])->render();
            $xpath = $this->xpath($html); $image = $xpath->query('//img')->item(0);
            $this->assertSame('body', $image->parentNode->nodeName);
            $this->assertSame('h-9 w-9 rounded-full object-cover', $image->getAttribute('class'));
        }
    }

    public function test_circular_initial_fallback_retains_unicode_and_empty_name_behavior(): void
    {
        foreach ([' Ñora' => 'Ñ', '   ' => 'U'] as $name => $initial) {
            $html = Blade::render('<x-profile-avatar :name="$name" :circular="true" />', compact('name'));
            $xpath = $this->xpath($html); $avatar = $xpath->query('//span')->item(0);
            $this->assertSame($initial, trim($avatar->textContent));
            $this->assertStringContainsString('rounded-full', $avatar->getAttribute('class'));
        }
    }
}
