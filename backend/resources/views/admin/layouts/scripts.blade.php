<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->
<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
    @csrf
</form>

<!-- Timer display -->
<div id="logout-timer" style="position:fixed; display:none; bottom:10px; right:10px; background:#f8d7da75; color:#721c24; padding:10px 15px; border-radius:5px; font-weight:bold; z-index:999;">
    Auto logout in: <span id="countdown">24:00:00</span>
</div>

<script>
    // Operators work full shifts; previously 10 min of idle = kicked out mid-task.
    // Extended to match backend session lifetime (config/session.php => 1440 min).
    // Warning only surfaces in the last 5 min so the toast doesn't nag all day.
    const LOGOUT_AFTER = 24 * 60 * 60 * 1000; // 24 hours
    const WARNING_BEFORE = 5 * 60 * 1000;     // 5 minutes before logout
    const ACTIVITY_KEY = 'lastUserActivity';
    const LOGOUT_KEY = 'userForceLogout';

    let countdownInterval;
    let remaining = LOGOUT_AFTER;

    // Trigger logout in this tab
    function autoLogout() {
        clearInterval(countdownInterval);
        // Broadcast logout to other tabs
        localStorage.setItem(LOGOUT_KEY, Date.now());
        document.getElementById('logout-form').submit();
    }

    function showWarning() {
        $('#logout-timer').show();
    }

    function updateCountdownDisplay(ms) {
        // ms is >24 h for most of the day — surface MM:SS only when it's
        // below the warning threshold, otherwise don't bother rendering.
        if (ms > WARNING_BEFORE) {
            $('#logout-timer').hide();
            return;
        }
        const totalSeconds = Math.max(0, Math.floor(ms / 1000));
        const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
        const seconds = String(totalSeconds % 60).padStart(2, '0');
        document.getElementById('countdown').textContent = `${minutes}:${seconds}`;
        $('#logout-timer').show();
    }

    function updateActivity() {
        localStorage.setItem(ACTIVITY_KEY, Date.now().toString());
    }

    function startMonitoring() {
        // Countdown timer
        setInterval(() => {
            const lastActivity = parseInt(localStorage.getItem(ACTIVITY_KEY)) || 0;
            const now = Date.now();
            const elapsed = now - lastActivity;

            remaining = LOGOUT_AFTER - elapsed;
            updateCountdownDisplay(remaining);

            if (remaining <= 0) {
                autoLogout();
            }
        }, 1000);
    }

    function listenForCrossTabLogout() {
        window.addEventListener('storage', (e) => {
            if (e.key === LOGOUT_KEY) {
                // Logout triggered in another tab
                document.getElementById('logout-form').submit();
            }
        });
    }

    // Init
    ['click', 'mousemove', 'keypress', 'scroll'].forEach(event => {
        window.addEventListener(event, updateActivity);
    });

    updateActivity();
    startMonitoring();
    listenForCrossTabLogout();
</script>





<!--begin:: Global Mandatory Vendors -->
<script src="{{asset('css/vendors/general/jquery/dist/jquery.js')}}" type="text/javascript"></script>
<script src="{{asset('css/vendors/general/popper.js/dist/umd/popper.js')}}" type="text/javascript"></script>
<script src="{{asset('css/vendors/general/bootstrap/dist/js/bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{asset('css/vendors/general/perfect-scrollbar/dist/perfect-scrollbar.js')}}" type="text/javascript"></script>
{{--<script src="{{asset('css/vendors/general/sweetalert2/dist/sweetalert2.min.js')}}" type="text/javascript"></script>--}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}"
                type="text/javascript"></script>
<!--end:: Global Mandatory Vendors -->

<!--begin::Global Theme Bundle(used by all pages) -->
<script src="{{asset('css/demo/default/base/scripts.bundle.js')}}" type="text/javascript"></script>
{{--<script src="{{asset('js/keen-datatable/advanced/column-rendering.js')}}" type="text/javascript"></script>--}}

<!--end::Global Theme Bundle -->

<script>
     $(document).ready(function(){
        $(".select2").select2({
       })/* .on('select2-open', function() {

            // however much room you determine you need to prevent jumping
            var requireHeight = 600;
            var viewportBottom = $(window).scrollTop() + $(window).height();

            // figure out if we need to make changes
            if (viewportBottom < requireHeight)
            {
                // determine how much padding we should add (via marginBottom)
                var marginBottom = requireHeight - viewportBottom;

                // adding padding so we can scroll down
                $(".aLwrElmntOrCntntWrppr").css("marginBottom", marginBottom + "px");

                // animate to just above the select2, now with plenty of room below
                $('html, body').animate({
                    scrollTop: $("#mySelect2").offset().top - 10
                }, 1000);
            }
        }) */;
    });

</script>
<!--start::Notification hide -->
<script>
    $(document).ready(function(){
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $("#loader").hide();
    });
   /* $(document).ready(function(){
        $('.kt-avatar__upload').prop('title', 'your new title');
    });*/

</script>
<script>
    setTimeout(function() {
        $('.alert').fadeOut('fast');
    }, 6000); // <-- time in milliseconds

</script>
 <script>
    var KTAppOptions = {

"colors": {

"state": {

    "brand": "#5d78ff",

    "metal": "#c4c5d6",

    "light": "#ffffff",

    "accent": "#00c5dc",

    "primary": "#5867dd",

    "success": "#34bfa3",

    "info": "#36a3f7",

    "warning": "#ffb822",

    "danger": "#fd3995",

    "focus": "#9816f4"
},

"base": {

    "label": [

        "#c5cbe3",

        "#a1a8c3",

        "#3d4465",

        "#3e4466"
    ],

    "shape": [

        "#f0f3ff",

        "#d9dffa",

        "#afb4d4",

        "#646c9a"
    ]
}
}
};
</script>


<script>

    /*$(document).ready(function() {
        var ajaxRequest;
        var append = '';
            $.ajax({
                url: '{{ route('admin.notification.GetNotification') }}',
                data: {
                    "_token": "{{ csrf_token() }}",

                },
                type: 'post',
                datatype : 'json',
                success: function (data) {
                    if (data) {
                        $('span.notificationCount').html(data.count+' unread messages');
                        $.each(data.notifications, function(key,value){
                            append += ' <a href="{{--{!! route('') !!}--}} #"  class="kt-notification__item" data-toggle="modal" data-target="#exampleModalTooltips">';
                            append += ' <input type="hidden"  class="id" name="id" value="'+ value.id + '">';
                            append += ' <div class="kt-notification__item-icon"> <i class="flaticon2-notification kt-font-info"></i> </div>';
                            append += '<div class="kt-notification__item-details">';
                            append += '<div class="kt-notification__item-title">'+ value.description + '</div>';
                            append += '<div class="kt-notification__item-time">'+ value.created_at + '</div>';
                            append += '</div>';
                            if(value.read_at == 0){
                                append += '<span name="read" class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"> Unread</span>';
                            }
                            append += '</a>';
                        });
                        $('.allNotification').append(append);

                    } else {
                        append += '<p>No Notifications</p>';
                        $('.allNotification').append(append);
                    }

                }
            });
    });*/
</script>

{{--<button type="button" class="btn btn-outline-brand" data-toggle="modal" data-target="#exampleModalTooltips"> Launch tooltips and popovers demo modal </button>--}}

<script>
    $(document).ready(function() {
       var isActive = {!! json_encode((array)auth()->user()->active) !!};
       var isLogin = {!! json_encode((array)auth()->user()->is_graphite_login) !!};
      if(isActive != 1 || isLogin != 1){
          //window.location = "{{ url('/logout') }}";
      }
    })
    $(document).on('click','a.kt-notification__item', function(){
        var id = $(this).closest("a.kt-notification__item").find("input[name='id']").val();
        $(this).closest("a.kt-notification__item").find("span[name='read']").remove();
        $( "#performedBy,#date,#time,#performedOn,#activityDescription,#activityTitlez" ).empty();
        $.ajax({
            url: '{{ route('admin.notification.data') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                $('#activityTitle').html(data.activity.log_name);
                $('#activityDescription').html(data.activity.description);
                $('#performedBy').html(data.performedBy);
                $('#performedOn').html(data.performedOn);
                $('#time').html(data.time);
                $('#date').html(data.date);
                if(data.update != null)
                    $('#updatetime').html(data.update);
                else
                    $('#updatetime').html('Never');


            },
        });
    })



</script>

