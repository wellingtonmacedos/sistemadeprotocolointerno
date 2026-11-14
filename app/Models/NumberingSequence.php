<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSequence extends Model
{
    protected $fillable = ['institution_id', 'scope', 'modality', 'year', 'last_number'];

    public const SCOPE_PROTOCOL = 'protocol';

    public const SCOPE_PROPOSITION = 'proposition';
}
