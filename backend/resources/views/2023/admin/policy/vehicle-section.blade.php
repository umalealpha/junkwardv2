<br>
<h3 class="card-title align-items-start flex-column">
		<span class="card-label font-weight-bolder font-size-h4 text-dark-75"> Vehicle Selection:</span>
</h3>
<div class="row">
	<div class="col-lg-6">
		<div class="form-group">
			<label>Vehicle Number </label>
			{{Form::text('vehiclePlate', (isset($vehicle)) ? $vehicle->vehiclePlate:'',['class'=>'form-control ','id'=>'vehiclePlate',"placeholder"=>"Enter vehicle number",'maxlength'=>'12','minlength'=>'12',"onkeypress"=>"return /[a-zA-Z0-9/_,. ]/i.test(event.key)"])}}
			  <span style="color: red;display:none;" id="vehicleError" >Policy already processed for this vehicle.</span>
		</div>
		<div class="form-group">
			<label>Purpose</label>
			{{Form::select('purpose',Helper::getLookUp('vehicle_purpose'),(isset($vehicle)) ? $vehicle->purpose:'',['class'=>'form-control','id'=>'purpose'])}}
		</div>

		 <div class="form-group">
			<label>Year of Manufacture</label>
			<select class="form-control" title="Please choose year" id="year" name="year" >
				<option value=""> Choose a year</option>
			</select>
		</div>

		<div class="form-group">
			<label>Engine Number </label>
			{{Form::text('engineNo', (isset($vehicle)) ? $vehicle->engineNo:'',['class'=>'form-control ','id'=>'engineNo',"placeholder"=>"Enter Engine Number",'maxlength'=>'20','minlength'=>'5',])}}
			  <span style="color: red;display:none;" id="engineError">Policy already processed for this
                                    engine number.</span>
		</div>
		<div class="form-group">
			<label>Is it imported?</label>
			<div class="radio-inline">
				<label class="radio radio-lg">
					<input type="radio" name="is_imported"  class="isimport" value="1" {{(isset($vehicle) && $vehicle->is_imported=="1") ? "checked" : ''}}>
					<span></span> Yes
				</label>
				<label class="radio radio-lg">
					<input type="radio" class="isimport" name="is_imported" value="0" {{(isset($vehicle) && $vehicle->is_imported=="0") ? "checked" : ''}}>
					<span></span> No
				</label>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="form-group">
			<label>Chassis Number(VIN Code)</label>
			{{Form::text('chassisNo',(isset($vehicle)) ? $vehicle->chassisNo:'',['class'=>'form-control ','id'=>'chassisNo',"placeholder"=>"Enter chassis number","onkeypress"=>"return /[a-zA-Z0-9/_,. ]/i.test(event.key)" ,'maxlength'=>'20','minlength'=>'5'])}}
			  <span style="color: red;display:none;" id="chasisError">Policy already processed for this
                                    chasis number.</span>
		 </div>

		 <div class="form-group">
			<label>Make</label>
			<select class="form-control" title="Please choose make" id="make" name="make" >
				<option value=""> Choose a Make</option>
			</select>
		</div>
		 <div class="form-group">
			<label>Model</label>
			<select class="form-control" title="Please choose model" id="model" name="model" >
				<option value=""> Choose a Model</option>
			</select>
		</div>
		<div class="form-group">
			<label>No Of Seats </label>
			{{Form::text('seats', (isset($vehicle)) ? $vehicle->seats:'',['class'=>'form-control ','id'=>'seats',"placeholder"=>"Enter No Of Seats",'maxlength'=>'1'])}}
		</div>
	</div>
</div>
<hr>
<br>
<h3 class="card-title align-items-start flex-column">
		<span class="card-label font-weight-bolder font-size-h4 text-dark-75"> Vehicle Images:</span>
</h3>
<div class="form-group row mt-3">
    <label class="col-lg-1 col-form-label text-lg-right">Left:</label>
    <div class="col-lg-2">
        <div class="image-input" id="kt_image_1">
            @if(isset($vehicle) && !empty($vehicle) && $vehicle->left!="")
                <div class="image-input-wrapper"
                     style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->left)}})"></div>
                <label
                    class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                    data-action="change" title="" data-original-title="Change avatar">
                    <i class="fa fa-pen icon-sm text-muted"></i>
                    <input type="file" class="exc" name="left" accept=".png, .jpg, .jpeg" />
                </label>
                <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                      data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
                </span>
            @else
                <div class="image-input-wrapper"
                     style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                <label
                    class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                    data-action="change" title="" data-original-title="Change avatar">
                    <i class="fa fa-pen icon-sm text-muted"></i>
                    <input type="file" class="exc" name="left" accept=".png, .jpg, .jpeg" />
                </label>
                <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                      data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
               </span>
            @endif
        </div>
    </div>
    <label class="col-lg-1 col-form-label text-lg-right">Right:</label>
    <div class="col-lg-2">
        <div class="image-input" id="kt_image_2">
            @if(isset($vehicle) && !empty($vehicle) && $vehicle->right!="")
                <div class="image-input-wrapper"
                     style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->right)}})"></div>
                <label
                    class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                    data-action="change" title="" data-original-title="Change avatar">
                    <i class="fa fa-pen icon-sm text-muted"></i>
                    <input type="file" class="exc" name="right" accept=".png, .jpg, .jpeg" />
                </label>
                <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                      data-action="cancel" title="Cancel avatar">
                    <i class="ki ki-bold-close icon-xs text-muted"></i>
                </span>
            @else
                <div class="image-input-wrapper"
                     style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                <label
                    class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                    data-action="change" title="" data-original-title="Change avatar">
                    <i class="fa fa-pen icon-sm text-muted"></i>
                    <input type="file" class="exc"  name="right" accept=".png, .jpg, .jpeg" />
                </label>
                <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                      data-action="cancel" title="Cancel avatar">
                    <i class="ki ki-bold-close icon-xs text-muted"></i>
                </span>
            @endif
        </div>
    </div>
    <label class="col-lg-1 col-form-label text-lg-right">Back:</label>
    <div class="col-lg-2">
        <div class="image-input" id="kt_image_3">
            @if(isset($vehicle) && !empty($vehicle) && $vehicle->back!="")
                <div class="image-input-wrapper"
                     style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->back)}})"></div>
                <label
                    class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                    data-action="change" title="" data-original-title="Change avatar">
                    <i class="fa fa-pen icon-sm text-muted"></i>
                    <input type="file" class="exc" name="back" accept=".png, .jpg, .jpeg" />
                </label>
                <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                      data-action="cancel" title="Cancel avatar">
                    <i class="ki ki-bold-close icon-xs text-muted"></i>
                </span>
            @else
                <div class="image-input-wrapper"
                     style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                <label
                    class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                    data-action="change" title="" data-original-title="Change avatar">
                    <i class="fa fa-pen icon-sm text-muted"></i>
                    <input type="file" class="exc" name="back" accept=".png, .jpg, .jpeg" />
                </label>
                <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                      data-action="cancel" title="Cancel avatar">
                    <i class="ki ki-bold-close icon-xs text-muted"></i>
                </span>
            @endif
        </div>
    </div>
	<label class="col-lg-1 col-form-label text-lg-right">Front:</label>
    <div class="col-lg-2">
			<div class="image-input" id="kt_image_4">
                @if(isset($vehicle) && !empty($vehicle) && $vehicle->front!="")
                    <div class="image-input-wrapper"
                        style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->front)}})"></div>
                        <label
                            class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                            data-action="change" title="" data-original-title="Change avatar">
                            <i class="fa fa-pen icon-sm text-muted"></i>
                            <input type="file" class="exc" name="front" accept=".png, .jpg, .jpeg" />
                        </label>
                        <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                              data-action="cancel" title="Cancel avatar">
                        <i class="ki ki-bold-close icon-xs text-muted"></i>
                    </span>
                @else
                    <div class="image-input-wrapper"
                        style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                    <label
                        class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                        data-action="change" title="" data-original-title="Change avatar">
                        <i class="fa fa-pen icon-sm text-muted"></i>
                        <input type="file" class="exc" name="front" accept=".png, .jpg, .jpeg" />
                    </label>
                    <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                        data-action="cancel" title="Cancel avatar">
                        <i class="ki ki-bold-close icon-xs text-muted"></i>
                    </span>
                @endif
            </div>
	</div>
</div>
<div class="form-group row mt-3">
    <label class="col-lg-1 col-form-label text-lg-right">Registration:</label>
    <div class="col-lg-2">
            <div class="image-input" id="kt_image_5">
                @if(isset($vehicle) && !empty($vehicle) && $vehicle->vehicleRegistration!="")
                    <div class="image-input-wrapper"
                         style="background-image: url({{ \Helper::getCloudFrontURL($vehicle->vehicleRegistration)}})"></div>
                    <label
                        class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                        data-action="change" title="" data-original-title="Change avatar">
                        <i class="fa fa-pen icon-sm text-muted"></i>
                        <input type="file" class="exc" name="vehicleRegistration" accept=".png, .jpg, .jpeg" />
                    </label>
                    <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                          data-action="cancel" title="Cancel avatar">
					<i class="ki ki-bold-close icon-xs text-muted"></i>
				</span>
                @else
                    <div class="image-input-wrapper"
                         style="background-image: url({{ asset('/img/200x200.png')}})"></div>
                    <label
                        class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                        data-action="change" title="" data-original-title="Change avatar">
                        <i class="fa fa-pen icon-sm text-muted"></i>
                        <input type="file" class="exc" name="vehicleRegistration" accept=".png, .jpg, .jpeg" />
                    </label>
                    <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                          data-action="cancel" title="Cancel avatar">
					<i class="ki ki-bold-close icon-xs text-muted"></i>
				    </span>
                @endif
            </div>
    </div>
</div>

{!! Form::hidden('selected_make_id', (isset($vehicle)) ? $vehicle->make: '', ['id'=>'selected_make_id'])!!}
{!! Form::hidden('selected_model_id', (isset($vehicle)) ? $vehicle->model: '', ['id'=>'selected_model_id'])!!}
{!! Form::hidden('selected_year', (isset($vehicle)) ? $vehicle->year: '', ['id'=>'selected_year'])!!}
{!! Form::hidden('has_vehicle', (isset($products)) ? $products->has_vehicle: '', ['id'=>'has_vehicle'])!!}
@push('scripts')
	<script>
	async function postData(url = '', data = {}) {
		const response = await fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				"Content-Type": "application/json",
				'Accept':'application/json',
				"X-CSRF-TOKEN": "{{csrf_token()}}"
			},
			body: JSON.stringify(data)
		  });
		return response.json();
	}


	$(document).ready(function() {
		$('.date-own').datepicker({
			minViewMode: 2,
			format: 'yyyy'
		});
		if($('.isimport').val()!=""){
			console.log("isimport");
			getMake();
		}
		if($('#make').val() != "" || $('#selected_make_id').val() != ""){
			console.log("year call--1");
		   getYear();
		}

		if($('#selected_year').val() != ""){
			getModel();
		}
		$('#make').change(function() {
			$('#selected_make_id').val("");
			$('#selected_year').val("");
			$('#model').val("");
			getYear();
		});
		$('#year').change(function() {
			$('#selected_year').val("");
			$('#selected_model_id').val("");
			getModel();
		});


		$('#vehiclePlate').on('change paste', function () {

			$('#vehicleError').hide();
			$(this).val($(this).val().toUpperCase());
			if($('#has_vehicle').val()!="" && $('#vehiclePlate').val()!=""){
				postData("{{ url('api/checkVehicle') }}", {
					"vehiclePlate": $(this).val(),
					"has_vehicle": $('#has_vehicle').val(),
				})
				.then(data => {
					if(data.count>0){
						$('#vehiclePlate').addClass("is-invalid");
						$('#vehicleError').show();
						$('#sub_but').prop('disabled', true);
					}else{
						$('#vehicleError').hide();
						$('#vehiclePlate').addClass("is-valid");
						$('#sub_but').prop('disabled', false);
					}
				}).catch((error) => {
					$('#vehicleError').html("please try after sometime");
					$('#vehiclePlate').addClass("is-invalid");
				});
			}
		});

		$('#engineNo').on('change paste', function () {//checkEngineNumber
			$('#engineError').hide();
			$(this).val($(this).val().toUpperCase());
			if($('#has_vehicle').val()!="" && $('#engineNo').val()!=""){
				postData("{{ url('api/checkEngineNumber') }}", {
					"engine_number": $(this).val(),
					"has_vehicle": $('#has_vehicle').val(),
				})
				.then(data => {

					if(data.count>0){
						$('#engineNo').addClass("is-invalid");
						$('#engineError').show();
						$('#sub_but').prop('disabled', true);
					}else{
						$('#engineError').hide();
						$('#engineNo').addClass("is-valid");
						$('#sub_but').prop('disabled', false);
					}
				}).catch((error) => {
					$('#engineError').html("please try after sometime");
					$('#engineNo').addClass("is-invalid");
				});
			}
		});

		$('#chassisNo').on('change paste', function () {//chassisNo
			$('#chasisError').hide();
			$(this).val($(this).val().toUpperCase());
			if($('#has_vehicle').val()!="" && $('#chassisNo').val()!=""){
				postData("{{ url('api/checkChasisNumber') }}", {
					"chasis_number": $(this).val(),
					"has_vehicle": $('#has_vehicle').val(),
				})
				.then(data => {
					if(data.count>0){
						$('#chassisNo').addClass("is-invalid");
						$('#chasisError').show();
						$('#sub_but').prop('disabled', true);
					}else{
						$('#chasisError').hide();
						$('#chassisNo').addClass("is-valid");
						$('#sub_but').prop('disabled', false);
					}
				}).catch((error) => {
					$('#chasisError').html("please try after sometime");
					$('#chassisNo').addClass("is-invalid");
				});
			}
		});

	});



	getMake = function(){
		$('#make').html('<option value="">Please Wait . .</option>');
		//console.log("checked make value"+$("input[name='is_imported']:checked").val());
		if($("input[name='is_imported']:checked").val()!=""){
			 if($("input[name='is_imported']:checked").val()=='0'){
				// console.log("checked value"+$('.isimport').val());
				postData("{{ url('api/frontend/getTTVehicleMakes') }}")
				.then(data => {
					var _html = '<option value="">-- Select --</option>';
					$.each(data.Makes, function(index, value) {

						if($('#selected_make_id').val() == value){
							_html+='<option value="'+value+'" selected>'+value+'</option>';
						}else{
							_html+='<option value="'+value+'">'+value+'</option>';
						}
						$('#make').html(_html);
					});
				}).catch((error) => {
					$('#make').html('<option value="">-- Select --</option>');

				});
			}
			if($("input[name='is_imported']:checked").val()=='1'){
				postData("{{ url('api/frontend/vehicleMake') }}")
				.then(data => {
					var _html = '<option value="">-- Select --</option>';
					$.each(data.makes, function(index, value) {
						if($('#selected_make_id').val() == this.s_Make){
							_html+='<option value="'+this.s_Make+'" selected>'+this.s_Make+'</option>';
						}else{
							_html+='<option value="'+this.s_Make+'">'+this.s_Make+'</option>';
						}
						$('#make').html(_html);
					});
				}).catch((error) => {
					$('#make').html('<option value="">-- Select --</option>');

				});
			}
		}

	}

	getYear = function(){
		$('#year').html('<option value="">Please Wait . .</option>');
		postData("{{ route('getYear') }}", { make: $('#make').val() })
		.then(data => {
			var _html = '<option value="">-- Select --</option>';
			$.each(data.year, function(index, value) {
				if($('#selected_year').val() == index){
					_html+='<option value="'+index+'" selected>'+value+'</option>';
				}else{
					_html+='<option value="'+index+'">'+value+'</option>';
				}
				$('#year').html(_html);
			});
		}).catch((error) => {
			$('#year').html('<option value="">-- Select --</option>');
		});
	}



	getModel = function(){
		$('#model').html('<option value="">Please Wait . .</option>');
		if($('#selected_year').val()!=""){
			var year=$('#selected_year').val();
			var make=$('#selected_make_id').val();
		}else{
			var year=$('#year').val();
			var make=$('#make').val();
		}

		//make: make,manufacturing_year:year
		if($("input[name='is_imported']:checked").val()!=""){
			if($("input[name='is_imported']:checked").val()=='0'){
				postData("{{ url('api/frontend/getTTVehicleModels') }}", { make: make })

				.then(data => {
					var _html = '<option value="">-- Select --</option>';
					$.each(data.Models, function(index, value) {
						if($('#selected_model_id').val().toUpperCase() == this.Model.toUpperCase()){
							_html+='<option value="'+this.Model+'" selected>'+this.Model+'</option>';
						}else{
							_html+='<option value="'+this.Model+'">'+this.Model+'</option>';
						}
						$('#model').html(_html);
					});
				}).catch((error) => {
					$('#model').html('<option value="">-- Select --</option>');

				});
			}
			if($("input[name='is_imported']:checked").val()=='1'){
				postData("{{ url('api/frontend/vehicleModel') }}", { vehicle_make: make })
				.then(data => {
					var _html = '<option value="">-- Select --</option>';
					$.each(data.makes, function(index, value) {
						if($('#selected_model_id').val().toUpperCase() == this.s_Variant.toUpperCase()){
							_html+='<option value="'+this.s_Variant+'" selected>'+this.s_Variant+'</option>';
						}else{
							_html+='<option value="'+this.s_Variant+'">'+this.s_Variant+'</option>';
						}
						$('#model').html(_html);
					});
				}).catch((error) => {
					$('#model').html('<option value="">-- Select --</option>');

				});
			}
		}

	}




	$("#purpose").change(function(){
        var selectedPurpose = $(this).children("option:selected").val();
        if(selectedPurpose == 29){
            $('.modal').modal('show');
			$('.modal-title').html("Transport Policy");
			$('.modal-body').html("We are not providing Transport Vehicle Policy for Now. Sorry, fot the In-convenience caused.");
            $("#purpose").val([]);
        }
        else{
            $('#purposeModal').modal('hide');
        }
    });

	$('.isimport').on('change',function(){
		$('#make').html('<option value="">Please Wait . .</option>');
		$('#year').html('<option value="">Please choose year .</option>');
		$('#model').html('<option value="">Please choose model .</option>');
		if($(this).val() == '0'){
			postData("{{ url('api/frontend/getTTVehicleMakes') }}")
			.then(data => {
				var _html = '<option value="">Please choose Make</option>';
				$.each(data.Makes, function(index, value) {
					_html+='<option value="'+value+'">'+value+'</option>';

					$('#make').html(_html);
				});
			}).catch((error) => {
				$('#make').html('<option value="">Please choose Make </option>');

			});
		}
		if($(this).val() == '1'){
			postData("{{ url('api/frontend/vehicleMake') }}")
			.then(data => {
				var _html = '<option value="">Please choose Make-</option>';
				$.each(data.makes, function(index, value) {
					_html+='<option value="'+this.s_Make+'">'+this.s_Make+'</option>';
					$('#make').html(_html);
				});
			}).catch((error) => {
				$('#make').html('<option value="">Please choose Make</option>');

			});
		}
	});






	</script>
@endpush
