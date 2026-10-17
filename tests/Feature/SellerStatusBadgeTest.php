<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerStatusBadgeTest extends TestCase
{
    public static function statuses(): array
    {
        return [
            'active' => ['active', 'Active', 'bg-emerald-50 text-emerald-700'],
            'approved' => ['approved', 'Approved', 'bg-emerald-50 text-emerald-700'],
            'verified' => ['verified', 'Verified', 'bg-emerald-50 text-emerald-700'],
            'pending' => ['pending', 'Pending', 'bg-amber-50 text-amber-700'],
            'under review' => ['under_review', 'Under Review', 'bg-amber-50 text-amber-700'],
            'awaiting approval' => ['awaiting-approval', 'Awaiting Approval', 'bg-amber-50 text-amber-700'],
            'suspended' => ['suspended', 'Suspended', 'bg-red-50 text-red-700'],
            'rejected' => ['rejected', 'Rejected', 'bg-red-50 text-red-700'],
            'disabled' => ['disabled', 'Disabled', 'bg-red-50 text-red-700'],
            'inactive' => ['inactive', 'Inactive', 'bg-red-50 text-red-700'],
            'unknown' => ['unmapped_status', 'Unmapped Status', 'bg-gray-100 text-gray-600'],
            'missing' => [null, 'Not available', 'bg-gray-100 text-gray-600'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_semantic_badge_colors_keep_the_real_status_label(?string $status, string $label, string $colors): void
    {
        $html = Blade::render('<x-seller-status-badge :status="$status" />', compact('status'));
        $this->assertStringContainsString($colors, $html);
        $this->assertStringContainsString('>'.$label.'</span>', $html);
        $this->assertStringContainsString('rounded-full', $html);
        $this->assertStringContainsString('whitespace-normal break-words', $html);
    }

    public function test_unmapped_labels_are_escaped_and_remain_neutral(): void
    {
        $status = '<script>alert(1)</script>';
        $html = Blade::render('<x-seller-status-badge :status="$status" />', compact('status'));
        $this->assertStringContainsString('bg-gray-100 text-gray-600', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;', $html);
    }
}
