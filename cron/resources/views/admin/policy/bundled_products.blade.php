@if(isset($policy->is_bundled) && $policy->is_bundled == 1)
<style>
.switch {
  position: relative;
  display: inline-block;
  width: 57px;
  height: 34px;
}

.switch input { 
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  
  -webkit-transition: .4s;
  transition: .4s;
  height: 26px;
}
.kt-switch input:empty ~ span {
    line-height: 30px;
    margin: 15px 0;
    height: 30px;
    width: 57px;
    border-radius: 15px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 26px;
  width: 26px;
  left: 4px;
  bottom: 0px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
  height: 26px;
}


input:checked + .slider:before {
  -webkit-transform: translateX(26px);
  -ms-transform: translateX(26px);
  transform: translateX(26px);
  -webkit-transform:rotate(180deg);
}

/* Rounded sliders */
.slider.round {
  border-radius: 34px;
}

.slider.round:before {
  border-radius: 50%;
}

</style>
<style>
    @media (min-width:500px){
        .position{
            font-size: 238%;
            top: 29%;
            width: 8%;
            background: transparent;
            text-shadow: 0 0 3px #51565a;
        }
    }
    @media (min-width: 992px){
        .col-lg-2 {
            flex: 0 0 19.666667% !important;
            max-width: 19.666667% !important;
        }
    }
    .alert_red{
        border-bottom: 1px solid red;
    }
    .position{
        font-size: 22px;
        top: 46px;
        width: 20px;
        background: transparent;
        text-shadow: 0 0 3px #51565a;
    }
    .labelled{
        font-size: 158%;
    }
    .labelled1{
        font-size: 138%;
    }
    .form-control:disabled, .form-control[readonly] {
        background-color: rgba(233, 236, 239, 0);
        opacity: 1;
    }
    /*.repeater.form-control.benefgender.error {*/
    /*    position: absolute;*/
    /*    left: 1px;*/
    /*    top: 202px;*/
    /*    color:greenyellow;*/
    /*}*/
    #back-error{
        position: absolute;
        left: 1px;
        top: 202px;
    }
    #front-error{
        position: absolute;
        left: 1px;
        top: 202px;
    }
    #right-error{
        position: absolute;
        left: 1px;
        top: 202px;
    }
    #left-error{
        position: absolute;
        left: 1px;
        top: 202px;
    }
    #vehicleRegistration-error{
        position: absolute;
        left: 1px;
        top: 202px;
    }
    #gender-error{
        position: absolute;
        left: 0px;
        margin-top: 8px;
        top: 30px;
        width: max-content;
    }
    #is_imported-error{
        position: absolute;
        left: 0px;
        margin-top: 8px;
        top: 30px;
        width: max-content;
    }
    #beneficiaries[0][beneficiaryGender]-error {
                         position: absolute;
                         left: 0px;
                         margin-top: 8px;
                         top: 30px;
                         width: max-content;
                     }
                     .imagePreview {
    width: 100%;
    height: 110px;
    background-position: center center;
    background-image:url(https://www.kindpng.com/picc/m/296-2969622_car-line-art-car-black-and-white-mitsubishi.png);
    background-color:#fff;
    background-size: cover;
    background-repeat:no-repeat;
    display: inline-block;
    box-shadow:0px -3px 6px 2px rgba(0,0,0,0.2);
}
.btn-primary
{
    display:block;
    border-radius:0px;
    box-shadow:0px 4px 6px 2px rgba(0,0,0,0.2);
    margin-top:-5px;
}
.imgUp
{
    margin-bottom:15px;
}
.del
{
    position:absolute;
    top:0px;
    right:15px;
    width:30px;
    height:30px;
    text-align:center;
    line-height:30px;
    background-color:rgba(255,255,255,0.6);
    cursor:pointer;
}
.imgAdd
{
    width:30px;
    height:30px;
    border-radius:50%;
    background-color:#4bd7ef;
    color:#fff;
    box-shadow:0px 0px 2px 1px rgba(0,0,0,0.2);
    text-align:center;
    line-height:30px;
    margin-top:0px;
    cursor:pointer;
    font-size:15px;
}
</style>
<input type="hidden" id="premium" value="{!! number_format( ( $PolicyBundleds->final_premium + $policy->bundled_discount - $PolicyBundleds->subtotal )     ,2,'.',',') !!}">
<section id="page-title" class="mb-4">
  <div class="container clearfix">
        <table class="table">
        <thead>
            <tr>
              <th><h5>Product Name</h5></th>
              <th><h5>Select Plan</h5></th>
              <th><h5>Premium Rate</h5></th>
              <th><h5>Add to Cart</h5></th>
            </tr>
        </thead>
        <tbody>
            <tr @if($policy->product_id == 1) style="display:none;" @endif>
                @php $p1_m_a_d_i = $PolicyBundled->where('policy_id',$policy->id)->where('product_id',1)->first(); @endphp
                <td>P1 Million Accidental Death Insurance</td>
                <td><select class="form-control " name="p1_m_a_d_i" id="p1_m_a_d_i" title="Please Select Plan">
                                <option value="">--Select Plan--</option>
                                <option value="1" @if($p1_m_a_d_i) selected @endif >P1 Million Accidental Death Insurance</option>
                     </select> 
                </td>
                <td><h5 id="model_label">@if($p1_m_a_d_i) P {{ $p1_m_a_d_i->premium }}@endif</h5></td>
                <td><span class="kt-switch" style="margin-top: -10px;">
                <label class="switch">
                 <input id="switchValue1" type="checkbox"  name="add_p1_m_a_d_i" value="1"  onchange="statusMsg1()">
                    <span class="slider round" ></span>
                    <h5 id="switchMsg1" style="display:inline;float:left;margin-top: -40px;margin-left: 65px;color:red;">No</h5>
                </label>
                </span> 
                </td>
            </tr>
            
            
            <tr id="ThirdPartyCarInsurancex" @if($policy->product_id == 2 || $policy->product_id == 3) style="display:none;" @endif>
                <td>Third Party Car Insurance</td>
                @php $t_p_c_i = $PolicyBundled->where('policy_id',$policy->id)->where('product_id',2)->first(); @endphp
                
                <td><select class="form-control " name="t_p_c_i" id="t_p_c_i" title="Please Select Plan">
                                <option value="">--Select Plan--</option>
                              
                                <option value="3" @if($t_p_c_i) selected @endif>P49 P1000000 Cover</option>
                    </select> 
                </td>
                <td><h5 id="t_p_c_i_premium">@if($t_p_c_i) P {{ $t_p_c_i->premium }}@endif</h5></td>
                <td><span class="kt-switch" style="margin-top: -10px;">
                <label class="switch">
                 <input id="switchValue2" type="checkbox"  name="add_life_insurance" value="1" onchange="statusMsg2()">
                     <span class="slider round" ></span>
                         <h5 id="switchMsg2" style="display:inline;float:left;margin-top: -40px;margin-left: 65px;color:red;">No</h5>
               </label>
               </span> </td>
                </tr>
                <tr id="MotorComprehensivedisplay" @if($policy->product_id == 2 || $policy->product_id == 3)  style="display:none;" @endif>
                    <td>Motor Comprehensive</td>
                    <td>
                    @php $motor_comprehensive = $PolicyBundled->where('policy_id',$policy->id)->where('product_id',3)->first(); @endphp
                    <select class="form-control " name="motor_comprehensive" id="motor_comprehensive" onchange="MotorComprehensive()" title="Please Select Plan">
                                    <option value="">--Select Plan--</option>
                                    <option value="1" @if($motor_comprehensive) selected @endif>Motor Comprehensive</option>
                                
                    </select> 
                    <select id="frequency_select" class="form-control frequencyx" name="frequency_mc" onchange="FrequencySelect()" title="Please Select Plan">
                                
                                    <option value="1" selected>Monthly</option>
                                    <option value="2">3 Installments</option>
                                    <option value="3">Annual</option>
                                
                    </select> 
               
                
                    </td>
                    <td><input type="hidden" id="moter_comp_pre" value=""><input type="hidden" id="comp_premium_premium" value=""><h5 id="motor_comprehensive_cal">@if($motor_comprehensive) P {{ $motor_comprehensive->premium }}@endif</h5></td>
                    <td><span class="kt-switch" style="margin-top: -10px;">
                    <label class="switch">
                            <input id="switchValue3" type="checkbox"  name="add_life_insurance" value="1" onchange="statusMsg3()">
                                <span class="slider round" ></span>
                                    <h5 id="switchMsg3" style="display:inline;float:left;margin-top: -40px;margin-left: 65px;color:red;">No</h5>
                    </label>
                    </span> </td>
                    </tr>
                    <tr @if($policy->product_id == 4) style="display:none;" @endif>
                        <td>Legal Insurance</td>
                        <td>
                        @php $legal_insurance = $PolicyBundled->where('policy_id',$policy->id)->where('product_id',4)->first(); @endphp
                    <select class="form-control " name="legal_insurance" id="legal_insurance" title="Please Select Plan">
                                    <option value="">--Select Plan--</option>
                                    <option value="1"  @if($legal_insurance) selected @endif>P49 Legal Insurance</option>
                                
                                
                                
                    </select> 
                    </td>
                    <td><h5 id="legal_insurance_premium">@if($legal_insurance) P {{ $legal_insurance->premium }}@endif</h5></td>
                    <td><span class="kt-switch" style="margin-top: -10px;">
                    <label class="switch">
                    <input id="switchValue4" type="checkbox"  name="add_life_insurance" value="1" onchange="statusMsg4()">
                        <span class="slider round" ></span>
                            <h5 id="switchMsg4" style="display:inline;float:left;margin-top: -40px;margin-left: 65px;color:red;">No</h5>
                    </label>
                    </span> </td>
                    </tr>
                    </tr>
                    <tr  @if($policy->product_id == 5) style="display:none;" @endif>
                    <td>Cellphone and device insurance</td>
                    <td>
                    @php $c_d_insurance = $PolicyBundled->where('policy_id',$policy->id)->where('product_id',5)->first(); @endphp
                    <select class="form-control " name="c_d_insurance" id="c_d_insurance" title="Please Select Plan">
                                    <option value="">--Select Plan--</option>
                                    <option value="1" @if($c_d_insurance) selected @endif>Mobile  And Electronic Device Instant Insurance</option>
                                
                    </select> 
                    </td>
                    <td><h5 id="c_d_insurance_premium">@if($c_d_insurance) P {{ $c_d_insurance->premium }}@endif</h5></td>
                    <td><span class="kt-switch" style="margin-top: -10px;">
                    <label class="switch">
                    <input id="switchValue5" type="checkbox"  name="add_life_insurance" value="1" onchange="statusMsg5()">
                        <span class="slider round" ></span>
                            <h5 id="switchMsg5" style="display:inline;float:left;margin-top: -40px;margin-left: 65px;color:red;">No</h5>
                    </label>
                    </span> </td>
                      </tr>
                </tbody>
                <thead>
                    <tr>
                    @php $PolicyBundleds = $PolicyBundled->where('policy_id',$policy->id)->first(); @endphp
                        <th><h5><strong>Sub Total Premium</strong></h5></th>
                        <th></th>
                        <th><h5><strong id="subtotal1">P {{ $PolicyBundleds->subtotal }}</strong></h5></th>
                        <th></th>
                        </tr>
                    </thead>
                </table>
           <table class="table">
            <thead>
            <tr>
              <th><h5>Sub Total Premium</h5></th>
              <th><h5>
                   @if ($policy->product_id == 1 )  P1 Million Accidental Death Insurance   @endif
                   @if ($policy->product_id == 2 )  Third Party Car Insurance               @endif
                   @if ($policy->product_id == 3 )  Motor Comprehensive                     @endif
                   @if ($policy->product_id == 4 )  Legal Insurance                         @endif
                   @if ($policy->product_id == 5 )  Cellphone and device insurance          @endif
           
                 </h5></th>
              <th><h5>Discounts Rate</h5></th>
              <th><h5>Total Discounts <small id="discount_on" style="font-size: 10px;"></small></h5></th>
              <th><h5><strong>Final Premium</strong></h5></th>
            </tr>
            </thead>
            <tbody>
                <tr>
                    <td><h5 id="subtotal">P {{ $PolicyBundleds->subtotal }}</h5></td>
                    <td><h5 id="add_life_insurance">P {!! number_format( ( $PolicyBundleds->final_premium + $policy->bundled_discount - $PolicyBundleds->subtotal )     ,2,'.',',') !!}</h5></td>
                    <td><h5 id="discountrate">{{ $policy->bundled_discount_precent }} %</h5></td>
                    <td><h5 id="discount">P {{ $policy->bundled_discount }}</h5></td>
                    <td><h5><strong id="totalPremium">P {{ $PolicyBundleds->final_premium }}</strong></h5></td>
                </tr>
            </tbody>
           </table>
        </div>

            <div class="modal " id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="sms_delete_confirm_title" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content" id="modal-content1">

                    </div>
                </div>
            </div>

 </section>
 <section class="container mt-5" >
   
 @include('admin.policy.bundledPayment')
 
 </section>
 
 
 </section>
 
@endif



