<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
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
<div class="kt-grid kt-grid--hor kt-grid--root">
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')
    </div>
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Edit Reward Tier</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('reward-tiers.index')}}" class="kt-subheader__breadcrumbs-link"> Reward Tiers </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
        </div>
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet">
                <form action="{{ route('reward-tiers.update', $tier->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Name</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="name" value="{{ old('name', $tier->name) }}" placeholder="Enter tier name" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Label</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="label" value="{{ old('label', $tier->label) }}" placeholder="Enter tier label">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Description</label>
                            <div class="col-8">
                                <textarea class="form-control" name="description" placeholder="Enter description">{{ old('description', $tier->description) }}</textarea>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Number of Months (condition1)</label>
                            <div class="col-8">
                                <input type="number" class="form-control" name="condition1" value="{{ old('condition1', $tier->condition1) }}" placeholder="Enter number of months" min="0" max="40">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Is Bundled (condition2)</label>
                            <div class="col-8">
                                <select class="form-control" name="condition2">
                                    <option value="">Select</option>
                                    <option value="1" {{ old('condition2', $tier->condition2) == '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ old('condition2', $tier->condition2) == '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Is DomCom (condition3)</label>
                            <div class="col-8">
                                <select class="form-control" name="condition3">
                                    <option value="">Select</option>
                                    <option value="1" {{ old('condition3', $tier->condition3) == '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ old('condition3', $tier->condition3) == '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Multi Policy Holder (condition4)</label>
                            <div class="col-8">
                                <select class="form-control" name="condition4">
                                    <option value="">Select</option>
                                    <option value="1" {{ old('condition4', $tier->condition4) == '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ old('condition4', $tier->condition4) == '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Level Point</label>
                            <div class="col-8">
                                <input type="number" class="form-control" name="level_point" value="{{ old('level_point', $tier->level_point) }}" placeholder="Enter level point (1000-4000)" min="1000" max="4000" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Status</label>
                            <div class="col-8">
                                <select class="form-control" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="1" {{ old('status', $tier->status) == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status', $tier->status) == '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Tier Image</label>
                            <div class="col-8">
                                <div class="kt-avatar" id="image" style="float: left; clear: left;">
                                    @if($tier->image)
                                        <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($tier->image) !!})"></div>
                                    @else
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @endif
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' name="image" accept="image/*" />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                        <i class="fa fa-times"></i>
                                    </span>
                                </div>
                                <small class="form-text text-muted">Upload an image for this reward tier (JPEG, PNG, JPG, GIF, SVG - Max 2MB)</small>
                            </div>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" class="btn btn-brand">Update</button>
                                    <a class="btn btn-secondary" href="{{ route('reward-tiers.index') }}">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @include('includes.footer')
</div>
@include('admin.layouts.scripts')
</body>
</html> 