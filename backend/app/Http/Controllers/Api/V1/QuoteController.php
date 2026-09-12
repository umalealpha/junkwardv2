<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Quote;
use AlphaDirect\ReratedPremiumQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuoteController extends Controller
{
    // ─── Status code labels ─────────────────────────────
    private const STATUS_LABELS = [
        0 => 'Draft',
        1 => 'Active',
        2 => 'Used',
        3 => 'Expired',
        4 => 'Rejected',
    ];

    private const MARITAL_LABELS = [
        1 => 'Single', 2 => 'Married', 3 => 'Divorced',
        4 => 'Widowed', 5 => 'Living Together', 6 => 'Living Separately',
    ];

    /**
     * List quotes with pagination, search, and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'   => 'nullable|string|max:50',
            'agent_id' => 'nullable|integer',
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $perPage = $validated['per_page'] ?? 25;

        $query = Quote::with([
                'customerQuote:id,firstName,middleName,lastName,cellphone,email',
                'createdByAgent:id,firstName,lastName',
                'policy:quoteNumber,policyNumber,status',
            ])
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($validated['agent_id'] ?? null, fn($q, $v) => $q->where('agentId', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('quoteCode', 'like', $like)
                      ->orWhereHas('customerQuote', function ($c) use ($like, $words) {
                          $c->where('firstName', 'like', $like)
                            ->orWhere('lastName', 'like', $like)
                            ->orWhereRaw("CONCAT_WS(' ', TRIM(firstName), TRIM(lastName)) LIKE ?", [$like])
                            ->orWhere('cellphone', 'like', $like);
                          if (count($words) >= 2) {
                              $c->orWhere(fn($i) =>
                                  $i->where('firstName', 'like', "%{$words[0]}%")
                                    ->where('lastName', 'like', "%{$words[1]}%")
                              )->orWhere(fn($i) =>
                                  $i->where('firstName', 'like', "%{$words[1]}%")
                                    ->where('lastName', 'like', "%{$words[0]}%")
                              );
                          }
                      });
                });
            })
            ->orderBy('id', 'desc');

        // Permission check
        $user = auth()->user();
        if ($user && !$user->hasPermissionTo('quote-Full List') && !$user->hasRole('Manager') && !$user->hasRole('Super Admin')) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('agentId')->orWhere('agentId', $user->id);
            });
        }

        $quotes = $query->paginate($perPage);

        return response()->json([
            'data' => $quotes->map(function ($q) {
                $customer = $q->customerQuote;
                return [
                    'id'           => $q->id,
                    'quoteCode'    => $q->quoteCode,
                    'status'       => $q->status,
                    'statusLabel'  => self::STATUS_LABELS[$q->status] ?? 'Unknown',
                    'productId'    => $q->productId,
                    'planId'       => $q->planId,
                    'policyNumber' => $q->policy->policyNumber ?? null,
                    'policyStatus' => $q->policy->status ?? null,
                    'customerName' => $customer
                        ? trim(ucwords($customer->firstName) . ' ' . ucwords($customer->middleName ?? '') . ' ' . ucwords($customer->lastName))
                        : null,
                    'customerPhone'=> $customer->cellphone ?? null,
                    'customerEmail'=> $customer->email ?? null,
                    'agentName'    => $q->createdByAgent ? trim($q->createdByAgent->firstName . ' ' . $q->createdByAgent->lastName) : null,
                    'createdAt'    => optional($q->created_at)->toIso8601String(),
                    'updatedAt'    => optional($q->updated_at)->toIso8601String(),
                ];
            }),
            'meta' => [
                'total'        => $quotes->total(),
                'per_page'     => $quotes->perPage(),
                'current_page' => $quotes->currentPage(),
                'last_page'    => $quotes->lastPage(),
                'from'         => $quotes->firstItem(),
                'to'           => $quotes->lastItem(),
            ],
        ]);
    }

    /**
     * Show full quote detail with customer, vehicle, premium info.
     */
    public function show(int $id): JsonResponse
    {
        $row = DB::table('quotes as q')
            ->leftJoin('motor_comp_quotes as mcq', 'mcq.quoteNumber', '=', 'q.quoteCode')
            ->leftJoin('customer as c', 'c.id', '=', 'q.customerId')
            ->leftJoin('customer_profile as cp', 'cp.customer_id', '=', 'q.customerId')
            ->leftJoin('products as p', 'p.id', '=', 'q.productId')
            ->leftJoin('product_plans as pp', 'pp.id', '=', 'q.planId')
            ->leftJoin('users as u', 'u.id', '=', 'q.agentId')
            ->leftJoin('stores as s', 's.id', '=', 'mcq.storeID')
            ->select([
                // Quote
                'q.id', 'q.quoteCode', 'q.status as quote_status', 'q.productId', 'q.planId',
                'q.has_vehicle', 'q.has_member', 'q.preinspection', 'q.created_at', 'q.updated_at',
                // Customer
                'c.id as customer_id', 'c.firstName', 'c.middleName', 'c.lastName',
                'c.email', 'c.cellphone',
                // Profile
                'cp.gender', 'cp.dob', 'cp.maritalstatus', 'cp.omang', 'cp.passport',
                'cp.address', 'cp.city', 'cp.state',
                // Product
                'p.name as product_name',
                'pp.name as plan_name',
                // Motor comp quote
                'mcq.id as mcq_id', 'mcq.make', 'mcq.model', 'mcq.manufacturingYear',
                'mcq.estimatedValue', 'mcq.is_imported', 'mcq.priorAccidents',
                'mcq.premiumMonthly', 'mcq.premium3Inst', 'mcq.premiumAnnually',
                'mcq.premium_rate', 'mcq.ratings_id', 'mcq.status as mcq_status',
                'mcq.discount_surcharge', 'mcq.percent_discount_surcharge',
                'mcq.premiumFrequency', 'mcq.variant', 'mcq.expiry_date',
                'mcq.type as quote_type',
                // Agent / Store
                'u.firstName as agent_first', 'u.lastName as agent_last',
                's.name as store_name',
            ])
            ->where('q.id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Quote not found.'], 404);
        }

        // Policy linked to this quote
        $policy = DB::table('policies')
            ->where('quoteNumber', $row->quoteCode)
            ->first(['id as policy_id', 'policyNumber', 'status as policy_status']);

        // Premium history
        $history = [];
        if ($row->ratings_id) {
            $history = DB::table('rerated_premium_quotes as rp')
                ->leftJoin('users as u', 'u.id', '=', 'rp.added_by')
                ->where('rp.rate_id', $row->ratings_id)
                ->orderBy('rp.id', 'desc')
                ->get(['rp.id', 'rp.old_value', 'rp.new_value', 'rp.discount_surcharge', 'rp.reason', 'rp.created_at', 'u.firstName as user_first', 'u.lastName as user_last'])
                ->map(fn($r) => [
                    'id' => $r->id, 'oldValue' => $r->old_value, 'newValue' => $r->new_value,
                    'discountSurcharge' => $r->discount_surcharge, 'reason' => $r->reason,
                    'addedBy' => trim(($r->user_first ?? '') . ' ' . ($r->user_last ?? '')) ?: null,
                    'createdAt' => $r->created_at,
                ])
                ->toArray();
        }

        $statusCode = (int) ($row->mcq_status ?? $row->quote_status);

        return response()->json([
            'data' => [
                // Quote info
                'id'            => $row->id,
                'quoteCode'     => $row->quoteCode,
                'status'        => $statusCode,
                'statusLabel'   => self::STATUS_LABELS[$statusCode] ?? 'Unknown',
                'quoteType'     => $row->quote_type ?? 'New',
                'expiryDate'    => $row->expiry_date,
                'createdAt'     => $row->created_at,
                'updatedAt'     => $row->updated_at,

                // Customer
                'customer' => [
                    'id'            => $row->customer_id,
                    'firstName'     => $row->firstName,
                    'middleName'    => $row->middleName,
                    'lastName'      => $row->lastName,
                    'fullName'      => trim(($row->firstName ?? '') . ' ' . ($row->middleName ?? '') . ' ' . ($row->lastName ?? '')),
                    'email'         => $row->email,
                    'cellphone'     => $row->cellphone,
                    'gender'        => $row->gender !== null ? ((int)$row->gender === 1 ? 'Male' : 'Female') : null,
                    'dob'           => $row->dob,
                    'maritalStatus' => self::MARITAL_LABELS[(int)($row->maritalstatus ?? 0)] ?? null,
                    'omang'         => $row->omang,
                    'passport'      => $row->passport,
                    'address'       => $row->address,
                ],

                // Product
                'product' => [
                    'id'   => $row->productId,
                    'name' => $row->product_name,
                    'plan' => $row->plan_name,
                ],

                // Vehicle (motor comp)
                'vehicle' => $row->mcq_id ? [
                    'make'              => $row->make,
                    'model'             => $row->model,
                    'year'              => $row->manufacturingYear,
                    'estimatedValue'    => $row->estimatedValue,
                    'isImported'        => $row->is_imported === 'Yes',
                    'priorAccidents'    => $row->priorAccidents,
                    'variant'           => $row->variant,
                ] : null,

                // Premium
                'premium' => [
                    'monthly'           => $row->premiumMonthly,
                    'threeInstalment'   => $row->premium3Inst,
                    'annually'          => $row->premiumAnnually,
                    'rate'              => $row->premium_rate,
                    'ratingsId'         => $row->ratings_id,
                    'frequency'         => $row->premiumFrequency,
                    'discountSurcharge' => $row->discount_surcharge,
                    'percentDiscount'   => $row->percent_discount_surcharge,
                ],

                // Agent & store
                'agentName' => trim(($row->agent_first ?? '') . ' ' . ($row->agent_last ?? '')) ?: null,
                'storeName' => $row->store_name,

                // Linked policy
                'policy' => $policy ? [
                    'id'           => $policy->policy_id,
                    'policyNumber' => $policy->policyNumber,
                    'status'       => $policy->policy_status,
                ] : null,

                // Premium history
                'premiumHistory' => $history,
            ],
        ]);
    }

    /**
     * Reject a quote (set status to 4).
     */
    public function reject(int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);
        $mcq = MotorComprehensiveQuotes::where('quoteNumber', $quote->quoteCode)->first();

        if ($mcq) {
            $mcq->update(['status' => 4]);
        }

        activity('Quote')
            ->performedOn($quote)
            ->causedBy(auth()->user())
            ->log('Quote Rejected');

        return response()->json(['message' => 'Quote rejected successfully.']);
    }

    /**
     * Update premium with discount/surcharge.
     */
    public function updatePremium(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'type'   => 'required|string|in:Discount,Surcharge',
            'value_type' => 'required|integer|in:1,2', // 1=Flat, 2=Percent
            'value'  => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $quote = Quote::findOrFail($id);
        $mcq = MotorComprehensiveQuotes::where('quoteNumber', $quote->quoteCode)->first();

        if (!$mcq) {
            return response()->json(['message' => 'Motor comprehensive quote not found.'], 404);
        }

        try {
            DB::beginTransaction();

            $oldAnnual = (float) $mcq->premiumAnnually;
            $adjustment = 0;

            if ($validated['value_type'] == 2) {
                // Percentage
                $adjustment = $oldAnnual * ($validated['value'] / 100);
            } else {
                // Flat
                $adjustment = $validated['value'];
            }

            $newAnnual = $validated['type'] === 'Discount'
                ? $oldAnnual - $adjustment
                : $oldAnnual + $adjustment;

            if ($newAnnual < 0) $newAnnual = 0;

            // Calculate monthly and 3-instalment from annual
            $newMonthly = round($newAnnual / 12, 2);
            $new3Inst   = round($newAnnual / 3, 2);

            // Update motor comp quote
            $mcq->update([
                'premiumAnnually' => round($newAnnual, 2),
                'premiumMonthly'  => $newMonthly,
                'premium3Inst'    => $new3Inst,
                'discount_surcharge' => ($mcq->discount_surcharge ?? 0) + ($validated['type'] === 'Discount' ? -$adjustment : $adjustment),
                'percent_discount_surcharge' => $validated['value_type'] == 2 ? $validated['value'] : null,
            ]);

            // Log the change
            ReratedPremiumQuote::create([
                'quote_number'       => $quote->quoteCode,
                'rate_id'            => $mcq->ratings_id,
                'old_value'          => $oldAnnual,
                'new_value'          => round($newAnnual, 2),
                'discount_surcharge' => round($adjustment, 2),
                'reason'             => $validated['reason'] . " ({$validated['type']} {$validated['value']}" . ($validated['value_type'] == 2 ? '%' : ' flat') . ")",
                'added_by'           => auth()->id(),
                'ip'                 => $request->ip(),
            ]);

            activity('Quote')
                ->performedOn($quote)
                ->causedBy(auth()->user())
                ->log("Premium updated: {$validated['type']} {$validated['value']} ({$validated['reason']})");

            DB::commit();

            return response()->json([
                'message' => 'Premium updated successfully.',
                'data' => [
                    'oldPremium' => $oldAnnual,
                    'newPremium' => round($newAnnual, 2),
                    'monthly'    => $newMonthly,
                    'threeInst'  => $new3Inst,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quote premium update failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update premium.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    /**
     * Get premium update history for a quote.
     */
    public function history(int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);
        $mcq = MotorComprehensiveQuotes::where('quoteNumber', $quote->quoteCode)->first();

        if (!$mcq || !$mcq->ratings_id) {
            return response()->json(['data' => []]);
        }

        $history = DB::table('rerated_premium_quotes as rp')
            ->leftJoin('users as u', 'u.id', '=', 'rp.added_by')
            ->where('rp.rate_id', $mcq->ratings_id)
            ->orderBy('rp.id', 'desc')
            ->select([
                'rp.id', 'rp.old_value', 'rp.new_value',
                'rp.discount_surcharge', 'rp.reason', 'rp.created_at',
                'u.firstName as user_first', 'u.lastName as user_last',
            ])
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'oldValue'        => $r->old_value,
                'newValue'        => $r->new_value,
                'discountSurcharge' => $r->discount_surcharge,
                'reason'          => $r->reason,
                'addedBy'         => trim(($r->user_first ?? '') . ' ' . ($r->user_last ?? '')) ?: null,
                'createdAt'       => $r->created_at,
            ]);

        return response()->json(['data' => $history]);
    }

    /**
     * Update quote — customer details + vehicle details (mirrors backend rerate/edit).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);

        $validated = $request->validate([
            'first_name'  => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name'   => 'nullable|string|max:100',
            'email'       => 'nullable|email|max:255',
            'cellphone'   => 'nullable|string|max:20',
            'gender'      => 'nullable|string|in:0,1',
            'dob'         => 'nullable|date_format:Y-m-d',
            'omang'       => 'nullable|string|max:25',
            'passport'    => 'nullable|string|max:25',
            'marital_status' => 'nullable|string|max:50',
            'address'     => 'nullable|string|max:500',
            // Vehicle
            'make'              => 'nullable|string|max:100',
            'model'             => 'nullable|string|max:100',
            'manufacturing_year'=> 'nullable|string|max:10',
            'estimated_value'   => 'nullable|numeric|min:0',
            'is_imported'       => 'nullable|string|in:Yes,No',
            'variant'           => 'nullable|string|max:100',
        ]);

        try {
            DB::beginTransaction();

            // Update customer
            $customer = \AlphaDirect\Customer::find($quote->customerId);
            if ($customer) {
                $customerFields = [];
                if (isset($validated['first_name'])) $customerFields['firstName'] = $validated['first_name'];
                if (isset($validated['middle_name'])) $customerFields['middleName'] = $validated['middle_name'];
                if (isset($validated['last_name'])) $customerFields['lastName'] = $validated['last_name'];
                if (isset($validated['email'])) $customerFields['email'] = $validated['email'];
                if (isset($validated['cellphone'])) $customerFields['cellphone'] = $validated['cellphone'];
                if (!empty($customerFields)) $customer->update($customerFields);

                // Update profile
                $profileData = [];
                if (array_key_exists('gender', $validated)) $profileData['gender'] = $validated['gender'];
                if (array_key_exists('dob', $validated) && $validated['dob']) $profileData['dob'] = $validated['dob'];
                if (array_key_exists('omang', $validated)) $profileData['omang'] = $validated['omang'];
                if (array_key_exists('passport', $validated)) $profileData['passport'] = $validated['passport'];
                if (array_key_exists('address', $validated)) $profileData['address'] = $validated['address'];
                if (array_key_exists('marital_status', $validated)) {
                    $msMap = ['Single' => 1, 'Married' => 2, 'Divorced' => 3, 'Widowed' => 4, 'Living Together' => 5, 'Living Separately' => 6];
                    $profileData['maritalstatus'] = $msMap[$validated['marital_status']] ?? null;
                }
                if (!empty($profileData)) {
                    \AlphaDirect\CustomerProfile::updateOrCreate(
                        ['customer_id' => $customer->id],
                        $profileData
                    );
                }
            }

            // Update vehicle (motor_comp_quotes)
            $mcq = MotorComprehensiveQuotes::where('quoteNumber', $quote->quoteCode)->first();
            if ($mcq) {
                $vehicleFields = [];
                if (isset($validated['make'])) $vehicleFields['make'] = $validated['make'];
                if (isset($validated['model'])) $vehicleFields['model'] = $validated['model'];
                if (isset($validated['manufacturing_year'])) $vehicleFields['manufacturingYear'] = $validated['manufacturing_year'];
                if (isset($validated['estimated_value'])) $vehicleFields['estimatedValue'] = $validated['estimated_value'];
                if (isset($validated['is_imported'])) $vehicleFields['is_imported'] = $validated['is_imported'];
                if (isset($validated['variant'])) $vehicleFields['variant'] = $validated['variant'];
                if (!empty($vehicleFields)) $mcq->update($vehicleFields);
            }

            activity('Quote')
                ->performedOn($quote)
                ->causedBy(auth()->user())
                ->log('Quote Updated');

            DB::commit();

            return response()->json(['message' => 'Quote updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quote update failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update quote.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    /**
     * Export quote as PDF — mirrors the exact logic from Admin\QuoteController@downloadQuote.
     */
    public function export(int $id): JsonResponse
    {
        $quoteModel = Quote::findOrFail($id);

        try {
            // Exact same query pattern as the original downloadQuote method
            $data = Quote::leftJoin('motor_comp_quotes', 'motor_comp_quotes.quoteNumber', 'quotes.quoteCode')
                ->leftJoin('customer', 'customer.id', 'quotes.customerId')
                ->leftJoin('customer_profile', 'customer_profile.customer_id', 'quotes.customerId')
                ->leftJoin('products', 'products.id', 'quotes.productId')
                ->where('quotes.quoteCode', $quoteModel->quoteCode)
                ->orderBy('quotes.id', 'desc')
                ->first();

            if (!$data) {
                return response()->json(['message' => 'Quote data not found.'], 404);
            }

            $mcqData = MotorComprehensiveQuotes::where('quoteNumber', $data->quoteCode)->first(['status', 'agentID', 'premium_rate', 'created_at', 'expiry_date']);

            $plan = \AlphaDirect\Productplan::where('id', 8)->first();

            // The unqualified join query above selects all columns from all 5 tables;
            // whichever table's `created_at` appears last in the join wins (products.created_at
            // in practice — the product record date, not the quote date). Use motor_comp_quotes
            // .created_at instead, matching the admin downloadQuote method. Fall back to
            // quotes.created_at only if the motor comp quote record is absent.
            $quoteCreatedAt = ($mcqData && $mcqData->created_at) ? $mcqData->created_at : null;
            if (!$quoteCreatedAt) {
                $quoteCreatedAt = Quote::where('quoteCode', $data->quoteCode)->value('created_at');
            }
            $date = $quoteCreatedAt instanceof \Carbon\Carbon
                ? $quoteCreatedAt->format('Y-m-d')
                : (new \Carbon\Carbon($quoteCreatedAt))->format('Y-m-d');

            if ($data->premium_rate != null) {
                $ratio = $data->premium_rate;
            } else {
                $ratio = ($data->estimatedValue != 0 && $data->estimatedValue)
                    ? ($data->premiumAnnually / $data->estimatedValue) * 100
                    : '-';
            }

            $data['created_at'] = $date;
            $data['plan_name'] = $plan->name ?? '';
            $data['ratio'] = $ratio;
            $data['quote_status'] = $mcqData->status ?? 0;

            $agent = $mcqData && $mcqData->agentID
                ? \AlphaDirect\User::where('id', $mcqData->agentID)->first(['firstName', 'lastName'])
                : null;

            // Use the stored expiry_date from motor_comp_quotes (same value the UI displays).
            // Only fall back to created_at + DaysToExpireQuote when the column is absent,
            // which prevents the PDF from showing a date that differs from the screen.
            if ($mcqData && $mcqData->expiry_date) {
                $expiryDate = \Carbon\Carbon::parse($mcqData->expiry_date)->format('d-m-Y');
            } else {
                $setting = \AlphaDirect\QuoteSettings::first();
                $expiryDate = (new \Carbon\Carbon($date))->addDays($setting->DaysToExpireQuote ?? 30)->format('d-m-Y');
            }

            $policyNumber = \AlphaDirect\Policy::where('quoteNumber', $data->quoteCode)->first(['policyNumber']);

            $store = \AlphaDirect\Stores::where('id', $data->storeID ?? null)->first();
            $storeName = ($store && $store->name) ? $store->name : null;

            // QR Code
            $qr = '';
            try {
                $qrResult = \Endroid\QrCode\Builder\Builder::create()
                    ->writer(new \Endroid\QrCode\Writer\PngWriter())
                    ->data(env('SELF_URL', 'https://graphite.alphadirect.co.bw') . '/api/Back-To-Quote/' . $data->quoteCode)
                    ->size(200)
                    ->build();
                $qrPath = 'qrcodes/quote_' . $data->quoteCode . '.png';
                if (!is_dir(public_path('qrcodes'))) {
                    mkdir(public_path('qrcodes'), 0755, true);
                }
                file_put_contents(public_path($qrPath), $qrResult->getString());
                $qr = $qrPath;
            } catch (\Exception $e) {
                // QR generation is optional
            }

            $data1 = [
                'policyNumber' => $policyNumber,
                'data'         => $data,
                'agent'        => $agent,
                'expiryDate'   => $expiryDate,
                'storeName'    => $storeName,
                'QRCode'       => $qr,
            ];

            libxml_use_internal_errors(true);
            // Use the same PDF facade as the backend (Snappy on production, DomPDF fallback)
            $pdf = \PDF::loadView('admin/policy/quotes/document', $data1);

            $timestamp = \Carbon\Carbon::now()->timestamp;
            $s3Path = 'Quotes/' . $data->quoteCode . '/Quote_' . ($policyNumber->policyNumber ?? 'draft') . '_' . $timestamp . '.pdf';
            \Storage::disk('s3')->put($s3Path, $pdf->output(), 'public');

            // Cleanup QR
            if ($qr && \File::exists(public_path($qr))) {
                \File::delete(public_path($qr));
            }

            $url = \Storage::disk('s3')->url($s3Path);

            activity('Quote')
                ->performedOn($quoteModel)
                ->causedBy(auth()->user())
                ->log('Quote PDF exported');

            return response()->json([
                'data' => [
                    'url'      => $url,
                    'path'     => $s3Path,
                    'filename' => 'Quote_' . $data->quoteCode . '.pdf',
                ],
                'message' => 'Quote PDF generated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Quote export failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Failed to generate quote PDF.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Mark quote as used (status=2) when policy is generated.
     */
    public function markUsed(int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);
        $mcq = MotorComprehensiveQuotes::where('quoteNumber', $quote->quoteCode)->first();

        if ($mcq) {
            $mcq->update(['status' => 2]);
        }

        activity('Quote')
            ->performedOn($quote)
            ->causedBy(auth()->user())
            ->log('Quote marked as Used (policy generated)');

        return response()->json(['message' => 'Quote marked as used.']);
    }
}
