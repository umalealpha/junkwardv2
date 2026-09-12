<!--begin::Portlet-->
<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                SMS/Email Logs
            </h3>
            <div style="position: absolute; right:0;">
                <a href="{{ route('admin.policy.generatePolicySmsEmailLogPdf',$policy->id) }}" target="_blank" class="btn btn-primary confirm-data" id="expot_pdf_data" style="color:#fff">Export</a>
            </div>
        </div>
    </div>
    <div class="kt-portlet__body">
        <!--begin::Section-->
        <table class="table table-striped table-bordered table-hover table-checkable" id="sms_email_table">
            <thead>
            <tr>
                <th>Type</th>
                <th>Message Id</th>
                <th>Message</th>
                {{-- <th>Content</th> --}}
                <th>Hook</th>
                {{-- <th>Attachments</th> --}}
                <th>Send to</th>
                <th>Sent Date</th>
                <!-- <th>Action</th> -->
            </tr>
            </thead>
        </table>
    </div>
</div>
<!--end::Portlet-->

