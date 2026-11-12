<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\LogisticsSortingZone;
use App\Models\Order;
use Illuminate\Support\Collection;

class SortingZoneResolver
{
    public function covers(LogisticsSortingZone $zone, Order $destination): bool
    {
        return $zone->areas()->where('region_code', $destination->region_code)
            ->where('city_municipality_code', $destination->city_municipality_code)
            ->where(function ($query) use ($destination) {
                $destination->province_code ? $query->where('province_code', $destination->province_code) : $query->whereNull('province_code');
            })
            ->where(function ($query) use ($destination) {
                $query->whereNull('barangay_code')->orWhere('barangay_code', $destination->barangay_code);
            })->exists();
    }

    /** @return array{zone:?LogisticsSortingZone, specificity:?string, ambiguous:bool, candidates:Collection} */
    public function resolve(LogisticsProvider $provider, Order $destination): array
    {
        $region = trim((string) $destination->region_code);
        $province = trim((string) $destination->province_code);
        $city = trim((string) $destination->city_municipality_code);
        $barangay = trim((string) $destination->barangay_code);
        if ($region === '' || $city === '') {
            return ['zone' => null, 'specificity' => null, 'ambiguous' => false, 'candidates' => collect()];
        }

        $matches = LogisticsSortingZone::query()
            ->with(['hub', 'areas'])
            ->where('logistics_provider_id', $provider->id)
            ->where('is_active', true)
            ->whereHas('hub', fn ($hub) => $hub
                ->where('is_active', true)
                ->where('logistics_provider_id', $provider->id))
            ->whereHas('areas', function ($query) use ($region, $province, $city, $barangay) {
                $query->where('region_code', $region)
                    ->where('city_municipality_code', $city)
                    ->where(function ($query) use ($province) {
                        $province === '' ? $query->whereNull('province_code') : $query->where('province_code', $province);
                    })
                    ->where(function ($query) use ($barangay) {
                        if ($barangay !== '') {
                            $query->whereNull('barangay_code')->orWhere('barangay_code', $barangay);
                        } else {
                            $query->whereNull('barangay_code');
                        }
                    });
            })
            ->get()
            ->map(function (LogisticsSortingZone $zone) use ($region, $province, $city, $barangay) {
                $area = $zone->areas->first(fn ($area) => $area->region_code === $region
                    && $area->city_municipality_code === $city
                    && (string) $area->province_code === $province
                    && ($area->barangay_code === $barangay || $area->barangay_code === null));
                if (! $area) return null;
                return ['zone' => $zone, 'specificity' => $area->barangay_code ? 2 : 1];
            })
            ->filter()
            ->sortBy(fn ($match) => [-$match['specificity'], $match['zone']->priority])
            ->values();

        if ($matches->isEmpty()) {
            return ['zone' => null, 'specificity' => null, 'ambiguous' => false, 'candidates' => collect()];
        }

        $specificity = $matches->first()['specificity'];
        $best = $matches->where('specificity', $specificity)->values();
        $bestPriority = $best->min(fn ($match) => $match['zone']->priority);
        $winners = $best->where('zone.priority', $bestPriority)->values();
        if ($winners->count() !== 1) {
            return ['zone' => null, 'specificity' => $specificity === 2 ? 'barangay' : 'city', 'ambiguous' => true, 'candidates' => $winners->pluck('zone')];
        }

        return ['zone' => $winners->first()['zone'], 'specificity' => $specificity === 2 ? 'barangay' : 'city', 'ambiguous' => false, 'candidates' => $best->pluck('zone')];
    }

    public function areaKey(string $region, ?string $province, string $city, ?string $barangay): string
    {
        return implode(':', [$region, $province ?: '-', $city, $barangay ?: '-']);
    }
}
