<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use ReflectionMethod;
use Tests\TestCase;

class PhaseFiveReportExportSafetyTest extends TestCase
{
    public function test_csv_formula_like_values_are_neutralized(): void
    {
        $method = new ReflectionMethod(ReportController::class, 'csvValue');
        $method->setAccessible(true);
        $controller = app(ReportController::class);
        foreach (['=SUM(1,1)', '+1', '-1', '@cmd'] as $value) {
            $this->assertSame("'".$value, $method->invoke($controller, $value));
        }
        $this->assertSame('ordinary text', $method->invoke($controller, 'ordinary text'));
    }
}
