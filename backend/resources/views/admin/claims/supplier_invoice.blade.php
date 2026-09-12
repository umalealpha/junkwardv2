
<!DOCTYPE html>

<html lang="en" >
<!-- begin::Head -->

@include('Agents.Layout.header')
<link href="{{asset('css/app/custom/invoice/invoice-v2.default.css')}}" rel="stylesheet" type="text/css" />




<!-- end::Head -->
<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-page--loading" >

<div class="col-lg-11 col-xl-11 order-lg-11 order-xl-1" style="margin-left:5%;margin-top:5%">
    <!--begin::Portlet-->
    <div class="kt-portlet">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <span class="kt-portlet__head-icon"> <i class="la  la-barcode "></i> </span>
                <h3 class="kt-portlet__head-title">
                    {{$supplierDetails->supplierName}} {{$vehicleDetails->vehiclePlate}} <small>Glass Quote</small>
                </h3>
            </div>
            <div class="kt-portlet__head-toolbar">
                <div class="kt-portlet__head-group">
                </div>
            </div>
        </div>
        <div class="kt-portlet__body">

        
            <!--If Password default, show edit details --> 
       
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="row">
                    <div class="col-xl-12">
                        <!--begin::Portlet-->
                        <div class="kt-portlet">
                            <div class="kt-invoice-v2" style="padding:0px 0px 0px">
                                <div class="kt-invoice-v2__header">
                                    <div class="kt-invoice-v2__header-left">
                                        <h1 class="kt-invoice-v2__title">
                                            {{$vehicleDetails->make}} {{$vehicleDetails->model}} Glass Quote
                                        </h1>
                                    </div>
                                    <div class="kt-invoice-v2__header-right">
                                        {{--<div class="kt-invoice-v2__logo">
                                            <img src="{{asset('/'.$supplierDetails->image)}}" class="kt-invoice-v2__logoimage" title="Invoice" alt="Invoice"/>
                                        </div>--}}
                                        <div class="kt-invoice-v2__companyinfo">
                                            Supplier Name: {!! $supplierDetails->supplierName !!}
                                            <br/>
                                            Supplier Location: {!! $supplierDetails->supplierLocation !!}
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-invoice-v2__line"></div>
                                <div class="kt-invoice-v2__details">
                                    <div class="kt-invoice-v2__details-left">
                                        <div class="kt-invoice-v2__details-label">INVOICE TO: {!! $quotesTotal->id !!}</div>
                                        <div class="kt-invoice-v2__details-value">
                                            Alpha Direct
                                            <br/>
                                            Block 8 Industrial , Botswana Innovation Hub
                                        </div>
                                    </div>
                                    <div class="kt-invoice-v2__details-right">
                                        <div class="kt-invoice-v2__detail--date">
                                            <div class="kt-invoice-v2__details-label">DATE: {!! \Carbon\Carbon::parse($quotesTotal->created_at)->format('d-m-Y') !!}</div>
                                            <div id="todaysDate" class="kt-invoice-v2__details-value"></div>
                                        </div>
                                        {{--<div class="kt-invoice-v2__details--invoiceno">
                                            <div class="kt-invoice-v2__details-label">INVOICE NO:</div>
                                            <div class="kt-invoice-v2__details-value">
                                                <label></label>

                                            </div>
                                        </div>--}}
                                    </div>
                                </div>
                                <div class="kt-invoice-v2__body">
                                    <div class="table-responsive">

                                        <form id="form" action="{{Route('submitQuote')}}" method="POST" enctype="multipart/form-data">
                                            {{csrf_field()}}
                                            <input type="hidden" name="claim_id" value="{{$glassClaim->id}}">
                                            <input type="hidden" name="total" id="total"/>
                                            <input type="hidden" name="finalTotal" id="finalTotal"/>
                                            <input type="hidden" name="supplier_id" value="{{$supplierDetails->id}}">
                                        </form>
                                        <table class="kt-invoice-v2__summary" id="table-diensten">

                                            <thead class="kt-invoice-v2__summary-header">

                                            <tr>
                                                <th>Glass</th>
                                                <th>amount</th>
                                                <th>total</th>
                                                <th>BTW</th>
                                            </tr>
                                            </thead>
                                            <tbody class="table-body">
                                            @foreach($quotes as $quote)
                                            <tr class="table-row">
                                                <td>{!! $quote->glass !!}</td>
                                                <td>{!! $quote->cost !!}</td>
                                                <td>VAT <span id="quoteTol">0</span></td>
                                                <td>12%</td>
                                            </tr>


                                            @endforeach
                                            </tbody>
                                            <tfoot>
                                            <tr>
                                                <td> </td>
                                                <td> </td>
                                                <td><strong>Subtotal</strong></td>
                                                <td>BWP <span id="subtotal">0</span></td>
                                                <td>{!! $quote->total !!}</td>
                                            </tr>
                                            <tr>
                                                <td> </td>
                                                <td> </td>
                                                <td>Total <span id="quoteTotal">{!! $quotesTotal->total !!}</span></td>
                                                <td> </td>
                                            </tr>
                                            {{--      <tr>
                                                     <td> </td>
                                                     <td> </td>
                                                     <td class="table-total"><span class="total">Total</span></td>
                                                     <td class="table-total"><span class="total">BWP <span id="totalofferte">0,00</span></span></td>
                                                     <td> </td>
                                                 </tr> --}}
                                            </tfoot>


                                        </table>


                                    </div>
                                </div>
                                <!--end::Portlet-->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="kt-portlet__foot">
                    <div class="row align-items-center">
                        <div class="col-lg-6"></div>
                        <div class="col-lg-6 kt-align-right">
                            <a id="submit" href="javascript:window.close();" class="btn btn-sm btn-secondary btn-pill">Close</a>
                            {{--<button type="button" class="btn btn-sm btn-danger btn-pill">Download</button>--}}
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Portlet--> 
      
        </div>


                <!--begin:: Global Mandatory Vendors -->
        <script src="{{asset('css/vendors/general/jquery/dist/jquery.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/popper.js/dist/umd/popper.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap/dist/js/bootstrap.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/js-cookie/src/js.cookie.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/moment/min/moment.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/tooltip.js/dist/umd/tooltip.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/perfect-scrollbar/dist/perfect-scrollbar.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/sticky-js/dist/sticky.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/wnumb/wNumb.js')}}" type="text/javascript"></script>
        <!--end:: Global Mandatory Vendors -->

        <!--begin:: Global Optional Vendors -->
        <script src="{{asset('css/vendors/general/jquery-form/dist/jquery.form.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/block-ui/jquery.blockUI.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/custom/components/vendors/bootstrap-datepicker/init.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap-datetime-picker/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/custom/components/vendors/bootstrap-timepicker/init.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap-daterangepicker/daterangepicker.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap-maxlength/src/bootstrap-maxlength.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/custom/vendors/bootstrap-multiselectsplitter/bootstrap-multiselectsplitter.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap-select/dist/js/bootstrap-select.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/typeahead.js/dist/typeahead.bundle.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/handlebars/dist/handlebars.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/inputmask/dist/jquery.inputmask.bundle.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/inputmask/dist/inputmask/inputmask.date.extensions.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/inputmask/dist/inputmask/inputmask.numeric.extensions.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/nouislider/distribute/nouislider.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/owl.carousel/dist/owl.carousel.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/autosize/dist/autosize.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/clipboard/dist/clipboard.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/dropzone/dist/dropzone.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/summernote/dist/summernote.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/markdown/lib/markdown.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/bootstrap-markdown/js/bootstrap-markdown.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/custom/components/vendors/bootstrap-markdown/init.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/jquery-validation/dist/jquery.validate.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/jquery-validation/dist/additional-methods.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/custom/components/vendors/jquery-validation/init.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/toastr/build/toastr.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/raphael/raphael.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/morris.js/morris.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/chart.js/dist/Chart.bundle.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/custom/vendors/bootstrap-session-timeout/dist/bootstrap-session-timeout.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/custom/vendors/jquery-idletimer/idle-timer.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/waypoints/lib/jquery.waypoints.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/counterup/jquery.counterup.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/es6-promise-polyfill/promise.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/sweetalert2/dist/sweetalert2.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/custom/components/vendors/sweetalert2/init.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/vendors/general/dompurify/dist/purify.js')}}" type="text/javascript"></script>
        <script src="{{asset('css/app/custom/general/components/extended/sweetalert2.min.js')}}" type="text/javascript"></script>

        <!--end:: Global Optional Vendors -->

        <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/fuse.js/3.4.4/fuse.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.1/cropper.min.js"></script>
        <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/jquery-cropper@1.0.0/dist/jquery-cropper.js"></script>




        <!--begin::Global Theme Bundle(used by all pages) -->

        <!--end::Global Theme Bundle -->
        <!--begin::Page Vendors(used by this page) -->
        <script src="{{asset('css/vendors/custom/fullcalendar/fullcalendar.bundle.js')}}" type="text/javascript"></script>

        <!--end::Page Vendors -->
        <!--begin::Page Scripts(used by this page) -->
        <!--end::Page Scripts -->
        <!--begin::Global App Bundle(used by all pages) -->
        <!--end::Global App Bundle -->
        <script src="{{asset('js/push.min.js')}}" type="text/javascript"></script>







</body>
<!-- end::Body -->
</html>

{{--                         <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
 --}}



