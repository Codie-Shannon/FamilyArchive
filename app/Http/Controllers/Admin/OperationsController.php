<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class OperationsController extends Controller
{
    public function __invoke(): View
    {
        $checks = DB::table('integrity_checks')
            ->selectRaw('result, count(*) as total')
            ->groupBy('result')
            ->pluck('total', 'result');

        $transfers = DB::table('storage_transfers')
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        return view('admin.operations', [
            'checks' => $checks,
            'checkTotal' => $checks->sum(),
            'mismatchTotal' => $checks
                ->except(['verified'])
                ->sum(),
            'transfers' => $transfers,
            'repairs' => DB::table('repair_cases')
                ->join('integrity_checks', 'integrity_checks.id', '=', 'repair_cases.integrity_check_id')
                ->whereNotIn('repair_cases.state', ['closed'])
                ->select([
                    'repair_cases.case_id',
                    'repair_cases.state',
                    'repair_cases.updated_at',
                    'integrity_checks.result',
                ])
                ->latest('repair_cases.updated_at')
                ->limit(8)
                ->get(),
            'backups' => DB::table('backup_verifications')
                ->latest()
                ->limit(5)
                ->get(),
            'events' => DB::table('operational_events')
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
