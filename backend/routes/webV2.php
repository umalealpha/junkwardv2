<?php

use AlphaDirect\Http\Livewire\Policy\Submit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
Route::middleware(['auth'])->group(function () {
    
    Route::group(['prefix' => 'dashboard'], function () {
        Route::get('/', \AlphaDirect\Http\Livewire\Dashboard\DashboardWizard::Class)->name('dashboard');
    });

    Route::group(['prefix' => 'policy'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"policy-table",
                "addRoute"=>"policy.add"
            ]);

        })->name('policy');
        Route::get('/add', \AlphaDirect\Http\Livewire\Policy\AddWizard::Class)->name('policy.add');
        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\Policy\EditWizard::Class)
            ->name('policy.edit');
        //Route::get('view', \AlphaDirect\Http\Livewire\Policy\ViewWizard::Class)->name('view');
    });

    Route::group(['prefix' => 'validation-rule'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"validation-rule.table",
                "addRoute"=>"validationrule.add",
                "pageName" => 'Validation Rule',
            ]);
        })->name('validationrule');
        Route::get('/add', \AlphaDirect\Http\Livewire\ValidationRule\Add::Class)->name('validationrule.add');

        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\ValidationRule\Edit::Class)->name('validationrule.edit');
     // Route::get('/', \AlphaDirect\Http\Livewire\ValidationRule\Add::Class)->name('validationrule.add');
    });

    Route::group(['prefix' => 'validation-rule-group'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"validation-rule-group.table",
                "addRoute"=>"validationrulegroup.add",
                "pageName" => 'Validation Rule Group',
            ]);
        })->name('validationrulegroup');
        Route::get('/add', \AlphaDirect\Http\Livewire\ValidationRuleGroup\Add::Class)->name('validationrulegroup.add');

        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\ValidationRuleGroup\Edit::Class)->name('validationrulegroup.edit');
    });


    Route::group(['prefix' => 'specified_coverage'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"coverage.specified-coverage.table","addRoute"=>"specifiedcoverage.add","pageName" => 'Specified Items'
            ]);
        })->name('specifiedcoverage');

        Route::get('/add', \AlphaDirect\Http\Livewire\Coverage\SpecifiedCoverage\Add::class)->name('specifiedcoverage.add');

        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\Coverage\SpecifiedCoverage\Edit::class)->name('specifiedcoverage.edit');
    });

    // Route::group(['prefix' => 'coverage'], function () {
    //     Route::get('/', function(){
    //         return view("v2.livewire-page",[
    //             'page'=>"coverage.table",
    //                 if(Auth::user()->hasPermissionTo('policy_submit_to_issue')){
    //                 "addRoute"=>"coverage.add",
    //                 }
    //             "pageName" => 'Coverages'
    //         ]);
    //     })->name('coverage');

    //     Route::get('/add', \AlphaDirect\Http\Livewire\Coverage\Add::class)->name('coverage.add');

    //     Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\Coverage\Edit::class)->name('coverage.edit');
    // });

    Route::group(['prefix' => 'coverage'], function () {
        Route::get('/', function(){
            $pagesData = [
                'page'=>"coverage.table",
                "pageName" => 'Coverages'
                ];
                if(Auth::user()->hasPermissionTo('add_coverages')){
                $pagesData["addRoute"] = "coverage.add";
                }
                return view("v2.livewire-page",$pagesData);
        })->name('coverage');

        Route::get('/add', \AlphaDirect\Http\Livewire\Coverage\Add::class)->name('coverage.add');
        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\Coverage\Edit::class)->name('coverage.edit');
    });


    Route::group(['prefix' => 'sub-coverage'], function () {
        Route::get('/', function(){
            $pagesData = [
                'page'=>"sub-coverage.table",
                "pageName" => 'Sub Coverages'
                ];
                if(Auth::user()->hasPermissionTo('add_sub_coverages')){
                $pagesData["addRoute"] = "sub-coverage.add";
                }
                return view("v2.livewire-page",$pagesData);
        })->name('subcoverage');
        Route::get('/add', \AlphaDirect\Http\Livewire\SubCoverage\Add::class)->name('sub-coverage.add');
        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\SubCoverage\Edit::class)->name('sub-coverage.edit');
    });

    Route::group(['prefix' => 'extentions'], function () {
        Route::get('/', function(){
            $pagesData = [
                'page'=>"extentions.table",
                "pageName" => 'Ext,Excess & Misc'
                ];
                if(Auth::user()->hasPermissionTo('add_sub_coverages')){
                $pagesData["addRoute"] = "extentions.add";
                }
                return view("v2.livewire-page",$pagesData);
        })->name('extentions');
        Route::get('/add', \AlphaDirect\Http\Livewire\Extentions\Add::class)->name('extentions.add');
        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\Extentions\Edit::class)->name('extentions.edit');
    });


    Route::group(['prefix' => 'company'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"company.table","addRoute"=>"company.add","pageName" => 'Company'
            ]);
        })->name('companies');

        Route::get('/add', \AlphaDirect\Http\Livewire\Company\Add::class)->name('company.add');

        Route::get('/edit/{company}', \AlphaDirect\Http\Livewire\Company\Edit::class)->name('company.edit');
    });

   

    Route::group(['prefix' => 'Reinsurancetreaty'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"re-insurance-treaty.table","addRoute"=>"re-insurance-treaty.add","pageName" => 'Reinsurance Treaty'
            ]);
        })->name('reinsuranceTreaty');

        Route::get('/add', \AlphaDirect\Http\Livewire\ReInsuranceTreaty\Add::class)->name('re-insurance-treaty.add');
        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\ReInsuranceTreaty\Edit::class)->name('re-insurance-treaty.edit');
    });

    Route::group(['prefix' => 'Reinsuranceformula'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"re-insurance-formula.table","addRoute"=>"re-insurance-formula.add","pageName" => 'Reinsurance Formula'
            ]);
        })->name('reinsuranceFormula');

        Route::get('/add', \AlphaDirect\Http\Livewire\ReInsuranceFormula\Add::class)->name('re-insurance-formula.add');
        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\ReInsuranceFormula\Edit::class)->name('re-insurance-formula.edit');
    });

    Route::group(['prefix' => 'reinsurance-type'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"re-insurance-type.table","addRoute"=>"reinsurance-type.add","pageName" => 'Reinsurance Type'
            ]);
        })->name('reinsurance-type');

        Route::get('/add', \AlphaDirect\Http\Livewire\ReInsuranceType\Add::class)->name('reinsurance-type.add');

        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\ReInsuranceType\Edit::class)->name('reinsurance-type.edit');
    });

    Route::group(['prefix' => 'reinsurance-group-coverage'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"re-insurance-group-coverage.table","addRoute"=>"reinsurance-group-coverage.add","pageName" => 'Reinsurance Group Coverage'
            ]);
        })->name('reinsurance-group-coverage');

        Route::get('/add', \AlphaDirect\Http\Livewire\ReInsuranceGroupCoverage\Add::class)->name('reinsurance-group-coverage.add');

        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\ReInsuranceGroupCoverage\Edit::class)->name('reinsurance-group-coverage.edit');
    });

    Route::group(['prefix' => 'reinsurer'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"reinsurer.table","addRoute"=>"reinsurer.add","pageName" => 'Reinsurer'
            ]);
        })->name('reinsurer');

        Route::get('/add', \AlphaDirect\Http\Livewire\Reinsurer\Add::class)->name('reinsurer.add');

        Route::get('/edit/{id}', \AlphaDirect\Http\Livewire\Reinsurer\Edit::class)->name('reinsurer.edit');
    });

    // Route::group(['prefix' => 'test'], function () {
        Route::get('test', function(){
            return view("v2.livewire-page",[
                'page'=>"account-view.table","addRoute"=>"coverage.add","policy_id"=>1,"pageName" => 'AccountView'
            ]);
        });//->name('test');
    // });

    Route::group(['prefix' => 'customerKyc'], function () {
        Route::get('/', function(){
            return view("v2.livewire-page",[
                'page'=>"customer-kyc.table","pageName" => 'Customer Kyc'
            ]);
        })->name('customerKyc');

        Route::get('/edit/{company}', \AlphaDirect\Http\Livewire\Company\Edit::class)->name('company.edit');

    });

});
?>
