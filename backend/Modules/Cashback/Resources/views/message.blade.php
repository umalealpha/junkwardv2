@if (session()->has('success'))
    <div class="row">
        <div class="col-sm-12">
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">×</button>
                <strong>{!! session('success') !!}</strong>
            </div>
        </div>
    </div>
@endif
@if (session()->has('error'))
    <div class="row">
        <div class="col-sm-12">
            <div class="alert alert-danger alert-block">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">×</button>
                <strong>{!! session('error') !!}</strong>
            </div>
        </div>
    </div>
@endif

@if (session()->has('warning'))
    <div class="row">
        <div class="col-sm-12">
            <div class="alert alert-warning alert-block">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">×</button>
                <strong>{!! session('warning') !!}</strong>
            </div>
        </div>
    </div>

@endif
@if (session()->has('info'))
    <div class="row">
        <div class="col-sm-12">
            <div class="alert alert-info alert-block">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">×</button>
                <strong>{!! session('info') !!}</strong>
            </div>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>

        @foreach ($errors->all() as $error)
            {{ $error }}<br />
        @endforeach
    </div>
@endif
