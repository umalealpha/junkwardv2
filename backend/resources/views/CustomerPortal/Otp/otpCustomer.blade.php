
<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>

        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Alpha Direct Admin</title>
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css">
        <!-- Bootstrap core CSS -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.2.1/css/bootstrap.min.css" rel="stylesheet">
        <!-- Material Design Bootstrap -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/mdbootstrap/4.7.3/css/mdb.min.css" rel="stylesheet">
        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Raleway:100,600" rel="stylesheet" type="text/css">


        <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">


       <style>
        .login {
            max-width: 500px;
            margin: auto;
        }

       </style>


    </head>
    <body class="bg-image">
       
  <div class="login card p-4" style="margin-top:30px">
        {{$checkVerifiedUser}}

      <img src="{{asset('Logo.png')}}" class="rounded mx-auto d-block" alt="...">
      
          <div class="md-form">
            <i class="fas fa-lock prefix"></i>
            <input type="password" id="inputValidationEx2" name="password" class="form-control validate">
          <label for="inputValidationEx2">OTP PIN </label>
          </div>
           <div class="row">
                <div class="col-6">
       
                    <button id="sms" class="btn btn-info" type="submit" onclick="">Send OTP via sms</button>

                </div>
            <div class="col-6">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="via" id="email">
                    <label class="custom-control-label" for="sms">Send OTP via sms</label>
                </div>
        
            </div>
          
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-warning btn-lg btn-block">Login</button> 

            </div>
         

             <form id="smsForm" action="{{Route('sendSmsOtp')}}" method="POST">
                    {{csrf_field()}}
    
                <input type="hidden" name="email" value="{{Session::get('checkVerifiedUser->email')}}">
                <input type="hidden" name="userCellphone" value="{{Session::get('checkVerifiedUser->email')}}">
                <input type="hidden" name="via" value="sms">
                </form>
      </div>

 


    </body>
    <!-- JQuery -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<!-- Bootstrap tooltips -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.4/umd/popper.min.js"></script>
<!-- Bootstrap core JavaScript -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.2.1/js/bootstrap.min.js"></script>
<!-- MDB core JavaScript -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdbootstrap/4.7.3/js/mdb.min.js"></script>

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<script>


    $('#sms').on("click",function(){

        $('#smsForm').submit();
        $('#sms').prop('disabled',true);
        $('#email').prop('disabled',true);

    });
</script>

<script>
  @if(Session::has('failedAuth'))
    Toastify({
      text: "{{ Session::get('failedAuth') }}",
      duration: 4000,
      newWindow: true,
      gravity: "top", // `top` or `bottom`
      positionRight: true, // `true` or `false`
      backgroundColor: "#CC0000",
    }).showToast();   
  
  @endif
  @if(Session::has('sessionExpired'))
Toastify({
  text: "{{ Session::get('sessionExpired') }}",
  duration: 4000,
  newWindow: true,
  gravity: "top", // `top` or `bottom`
  positionRight: true, // `true` or `false`
  backgroundColor: "#CC0000",
}).showToast();   
@endif
  
 
</script>
</html>

