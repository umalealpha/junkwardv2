<?php

namespace Tests\Unit;

use AlphaDirect\Console\Commands\UpdateCustomerKycCompliance;
use ReflectionClass;
use Tests\TestCase;

/**
 * The KYC failure reason is what a reviewer reads before deciding whether to
 * chase the customer or open the document. Reporting an unreviewed document as
 * "Not Uploaded" sent staff to chase customers who had already complied, and
 * left 766 active policies unapproved with every document on file.
 */
class KycComplianceReasonTest extends TestCase
{
    private UpdateCustomerKycCompliance $cmd;
    private \ReflectionMethod $evaluate;
    private \ReflectionMethod $displayName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cmd = new UpdateCustomerKycCompliance();
        $ref = new ReflectionClass($this->cmd);

        $this->evaluate = $ref->getMethod('evaluateCompliance');
        $this->evaluate->setAccessible(true);

        $this->displayName = $ref->getMethod('displayName');
        $this->displayName->setAccessible(true);
    }

    private function doc(string $column, int $check = 1, ?string $alt = null, string $fieldName = ''): array
    {
        return ['column' => $column, 'alt_column' => $alt, 'check' => $check, 'field_name' => $fieldName];
    }

    private function evaluate(object $customer, array $docs): array
    {
        return $this->evaluate->invoke($this->cmd, $customer, $docs);
    }

    public function test_a_document_on_file_but_unreviewed_is_not_reported_as_not_uploaded(): void
    {
        $customer = (object) [
            'proof_incomeStatus' => null,
            'proof_income'       => 'MIS/1/KYC/payslip.pdf',
            'proof_incomeRemark' => null,
        ];

        $result = $this->evaluate($customer, [$this->doc('proof_income')]);

        $this->assertSame('Proof of Income : Uploaded — awaiting verification', $result['reason']);
        $this->assertStringNotContainsString('Not Uploaded', $result['reason']);
        $this->assertFalse($result['compliant'], 'unreviewed is still not compliant');
    }

    public function test_a_genuinely_missing_document_is_still_reported_as_not_uploaded(): void
    {
        $customer = (object) [
            'proof_incomeStatus' => null,
            'proof_income'       => null,
            'proof_incomeRemark' => null,
        ];

        $result = $this->evaluate($customer, [$this->doc('proof_income')]);

        $this->assertSame('Proof of Income : Not Uploaded', $result['reason']);
    }

    public function test_an_approved_document_passes_with_no_reason(): void
    {
        $customer = (object) [
            'proof_incomeStatus' => 1,
            'proof_income'       => 'x.pdf',
            'proof_incomeRemark' => null,
        ];

        $result = $this->evaluate($customer, [$this->doc('proof_income')]);

        $this->assertTrue($result['compliant']);
        $this->assertSame('', $result['reason']);
    }

    public function test_an_explicit_rejection_remark_is_preserved(): void
    {
        $customer = (object) [
            'proof_incomeStatus' => 2,
            'proof_income'       => 'x.pdf',
            'proof_incomeRemark' => 'Illegible scan',
        ];

        $result = $this->evaluate($customer, [$this->doc('proof_income')]);

        $this->assertSame('Proof of Income : Illegible scan', $result['reason']);
    }

    public function test_rejected_without_a_remark_reads_not_approved(): void
    {
        $customer = (object) [
            'proof_incomeStatus' => 2,
            'proof_income'       => 'x.pdf',
            'proof_incomeRemark' => null,
        ];

        $result = $this->evaluate($customer, [$this->doc('proof_income')]);

        $this->assertSame('Proof of Income : Not Approved', $result['reason']);
    }

    /**
     * 12,512 stored reasons read " : Not Uploaded" with no document named,
     * because the display name fell through to an empty field_name.
     */
    public function test_the_document_label_is_never_blank(): void
    {
        $this->assertSame(
            'Kyc Form',
            $this->displayName->invoke($this->cmd, $this->doc('kyc_form', 1, null, 'Kyc Form'))
        );

        $this->assertSame(
            'Data Protection Form',
            $this->displayName->invoke($this->cmd, $this->doc('data_protection_form'))
        );

        $fallback = $this->displayName->invoke($this->cmd, $this->doc(''));
        $this->assertNotSame('', $fallback);
        $this->assertSame('Required document', $fallback);
    }

    public function test_either_or_documents_report_the_combined_label(): void
    {
        $onFile = (object) [
            'omangFrontStatus' => null, 'omang'    => 'front.jpg',
            'passportStatus'   => null, 'passport' => null,
            'omangFrontRemark' => null,
        ];
        $this->assertSame(
            'Omang (Front) or Passport : Uploaded — awaiting verification',
            $this->evaluate($onFile, [$this->doc('omang', 2, 'passport', 'Omang Front')])['reason']
        );

        $neither = (object) [
            'omangFrontStatus' => null, 'omang'    => null,
            'passportStatus'   => null, 'passport' => null,
            'omangFrontRemark' => null,
        ];
        $this->assertSame(
            'Omang (Front) or Passport : Not Uploaded',
            $this->evaluate($neither, [$this->doc('omang', 2, 'passport', 'Omang Front')])['reason']
        );
    }

    public function test_either_or_is_satisfied_by_the_alternative(): void
    {
        $customer = (object) [
            'omangFrontStatus' => null, 'omang'    => null,
            'passportStatus'   => 1,    'passport' => 'p.jpg',
            'omangFrontRemark' => null,
        ];

        $this->assertTrue(
            $this->evaluate($customer, [$this->doc('omang', 2, 'passport', 'Omang Front')])['compliant']
        );
    }

    public function test_every_failing_document_is_listed(): void
    {
        $customer = (object) [
            'proof_incomeStatus'    => null, 'proof_income'    => 'a.pdf', 'proof_incomeRemark'    => null,
            'proof_residenceStatus' => null, 'proof_residence' => null,    'proof_residenceRemark' => null,
        ];

        $result = $this->evaluate($customer, [$this->doc('proof_income'), $this->doc('proof_residence')]);

        $this->assertSame(
            'Proof of Income : Uploaded — awaiting verification<br>Proof of Residence : Not Uploaded',
            $result['reason']
        );
    }

    /**
     * An either/or document that is on file but was REVIEWED and rejected must
     * not read "awaiting verification" — that tells the reviewer to wait when the
     * document has in fact been actioned. It should read the rejection.
     */
    public function test_either_or_rejected_document_is_not_labelled_awaiting_verification(): void
    {
        $noRemark = (object) [
            'omangFrontStatus' => 2, 'omang'    => 'front.jpg',
            'passportStatus'   => null, 'passport' => null,
            'omangFrontRemark' => null, 'passportRemark' => null,
        ];
        $reason = $this->evaluate($noRemark, [$this->doc('omang', 2, 'passport', 'Omang Front')])['reason'];
        $this->assertSame('Omang (Front) or Passport : Not Approved', $reason);
        $this->assertStringNotContainsString('awaiting verification', $reason);

        $withRemark = (object) [
            'omangFrontStatus' => 2, 'omang'    => 'front.jpg',
            'passportStatus'   => null, 'passport' => null,
            'omangFrontRemark' => 'Blurred photo', 'passportRemark' => null,
        ];
        $this->assertSame(
            'Omang (Front) or Passport : Blurred photo',
            $this->evaluate($withRemark, [$this->doc('omang', 2, 'passport', 'Omang Front')])['reason']
        );
    }

    /**
     * A required document whose column has no STATUS_COLUMN_MAP entry (e.g. the
     * KYC / Data Protection forms if they are ever switched to required before
     * being mapped) must FAIL CLOSED — never be silently skipped and let the
     * policy be marked compliant without the document.
     */
    public function test_a_required_document_with_no_status_mapping_fails_closed(): void
    {
        $customer = (object) ['kyc_form' => 'form.pdf'];

        $result = $this->evaluate($customer, [$this->doc('kyc_form', 1, null, 'Kyc Form')]);

        $this->assertFalse($result['compliant'], 'an unmapped required doc must not pass');
        $this->assertSame('Kyc Form : cannot be verified (no compliance mapping)', $result['reason']);
    }

    /**
     * A DD/MM/YYYY expiry date (e.g. "30/04/2021") crashed the whole sweep
     * because Carbon::parse() reads "/" as US M/D/Y and throws on day > 12.
     * parseDate() must read it day-first, and must return null (never throw)
     * on anything unparseable so one bad row can't abort the run.
     */
    public function test_parse_date_handles_ddmmyyyy_and_never_throws(): void
    {
        $ref = new ReflectionClass($this->cmd);
        $parse = $ref->getMethod('parseDate');
        $parse->setAccessible(true);

        $this->assertSame('2021-04-30', $parse->invoke($this->cmd, '30/04/2021')->format('Y-m-d'));
        $this->assertSame('2024-01-15', $parse->invoke($this->cmd, '2024-01-15')->format('Y-m-d'));

        $this->assertNull($parse->invoke($this->cmd, 'not-a-date'));
        $this->assertNull($parse->invoke($this->cmd, null));
        $this->assertNull($parse->invoke($this->cmd, ''));
        $this->assertNull($parse->invoke($this->cmd, '0000-00-00'));
    }
}
