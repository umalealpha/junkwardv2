<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use AlphaDirect\Services\Swiftly\SwiftlyService;

/**
 * Exercises the outbound invoice-submission logic against a mocked Swiftly
 * server — a local "dry run" of what runs on staging, without needing the
 * live API key or a deploy. Confirms the request we build is correct
 * (endpoint, auth header, program_id, minor-unit amount, and the new
 * auto_request_early_payment flag) and that the disable switch short-circuits.
 */
class SwiftlyServiceTest extends TestCase
{
    public function test_submit_invoice_sends_auto_request_flag_and_program_id(): void
    {
        config([
            'services.swiftly.enabled'    => true,
            'services.swiftly.api_key'    => 'test-key',
            'services.swiftly.program_id' => 'PROG-TEST',
            'services.swiftly.base_url'   => 'https://api.staging.swiftly.finance',
            'services.swiftly.currency'   => 'BWP',
        ]);

        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['reference' => 'SW-REF-1', 'status' => 'created'])),
        ]));
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $resp = (new SwiftlyService($client))->submitInvoice([
            'invoice_id'                 => 'TEST-1',
            'supplier_id'                => 'SUP-1',
            'amount_minor'               => 5000000,   // BWP 50,000.00
            'due_at'                     => '2026-12-31',
            'auto_request_early_payment' => true,
        ]);

        $this->assertTrue($resp->isSuccess());
        $this->assertSame('SW-REF-1', $resp->get('reference'));

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/api/v1/invoices', (string) $request->getUri());
        $this->assertStringContainsString('ApiKey test-key', $request->getHeaderLine('Authorization'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertTrue($body['auto_request_early_payment']);
        $this->assertSame('PROG-TEST', $body['program_id']);
        $this->assertSame(5000000, $body['invoice_amount']);
        $this->assertSame('SUP-1', $body['supplier_id']);
    }

    public function test_submit_invoice_short_circuits_when_disabled(): void
    {
        config([
            'services.swiftly.enabled'    => false,
            'services.swiftly.api_key'    => 'test-key',
            'services.swiftly.program_id' => 'PROG-TEST',
        ]);

        $resp = (new SwiftlyService())->submitInvoice([
            'invoice_id'   => 'TEST-2',
            'supplier_id'  => 'SUP-1',
            'amount_minor' => 100,
            'due_at'       => '2026-12-31',
        ]);

        $this->assertFalse($resp->isSuccess());
        $this->assertStringContainsString('disabled', strtolower((string) $resp->error));
    }
}
