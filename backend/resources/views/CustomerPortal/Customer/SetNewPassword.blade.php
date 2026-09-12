
<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>

        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Alpha Direct Customer</title>
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
        

     <form action="{{Route('customer-setNewPassword')}}" method="POST">
      {{ csrf_field() }}
      <img src="{{asset('Logo.png')}}" class="rounded mx-auto d-block" alt="...">
          <div class="md-form">
                <i class="fas fa-lock prefix"></i>
                <input type="password" id="inputValidationEx1" autocomplete="off" name="password" class="form-control validate" minlength="7" maxlength="12">
          <label for="inputValidationEx1">Set New Password</label>
          </div>
          
          <div class="md-form">
                <i class="fas fa-lock prefix"></i>
                <input type="password" id="inputValidationEx1" autocomplete="off" name="confirmPassword" class="form-control validate" minlength="7" maxlength="12">
          <label for="inputValidationEx1">Confirm New Password</label>
          </div>

        <input name="userId" type="hidden" value="{{Auth::user()->id}}">
     
        <button type="submit" class="btn btn-warning btn-lg btn-block">Set new password</button> 
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

