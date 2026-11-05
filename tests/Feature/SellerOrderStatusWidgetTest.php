<?php

namespace Tests\Feature;

use App\Support\SellerOrders;
use Tests\Support\IsolatedStorageTestCase;

class SellerOrderStatusWidgetTest extends IsolatedStorageTestCase
{
    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Only isolated in-memory SQLite is allowed.');
        }
        parent::setUp();
        $this->assertStoragePathIsIsolated();
    }

    private function widget(array $counts): \DOMXPath
    {
        $html = view('seller.dashboard-partials.order-status', [
            'statusCounts'=>$counts, 'orderCount'=>array_sum($counts),
            'completedCount'=>$counts['completed']??0, 'statusLabels'=>SellerOrders::statuses(),
        ])->render();
        $dom = new \DOMDocument;
        $before = libxml_use_internal_errors(true);
        try { $dom->loadHTML('<?xml encoding="UTF-8">'.$html); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($before); }
        return new \DOMXPath($dom);
    }

    public function test_mixed_distribution_has_keyed_semantic_colors_counts_percentages_and_matching_badges(): void
    {
        // Deliberately not chart order: identity must never depend on count ordering.
        $xpath = $this->widget(['unknown'=>1,'rts_returning'=>1,'preparing'=>2,'completed'=>4,'delivered'=>2]);
        $expected = ['completed'=>['#15803D',4,'40.0'], 'progress'=>['#D97706',2,'20.0'],
            'delivered'=>['#2563EB',2,'20.0'], 'attention'=>['#DC2626',1,'10.0'], 'unknown'=>['#64748B',1,'10.0']];
        $arcTotal = 0;
        foreach ($expected as $key=>[$color,$count,$percent]) {
            $segment = $xpath->query('//*[@data-status-segment="'.$key.'"]')->item(0);
            $choice = $xpath->query('//*[@data-status-choice="'.$key.'"]')->item(0);
            $this->assertNotNull($segment); $this->assertNotNull($choice);
            $this->assertSame('--status-color:'.$color,$segment->getAttribute('style'));
            $this->assertSame('--status-color:'.$color,$choice->parentNode->getAttribute('style'));
            $this->assertSame((string)$count,$segment->getAttribute('data-status-count'));
            $this->assertSame($percent,$segment->getAttribute('data-status-percent'));
            $this->assertSame($percent,$choice->getAttribute('data-status-percent'));
            $this->assertSame('0',$segment->getAttribute('tabindex'));
            $this->assertSame('button',$choice->getAttribute('type'));
            $this->assertStringContainsString($percent.'%',$choice->getAttribute('aria-label'));
            $badge = $xpath->query('//*[@data-status-category="'.$key.'"]')->item(0);
            $this->assertNotNull($badge); $this->assertSame('--status-color:'.$color,$badge->getAttribute('style'));
            $arcTotal += (float)explode(' ',$segment->getAttribute('stroke-dasharray'))[0];
        }
        $this->assertEqualsWithDelta(95,$arcTotal,0.001); // Five small, real segment boundaries.
        $this->assertSame(1,$xpath->query('//details/summary')->length);
        $this->assertSame(0,$xpath->query('//*[@data-status-category="unknown" and @href]')->length);
        $badge=$xpath->query('//*[@data-status-category="progress"]')->item(0);
        $this->assertSame(route('seller.orders',['status'=>'PREPARING']),$badge->getAttribute('href'));
    }

    public function test_zero_total_has_no_colored_segments_and_five_real_zero_percentages(): void
    {
        $xpath=$this->widget([]);
        $this->assertSame(0,$xpath->query('//*[@data-status-segment]')->length);
        $this->assertSame(5,$xpath->query('//*[@data-status-choice and @data-status-count="0" and @data-status-percent="0.0"]')->length);
        $this->assertSame(1,$xpath->query('//*[@data-status-detail and @aria-live="polite"]')->length);
        $this->assertStringContainsString('No orders yet.',$xpath->query('//*[@data-status-detail]')->item(0)->textContent);
    }

    public function test_single_status_has_a_full_ring_and_keeps_zero_category_colors_and_responsive_theme_support(): void
    {
        $xpath=$this->widget(['delivered'=>3]);
        $this->assertSame(1,$xpath->query('//*[@data-status-segment]')->length);
        $segment=$xpath->query('//*[@data-status-segment="delivered"]')->item(0);
        $this->assertSame('100 0',$segment->getAttribute('stroke-dasharray'));
        $this->assertSame('100.0',$segment->getAttribute('data-status-percent'));
        $this->assertSame('--status-color:#15803D',$xpath->query('//*[@data-status-choice="completed"]')->item(0)->parentNode->getAttribute('style'));
        $css=file_get_contents(resource_path('views/seller/dashboard-partials/style.blade.php'));
        foreach(['width:min(100%,210px)','minmax(0,1fr)','html[data-theme=dark]','prefers-reduced-motion','focus-visible','min-height:44px'] as $rule) $this->assertStringContainsString($rule,$css);
    }
}
