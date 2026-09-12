<div class="kt-portlet">
 

    <div class="kt-portlet__body">
       <div style="margin-bottom:2%" id="">
            <form id=""  action="#" method="POST" enctype="multipart/form-data" class="kt-form">
               
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <input type="hidden" name="bundled_policy_id" id="bundled_policy_id" value="{{$policy->id}}"/>
                <input type="hidden" name="product_id_1_premium" id="product_id_1_premium" value=""/>
                <input type="hidden" name="product_id_2_premium" id="product_id_2_premium" value=""/>
                <input type="hidden" name="product_id_3_premium" id="product_id_3_premium" value=""/>
                <input type="hidden" name="frequency_mc_bundled_rerate" id="frequency_mc_bundled_rerate" value="1"/>
                <input type="hidden" name="product_id_6_premium" id="product_id_6_premium" value=""/>
                <input type="hidden" name="product_id_5_premium" id="product_id_5_premium" value=""/>
                <input type="hidden" name="subtotal5" id="subtotal5" value=""/>
                <input type="hidden" name="bundled_discount_precent" id="bundled_discount_precent" value=""/>
                <input type="hidden" name="bundled_discount" id="bundled_discount" value=""/>
                <input type="hidden" name="final_premium" id="final_premium" value=""/>
               
                
                <div class="kt-form__actions" id="bundled_retatesubmit_div">
                    <div class="row">
                        <div class="col-5"></div>
                        <div class="col-7">
                            <button type="button" class="btn btn-info"
                                id="bundled_rerate_submit">Submit</button>
                            <p class="btn btn-secondary" id="" style="margin-top:2%">Cancel</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
   </div>

 </div>
  


