<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Services\ArchiveKnowledgeExplorer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class KnowledgeHubController extends Controller
{
    public function __invoke(Request $request, ArchiveKnowledgeExplorer $explorer): View
    {
        $query = (string) $request->string('q');

        return view('archive.knowledge', [
            'counts' => $explorer->counts(),
            'query' => $query,
            'results' => $explorer->search($query),
        ]);
    }
}
