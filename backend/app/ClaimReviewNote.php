<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A handler-authored note on a claim review. Multiple notes per claim; each
 * note carries its recipients (notified in-app + email) and file attachments.
 * Audited like ClaimComplaintLog so changes show in the claim Activity Log.
 */
class ClaimReviewNote extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'claim_review_notes';
    protected $guarded = ['id'];

    public function recipients()
    {
        return $this->hasMany(ClaimReviewNoteRecipient::class, 'review_note_id');
    }

    public function files()
    {
        return $this->hasMany(ClaimReviewNoteFile::class, 'review_note_id');
    }
}
