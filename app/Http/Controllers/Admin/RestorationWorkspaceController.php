<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Storage\Services\ArchiveProviderConfiguration;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class RestorationWorkspaceController extends Controller
{
    public function __invoke(ArchiveProviderConfiguration $providers): View
    {
        return view('admin.restoration', [
            'recipes' => DB::table('processing_recipes')->latest()->limit(20)->get(),
            'jobs' => DB::table('processing_jobs')
                ->join('processing_recipes', 'processing_recipes.id', '=', 'processing_jobs.processing_recipe_id')
                ->select([
                    'processing_jobs.job_id',
                    'processing_jobs.state',
                    'processing_jobs.attempts',
                    'processing_jobs.created_at',
                    'processing_recipes.name as recipe_name',
                    'processing_recipes.version as recipe_version',
                ])
                ->latest('processing_jobs.created_at')
                ->limit(20)
                ->get(),
            'provider' => $providers->status(),
            'wasabi' => $providers->status('wasabi'),
        ]);
    }
}
