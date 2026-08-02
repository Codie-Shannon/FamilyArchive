<?php

namespace App\Http\Controllers;

use App\Domain\PublicDiscovery\Services\PublicMapPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PublicDiscoveryController extends Controller
{
    public function index(): View
    {
        return view('public-discovery.index', ['entries' => $this->publishedEntries()]);
    }

    public function map(PublicMapPolicy $mapPolicy): View
    {
        $points = DB::table('public_map_points')
            ->join('public_showcase_entries', 'public_showcase_entries.id', '=', 'public_map_points.public_showcase_entry_id')
            ->where('public_showcase_entries.state', 'published')
            ->where('public_map_points.privacy_reviewed', true)
            ->whereIn('public_map_points.precision', ['neighbourhood', 'town', 'region'])
            ->select([
                'public_showcase_entries.public_title',
                'public_map_points.latitude',
                'public_map_points.longitude',
                'public_map_points.precision',
                'public_map_points.public_place_name',
            ])
            ->get()
            ->map(function (object $point) use ($mapPolicy): array {
                $protected = $mapPolicy->protect(
                    (float) $point->latitude,
                    (float) $point->longitude,
                    (string) $point->precision,
                );

                return [
                    'title' => (string) $point->public_title,
                    'place' => (string) $point->public_place_name,
                    'precision' => $protected['precision'],
                    'latitude' => $protected['latitude'],
                    'longitude' => $protected['longitude'],
                ];
            });

        return view('public-discovery.map', [
            'points' => $points,
            'googleMapsKey' => (string) config('services.google_maps.browser_key', ''),
        ]);
    }

    public function admin(): View
    {
        return view('admin.public-discovery', [
            'entries' => DB::table('public_showcase_entries')->latest()->get(),
            'points' => DB::table('public_map_points')
                ->join('public_showcase_entries', 'public_showcase_entries.id', '=', 'public_map_points.public_showcase_entry_id')
                ->select([
                    'public_showcase_entries.public_title',
                    'public_map_points.public_place_name',
                    'public_map_points.precision',
                    'public_map_points.privacy_reviewed',
                ])
                ->latest('public_map_points.created_at')
                ->get(),
            'receipts' => DB::table('social_publication_receipts')
                ->join('public_showcase_entries', 'public_showcase_entries.id', '=', 'social_publication_receipts.public_showcase_entry_id')
                ->select([
                    'public_showcase_entries.public_title',
                    'social_publication_receipts.channel',
                    'social_publication_receipts.state',
                ])
                ->latest('social_publication_receipts.created_at')
                ->get(),
        ]);
    }

    private function publishedEntries(): mixed
    {
        return DB::table('public_showcase_entries')->where('state', 'published')->latest('published_at')->get();
    }
}
