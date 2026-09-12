<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * claim_type -> claim form/letter mapping used by ClaimFormEmailListener to
 * email the claimant the right document when a claim is registered.
 *
 * Lives on the default connection alongside the `claims` table.
 */
class ClaimTypeForm extends Model
{
    protected $table = 'claim_type_forms';

    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
    ];
}
