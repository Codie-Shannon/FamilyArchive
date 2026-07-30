<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Release\Services\AcceptanceMatrix;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ReleaseAcceptanceController extends Controller
{
    public function __invoke(AcceptanceMatrix $matrix): View
    {
        return view('admin.release-acceptance', [
            'gates' => $matrix->gates(),
            'feedback' => DB::table('pilot_feedback')->latest()->limit(8)->get(),
            'custodians' => DB::table('custodian_designations')->latest()->limit(8)->get(),
            'latestRun' => DB::table('release_acceptance_runs')->latest()->first(),
            'humanGates' => [
                'Controlled family pilot approval' => DB::table('pilot_feedback')
                    ->where('state', 'accepted')
                    ->exists(),
                'Production deployment proof' => DB::table('operational_events')
                    ->where('type', 'deployment')
                    ->whereNotNull('resolved_at')
                    ->exists(),
                'Primary custodian confirmation' => DB::table('custodian_designations')
                    ->where('role', 'primary')
                    ->where('state', 'confirmed')
                    ->exists(),
            ],
        ]);
    }
}
