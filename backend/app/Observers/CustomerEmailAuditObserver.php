<?php

namespace AlphaDirect\Observers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * CustomerEmailAuditObserver
 *
 * Captures every email change on the customer model into
 * customer_email_history. Populated automatically wherever Eloquent is
 * used. Direct DB::update() writes bypass this (intentionally — bulk
 * ETL shouldn't spam the audit log), so any future bulk-import path
 * should insert into customer_email_history manually.
 *
 * Registered in AppServiceProvider::boot.
 */
class CustomerEmailAuditObserver
{
    public function updating($customer): void
    {
        try {
            // Only log when the 'email' column is actually being changed
            if (!$customer->isDirty('email')) return;

            $old = $customer->getOriginal('email');
            $new = $customer->email;
            if ((string) $old === (string) $new) return;

            DB::table('customer_email_history')->insert([
                'customer_id'        => $customer->id,
                'old_email'          => $old ? substr((string) $old, 0, 190) : null,
                'new_email'          => $new ? substr((string) $new, 0, 190) : null,
                'changed_by_user_id' => optional(auth()->user())->id,
                'source'             => $this->detectSource(),
                'ip'                 => $this->ip(),
                'user_agent'         => $this->userAgent(),
                'otp_verified'       => false,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit failures must NEVER block the save
            Log::warning('CustomerEmailAuditObserver: failed to write history', [
                'customer_id' => $customer->id ?? null,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function detectSource(): string
    {
        if (app()->runningInConsole()) return 'cli';
        try {
            return RequestFacade::is('api/*') ? 'api' : 'web';
        } catch (\Throwable $e) {
            return 'system';
        }
    }

    private function ip(): ?string
    {
        try { return RequestFacade::ip(); } catch (\Throwable $e) { return null; }
    }

    private function userAgent(): ?string
    {
        try {
            $ua = RequestFacade::userAgent();
            return $ua ? substr((string) $ua, 0, 400) : null;
        } catch (\Throwable $e) { return null; }
    }
}
