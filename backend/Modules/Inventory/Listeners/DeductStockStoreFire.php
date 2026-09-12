<?php

namespace Modules\Inventory\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Inventory\Events\DeductStockStore;
use Modules\Inventory\Entities\StoresInventory;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Illuminate\Mail\Markdown;

class DeductStockStoreFire
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(DeductStockStore $event)
    {
        $inventory = StoresInventory::find($event->id);
        if($inventory){
			$stock = new \Modules\Inventory\Entities\StoreStock();
			$stock->store_inteventories_id=$inventory->id;
			$stock->stock=$event->quantity;
			$stock->stock_add_remove=1;  # marked as stock decucted
			$stock->user_id = $event->user->id;
			$stock->save();
			$left =(intval($inventory->counter) - intval($event->quantity));
			if($left <= $inventory->min_inventory){
				$email_id = $inventory->store->wireHouse->email_id ?? '';
                $storemail = $inventory->store->email_id ??'';
                $mobile = $inventory->store->wireHouse->mobile ?? '';
                $storemobile = $inventory->store->mobile ?? '';
                $warehouseContact = $inventory->store->wireHouse->contact_person ?? '';
                $warehouseName = $inventory->store->wireHouse->name ?? '';
                $storeContact = $inventory->store->contact_person ?? '';
                $storename = $inventory->store->name ?? '';
                if($inventory->store != NULL)
                {
                    $storeCity = \AlphaDirect\City::where('id',$inventory->store->city)->first();
                    if($storeCity != NULL)
                        $storeCityName = $storeCity->name;
                    else
                        $storeCityName = NULL;
                    $storeState = \AlphaDirect\State::where('id',$inventory->store->state_id)->first();
                    if($storeState != NULL)
                        $storeStateName = $storeState->name;
                    else
                        $storeStateName = NULL;
                } else {
                    $storeCityName = NULL;
                    $storeStateName = NULL;
                }
                
				$DeferenceInDays = \Carbon\Carbon::parse(\Carbon\Carbon::now())->diffInDays($inventory->last_notified);
                $DeferenceInDaysStore = \Carbon\Carbon::parse(\Carbon\Carbon::now())->diffInDays($inventory->last_notified);
                if($mobile!="" && ($DeferenceInDays > 1 || $inventory->last_notified=='')){
                    event(new \AlphaDirect\Events\SendSms('+267' . $mobile, 'Stock is running low please add stock for'.''.$inventory->product->name.' at '.$warehouseName));
                }
                if($storemobile!="" && ($DeferenceInDays > 1 || $inventory->last_notified=='')){
                    InfobipSms::send('+267' . $storemobile, 'Stock is running low please add stock for '.''.$storename.' '.$storeCityName.','.$storeStateName);
                }
				if($email_id!="" && ($DeferenceInDays > 1 || $inventory->last_notified=='')){
					$all = StoresInventory::with(['product','plan'])->where('store_id',$inventory->store_id)->get();
                    $markdown = new Markdown(view(), config('mail.markdown'));
                    $html = $markdown->render('Mail.StockAlert',['email'=>$email_id,'storeInventory'=>$all,'inventory'=>$inventory,'storename'=>$storename,'warehouseContact'=>$warehouseContact]);
	                event(new \AlphaDirect\Events\SendMail($email_id,"AlphaDirect | Stock Alert For Your Warehouse","Email Content in Text",$html));
				}
                if($storemail!="" && ($DeferenceInDaysStore > 1 || $inventory->last_notified=='')){
                    $all = StoresInventory::with(['product','plan'])->where('store_id',$inventory->store_id)->get();
                    $markdown = new Markdown(view(), config('mail.markdown'));
                    $html = $markdown->render('Mail.StoreStockAlert',['email'=>$storemail,'storeInventory'=>$all,'inventory'=>$inventory,'storename'=>$storename,'storeContact'=>$storeContact,'storecity'=>$storeCityName,'storestate'=>$storeStateName]);
                    event(new \AlphaDirect\Events\SendMail($storemail,"AlphaDirect | Stock Alert For Your Store","Email Content in Text",$html));$inventory->store->last_notified=\Carbon\Carbon::now();$inventory->store->save();
                    $inventory->last_notified=\Carbon\Carbon::now();
					$inventory->save();
                }
			}
		}
    }
}
