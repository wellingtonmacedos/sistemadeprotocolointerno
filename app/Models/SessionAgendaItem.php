<?php

namespace App\Models;

use App\Enums\AgendaItemStatus;
use App\Enums\AppreciationType;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionAgendaItem extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'session_id', 'proposition_id', 'position',
        'appreciation_type', 'will_discuss', 'will_vote', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'appreciation_type' => AppreciationType::class,
            'status' => AgendaItemStatus::class,
            'will_discuss' => 'boolean',
            'will_vote' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SessionDocument::class, 'session_agenda_item_id');
    }
}
