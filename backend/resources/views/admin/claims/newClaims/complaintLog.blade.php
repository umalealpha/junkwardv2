<div class="kt-portlet kt-portlet--tabs kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                Complaint Log
            </h3>
        </div>
        <div class="col-lg-5">
            <button class="btn btn-success" style="float:right; margin-top: 2%;  margin-right: 10px;" id="complaintLog" type="button" data-toggle="collapse" data-target="#complaintLogCollapse" aria-expanded="false" aria-controls="collapseExample"> Add Complaint </button>
        </div>
    </div>

    <div class="kt-portlet__body">
        <div class="tab-content">
            <div class="tab-pane fade active show" id="kt_portlet_tabs_1_1_1_content" role="tabpanel">
                {{-- <div class="kt-scroll" data-scroll="true" style="height: 420px;" data-mobile-height="350"> --}}
                    <!--Begin::Timeline -->
                    <div class="kt-timeline">
                    </div>
                    <div class="form-group row collapse" id="complaintLogCollapse">
                        <div class="col-md-12">
                            <div class="kt-checkbox-inline">
                                    <form id="addComplaintLog"  action="{{ url('admin/claims/storeComplaint/'.$claims->id) }}" method="POST"  class="kt-form">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                    <input type="hidden" name="claim_id" value="{!! $claims->id !!}" />
                                    <input type="hidden" name="policy_id" value="{!! $policy->id !!}" />

                                    <div class="kt-portlet__body">
                                        <div class="kt-widget-4">
                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-3 col-form-label">Complaint Of</label>
                                                <div class="col-9">
                                                    <select class="form-control kt_selectpicker" title="Please select complaint of"
                                                            data-live-search="true" name="complaint_of" >
                                                            <option value="Parts">Parts</option>
                                                            <option value="Assessors">Assessors</option>
                                                            <option value="Service Provider">Service Provider</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-3 col-form-label">Complaint Details</label>
                                                <div class="col-9">
                                                    <textarea type="text" class="form-control" name="complaint_details" value=""  placeholder="Enter complaint details"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @if ($claims && $claims->status != 'Closed')
                                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                    <div class="col-3"></div>
                                                    <div class="col-9" style="margin-left: 300px">
                                                        <button class="btn btn-brand" type="submit" id="addComplaintLog">Submit</button>
                                                        <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#complaintLogCollapse" aria-expanded="false" aria-controls="collapseExample"> Cancel </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </form>
                            </div>
                        </div>
                    </div>
                    <table class="table table-striped table-bordered table-hover table-checkable" id="complaint_table">
                        <thead>
                            <tr>

                                <th>Id</th>
                                <th>Added By</th>
                                <th>Complaint Of</th>
                                <th>Complaint Details</th>
                                <th>Added Date</th>
                            </tr>
                        </thead>
                    </table>
                    <!--End::Timeline 1 -->
                {{-- </div> --}}
            </div>
        </div>
    </div>
</div>
