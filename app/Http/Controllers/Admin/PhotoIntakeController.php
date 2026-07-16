<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Intake\Presenters\IncomingUploadPresenter;
use App\Domain\Intake\Services\CreateAndRetainIncomingPhoto;
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

    public function store(Request $request, CreateAndRetainIncomingPhoto $creator): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'file']]);
        $upload = $creator->create($request->user(), $request->file('photo'));

        return redirect()->route('admin.photo-intake.show', $upload)->with('retained_upload', $upload->upload_id);
    }

    public function queue(IncomingUploadPresenter $presenter): View
    {
        $rows = IncomingUpload::query()->latest('submitted_at')->limit(50)->get()->map(fn (IncomingUpload $upload) => $presenter->present($upload));

        return view('admin.photo-intake.queue', ['rows' => $rows]);
    }

    public function show(IncomingUpload $incomingUpload, IncomingUploadPresenter $presenter): View
    {
        return view('admin.photo-intake.show', ['upload' => $presenter->present($incomingUpload)]);
    }
}
