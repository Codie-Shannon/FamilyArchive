<?php

namespace App\Http\Controllers;

use App\Domain\Access\Models\UploadSession;
use App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MemberHomeController extends Controller
{
    public function __invoke(Request $request, ApprovedPhotoGalleryQuery $archive): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $focusedPhotoId = $request->integer('photo') ?: null;
        $photos = $archive->handle($user, 4, $focusedPhotoId);
        $communitySpaces = DB::table('community_spaces')
            ->join('community_memberships', 'community_memberships.community_space_id', '=', 'community_spaces.id')
            ->where('community_memberships.user_id', $user->id)
            ->whereNull('community_memberships.suspended_at')
            ->distinct()
            ->count('community_spaces.id');
        $uploadSessions = $user->canContribute()
            ? UploadSession::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(3)
                ->get()
            : collect();

        return view('dashboard', compact('user', 'photos', 'communitySpaces', 'uploadSessions', 'focusedPhotoId'));
    }
}
