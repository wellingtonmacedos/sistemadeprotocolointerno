<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Councilor extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $fillable = [
        'institution_id', 'user_id', 'name', 'party', 'email',
        'phone', 'mandate', 'legislature', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::created(function (Councilor $councilor) {
            if ($councilor->user_id !== null) {
                static::syncUserLink($councilor->user_id, $councilor->id);
            }
        });

        static::updated(function (Councilor $councilor) {
            if ($councilor->wasChanged('user_id')) {
                static::syncUserLink($councilor->user_id, $councilor->id);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mantém espelhado o vínculo usuário ↔ vereador (relação 1:1). Gravar um
     * lado atualiza o outro e ninguém acumula dois vínculos.
     */
    public static function syncUserLink(?int $userId, ?int $councilorId): void
    {
        // Um usuário responde por, no máximo, um vereador.
        if ($userId !== null) {
            static::query()
                ->where('user_id', $userId)
                ->when($councilorId !== null, fn ($query) => $query->whereKeyNot($councilorId))
                ->update(['user_id' => null]);
        }

        // Um vereador é respondido por, no máximo, um usuário.
        if ($councilorId !== null) {
            User::query()
                ->where('councilor_id', $councilorId)
                ->when($userId !== null, fn ($query) => $query->whereKeyNot($userId))
                ->update(['councilor_id' => null]);
        }

        // Espelha o par atual nos dois lados.
        if ($councilorId !== null) {
            static::query()->whereKey($councilorId)->update(['user_id' => $userId]);
        }

        if ($userId !== null) {
            User::query()->whereKey($userId)->update(['councilor_id' => $councilorId]);
        }
    }
}
