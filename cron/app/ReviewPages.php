<?php

namespace AlphaDirect;

use AlphaDirect\MarketingQuestions;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReviewPages extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'review_pages';

    public function marketingQuestions()
    {
        return $this->belongsToMany(MarketingQuestions::class, 'review_question_review_page');
    }
}
