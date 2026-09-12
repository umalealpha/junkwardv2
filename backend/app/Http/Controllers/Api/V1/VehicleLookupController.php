<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\VehicleMake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleLookupController extends Controller
{
    /**
     * TruTrade API — used for NON-imported (Botswana local) vehicles.
     * Matches graphiteBWV8 AddVehicle.php lines 830-832, 882-884.
     */
    private const TRUTRADE_URL = 'http://api.realintel.co.za/json/trutrade';
    private const RATING_URL   = 'https://rate.alphadirect.co.bw/api/calculation';

    private function truTradeToken(): string
    {
        return base64_encode('026B4456-A4F0-465B-90CD-754E3AD8738B:10415');
    }

    /**
     * Normalise the frontend's is_imported flag.
     * Frontend sends '0'/'1' or 'Yes'/'No' or true/false.
     * Returns boolean: true = imported vehicle (uses local DB).
     */
    private function isImported(Request $request): bool
    {
        $v = $request->get('is_imported', '0');
        return in_array($v, ['1', 'true', 'Yes', true, 1], false);
    }

    /**
     * Shared curl helper for TruTrade API calls.
     */
    private function truTradeGet(string $url, array $params = []): ?array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->truTradeToken()],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::warning("TruTrade API curl error: {$error} (URL: {$url})");
            return null;
        }
        if ($httpCode !== 200) {
            Log::warning("TruTrade API HTTP {$httpCode} (URL: {$url})");
            return null;
        }
        return json_decode($response, true);
    }

    /**
     * Get vehicle makes.
     *
     * is_imported=1 → local DB (tb_prmotormakemodels)
     * is_imported=0 → TruTrade API (non-imported Botswana vehicles)
     */
    public function makes(Request $request): JsonResponse
    {
        if ($this->isImported($request)) {
            // Imported → local DB
            $makes = VehicleMake::whereNotNull('s_Make')
                ->groupBy('s_Make')
                ->orderBy('s_Make')
                ->pluck('s_Make')
                ->toArray();
            return response()->json(['data' => $makes]);
        }

        // Non-imported → TruTrade API (matches graphiteBWV8 AddVehicle.php:830)
        $data = $this->truTradeGet(self::TRUTRADE_URL . '/GetMakes');
        if ($data && isset($data['Makes'])) {
            $makes = collect($data['Makes'])
                ->pluck('Make')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->toArray();
            return response()->json(['data' => $makes]);
        }

        return response()->json(['data' => []]);
    }

    /**
     * Get vehicle models for a given make.
     *
     * is_imported=1 → local DB
     * is_imported=0 → TruTrade API (requires make + optional year)
     */
    public function models(Request $request): JsonResponse
    {
        $make = $request->get('make');
        if (!$make) return response()->json(['data' => []]);

        if ($this->isImported($request)) {
            // Imported → local DB
            $models = VehicleMake::where('s_Make', $make)
                ->where('s_Variant', '<>', '')
                ->whereNotNull('s_Variant')
                ->pluck('s_Variant')
                ->unique()
                ->sort()
                ->values()
                ->toArray();
            return response()->json(['data' => $models]);
        }

        // Non-imported → TruTrade API (matches graphiteBWV8 AddVehicle.php:882)
        $params = ['make' => $make];
        $year = $request->get('year');
        if ($year) $params['year'] = $year;

        $data = $this->truTradeGet(self::TRUTRADE_URL . '/GetModels', $params);
        if ($data && isset($data['Variants'])) {
            $models = collect($data['Variants'])
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->toArray();
            return response()->json(['data' => $models]);
        }

        return response()->json(['data' => []]);
    }

    /**
     * Get years for a given make + model (TruTrade only — imported vehicles
     * don't have a year-filtered lookup, just make→model).
     */
    public function years(Request $request): JsonResponse
    {
        $make = trim($request->get('make', ''));
        $model = trim($request->get('model', ''));

        if (!$make || !$model) return response()->json(['data' => []]);

        $params = ['make' => $make, 'model' => $model];
        $data = $this->truTradeGet(self::TRUTRADE_URL . '/GetYears', $params);
        if ($data && isset($data['Years'])) {
            return response()->json(['data' => collect($data['Years'])->unique()->sort()->values()->toArray()]);
        }

        return response()->json(['data' => []]);
    }

    /**
     * Get variants for make + model + year.
     */
    public function variants(Request $request): JsonResponse
    {
        $make = trim($request->get('make', ''));
        $model = trim($request->get('model', ''));
        $year = $request->get('year', '');

        if (!$make || !$model || !$year) return response()->json(['data' => []]);

        $params = ['make' => $make, 'model' => $model, 'year' => $year];
        $data = $this->truTradeGet(self::TRUTRADE_URL . '/GetVariants', $params);
        return response()->json(['data' => $data ?? []]);
    }

    /**
     * Call the rating engine and return premium calculation.
     */
    public function calculatePremium(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'make'              => 'required|string|max:100',
            // Vehicle age cap: max 60 years old. Older vehicles are
            // refused on the auto path — agents handle classics manually.
            'manufacturing_year'=> [
                'required', 'digits:4',
                'integer',
                'min:' . (int) (date('Y') - 60),
                'max:' . (int) (date('Y') + 1),
            ],
            // Driver age band: 18 minimum (BW legal), 75 maximum
            // (motor underwriting band). Server-side enforcement so
            // a tampered FE can't slip an under-18 through the
            // rating engine.
            'dob'               => [
                'required', 'date_format:Y-m-d',
                'before:' . now()->subYears(18)->toDateString(),
                'after_or_equal:' . now()->subYears(75)->toDateString(),
            ],
            'sum_insured'       => 'required|numeric|min:1000|max:5000000',
            'is_imported'       => 'required|string|in:Yes,No',
            'marital_status'    => 'required|string',
            'claim_count'       => 'required|integer|min:0|max:3',
            'gender'            => 'required|string|in:Male,Female',
        ]);

        // MIS Motor Comprehensive cap: P500,000 sum insured.
        // Agents with bypass_500k = 1 may exceed the cap for endorsed high-value cases.
        if ($validated['sum_insured'] > 500000) {
            $user = auth()->user();
            if (!$user || !$user->bypass_500k) {
                return response()->json([
                    'success'                  => false,
                    'referred_to_underwriting' => true,
                    'message'                  => 'The Sum Insured of P' . number_format($validated['sum_insured'], 2, '.', ',') .
                        ' exceeds the MIS Motor Comprehensive limit of P500,000.00. ' .
                        'This quote must be referred to Underwriting for manual review and quotation. ' .
                        'Please contact the Underwriting team directly to process this case.',
                ], 422);
            }
        }

        $maritalMap = [
            'Single' => 'Never Married', 'Married' => 'Married Before',
            'Divorced' => 'Married Before', 'Widowed' => 'Married Before',
            'Living Together' => 'Never Married', 'Living Separately' => 'Married Before',
            'Never Married' => 'Never Married', 'Married Before' => 'Married Before',
        ];
        $ratingMarital = $maritalMap[$validated['marital_status']] ?? 'Never Married';
        $dob = date('d/m/Y', strtotime($validated['dob']));

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => self::RATING_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                // The rating engine sits behind Cloudflare, which advertises
                // HTTP/2 via ALPN. A libcurl built with HTTP/2 support
                // negotiates h2 by default (CURL_HTTP_VERSION_NONE), and h2
                // negotiation against this origin fails on the prod backend —
                // surfacing as a curl error → "Couldn't reach rating engine".
                // Every other rating-engine caller in this codebase
                // (QuoteController, PolicyController, the rerate console
                // commands) pins HTTP/1.1 and follows redirects, and they all
                // work — this endpoint was the only one that didn't. Match
                // them so the Start Quote motor-comp rating request connects.
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_ENCODING       => '',
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode([
                    'make'              => $validated['make'],
                    'manufacturing_year'=> $validated['manufacturing_year'],
                    'dob'               => $dob,
                    'sum_insured'       => $validated['sum_insured'],
                    'status'            => $validated['is_imported'],
                    'marital_status'    => $ratingMarital,
                    'claim_count'       => $validated['claim_count'],
                    'gender'            => $validated['gender'],
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);

            $response = curl_exec($ch);
            $error    = curl_error($ch);
            $errno    = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                // Log curl errno + HTTP code alongside the message so prod logs
                // distinguish a true connection failure (DNS/TLS/HTTP-version)
                // from an HTTP-level error, per the rating-request error-handling review.
                Log::error('Rating engine curl error', [
                    'errno'     => $errno,
                    'error'     => $error,
                    'http_code' => $httpCode,
                    'url'       => self::RATING_URL,
                ]);
                return response()->json(['success' => false, 'message' => 'Failed to reach rating engine.'], 500);
            }

            $data = json_decode($response, true);

            if (isset($data['success']) && $data['success'] == 1) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'rateId'          => $data['rate_id'] ?? null,
                        'monthly'         => $data['monthly_premium_vat'] ?? null,
                        'threeInstalment' => $data['threemonthly_preminum_vat'] ?? null,
                        'annually'        => $data['result'] ?? null,
                        'rate'            => $validated['sum_insured'] > 0
                            ? round(($data['result'] / $validated['sum_insured']) * 100, 4)
                            : null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Rating engine returned an error.',
                'raw' => config('app.debug') ? $data : null,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Rating engine call failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to reach rating engine.'], 500);
        }
    }
}
