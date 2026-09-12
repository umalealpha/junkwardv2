
@if(Session::has('message'))
<div class="row" data-aos="fade-up">
	<div class="col-sm-12">
		<div class="alert alert-success alert-dismissible fade show" role="alert">
		  <strong>Success!</strong> {{Session::get('message')}}
		  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	</div>
</div>  
@endif


@if ($message = Session::get('success'))
<div class="row" data-aos="fade-up">
     <div class="col-sm-12" >
		<div class="alert alert-success alert-dismissible fade show" role="alert">
		  <strong>Success!</strong> {{$message}}
		  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
     </div>
</div>
@endif

@if ($message = Session::get('error'))
<div class="row" data-aos="fade-up">
     <div class="col-sm-12">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
		  <strong>Error!</strong> {{$message}}
		  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
     </div>
</div>
@endif

@if ($message = Session::get('warning'))
<div class="row" data-aos="fade-up">
     <div class="col-sm-12">
		<div class="alert alert-warning alert-dismissible fade show" role="alert">
		  <strong>Warning!</strong> {{$message}}
		  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
     </div>
</div>

@endif
@if ($message = Session::get('info'))
<div class="row" data-aos="fade-up">
     <div class="col-sm-12">
		<div class="alert alert-warning alert-dismissible fade show" role="alert">
		  <strong>Info!</strong> {{$message}}
		  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
     </div>
</div>
@endif

@if ( ! $errors->isEmpty() )
	<div class="row" data-aos="fade-up">
		<div class="col-sm-12">
			<div class="alert alert-danger alert-dismissible fade show">
				<ul>@foreach ( $errors->all() as $error )
					<li><strong>Warning !</strong> {{ $error }}</li>
				@endforeach </ul>
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		</div>
	</div>
@endif

