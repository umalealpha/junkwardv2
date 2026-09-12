<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

class WordingFile extends Model
{
    protected $table = 'wordings_files';

    protected $fillable = [
        'category',
        'product_code',
        'display_name',
        's3_key',
        'content_hash',
        'size_bytes',
        'mime_type',
        'is_active',
        'notes',
        'uploaded_by',
        'uploaded_at',
        'deactivated_by',
        'deactivated_at',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'size_bytes'      => 'integer',
        'uploaded_at'     => 'datetime',
        'deactivated_at'  => 'datetime',
        'deleted_at'      => 'datetime',
    ];

    public const CATEGORIES = [
        'adi'                => 'Accidental Death Insurance',
        'com'                => 'Commercial',
        'dom'                => 'Domestic',
        'funeral-cover'      => 'Funeral Cover',
        'hospital-cashback'  => 'Hospital Cashback',
        'legal'              => 'Legal',
        'mobile-electronic'  => 'Mobile & Electronic Devices',
        'motor-3rd-party'    => 'Motor Third Party',
    ];

    public function scopeActive($q)
    {
        return $q->whereNull('deleted_at')->where('is_active', true);
    }

    public function scopeAlive($q)
    {
        return $q->whereNull('deleted_at');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
