	<div class="row">
			 <div class="col-lg-3" style="margin-bottom: 15px;">
				<div class="form-group">
					<label>Do you have Activation Code?</label>
					<div class="radio-inline">
						<label class="radio radio-lg">
							<input type="radio" checked="checked" name="check_activation" checked="checked" value="Yes"/>
						<span></span>Yes</label>
						<label class="radio radio-lg">
							<input type="radio" name="check_activation" checked="checked" value="No"/>
						<span></span>No</label>
					</div>
				</div>
			</div>
			 <div class="col-lg-9">
				<div class="form-group" id="input_activation" style="display:none;">
					<label>Activation Code</label>
					<input type="text" name="activation_code" id="activation_code"
						   class="form-control col-md-6" placeholder="Enter Activation Code">
				</div>
				<input type="button" id="generate_code" value="Generate Activation Code"
					   class="btn btn-primary col-md-3"
					   style="float: left; clear: left; display:none;">
				<div class="col-lg-9" id="codeDiv" style="margin: 0.5% 0 0 20%;"></div>
			</div>
		</div>
							