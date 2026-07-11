<?php

namespace App\Models;

use App\Enums\EntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    protected $fillable = [
        'url', 'host', 'title', 'tagline', 'author_name',
        'x_handle', 'og_image_url', 'screenshot_url', 'votes_count', 'status',
    ];

    protected $attributes = [
        'votes_count' => 0,
    ];

    protected $casts = [
        'status' => EntryStatus::class,
        'votes_count' => 'integer',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
