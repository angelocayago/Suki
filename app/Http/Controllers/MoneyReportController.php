<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\Seller;
use App\Services\MoneyReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MoneyReportController extends Controller
{
    public function rider(Request $request, MoneyReportService $reports): View
    {
        $rider = Rider::query()->where('user_id', $request->user()->id)->firstOrFail();
        return view('rider.money-report', ['data' => $reports->rider($rider)]);
    }

    public function logistics(Request $request, MoneyReportService $reports): View
    {
        $provider = LogisticsProvider::query()->where('user_id', $request->user()->id)->where('status', 'approved')->firstOrFail();
        return view('logistics.money-report', ['data' => $reports->logistics($provider)]);
    }

    public function seller(Request $request, MoneyReportService $reports): View
    {
        $seller = Seller::query()->where('user_id', $request->user()->id)->where('status', 'approved')->firstOrFail();
        return view('seller.settlements', ['data' => $reports->seller($seller)]);
    }

    public function admin(Request $request, MoneyReportService $reports): View|Response
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'provider_id' => ['nullable', 'integer', 'exists:logistics_providers,id'], 'seller_id' => ['nullable', 'integer', 'exists:sellers,id'], 'status' => ['nullable', 'in:OUTSTANDING,SUBMITTED,ACCEPTED,REJECTED']]);
        $data = $reports->admin($filters);
        if ($request->boolean('export')) return $this->csv($data['providerRows']);
        $providers = LogisticsProvider::query()->orderBy('name')->get(['id', 'name']);
        $sellers = Seller::query()->orderBy('name')->get(['id', 'name']);
        return view('admin.financial-report', compact('data', 'filters', 'providers', 'sellers'));
    }

    private function csv(iterable $rows): Response
    {
        $out = fopen('php://temp', 'r+'); fputcsv($out, ['provider', 'rider_received_minor', 'remitted_minor', 'outstanding_minor']);
        foreach ($rows as $row) fputcsv($out, array_map(fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value, $row));
        rewind($out); $content = stream_get_contents($out); fclose($out);
        return response($content, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="suki-financial-report.csv"', 'X-Content-Type-Options' => 'nosniff']);
    }
}
