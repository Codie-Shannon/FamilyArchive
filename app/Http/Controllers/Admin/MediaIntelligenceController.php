<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class MediaIntelligenceController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.media-intelligence', [
            'candidates' => DB::table('visual_similarity_candidates')->latest()->limit(20)->get(),
            'alternates' => DB::table('alternate_media_sources')->latest()->limit(20)->get(),
            'merges' => DB::table('metadata_merge_proposals')->latest()->limit(20)->get(),
        ]);
    }
}
