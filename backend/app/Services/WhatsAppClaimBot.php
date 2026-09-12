<?php

/**
 * SQL migration for whatsapp_conversations table:
 *
 * CREATE TABLE whatsapp_conversations (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     phone VARCHAR(20) NOT NULL,
 *     flow VARCHAR(30) NOT NULL,
 *     step VARCHAR(30) NOT NULL DEFAULT 'start',
 *     data JSON NULL,
 *     policy_id INT NULL,
 *     customer_id INT NULL,
 *     expires_at TIMESTAMP NULL,
 *     created_at TIMESTAMP NULL,
 *     updated_at TIMESTAMP NULL,
 *     INDEX idx_phone_flow (phone, flow),
 *     INDEX idx_expires (expires_at)
 * ) ENGINE=InnoDB;
 */

namespace AlphaDirect\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppClaimBot
{
    /**
     * Conversation expiry in minutes.
     */
    const EXPIRY_MINUTES = 30;

    /**
     * Claim type mapping: user input number => claim_type value stored in claims table.
     */
    const CLAIM_TYPES = [
        '1' => 'Glass',
        '2' => 'Accident',
        '3' => 'Theft',
        '4' => 'Fire',
        '5' => 'Other',
    ];

    /**
     * Handle an incoming WhatsApp message and return the bot reply text.
     *
     * @param  string      $phone    Sender phone number
     * @param  string      $message  Message body text
     * @param  string|null $mediaUrl URL of attached photo/image (if any)
     * @return string
     */
    public function handleMessage(string $phone, string $message, ?string $mediaUrl = null): string
    {
        $message = trim($message);

        // Check if user is initiating a new claim flow
        if ($this->isClaimTrigger($message)) {
            $this->expireConversation($phone, 'claim');
            $this->createConversation($phone, 'claim', 'verify_policy');

            return "Welcome! Please enter your policy number (e.g. MIS2025123456)";
        }

        // Look for an active conversation
        $conversation = $this->getActiveConversation($phone, 'claim');

        if (!$conversation) {
            // No active claim conversation — ignore or let other handlers deal with it
            return '';
        }

        $step = $conversation->step;
        $data = json_decode($conversation->data, true) ?: [];

        switch ($step) {
            case 'verify_policy':
                return $this->handleVerifyPolicy($conversation, $message, $data);

            case 'claim_type':
                return $this->handleClaimType($conversation, $message, $data);

            case 'description':
                return $this->handleDescription($conversation, $message, $data);

            case 'photo':
                return $this->handlePhoto($conversation, $message, $mediaUrl, $data);

            default:
                return '';
        }
    }

    /**
     * Step: verify_policy — Customer sends a policy number.
     */
    private function handleVerifyPolicy(object $conversation, string $message, array $data): string
    {
        $policyNumber = strtoupper(trim($message));

        $policy = DB::table('policies')
            ->where('policyNumber', $policyNumber)
            ->where('status', 1)
            ->first(['id', 'customer_id', 'policyNumber']);

        if (!$policy) {
            return "Policy not found. Please check and try again.";
        }

        $customer = DB::table('customer')
            ->where('id', $policy->customer_id)
            ->first(['id', 'firstName', 'lastName']);

        $customerName = $customer
            ? ucwords(strtolower($customer->firstName)) . ' ' . ucwords(strtolower($customer->lastName))
            : 'Customer';

        $data['policy_number'] = $policy->policyNumber;
        $data['customer_name'] = $customerName;

        $this->updateConversation($conversation->id, [
            'step'        => 'claim_type',
            'policy_id'   => $policy->id,
            'customer_id' => $policy->customer_id,
            'data'        => json_encode($data),
        ]);

        return "Policy {$policy->policyNumber} found for {$customerName}. What type of claim? Reply with a number:\n"
            . "1. Glass\n"
            . "2. Accident\n"
            . "3. Theft\n"
            . "4. Fire\n"
            . "5. Other";
    }

    /**
     * Step: claim_type — Customer replies 1-5.
     */
    private function handleClaimType(object $conversation, string $message, array $data): string
    {
        $choice = trim($message);

        if (!isset(self::CLAIM_TYPES[$choice])) {
            return "Invalid choice. Please reply with a number:\n"
                . "1. Glass\n"
                . "2. Accident\n"
                . "3. Theft\n"
                . "4. Fire\n"
                . "5. Other";
        }

        $data['claim_type'] = self::CLAIM_TYPES[$choice];

        $this->updateConversation($conversation->id, [
            'step' => 'description',
            'data' => json_encode($data),
        ]);

        return "Please describe what happened (in a few sentences)";
    }

    /**
     * Step: description — Customer sends a text description.
     */
    private function handleDescription(object $conversation, string $message, array $data): string
    {
        $data['description'] = $message;

        $this->updateConversation($conversation->id, [
            'step' => 'photo',
            'data' => json_encode($data),
        ]);

        return "Would you like to upload a photo? Send a photo now, or type SKIP to continue";
    }

    /**
     * Step: photo — Customer sends a photo or types SKIP.
     */
    private function handlePhoto(object $conversation, string $message, ?string $mediaUrl, array $data): string
    {
        if ($mediaUrl) {
            $data['photo_url'] = $mediaUrl;
        }
        // If no media and user did not type SKIP, still accept as skip (graceful)
        // but only proceed if media or SKIP
        if (!$mediaUrl && strtoupper(trim($message)) !== 'SKIP') {
            return "Please send a photo or type SKIP to continue without a photo.";
        }

        // File the claim
        $claimResult = $this->createClaim($conversation, $data);

        // Mark conversation as done
        $this->updateConversation($conversation->id, [
            'step' => 'done',
            'data' => json_encode($data),
        ]);

        $policyNumber = $data['policy_number'] ?? '';
        $claimType    = $data['claim_type'] ?? '';

        return "Your claim has been filed!\n\n"
            . "Claim #: {$claimResult['claim_number']}\n"
            . "Policy: {$policyNumber}\n"
            . "Type: {$claimType}\n"
            . "Status: Pending\n\n"
            . "You'll receive updates via SMS. Thank you!";
    }

    /**
     * Insert a new claim into the claims table.
     *
     * @param  object $conversation
     * @param  array  $data
     * @return array  ['claim_number' => string, 'claim_id' => int]
     */
    private function createClaim(object $conversation, array $data): array
    {
        $write = DB::connection('mysql_write');

        // Generate claim number: G + year + zero-padded (latest id + 1)
        $latest = DB::table('claims')->orderBy('id', 'DESC')->first(['id']);
        $nextId = $latest ? $latest->id + 1 : 1;
        $claimNumber = 'G' . Carbon::now()->year . str_pad($nextId, 6, '0', STR_PAD_LEFT);

        $now = Carbon::now();

        $photoUrl = $data['photo_url'] ?? null;

        $insertData = [
            'claim_number' => $claimNumber,
            'customer_id'  => $conversation->customer_id,
            'agent_id'     => null,
            'policy_id'    => $conversation->policy_id,
            'claim_type'   => $data['claim_type'],
            'note'         => $data['description'] ?? '',
            'status'       => 'Pending',
            'document_1'   => $photoUrl,
            'created_by'   => 'whatsapp_bot',
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        $claimId = $write->table('claims')->insertGetId($insertData);

        Log::info('WhatsAppClaimBot: Claim created', [
            'claim_id'     => $claimId,
            'claim_number' => $claimNumber,
            'policy_id'    => $conversation->policy_id,
            'customer_id'  => $conversation->customer_id,
            'phone'        => $conversation->phone,
        ]);

        return [
            'claim_number' => $claimNumber,
            'claim_id'     => $claimId,
        ];
    }

    // ─── Conversation state management ─────────────────────────────────

    /**
     * Check if the message is a trigger to start a claim flow.
     */
    private function isClaimTrigger(string $message): bool
    {
        $normalised = strtolower(trim($message));
        return in_array($normalised, ['claim', 'file claim', 'file a claim', 'new claim']);
    }

    /**
     * Get the active (non-expired) conversation for this phone + flow.
     */
    private function getActiveConversation(string $phone, string $flow): ?object
    {
        $conversation = DB::table('whatsapp_conversations')
            ->where('phone', $phone)
            ->where('flow', $flow)
            ->where('step', '!=', 'done')
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('id', 'DESC')
            ->first();

        return $conversation;
    }

    /**
     * Create a new conversation row.
     */
    private function createConversation(string $phone, string $flow, string $step): int
    {
        $now = Carbon::now();

        return DB::connection('mysql_write')
            ->table('whatsapp_conversations')
            ->insertGetId([
                'phone'      => $phone,
                'flow'       => $flow,
                'step'       => $step,
                'data'       => json_encode([]),
                'expires_at' => $now->copy()->addMinutes(self::EXPIRY_MINUTES),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Update conversation fields.
     */
    private function updateConversation(int $id, array $fields): void
    {
        $fields['updated_at'] = Carbon::now();
        $fields['expires_at'] = Carbon::now()->addMinutes(self::EXPIRY_MINUTES);

        DB::connection('mysql_write')
            ->table('whatsapp_conversations')
            ->where('id', $id)
            ->update($fields);
    }

    /**
     * Expire/close any open conversation for this phone + flow.
     */
    private function expireConversation(string $phone, string $flow): void
    {
        DB::connection('mysql_write')
            ->table('whatsapp_conversations')
            ->where('phone', $phone)
            ->where('flow', $flow)
            ->where('step', '!=', 'done')
            ->update([
                'step'       => 'done',
                'updated_at' => Carbon::now(),
            ]);
    }
}
