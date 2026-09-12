@if ($errors->any())
    <div class="alert alert-secondary fade show" role="alert">
        <div class="alert-text" style="color:red"><strong>Error:</strong> Please check the form below for errors</div>
        <div class="alert-close">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true"><i class="la la-close"></i></span>
            </button>
        </div>
    </div>
@endif

@if ($message = Session::get('success'))
    <div class="alert alert-success fade show" role="alert">
        <div class="alert-text"><strong>Success:</strong> {{ $message }}</div>
        <div class="alert-close">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true"><i class="la la-close"></i></span>
            </button>
        </div>
    </div>
@endif

@if ($message = Session::get('error'))
    <div class="alert alert-danger fade show" role="alert">
        <div class="alert-text"><strong>Error:</strong> {{ $message }}</div>
        <div class="alert-close">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true"><i class="la la-close"></i></span>
            </button>
        </div>
    </div>
@endif


@if ($message = Session::get('warning'))
    <div class="alert alert-warning fade show" role="alert">
        <div class="alert-text"><strong>Warning:</strong> {{ $message }}</div>
        <div class="alert-close">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true"><i class="la la-close"></i></span>
            </button>
        </div>
    </div>
@endif

@if ($message = Session::get('info'))
    <div class="alert alert-info fade show" role="alert">
        <div class="alert-text"><strong>Info:</strong> {{ $message }}</div>
        <div class="alert-close">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true"><i class="la la-close"></i></span>
            </button>
        </div>
    </div>
@endif



