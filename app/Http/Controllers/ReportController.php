<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\Seller;
use App\Services\ReportService;
use App\Services\SellerWalletService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function admin(Request $request, ReportService $reports): View|Response
    {
        $dates = $this->dates($request);
        $data = $reports->admin(...$dates);
        if ($request->boolean('export')) return $this->csv('admin-sales-report.csv', $data['seller_performance'], ['seller','completed_orders','raw_minor','commission_minor','seller_discount_minor','platform_discount_minor','net_minor']);
        return view('admin.reports.phase-five', compact('data', 'dates'));
    }

    public function seller(Request $request, ReportService $reports, SellerWalletService $wallet): View|Response
    {
        $seller = $request->user()->sellers()->where('status', 'approved')->firstOrFail();
        $dates = $this->dates($request);
        $data = $reports->seller($seller, ...$dates);
        $data['wallet'] = $wallet->summary($seller);
        if ($request->boolean('export')) return $this->csv('seller-sales-report.csv', $data['products'], ['name','units','raw_minor']);
        return view('seller.phase-five-reports', compact('data', 'dates'));
    }

    public function logistics(Request $request, ReportService $reports): View|Response
    {
        $provider = LogisticsProvider::query()->where('user_id', $request->user()->id)->where('status', 'approved')->firstOrFail();
        $dates = $this->dates($request); $data = $reports->logistics($provider, ...$dates);
        if ($request->boolean('export')) return $this->csv('logistics-delivery-report.csv', $data['riders'], ['name','assignments','delivered','cod_collected_minor']);
        return view('logistics.phase-five-reports', compact('data', 'dates'));
    }

    public function rider(Request $request, ReportService $reports): View|Response
    {
        $rider = Rider::query()->where('user_id', $request->user()->id)->where('is_active', true)->firstOrFail();
        $dates = $this->dates($request); $data = $reports->rider($rider, ...$dates);
        if ($request->boolean('export')) return $this->csv('rider-history-report.csv', $data['shipments']->map(fn ($s) => ['tracking_code'=>$s->tracking_code,'status'=>$s->workflow_status?->value ?? $s->workflow_status,'cod_collected_minor'=>$s->cod_collected_minor]), ['tracking_code','status','cod_collected_minor']);
        return view('rider.reports', compact('data', 'dates'));
    }

    private function dates(Request $request): array
    {
        $validated = $request->validate(['from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from']]);
        return [isset($validated['from']) ? CarbonImmutable::parse($validated['from'])->startOfDay() : null, isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : null];
    }

    private function csv(string $filename, iterable $rows, array $columns): Response
    {
        $out = fopen('php://temp', 'r+'); fputcsv($out, $columns);
        foreach ($rows as $row) { $line=[]; foreach ($columns as $column) { $value=is_array($row) ? ($row[$column] ?? '') : ($row->{$column} ?? ''); $line[]=$this->csvValue((string)$value); } fputcsv($out, $line); }
        rewind($out); $content=stream_get_contents($out); fclose($out);
        return response($content, 200, ['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$filename.'"','X-Content-Type-Options'=>'nosniff']);
    }

    private function csvValue(string $value): string { return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value; }
}
