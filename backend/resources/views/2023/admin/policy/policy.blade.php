<x-app-layout>
    <x-slot name="breadcrum">
        <div class="d-flex align-items-center flex-wrap mr-1">
            <div class="d-flex align-items-baseline flex-wrap mr-5">
                <h5 class="text-dark font-weight-bold my-1 mr-5">Policy Details</h5>
                {{ Breadcrumbs::render('policy') }}
            </div>
        </div>
    </x-slot>
    <style>
        img, video {
            max-width: 100%;
            height: 400px !important;
        }
		#loading {
		  position: fixed;
		  display: block;
		  width: 100%;
		  height: 100%;
		  top: 0;
		  left: 0;
		  text-align: center;
		  opacity: 0.7;
		  background-color: #fff;
		  z-index: 99;
		}

		#loading-image {
		  position: absolute;
		  top: 100px;
		  left: 30%;
		  z-index: 100;
		}
    </style>
	<div id="loading">
		<img id="loading-image" src="{{asset('/img/spinner-icon-gif-12.jpg')}}" alt="Loading..." />
	</div>
    <div class="d-flex flex-column-fluid">
        <div class="container">
			@include('message-out')
			<div class="row">
				<div class="col-md-12">
					<div class="card card-custom">
					@if(isset($data))
						{{Form::open(['url' => route('policyStore',\Crypt::encrypt($data->id)), 'files' => true,'class'=>'form','id'=>'policy'])}}
					@else
						{{Form::open(['url' => route('policyStore'), 'files' => true,'class'=>'form','id'=>'policy'])}}
					@endif

						<div class="card-body overlay stay " id="over1">
							<h3 class="card-title align-items-start flex-column">
								<span class="card-label font-weight-bolder font-size-h4 text-dark-75">1. Customer Details</span>
							</h3>
							<!-- Modal-->
							<div class="modal fade" id="Modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
								<div class="modal-dialog" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h5 class="modal-title" id="exampleModalLabel"></h5>
											<button type="button" class="close" data-dismiss="modal" aria-label="Close">
												<i aria-hidden="true" class="ki ki-close"></i>
											</button>
										</div>
										<div class="modal-body">
										</div>
										<div class="modal-footer">
											<button type="button" class="btn btn-light-primary font-weight-bold" data-dismiss="modal">Close</button>
										</div>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-8">
									<div class="form-group">
										<label>National ID </label>
										{{Form::text('nid', (isset($user)) ? $user->profile->nid:'',['style'=>"text-transform: uppercase",'class'=>'form-control','id'=>'nid','maxlength'=>'13',"placeholder"=>"Please enter national id "])}}
										<div class="error invalid-feedback"></div>
									</div>
									<div class="form-group">
										<label>Passport Number </label>
										{{Form::text('passport', (isset($user)) ? $user->profile->passport:'',['style'=>"text-transform: uppercase",'class'=>'form-control','id'=>'passport',"placeholder"=>"Please enter passport number",'maxlength'=>'25','minlength'=>'5'])}}
										<div class="error invalid-feedback"></div>
									</div>
									<div class="form-group">
										<label>Passport Issuing Country </label>
										{{Form::select('passportIssuingCountry',Helper::getCountries(),(isset($user)) ? $user->profile->countryId:'',['class'=>'form-control ','id'=>'passportIssuingCountry','placeholder'=>' Choose a country'])}}
									</div>
									<div class="form-group">
										<label>First Name </label>
										{{Form::text('fname', (isset($user)) ? $user->firstName:'',['style'=>"text-transform: capitalize",'class'=>'form-control required_main','id'=>'fname','onkeypress'=>"return /[A-z_. ]/i.test(event.key);","placeholder"=>"First Name",'maxlength'=>'15'])}}
									</div>
									<div class="form-group">
										<label>Middle Name </label>
										{{Form::text('middleName', (isset($user)) ? $user->middleName:'',['style'=>"text-transform: capitalize",'class'=>'form-control','id'=>'middleName',"placeholder"=>"Middle Name",'maxlength'=>'15'])}}
									</div>
									<div class="form-group">
										<label>Last Name </label>
										{{Form::text('lname', (isset($user)) ? $user->lastName:'',['style'=>"text-transform: capitalize",'class'=>'form-control required_main','id'=>'lname',"placeholder"=>"Last Name",'maxlength'=>'15'])}}
									</div>
									<div class="form-group">
										<label>Gender</label>
										<div class="radio-inline">
											<label class="radio radio-lg">
												<input type="radio" class="required_main" name="gender" value="1" {{(isset($user) && ($user->profile->gender=='1')) ? "checked" : ""}} />
											<span></span>Male</label>
											<label class="radio radio-lg">
												<input type="radio" class="required_main" name="gender" value="0" {{(isset($user) && ($user->profile->gender=='0')) ? "checked" : ""}}/>
											<span></span>Female</label>
                                            <label class="radio radio-lg">
												<input type="radio" class="required_main" name="gender" value="0" {{(isset($user) && ($user->profile->gender=='2')) ? "checked" : ""}}/>
											<span></span>Other</label>
										</div>
									</div>
									<div class="form-group">
										<label>Cellphone Number </label>
										{{Form::text('cellphone', (isset($user)) ? $user->cellphone:'',['class'=>'form-control required_main','id'=>'cellphone',"placeholder"=>"Please enter customers cellphone","maxlength"=>"10"])}}
										<div class="error invalid-feedback"></div>
									</div>
									<div class="form-group">
										<label>Email </label>
										{{Form::email('email', (isset($user)) ? $user->email:'',['class'=>'form-control required_main','id'=>'email',"placeholder"=>"Email Id"])}}
										<div class="error invalid-feedback"></div>
									</div>
									<div class="form-group">
										<label>Physical Address </label>
										{{Form::text('address',  (isset($user)) ? $user->profile->address:'',['class'=>'form-control required_main','id'=>'address',"placeholder"=>"Please enter customers physical address","maxlength"=>"50", "data-fv-string-length___message"=>"Address can be of maximum 50 characters"])}}
										<div class="error invalid-feedback"></div>
									</div>
									<div class="form-group">
										<label>Date Of Birth </label>
										{{Form::text('dob',(isset($user)) ? \Carbon\Carbon::parse($user->profile->dob)->format('d/m/Y') : '',['class'=>'form-control','id'=>'dob','placeholder'=>'Date Of Birth'])}}
										<div class="error invalid-feedback"></div>
									</div>
									<div class="form-group">
										<label>Maritial Status </label>{{Form::select('maritalstatus',Helper::getMaritalProperty(),(isset($user)) ? $user->profile->maritalstatus:'',['class'=>'form-control required_main','id'=>'maritalstatus','placeholder'=>'Maritial Status'])}}
									</div>
                                    @if($data && $data->has_vehicle==1)
									<div class="form-group">
										<label>Driving License </label>
										{{ Form::text('driving_license_number', (isset($user)) ? $user->profile->driving_license_number:'',['style'=>"text-transform: uppercase",'class'=>'form-control ','id'=>'driving_license_number',"maxlength"=>"15",'placeholder'=>'driving license number'])}}
									</div>
									<div class="form-group">
										<label>Driving License Valid Till </label>
										{{Form::text('license_valid_till', (isset($user)) ? \Carbon\Carbon::parse($user->profile->license_valid_till)->format('d/m/Y') : '',['class'=>'form-control','id'=>'license_valid_till'])}}
									</div>
                                    @endif
									@livewire('show-country',['data' => (isset($user)) ? $user->profile:''])
								</div>
							</div>
							<br>
							<hr>
							<br>
							<h3 class="card-title align-items-start flex-column" style="font-size:20px;">
								<span class="card-label font-weight-bolder font-size-h4 text-dark-75"> Product : {{(isset($products)) ? $products->name : ''}}</span>
							</h3>
							<hr>
							<br>
							<div class="row" style="display:none;">
								<div class="col-lg-6">
									<div class="form-group">
										 <div class="form-group">
											<label>Please choose the product</label>
											{{Form::select('product',Helper::getProduct(),(isset($data)) ? $data->product_id:'',['class'=>'form-control', ($data && $data->product_id!='')? "disabled='disabled'":"",'id'=>'product','placeholder'=>'Please choose the product'])}}
											<p id="existingProduct" style="color:#e61c30;display:none;" >Selected Customer already has a Policy for this Product</p>
										</div>
									</div>
								</div>
							</div>
							<div class="row" id="planID" style="display:none;">
								<div class="col-lg-6">
									 <div class="form-group">
										<label>Please choose the plan</label>
										<select class="form-control" id="plan" name="plan" title="Please choose plan" data-live-search="true" {{($data && $data->plan_id!='') ? "disabled='disabled'":""}}>
										</select>
									</div>
								</div>
							</div>

							 <div class="row" id="factor_main"></div>
							@if($data && $data->has_vehicle==1)
							<div id="vehicle_section" style="display: none;">
									@include('admin.policy.vehicle-section')
									@include('admin.policy.driver-section')
							</div>
							@endif
							@if($data->product_id == 5)
							<div id="addCellphoneDiv" style="display: none;">
									@include('admin.policy.cellphone-section')
							</div>
							@endif
							@if($data && $data->has_member == 1)
								<div id="addBeneficiaryDiv" style="display: none;">
									@include('admin.policy.benificery-section')
								</div>
							@endif
							<!--<div id="coverageDiv" style="display: none;"></div>-->
							<div id="customerKYCDiv" style="display: none;">
									@include('admin.policy.customerkyc-section')
							</div>
							<div class="separator separator-dashed my-10"></div>
								<div class="row">
									<div class="col-lg-12">
										<div class="form-group">
										<h3 class="card-title align-items-start flex-column">
											<span class="card-label font-weight-bolder font-size-h4 text-dark-75">Note:</span>
										</h3>
											<textarea class="form-control" name="note" placeholder="Add Note" rows="3"></textarea>
										</div>
									</div>
								</div>
							<div class="separator separator-dashed my-10"></div>
							<div>
								@livewire('bank-details',['data' => (isset($data)) ? $data:''])
							</div>

						</div>
						 <div class="card-footer">
							<a  id="sub_but" class="btn btn-primary font-weight-bold mr-2">Submit</a>
							<a class="btn btn-light-primary font-weight-bold" href="{{ route('policy') }}" >Cancel</a>
						</div>
						{!! Form::hidden('id', (isset($data) && !empty( $data )) ? $data->customer_id : '', ['id'=>'id'])!!}

						{!! Form::hidden('selected_plan_id', (isset($data)) ? $data->plan_id: '', ['id'=>'selected_plan_id'])!!}

						{!! Form::hidden('selected_product_id', (isset($data)) ? $data->product_id: '', ['id'=>'selected_product_id'])!!}
						{!! Form::hidden('has_vehicle', (isset($data)) ? $data->has_vehicle: '', ['id'=>'has_vehicle'])!!}

						{!! Form::hidden('has_member', (isset($data)) ? $data->has_member: '', ['id'=>'has_member'])!!}

						{{Form::close()}}
					</div>
				</div>
			</div>
		</div>
    </div>
	@push('scripts')
	<script>
	$(document).ready(function () {
		'use strict';

		if($('#product').val()!=""){
			 $('#product').trigger('change');
		}
		/* if($('#has_vehicle').val()==1){
			 $("#vehicle_section :input").not('.exc').addClass('required');
		} */

		var avatar1 = new KTImageInput('kt_image_1');
		var avatar2 = new KTImageInput('kt_image_2');
		var avatar3 = new KTImageInput('kt_image_3');
		var avatar4 = new KTImageInput('kt_image_4');
		var avatar5 = new KTImageInput('kt_image_5');
		var avatar6 = new KTImageInput('kt_image_6');
		var avatar7 = new KTImageInput('kt_image_7');
		var avatar8 = new KTImageInput('kt_image_8');
		var avatar9 = new KTImageInput('kt_image_9');
		var avatar10 = new KTImageInput('kt_image_10');
		var avatar11 = new KTImageInput('kt_image_11');

		$('.selectpicker').selectpicker('refresh');
		let validation;
		validation=FormValidation.formValidation(
			KTUtil.getById('policy'),
			{
				fields: {
					required: {
						selector: '.required',
						validators: {
							callback: {
								callback: function(input) {
									console.log("repeater input",input.element);
									if (input.value === '') {
										return {
											valid: false,
											message: input.element.getAttribute('placeholder')+ ' is required'
										};
									}
									return true;
								}
							},
						},
					},

					required_main: {
						selector: '.required_main',
						validators: {
							callback: {
								callback: function(input) {
									console.log("base input",input.element);
									if (input.value === '') {
										return {
											valid: false,
											message: input.element.getAttribute('placeholder')+ ' is required'
										};
									}
									return true;
								}
							},
						},
					},

					dob: {
						validators: {
							notEmpty: {
								message: 'The dob is required'
							},
							driverDOBReq: {
								format: 'DD/MM/YYYY',
								message: 'The dob is not valid'
							}
						}
					},
					license_valid_till: {
						validators: {
							notEmpty: {
								message: 'The driving license expiry date is required'
							},
							driverDOBReq: {
								format: 'DD/MM/YYYY',
								message: 'The driving license expiry date is not valid'
							}
						}
					},

					beneficiaryPercentCalculate: {
						selector: '.beneficiaryPercentCalculate',
						verbose: false,

						validators: {
							between: {
								min: 1,
								max: 100,
								message: 'The percentage must be between 1 and 100'
							},
							callback: {
								message: 'The sum of percentages must be 100',
								callback: function(value, validator, $field) {
										var sum  = 0;
										var per=0;
										var cnt=0;
										var total=0;
										console.log("value"+value);
										$('.beneficiaryPercentCalculate').each(function (index,value){
											cnt=cnt+1;
											var el="beneficiaries["+index+"][beneficiaryPayment]";
											console.log("beneficiaries value--"+$("input[name='"+el+"']" ).val());
											if($("input[name='"+el+"']" ).val()!="" && isNaN($("input[name='"+el+"']" ).val())!=true){
												sum += parseInt($("input[name='"+el+"']" ).val());
											}

										});
										console.log("old sum"+sum);
										if (sum === 100 ) {
											console.log("true"+sum);
											$('.beneficiaryPercentCalculate').parent('div').find("div.fv-plugins-message-container div").remove();
											$('.beneficiaryPercentCalculate').parent('div').removeClass("has-danger");
											$('.beneficiaryPercentCalculate').parent('div').addClass("has-success");
											$('.beneficiaryPercentCalculate').addClass("is-valid");
											$('.beneficiaryPercentCalculate').removeClass("is-invalid");
											validator.updateStatus('beneficiaryPercentCalculate', 'VALID', 'callback');
											 return true;
										}
										return false;

								}
							}
						}
					},

				},
				plugins: {
					/* declarative: new FormValidation.plugins.Declarative({
						html5Input: true,
					}), */
					trigger: new FormValidation.plugins.Trigger(),
					submitButton: new FormValidation.plugins.SubmitButton(),
					defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
					bootstrap: new FormValidation.plugins.Bootstrap(),
					excluded: new FormValidation.plugins.Excluded({
						excluded: function(field, ele, eles) {
							const passport = KTUtil.getById('policy').querySelector('[name="passport"]');
							console.log("passport"+passport);
							const nid = KTUtil.getById('policy').querySelector('[name="nid"]');
							const passportIssuingCountry = KTUtil.getById('policy').querySelector('[name="passportIssuingCountry"]');
							if (field === 'passportIssuingCountry' && passport.value === ''){return true;}
							if (field === 'nid' && passport.value !== ''){return true;}
							if (field === 'nid' && passport.value !== '' && passportIssuingCountry.value !== ''){return true;}
							if (field === 'passport' && nid.value !== ''){return true;}
						},
					}),
				},
			}
		);
		$("input[name='dob']" ).datepicker({
			rtl: KTUtil.isRTL(),
		   todayHighlight: true,
		   orientation: "top left",
		   format: 'dd/mm/yyyy',
		   templates: {
			leftArrow: '<i class="la la-angle-left"><i>',
			rightArrow: '<i class="la la-angle-right"><i>'
			  }
		}).on('changeDate', function(e) {
			validation.revalidateField('dob');
		});
		if($('#has_vehicle').val()==1){
			$("input[name='license_valid_till']" ).datepicker({
				rtl: KTUtil.isRTL(),
			   todayHighlight: true,
			   orientation: "top left",
			   format: 'dd/mm/yyyy',
			   templates: {
				leftArrow: '<i class="la la-angle-left"><i>',
				rightArrow: '<i class="la la-angle-right"><i>'
				  }
			}).on('changeDate', function(e) {
				validation.revalidateField('license_valid_till');
			});
		}

		window.id = 0;
		window.outerRepeater = $('.form').repeater({
            isFirstItemUndeletable: true,

			show: function () {
				console.log("test"+$(this).find('input')[1]);
                $(this).slideDown();
				validation=FormValidation.formValidation(
					KTUtil.getById('policy'),
					{
						fields: {
							required: {
								selector: '.required',
								validators: {
									callback: {
										callback: function(input) {
											console.log("input",input);
											if (input.value === '') {
												return {
													valid: false,
													message: input.element.getAttribute('placeholder')+ ' is required'
												};
											}
											return true;
										}
									},
								}
							},
							driverGenderReq: {
								selector: '.driverGenderReq',
								validators: {
									notEmpty: {
										message: 'The driver gender is required'
									},
								}
							},
							driverDOBReq: {
								selector: '.driverDOBReq',
								validators: {
									notEmpty: {
										message: 'The driver dob is required'
									},
									driverDOBReq: {
										format: 'DD/MM/YYYY',
										message: 'The driver dob is not valid'
									}
								}
							},
							driverLicenseValidReq: {
								selector: '.driverLicenseValidReq',
								validators: {
									notEmpty: {
										message: 'The driver driving license expiry date is required'
									},
									driverLicenseValidReq: {
										format: 'DD/MM/YYYY',
										message: 'The driver driving license expiry date is not valid'
									}
								}
							},
							beneficiaryDOBReq: {
								selector: '.beneficiaryDOBReq',
								validators: {
									notEmpty: {
										message: 'The beneficiary dob is required'
									},
									beneficiaryDOBReq: {
										format: 'DD/MM/YYYY',
										message: 'The beneficiary dob is not valid'
									}
								}
							},
							beneficiaryPercentCalculate: {
								selector: '.beneficiaryPercentCalculate',
								verbose: false,

								validators: {
									between: {
										min: 1,
										max: 100,
										message: 'The percentage must be between 1 and 100'
									},
									callback: {
										message: 'The sum of percentages must be 100',
										callback: function(value, validator, $field) {
												var sum  = 0;
												var per=0;
												var cnt=0;
												var total=0;
												console.log("value"+value);
												$('.beneficiaryPercentCalculate').each(function (index,value){
													cnt=cnt+1;
													var el="beneficiaries["+index+"][beneficiaryPayment]";
													if($("input[name='"+el+"']" ).val()!="" && isNaN($("input[name='"+el+"']" ).val())!=true){
														sum += parseInt($("input[name='"+el+"']" ).val());
													}

												});
												console.log("new sum"+sum);
												if (sum === 100 ) {
													console.log("true"+sum);
													$('.beneficiaryPercentCalculate').parent('div').find("div.fv-plugins-message-container div").remove();
													$('.beneficiaryPercentCalculate').parent('div').removeClass("has-danger");
													$('.beneficiaryPercentCalculate').parent('div').addClass("has-success");
													$('.beneficiaryPercentCalculate').addClass("is-valid");
													$('.beneficiaryPercentCalculate').removeClass("is-invalid");
													validator.updateStatus('beneficiaryPercentCalculate', 'VALID', 'callback');
													 return true;
												}
												return false;

										}
									}
								}
							},

						},
						plugins: {
							/* declarative: new FormValidation.plugins.Declarative({
								html5Input: true,
							}), */
							trigger: new FormValidation.plugins.Trigger(),
							submitButton: new FormValidation.plugins.SubmitButton(),
							defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
							bootstrap: new FormValidation.plugins.Bootstrap(),

						},
					}
				);
				var eBR=[];
					if($('#has_vehicle').val()==1){
						$('[id^=driverDOB_]').each(function () {
							eBR.push($(this).attr('name'));
						});
						$('[id^=driverLicenseValid_]').each(function () {
							eBR.push($(this).attr('name'));
						});
						$('[id^=driverGender_]').each(function () {
							eBR.push($(this).attr('name'));
						});
					}
					if($('#has_member').val()==1){
						$('[id^=beneficiaryDOB_]').each(function () {
							eBR.push($(this).attr('name'));
						});
					}
						let er=[...new Set(eBR)];
						console.log("er"+er);
						var cl="";
						er.forEach(el=>{
							if(el.includes("driverDOB")){
								var cl="driverDOBReq";
							}if(el.includes("driverLicenseValid")){
								var cl="driverLicenseValidReq";
							}
							if(el.includes("beneficiaryDOB")){
								var cl="beneficiaryDOBReq";
							}
							if(el.includes("driverGender")){
								validation.revalidateField("driverGenderReq");
							}

							var myarray= ["driverDOBReq", "driverLicenseValidReq", "beneficiaryDOBReq"];
							if(jQuery.inArray(cl, myarray) != -1) {
								$("input[name='"+el+"']" ).datepicker({
									rtl: KTUtil.isRTL(),
								   todayHighlight: true,
								   orientation: "top left",
								   format: 'dd/mm/yyyy',
								   templates: {
									leftArrow: '<i class="la la-angle-left"><i>',
									rightArrow: '<i class="la la-angle-right"><i>'
									  }
								}).on('changeDate', function(e) {
									console.log("date"+cl);
									// Revalidate the date field
									validation.revalidateField(cl);
								});
							}
						});


            },
            hide: function (deleteElement) {
                if(confirm('Are you sure you want to delete this element?')) {
                    $(this).slideUp(deleteElement);
                }
            },
            ready: function (setIndexes) {

            }
		});


		var btn = KTUtil.getById("sub_but");
		$('#sub_but').on('click', function(e) {
            KTUtil.btnWait(btn, "spinner spinner-right spinner-white pr-15", "Please wait");
            e.preventDefault();
			//$(".fv-help-block").remove();
			validation.validate().then(function(status) {
				console.log("status"+status);
                if (status == 'Valid') {
                    $('form#product').submit();
                } else {

					var second_array=[];
					var ehtml = "<ol>";
					$($('.fv-help-block') ).each(function( index ) {
						second_array.push($( this ).text());
					});
					let er=[...new Set(second_array)]
					console.log("er"+er);
					er.forEach(el=>{
						ehtml += "<li style='list-style-type:disc;color:red;'><b>"+el+"</b></li>";
					});
					ehtml +="</ol>";

                    swal.fire({
                        html: ehtml,
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok, got it!",
                        customClass: {
                            confirmButton: "btn font-weight-bold btn-light-primary"
                        }
                    }).then(function() {
                        KTUtil.btnRelease(btn);
                    });
                }
            });
        });
		setTimeout(function(){
			$('#loading').remove();
		},1000);
	});


	 $('#product').on('change',function(e, data){
		var product_id = $('#selected_product_id').val();
		 // cellphone product_id == 5
		if (product_id == 5) {
			$('#addCellphoneDiv').show();
		}else {
			$('#addCellphoneDiv').hide();
		}
		var selected = '';
		$.ajax({
			url: '{{ route('getProductFactors') }}',
			data: {
				"_token": "{{ csrf_token() }}",
				"id": product_id,
				"nid" : $('#nid').val(),
				"passport" : $('#passport').val(),
			},
			type: 'post',
			datatype : 'json',
			success: function(data) {
				var append = '';
				var coverage = '';
				if(data){
					 if (data.status === 'noCustomer') {
						 $('#existingProduct').text('Please enter omang or Passport of Customer')
						 $('#existingProduct').css('display','block');
					} else {
						$('#existingProduct').css('display','none');
						$('#factor_main').empty();
						/*15 is the id of dynamic  preminum_type  which is coming from lookup data table*/
						if (data.product.premium_type_id === 15) {
							append += '<div class="col-lg-12">' +
									'<button type="button" id="calculate" class="btn btn-primary col-lg-2" style="float: left; clear: left;">Calculate Premium</button>' +
									' ' +
									'<div class="col-lg-10" id="premiumDiv" style="margin: 0.5% 0 0 20%;">' +
									'</div></div>';
							append += '<div class="col-lg-8" style="margin-top: 20px;">';
							if (data.product.sum_insured != null)
								append += '<h5>Sum Assured/Insured : P ' + data.product.sum_insured + '</h5>';
							else
								append += '<h5>Sum Assured/Insured Limit not set for this product.</h5>';
							append += '<input type="hidden" class="form-control" name="sum_assured" value="' + data.product.sum_insured + '" placeholder="Please enter Sum Assured/Insured">' +
									'</div>';
						}
					/* 11 is the id of manual preminum_type  which is coming from lookup data table */
						else if (data.product.premium_type_id === 11) {

							append += '<option value="">Select Plan</option>';
							if (data.plans.length > 0) {
								data.plans.forEach(function ($value) {
									if($('#selected_plan_id').val() == $value.id){
										selected = 'selected';
									}else{
										selected = '';
									}
									append += '<option value="' + $value.id + '" '+ selected + ' content="' + $value.sum_assured + '" >' + $value.name + ' | Premium : ' + $value.premium + ' | Sum Assured : ' + $value.sum_assured + '</option>';
								});
							}


						}
						$('#plan').html(append);
						$('#planID').hide();
						console.log(data.product.has_vehicle);
						if(data.product.has_vehicle == 1 || data.product.is_motor_items == 1){
							if(data.product.has_vehicle == 1){
								$('#vehicle_section').show();
								//$("#vehicle_section :input").not('.exc').addClass('required');
							}
							if(data.product.is_motor_items == 1){
								$('#motors').show();
							}
							$('#coverageDiv').show();
							$('#coverageDiv').html(data.coverageData);
						 } else {
							$('#vehicle_section').hide();
							$('#motors').hide();
							$('#coverageDiv').hide();
							$('#coverageDiv').html("");
						 }
						if (data.product.kyc_customer == 1) {
							$('#customerKYCDiv').show();
						} else {
							$('#customerKYCDiv').hide();
						}
						if (data.product.has_member == 1) {
							$('#addBeneficiaryDiv').show();
						 } else{
							$('#addBeneficiaryDiv').hide();
						}

					}

				}else {
					$('#factor_main').empty();
					$('#factor_main').append("Nothing to Show");
				}
			},error: function(){
              console.log("error"+data);
			}
		});

	});
	</script>
	@endpush
</x-app-layout>
