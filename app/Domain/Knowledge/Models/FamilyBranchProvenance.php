<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FamilyBranchProvenance extends Model
{
    protected $table = 'family_branch_provenance_links';

    protected $guarded = [];

    /** @return BelongsTo<FamilyBranch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(FamilyBranch::class);
    }

    /** @return BelongsTo<SourceCollection, $this> */
    public function sourceCollection(): BelongsTo
    {
        return $this->belongsTo(SourceCollection::class);
    }

    /** @return BelongsTo<ScanBatch, $this> */
    public function scanBatch(): BelongsTo
    {
        return $this->belongsTo(ScanBatch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function attachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by');
    }
}
