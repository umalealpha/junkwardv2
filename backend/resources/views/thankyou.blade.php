<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank You</title>
    <link href='https://fonts.googleapis.com/css?family=Lato:300,400|Montserrat:700' rel='stylesheet' type='text/css'>
    <style>
        @import url(//cdnjs.cloudflare.com/ajax/libs/normalize/3.0.1/normalize.min.css);
        @import url(//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css);
    </style>
    <link rel="stylesheet" href="https://2-22-4-dot-lead-pages.appspot.com/static/lp918/min/default_thank_you.css">
    <script src="https://2-22-4-dot-lead-pages.appspot.com/static/lp918/min/jquery-1.9.1.min.js"></script>
    <script src="https://2-22-4-dot-lead-pages.appspot.com/static/lp918/min/html5shiv.js"></script>
    <style>
        * {
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
            box-sizing: border-box;
        }

        .buttons {
            margin: 10%;
            text-align: center;
        }

        .btn-hover {
            width: 200px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            margin: 20px;
            height: 55px;
            text-align:center;
            border: none;
            background-size: 300% 100%;

            border-radius: 50px;
            moz-transition: all .4s ease-in-out;
            -o-transition: all .4s ease-in-out;
            -webkit-transition: all .4s ease-in-out;
            transition: all .4s ease-in-out;
        }

        .btn-hover:hover {
            background-position: 100% 0;
            moz-transition: all .4s ease-in-out;
            -o-transition: all .4s ease-in-out;
            -webkit-transition: all .4s ease-in-out;
            transition: all .4s ease-in-out;
        }

        .btn-hover:focus {
            outline: none;
        }

        .btn-hover.color-1 {
            background-image: linear-gradient(to right, #25aae1, #40e495, #30dd8a, #2bb673);
            box-shadow: 0 4px 15px 0 rgba(49, 196, 190, 0.75);
        }
        .btn-hover.color-2 {
            background-image: linear-gradient(to right, #f5ce62, #e43603, #fa7199, #e85a19);
            box-shadow: 0 4px 15px 0 rgba(229, 66, 10, 0.75);
        }
    </style>
</head>
<body>
<header class="site-header" id="header">
    <h1 class="site-header__title" data-lead-id="site-header-title">THANK YOU!</h1>
</header>

@if($statusMessage == 'YES')
<div class="main-content">
    <i class="fa fa-check main-content__checkmark" id="checkmark"></i>
    <h4>{{$statusMessage}} </h4>
    <br>
    <p>Payment was successful , Welcome to the Alpha Direct family<p>
    <a class="buttons">
        <a href="" ><button class="btn-hover color-2">Activation</button></a>
    </div>
    @else
    <div class="main-content">
        <i class="fa fa-check main-content__checkmark" id="checkmark"></i>
        <h4>{{$statusMessage}} </h4>
        <br>
        <p>Payment was unsuccessful an Alpha Direct Agent will assist you shortly, Welcome to the Alpha Direct family<p>
        <a class="buttons">
            <a href="" ><button class="btn-hover color-2">Activation</button></a>
        </div>

    @endif 
</div>

<footer class="site-footer" id="footer">
    <p class="site-footer__fineprint" id="fineprint">Copyright ©2019 | All Rights Reserved</p>
</footer>
</body>
</html>