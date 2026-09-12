<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Admin\AiConfigController;
use AlphaDirect\Services\OcrExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrController extends Controller
{
    /**
     * Parse raw OCR text into structured fields + run fraud checks.
     *
     * POST /api/v1/ocr/parse
     * Body: { "text": "...", "document_type": "id|vehicle|property|passport|insurance|invoice|any" }
     */
    public function parse(Request $request): JsonResponse
    {
        set_time_limit(120);

        $request->validate([
            'text'          => 'required|string|min:10|max:10000',
            'document_type' => 'required|string',
        ]);

        $text    = $request->input('text');
        $docType = $request->input('document_type');

        try {
            // Step 1: Extract fields
            $extractPrompt = $this->buildExtractionPrompt($text, $docType);
            $fields = $this->callAi($extractPrompt);

            // Step 2: Fraud / tamper checks
            $fraudPrompt = $this->buildFraudCheckPrompt($text, $docType);
            $fraudResult = $this->callAi($fraudPrompt);

            // Step 3: Cross-reference with existing DB records
            $dbFlags = $this->crossReferenceDb($fields, $docType);

            return response()->json([
                'data'   => $fields,
                'fraud'  => $fraudResult,
                'db_flags' => $dbFlags,
            ]);
        } catch (\Exception $e) {
            Log::error('OCR parse failed: ' . $e->getMessage());
            return response()->json([
                'error'  => 'Failed to extract fields from document text.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Vision-LLM extraction — sends the image directly to Claude/Llama-Scout
     * (skips Tesseract). Used by the customer-facing start.alphadirect.co.bw
     * for high-accuracy field extraction from Omang, driving licence,
     * vehicle valuation, employment letter and proof of residence.
     *
     * POST /api/v1/ocr/extract
     * Multipart: file (required, image), document_type (optional, see
     *            OcrExtractor::TYPES for the list — null = auto-detect)
     *
     * Returns:
     *   {
     *     type: 'omang_id' | 'driver_license' | ...,
     *     fields: { ...schema-shaped extraction... },
     *     confidence: 0.0..1.0,         // fraction of schema fields populated
     *     ai_provider: 'anthropic' | 'groq',
     *     ai_model: '...',
     *   }
     */
    public function extract(Request $request, OcrExtractor $extractor): JsonResponse
    {
        set_time_limit(120);

        $request->validate([
            'file'          => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:15360', // 15MB
            'document_type' => 'nullable|string|in:' . implode(',', OcrExtractor::TYPES),
        ]);

        $file    = $request->file('file');
        $docType = $request->input('document_type'); // nullable → auto-detect

        try {
            // PDFs: render the first page to PNG via Imagick if available;
            // otherwise pass the raw PDF (Anthropic accepts PDF too via
            // their `document` block — wired in the next iteration).
            $path = $file->getRealPath();

            $result = $extractor->extract($path, $docType);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('ocr.extract failed', [
                'message' => $e->getMessage(),
                'file'    => $file->getClientOriginalName(),
            ]);
            return response()->json([
                'error'  => 'Extraction failed',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Upload a file (PDF, DOCX, XLSX, image), extract text, parse with AI.
     *
     * POST /api/v1/ocr/upload
     * Multipart: file (required), document_type (optional, default "any")
     */
    public function upload(Request $request): JsonResponse
    {
        set_time_limit(180);

        $request->validate([
            'file'          => 'required|file|max:20480', // 20MB
            'document_type' => 'nullable|string',
        ]);

        $file           = $request->file('file');
        $docType        = $request->input('document_type', 'any');
        $productContext = $request->input('product_context', '');
        $entityContext  = $request->input('entity_context', '');
        $ext            = strtolower($file->getClientOriginalExtension());
        $mime           = $file->getMimeType();

        try {
            $text = $this->extractTextFromFile($file, $ext, $mime);

            if (strlen(trim($text)) < 10) {
                return response()->json([
                    'data'          => [],
                    'fraud'         => ['risk_level' => 'low', 'risk_score' => 0, 'flags' => [], 'summary' => 'Insufficient text to analyze'],
                    'db_flags'      => [],
                    'detected_type' => 'unknown',
                    'text_length'   => 0,
                    'file_name'     => $file->getClientOriginalName(),
                    'skipped'       => true,
                    'skip_reason'   => 'Could not extract readable text from this file.',
                ]);
            }

            // Auto-detect document type from content if "any"
            if ($docType === 'any') {
                $docType = $this->detectDocumentType($text);
            }

            $extractPrompt = $this->buildExtractionPrompt($text, $docType, $productContext, $entityContext);
            $fields = $this->callAi($extractPrompt);

            $fraudPrompt = $this->buildFraudCheckPrompt($text, $docType);
            $fraudResult = $this->callAi($fraudPrompt);

            $dbFlags = $this->crossReferenceDb($fields, $docType);

            return response()->json([
                'data'          => $fields,
                'fraud'         => $fraudResult,
                'db_flags'      => $dbFlags,
                'detected_type' => $docType,
                'text_length'   => strlen($text),
                'file_name'     => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            Log::error('OCR upload failed: ' . $e->getMessage());
            return response()->json([
                'error'  => config('app.debug')
                    ? 'Failed to process file: ' . $e->getMessage()
                    : 'Failed to process the uploaded file.',
                'detail' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Extract text from any uploaded file.
     */
    private function extractTextFromFile($file, string $ext, string $mime): string
    {
        // PDF
        if ($ext === 'pdf' || $mime === 'application/pdf') {
            // Try smalot/pdfparser first
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($file->getRealPath());
                return $pdf->getText();
            }
            // Fallback: pdftotext command
            $output = [];
            exec('pdftotext ' . escapeshellarg($file->getRealPath()) . ' -', $output);
            return implode("\n", $output);
        }

        // DOCX (ZIP containing XML)
        if ($ext === 'docx' || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $zip = new \ZipArchive();
            if ($zip->open($file->getRealPath()) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    // Strip XML tags, keep text
                    $text = strip_tags(str_replace(['<w:p ', '<w:p>', '</w:p>'], ["\n<w:p ", "\n", "\n"], $xml));
                    return trim(preg_replace('/\s+/', ' ', str_replace("\n", "\n", $text)));
                }
            }
            return '';
        }

        // Excel (XLSX — read as CSV-like text)
        if (in_array($ext, ['xlsx', 'xls', 'csv']) || str_contains($mime, 'spreadsheet') || str_contains($mime, 'csv')) {
            if ($ext === 'csv') {
                return file_get_contents($file->getRealPath());
            }
            // XLSX: extract shared strings from ZIP
            $zip = new \ZipArchive();
            if ($zip->open($file->getRealPath()) === true) {
                $rows = [];
                // Read shared strings
                $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                $strings = [];
                if ($ssXml) {
                    preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $ssXml, $m);
                    $strings = $m[1] ?? [];
                }
                // Read sheet1
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheetXml) {
                    preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches);
                    foreach ($rowMatches[1] ?? [] as $rowXml) {
                        preg_match_all('/<c[^>]*t="s"[^>]*><v>(\d+)<\/v>|<c[^>]*><v>([^<]*)<\/v>/s', $rowXml, $cellMatches);
                        $cells = [];
                        for ($i = 0; $i < count($cellMatches[0]); $i++) {
                            if ($cellMatches[1][$i] !== '') {
                                $idx = (int) $cellMatches[1][$i];
                                $cells[] = $strings[$idx] ?? '';
                            } else {
                                $cells[] = $cellMatches[2][$i];
                            }
                        }
                        if (!empty(array_filter($cells))) $rows[] = implode(' | ', $cells);
                    }
                }
                $zip->close();
                return implode("\n", array_slice($rows, 0, 200)); // Max 200 rows
            }
            return '';
        }

        // DOC (old Word format — extract raw text)
        if ($ext === 'doc') {
            $content = file_get_contents($file->getRealPath());
            // Strip binary, keep printable text
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $content);
            return trim(preg_replace('/\s+/', ' ', $text));
        }

        // Images — return empty (frontend does OCR via tesseract.js, or we note it)
        if (str_starts_with($mime, 'image/')) {
            // Try tesseract CLI if available
            $tmpOutput = tempnam(sys_get_temp_dir(), 'ocr_');
            exec('tesseract ' . escapeshellarg($file->getRealPath()) . ' ' . escapeshellarg($tmpOutput) . ' 2>/dev/null');
            if (file_exists($tmpOutput . '.txt')) {
                $text = file_get_contents($tmpOutput . '.txt');
                @unlink($tmpOutput . '.txt');
                @unlink($tmpOutput);
                return $text;
            }
            @unlink($tmpOutput);
            return '';
        }

        // Plain text
        if (str_starts_with($mime, 'text/')) {
            return file_get_contents($file->getRealPath());
        }

        return '';
    }

    /**
     * Auto-detect document type from extracted text content.
     */
    private function detectDocumentType(string $text): string
    {
        $lower = strtolower($text);
        if (str_contains($lower, 'omang') || str_contains($lower, 'national id') || str_contains($lower, 'identity card')) return 'id';
        if (str_contains($lower, 'passport') || str_contains($lower, 'travel document')) return 'passport';
        if (str_contains($lower, 'vehicle') || str_contains($lower, 'registration') || str_contains($lower, 'engine') || str_contains($lower, 'chassis')) return 'vehicle';
        if (str_contains($lower, 'title deed') || str_contains($lower, 'lease') || str_contains($lower, 'property')) return 'property';
        if (str_contains($lower, 'policy') || str_contains($lower, 'premium') || str_contains($lower, 'insured') || str_contains($lower, 'sum assured')) return 'insurance';
        if (str_contains($lower, 'invoice') || str_contains($lower, 'amount due') || str_contains($lower, 'total')) return 'invoice';
        if (str_contains($lower, 'first information report') || str_contains($lower, 'fir no') || str_contains($lower, 'police station') || str_contains($lower, 'complainant') || str_contains($lower, 'f.i.r')) return 'fir';
        if (str_contains($lower, 'medical report') || str_contains($lower, 'diagnosis') || str_contains($lower, 'admission') || str_contains($lower, 'discharge')) return 'medical_report';
        if (str_contains($lower, 'repair estimate') || str_contains($lower, 'labour') || str_contains($lower, 'spare parts') || str_contains($lower, 'workshop')) return 'repair_estimate';
        if (str_contains($lower, 'loss adjuster') || str_contains($lower, 'adjuster') || str_contains($lower, 'assessment report')) return 'loss_adjuster_report';
        return 'any';
    }

    /**
     * Get existing documents for a policy (for scanning into new policy).
     *
     * GET /api/v1/ocr/policy-documents/{policyId}
     */
    public function policyDocuments(int $policyId): JsonResponse
    {
        $policy = DB::table('policies')
            ->leftJoin('customer', 'customer.id', '=', 'policies.customer_id')
            ->where('policies.id', $policyId)
            ->first([
                'policies.id', 'policies.policyNumber', 'policies.customer_id',
                'customer.firstName', 'customer.lastName',
            ]);

        if (!$policy) {
            return response()->json(['error' => 'Policy not found'], 404);
        }

        $cdn = rtrim(env('AWS_CLOUDFRONT', ''), '/');
        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');
        $makeCdnUrl = function (?string $path) use ($cdn, $bucket, $region) {
            if (empty($path)) return null;
            $path = str_replace(['\/', ' '], ['/', '%20'], $path);
            if ($cdn) return $cdn . '/' . ltrim($path, '/');
            if ($bucket && $region) return "https://{$bucket}.s3.{$region}.amazonaws.com/" . ltrim($path, '/');
            return $path;
        };

        // KYC documents
        $kyc = DB::table('customer_kyc')
            ->where('customer_id', $policy->customer_id)
            ->first();

        $kycDocs = [];
        if ($kyc) {
            $docFields = [
                'omang'                   => 'Omang ID Front',
                'omangBack'               => 'Omang ID Back',
                'passport'                => 'Passport Front',
                'passport_back'           => 'Passport Back',
                'driving_license'         => 'Driving License Front',
                'driving_license_back'    => 'Driving License Back',
                'proof_residence'         => 'Proof of Residence',
                'proof_income'            => 'Proof of Income',
                'bank_statement_file_path'=> 'Bank Statement',
                'debit_authorization_form'=> 'Debit Authorization',
            ];
            foreach ($docFields as $field => $label) {
                $val = $kyc->{$field} ?? null;
                if ($val) {
                    $kycDocs[] = [
                        'field'    => $field,
                        'label'    => $label,
                        'url'      => $makeCdnUrl($val),
                        'type'     => str_contains($field, 'omang') ? 'id' : (str_contains($field, 'passport') ? 'passport' : 'other'),
                    ];
                }
            }
        }

        // Policy attachments (user uploads — serialized array)
        $attachDocs = [];
        $attachments = DB::table('policy_attachments')->where('policy_id', $policyId)->get();
        foreach ($attachments as $att) {
            $paths = @unserialize($att->attachment, ['allowed_classes' => false]);
            if (!is_array($paths)) $paths = $att->attachment ? [$att->attachment] : [];
            foreach ($paths as $path) {
                $attachDocs[] = [
                    'field' => 'attachment_' . $att->id,
                    'label' => $att->name ?: 'Attachment',
                    'url'   => $makeCdnUrl($path),
                    'type'  => ($att->type === 'Invoice') ? 'invoice' : 'other',
                ];
            }
        }

        // Generated policy documents (schedules, endorsements)
        $policyDocs = [];
        $genDocs = DB::table('policy_documents')->where('policy_id', $policyId)->get();
        foreach ($genDocs as $d) {
            $policyDocs[] = [
                'field' => 'policy_doc_' . $d->id,
                'label' => $d->file_name ?: ($d->is_cancellation_note ? 'Cancellation Note' : 'Policy Schedule'),
                'url'   => $makeCdnUrl($d->doc_path),
                'type'  => 'insurance',
            ];
        }

        return response()->json([
            'policy' => [
                'id'           => $policy->id,
                'policyNumber' => $policy->policyNumber,
                'customer'     => trim(($policy->firstName ?? '') . ' ' . ($policy->lastName ?? '')),
            ],
            'kyc_documents'   => $kycDocs,
            'attachments'     => $attachDocs,
            'policy_documents' => $policyDocs,
        ]);
    }

    // ─── Prompt Builders ─────────────────────────────────────────────────────

    private function buildExtractionPrompt(string $text, string $docType, string $productContext = '', string $entityContext = ''): string
    {
        $fieldsByType = [
            'id' => 'Extract: first_name, middle_name, last_name, gender (Male/Female), dob (YYYY-MM-DD), omang (9-digit Botswana ID), nationality, place_of_birth',
            'passport' => 'Extract: first_name, middle_name, last_name, gender, dob (YYYY-MM-DD), passport (number), nationality, expiry_date (YYYY-MM-DD)',
            'vehicle' => 'Extract: vehicle_plate (reg number like "B 123 ABC"), make, model, year (integer), engine_no, chassis_no, vin_number, color, cubic_capacity (cc integer), owner_name, estimated_value (number)',
            'property' => 'Extract: address_name, physical_address, city, area, year_built (integer), structure_type, const_type (construction type), owner_name',
            'insurance' => 'Extract: policy_number, insurer_name, product_type, first_name, last_name, premium (number), start_date (YYYY-MM-DD), expiry_date (YYYY-MM-DD), sum_insured (number), vehicle_plate, make, model',
            'invoice' => 'Extract: invoice_number, vendor_name, amount (number), date (YYYY-MM-DD), description, vehicle_plate, make, model, year, vin_number, items (array of {name, qty, amount})',
            'fir' => 'Extract from First Information Report (FIR/Police Report): fir_number, police_station, police_station_address, police_station_phone, date_of_report (YYYY-MM-DD), time_of_report, complainant_name, complainant_address, complainant_phone, complainant_id_number, date_of_incident (YYYY-MM-DD), time_of_incident, location_of_incident, type_of_offence, description_of_incident (full narrative), accused_name, accused_description, vehicle_plate (if motor), vehicle_make, vehicle_model, vehicle_color, damage_description, items_stolen (array of {item, estimated_value}), witnesses (array of {name, contact}), investigating_officer, investigating_officer_rank, investigating_officer_badge',
            'medical_report' => 'Extract: patient_name, patient_id, patient_dob (YYYY-MM-DD), hospital_name, doctor_name, doctor_registration, admission_date (YYYY-MM-DD), discharge_date (YYYY-MM-DD), diagnosis, treatment_summary, injuries (array of {body_part, injury_type, severity}), prognosis, disability_percentage, total_bill_amount (number), is_medico_legal (boolean)',
            'repair_estimate' => 'Extract: workshop_name, workshop_address, workshop_phone, estimate_number, estimate_date (YYYY-MM-DD), vehicle_plate, vehicle_make, vehicle_model, vehicle_year, vehicle_color, damage_description, parts (array of {part_name, part_number, qty, unit_price, total}), labour (array of {description, hours, rate, total}), parts_total (number), labour_total (number), vat (number), grand_total (number), estimated_days',
            'loss_adjuster_report' => 'Extract: adjuster_name, adjuster_company, report_date (YYYY-MM-DD), policy_number, insured_name, claim_number, date_of_loss (YYYY-MM-DD), cause_of_loss, location, property_description, damage_assessment, estimated_loss_amount (number), salvage_value (number), recommended_settlement (number), liability_opinion, recommendations',
        ];

        $fields = $fieldsByType[$docType] ?? '';

        // Add product-specific context
        $productHints = '';
        $lowerProduct = strtolower($productContext);
        if (str_contains($lowerProduct, 'motor') || str_contains($lowerProduct, 'third party')) {
            $productHints = "PRODUCT CONTEXT: Motor insurance policy. Prioritise extracting: vehicle_plate, make, model, year, engine_no, chassis_no, vin_number, color, cubic_capacity, estimated_value, owner_name, first_name, last_name, omang, dob, cellphone, email, physical_address.";
        } elseif (str_contains($lowerProduct, 'domestic')) {
            $productHints = "PRODUCT CONTEXT: Domestic/household insurance. Prioritise extracting: first_name, last_name, omang, dob, physical_address, city, area, year_built, structure_type, const_type (construction type), sum_insured, cellphone, email, proof of address details.";
        } elseif (str_contains($lowerProduct, 'commercial')) {
            $productHints = "PRODUCT CONTEXT: Commercial insurance. Prioritise extracting: company_name, company_no (registration number), directors (array of {name, id_number}), shareholders (array of {name, id_number}), physical_address, business_address, incorporation_date, re_registration_date, firm_type, owner_name, cellphone, email. Also extract vehicle details if present (vehicle_plate, make, model, year, vin_number).";
        } elseif (str_contains($lowerProduct, 'legal')) {
            $productHints = "PRODUCT CONTEXT: Legal insurance. Prioritise extracting: first_name, last_name, omang, dob, cellphone, email, physical_address.";
        } elseif (str_contains($lowerProduct, 'health') || str_contains($lowerProduct, 'hospital')) {
            $productHints = "PRODUCT CONTEXT: Health/Hospital Cashback insurance. Prioritise extracting: first_name, last_name, omang, dob, gender, cellphone, email, physical_address, beneficiaries.";
        }

        // Entity context
        $entityHints = '';
        if (strtolower($entityContext) === 'organisation') {
            $entityHints = "ENTITY: Organisation/Company. Look for: company_name, company_no, registration_number, incorporation_date, directors, shareholders, business_address, registered_office. Do NOT expect individual-only fields like gender or marital_status.";
        } elseif (strtolower($entityContext) === 'individual') {
            $entityHints = "ENTITY: Individual person. Look for: first_name, last_name, middle_name, omang, passport, dob, gender, cellphone, email, physical_address.";
        }

        if (empty($fields)) {
            $fields = 'Extract ALL identifiable fields relevant to insurance policy creation.';
        }

        return <<<PROMPT
You are a document data extraction AI for Alpha Direct Insurance (Botswana).

Given raw OCR text from a scanned document, extract structured data.

{$productHints}

{$entityHints}

{$fields}

RULES:
1. Return ONLY valid JSON — no markdown, no explanation
2. Use null for fields not found
3. Dates → YYYY-MM-DD. Convert any DD/MM/YYYY or other formats
4. Clean OCR artifacts: fix obvious typos, remove extra spaces
5. Botswana Omang ID: typically 9 digits
6. Add "_confidence" (0.0-1.0) for each extracted field
7. Extract EVERYTHING relevant to the product context — the more data the better

RAW OCR TEXT:
---
{$text}
---

JSON:
PROMPT;
    }

    private function buildFraudCheckPrompt(string $text, string $docType): string
    {
        $today = now()->format('Y-m-d');

        return <<<PROMPT
You are a fraud detection AI for Alpha Direct Insurance (Botswana). Today is {$today}.
Analyze the following OCR text from a "{$docType}" document for ACTUAL signs of tampering or forgery.

IMPORTANT — UNDERSTAND DOCUMENT CONTEXT BEFORE FLAGGING:
- **Certificate of Incorporation / Re-registration**: These are legal documents. An incorporation date of 1986 with a re-registration date of 2019 and a certificate generation date of 2025 is PERFECTLY NORMAL. The company was founded in 1986, re-registered in 2019, and the certificate was printed/generated in 2025. This is NOT suspicious. Do NOT flag historical dates on legal/corporate documents.
- **Title Deeds / Property documents**: These naturally have old dates (property was built/transferred years ago). Old dates are expected.
- **Vehicle Registration**: A vehicle manufactured in 2015 with a registration date of 2020 is normal.
- **Invoices / Quotes**: Dates should be recent (within last 12 months). Old invoices ARE suspicious.
- **ID documents (Omang, Passport)**: Check expiry dates. A DOB in the future IS suspicious. An Omang issued in 2010 is normal.
- **Mixed fonts from OCR**: OCR scanning naturally produces mixed character patterns due to image quality. This is NOT evidence of tampering unless clearly artificial (e.g. one word in a completely different font/size mid-sentence).
- **"CERTIFIED COPY"**: Government-issued certified copies are standard in Botswana. Not suspicious by itself.

ONLY FLAG THESE REAL ISSUES:
1. **Future dates where impossible** — DOB in the future, document issued in the future (beyond today {$today})
2. **ID number anomalies** — all zeros (000000000), sequential (123456789), too short/long for Botswana Omang (should be 9 digits)
3. **Critical data missing** — an ID document with no name, a vehicle reg with no plate number
4. **Logical contradictions** — person's DOB makes them under 18 but they are listed as a company director; vehicle year is 2030
5. **Expired documents** — passport or ID with expiry date before {$today}
6. **Actual tampering evidence** — visible white-out marks described in text, obviously spliced numbers (e.g. "1234 567" where a number was cut)

DO NOT FLAG:
- Old dates on corporate/legal documents (incorporation, re-registration)
- Mixed OCR formatting (this is normal from scanning)
- "CERTIFIED COPY" text (standard in Botswana)
- Historical dates that make contextual sense

RETURN ONLY VALID JSON:
{{
  "risk_level": "low" | "medium" | "high",
  "risk_score": 0-100,
  "flags": [
    {{
      "type": "category",
      "severity": "low" | "medium" | "high",
      "description": "specific issue found",
      "field": "affected field or null"
    }}
  ],
  "summary": "one-line assessment"
}}

If the document looks legitimate, return risk_level "low", risk_score 0, empty flags array, and a positive summary.

OCR TEXT:
---
{$text}
---

JSON:
PROMPT;
    }

    // ─── DB Cross-Reference ──────────────────────────────────────────────────

    private function crossReferenceDb(array $fields, string $docType): array
    {
        $flags = [];

        // Helper to get value from nested or flat format
        $getValue = fn($field) => isset($fields[$field])
            ? (is_array($fields[$field]) ? ($fields[$field]['value'] ?? null) : $fields[$field])
            : null;

        // Check Omang against existing customers
        $omang = $getValue('omang');
        if ($omang && strlen((string)$omang) >= 5) {
            $existing = DB::table('customer')
                ->leftJoin('customer_kyc', 'customer_kyc.customer_id', '=', 'customer.id')
                ->where(function ($q) use ($omang) {
                    $q->where('customer_kyc.omangNumber', (string) $omang)
                      ->orWhere('customer_kyc.omang_id', (string) $omang)
                      ->orWhere('customer.id', 'like', '') // dummy to keep structure
                      ;
                })
                ->first(['customer.id', 'customer.firstName', 'customer.lastName']);

            if ($existing) {
                $flags[] = [
                    'type' => 'existing_customer',
                    'severity' => 'info',
                    'description' => "ID {$omang} belongs to existing customer: " . trim($existing->firstName . ' ' . $existing->lastName) . " (ID: {$existing->id})",
                    'customer_id' => $existing->id,
                ];
            }

            // Check if same Omang used by different names
            $otherNames = DB::table('customer')
                ->join('customer_kyc', 'customer_kyc.customer_id', '=', 'customer.id')
                ->where('customer_kyc.omangNumber', (string) $omang)
                ->select('customer.firstName', 'customer.lastName')
                ->get();

            $extractedName = strtolower(trim(($getValue('first_name') ?? '') . ' ' . ($getValue('last_name') ?? '')));
            foreach ($otherNames as $other) {
                $dbName = strtolower(trim($other->firstName . ' ' . $other->lastName));
                if ($extractedName && $dbName && $extractedName !== $dbName) {
                    similar_text($extractedName, $dbName, $pct);
                    if ($pct < 70) {
                        $flags[] = [
                            'type' => 'name_mismatch',
                            'severity' => 'high',
                            'description' => "ID {$omang} registered to \"{$other->firstName} {$other->lastName}\" but document shows different name. Possible fraud.",
                        ];
                    }
                }
            }
        }

        // Check vehicle plate against existing policies
        $plate = $getValue('vehicle_plate');
        if ($plate) {
            $existingVehicle = DB::table('vehicle')
                ->where('vehiclePlate', 'like', '%' . str_replace(' ', '%', (string) $plate) . '%')
                ->join('policies', 'policies.id', '=', 'vehicle.policy_id')
                ->where('policies.status', 1)
                ->first(['policies.policyNumber', 'vehicle.vehiclePlate']);

            if ($existingVehicle) {
                $flags[] = [
                    'type' => 'vehicle_already_insured',
                    'severity' => 'medium',
                    'description' => "Vehicle {$existingVehicle->vehiclePlate} already has active policy {$existingVehicle->policyNumber}. Verify before creating duplicate coverage.",
                ];
            }
        }

        return $flags;
    }

    // ─── AI Call ─────────────────────────────────────────────────────────────

    private function callAi(string $prompt): array
    {
        $cfg = AiConfigController::getSettings();
        $provider = strtolower($cfg['ai_provider'] ?? 'groq');

        if ($provider === 'groq') {
            $apiKey = $cfg['groq_api_key'] ?? '';
            $model  = $cfg['groq_model'] ?? 'meta-llama/llama-4-scout-17b-16e-instruct';
            if (empty($apiKey)) throw new \Exception('Groq API key not configured');

            $response = Http::withOptions(['verify' => false])->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => $model,
                'max_tokens'  => 2000,
                'temperature' => 0.1,
                'messages'    => [
                    ['role' => 'system', 'content' => 'You extract structured data and detect fraud in documents. Return only valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
        } elseif ($provider === 'gemini') {
            $apiKey = $cfg['gemini_api_key'] ?? '';
            $model  = $cfg['gemini_model'] ?? 'gemini-2.5-flash';
            if (empty($apiKey)) throw new \Exception('Gemini API key not configured');

            $response = Http::withOptions(['verify' => false])->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type'   => 'application/json',
            ])->timeout(60)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => 'You extract structured data and detect fraud in documents. Return only valid JSON.']]],
                'contents'           => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig'   => ['temperature' => 0.1, 'maxOutputTokens' => 2048, 'thinkingConfig' => ['thinkingBudget' => 0], 'responseMimeType' => 'application/json'],
            ]);
        } else {
            $apiKey = $cfg['anthropic_api_key'] ?? '';
            $model  = $cfg['anthropic_model'] ?? 'claude-sonnet-4-20250514';
            if (empty($apiKey)) throw new \Exception('Anthropic API key not configured');

            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'       => $model,
                'max_tokens'  => 2000,
                'temperature' => 0.1,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
            ]);
        }

        // Fail-safe: if Gemini errored (commonly a transient 503), fall back to
        // Groq so the call still completes. Requires a Groq key (best-effort —
        // Groq has a 100k-tokens/day cap).
        if ($provider === 'gemini' && $response->failed() && !empty($cfg['groq_api_key'] ?? '')) {
            \Illuminate\Support\Facades\Log::warning('OcrController::callAi — Gemini failed, falling back to Groq', ['status' => $response->status()]);
            $provider = 'groq';
            $model    = $cfg['groq_model'] ?? 'meta-llama/llama-4-scout-17b-16e-instruct';
            $response = Http::withOptions(['verify' => false])->withHeaders([
                'Authorization' => 'Bearer ' . ($cfg['groq_api_key'] ?? ''),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => $model,
                'max_tokens'  => 2000,
                'temperature' => 0.1,
                'messages'    => [
                    ['role' => 'system', 'content' => 'You extract structured data and detect fraud in documents. Return only valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
        }

        if ($response->failed()) {
            throw new \Exception("AI API error {$response->status()}: " . substr($response->body(), 0, 500));
        }

        $body = $response->json();
        if ($provider === 'groq') {
            $text = $body['choices'][0]['message']['content'] ?? '';
        } elseif ($provider === 'gemini') {
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            $text = $body['content'][0]['text'] ?? '';
        }

        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```\s*$/m', '', $text);
        $text = trim($text);

        $parsed = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // AI sometimes appends commentary after valid JSON — extract first { ... }
            if (preg_match('/\{(?:[^{}]|(?:\{[^{}]*\}))*\}/s', $text, $m)) {
                $parsed = json_decode($m[0], true);
            }
            // Try finding nested objects (deeper braces)
            if ($parsed === null && preg_match('/(\{.+\})/s', $text, $m2)) {
                $parsed = json_decode($m2[1], true);
            }
            if ($parsed === null) {
                throw new \Exception('AI returned invalid JSON: ' . substr($text, 0, 300));
            }
        }

        return $parsed;
    }
}
