<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    protected $fillable = ['entry_id', 'voter_hash'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
