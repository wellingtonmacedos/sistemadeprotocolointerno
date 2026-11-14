<?php

namespace App\Models;

use App\Enums\VoteType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VotingRecord extends Model
{
    use BelongsToInstitution;

    protected $fillable = [
        'institution_id', 'voting_id', 'councilor_id', 'vote', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'vote' => VoteType::class,
        ];
    }

    public function voting(): BelongsTo
    {
        return $this->belongsTo(Voting::class);
    }

    public function councilor(): BelongsTo
    {
        return $this->belongsTo(Councilor::class);
    }
}
