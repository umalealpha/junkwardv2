<?php

namespace AlphaDirect\Http\Livewire\Policy\Kyc;

use AlphaDirect\CustomerKycDomCom;
use Livewire\Component;
use AlphaDirect\Policy;
use AlphaDirect\KYC;
use AlphaDirect\Models\PolicyAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;

class View extends Component
{

    public Policy $policy;
    public $kyc;
    public $kycDomCom;
    public $customer;
    public $actionId;
    public $showKycHistory = false;
    public $kycHistoryByYear = [];
    public $expandedYears = [];
    // public $expandedYears;
    protected $listeners = ['toggleYearEvent' => 'toggleYear'];


    public function mount(){
        $this->kyc = KYC::where('customer_id',  $this->policy->customer_id)->first();

        $this->kycDomCom = CustomerKycDomCom::where('customer_id',  $this->policy->customer_id)->first();

        // $this->kycDomCom = CustomerKycDomCom::where('customer_id', $this->policy->customer_id)
        //     ->where('action_id', $this->actionId)
        //     ->first();

        // if ($this->kycDomCom) {
        //     // Only load customer_kyc data if dom_com record exists for this action
        //     $this->kyc = KYC::where('customer_id', $this->policy->customer_id)->first();
        // } else {
        //     $this->kyc = null; // or leave unset if not needed
        // }


        $this->customer = $this->policy->customer_id??"";
        // dd("jhj");
        $this->loadKycHistory();
    }

    public function render()
    {
        // dd($this->showKycHistory);
        // dd($this->kycHistoryByYear);
        // dd($this->expandedYears);
        return view('v2.livewire.policy.kyc.view');
    }

    public function toggleKycHistory()
    {
        $this->showKycHistory = !$this->showKycHistory;

        if ($this->showKycHistory && empty($this->kycHistoryByYear)) {
            $this->loadKycHistory();
        }
    }

    public function toggleYear($label)
    {
        if (in_array($label, $this->expandedYears)) {
            $this->expandedYears = array_values(array_diff($this->expandedYears, [$label]));
        } else {
            $this->expandedYears[] = $label;
        }

        // $this->loadKycHistory();
    }

    // public function loadKycHistory()
    // {
    //     $groupedByActions = [];

    //     $actions = PolicyAction::where('policy_id', $this->policy->id)
    //         ->orderBy('effective_from', 'desc')
    //         ->get();

    //     $kycRecords = CustomerKycDomCom::where('customer_id', $this->policy->customer_id)->get();

    //     $documentFields = [
    //         'omang', 'omangBack', 'passport', 'proof_residence', 'proof_income', 'kyc_form', 'data_protection_form',
    //         'certificate_of_incorporation', 'extract_controllers', 'resolution', 'proof_business_address',
    //         'proof_residential_address', 'directors_id_front', 'directors_id_back', 'directors_passport',
    //         'shareholders_id_front', 'shareholders_id_back', 'shareholders_passport'
    //     ];

    //     foreach ($actions as $action) {
    //         $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
    //             \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
    //             \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';

    //         $matching = $kycRecords->filter(function ($item) use ($action) {
    //             return \Carbon\Carbon::parse($item->updated_at)->between(
    //                 \Carbon\Carbon::parse($action->effective_from),
    //                 \Carbon\Carbon::parse($action->effective_to)
    //             );
    //         });

    //         $documents = [];

    //         foreach ($matching as $record) {
    //             foreach ($documentFields as $field) {
    //                 if (!empty($record->$field)) {
    //                     $documents[] = [
    //                         'type'       => ucwords(str_replace('_', ' ', $field)),
    //                         'url'        => $record->$field,
    //                         'created_at' => \Carbon\Carbon::parse($record->updated_at)->format('Y-m-d'),
    //                     ];
    //                 }
    //             }
    //         }

    //         // Add even if empty
    //         $groupedByActions[$label] = $documents;
    //     }

    //     $this->kycHistoryByYear = $groupedByActions;
    // }

    //////////////this function is in use currently
//     public function loadKycHistory()
// {
//     $groupedByActions = [];

//     $actions = PolicyAction::where('policy_id', $this->policy->id)
//         ->orderBy('effective_from', 'desc')
//         ->get()
//         ->keyBy('id');

//     $documentFields = [
//         'omang', 'omangBack', 'passport', 'proof_residence', 'proof_income', 'kyc_form', 'data_protection_form',
//         'certificate_of_incorporation', 'extract_controllers', 'resolution', 'proof_business_address',
//         'proof_residential_address', 'directors_id_front', 'directors_id_back', 'directors_passport',
//         'shareholders_id_front', 'shareholders_id_back', 'shareholders_passport'
//     ];

//     // Fetch all dom_com KYC records
//     $kycRecords = CustomerKycDomCom::where('customer_id', $this->policy->customer_id)->get();

//     // Fetch customer_kyc (global doc table)
//     $globalKyc = KYC::where('customer_id', $this->policy->customer_id)->first();

//     foreach ($actions as $action) {
//         $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
//             \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
//             \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';

//         $matching = $kycRecords->filter(function ($item) use ($action) {
//             return \Carbon\Carbon::parse($item->updated_at)->between(
//                 \Carbon\Carbon::parse($action->effective_from),
//                 \Carbon\Carbon::parse($action->effective_to)
//             );
//         });

//         $documents = [];

//         // DOM_COM documents
//         foreach ($matching as $record) {
//             foreach ($documentFields as $field) {
//                 if (!empty($record->$field)) {
//                     $documents[] = [
//                         'type'       => ucwords(str_replace('_', ' ', $field)),
//                         'url'        => $record->$field,
//                         'created_at' => \Carbon\Carbon::parse($record->updated_at)->format('Y-m-d'),
//                     ];
//                 }
//             }

//             // ✅ Include customer_kyc documents only if dom_com record for this action_id exists
//             if ($globalKyc && $record->action_id == $action->id) {
//                 foreach (['passport', 'omang', 'omangBack'] as $field) {
//                     if (!empty($globalKyc->$field)) {
//                         $documents[] = [
//                             'type'       => ucwords(str_replace('_', ' ', $field)),
//                             'url'        => $globalKyc->$field,
//                             'created_at' => \Carbon\Carbon::parse($globalKyc->updated_at)->format('Y-m-d'),
//                         ];
//                     }
//                 }
//             }
//         }

//         $groupedByActions[$label] = $documents;
//     }

//     // Archived records
//     $historyRecords = \DB::table('customer_kyc_dom_com_histories')
//         ->where('customer_id', $this->policy->customer_id)
//         ->get();

//     foreach ($historyRecords as $history) {
//         $record = json_decode($history->data, true);
//         $actionId = $record['action_id'] ?? 0;

//         if ($actionId && isset($actions[$actionId])) {
//             $action = $actions[$actionId];
//             $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
//                 \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
//                 \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';
//         } else {
//             $label = 'NO POLICY ACTION DATE FOUND';
//         }

//         $documents = [];

//         foreach ($documentFields as $field) {
//             if (!empty($record[$field])) {
//                 $documents[] = [
//                     'type'       => ucwords(str_replace('_', ' ', $field)),
//                     'url'        => $record[$field],
//                     'created_at' => isset($record['updated_at'])
//                         ? \Carbon\Carbon::parse($record['updated_at'])->format('Y-m-d')
//                         : \Carbon\Carbon::parse($history->created_at)->format('Y-m-d'),
//                 ];
//             }
//         }

//         if (!isset($groupedByActions[$label])) {
//             $groupedByActions[$label] = [];
//         }

//         $groupedByActions[$label] = array_merge($groupedByActions[$label], $documents);
//     }

//     $this->kycHistoryByYear = $groupedByActions;
// }

////////////////// this is today changes
// public function loadKycHistory()
// {
//     $groupedByActions = [];

//     $actions = PolicyAction::where('policy_id', $this->policy->id)
//         ->orderBy('effective_from', 'desc')
//         ->get()
//         ->keyBy('id');

//     $documentFields = [
//         'omang', 'omangBack', 'passport', 'proof_residence', 'proof_income', 'kyc_form', 'data_protection_form',
//         'certificate_of_incorporation', 'extract_controllers', 'resolution', 'proof_business_address',
//         'proof_residential_address', 'directors_id_front', 'directors_id_back', 'directors_passport',
//         'shareholders_id_front', 'shareholders_id_back', 'shareholders_passport'
//     ];

//     // Fetch all dom_com KYC records
//     $kycRecords = CustomerKycDomCom::where('customer_id', $this->policy->customer_id)->get();

//     // Fetch customer_kyc (global doc table)
//     $globalKyc = KYC::where('customer_id', $this->policy->customer_id)->first();

//     foreach ($kycRecords as $record) {
//         $actionId = $record->action_id ?? null;

//         if ($actionId && isset($actions[$actionId])) {
//             $action = $actions[$actionId];
//             $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
//                 \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
//                 \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';
//         } else {
//             $label = 'NO POLICY ACTION DATE FOUND';
//         }

//         $documents = [];

//         // DOM_COM documents
//         foreach ($documentFields as $field) {
//             if (!empty($record->$field)) {
//                 $documents[] = [
//                     'type'       => ucwords(str_replace('_', ' ', $field)),
//                     'url'        => $record->$field,
//                     'created_at' => \Carbon\Carbon::parse($record->updated_at)->format('Y-m-d'),
//                 ];
//             }
//         }

//         // Include customer_kyc documents only if action_id matches
//         if ($globalKyc && $actionId && isset($actions[$actionId])) {
//             foreach (['passport', 'omang', 'omangBack'] as $field) {
//                 if (!empty($globalKyc->$field)) {
//                     $documents[] = [
//                         'type'       => ucwords(str_replace('_', ' ', $field)),
//                         'url'        => $globalKyc->$field,
//                         'created_at' => \Carbon\Carbon::parse($globalKyc->updated_at)->format('Y-m-d'),
//                     ];
//                 }
//             }
//         }

//         if (!isset($groupedByActions[$label])) {
//             $groupedByActions[$label] = [];
//         }

//         $groupedByActions[$label] = array_merge($groupedByActions[$label], $documents);
//     }

//     // Archived records
//     $historyRecords = \DB::table('customer_kyc_dom_com_histories')
//         ->where('customer_id', $this->policy->customer_id)
//         ->get();

//     foreach ($historyRecords as $history) {
//         $record = json_decode($history->data, true);
//         $actionId = $record['action_id'] ?? 0;

//         if ($actionId && isset($actions[$actionId])) {
//             $action = $actions[$actionId];
//             $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
//                 \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
//                 \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';
//         } else {
//             $label = 'NO POLICY ACTION DATE FOUND';
//         }

//         $documents = [];
//         foreach ($documentFields as $field) {
//             if (!empty($record[$field])) {
//                 $documents[] = [
//                     'type'       => ucwords(str_replace('_', ' ', $field)),
//                     'url'        => $record[$field],
//                     'created_at' => isset($record['updated_at'])
//                         ? \Carbon\Carbon::parse($record['updated_at'])->format('Y-m-d')
//                         : \Carbon\Carbon::parse($history->created_at)->format('Y-m-d'),
//                 ];
//             }
//         }

//         if (!isset($groupedByActions[$label])) {
//             $groupedByActions[$label] = [];
//         }

//         $groupedByActions[$label] = array_merge($groupedByActions[$label], $documents);
//     }

//     $this->kycHistoryByYear = $groupedByActions;
// }

// public function loadKycHistory()
// {
//     $groupedByActions = [];

//     // All actions for this policy
//     $actions = PolicyAction::where('policy_id', $this->policy->id)
//         ->orderBy('effective_from', 'desc')
//         ->get()
//         ->keyBy('id');

//     $documentFields = [
//         'omang', 'omangBack', 'passport', 'proof_residence', 'proof_income', 'kyc_form', 'data_protection_form',
//         'certificate_of_incorporation', 'extract_controllers', 'resolution', 'proof_business_address',
//         'proof_residential_address', 'directors_id_front', 'directors_id_back', 'directors_passport',
//         'shareholders_id_front', 'shareholders_id_back', 'shareholders_passport'
//     ];

//     // Global KYC record
//     $globalKyc = KYC::where('customer_id', $this->policy->customer_id)->first();

//     // --- Step 1: Load latest KYC records ---
//     $latestRecords = CustomerKycDomCom::where('customer_id', $this->policy->customer_id)->get();

//     foreach ($latestRecords as $record) {
//         $this->addRecordToGroup($record, $actions, $documentFields, $globalKyc, $groupedByActions);
//     }

//     // --- Step 2: Load historical KYC records ---
//     $historyRecords = \DB::table('customer_kyc_dom_com_histories')
//         ->where('customer_id', $this->policy->customer_id)
//         ->get();

//     foreach ($historyRecords as $history) {
//         $recordData = json_decode($history->data, true);

//         // Attach created_at from history table if not in JSON
//         $recordData['created_at'] = $recordData['updated_at'] ?? $history->created_at;

//         $this->addRecordToGroup((object)$recordData, $actions, $documentFields, $globalKyc, $groupedByActions, true);
//     }

//     $this->kycHistoryByYear = $groupedByActions;
// }

// /**
//  * Add a single KYC record (latest or historical) into the correct group
//  */
// private function addRecordToGroup($record, $actions, $documentFields, $globalKyc, &$groupedByActions, $isHistory = false)
// {
//     $actionId = $record->action_id ?? null;

//     if ($actionId && isset($actions[$actionId])) {
//         $action = $actions[$actionId];
//         $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
//             \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
//             \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';
//     } else {
//         $label = 'NO POLICY ACTION DATE FOUND';
//     }

//     $documents = [];

//     // Add DOM_COM fields
//     foreach ($documentFields as $field) {
//         if (!empty($record->$field ?? null)) {
//             $documents[] = [
//                 'type'       => ucwords(str_replace('_', ' ', $field)),
//                 'url'        => $record->$field,
//                 'created_at' => isset($record->updated_at)
//                     ? \Carbon\Carbon::parse($record->updated_at)->format('Y-m-d')
//                     : (isset($record->created_at) ? \Carbon\Carbon::parse($record->created_at)->format('Y-m-d') : null),
//                 'is_history' => $isHistory,
//             ];
//         }
//     }

//     // Add global KYC docs only for latest record & matching action
//     if ($globalKyc && $actionId && isset($actions[$actionId]) && !$isHistory) {
//         foreach (['passport', 'omang', 'omangBack'] as $field) {
//             if (!empty($globalKyc->$field)) {
//                 $documents[] = [
//                     'type'       => ucwords(str_replace('_', ' ', $field)),
//                     'url'        => $globalKyc->$field,
//                     'created_at' => \Carbon\Carbon::parse($globalKyc->updated_at)->format('Y-m-d'),
//                     'is_history' => false,
//                 ];
//             }
//         }
//     }

//     if (!isset($groupedByActions[$label])) {
//         $groupedByActions[$label] = [];
//     }

//     $groupedByActions[$label] = array_merge($groupedByActions[$label], $documents);
// }

public function loadKycHistory()
{
    $groupedByActions = [];

    // All actions for this policy
    $actions = PolicyAction::where('policy_id', $this->policy->id)
        ->orderBy('effective_from', 'desc')
        ->get()
        ->keyBy('id');

    $documentFields = [
        'omang', 'omangBack', 'passport', 'proof_residence', 'proof_income', 'kyc_form', 'data_protection_form',
        'certificate_of_incorporation', 'extract_controllers', 'resolution', 'proof_business_address',
        'proof_residential_address', 'directors_id_front', 'directors_id_back', 'directors_passport',
        'shareholders_id_front', 'shareholders_id_back', 'shareholders_passport'
    ];

    // Global KYC record
    $globalKyc = KYC::where('customer_id', $this->policy->customer_id)->first();

    // --- Step 1: Pre-fill ALL actions with empty arrays ---
    foreach ($actions as $action) {
        $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
            \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
            \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';
        $groupedByActions[$label] = [];
    }

    // Always have a group for no action date
    $groupedByActions['NO POLICY ACTION DATE FOUND'] = [];

    // --- Step 2: Latest records ---
    $latestRecords = CustomerKycDomCom::where('customer_id', $this->policy->customer_id)->get();
    foreach ($latestRecords as $record) {
        $this->addRecordToGroup($record, $actions, $documentFields, $globalKyc, $groupedByActions);
    }

    // --- Step 3: Historical records ---
    $historyRecords = \DB::table('customer_kyc_dom_com_histories')
        ->where('customer_id', $this->policy->customer_id)
        ->get();

    foreach ($historyRecords as $history) {
        $recordData = json_decode($history->data, true);
        $recordData['created_at'] = $recordData['updated_at'] ?? $history->created_at;
        $this->addRecordToGroup((object)$recordData, $actions, $documentFields, $globalKyc, $groupedByActions, true);
    }

    $this->kycHistoryByYear = $groupedByActions;
}

private function addRecordToGroup($record, $actions, $documentFields, $globalKyc, &$groupedByActions, $isHistory = false)
{
    $actionId = $record->action_id ?? null;

    if ($actionId && isset($actions[$actionId])) {
        $action = $actions[$actionId];
        $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
            \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
            \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';
    } else {
        $label = 'NO POLICY ACTION DATE FOUND';
    }

    $documents = [];

    // Add DOM_COM fields
    foreach ($documentFields as $field) {
        if (!empty($record->$field ?? null)) {
            $documents[] = [
                'type'       => ucwords(str_replace('_', ' ', $field)),
                'url'        => $record->$field,
                'created_at' => isset($record->updated_at)
                    ? \Carbon\Carbon::parse($record->updated_at)->format('Y-m-d')
                    : (isset($record->created_at) ? \Carbon\Carbon::parse($record->created_at)->format('Y-m-d') : null),
                'is_history' => $isHistory,
            ];
        }
    }

    // Add global KYC docs only for latest record & matching action
    if ($globalKyc && $actionId && isset($actions[$actionId]) && !$isHistory) {
        foreach (['passport', 'omang', 'omangBack'] as $field) {
            if (!empty($globalKyc->$field)) {
                $documents[] = [
                    'type'       => ucwords(str_replace('_', ' ', $field)),
                    'url'        => $globalKyc->$field,
                    'created_at' => \Carbon\Carbon::parse($globalKyc->updated_at)->format('Y-m-d'),
                    'is_history' => false,
                ];
            }
        }
    }

    // Ensure group exists before merging
    if (!isset($groupedByActions[$label])) {
        $groupedByActions[$label] = [];
    }

    $groupedByActions[$label] = array_merge($groupedByActions[$label], $documents);
}

//     public function loadKycHistory()
// {
//     $groupedByActions = [];

//     $actions = PolicyAction::where('policy_id', $this->policy->id)
//         ->orderBy('effective_from', 'desc')
//         ->get()
//         ->keyBy('id');

//     $documentFields = [
//         'omang', 'omangBack', 'passport', 'proof_residence', 'proof_income', 'kyc_form', 'data_protection_form',
//         'certificate_of_incorporation', 'extract_controllers', 'resolution', 'proof_business_address',
//         'proof_residential_address', 'directors_id_front', 'directors_id_back', 'directors_passport',
//         'shareholders_id_front', 'shareholders_id_back', 'shareholders_passport'
//     ];

//     // 1. Include Live KYC DOMCOM Records
//     // $kycRecords = CustomerKycDomCom::where('customer_id', $this->policy->customer_id)->get();
//     $kycRecords = CustomerKycDomCom::where('action_id', $this->actionId)->get();

//     foreach ($actions as $action) {
//         $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
//             \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
//             \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';

//         $matching = $kycRecords->filter(function ($item) use ($action) {
//             return \Carbon\Carbon::parse($item->updated_at)->between(
//                 \Carbon\Carbon::parse($action->effective_from),
//                 \Carbon\Carbon::parse($action->effective_to)
//             );
//         });

//         $documents = [];

//         foreach ($matching as $record) {
//             foreach ($documentFields as $field) {
//                 if (!empty($record->$field)) {
//                     $documents[] = [
//                         'type'       => ucwords(str_replace('_', ' ', $field)),
//                         'url'        => $record->$field,
//                         'created_at' => \Carbon\Carbon::parse($record->updated_at)->format('Y-m-d'),
//                     ];
//                 }
//             }
//         }

//         $groupedByActions[$label] = $documents;
//     }

//     // 2. Include Archived History from customer_kyc_dom_com_histories
//     $historyRecords = \DB::table('customer_kyc_dom_com_histories')
//         ->where('customer_id', $this->policy->customer_id)
//         ->get();

//     foreach ($historyRecords as $history) {
//         $record = json_decode($history->data, true);
//         $actionId = $record['action_id'] ?? 0;

//         if ($actionId && isset($actions[$actionId])) {
//             $action = $actions[$actionId];
//             $label = strtoupper($action->transaction_type) . ' - ' . strtoupper($action->status) . ' (' .
//                 \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') . ' - ' .
//                 \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') . ')';
//         } else {
//             $label = 'NO POLICY ACTION DATE FOUND';
//         }

//         $documents = [];

//         foreach ($documentFields as $field) {
//             if (!empty($record[$field])) {
//                 $documents[] = [
//                     'type'       => ucwords(str_replace('_', ' ', $field)),
//                     'url'        => $record[$field],
//                     'created_at' => isset($record['updated_at'])
//                         ? \Carbon\Carbon::parse($record['updated_at'])->format('Y-m-d')
//                         : \Carbon\Carbon::parse($history->created_at)->format('Y-m-d'),
//                 ];
//             }
//         }

//         if (!isset($groupedByActions[$label])) {
//             $groupedByActions[$label] = [];
//         }

//         $groupedByActions[$label] = array_merge($groupedByActions[$label], $documents);
//     }

//     $this->kycHistoryByYear = $groupedByActions;
// }

}
