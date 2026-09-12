<?php

namespace AlphaDirect\Http\Livewire\Dashboard;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Redirect;

class DashboardWizard extends Component
{
    public $showLossRatioPer = false;
    public $showCollPerDetails = false;
    public $buttonLossRatioVisible=false;
    public $buttonCollectionsPerVisible=false;
    public $buttonFailedTransactionsVisible=false;
    

    public function showLossRatioButton()
    {
        $this->buttonLossRatioVisible = true;
        $this->buttonCollectionsPerVisible = false;
        $this->buttonFailedTransactionsVisible=false;
        
    }
    public function showCollectionsPerButton()
    {
        $this->buttonCollectionsPerVisible = true;
        $this->buttonLossRatioVisible = false;
        $this->buttonFailedTransactionsVisible=false;
        
    }
    public function showFailedTransactionsButton()
    {
        $this->buttonCollectionsPerVisible = false;
        $this->buttonLossRatioVisible = false;
        $this->buttonFailedTransactionsVisible=true;
        
    }
    public function render()
    {
        return view('v2.livewire.dashboard.dashboard-wizard')->layout('layouts.app-v2');
	}
}
