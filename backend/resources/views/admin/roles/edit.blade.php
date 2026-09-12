<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

        <!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " >
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{asset('images/logo.png')}}"/>
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left" id="kt_aside_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more"></i></button>
    </div>
</div>
<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')

    </div>
     <!-- check if is first time login -->

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Edit Role
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{!! route('admin.roles.index') !!}" class="kt-subheader__breadcrumbs-link"> Role </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                {!! Form::model($role, ['method' => 'PATCH','route' => ['admin.roles.update', $role->id]]) !!}
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="col-xs-12 col-sm-12 col-md-12">
                            <div class="form-group">
                                <strong>Name:</strong>
                                {!! Form::text('name', $role->name, array('placeholder' => 'Name','class' => 'form-control')) !!}
                            </div>
                        </div>
                        <div class="col-xs-12 col-sm-12 col-md-12">
                            <div class="form-group">
                                <strong>Group Rule:</strong>
                                <select class="form-control kt_selectpicker" title="Please select rule group"
                                    data-live-search="true" name="rule_group">
                                    <option value="">Please Select</option>
                                    @foreach($ruleGroups as $key=>$rule_group)
                                        <option value="{{$rule_group->n_PrValidationRuleGroupMasters_PK}}" {{$rule_group->n_PrValidationRuleGroupMasters_PK == $role->rule_group  ? 'selected' : ''}}>{{$rule_group->s_RuleCode}} - {{ $rule_group->s_RuleDesc }}</option>
                                    @endforeach
                               </select>
                            </div>
                        </div>

                        <div class="col-xs-12 col-sm-12 col-md-12">
                            <div class="form-group">
                                <strong>Permission:</strong>
                                &nbsp&nbsp
                                @if($role->name == "Super Admin")
                                <label class="kt-checkbox kt-checkbox--brand selectSpan">
                                        <input type="checkbox" name="selectAll" id="selectAll" value="0">
                                     Select All
                                    <span></span>
                                </label>
                                @endif

                                @foreach($permission as $key => $value)
                                    @if($value->category != $category)
                                        <div class="form-group" >
                                            <label><strong>{!! ($value->category) !!}</strong></label><br>
                                            <div class="kt-checkbox-inline">
                                                <?php $category = $value->category; ?>

                                                @endif

                                                @if($key == 0)
                                                    <label class="kt-checkbox" style="margin-bottom: 30px;">
                                                        {{ Form::checkbox('permission[]', $value->id, in_array($value->id, $rolePermissions) ? true : false, array('class' => 'name')) }}
                                                        {!! explode("-", $value->name)[count(explode("-", $value->name))-1] !!}
                                                        <span></span>
                                                    </label>
                                                @else
                                                    @if($value->category != $category)
                                                        <label class="kt-checkbox" style="margin-bottom: 30px;">
                                                            {{ Form::checkbox('permission[]', $value->id, in_array($value->id, $rolePermissions) ? true : false, array('class' => 'name')) }}
                                                            {!! explode("-", $value->name)[count(explode("-", $value->name))-1] !!}
                                                            <span></span>
                                                        </label>
                                                        </div>
                                                    </div>
                                                @else
                                                <label class="kt-checkbox" style="margin-bottom: 30px;">
                                                    {{ Form::checkbox('permission[]', $value->id, in_array($value->id, $rolePermissions) ? true : false, array('class' => 'name')) }}
                                                    {!! explode("-", $value->name)[count(explode("-", $value->name))-1] !!}
                                                    <span></span>
                                                </label>
                                                @endif
                                    @endif
                                @endforeach
                                </div>
                            </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" value="Submit" class="btn btn-brand">Submit</button>
                                    <button type="button" id="loadingBtn" class="btn btn-brand" style="display:none"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...</button>
                                    <a class="btn btn-secondary" href="{{ route('admin.roles.index') }}" >Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                {!! Form::close() !!}
                <!--end::Form-->
            </div>
            <!--end::Portlet-->
        </div>
        <!-- end:: Content -->
    </div>

    <!-- begin:: Footer -->
    @include('includes.footer')
    <!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')
<script>
    function loadingButton(){
        document.getElementById("btn").style.display = "none";
        document.getElementById("loadingBtn").style.display = "block";
    }

    $("#selectAll").change(function(){
       var value = $('#selectAll').val();
       if (value == 0){
           $("input:checkbox").prop('checked', true);
           $('#selectAll').attr("value","1");
       }
       else{
           $("input:checkbox").prop('checked', false);
           $('#selectAll').attr("value","0");
       }

    });


</script>

</body>
<!-- end::Body -->
</html>
