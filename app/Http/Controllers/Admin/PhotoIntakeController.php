<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Intake\Presenters\IncomingUploadPresenter;
use App\Domain\Intake\Services\CreateIncomingPhotoRecord;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PhotoIntakeController extends Controller
{
    public function index(): View
    {
        return view('admin.photo-intake.index', ['limits' => config('archive.photo_intake')]);
    }

    public function store(Request $request, CreateIncomingPhotoRecord $creator): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'file']]);
        $u = $creator->create($request->user(), $request->file('photo'));

        return redirect()->route('admin.photo-intake.show', $u)->with('created_upload', $u->upload_id);
    }

    public function queue(IncomingUploadPresenter $p): View
    {
        $rows = IncomingUpload::query()->latest('submitted_at')->limit(50)->get()->map(fn ($u) => $p->present($u));

        return view('admin.photo-intake.queue', ['rows' => $rows]);
    }

    public function show(IncomingUpload $incomingUpload, IncomingUploadPresenter $p): View
    {
        return view('admin.photo-intake.show', ['upload' => $p->present($incomingUpload)]);
    }
}
