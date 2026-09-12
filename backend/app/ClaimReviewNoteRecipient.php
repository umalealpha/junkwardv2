<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimReviewNoteRecipient extends Model
{
    use HasFactory;

    protected $table = 'claim_review_note_recipients';
    public $timestamps = false;
    protected $guarded = ['id'];
}
