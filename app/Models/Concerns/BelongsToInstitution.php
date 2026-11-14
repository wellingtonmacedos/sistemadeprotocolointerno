<?php

namespace App\Models\Concerns;

use App\Models\Institution;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplica isolamento lógico por institution_id e preenchimento automático.
 */
trait BelongsToInstitution
{
    protected static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope('institution', function (Builder $query) {
            if (Tenant::id() !== null && ! Tenant::isBypassed()) {
                $query->where($query->getModel()->getTable().'.institution_id', Tenant::id());
            }
        });

        static::creating(function ($model) {
            if (Tenant::id() !== null && empty($model->institution_id)) {
                $model->institution_id = Tenant::id();
            }
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
