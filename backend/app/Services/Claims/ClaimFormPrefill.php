<?php

namespace AlphaDirect\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ClaimFormPrefill — everything Graphite already knows about a claim, shaped for
 * the claim form we send the claimant.
 *
 * The rule this exists to serve (CFO, 8-Aug-2026): the system fills in
 * everything it already holds; the claimant is asked only for what only they can
 * know. So a field appearing here means the claimant should NEVER be typing it.
 *
 * Claimant PII lives in this array. It is rendered server-side into a PDF and
 * into an HTML form and never leaves Graphite — it must not be logged, exported,
 * or sent to any external model (AD-POL-AI-GOV-001).
 */
class ClaimFormPrefill
{
    /**
     * Build the pre-fill set for a claim. Returns [] when the claim is gone.
     *
     * Reads are defensive: several claim detail tables are optional depending on
     * the product, so every lookup tolerates absence rather than assuming a
     * shape. The claim itself is read from `claims` only — see below.
     */
    public function build(int $claimId): array
    {
        // `claims` ONLY, matching ClaimFormDispatchService::findClaim. The two
        // claim tables have overlapping id ranges, so resolving a bare id across
        // both can name a different claim than the caller meant — and this data
        // is emailed to a customer. One table, or a clean not-found.
        $claim = DB::table('claims')->where('id', $claimId)->first();
        if (!$claim) {
            return [];
        }

        $customer = $this->customer($claim);
        $policy   = $this->policy($claim);
        $vehicle  = $this->vehicle($claimId, $policy);

        return [
            'claim' => [
                'number'         => $claim->claim_number ?? null,
                'type'           => $claim->claim_type ?? null,
                'date_of_loss'   => $this->date($claim->date_of_loss ?? null),
                'time_of_loss'   => $claim->time_of_loss ?? null,
                'reported_on'    => $this->date($claim->registered_claim ?? ($claim->created_at ?? null)),
                'description'    => $claim->description_of_loss ?? null,
                'location'       => $claim->location ?? null,
                'handler'        => $this->handler($claim),
            ],
            // Only the three fields customer() actually returns. An earlier
            // version also read the postal address and the identity number here,
            // which customer() had stopped returning once the guessed column
            // names were removed — every build() then threw and the whole feature
            // was dead. Neither is rendered on any form, and leaving identity
            // documents out of a payload we email is the data-minimal answer as
            // well as the working one.
            'insured' => [
                'name'  => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
            ],
            'policy' => [
                'number'       => $policy['number'],
                'product'      => $policy['product'],
                'inception'    => $this->date($policy['inception']),
                'expiry'       => $this->date($policy['expiry']),
                'sum_insured'  => $policy['sum_insured'],
                'excess'       => $policy['excess'],
            ],
            'vehicle' => $vehicle,
            // Fields the claimant must complete — the system cannot know these.
            // Rendered as empty inputs; date and time of loss are compulsory
            // because incident_date is blank on every historic claim, which is
            // exactly why duplicate detection cannot work today.
            'claimant_to_complete' => $this->questionsFor($claim->claim_type ?? ''),
        ];
    }

    /**
     * Resolve the claimant from the `customer` table (soft, may be absent).
     *
     * Column names verified against the live detail serialiser
     * (Api\V1\ClaimsController:1191): firstName / lastName / email / cellphone.
     * A company customer's display name is its company name; an individual's is
     * first + last. Nothing is guessed — a field we cannot name is left blank
     * for the claimant to complete rather than printed wrong.
     */
    private function customer($claim): array
    {
        $blank = ['name' => null, 'email' => null, 'phone' => null];
        if (empty($claim->customer_id)) {
            return $blank;
        }

        try {
            $c = DB::table('customer')->where('id', $claim->customer_id)->first();
        } catch (\Throwable $e) {
            Log::warning('[ClaimFormPrefill] customer read failed', ['claim' => $claim->id ?? null]);
            return $blank;
        }
        if (!$c) {
            return $blank;
        }

        $company    = trim((string) $this->pick($c, ['company_name', 'companyName']));
        $individual = trim(implode(' ', array_filter([$c->firstName ?? null, $c->lastName ?? null])));

        return [
            'name'  => $company !== '' ? $company : ($individual !== '' ? $individual : null),
            'email' => $c->email ?? null,
            'phone' => $c->cellphone ?? null,
        ];
    }

    /**
     * First non-empty value among candidate column names on a DB row.
     *
     * This codebase carries two generations of schema side by side (`claims` +
     * `new_claims`, `policyNumber` + `policy_number`, camel and snake in the
     * same table), so a single hard-coded column name is how a form ends up
     * blank in production. Candidates are tried in order and anything unknown
     * degrades to null — never to a wrong value on a customer-facing document.
     */
    private function pick($row, array $candidates)
    {
        foreach ($candidates as $key) {
            if (is_object($row) && isset($row->$key) && $row->$key !== '' && $row->$key !== null) {
                return $row->$key;
            }
        }
        return null;
    }

    /** Policy header + the current term. */
    private function policy($claim): array
    {
        $out = ['id' => null, 'number' => null, 'product' => null, 'inception' => null,
                'expiry' => null, 'sum_insured' => null, 'excess' => null];

        if (empty($claim->policy_id)) {
            return $out;
        }

        try {
            $p = DB::table('policies')->where('id', $claim->policy_id)->first();
        } catch (\Throwable $e) {
            return $out;
        }
        if (!$p) {
            return $out;
        }

        $out['id'] = $p->id;
        // The live serialiser reads `policyNumber` (camel) — see
        // Api\V1\ClaimsController:1171. Snake is kept as a fallback only.
        $out['number'] = $this->pick($p, ['policyNumber', 'policy_number']);

        try {
            if (!empty($p->product_id)) {
                $out['product'] = DB::table('products')->where('id', $p->product_id)->value('name');
            }
        } catch (\Throwable $e) {
            // product name is cosmetic on the form — never fail the whole build
        }

        // The policy HEADER lies about premium and term — proved on claim
        // G2026004801, where the header said P139.35 and the real premium was
        // P942.02. The live term lives in policy_actions, so read the term from
        // there and never from the header.
        try {
            $action = DB::table('policy_actions')
                ->where('policy_id', $p->id)
                ->orderByDesc('id')
                ->first();
            if ($action) {
                $out['inception']   = $this->pick($action, ['start_date', 'from_date', 'startDate', 'inception_date']);
                $out['expiry']      = $this->pick($action, ['end_date', 'to_date', 'endDate', 'expiry_date']);
                $out['sum_insured'] = $this->pick($action, ['sum_insured', 'sumInsured']);
                $out['excess']      = $this->pick($action, ['excess', 'excess_amount']);
            }
        } catch (\Throwable $e) {
            // Leave the term blank rather than print a stale one on a form the
            // claimant will treat as authoritative.
        }

        return $out;
    }

    /**
     * Vehicle detail for motor and glass forms; blank for non-motor claims.
     *
     * SCHEMA NOTE, learned the hard way — `claim_vehicle` is NOT a vehicle
     * table. It is the GLASS claim detail row (name_of_insured, type_of_glass,
     * broken_plate_size, damage_cause, damage_extent, replacement_estimate,
     * situated_glass_address, four damage images) plus two keys: claim_id and
     * **vehicle_id**. The vehicle's own attributes live on `vehicles`, reached
     * through that vehicle_id — columns are camel-case there (`vehiclePlate`,
     * `vehicleRegistration`, `chassisNo`) alongside plain make/model/year.
     *
     * Verified from the live read path (Api\V1\ClaimsController:787) and the
     * fields the existing claim blades render. Anything still unnamed comes
     * back null and is printed as a blank for the claimant to complete — never
     * as a guess on a document they will treat as authoritative.
     */
    private function vehicle(int $claimId, array $policy): array
    {
        $blank = ['registration' => null, 'make' => null, 'model' => null,
                  'year' => null, 'chassis' => null, 'glass' => null];

        try {
            $cv = DB::table('claim_vehicle')->where('claim_id', $claimId)->first();
        } catch (\Throwable $e) {
            return $blank;
        }
        if (!$cv) {
            return $blank;
        }

        // Glass claims: the detail the official Glass form asks for is already
        // captured here, so the claimant should not be asked for it again.
        $glass = array_filter([
            'type_of_glass'    => $cv->type_of_glass ?? null,
            'plate_size'       => $cv->broken_plate_size ?? null,
            'damage_cause'     => $cv->damage_cause ?? null,
            'damage_extent'    => $cv->damage_extent ?? null,
            'date_of_damage'   => $this->date($cv->date_of_damage ?? null),
            'vehicle_situated' => $cv->situated_glass_address ?? null,
            'estimate'         => $cv->replacement_estimate ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $out = $blank;
        $out['glass'] = $glass ?: null;

        if (empty($cv->vehicle_id)) {
            return $out;
        }

        try {
            $v = DB::table('vehicles')->where('id', $cv->vehicle_id)->first();
        } catch (\Throwable $e) {
            return $out;
        }
        if (!$v) {
            return $out;
        }

        $out['registration'] = $this->pick($v, ['vehicleRegistration', 'vehiclePlate']);
        $out['make']         = $this->pick($v, ['make']);
        $out['model']        = $this->pick($v, ['model']);
        $out['year']         = $this->pick($v, ['year']);
        $out['chassis']      = $this->pick($v, ['chassisNo']);

        return $out;
    }

    private function handler($claim): ?string
    {
        $id = $claim->claim_allocated_to ?? null;
        if (!$id) {
            return null;
        }
        try {
            $u = DB::table('users')->where('id', $id)->first(['firstName', 'lastName']);
            return $u ? trim($u->firstName . ' ' . $u->lastName) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function date($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * The questions only the claimant can answer, by form template. Kept here
     * (not in a template) so the SAME list drives the PDF we attach and the
     * online form — one source, so the two can never drift apart.
     *
     * `req` marks compulsory. Date and time of loss are compulsory on every
     * form: they are blank on all 4,102 historic claims, which is the single
     * reason duplicate-claim detection cannot work, and the fix belongs at the
     * point of capture.
     */
    private function questionsFor(string $claimType): array
    {
        $common = [
            ['key' => 'loss_date',        'label' => 'Date of the incident',                    'type' => 'date', 'req' => true],
            ['key' => 'loss_time',        'label' => 'Time of the incident (as close as you can)', 'type' => 'time', 'req' => true],
            ['key' => 'loss_place',       'label' => 'Where it happened',                       'type' => 'text', 'req' => true],
            ['key' => 'loss_description', 'label' => 'In your own words, what happened',        'type' => 'textarea', 'req' => true],
            ['key' => 'reported_police',  'label' => 'Was it reported to the police?',          'type' => 'yesno', 'req' => true],
            ['key' => 'police_ref',       'label' => 'Police reference number (if reported)',    'type' => 'text', 'req' => false],
        ];

        $motor = [
            ['key' => 'driver_name',      'label' => 'Who was driving',                          'type' => 'text', 'req' => true],
            ['key' => 'driver_licence',   'label' => "Driver's licence number",                  'type' => 'text', 'req' => true],
            ['key' => 'driver_permission','label' => 'Were they driving with your permission?',   'type' => 'yesno', 'req' => true],
            ['key' => 'damage_description','label' => 'What is damaged on your vehicle',          'type' => 'textarea', 'req' => true],
            ['key' => 'vehicle_location', 'label' => 'Where can the vehicle be inspected',        'type' => 'text', 'req' => true],
            ['key' => 'third_party',      'label' => 'Any other vehicle or person involved? Give their details', 'type' => 'textarea', 'req' => false],
            ['key' => 'injuries',         'label' => 'Was anybody injured? Who, and how',         'type' => 'textarea', 'req' => false],
        ];

        $glass = [
            ['key' => 'glass_item',       'label' => 'Which glass is damaged (windscreen, side, rear)', 'type' => 'text', 'req' => true],
            ['key' => 'glass_cause',      'label' => 'What caused the damage',                    'type' => 'text', 'req' => true],
            ['key' => 'fitter_preference','label' => 'Preferred fitter, if you have one',         'type' => 'text', 'req' => false],
        ];

        $type = strtolower(trim($claimType));

        if (str_contains($type, 'glass') || str_contains($type, 'windscreen')) {
            return array_merge($common, $glass);
        }
        if (str_contains($type, 'accident') || str_contains($type, 'motor') || str_contains($type, 'vehicle')) {
            return array_merge($common, $motor);
        }

        return array_merge($common, [
            ['key' => 'items_lost',     'label' => 'What was lost or damaged, and what it is worth', 'type' => 'textarea', 'req' => true],
            ['key' => 'ownership_proof','label' => 'Do you have proof of ownership or purchase?',    'type' => 'yesno', 'req' => false],
        ]);
    }
}
