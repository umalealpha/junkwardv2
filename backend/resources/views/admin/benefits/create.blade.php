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
 <!-- Add Benefit Type Modal -->
 <div class="modal fade" id="addTypeModal" tabindex="-1" role="dialog" aria-labelledby="addTypeModalLabel" aria-hidden="true">
                          <div class="modal-dialog" role="document">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="addTypeModalLabel">Add Benefit Type</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                  <span aria-hidden="true">&times;</span>
                                </button>
                              </div>
                              <div class="modal-body">
                                <div id="addTypeForm">
                                  @csrf
                                  <div class="form-group">
                                    <label for="type-code">Code</label>
                                    <input type="text" class="form-control" id="type-code" name="code" required>
                                  </div>
                                  <div class="form-group">
                                    <label for="type-name">Name</label>
                                    <input type="text" class="form-control" id="type-name" name="name" required>
                                  </div>
                                  <button id="addbenifittype" class="btn btn-primary">Add</button>
                                </div>
                                <div id="addTypeError" class="text-danger mt-2" style="display:none;"></div>
                              </div>
                            </div>
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
                <h3 class="kt-subheader__title">Create Benefit</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('benefits.index')}}" class="kt-subheader__breadcrumbs-link"> Benefits </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet">
                <form action="{{ route('benefits.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="kt-portlet__body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                        @if(session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif
                        
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Tag</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="tag" value="{{ old('tag') }}" placeholder="Enter benefit tag" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Type</label>
                            <div class="col-8 d-flex align-items-center">
                                <select class="form-control mr-2" name="type" id="type-select">
                                    <option value="">Select type</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type->code }}" {{ old('type') == $type->code ? 'selected' : '' }}>{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addTypeModal">Add Benefit Type</button>
                            </div>
                        </div>

                       

                      

                        <div class="form-group row">
                            <label class="col-3 col-form-label">Status</label>
                            <div class="col-8">
                                <select class="form-control" name="status" required>
                                    <option value="1" {{ old('status', '1') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-3 col-form-label">Price</label>
                            <div class="col-8">
                                <input type="number" step="0.01" class="form-control" name="price" value="{{ old('price') }}" placeholder="Enter price">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Point</label>
                            <div class="col-8">
                                <input type="number" class="form-control" name="point" value="{{ old('point') }}" placeholder="Enter point">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Benefit Image</label>
                            <div class="col-8">
                                <div class="kt-avatar" id="image" style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' name="image" accept="image/*" />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                        <i class="fa fa-times"></i>
                                    </span>
                                </div>
                                <small class="form-text text-muted">Upload an image for this benefit (JPEG, PNG, JPG, GIF, SVG - Max 2MB)</small>
                            </div>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" class="btn btn-brand">Submit</button>
                                    <a class="btn btn-secondary" href="{{ route('benefits.index') }}">Cancel</a>
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
<script>
                        $(function() {
                          $('#addbenifittype').on('click', function(e) {
                            e.preventDefault();
                            var code = $('#type-code').val();
                            var name = $('#type-name').val();
                            var token = $('input[name="_token"]').val();
                            $.ajax({
                              url: '{{ route('admin.benefit-types.add') }}',
                              method: 'POST',
                              data: { code: code, name: name, _token: token },
                              success: function(response) {
                                if(response.success) {
                                  // Add new option to select
                                  $('#type-select').append('<option value="'+code+'">'+name+'</option>');
                                  $('#type-select').val(code);
                                  $('#addTypeModal').modal('hide');
                                  $('#type-code').val('');
                                  $('#type-name').val('');
                                  $('#addTypeError').hide();
                                } else {
                                  $('#addTypeError').text(response.message || 'Error adding type').show();
                                }
                              },
                              error: function(xhr) {
                                $('#addTypeError').text('Error adding type').show();
                              }
                            });
                          });
                        });
                        </script>
</body>
</html> 