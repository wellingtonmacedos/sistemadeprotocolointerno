<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropositionAuthor extends Model
{
    protected $fillable = ['proposition_id', 'councilor_id', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function councilor(): BelongsTo
    {
        return $this->belongsTo(Councilor::class);
    }
}
