<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolAttachment extends Model
{
    use BelongsToInstitution;

    protected $fillable = [
        'institution_id', 'protocol_id', 'protocol_movement_id',
        'original_name', 'file_path', 'mime_type', 'size', 'hash_sha256', 'uploaded_by',
    ];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(ProtocolMovement::class, 'protocol_movement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
