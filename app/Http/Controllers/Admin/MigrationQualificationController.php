<?php

namespace App\Http\Controllers\Admin;

use App\Domain\CloudImport\Models\MigrationQualificationRun;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MigrationQualificationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $focus = $request->string('qualification')->trim()->toString();
        $runs = MigrationQualificationRun::query()
            ->when($focus !== '', fn ($query) => $query->where('qualification_id', $focus))
            ->latest()->limit(10)->get();
        $focused = $runs->first();

        return view('admin.migration-qualification', [
            'runs' => $runs,
            'focused' => $focused,
            'checkpoints' => $focused === null ? collect() : DB::table('migration_qualification_checkpoints')
                ->where('migration_qualification_run_id', $focused->id)->orderByDesc('checkpoint_number')->limit(12)->get(),
        ]);
    }
}
