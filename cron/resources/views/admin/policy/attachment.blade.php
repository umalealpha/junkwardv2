<!--begin::Portlet-->
<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                Attachments
            </h3>
        </div>
        {{-- @if ($claims && $claims->status != 'Closed') --}}
            <div class="col-lg-5">
                <button class="btn btn-brand" style="float:right; margin-top: 2%;  margin-right: 10px;" id="attachmentUpload" type="button" data-toggle="collapse" data-target="#attachment" aria-expanded="false" aria-controls="collapseExample"> Add </button>
            </div>
        {{-- @endif --}}
    </div>
    <div class="kt-portlet__body">
        <div class="form-group row collapse" id="attachment">
            <div class="col-md-12">
                <div class="kt-checkbox-inline">
                    <form action="{{ url('admin/policy/attachmentUpload/'.$policy->id) }}" method="POST" id="attachmentForm" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" id="policy_id" value= "{{ $policy->id }}" />

                        <div class="kt-portlet__body" id="attachmentDiv">
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Name</label>
                                <div class="col-6">
                                    <input type="text" class="form-control" name="name[0]"  title="Name is required" placeholder="Enter Name" required>
                                </div>
                            </div>
                            <div class="form-group row DivCountAttachment">
                                <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                <label for="example-text-input" class="col-3 col-form-label">Document Type</label>
                                <div class="col-6">
                                    <select class="form-control kt_selectpicker" data-live-search="true" title="Please Select document type" name="type[]" required>
                                        @foreach($fileNames as $fileName)
                                            <option value="{!! $fileName->value !!}">{!! $fileName->value !!}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Attachment</label>
                                <div class="col-lg-9">
                                    <div class="input-group 0">
                                        <div class="kt-avatar multiple_file_avatar" style="float: left; clear: left;" id="typeDiv">
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                <i class="fa fa-pen"></i>
                                                <input type='file' name="attachment_file[0][]" />
                                            </label>
                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel avatar"> <i class="fa fa-times"></i> </span>
                                        </div>
                                    </div>
                                    <div class="kt-separator kt-separator--space-sm"></div>
                                    <div>
                                        <span class="btn btn-info btn-sm addAttachment"> <i class="la la-plus"></i> Add </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="btn btn-info btn-sm" id="addButton"> <i class="la la-plus"></i> Add New Attachment </span>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9" style="margin-left: 300px">
                                        <button class="btn btn-brand" type="submit" id="attachmentsbtBtn">Submit</button>
                                        <button class="btn btn-brand" type="button" id="attachmentloadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!--begin::Section-->
        <table class="table table-striped table-bordered table-hover table-checkable" id="attatchment_table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Document Type</th>
                <th>Attachment</th>
                <th>Action</th>
            </tr>
            </thead>
        </table>
    </div>
</div>
<!--end::Portlet-->

