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
                                        <img src="http://placehold.it/200x200" width="auto" style="max-height: 150px" >
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
                                    @endif
                                </td>
                            </tr>


                            <tr>
                                <th>Quotation Parts</th>
                                <td>
                                    @if($assessorData->quotations_parts == NULL)
                                        <img src="http://placehold.it/200x200" width="auto" style="max-height: 150px" >
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
                                    @endif

                                </td>
                            </tr>

                            <tr>
                                <th>Valuation</th>
                                <td>
                                    @if($assessorData->valuation == NULL)
                                        <img src="http://placehold.it/200x200" width="auto" style="max-height: 150px" >
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