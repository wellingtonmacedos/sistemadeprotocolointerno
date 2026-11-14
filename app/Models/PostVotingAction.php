<?php

namespace App\Models;

use App\Enums\PostVotingStatus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostVotingAction extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'proposition_id', 'voting_id', 'status', 'notes',
        'performed_by', 'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostVotingStatus::class,
            'performed_at' => 'datetime',
        ];
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function voting(): BelongsTo
    {
        return $this->belongsTo(Voting::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
