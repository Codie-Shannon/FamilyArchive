<?php

namespace App\Http\Controllers\Admin;

use App\Domain\CloudImport\Services\CloudImportPlanner;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CloudImportController extends Controller
{
    public function __invoke(CloudImportPlanner $planner): View
    {
        return view('admin.cloud-imports', [
            'readiness' => $planner->readiness(),
            'sessions' => DB::table('cloud_import_sessions')->latest()->limit(20)->get(),
            'items' => DB::table('cloud_import_items')->latest()->limit(20)->get(),
            'profiles' => DB::table('media_playback_profiles')->latest()->limit(20)->get(),
        ]);
    }
}
