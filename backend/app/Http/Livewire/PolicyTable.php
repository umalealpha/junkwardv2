<?php

namespace AlphaDirect\Http\Livewire;

use AlphaDirect\Models\User;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Services\CacheService;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\TextFilter;
use Log;
use Illuminate\Support\Facades\DB;
class PolicyTable extends DataTableComponent
{
	public function boot(): void
    {
        config(['livewire-tables.theme' => 'bootstrap-5']);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        // $this->setDebugEnabled();
    }

	public function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->options([
                    ''  => 'All',
                    '0' => 'In-Active',
					'1' => 'Activated',
					'2' => 'Canceled',
                    '3' => 'Expired',
                ])
                ->filter(function(Builder $query, string $value) {
                    $query->where('status',$value);
                }),

            SelectFilter::make('Product Name')
            ->options(['All' => 'All'] + CacheService::remember(
                'filter_products',
                fn() => Product::select('name', 'id')->get()->pluck('name', 'id')->toArray(),
                CacheService::CACHE_TTL_LONG,
                [CacheService::TAG_PRODUCTS]
            ))
            ->filter(function(Builder $query, string $value) {
                $query->where('product_id',$value);
            }),

            SelectFilter::make('Agent')
            ->options(CacheService::remember(
                'filter_agents',
                fn() => User::select('firstName', 'lastName', 'id')->get()->mapWithKeys(fn($u) => [$u->id => $u->firstName . ' ' . $u->lastName])->toArray(),
                CacheService::CACHE_TTL_LONG
            ))
            ->filter(function(Builder $query, string $value) {
                $query->where('agent_id',$value);
            }),

            'paymentMethod'   => SelectFilter::make('Payment Method')
				->options([
                    ''  => 'Any',
					'DPO'  => 'DPO',
					'RealPay' => 'RealPay',
                    'VCS' => 'VCS',
					'CASH' => 'Cash',
                    'orangeMoney' => 'Orange Money',
					'N-Genius' => 'N-Genius',
                ]),

		];
    }


	public function columns(): array
    {
        return [
            Column::make('Policy Number','policyNumber')
			->sortable()
			->searchable()
			->format(function($value, $row) {
                return
                '<a href="' . route('policy.edit',\Crypt::encrypt($row->id)) . '" target="_blank">
                <span style="height: 11px;width: 11px;background-color: #8699a6;border-radius: 50%;display: inline-block;"></span>&nbsp&nbsp' . $value . '</a>';

				// return
                // '<a wire:click="viewPolicy(\''.$row->id.'\')" class="text-gray-800 text-hover-primary mb-1" href="javascript:void(0)"> '.$value.'
				// </a>';
			})->html(),

			Column::make('Customer Name','customer_id')
			->sortable()
			->searchable(function(Builder $builder, string $value) {
                $builder->orWhereHas('customer', function($query) use ($value) {
                    $query->where('firstName', 'LIKE', "%{$value}%")
                          ->orWhere('lastName', 'LIKE', "%{$value}%")
                          ->leftJoin('companies', function($join) use ($value) {
                              $join->on('companies.id', '=', 'customer.company_id');
                          })
                          ->orwhere('companies.name', 'LIKE', "%{$value}%");

                });
            })
			->format(function($value, $row) {
                $name = 'NA';
                if (!empty($row->profile) && $row->profile->entity_type=="Organisation") {
                    $name = '<a href="' . route('admin.customer.edit', $row->profile->customer_id) . '" target="_blank">
                    <span style="height: 11px;width: 11px;background-color: #8699a6;border-radius: 50%;display: inline-block;"></span>&nbsp&nbsp' . $row->profile->company?->name . '</a>';
                }else{
                    if(!empty($row->customer)){
                        if($row->customer->customer_category == 1){
                            $name = '<a href="' . route('admin.customer.edit', $row->customer->id) . '" target="_blank">
                             <span style="height: 11px;width: 11px;background-color: #8699a6;border-radius: 50%;display: inline-block;"></span>&nbsp&nbsp' . $row->customer->firstName . ' ' . $row->customer->middleName . ' ' . $row->customer->lastName . '</a>';
                        }elseif($row->customer->customer_category == 2){
                            $name = '<a href="' . route('admin.customer.edit', $row->customer->id) . '" target="_blank">
                            <span style=" height: 11px;width: 11px;background-color: #171717;border-radius: 50%;display: inline-block;"></span>&nbsp&nbsp' .$row->customer->firstName . ' ' . $row->customer->middleName . ' ' . $row->customer->lastName . '</a>';
                        }else{
                            $name = '<a href="' . route('admin.customer.edit', $row->customer->id) . '" target="_blank">
                            <span style="height: 11px;width: 11px;background-color: #6ECB63;border-radius: 50%;display: inline-block;"></span>&nbsp&nbsp' .$row->customer->firstName . ' ' . $row->customer->middleName . ' ' . $row->customer->lastName . '</a>';
                        }
                    }
                }
				return $name;
			})->html(),

			Column::make('Product Name','product_id')
			->sortable()
            // ->searchable()
			->format(function($value, $row) {
                if (!empty($row->product) && !empty($row->product->name)) {
                   $name = $row->product->name;
                }
				return $name ?? null;
			}),

			Column::make('Policy Status','status')
			->sortable()
			->format(function($value, $row) {
                // Vehicle Preinspection Status
               //  $latest_vehicle = $row->PolicyVehicle()->latest()->first();
                $vehicle_status = '';
                if($row->vehicle != null){
                    if ($row->vehicle->status == 1) {
                        $vehicle_status = '<br><span class="badge badge-success">Preinspection Done</span>';
                    }elseif($row->vehicle->status == 0){
                        $vehicle_status = '<br><span class="badge badge-info">Preinspection Pending </span>';
                    }elseif($row->vehicle->status == 2){
                        $vehicle_status = '<br><span class="badge badge-danger">Preinspection Unapproved  </span>';
                    }
                }

                // KYC Compliance Status
                $compliance ='';
                if($row->kyc != null){
                    if ($row->kyc->compliance == 1) {
                        $compliance = '<br><span class="badge badge-success">KYC Compliant</span>';
                    } elseif ($row->kyc->compliance == 0) {
                        $compliance = '<br><span class="badge badge-primary">KYC Verification Pending </span>';
                    } elseif ($row->kyc->compliance == 2) {
                        $compliance = '<br><span class="badge badge-info">KYC Non-Compliant</span>';
                    } else {
                        $compliance = '<br><span class="badge badge-danger">KYC Status not found</span>';
                    }
                }
                // Policy Action Status
                $actionStatus = '';

                if ($row->last_transaction_type != null) {

                    $transactionType = $row->last_transaction_type ?? 'N/A';
                    $status = $row->last_action_status ?? null;
                      $actionStatus= '<br>
                    <span class="badge badge-warning">
                        ' . ucfirst(strtolower($transactionType)) . ' - '.$status.'
                    </span>';
                }


                // Payment Transaction Status
//                $latest_transaction = $row->transactions()->latest()->first(); //get latest transaction detail
                $payment_trans = '<br><span class="badge badge-danger">Payment Unsuccessful</span>';
                if ($row->transaction != null) {
                    if ($row->transaction->status != null && strtoupper($row->transaction->status) == "SUCCESS") {
                        $payment_trans = '<br><span class="badge badge-success">Payment Successful</span>';
                    } elseif ($row->transaction->status != null && (strtoupper($row->transaction->status) == "PENDING" || strtoupper($row->transaction->status) == "A")) {
                        $payment_trans = '<br><span class="badge badge-info">Payment Pending</span>';
                    } elseif ($row->transaction->status != null && strtoupper($row->transaction->status) == "PROCESSING") {
                        $payment_trans = '<br><span class="badge badge-primary">Payment Processing</span>';
                    } elseif ($row->transaction->status != null && strtoupper($row->transaction->status) == "CANCELLED") {
                        $payment_trans = '<br><span class="badge badge-danger">Payment Cancelled</span>';
                    }
                } else {
//                    $latest_transaction = $row->Policytransactions()->latest()->first();
                    if ($row->trans != null){
                        if ($row->trans->status != null && strtoupper($row->trans->status) == "SUCCESS") {
                            $payment_trans = '<br><span class="badge badge-success">Payment Successful</span>';
                        } elseif ($row->trans->status != null && (strtoupper($row->trans->status) == "PENDING" || strtoupper($row->trans->status) == "A")) {
                            $payment_trans = '<br><span class="badge badge-info">Payment Pending</span>';
                        } elseif ($row->trans->status != null && strtoupper($row->trans->status) == "PROCESSING") {
                            $payment_trans = '<br><span class="badge badge-primary">Payment Processing</span>';
                        } elseif ($row->trans->status != null && strtoupper($row->trans->status) == "CANCELLED") {
                            $payment_trans = '<br><span class="badge badge-danger">Payment Cancelled</span>';
                        } elseif ($row->trans->status != null && strtoupper($row->trans->status) == "0") {
                            $payment_trans = '<br><span class="badge badge-info">Payment not initiated</span>';
                        } elseif ($row->trans->status != null && $row->trans->status == null && $row->trans->status == null && $row->trans->status != null && strtoupper($row->trans->status) == "0") {
                            $payment_trans = '<br><span class="badge badge-danger">Payment not initiated</span>';
                        }
                    }
                }

				return $this->paymentStatus($value).$vehicle_status.$compliance.$actionStatus.$payment_trans;
			})->html(),

            Column::make('Created At','created_at')
                ->sortable()
                ->format(function($value, $row) {
                    return \Carbon\Carbon::parse($value)->format('d/m/Y');
                })->html(),

            Column::make('Vehicle Plate','id')->hideIf(false)->sortable()
                ->format(function($value, $row) {
                    if (!empty($row->vehicle) && !empty($row->vehicle->vehiclePlate)) {
                    $vehiclePlate = $row->vehicle->vehiclePlate;
                    }
                    return $vehiclePlate ?? null;
                })->html()->searchable(function(Builder $builder, string $value) {
                    $normalizedValue = str_replace(' ', '', $value);
                    $builder->orWhereHas('vehicle', function($query) use ($value, $normalizedValue) {
                        $query->where('vehiclePlate', 'LIKE', "%{$value}%")
                              ->orWhere('vehiclePlate', 'LIKE', "%{$normalizedValue}%");
                    });
                }),

            Column::make('Risk Address','id')->hideIf(true)
                // ->format(function($value, $row) {
                //     Log::debug("address test name : {$row->risk_address->address_name}");
                //     if (!empty($row->risk_address) && !empty($row->risk_address->address_name)) {
                //     $address_name = $row->risk_address->address_name;

                //     }
                //     return $address_name ?? null;
                // })
                // ->html()
                ->searchable(function(Builder $builder, string $value) {
                    $builder->orwhereHas('risk_address', function($query) use ($value) {
                        $query->where('address_name', 'like', "%{$value}%");
                    });
                }),

			Column::make('Action','id')
			->format(function($value,$row){
				return view('v2.tables.route-action', [
					'id' => $row->id,
					'action'=>$this->checkClaimProcess($row)
				]);
			}),
        ];
    }

    public function builder(): Builder
    {
        // Use a derived table with GROUP BY instead of correlated subquery — much faster
        $latestPolicyAction = DB::table('policy_actions')
            ->select('policy_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('policy_id');

        // Eager load relationships to avoid N+1 queries
		$query = Policy::with([
            'customer:id,firstName,lastName,middleName,customer_category',
            'product:id,name',
            'vehicle:id,policy_id,vehiclePlate,status',
            'transaction:id,policyNumber,status,paymentMethod',
            'trans:id,policyNumber,status',
            'kyc:id,policy_id,compliance',
            'profile:id,customer_id,entity_type,company_id',
            'profile.company:id,name'
        ])->select(
			'policies.policyNumber',
            'policies.status',
			'policies.id',
            'policies.created_at',
            'policies.customer_id',
            'policies.product_id',
            'pa_data.transaction_type as last_transaction_type',
            'pa_data.status as last_action_status'
		)
        ->when($this->getAppliedFilterWithValue('policyNumber'), fn($query, $id) => $query->where('policies.policyNumber', $id))
        ->when($this->getAppliedFilterWithValue('paymentMethod'), function($query,$value){
            $query->whereHas('transactions', function($innerQuery) use ($value) {
                $innerQuery->where('paymentMethod', $value);
            });
        })
        ->leftJoinSub($latestPolicyAction, 'pa_latest', function ($join) {
            $join->on('pa_latest.policy_id', '=', 'policies.id');
        })
        ->leftJoin('policy_actions as pa_data', 'pa_data.id', '=', 'pa_latest.max_id')
        ->whereIn('policies.product_id',[7,8,16,17,18,20,22,23,24]);

        // Apply vehicle plate search to all columns' searchable callbacks
        // This ensures vehicle plate is searched globally along with policy number, customer name, etc
        $query->leftJoin('vehicle', 'vehicle.policy_id', '=', 'policies.id');

        return $query->orderBy('policies.id','desc');
    }

	// public function viewPolicy($id){
    //     // redirect()->route('policy.edit',[\Crypt::encrypt($id)]);
    //     redirect()->route('policy.edit',\Crypt::encrypt($id));
	// }

	/*
		@id =policies.id,
		@status = policies.status
	*/
	public function paymentStatus($status){
		$details = '';
		switch ($status) {
		  case 1:
			$details ='<span class="badge badge-success">Activated</span>';
			break;
		  case 2:
			$details ='<span class="badge badge-primary">Cancelled</span>';
			break;
		  default:
			$details ='<span class="badge badge-danger">In-Active</span>';
		}
		return $details;
	}

	protected function checkClaimProcess($d){
		// Use the already eager-loaded kyc relation instead of firing a new query per row
		$kycCompliant = $d->kyc && $d->kyc->compliance == 1;
		if($kycCompliant && $d->status==1){
            // if(env("APP_STATUS") == "Development"){
                return [
                    'edit'		=>[
                        'href'	=>route('policy.edit',\Crypt::encrypt($d->id)),
                    ],
                    'claim'=> [
                        'href'	=>route('admin.newclaims.prosess',$d->id),
                    ]
                ];

            // }else{
            //     return [
            //         'edit'		=>[
            //             'href'	=>route('policy.edit',\Crypt::encrypt($d->id)),
            //         ],
            //         'claim'=> [
            //             "fun"=> "claimProcess(".$d->id.",".$d->id.")"
            //         ]
            //     ];
            // }
			return [
				'edit'		=>[
					'href'	=>route('policy.edit',\Crypt::encrypt($d->id)),
				],
				'claim'=> [
					"fun"=> "claimProcess(".$d->id.",".$d->id.")"
				]
			];
		}else{
			return [
				'edit'		=>[
					'href'	=>route('policy.edit',\Crypt::encrypt($d->id))
				]
			];
		}
	}


}
