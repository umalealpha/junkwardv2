<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class WebsiteLead extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    public const TYPE_QUOTATION = 'quotation';
    public const TYPE_CONTACT = 'contact';
    public const TYPE_CLAIM = 'claim';
    public const TYPE_CAREER = 'career';
    public const TYPE_NEWSLETTER = 'newsletter';

    public const TYPES = [
        self::TYPE_QUOTATION,
        self::TYPE_CONTACT,
        self::TYPE_CLAIM,
        self::TYPE_CAREER,
        self::TYPE_NEWSLETTER,
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_SPAM = 'spam';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_SPAM,
    ];

    protected $table = 'website_leads';

    protected $fillable = [
        'type',
        'full_name',
        'email',
        'phone',
        'product',
        'message',
        'details',
        'status',
        'source',
        'ip',
        'user_agent',
        'submitted_at',
    ];

    protected $casts = [
        'details' => 'array',
        'submitted_at' => 'datetime',
    ];
}
