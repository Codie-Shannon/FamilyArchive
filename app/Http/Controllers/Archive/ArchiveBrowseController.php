<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class ArchiveBrowseController extends Controller
{
    public function index(ApprovedPhotoGalleryQuery $query): View
    {
        return view('archive.index', ['photos' => $query->handle()]);
    }
}
