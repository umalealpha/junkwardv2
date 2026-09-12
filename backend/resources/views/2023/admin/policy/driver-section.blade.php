
@if(count($policyDriver)>0)

	<div data-repeater-list="drivers">
	@foreach($policyDriver as $k=>$v)
		<br>
		<h3 class="card-title align-items-start flex-column">
			<span class="card-label font-weight-bolder font-size-h4 text-dark-75">  Policy Driver Info:</span>
		</h3>
		<div data-repeater-item class="row">
			<div class="col-lg-6">
				<div class="form-group">
					<label>First name </label>
					{{Form::text('driverFName', (isset($v) && !empty( $v )) ? $v->first_name : '',['class'=>'form-control required','style'=>"text-transform: capitalize","placeholder"=>"driver first name"])}}
				</div>
				<div class="form-group">
					<label>Last name </label>
					{{Form::text('driverLName', (isset($v) && !empty( $v )) ? $v->last_name : '',['class'=>'form-control required','style'=>"text-transform: capitalize","placeholder"=>"driver last name"])}}
				</div>
				<div class="form-group">
					<label>Cellphone Number </label>
					{{Form::text('driverCellPhone', (isset($v) && !empty( $v )) ? $v->cellphone : '',['class'=>'form-control required',"placeholder"=>"driver cellphone"])}}
				</div>
				<div class="form-group">
					<label>Driving License Number </label>
					{{Form::text('driverLicense', (isset($v) && !empty( $v )) ? $v->license : '',['class'=>'form-control required ',"placeholder"=>"driver driving license number"])}}
				</div>
				<div class="form-group">
					<label>Identification Number </label>
					{{Form::text('driverId', (isset($v) && !empty( $v )) ? $v->id_number : '',['class'=>'form-control required',"placeholder"=>"driver identification number"])}}
				</div>
			</div>
			<div class="col-lg-6">
				<div class="form-group">
					<label>Middle name </label>
					{{Form::text('driverMName', (isset($v) && !empty( $v )) ? $v->middle_name : '',['class'=>'form-control exc','style'=>"text-transform: capitalize","placeholder"=>"Middle name"])}}
				</div>
				<div class="form-group">
					<label>Gender</label>
					<div class="radio-inline">
						<label class="radio radio-lg">
							<input type="radio" class="driverGenderReq" id="driverGender_" placeholder="Gender" name="driverGender" value="1" {{(isset($v) && ($v->gender=='1')) ? "checked" : ""}}/>
						<span></span>Male</label>
						<label class="radio radio-lg">
							<input type="radio" class="driverGenderReq"  id="driverGender_" placeholder="Gender" name="driverGender" value="0" {{(isset($v) && ($v->gender=='0')) ? "checked" : ""}}/>
						<span></span>Female</label>
					</div>
				</div>

				<div class="form-group">
					<label>Date Of Birth </label>
					{{Form::text('driverDOB',(isset($v)) ? \Carbon\Carbon::parse($v->dob)->format('d/m/Y') : '',['class'=>'form-control driverDOBReq','id'=>'driverDOB_',"placeholder"=>"Date Of Birth"])}}
				</div>
				<div class="form-group">
					<label>Driving License Valid Till </label>
					{{Form::text('driverLicenseValid',(isset($v)) ? \Carbon\Carbon::parse($v->dob)->format('d/m/Y') : '',['class'=>'form-control driverLicenseValidReq','id'=>'driverLicenseValid_',"placeholder"=>"driver license expiry date"])}}
				</div>
			</div>
			<div class="form-group row">
				<div class="col-lg-offset-1 col-lg-8">
					<span data-repeater-delete class="btn btn-warning btn-sm"><i class="la la-close"></i>Remove </span>
				</div>
			</div>
		</div>

		<br>

	@endforeach
	</div>
	<br>
	<div class="form-group row">
		<div class="col-lg-offset-1 col-lg-4">
			<span data-repeater-create class="btn btn-primary btn-sm" ><i class="la la-plus"></i>Add </span>
		</div>
	</div>

@endif

