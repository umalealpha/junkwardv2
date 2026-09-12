<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                Assessor Details
            </h3>
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="form-group row">
            <div class="col-md-12">
                <div class="kt-checkbox-inline">
                    <form id="assessorForm"  action="{{ url('admin/claims/assessorUpload/'.$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">

                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                        <div class="kt-portlet__body">
                            <div class="kt-widget-4">
                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Assessor</label>
                                    <div class="col-9">
                                        <select class="form-control kt_selectpicker" title="Please select assessor"
                                                data-live-search="true" name="assessor" >
                                            @foreach($assessors as $assessor)
                                                <option value="{{$assessor->id}}">{{$assessor->firstName}} {{$assessor->lastName}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label"> Do you want to send mail to the attorney?</label>
                                        <div class="col-9">
                                            <label class="kt-checkbox kt-checkbox--brand">
                                                <input id="attorneyValue" class="attorneyValue" type="checkbox" name="attorneyValue" value="1">
                                                <span></span><p style="color:red;" class="attorneyMsg" hidden>Attorney is not allocated for this claim.</p>
                                            </label>
                                        </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-form-label col-lg-3 col-sm-12">File Attachment</label>
                                        <div class="col-lg-4 col-md-9 col-sm-12">
                                        <div class="kt-avatar" id="attachFile" style="float: left; clear: left;">
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Select File to be attached in Email.">
                                                <i class="fa fa-pen"></i>
                                                <input type='file' class="left" name="attachFile" id="attachFile" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                            </label>
                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                        </div>
                                        </div>
                            </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9" style="margin-left: 300px">
                                        <button class="btn btn-brand" type="button" id="Btn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <button class="btn btn-brand" id="mail" type="submit">Send Mail</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="kt-section">
            <div class="kt-section__content">
                <table class="table supplierTable table-head-noborder">
                    <tbody>
                    @if(count($claimAssessmentCount))
                        @foreach($claimAssessmentCount as $key => $assessorData)
                            <tr>
                                <th>Assessment Report</th>
                                <td>
                                    @if($assessorData->assessment_report == NULL)
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="auto" style="max-height: 150px" >
                                    @else
                                        @foreach(json_decode($assessorData->assessment_report,true) as $file)
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($file) !!}" target="_blank" download>
                                                @if(pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                                                    <img src="{{asset('images/pdf.ico')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                                                    <img src="{{asset('images/word.ico')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                                                    <img src="{{asset('images/excel.png')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($file)}}" width="auto" style="max-height: 150px" >
                                                @else
                                                    <img src="{{asset('images/doc.png')}}" width="auto"style="max-height: 150px" >
                                                @endif
                                            </a>
                                        @endforeach

                                           {{-- <a href="{!! str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($assessorData->assessment_report) !!}" target="_blank" download>
                                            @if(pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="auto" style="max-height: 150px" >
                                            @elseif(pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="auto" style="max-height: 150px" >
                                            @elseif(pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}" width="auto" style="max-height: 150px" >
                                            @elseif(pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'png')
                                                <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($assessorData->assessment_report )}}" width="auto" style="max-height: 150px" >
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="auto" style="max-height: 150px">
                                            @endif
                                        </a>--}}

                                    @endif
                                </td>
                            </tr>


                             <tr>
                                <th>Quotation Parts</th>
                                <td>
                                    @if($assessorData->quotations_parts == NULL)
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="auto" style="max-height: 150px" >
                                    @else
                                            @foreach(json_decode($assessorData->quotations_parts,true) as $file)
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($file) !!}" target="_blank" download>
                                                    @if(pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="auto" style="max-height: 150px">
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="auto" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                                                        <img src="{{asset('images/excel.png')}}" width="auto" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($file)}}" width="auto" style="max-height: 150px" >
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="auto" style="max-height: 150px" >
                                                    @endif
                                                </a>
                                            @endforeach

                                            {{--<a href="{!! str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($assessorData->quotations_parts) !!}" target="_blank" download>
                                                @if(pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'pdf')
                                                    <img src="{{asset('images/pdf.ico')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'docm')
                                                    <img src="{{asset('images/word.ico')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'csv')
                                                    <img src="{{asset('images/excel.png')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'png')
                                                    <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($assessorData->quotations_parts )}}" width="auto" style="max-height: 150px" >
                                                @else
                                                    <img src="{{asset('images/doc.png')}}" width="auto" style="max-height: 150px">
                                                @endif
                                            </a>--}}
                                    @endif

                                </td>
                            </tr>

                          <tr>
                                <th>Valuation</th>
                                <td>
                                    @if($assessorData->valuation == NULL)
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="auto" style="max-height: 150px" >
                                    @else
                                            @foreach(json_decode($assessorData->valuation,true) as $file)
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($file) !!}" target="_blank" download>
                                                    @if(pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="auto" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="auto" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                                                        <img src="{{asset('images/excel.png')}}" width="auto" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($file)}}" width="auto" style="max-height: 150px" >
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="auto" style="max-height: 150px" >
                                                    @endif
                                                </a>
                                            @endforeach
                                                {{--<a href="{!! \AlphaDirect\Helper::getCloudFrontURL($assessorData->valuation) !!}" target="_blank" download>
                                                    @if(pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="auto" style="max-height: 150px" >
                                                    @elseif(pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="auto" style="max-height: 150px" >
                                                    @elseif(pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'csv')
                                                        <img src="{{asset('images/excel.png')}}" width="auto" style="max-height: 150px" >
                                                    @elseif(pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'png')
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($assessorData->valuation )}}" width="auto" style="max-height: 150px" >
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="auto" style="max-height: 150px">
                                                    @endif
                                                </a>--}}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Attached File</th>
                                <td>
                                    @if($assessorData->attached_file == NULL)
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="auto" style="max-height: 150px" >
                                    @else
                                        @if(is_array($assessorData))
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($assessorData->attached_file) !!}" target="_blank" download>
                                                @if(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'pdf')
                                                    <img src="{{asset('images/pdf.ico')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'docm')
                                                    <img src="{{asset('images/word.ico')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'csv')
                                                    <img src="{{asset('images/excel.png')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'png')
                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($assessorData->attached_file)}}" width="auto" style="max-height: 150px" >
                                                @else
                                                    <img src="{{asset('images/doc.png')}}" width="auto" style="max-height: 150px">
                                                @endif
                                            </a>
                                        @else
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($assessorData->attached_file) !!}" target="_blank" download>
                                                @if(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'pdf')
                                                    <img src="{{asset('images/pdf.ico')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'docm')
                                                    <img src="{{asset('images/word.ico')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'csv')
                                                    <img src="{{asset('images/excel.png')}}" width="auto" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'png')
                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($assessorData->attached_file)}}" width="auto" style="max-height: 150px" >
                                                @else
                                                    <img src="{{asset('images/doc.png')}}" width="auto" style="max-height: 150px">
                                                @endif
                                            </a>
                                        @endif
                                    @endif
                                </td>
                            </tr>

                            <tr>
                                <th>Notes </th>
                                <td>
                                   {{ $assessorData->notes }}
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" style="text-align: center;">No Assessor reports found</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>