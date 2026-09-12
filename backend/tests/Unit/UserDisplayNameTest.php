<?php

namespace Tests\Unit;

use AlphaDirect\User;
use Tests\TestCase;

/**
 * `User::$name`, which did not exist and was read everywhere.
 *
 * There is no `name` column on `users` — the table carries `firstName` and
 * `lastName`. So every `Auth::user()->name` in the codebase resolved to null,
 * and did it silently: they are all written as `?? null` or `?: something`.
 *
 * The visible symptom was Reinsurance's: the FAC slip printed "PREPARED BY"
 * with nothing after it, because FacSlipService sets `prepared_by_name` from
 * `$placement->underwriter_name ?: Auth::user()->name` and BOTH sides came from
 * the same missing attribute — `underwriter_name` also defaults to it on
 * capture, which is why it is null on all six placements in the register.
 *
 * NO DATABASE IS TOUCHED. The accessor is pure and the models here are built in
 * memory and never saved, so nothing queries — which matters because .env
 * points at a live server. The Laravel TestCase is needed only because
 * AlphaDirect\User implements Auditable and its boot reaches for config;
 * auditing is disabled below so the audit driver cannot reach for a connection.
 */
class UserDisplayNameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['audit.enabled' => false]);
        User::disableAuditing();
    }

    private function user(?string $first, ?string $last): User
    {
        $u = new User;
        $u->forceFill(['firstName' => $first, 'lastName' => $last]);

        return $u;
    }

    public function test_the_display_name_is_assembled_from_the_columns_that_hold_it(): void
    {
        $this->assertSame('Snehal Zunjarrao', $this->user('Snehal', 'Zunjarrao')->name);
    }

    /** The model title-cases the halves, and `name` inherits that. */
    public function test_the_name_is_title_cased_like_the_rest_of_the_model(): void
    {
        $this->assertSame('Snehal Zunjarrao', $this->user('SNEHAL', 'zunjarrao')->name);
    }

    /**
     * NULL, never ''. Every existing call site is written as `?? null` or
     * `?: $fallback`, so an empty string would defeat the fallback and store a
     * blank where the old code stored null — a different bug, not a fix.
     */
    public function test_a_user_with_no_name_at_all_gives_null_not_an_empty_string(): void
    {
        $this->assertNull($this->user(null, null)->name);
    }

    public function test_one_half_missing_still_gives_a_usable_name(): void
    {
        $this->assertSame('Snehal', $this->user('Snehal', null)->name);
        $this->assertSame('Zunjarrao', $this->user(null, 'Zunjarrao')->name);
    }

    /**
     * `name` is not appended. Adding it to $appends would put the key on every
     * serialised user and change existing API payloads.
     */
    public function test_the_accessor_does_not_change_the_serialised_payload(): void
    {
        $this->assertArrayNotHasKey('name', $this->user('Snehal', 'Zunjarrao')->toArray());
    }
}
