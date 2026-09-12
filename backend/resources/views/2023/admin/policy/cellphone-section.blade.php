
<h3 class="card-title align-items-start flex-column">
		<span class="card-label font-weight-bolder font-size-h4 text-dark-75">Cellphone Details:</span>
</h3>
<div class="row">
	<div class="col-lg-6">
		<div class="form-group">
			<label>Device Type</label>
			{{Form::select('deviceType',Helper::getDeviceType(),null,['class'=>'form-control  selectpicker','id'=>'deviceType'])}}
			<div class="error invalid-feedback"></div>
		</div>
		 <div class="form-group">
			<label class="device">Device Make : </label>
			<select class="form-control cell_phone_make" name="cell_phone_make">
				<option selected="" disabled="">Please select device make</option>
				<option value="Other">Other</option>
			</select>
		</div>
		<div class="form-group">
			<label>Value Of The Phone</label>
			{{Form::text('phone_value',null,['class'=>'form-control ','id'=>'phone_value',"placeholder"=>"Please enter Value of the phone"])}}
		</div>
	</div>
	<div class="col-lg-6">
		<div class="form-group">
			<label>IMEI</label>
			{{Form::text('imei',null,['class'=>'form-control','id'=>'imei',"placeholder"=>"Please enter IMEI"])}}
			<div class="error invalid-feedback"></div>
		</div>
		<div class="form-group">
			<label class="model">Device Model : </label>
			<select class="form-control cell_phone_model" name="cell_phone_model" title="Please select Cellphone Model">
				<option selected="" disabled="">Please select Device Model</option>
				<option value="Other">Other</option>
			</select>
		</div>
	</div>
</div>
<h3 class="card-title align-items-start flex-column">
		<span class="card-label font-weight-bolder font-size-h4 text-dark-75">Cellphone Pre-inspection Photos:</span>
</h3>
<div class="row">
	<div class="form-group row">
		<label class="col-xl-3 col-lg-3 col-form-label text-right">Cellphone Front</label>
		<div class="col-lg-9 col-xl-6">
			<div class="image-input image-input-empty image-input-outline" id="kt_image_5" style="background-image: url(asset('/img/200x200.png')">
				<div class="image-input-wrapper"></div>
				<label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow" data-action="change" data-toggle="tooltip" title="" data-original-title="Change image">
					<i class="fa fa-pen icon-sm text-muted"></i>
					<input type="file" name="profile_avatar" accept=".png, .jpg, .jpeg" />
					<input type="hidden" name="profile_avatar_remove" />
				</label>
				<span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow" data-action="cancel" data-toggle="tooltip" title="Cancel avatar">
					<i class="ki ki-bold-close icon-xs text-muted"></i>
				</span>
				<span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow" data-action="remove" data-toggle="tooltip" title="Remove avatar">
					<i class="ki ki-bold-close icon-xs text-muted"></i>
				</span>
			</div>
			<span class="form-text text-muted">Default empty input with blank image</span>
		</div>
	</div>
</div>
