@extends('admin/layouts/default')

{{-- Page title --}}
@section('title')
    Add Batch
    @parent
@stop

{{-- page level styles --}}
@section('header_styles')
    <!--page level css -->

    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/iCheck/css/all.css') }}"/>
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/iCheck/css/line/line.css') }}"/>
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/bootstrap-switch/css/bootstrap-switch.css') }}"/>
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/switchery/css/switchery.css') }}"/>
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/awesomeBootstrapCheckbox/awesome-bootstrap-checkbox.css') }}"/>
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/pages/formelements.css') }}"/>
    <!--end of page level css-->
    {{--To hide spinner of number type input--}}
    <style>
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
@stop


{{-- Page content --}}
@section('content')
    <section class="content-header">
        <h1>Add New Batch</h1>

        <ol class="breadcrumb">
            <li>
                <a href="{{ route('admin.dashboard') }}">
                    <i class="livicon" data-name="home" data-size="14" data-color="#000"></i>
                    Dashboard
                </a>
            </li>
            <li><a href="#"> Batch</a></li>
            <li class="active">Add New Batch</li>
        </ol>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-primary" style="margin-top: 10px">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="livicon" data-name="user-add" data-size="18" data-c="#fff" data-hc="#fff" data-loop="true"></i>
                            Add New Batch
                        </h3>
                                <span class="pull-right clickable">
                                    <i class="glyphicon glyphicon-chevron-up"></i>
                                </span>

                    </div>
                    <div class="panel-body">
                        <!--main content-->
                        <form id="batchForm" action="{{ route('admin.batch.store') }}"
                              method="POST" enctype="multipart/form-data" class="form-horizontal">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                            <div class="form-group {{ $errors->first('name', 'has-error') }}">
                                <label for="title" class="col-sm-3 control-label">
                                    Batch Name*
                                </label>
                                <div class="col-sm-5">
                                    <input type="text" id="name" name="name" class="form-control" placeholder="Batch Name"
                                           value="{!! old('name') !!}">
                                </div>
                                <div class="col-sm-4">
                                    {!! $errors->first('name', '<span class="help-block">:message</span> ') !!}
                                </div>
                            </div>
                            <div class="form-group {{ $errors->first('year', 'has-error') }}">
                                <label for="title" class="col-sm-3 control-label">
                                    Year*
                                </label>
                                <div class="col-sm-5">
                                    <input type="number" id="year" name="year" class="form-control" placeholder="Year"
                                           value="{!! old('year') !!}">
                                </div>
                                <div class="col-sm-4">
                                    {!! $errors->first('year', '<span class="help-block">:message</span> ') !!}
                                </div>
                            </div>
                            <div class="form-group {{ $errors->first('stations', 'has-error') }}">
                                <label for="stations" class="col-sm-3 control-label">
                                    No. of stations*<br>(maximum number of rooms / students in each circuit)
                                </label>
                                <div class="col-sm-5">
                                    <input type="number" id="stations" name="stations" class="form-control" placeholder="No. of Stations"
                                           value="{!! old('stations') !!}">
                                </div>
                                <div class="col-sm-4">
                                    {!! $errors->first('stations', '<span class="help-block">:message</span> ') !!}
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="title" class="col-sm-3 control-label">
                                    Status
                                </label>
                                <div class="col-sm-5">
                                    <span id="status" style="padding-right: 10px; font-weight: 900; font-size: 16px;">ACTIVE</span>
                                    <input type="checkbox" id="status_id" value="1" class="js-switch4" name="status" checked/>
                                </div>
                            </div>
                            <div class="col-md-12 form-group" style="text-align: center;">
                                <button type="submit" class="btn btn-primary" value="Submit">SAVE</button>
                                <a class="btn btn-danger" href="{{ route('admin.batch.index') }}" >CANCEL</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!--row end-->
    </section>
@stop

{{-- page level scripts --}}
@section('footer_scripts')

    <script language="javascript" type="text/javascript" src="{{ asset('assets/vendors/iCheck/js/icheck.js') }}"></script>
    <script language="javascript" type="text/javascript" src="{{ asset('assets/vendors/bootstrap-switch/js/bootstrap-switch.js') }}"></script>
    <script language="javascript" type="text/javascript" src="{{ asset('assets/vendors/switchery/js/switchery.js') }}" ></script>
    <script language="javascript" type="text/javascript" src="{{ asset('assets/vendors/bootstrap-maxlength/js/bootstrap-maxlength.js') }}"></script>
    <script language="javascript" type="text/javascript" src="{{ asset('assets/vendors/card/lib/js/jquery.card.js') }}"></script>
    <script language="javascript" type="text/javascript" src="{{ asset('assets/js/pages/radio_checkbox.js') }}"></script>
    <script src="{{ asset('assets/vendors/bootstrapvalidator/js/bootstrapValidator.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/js/pages/addbatch.js') }}"></script>
    <script>

        var elem = document.querySelector('.js-switch4');
        var init = new Switchery(elem, {
            size: 'big',
            color: '#01BC8C'
        });

        $('#status_id').change(function() {
            if($(this).is(":checked")) {
                $('#status').text("ACTIVE");
            } else {
                $('#status').text("IN-ACTIVE");
            }
        });
    </script>

@stop
