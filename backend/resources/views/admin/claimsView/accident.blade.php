<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                Assessor Details
            </h3>
        </div>
    </div>
    <div class="kt-portlet__body">
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
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="200px" style="max-height: 150px" >
                                    @else  
                                      @if(is_array($assessorData))
                                        @foreach(unserialize($assessorData->assessment_report) as $file)
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($file) !!}" target="_blank" download>
                                                @if(pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                                                    <img src="{{asset('images/pdf.ico')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                                                    <img src="{{asset('images/word.ico')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                                                    <img src="{{asset('images/excel.png')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($file )}}" width="200px" style="max-height: 150px" >
                                                @else
                                                    <img src="{{asset('images/doc.png')}}" width="200px"style="max-height: 150px" >
                                                @endif
                                            </a> 
                                        @endforeach 
                                      @else   
                                         
                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($assessorData->assessment_report) !!}" target="_blank" download>
                                            @if(pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="200px" style="max-height: 150px" >
                                            @elseif(pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="200px" style="max-height: 150px" >
                                            @elseif(pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}" width="200px" style="max-height: 150px" >
                                            @elseif(pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->assessment_report, PATHINFO_EXTENSION) == 'png')
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($assessorData->assessment_report )}}" width="200px" style="max-height: 150px" >
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="200px" style="max-height: 150px">
                                            @endif
                                        </a> 
                                      @endif
                                    @endif
                                </td>
                            </tr>


                             <tr>
                                <th>Quotation Parts</th>
                                <td>
                                    @if($assessorData->quotations_parts == NULL)
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="200px" style="max-height: 150px" >
                                    @else 
                                         @if(is_array($assessorData))
                                            @foreach(unserialize($assessorData->quotations_parts) as $file)
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($file) !!}" target="_blank" download>
                                                    @if(pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="200px" style="max-height: 150px">
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="200px" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                                                        <img src="{{asset('images/excel.png')}}" width="200px" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($file )}}" width="200px" style="max-height: 150px" >
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="200px" style="max-height: 150px" >
                                                    @endif
                                                </a>
                                            @endforeach  
                                        @else    
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($assessorData->quotations_parts) !!}" target="_blank" download>
                                                @if(pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'pdf')
                                                    <img src="{{asset('images/pdf.ico')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'docm')
                                                    <img src="{{asset('images/word.ico')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'csv')
                                                    <img src="{{asset('images/excel.png')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->quotations_parts, PATHINFO_EXTENSION) == 'png')
                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($assessorData->quotations_parts )}}" width="200px" style="max-height: 150px" >
                                                @else
                                                    <img src="{{asset('images/doc.png')}}" width="200px" style="max-height: 150px">
                                                @endif
                                            </a> 
                                        @endif
                                    @endif

                                </td>
                            </tr>

                          <tr>
                                <th>Valuation</th>
                                <td>
                                    @if($assessorData->valuation == NULL)
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="200px" style="max-height: 150px" >
                                    @else 
                                        @if(is_array($assessorData))
                                            @foreach(unserialize($assessorData->valuation) as $file)
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($file) !!}" target="_blank" download>
                                                    @if(pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="200px" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="200px" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                                                        <img src="{{asset('images/excel.png')}}" width="200px" style="max-height: 150px" >
                                                    @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($file )}}" width="200px" style="max-height: 150px" >
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="200px" style="max-height: 150px" >
                                                    @endif
                                                </a>
                                            @endforeach  
                                        @else    
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($assessorData->valuation) !!}" target="_blank" download>
                                                    @if(pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="200px" style="max-height: 150px" >
                                                    @elseif(pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="200px" style="max-height: 150px" >
                                                    @elseif(pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'csv')
                                                        <img src="{{asset('images/excel.png')}}" width="200px" style="max-height: 150px" >
                                                    @elseif(pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->valuation, PATHINFO_EXTENSION) == 'png')
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($assessorData->valuation )}}" width="200px" style="max-height: 150px" >
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="200px" style="max-height: 150px">
                                                    @endif
                                                </a> 
                                         @endif                       
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Attached File</th>
                                <td>
                                    @if($assessorData->attached_file == NULL)
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="200px" style="max-height: 150px" >
                                    @else 
                                        @if(is_array($assessorData))
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($assessorData->attached_file) !!}" target="_blank" download>
                                                @if(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'pdf')
                                                    <img src="{{asset('images/pdf.ico')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'docm')
                                                    <img src="{{asset('images/word.ico')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'csv')
                                                    <img src="{{asset('images/excel.png')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'png')
                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($assessorData->attached_file )}}" width="200px" style="max-height: 150px" >
                                                @else
                                                    <img src="{{asset('images/doc.png')}}" width="200px" style="max-height: 150px">
                                                @endif
                                            </a> 
                                        @else  
                                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($assessorData->attached_file) !!}" target="_blank" download>
                                                @if(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'pdf')
                                                    <img src="{{asset('images/pdf.ico')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'docx' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'doc' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'docm')
                                                    <img src="{{asset('images/word.ico')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'xls' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'csv')
                                                    <img src="{{asset('images/excel.png')}}" width="200px" style="max-height: 150px" >
                                                @elseif(pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($assessorData->attached_file, PATHINFO_EXTENSION) == 'png')
                                                    <img src="{{\AlphaDirect\Helper::getCloudFrontURL($assessorData->attached_file )}}" width="200px" style="max-height: 150px" >
                                                @else
                                                    <img src="{{asset('images/doc.png')}}" width="200px" style="max-height: 150px">
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