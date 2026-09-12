<?php

namespace Tests\Unit;

use AlphaDirect\Services\ClaimMentionParser;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB. Covers @mention parsing (single, multiple, none,
 * email-not-a-mention) and resolution against a candidate user list (valid,
 * unknown user, multiple).
 */
class ClaimMentionParserTest extends TestCase
{
    /** Candidate users the controller would load from the users table. */
    private function users(): array
    {
        return [
            ['id' => 1, 'firstName' => 'John',  'lastName' => 'Doe',    'email' => 'jdoe@alphadirect.co.bw'],
            ['id' => 2, 'firstName' => 'Aradhana', 'lastName' => 'K',   'email' => 'aradhana@alphadirect.co.bw'],
            ['id' => 3, 'firstName' => 'Mary',  'lastName' => 'Smith',  'email' => 'mary.smith@alphadirect.co.bw'],
        ];
    }

    // ─── parse ───────────────────────────────────────────────────────────────

    public function test_parse_no_mention(): void
    {
        $this->assertSame([], ClaimMentionParser::parse('Please review this claim today.'));
        $this->assertSame([], ClaimMentionParser::parse(''));
        $this->assertSame([], ClaimMentionParser::parse(null));
    }

    public function test_parse_single_mention(): void
    {
        $this->assertSame(['jdoe'], ClaimMentionParser::parse('Hey @jdoe can you check this?'));
    }

    public function test_parse_multiple_mentions_deduped_and_lowercased(): void
    {
        $handles = ClaimMentionParser::parse('@jdoe and @Aradhana please, cc @jdoe again');
        $this->assertSame(['jdoe', 'aradhana'], $handles);
    }

    public function test_parse_dotted_handle(): void
    {
        $this->assertSame(['mary.smith'], ClaimMentionParser::parse('assigning to @mary.smith now'));
    }

    public function test_parse_ignores_email_addresses(): void
    {
        // The '@' in an email is preceded by a word char, so it isn't a mention.
        $this->assertSame([], ClaimMentionParser::parse('email me at jane@alphadirect.co.bw'));
    }

    public function test_parse_trims_trailing_punctuation(): void
    {
        $this->assertSame(['jdoe'], ClaimMentionParser::parse('ping @jdoe.'));
    }

    // ─── resolve ───────────────────────────────────────────────────────────────

    public function test_resolve_valid_by_email_local_part(): void
    {
        $r = ClaimMentionParser::resolve(['jdoe'], $this->users());
        $this->assertSame([['handle' => 'jdoe', 'userId' => 1]], $r['matches']);
        $this->assertSame([], $r['unknown']);
    }

    public function test_resolve_valid_by_first_name(): void
    {
        $r = ClaimMentionParser::resolve(['aradhana'], $this->users());
        $this->assertSame([['handle' => 'aradhana', 'userId' => 2]], $r['matches']);
    }

    public function test_resolve_valid_by_dotted_first_last(): void
    {
        $r = ClaimMentionParser::resolve(['mary.smith'], $this->users());
        $this->assertSame([['handle' => 'mary.smith', 'userId' => 3]], $r['matches']);
    }

    public function test_resolve_unknown_user(): void
    {
        $r = ClaimMentionParser::resolve(['ghost'], $this->users());
        $this->assertSame([], $r['matches']);
        $this->assertSame(['ghost'], $r['unknown']);
    }

    public function test_resolve_multiple_mixed(): void
    {
        $r = ClaimMentionParser::resolve(['jdoe', 'ghost', 'aradhana'], $this->users());
        $this->assertSame(
            [['handle' => 'jdoe', 'userId' => 1], ['handle' => 'aradhana', 'userId' => 2]],
            $r['matches']
        );
        $this->assertSame(['ghost'], $r['unknown']);
    }

    public function test_matched_user_ids_are_unique(): void
    {
        // Two handles pointing at the same user collapse to one id.
        $r = ClaimMentionParser::resolve(['jdoe', 'john'], $this->users());
        $this->assertSame([1], ClaimMentionParser::matchedUserIds($r));
    }

    public function test_end_to_end_parse_then_resolve(): void
    {
        $handles = ClaimMentionParser::parse('@jdoe please loop in @mary.smith, not @ghost');
        $r = ClaimMentionParser::resolve($handles, $this->users());
        $this->assertSame([1, 3], ClaimMentionParser::matchedUserIds($r));
        $this->assertSame(['ghost'], $r['unknown']);
    }
}
