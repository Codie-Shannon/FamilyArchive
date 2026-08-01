<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class HighVolumeBatchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $focus = $request->string('batch')->trim()->toString();
        $sessionQuery = DB::table('cloud_import_sessions')->where('provider', 'manual_export');
        if ($focus !== '') {
            $sessionQuery->where('session_id', $focus);
        }

        $sessions = $sessionQuery->latest()->limit(20)->get()
            ->map(function (object $session): object {
                $session->manifest = json_decode((string) $session->source_manifest, true) ?: [];

                return $session;
            });

        $itemQuery = DB::table('cloud_import_items')
            ->join('cloud_import_sessions', 'cloud_import_sessions.id', '=', 'cloud_import_items.cloud_import_session_id')
            ->where('cloud_import_sessions.provider', 'manual_export');
        if ($focus !== '') {
            $itemQuery->where('cloud_import_sessions.session_id', $focus);
        }

        return view('admin.high-volume-batches', [
            'sessions' => $sessions,
            'recentItems' => $itemQuery->select('cloud_import_items.*')->latest('cloud_import_items.updated_at')->limit(24)->get(),
        ]);
    }
}
