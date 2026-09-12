<?php

namespace AlphaDirect;

use AlphaDirect\ReviewQuestion;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReviewAnswer extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'review_answers';

    /** an answer belongs to one question, it has an inverse in ReviewQuestion */
    public function questions()
    {
        return $this->belongsTo(ReviewQuestion::class, 'question_id');
    }

}
