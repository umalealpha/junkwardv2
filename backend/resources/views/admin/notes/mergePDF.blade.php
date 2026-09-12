<html lang="en">
<head>
  <title>Laravel 9 Merge Multiple PDF Files Example - ItSolutionStuff.com</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.0.1/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    @if (count($errors) > 0)
    <div class="alert alert-danger">
        <strong>Sorry!</strong> There were more problems with your HTML input.<br><br>
        <ul>
          @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
          @endforeach
        </ul>
    </div>
    @endif
    <h3 class="well">Laravel Merge Multiple PDF Files Example - ItSolutionStuff.com</h3>
    <form method="post" action="{{ route('merge.pdf.post') }}" enctype="multipart/form-data">
        {{csrf_field()}}
        <input type="file" name="filenames[]" class="myfrm form-control" multiple="">
        <button type="submit" class="btn btn-success" style="margin-top:10px">Submit</button>
  </form>
</div>
</body>

</html>
