<!--begin::Portlet-->
<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                Supplier Quotes
            </h3>
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="form-group row collapse" id="Suppliercollapse">
            <div class="col-md-12">
                <div class="kt-checkbox-inline">
                    <form action="{{ url('admin/claims/accidentSupplier/'.$claims->id) }}" id="accidentSupplierForm" method="POST" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                        <div class="kt-portlet__body" id="supplierDiv">
                            <div class="form-group row DivCount">
                                <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                <label for="example-text-input" class="col-3 col-form-label">Suppliers</label>
                                <div class="col-6">
                                    <select class="form-control kt_selectpicker" data-live-search="true" title="Please Select Supplier" name="supplier_id[0]" required>
                                        @foreach($suppliers as $supplier)
                                            <option value="{!! $supplier->id !!}">{!! $supplier->supplierName !!}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Additional File</label>
                                <div class="col-lg-9">
                                    <div class="input-group appendFileDiv 0">
                                        <div class="kt-avatar multiple_file_avatar 0" style="float: left; clear: left;" id="accident_file">
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                <i class="fa fa-pen"></i>
                                                <input type='file' class="accident_file" name="accident_file[0][]" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                            </label>
                                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                        </div>
                                    </div>
                                    <div class="kt-separator kt-separator--space-sm"></div>
                                    <div>
                                        <span class="btn btn-info btn-sm addSupplierFile"> <i class="la la-plus"></i> Add </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="btn btn-info btn-sm" id="addSupplierButton"> <i class="la la-plus"></i> Add Supplier Request </span>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9" style="margin-left: 300px">
                                        <button class="btn btn-brand" type="submit" id="quoteSbtBtn">Submit</button>
                                        <button class="btn btn-brand" type="button" id="quoteloadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#Suppliercollapse" aria-expanded="false" aria-controls="collapseExample"> Cancel </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="form-group row collapse" id="POcollapse">
            <div class="col-md-12">
                <div class="kt-checkbox-inline">
                    <form id="POUpdate"  action="{{ url('admin/claims/poUpload/'.$claims->id) }}" method="POST"  enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" name="dynamicSupplierId" id="dynamicSupplierId" value="" />
                        <input type="hidden" name="send_submit" id="send_submit" value="0" />

                        <div class="kt-portlet__body">
                            <div class="kt-widget-4">
                                <div class="form-group row">
                                    <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                    <label for="example-text-input" class="col-3 col-form-label">Upload PO</label>
                                    <div class="col-2">
                                        <div class="kt-avatar" id="po" style="float: left; clear: left;">
                                            @if($claims->po == NULL)
                                                <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claims->po) !!}" target="_blank" download>
                                                    @if(pathinfo($claims->po, PATHINFO_EXTENSION) == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                    @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'docx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'doc' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                    @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'xls' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'csv')
                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                    @elseif(pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'jpg' || pathinfo($claims->po, PATHINFO_EXTENSION) == 'png')
                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claims->po )}}" width="100%" height="auto" >
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                    @endif
                                                </a>
                                            @endif
                                            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                <i class="fa fa-pen"></i>
                                                <input type='file'  name="po" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                            </label>
                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9" style="margin-left: 300px">
                                        <button class="btn btn-brand" type="submit" id="poSbtBtn">Submit</button>
                                        <button class="btn btn-brand" type="button" id="poloadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <button class="btn btn-success" id="toshow" type="submit" style="display:none">Submit & Send</button>
                                        <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#POcollapse" aria-expanded="false" aria-controls="collapseExample"> Cancel </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!--begin::Section-->
        <div class="kt-section">
            <div class="kt-section__content">
                <table class="table supplierTable table-head-noborder">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Supplier</th>
                        <th>File</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if(count($supplierQuotes))
                        @foreach($supplierQuotes as $key => $quote)
                            <tr>
                                <th scope="row">{!! $key+1 !!}</th>
                                <td>{!! $quote->supplier->supplierName !!}</td>
                                <td>
                                    @if($quote->claim_file == NULL)
                                        <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="200px" height="auto" >
                                    @else
                                        @foreach(unserialize($quote->claim_file) as $file)
                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($file) !!}" target="_blank" download>
                                            @if(pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="200px" height="auto" >
                                            @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="200px" height="auto" >
                                            @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}" width="200px" height="auto" >
                                            @elseif(pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($file )}}" width="200px" height="auto" >
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="200px" height="auto" >
                                            @endif
                                        </a>
                                    @endforeach
                                    @endif

                                </td>
                                <td>@if($quote->total != NULL)P {!! $quote->total !!} @else - @endif</td>
                                <td>
                                    @if($quote->total == NULL)
                                        <span class="kt-font-bold kt-font-danger">Not Received</span>
                                    @elseif($quote->total != NULL && $quote->status == 0)
                                        <span class="kt-font-bold kt-font-brand">Quote Received</span>
                                    @else <span class="kt-font-bold kt-font-success">PO SENT</span>
                                    @endif
                                </td>
                                <td>
                                    @if($quote->total != NULL)
                                        @if($quote->status != 1)
                                            <a href="{!! route('admin.claims.acceptQuote',['quote_id'=>$quote->id]) !!}" class="btn btn-sm btn-success @if($claims->po == NULL) POModal @endif">Send PO</a>
                                        @endif
                                        <a target="_blank" href="{!! route('admin.supplier.quoteView',['quote_id'=>$quote->id]) !!}" class="btn btn-sm btn-danger">View</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" style="text-align: center;">No Quote Request Sent</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
        <!--end::Section-->
    </div>
</div> 
<!--end::Portlet-->