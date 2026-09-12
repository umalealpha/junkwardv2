
@if(count($beneficiaries)>0)
	
	<div data-repeater-list="beneficiaries">
		@foreach($beneficiaries as $k=>$v)
			<br>
			<h3 class="card-title align-items-start flex-column">
				<span class="card-label font-weight-bolder font-size-h4 text-dark-75">  Beneficiary Info:</span>
			</h3>
			<div data-repeater-item class="row">
				<div class="col-lg-6">
					<div class="form-group">
						<label>Which beneficiary would you like to add ? </label>
						{{Form::text('beneficiaryRelation', (isset($v) && !empty( $v )) ? $v->relation : '', ['class'=>'form-control required','style'=>"text-transform: capitalize","placeholder"=>"beneficiary relation",'maxlength'=>'20'])}}
					</div>
					<div class="form-group">
						<label>Passport </label>
						{{Form::text('beneficiaryPassport',(isset($v) && !empty( $v )) ? $v->passport : '',['class'=>'form-control required',"placeholder"=>"passport"])}}
					</div>
					<div class="form-group">
						<label>Middle name </label>
						{{Form::text('beneficiaryMName', (isset($v) && !empty( $v )) ? $v->middle_name : '',['class'=>'form-control required','style'=>"text-transform: capitalize","placeholder"=>"Middle name"])}}
					</div>

					<div class="form-group">
						<label>Gender</label>
						<div class="radio-inline">
							<label class="radio radio-lg">
								<input type="radio" class="required" name="beneficiaryGender" value="1" {{(isset($v) && ($v->gender=='1')) ? "checked" : ""}}/>
							<span></span>Male</label>
							<label class="radio radio-lg">
								<input type="radio" class="required" name="beneficiaryGender" value="0" {{(isset($v) && ($v->gender=='0')) ? "checked" : ""}}/>
							<span></span>Female</label>
						</div>
					</div>
					<div class="form-group">
						<label>Payment(%) </label>
						{{Form::number('beneficiaryPayment',(isset($v) && !empty( $v )) ? $v->payment : '',['class'=>'form-control required beneficiaryPercentCalculate','id'=>'beneficiaryPayment_'.$k,"placeholder"=>"Payment(%)"])}}
					</div>
				</div>
				<div class="col-lg-6">
					<div class="form-group">
						<label>National Id </label>
						{{Form::text('beneficiaryOmang', (isset($v) && !empty( $v )) ? $v->omang : '',['class'=>'form-control required',"placeholder"=>"national id"])}}
					</div>
					<div class="form-group">
						<label>First name </label>
						{{Form::text('beneficiaryFName', (isset($v) && !empty( $v )) ? $v->first_name : '',['class'=>'form-control required','style'=>"text-transform: capitalize","placeholder"=>"beneficiary first name"])}}
					</div>
					<div class="form-group">
						<label>Last name </label>
						{{Form::text('beneficiaryLName', (isset($v) && !empty( $v )) ? $v->last_name : '',['class'=>'form-control required','style'=>"text-transform: capitalize","placeholder"=>"beneficiary last name"])}}
					</div>
					<div class="form-group">
						<label>Date Of Birth </label>
						{{Form::text('beneficiaryDOB',(isset($v)) ? \Carbon\Carbon::parse($v->dob)->format('d/m/Y') : '',['class'=>'form-control beneficiaryDOBReq','id'=>'beneficiaryDOB_',"placeholder"=>"beneficiary date of birth"])}}
					</div>
				</div>
				<div class="form-group  row">
					<div class="col-lg-offset-1 col-lg-4">
						<span data-repeater-delete class="btn btn-warning btn-sm"><i class="la la-close"></i>Remove </span>
					</div>
				</div>	
			</div>
			<br>
		@endforeach
	</div>	
	<br>
	<div class="row">
		<div class="col-lg-offset-1 col-lg-6">
			<span data-repeater-create class="btn btn-primary btn-sm" ><i class="la la-plus"></i>Add </span>
		</div>
	</div>	
	
@endif