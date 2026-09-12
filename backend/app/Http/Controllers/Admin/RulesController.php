<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\AccountingRules;
use AlphaDirect\AccountName;
use AlphaDirect\Accounts;
use AlphaDirect\Transaction;
use AlphaDirect\Transactionsubtype;
use AlphaDirect\Transactiontype;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Lookup;
use AlphaDirect\Product;
use AlphaDirect\ProductType;
use AlphaDirect\Actions;
use Composer\DependencyResolver\Rule;
use Http\Client\Exception;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Rules;
use AlphaDirect\User;
use Illuminate\Support\Facades\Auth;
Use Redirect;
use Yajra\DataTables\DataTables;

class RulesController extends Controller
{
    /**
     * Show a list of all rules created.
     *
     * @return View rules index page
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('rule-list'))
        {
            $actions = Actions::get();
            $products = Product::get();
            $amount_types = Lookup::where('key', 'account_amount_type')->get(array(
                'id',
                'value'
            ));
            return view('admin.rules.index', compact('actions', 'products', 'amount_types'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    /**
     * Show a page to create multiple rules.
     *
     * @return View rules create page.
     */
    public function create()
    {
        if (Auth::user()
            ->hasPermissionTo('rule-create'))
        {
            $products = Product::get();
            $amount_types = Lookup::where('key', 'account_amount_type')->get(array(
                'id',
                'value'
            ));
            $trans_types = Transactiontype::get(array(
                'id',
                'name'
            ));
            $trans_sub_types = Lookup::where('key', 'transaction_sub_type')->get(array(
                'id',
                'value'
            ));
            $actions = Actions::get();
            $accountNames = AccountName::get();

            return view('admin.rules.create', compact('products', 'amount_types', 'actions', 'accountNames', 'trans_types', 'trans_sub_types'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */

    public function data(Request $request)
    {

        $rules = AccountingRules::get(array(
            'id',
            'action_id',
            'action',
            'product_id',
            'amount_type',
            'account_id',
            'entry_type',
            'trans_subtype_id',
            'trans_type_id'
        ));

        if ($request->action_filter != - 1 && $request->product_filter == - 1)
        {
            $rules = AccountingRules::where('action_id', $request->action_filter)
                ->get(['id', 'action_id', 'action', 'product_id', 'amount_type', 'account_id', 'entry_type', 'trans_subtype_id', 'trans_type_id']);
        }
        if ($request->product_filter != - 1 && $request->action_filter == - 1)
        {
            $rules = AccountingRules::where('product_id', $request->product_filter)
                ->get(['id', 'action_id', 'action', 'product_id', 'amount_type', 'account_id', 'entry_type', 'trans_subtype_id', 'trans_type_id']);
        }
        if ($request->action_filter != - 1 && $request->product_filter != - 1)
        {
            $rules = AccountingRules::where('action_id', $request->action_filter)
                ->where('product_id', $request->product_filter)
                ->get(['id', 'action', 'action_id', 'product_id', 'amount_type', 'account_id', 'entry_type', 'trans_subtype_id', 'trans_type_id']);
        }

        return DataTables::of($rules)->editColumn('amount_type', function ($rules)
        {
            $actionType = Lookup::where('id', $rules->amount_type)
                ->first();
            return $actionType ? $actionType->value : '-';
        })->editColumn('entry_type', function ($rules)
        {
            if ($rules->entry_type == 'credit')
            {
                return 'Credit';
            }
            else
            {
                return 'Debit';
            }

        })->editColumn('account_name', function ($rules)
        {
            $accountName = Accounts::where('id', $rules->account_id)
                ->first('account_name', 'account_num');

            return $accountName ? $accountName->account_name : '-';
        })->editColumn('trans_type_id', function ($rules)
        {
            $trans_type = Transactiontype::where('id', $rules->trans_type_id)
                ->first(array(
                    'id',
                    'name'
                ));
            return $trans_type ? $trans_type->name : '-';
        })->editColumn('trans_subtype_id', function ($rules)
        {
            $trans_Subtype = Transactionsubtype::where('id', $rules->trans_subtype_id)
                ->first(array(
                    'id',
                    'name'
                ));
            return $trans_Subtype ? $trans_Subtype->name : '-';
        })->editColumn('product_id', function ($rules)
        {
            $productName = Product::where('id', $rules->product_id)
                ->first();
            if ($productName && $productName->name != null)
            {
                return $productName->name;
            }
            else
            {
                return '-';
            }

        })->addColumn('actions', function ($rules)
        {
            $actions = '';
            if (Auth::user()->hasPermissionTo('rule-edit'))
            {
                $actions .= '<a href="' . url('admin/rules/edit', $rules->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
            }
            else
            {
                $actions .= '<a href="' . url('admin/rules/edit', $rules->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
            }
            if (Auth::user()
                ->hasPermissionTo('rule-delete'))
            {
                $actions .= '<a href="" value="' . $rules->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
            }
            return $actions;
        })->rawColumns(['amount_type', 'entry_type', 'account_name', 'product_id', 'trans_subtype_id', 'trans_type_id', 'actions'])
            ->make(true);
    }

    /**
     * method to store rules data from create page.
     *
     * @return View Rules index page
     */
    public function store(Request $request)
    {
        $action = Actions::where('id', $request->action_code)
            ->first(array(
                'id',
                'code'
            ));
        for ($i = 0;$i < count($request->amount_type);$i++)
        {
            if ($request->amount_type[$i] != null && $request->account_name[$i] != null && $request->entry_type[$i] != null)
            {

                $rule = new AccountingRules();
                $rule->action_id = $action->id;
                $rule->product_id = $request->get('product_name');
                $rule->amount_type = $request->amount_type[$i];
                $rule->action = $action->code;
                $rule->trans_type_id = $request->transaction_type;
                $rule->trans_subtype_id = $request->transaction_subType;
                $rule->account_id = $request->account_name[$i];
                $rule->entry_type = $request->entry_type[$i];
                $rule->save();
            }
        }
        activity('Create')
            ->performedOn($rule)->causedBy(User::where('id', auth()
                ->user()
                ->id)
                ->first())
            ->log('Rule has been created');

        return Redirect::route('admin.rules.index')
            ->with('success', 'Rule Created Successfully');
    }

    /**
     * Show a page to edit specific rule.
     **param: rule id ($id)
     * @return View rules edit page
     */
    public function edit(Request $request, $id)
    {
        $rule = AccountingRules::where('id', $id)->first();
        $amount_types = Lookup::where('key', 'account_amount_type')->get(array(
            'id',
            'value'
        ));
        $products = Product::where('id', $rule->product_id)
            ->first();
        $transaction_type = Transactiontype::where('id', $rule->trans_type_id)
            ->first();
        $transaction_subtype = Transactionsubtype::where('id', $rule->trans_subtype_id)
            ->first();
        $actions = Actions::where('id', $rule->action_id)
            ->first(array(
                'code'
            ));
        $accountNames = AccountName::get();
        if (Auth::user()->hasPermissionTo('rule-edit'))
        {

            return view('admin.rules.edit', compact('amount_types', 'actions', 'accountNames', 'rule', 'products', 'transaction_type', 'transaction_subtype'));
        }
        elseif (Auth::user()
            ->hasPermissionTo('rule-list'))
        {
            return view('admin.rules.view', compact('amount_types', 'actions', 'accountNames', 'rule', 'products', 'transaction_type', 'transaction_subtype'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method to update rules data from edit page.
     **param: rule id ($id)
     * @return View rukles index page.
     */
    public function update(Request $request, $id)
    {
        $rule = AccountingRules::where('id', $id)->first();
        $rule->amount_type = $request->amount_type;
        $rule->account_id = $request->account_name;
        $rule->entry_type = $request->entry_type;
        $rule->save();
        return Redirect::route('admin.rules.index')
            ->with('success', 'Rule updated Successfully');
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return View
     */
    public function getModalDelete(Request $request)
    {
        $body = 'Are you sure you want to delete the Rule ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
    }


    /**
     *deletes specific rule
     *param: rule id ($id)
     * @return user listing page
     */
    public function destroy($id)
    {
        AccountingRules::where('id', $id)->delete();
        return Redirect::route('admin.rules.index')
            ->with('success', 'Rule Deleted Successfully');
    }

    public function getTransSubtype(Request $request)
    {
        $transSubtype = Transactionsubtype::where('transaction_id', $request->get('trans_type'))
            ->get();
        return response()
            ->json(['count' => $transSubtype]);
    }
}

