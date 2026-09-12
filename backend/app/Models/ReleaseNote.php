<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseNote extends Model
{
    protected $fillable = ['version', 'title', 'highlights', 'is_published', 'published_at'];

    protected $casts = [
        'highlights'   => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopePublished($q)
    {
        return $q->where('is_published', true);
    }
}
