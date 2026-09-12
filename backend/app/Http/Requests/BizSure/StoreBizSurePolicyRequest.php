<?php

namespace AlphaDirect\Http\Requests\BizSure;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation contract for the V1 BizSure create-policy endpoint
 * (POST /api/v1/bizsure/... — routed elsewhere).
 *
 * Ports the entry-point validation the legacy
 * CommonApis/BizSurePolicyController::createPolicy did inline
 * (BizSurePolicyController.php:51-95) into a reusable FormRequest so the
 * controller stays focused on delegation to BizSureOrchestrator.
 *
 * Field names are kept EXACTLY as the orchestrator + V1 read them — the
 * top-level personal/business fields are snake_case (firstname, lastname,
 * omang, business_structure). Do NOT rename to camelCase; the orchestrator
 * pulls them by these exact keys (BizSureOrchestrator::resolveOrCreateCustomer
 * reads 'firstname'/'lastname', createPolicyShell reads 'business_structure').
 */
class StoreBizSurePolicyRequest extends FormRequest
{
    /**
     * The route is ability-gated (VerifyApiKey / ability middleware owned by
     * the routing worker), so authorization here is intentionally a no-op —
     * we don't double-handle 401 vs 422 semantics.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lower-case the two discriminator fields before validation so the `in:`
     * rules below are effectively case-insensitive. Mirrors V1, which did
     * strtolower() on leadSource (BizSurePolicyController.php:54) and
     * business_structure (line 79) before comparing.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('leadSource')) {
            $normalized['leadSource'] = strtolower((string) $this->input('leadSource'));
        }

        if ($this->has('business_structure')) {
            $normalized['business_structure'] = strtolower((string) $this->input('business_structure'));
        }

        if (! empty($normalized)) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        // `min:1` is only meaningful when a company MUST carry at least one
        // director. A sole_proprietor may legitimately send an empty (or
        // absent) directors[] — V1 never inspected directors unless
        // business_structure=='company' (BizSurePolicyController.php:87-95).
        // Gating min:1 preserves that behaviour instead of rejecting a valid
        // sole-proprietor payload that happens to include an empty array.
        $directorRules = ['required_if:business_structure,company', 'array'];
        if (strtolower((string) $this->input('business_structure')) === 'company') {
            $directorRules[] = 'min:1';
        }

        return [
            // leadSource gate — must be exactly 'bizsure' (already lower-cased
            // in prepareForValidation). V1: BizSurePolicyController.php:52-59.
            'leadSource'         => ['required', 'in:bizsure'],

            // Required entry fields — V1: BizSurePolicyController.php:61-76.
            'product'            => ['required'],
            'firstname'          => ['required', 'string'],
            'lastname'           => ['required', 'string'],
            'phone'              => ['required', 'string'],
            'email'              => ['required', 'email'],
            'omang'              => ['required', 'string'],

            // business_structure gates the directors[] expectation.
            // V1: BizSurePolicyController.php:79-95.
            'business_structure' => ['required', 'in:sole_proprietor,company'],
            'directors'          => $directorRules,
        ];
    }

    /**
     * Messages for the key gates — kept verbatim to V1's inline error strings
     * so the partner sees the same guidance it did on the legacy endpoint.
     */
    public function messages(): array
    {
        return [
            'leadSource.required'         => "BizSure createPolicy requires leadSource='bizsure'.",
            'leadSource.in'               => "BizSure createPolicy requires leadSource='bizsure'.",
            'product.required'            => "Missing required field 'product'.",
            'firstname.required'          => "Missing required field 'firstname'.",
            'lastname.required'           => "Missing required field 'lastname'.",
            'phone.required'              => "Missing required field 'phone'.",
            'email.required'              => "Missing required field 'email'.",
            'omang.required'              => "Missing required field 'omang'.",
            'business_structure.required' => "business_structure must be 'sole_proprietor' or 'company'.",
            'business_structure.in'       => "business_structure must be 'sole_proprietor' or 'company'.",
            'directors.required_if'       => "directors[] is required for business_structure='company'.",
            'directors.min'               => "directors[] is required for business_structure='company'.",
        ];
    }
}
