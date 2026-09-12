<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
 <link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
 <link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
 <link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " >
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{asset('images/logo.png')}}"/>
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left" id="kt_aside_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more"></i></button>
    </div>
</div>
<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')

    </div>
     <!--If Password default -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Excel Import For Create Policies
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Excel</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <form action="{{ route('admin.policy.excelADGroupPoliciesUpload') }}" id="ExcelImportForm" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="form-group">
                                        <label for="policyNumber">Upload Excel Data</label>
                                        <input type="file" class="form-control" name="file" id="file" >
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="type">File Type</label>
                                            <select class="form-control" name="type" id="type" required>
                                                <option value="">-- Select Type --</option>
                                                <option value="employer">Employer Group File</option>
                                                <option value="membership">Membership File</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                                        <div class="kt-form__actions">
                                            <div class="row">
                                                <div class="col-3"></div>
                                                <div class="col-9">
                                                    <button type="submit" id="btn" value="Submit" class="btn btn-brand">Import</button>
                                                    <button type="button" id="processUploadsBtn" class="btn btn-success ml-2">Process Uploaded Files</button>
                                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                    <a class="btn btn-secondary" href="{{-- {{ route('admin.dashboard') }} --}}" >Cancel</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                                  <div class="col-lg-4">
                                <div class="form-group mt-4">
                                            <label>Download Excel Template :</label>
                                            <a href="{{ \AlphaDirect\Helper::getCloudFrontURL('Document/Policy_Document/policyCreate/json_to_exce.xlsx') }}" class="btn btn-info">Download Excel</a>
                                        </div>
                                </div>
                                <div>
                                    <!-- <h5>Sample excel sheet link:</h5> -->
                                    <!-- <span></span> -->
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
            <!-- end:: Content -->

        </div>
    @endif

    <!-- begin:: Footer -->
    @include('includes.footer')
    <!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->







</div>

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<style>
    .modal-backdrop.show{opacity:0.25}
    #processLogs{max-height:220px; overflow:auto; background:#0b0b0b; color:#e6e6e6; padding:10px; border-radius:4px; font-family:monospace; font-size:12px}
    .small-muted{font-size:12px; color:#6c757d}
    .progress-status{font-size:13px}
    .elapsed{font-variant-numeric: tabular-nums}
</style>

<!-- Processing Modal -->
<div class="modal fade" id="processModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Processing Uploaded Files</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <span class="progress-status" id="processStatus">Starting...</span>
                    <span class="small-muted elapsed" id="elapsed">00:00</span>
                </div>
                <div class="progress mb-2">
                    <div id="processBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="small-muted mb-2" id="modalHint">Please keep this dialog open while processing.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="closeProcessBtn" disabled>Close</button>
            </div>
        </div>
    </div>
    </div>

<script>
    $(document).ready(function(){
        $( "#ExcelImportForm" ).validate({
            rules: {
                file:{
                    required:true,
                }
            },
            messages:{
                file: {
                    required: "Please upload excel file."
                }
            }
        });

        function formatTime(totalSec){
            var m = Math.floor(totalSec/60).toString().padStart(2,'0');
            var s = (totalSec%60).toString().padStart(2,'0');
            return m+":"+s;
        }

        $('#processUploadsBtn').on('click', function(){
            var $btn = $(this);
            var $bar = $('#processBar');
            var $status = $('#processStatus');
            var $elapsed = $('#elapsed');
            var $close = $('#closeProcessBtn');
            var $hint = $('#modalHint');

            // Reset UI
            $btn.prop('disabled', true).text('Processing...');
            $bar.removeClass('bg-danger bg-success').addClass('progress-bar-striped progress-bar-animated');
            $bar.css('width','0%').attr('aria-valuenow',0);
            $status.text('Starting...');
            $elapsed.text('00:00');
            $close.prop('disabled', true);
            // Check if anything pending first
            $.get("{{ route('admin.policy.adGroupPendingCheck') }}", function(check){
                if (!check || !check.pending || Number(check.pending) === 0){
                    // Show modal with friendly message instead of progress
                    $status.text('No file/nothing to import');
                    $hint.text('Upload a Membership file first, then click Process.');
                    $bar.removeClass('progress-bar-animated bg-success bg-danger').addClass('bg-secondary');
                    $bar.css('width','0%').attr('aria-valuenow', 0);
                    $('#processModal').modal({backdrop:true, keyboard:true});
                    $close.prop('disabled', false);
                    $btn.prop('disabled', false).text('Process Uploaded Files');
                    return;
                }

                $('#processModal').modal({backdrop:'static', keyboard:false});

            // Animate progress while waiting for server
            var pct = 0;
            var t = 0;
            var tick = setInterval(function(){
                t++;
                $elapsed.text(formatTime(t));
                // Ease towards 95% while processing
                if (pct < 95) {
                    pct += Math.max(0.2, (95 - pct) * 0.03);
                    $bar.css('width', pct.toFixed(1)+'%').attr('aria-valuenow', pct.toFixed(0));
                }
                if (t === 2) $status.text('Validating...');
                if (t === 6) $status.text('Importing rows...');
                if (t === 12) $status.text('Calculating premiums...');
                if (t === 20) $status.text('Finalizing and generating report...');
            }, 500);

            // Start processing
            $.ajax({
                url: "{{ route('admin.policy.processAdGroupUploads') }}",
                type: 'POST',
                data: {_token: "{{ csrf_token() }}"},
                success: function(res){
                    $status.text(res.message || 'Processing started');
                    $bar.removeClass('progress-bar-animated');
                    $bar.css('width','100%').attr('aria-valuenow', 100).addClass('bg-success');
                },
                error: function(xhr){
                    var msg = 'Failed to process uploads';
                    if (xhr.responseJSON && xhr.responseJSON.message) { msg = xhr.responseJSON.message; }
                    $status.text(msg);
                    $bar.removeClass('progress-bar-animated').addClass('bg-danger');
                },
                complete: function(){
                    clearInterval(tick);
                    $btn.prop('disabled', false).text('Process Uploaded Files');
                    $close.prop('disabled', false);
                }
            });

            // Poll progress
            var poll = setInterval(function(){
                $.get("{{ route('admin.policy.adGroupProgress') }}", function(p){
                    if (!p) return;
                    // Update bar from real progress
                    var total = Number(p.total || 0);
                    var done = Number(p.processed || 0);
                    if (total > 0){
                        var perc = Math.min(100, Math.round((done/total) * 100));
                        $bar.css('width', perc+'%').attr('aria-valuenow', perc);
                    }
                    if (p.status === 'completed'){
                        $status.text('Completed');
                        $bar.removeClass('progress-bar-animated').addClass('bg-success');
                        $close.prop('disabled', false);
                        clearInterval(poll);
                    } else if (p.status === 'failed'){
                        $status.text(p.message || 'Failed');
                        $bar.removeClass('progress-bar-animated').addClass('bg-danger');
                        $close.prop('disabled', false);
                        clearInterval(poll);
                    } else if (p.status === 'processing'){
                        $status.text('Processing...');
                    } else if (p.status === 'queued'){
                        $status.text('Queued...');
                    }
                });
            }, 1000);
            }); // end pending check
        });
    });
</script>
</body>
<!-- end::Body -->
</html>
