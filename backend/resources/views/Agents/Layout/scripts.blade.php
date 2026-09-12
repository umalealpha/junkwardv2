   <!--begin:: Global Mandatory Vendors -->
<script src="{{asset('css/vendors/general/jquery/dist/jquery.js')}}" type="text/javascript"></script>
<script src="{{asset('css/vendors/general/popper.js/dist/umd/popper.js')}}" type="text/javascript"></script>
<script src="{{asset('css/vendors/general/bootstrap/dist/js/bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{asset('css/vendors/general/js-cookie/src/js.cookie.js')}}" type="text/javascript"></script>
<script src="{{asset('css/vendors/general/moment/min/moment.min.js')}}" type="text/javascript"></script>
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
<!--end:: Global Optional Vendors -->


   {{--For additional CSS--}}
   @yield('additional_js')
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/fuse.js/3.4.4/fuse.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.1/cropper.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/jquery-cropper@1.0.0/dist/jquery-cropper.js"></script>




<!--begin::Global Theme Bundle(used by all pages) -->
        
        <script src="{{asset('css/demo/default/base/scripts.bundle.js')}}" type="text/javascript"></script>
        <!--end::Global Theme Bundle -->
        <!--begin::Page Vendors(used by this page) -->
        <script src="{{asset('css/vendors/custom/fullcalendar/fullcalendar.bundle.js')}}" type="text/javascript"></script>

        <!--end::Page Vendors -->
        <!--begin::Page Scripts(used by this page) -->
        <script src="{{asset('css/app/custom/general/dashboard.js')}}" type="text/javascript"></script>
        <!--end::Page Scripts -->
        <!--begin::Global App Bundle(used by all pages) -->
        <script src="{{asset('css/app/bundle/app.bundle.js')}}" type="text/javascript"></script>
        <!--end::Global App Bundle -->
           <script src="{{asset('js/push.min.js')}}" type="text/javascript"></script>
     <script>
           let isLoading = false;
    let failed = 0; // if poll fails 10 times stop polling 
    var urlValue = '{{ \Config::get('values.graphite_url') }}' 
    setInterval(function () {
        if (!isLoading && failed < 10) {
            isLoading = true;
            $.get(urlValue+"/api/notifications/new",
                { "user_id": {{Auth::user()->id}} }
            )
                .done((notifications, statusMessage, xhr) => {
                    if (xhr.status <= 200) {
                        if (notifications.length > 0 && typeof notifications != "undefined") {
                            let title = "Alpha Direct - {{Auth::user()->firstName}}";
                            let body = notifications[0].data;
                            let action = notifications[0].action;
                            console.log(window.location.hostname+'/agents/'+action);

                            if (Push.Permission.has()) {
                            console.log(1, window.location.hostname+'/agents/'+action);
                                Push.create(title, {
                                    body: body,
                                    icon: '{{asset('images/alpha-logo.png')}}',
                                    timeout: 60000
                                });
                            } else {
                            console.log(2, window.location.hostname+'/agents/'+action);
                                Push.Permission.request(() => {
                                    Push.create(title, {
                                        body: body,
                                        link:'https://'+window.location.hostname+'/agents/'+action,
                                        icon: '/alpha-direct.png',
                                        timeout: 60000,
                                        onClick: function () {
                                            window.focus();
                                            this.close();
                                        }
                                    });
                                });
                            }
                        }
                    } else {
                        failed++;
                    }
                    isLoading = false;
                });
        }
    }, 1000); // 1 second intervals on fetching notifications
        
</script>