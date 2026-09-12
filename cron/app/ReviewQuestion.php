<?php

namespace AlphaDirect;

use AlphaDirect\ReviewAnswer;
use AlphaDirect\ReviewPages;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class ReviewQuestion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'review_questions';

    /**A review Question has multiple answers, the foreign key is the question_id in the ReviewAnswer table. */
    public function reviewanswers()
    {
        return $this->hasMany(ReviewAnswer::class, 'question_id')->select('question_id', 'answer');
    }

    /**the Review Answer has one type of answer field, checkbox radio button. and from the ReviewAnswerType get only the id and the type of answer */
    public function answertype()
    {
        return $this->hasOne(ReviewAnswerType::class, 'id', 'answer_type_id')->select('id', 'type');
    }

    /**A review question can belong to many review pages, Leads,Policy*/
    public function reviewpages()
    {
        return $this->belongsToMany(ReviewPages::class, 'review_question_review_page');
    }

    /** This relationship points to pivot table which relates the question to the Review Page, it also gets questions beong to the Leads Form */
    public function leadsreviewpage()
    {
        return $this->belongsToMany(ReviewPages::class, 'review_question_review_page')->where('review_pages_id', 1);
        //->wherePivot('review_pages_id', '=', 1);
    }

    /**function for getting questions for Policy */
    public function policyreviewpage()
    {
        return $this->belongsToMany(ReviewPages::class, 'review_question_review_page')->where('review_pages_id', 2);
    }

}
