
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Date of Damage</label>
                            <input id="incidentDate" class="form-control kt_datepicker_1" type="text" name="incidentDate" required autocomplete="off">
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group" >
                            <label style="margin-left: 130px;">Damage Extent</label>
                            <div class="kt-radio-inline"  style="margin-left: 130px;">
                                <label class="kt-radio" style="margin-top: 5px">
                                    <input type="radio" name="extent" value="Cracked">
                                    Cracked <span></span>
                                </label>
                                <label class="kt-radio">
                                    <input type="radio" name="extent" value="Shattered">
                                    Shattered <span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Cause of Damage</label>
                            <div class="form-group form-group-last">
                                <textarea class="form-control" name="cause" id="cause" rows="3" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <div class="form-group">
                                <label for="exampleSelect1">Date of claim registered</label>
                                <input type="text" class="form-control  kt_datepicker_1"   placeholder="Please select date" name="registered_claim" autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>


                <!--begin::Portlet-->

                <div class="kt-portlet">
                    <div class="kt-invoice-v2">
                        <div class="kt-invoice-v2__header grid">
                            <div class="kt-invoice-v2__header-right">
                                <div class="kt-invoice-v2__logo thumb">
                                    <h3>Before</h3>
                                    <div class="row">
                                        <div class="col-lg-3" >
                                            <h3 class="col-form-label" style="float: left;">Front Side</h3>
                                            <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle && $vehicle->front != NULL)
                                                    <img style="height:72.5%" src="{{ \AlphaDirect\Helper::getCloudFrontURL($policy->policyDocument) }}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img style="height:72.5%" src="http://placehold.it/300x300" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>
                                        </div>
                                        <div class="col-lg-3">
                                            <h3 class="col-form-label" style="float: left;">Back Side</h3>
                                            <a class="thumbnail" href="#" id="backViewModal" data-image-id="backView" data-toggle="modal" data-title="This is my title" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle && $vehicle->back != NULL)
                                                <img style="height:72.5%" src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->back) }}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img  style="height:72.5%" src="http://placehold.it/300x300" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>
                                        </div>
                                        <div class="col-lg-3">
                                            <h3 class="col-form-label" style="float: left;">Right Side</h3>
                                            <a class="thumbnail" href="#" id="rightViewModal" data-image-id="rightView" data-toggle="modal" data-title="This is my title" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle && $vehicle->right != NULL)
                                                <img style="height:72.5%" src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->right) }}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img style="height:72.5%" src="http://placehold.it/300x300" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>

                                        </div>
                                        <div class="col-lg-3">
                                            <h3 class="col-form-label" style="float: left;">Left Side</h3>
                                            <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle && $vehicle->left != NULL)
                                                <img style="height:72.5%" src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->left) }}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img style="height:72.5%" src="http://placehold.it/300x300" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="myModal" class="modal">
                                <span class="close" id="close">&times;</span>
                                <img class="modal-content" id="img01">
                                {{-- <div id="caption"></div>--}}
                            </div>


                            <div class="kt-invoice-v2__header-left">
                                <div class="kt-invoice-v2__logo thumb">
                                    <h3>After</h3>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <h3 class="col-form-label" style="float: left;">Front Side</h3>
                                            <div class="kt-avatar" id="incidentFront" style="float: left; clear: left;">
                                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                    <i class="fa fa-pen"></i>
                                                    <input type='file' class="incidentFront" name="incidentFront" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                </label>
                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                            </div>
                                            <textarea class="form-control"  style="margin-top: 170px;" placeholder="Add Description" name="front_image_description" id="front_image_description"></textarea>
                                        </div>
                                        <div class="col-md-3">
                                            <h3 class="col-form-label" style="float: left;">Back Side</h3>
                                            <div class="kt-avatar" id="incidentBack" style="float: left; clear: left;">
                                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                    <i class="fa fa-pen"></i>
                                                    <input type='file' class="incidentBack" name="incidentBack" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                </label>
                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                            </div>
                                            <textarea  style="margin-top: 170px;" class="form-control" placeholder="Add Description" name="back_image_description" id="back_image_description"></textarea>

                                        </div>

                                        <div class="col-md-3">
                                            <h3 class="col-form-label" style="float: left;">Right Side</h3>
                                            <div class="kt-avatar" id="incidentRight" style="float: left; clear: left;">
                                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                    <i class="fa fa-pen"></i>
                                                    <input type='file' class="incidentRight" name="incidentRight" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                </label>
                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                            </div>
                                            <textarea class="form-control"  style="margin-top: 170px;" placeholder="Add Description" name="right_image_description" id="right_image_description"></textarea>
                                        </div>

                                        <div class="col-md-3">
                                            <h3 class="col-form-label" style="float: left;">Left Side</h3>
                                            <div class="kt-avatar" id="incidentLeft" style="float: left; clear: left;">
                                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                    <i class="fa fa-pen"></i>
                                                    <input type='file' class="incidentLeft" name="incidentLeft" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                </label>
                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                            </div>
                                            <textarea class="form-control"  style="margin-top: 170px;" placeholder="Add Description" name="left_image_description" id="left_image_description"></textarea>
                                        </div>
                                    </div>
                                    @if($policy->kyc_recipient)
                                        @include('admin.policy.recipient_kyc')
                                    @endif
                                    <div class="form-group row" style="padding-top:30px;">
                                        <label for="example-text-input" class="col-3 col-form-label" style="padding-left: 2.5%;">Does customer selected supplier?</label>
                                        <div class="col-3">
                                            <span class="kt-switch" >
                                                <label>
                                                <input id="vehicleValue" type="checkbox" name="customer_selected_supplier" value="1"
                                               onchange="vehicleMsg()">
                                                        <span style="margin-top: 5px;margin-left: 10px;"></span>
                                                    <h4 id="Msg" style="display:inline;float:left;margin-top: 9px;margin-left: 5px;color:#ff4d4d;">No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="kt-portlet__body" id="sup" style="display: none;">
                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">Supplier Name:</label>
                                            <div class="col-9">
                                                <input class="form-control" name="sname"  placeholder="Enter Supplier Name" title="Supplier Name is required">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">Supplier Type:</label>
                                            <div class="col-9">
                                                <select name="stype"  class="form-control kt_selectpicker" title="Select supplier type" data-live-search="true">
                                                    @foreach($supplierTypes as $supplierType)
                                                        <option value="{{ $supplierType->value }}">{{ $supplierType->value }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">VAT No. :</label>
                                            <div class="col-9">
                                                <input  class="form-control" name="vat"  placeholder="Enter VAT Number" title="Supplier VAT No. is required">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">Supplier Contact Name:</label>
                                            <div class="col-9">
                                                <input type="text" class="form-control" name="snumber"  placeholder="Enter Contact Number" title="Supplier contact number is required">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">Supplier Email:</label>
                                            <div class="col-9">
                                                <input type="text" class="form-control" name="semail"  placeholder="Enter Supplier Email" title="Supplier Email address is required">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="example-text-input" class="col-3 col-form-label">Supplier Location:</label>
                                            <div class="col-9">
                                                <input type="text" class="form-control" name="slocation"  placeholder="Enter Supplier Location" title="Supplier location is required">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Portlet-->
                <div class="kt-portlet__foot kt-portlet__foot--solid">
                    <div class="kt-form__actions">
                        <div class="row">
                            <div class="col-3"></div>
                            <div class="col-9">
                                <button type="submit"  value="Submit" class="btn btn-brand">Submit</button>
                                <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>


