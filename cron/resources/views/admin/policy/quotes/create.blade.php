<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

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
    @if(!empty($selectedCustomer))
        <div class="alert alert-success fade show" role="alert">
            <div class="alert-text"><strong>Success:</strong> Customer Selected Successfully !</div>
            <div class="alert-close">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true"><i class="la la-close"></i></span>
                </button>
            </div>
        </div>
    @endif
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Quote
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ route('admin.policy.index') }}" class="kt-subheader__breadcrumbs-link"> Quote </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

            <!--begin::Portlet-->
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            New Quote
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                   <div class="row">
                         <div class="col-lg-3 mt-2">
                            <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin">
                                1. Customer Details: {{$lead->leadCode}}
                                    <span class="kt-switch" style="position: absolute;">
                             </h3>
                           </div>

                   </div>


                    <!--begin::Form-->
                    <form id="policyForm" action="{{ route('admin.policy.store') }}"
                          method="POST" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Omang ID</label>
                                    <input id="omang" type="text" class="form-control id-type validateGroup1" name="omang" placeholder="Please enter omang number" pattern="[0-9]{9}" maxlength="9" >
                                    <p id="omangError" style="display:none; color:red;">Please enter another omang ID</p>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Passport Number</label>
                                    <input id="passport" type="text" class="form-control id-type validateGroup1" name="passport" placeholder="Please enter passport number" maxlength="12" >
                                    <p id="passportError" style="display:none; color:red;">Please enter another passport number</p>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" class="form-control" name="fname" placeholder="First Name" title="Please enter customer first name" pattern="[A-Za-z]{1,25}" maxlength="25" value="{{ $customer->firstName }}"  required>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" class="form-control" name="lname" title="Please enter customer last name" placeholder="Last Name" value="{{ $customer->lastName }}" pattern="[A-Za-z]{1,25}" maxlength="25" required>
                                </div>
                            </div>

                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Gender</label>
                                    <div class="kt-radio-inline">
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" value="1" @if(!empty($selectedCustomer) && $selectedCustomer->profile->gender == 1) checked @endif
                                            class="premiumField">
                                            Male <span></span>
                                        </label>
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" value="0" @if(!empty($selectedCustomer) && $selectedCustomer->profile->gender == 0) checked @endif class="premiumField">
                                            Female<span></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Cellphone Number</label>
                                    <input type="text" class="form-control" name="cellphone"  placeholder="Please enter customers cellphone" value="" pattern="[0-9]{1,25}" maxlength="8" required>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" class="form-control" name="email" placeholder="Please enter customer email address" value="{{ $customer->email}}">
                                </div>

                            </div>
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Physical Address</label>
                                    <input type="text" class="form-control" name="address" placeholder="Please enter customers physical address"  maxlength="60" required>
                                </div>
                            </div>


                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Date Of Birth</label>
                                    <input type="text" class="form-control kt_datepicker_1" name="dob" id="dob" autocomplete="off"  placeholder="Select date"/>
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <h3 class="kt-heading kt-heading--md">
                            2. Product Selection:
                        </h3>
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Please choose the product</label>
                                    <select class="form-control kt_selectpicker" name="product" title="Please choose product" data-live-search="true" id="product" required>
                                        @foreach($products as $product)
                                            <option data-subtext="{!! $product->name !!}" value="{!! $product->id !!}">{!! $product->name !!}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="factor_main"></div>
                        <div id="vehicle_section" style="display: none;">
                            <h3 class="kt-heading kt-heading--md">
                                Vehicle Selection:
                            </h3>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Vehicle Number</label>
                                        <input type="text" class="form-control vehiclePlate" name="vehiclePlate"  placeholder="Enter vehicle number">
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Chassis Number</label>
                                        <input type="text" class="form-control chassisNo" name="chassisNo"  placeholder="Enter chassis number">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Odometer</label>
                                        <input type="text" class="form-control odometer" name="odometer"  placeholder="Enter odometer number">
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Purpose</label>
                                        <input type="text" class="form-control purpose" name="purpose"  placeholder="Enter purpose here">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="errorLabel">Condition</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="condition" value="very poor" class="premiumField condition">
                                                Very Poor <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="condition" value="poor" class="premiumField condition">
                                                Poor<span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="condition" value="good" class="premiumField condition">
                                                Good <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="condition" value="very good" class="premiumField condition">
                                                Very Good <span></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div id="condition-error" class="error invalid-feedback" style="display: none;"></div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Year of Manufacture</label>
                                        <select class="form-control  kt_selectpicker"  name="date" title="Please choose Year" data-live-search="true" data-size="5">
                                            <option value="2021">2021</option>
                                            <option value="2020">2020</option>
                                            <option value="2019">2019</option>
                                            <option value="2018">2018</option>
                                            <option value="2017">2017</option>
                                            <option value="2016">2016</option>
                                            <option value="2015">2015</option>
                                            <option value="2014">2014</option>
                                            <option value="2013">2013</option>
                                            <option value="2012">2012</option>
                                            <option value="2011">2011</option>
                                            <option value="2010">2010</option>
                                            <option value="2009">2009</option>
                                            <option value="2008">2008</option>
                                            <option value="2007">2007</option>
                                            <option value="2006">2006</option>
                                            <option value="2005">2005</option>
                                            <option value="2004">2004</option>
                                            <option value="2003">2003</option>
                                            <option value="2002">2002</option>
                                            <option value="2001">2001</option>
                                            <option value="2000">2000</option>
                                            <option value="1999">1999</option>
                                            <option value="1998">1998</option>
                                            <option value="1997">1997</option>
                                            <option value="1996">1996</option>
                                            <option value="1995">1995</option>
                                            <option value="1994">1994</option>
                                            <option value="1993">1993</option>
                                            <option value="1992">1992</option>
                                            <option value="1991">1991</option>
                                            <option value="1990">1990</option>
                                            <option value="1989">1989</option>
                                            <option value="1988">1988</option>
                                            <option value="1987">1987</option>
                                            <option value="1986">1986</option>
                                            <option value="1985">1985</option>
                                            <option value="1984">1984</option>
                                            <option value="1983">1983</option>
                                            <option value="1982">1982</option>
                                            <option value="1981">1981</option>
                                            <option value="1980">1980</option>
                                            <option value="1979">1979</option>
                                            <option value="1978">1978</option>
                                            <option value="1977">1977</option>
                                            <option value="1976">1976</option>
                                            <option value="1975">1975</option>
                                            <option value="1974">1974</option>
                                            <option value="1973">1973</option>
                                            <option value="1972">1972</option>
                                            <option value="1971">1971</option>
                                            <option value="1970">1970</option>
                                            <option value="1969">1969</option>
                                            <option value="1968">1968</option>
                                            <option value="1967">1967</option>
                                            <option value="1966">1966</option>
                                            <option value="1965">1965</option>
                                            <option value="1964">1964</option>
                                            <option value="1963">1963</option>
                                            <option value="1962">1962</option>
                                            <option value="1961">1961</option>
                                            <option value="1960">1960</option>
                                            <option value="1959">1959</option>
                                            <option value="1958">1958</option>
                                            <option value="1957">1957</option>
                                            <option value="1956">1956</option>
                                            <option value="1955">1955</option>
                                            <option value="1954">1954</option>
                                            <option value="1953">1953</option>
                                            <option value="1952">1952</option>
                                            <option value="1951">1951</option>
                                            <option value="1950">1950</option>
                                            <option value="1949">1949</option>
                                            <option value="1948">1948</option>
                                            <option value="1947">1947</option>
                                            <option value="1946">1946</option>
                                            <option value="1945">1945</option>
                                            <option value="1944">1944</option>
                                            <option value="1943">1943</option>
                                            <option value="1942">1942</option>
                                            <option value="1941">1941</option>
                                            <option value="1940">1940</option>
                                            <option value="1939">1939</option>
                                            <option value="1938">1938</option>
                                            <option value="1937">1937</option>
                                            <option value="1936">1936</option>
                                            <option value="1935">1935</option>
                                            <option value="1934">1934</option>
                                            <option value="1933">1933</option>
                                            <option value="1932">1932</option>
                                            <option value="1931">1931</option>
                                            <option value="1930">1930</option>
                                            <option value="1929">1929</option>
                                            <option value="1928">1928</option>
                                            <option value="1927">1927</option>
                                            <option value="1926">1926</option>
                                            <option value="1925">1925</option>
                                            <option value="1924">1924</option>
                                            <option value="1923">1923</option>
                                            <option value="1922">1922</option>
                                            <option value="1921">1921</option>
                                            <option value="1920">1920</option>
                                            <option value="1919">1919</option>
                                            <option value="1918">1918</option>
                                            <option value="1917">1917</option>
                                            <option value="1916">1916</option>
                                            <option value="1915">1915</option>
                                            <option value="1914">1914</option>
                                            <option value="1913">1913</option>
                                            <option value="1912">1912</option>
                                            <option value="1911">1911</option>
                                            <option value="1910">1910</option>
                                            <option value="1909">1909</option>
                                            <option value="1908">1908</option>
                                            <option value="1907">1907</option>
                                            <option value="1906">1906</option>
                                            <option value="1905">1905</option>
                                            <option value="1904">1904</option>
                                            <option value="1903">1903</option>
                                            <option value="1902">1902</option>
                                            <option value="1901">1901</option>

                                        </select>

                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Make</label>
                                        <input type="text" class="form-control make" name="make"  placeholder="Enter vehicle make">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Model</label>
                                        <input type="text" class="form-control model" name="model"  placeholder="Enter vehicle model">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Registration Number</label>
                                        <input type="text" class="form-control registration_no" name="reg_No" title="Please enter registration number"  placeholder="Enter vehicle Registration No">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Engine Number</label>
                                        <input type="text" class="form-control engineNo" name="engineNo" autocomplete="off"
                                               placeholder="Enter Engine Number" title="Enter numbers only"
                                               onkeydown="return restrictAlphabets(event);"
                                               onblur="if (this.value == '') {this.value = 'Enter Numbers Only';}"
                                               onfocus="if (this.value == 'Enter Numbers Only') {this.value = '';}"/>
                                        <input type="hidden" value="enter numbers only" id="error">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Number of Cylinders</label>
                                        <input type="text" class="form-control cylinders" name="cylinders" title="Enter numbers only"
                                               placeholder="Enter Number of Cylinders"
                                               onkeydown="return restrictAlphabets(event);"
                                               onblur="if (this.value == '') {this.value = 'Enter Numbers Only';}"
                                               onfocus="if (this.value == 'Enter Numbers Only') {this.value = '';}"/>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label> Cubic Capacity</label>
                                        <input type="text" class="form-control cubic_capacity" name="cubic_capacity"  placeholder="Enter Cubic Capacity"
                                               onkeydown="return restrictAlphabets(event);"
                                               onblur="if (this.value == '') {this.value = 'Enter Numbers Only';}"
                                               onfocus="if (this.value == 'Enter Numbers Only') {this.value = '';}"/>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>No Of Seats</label>
                                        <input type="text" class="form-control seats" name="seats" title="Enter numbers only" placeholder="Enter No Of Seats"
                                               onkeydown="return restrictAlphabets(event);"
                                               onblur="if (this.value == '') {this.value = 'Enter Numbers Only';}"
                                               onfocus="if (this.value == 'Enter Numbers Only') {this.value = '';}"  />

                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Vechicle ID Number (VIN Code)</label>
                                        <input type="text" class="form-control vin" name="vin"  placeholder="Enter Vechicle ID Number (VIN Code)"
                                               onkeydown="return restrictAlphabets(event);"
                                               onblur="if (this.value == '') {this.value = 'Enter Numbers Only';}"
                                               onfocus="if (this.value == 'Enter Numbers Only') {this.value = '';}"  />
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Is it imported?</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="is_imported" value="1" class="premiumField ">
                                                Yes <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="is_imported" value="0" class="premiumField ">
                                                No<span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Is the vehicle fitted with tracking device?</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="is_tracking" value="1" class="premiumField ">
                                                Yes <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="is_tracking" value="0" class="premiumField ">
                                                No<span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Is the vehicle used for private use?</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="is_private" value="1" class="premiumField ">
                                                Yes <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="is_private" value="0" class="premiumField ">
                                                No<span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Is the vehicle modified in any way?</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="is_modified" value="1" class="premiumField ">
                                                Yes <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="is_modified" value="0" class="premiumField ">
                                                No<span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h3 class="kt-heading kt-heading--md">
                                Vehicle Images
                            </h3>
                            <div class="form-group row">
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Left</h3>
                                    <div class="kt-avatar" id="left" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="left" name="left" <?php echo config('app.accept_attr'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Right</h3>
                                    <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="right" name="right" <?php echo config('app.accept_attr'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Back</h3>
                                    <div class="kt-avatar" id="back" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="back" name="back" <?php echo config('app.accept_attr'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Front</h3>
                                    <div class="kt-avatar" id="front" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="front" name="front" <?php echo config('app.accept_attr'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Vehicle Registration</h3>
                                    <div class="kt-avatar" id="vehicleRegistration" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="vehicleRegistration" name="vehicleRegistration" <?php echo config('app.accept_attr'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            <h3 class="kt-heading kt-heading--md">
                                Coverages
                            </h3>
                            <div class="row">
                                <table class="table table-striped m-table">
                                    <tbody id="coverageDiv"></tbody>
                                </table>
                            </div>
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            <h3 class="kt-heading kt-heading--md">
                                Specified Motor Items
                            </h3>
                            <div class="row">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    <tr>
                                        <th>Description Of Items</th>
                                        <th>Sum Insured</th>
                                        <th>Actions</th>
                                    </tr>
                                    <tr class="fieldGroup">
                                        <td>
                                            <select class="form-control" name="item_name[]" title="Please choose Items" data-live-search="true">
                                                <option value="1">Item 1</option>
                                                <option value="2">Item 2</option>
                                                <option value="3">Item 3</option>
                                            </select>
                                        </td>
                                        <td><input type='text' class="form-control" name="item_value[]" /></td>
                                        <td><a class="btn btn-primary addMore" style="color:#fff;">Add</a> </td>
                                    </tr>
                                    <!-- copy of input fields group -->
                                    <tr class="fieldGroupCopy" style="display: none;">
                                        <td>
                                            <select class="form-control" name="item_name[]" title="Please choose Items">
                                                <option value="">Please choose Items</option>
                                                <option value="1">Item 1</option>
                                                <option value="2">Item 2</option>
                                                <option value="3">Item 3</option>
                                            </select>
                                        </td>
                                        <td><input type='text' class="form-control" name="item_value[]" /></td>
                                        <td><a class="btn btn-danger remove" style="color:#fff;">Remove</a> </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <h3 class="kt-heading kt-heading--md">
                                            Note
                                        </h3>
                                        <textarea class="form-control" name="note" placeholder="Add Note" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div id="members_section" style="display: none;">
                            <button type="button" id="addMember" class="btn btn-primary">Add Family Members</button>
                        </div>
                        <div class="row" id="addMemberDiv" style="display: none;">
                            <div class="col-lg-12">
                                <h3 class="kt-heading kt-heading--md">
                                    3. Add Family Members:
                                </h3>
                                <div class="kt-repeater">
                                    <div data-repeater-list="members">
                                        <div data-repeater-item class="kt-repeater__item">
                                            <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin">
                                                Member Info
                                            </h3>
                                            <div class="form-group">
                                                <label>Which family member would you like to add ?</label>
                                                <div class="col-lg-12">
                                                    <select class="form-control memberRelatives" name="relation" id="memberRelatives" >
                                                        <option value="">Please Choose...</option>
                                                        <option value="spouse">Spouse</option>
                                                        <option value="child">Child</option>
                                                        <option value="parent">Parent</option>
                                                        <option value="parent-in-law">Parent-in-law</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>First name</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="First name" name="memberFName">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Last name</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="memberLName" placeholder="Last name">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Date Of Birth</label>
                                                    <input type="text" class="form-control kt_datepicker_1" name="memberDOB" autocomplete="off" placeholder="Select date"/>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Gender</label>
                                                    <div class="kt-radio-inline">
                                                        <label class="kt-radio">
                                                            <input type="radio" name="memberGender" value="1">
                                                            Male <span></span>
                                                        </label>
                                                        <label class="kt-radio">
                                                            <input type="radio" name="memberGender" value="0">
                                                            Female<span></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="kt-repeater__data form-group">
                                                <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                                            </div>
                                            <div class="kt-separator kt-separator--border-dashed"></div>
                                            <div class="kt-separator kt-separator--height-sm"></div>
                                        </div>
                                    </div>
                                    <div class="kt-repeater__add-data">
                                        <span data-repeater-create="" class="btn btn-brand btn-sm" > <i class="la la-plus"></i> Add Members</span>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet__foot">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" value="Submit" class="btn btn-brand">Submit</button>
                                        <button type="button" value="Submit" class="btn btn-success">Save as Qoute</button>
                                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <!--end::Form-->
                </div>
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

<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/layouts/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>


<script>

    function statusMsg(){
        var isChecked=document.getElementById("switchValue").checked;
        if (isChecked){
            document.getElementById("switchMsg").innerHTML="NEW";
            document.getElementById("switchMsg").style.color="cornflowerblue";

        }
        else {
            document.getElementById("switchMsg").innerHTML="EXITSING";
            document.getElementById("switchMsg").style.color="#ff4d4d";
        }

        $('#customers').toggle();

    }


    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        jQuery.validator.addMethod("future", function(value, element) {
            return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
        }, "Please enter only past dates");

        var demo1 = function () {
            $( "#policyForm" ).validate({
// define validation rules
                rules: {
                    fname: {
                        required: true
                    },
                    lname: {
                        required: true
                    },
                    gender: {
                        required: true
                    },
                    cellphone: {
                        required: true
                    },
                    email: {
                        required: true
                    },
                    address: {
                        required: true
                    },
                    omang: {
                        require_from_group: [1, '.validateGroup1'],
                    },
                    passport: {
                        require_from_group: [1, '.validateGroup1'],
                    },
                    dob: {
                        required: true,
                        future: true,
                    },
                    accountNumber: {
                        required: true,
                    },
                },
                groups: {
                    validateGroup1: "omang passport"
                },
                messages: {
                    fname: "Please enter your First Name",
                    lname: "Please enter your Last Name",
                    gender: "Please select Gender",
                    cellphone: "Please enter your Cell phone number",
                    email: "Please enter your email",
                    address: "Please enter your address",
                    omang: {
                        require_from_group: "Please provide either your Omang Id or Passport",
                        max: "Your Omang Id can be max 9 characters long",
                        maxlength: "Your Omang Id can be max 9 characters long"
                    },
                    passport: {
                        require_from_group: "Please provide either your Omang Id or Passport",
                        max: "Your Passport can be max 12 characters long"
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    //KTUtil.scrollTo(validator.focusInvalid());
                    $('html, body').animate({
                        scrollTop: $(validator.errorList[0].element).offset().top - 200
                    }, 1000);

                },

                submitHandler: function (form) {
                    form[0].submit(); // submit the form
                }
            });
        }

        return {
// public functions
            init: function() {
                demo1();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTFormControls.init();


        //group add limit
        var maxGroup = 10;

        //add more fields group
        $(".addMore").click(function(){
            if($('body').find('.fieldGroup').length < maxGroup){
                var fieldHTML = '<tr class="fieldGroup">'+$(".fieldGroupCopy").html()+'</tr>';
                $('body').find('.fieldGroup:last').before(fieldHTML);
            }else{
                alert('Maximum '+maxGroup+' groups are allowed.');
            }
        });

        //remove fields group
        $("body").on("click",".remove",function(){
            $(this).parents(".fieldGroup").remove();
        });

    });


    $("#factor_main").on('click', '#calculate', function(){

        var selectOption = ($("[name^='factor_']").find(":selected").val());
        var CheckRoadioArray = $("[name^='factor_']:checked, [name^='factor_']:selected").map(function() {
            return $(this).val();
        }).get();

        $("#policyForm").validate().settings.ignore = ":input:not([name^='factor_'])";

        if($("#policyForm").valid())
        {
            $.ajax({
                url: '{{ route('admin.policy.calculatePremium') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "product_id": $('#product').val(),
                    "factors": CheckRoadioArray+','+selectOption,
                },
                type: 'post',
                datatype : 'json',
                success: function(data) {
                    var append = '';
                    $('#premiumDiv').empty();
                    if(data.status == 'success'){

                        $('<h3>Your Calculated Premium is : '+data.premium+' P</h3><input type="hidden" name="premium" value="'+data.premium+'">').appendTo('#premiumDiv');
                    }else{
                        var url = '{{ route("admin.product.formula",":id") }}';
                        url = url.replace(':id', $.trim($('#product').val()));
                        $('<h4>Formula is not defined. You can set the formula using <a href="'+url+'" title="Formula" target="_blank">THIS LINK</a></h4>').appendTo('#premiumDiv');
                    }


                },
            });
        }

    });


    $("#factor_main").on('click', '#generate_code', function(){

        $.ajax({
            url: '{{ route('admin.policy.generateActivationCode') }}',
            data: {
                "_token": "{{ csrf_token() }}",
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                var append = '';
                $('#codeDiv').empty();
                if(data.status == 'success'){

                    $('<p  id="activation_code_msg" style="font-size: 20px; font-weight:bolder; margin-left:10%; margin-top: 20px;">  Activation Code Generated : '+data.code+' </p><input type="hidden"  name="activation_code" value="'+data.code+'">').appendTo('#codeDiv');
                }else {
                    $('<p>Activation Code Generated Not GeneratedstoreActivationCode</p>').appendTo('#codeDiv');

                }
            },
        });

    });

    $('#product').on('change', function() {

        var product_id = this.value;
        $.ajax({
            url: '{{ route('admin.policy.product_factors') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": product_id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                var append = '';
                var coverage = '';
                if(data){
                    data.factors.forEach(function($factor){
                        append += '<div class="col-lg-6">' +
                                '<div class="form-group">' +
                                '<label>'+$factor.name+'</label>';
                        if($factor.type == 'Select')
                        {
                            append += '<select class="form-control kt_selectpicker" name="factor_'+$factor.id+'" data-live-search="true" title="Please choose factor">';
                            append += '<option value="0">Select Option</option>';
                            if($factor.value.length > 0){
                                $factor.value.forEach(function($value){
                                    append += '<option value="'+$value.id+'">'+$value.name+'</option>';
                                });
                            }
                            append += '</select></div></div>';
                        } else if($factor.type == 'Radio')
                        {
                            append += '<div class="kt-radio-inline">';
                            if($factor.value.length > 0){
                                $factor.value.forEach(function($value){
                                    append += '<label class="kt-radio"><input type="radio" name="factor_'+$factor.id+'[]" value="'+$value.id+'">'+$value.name+' <span></span> </label>';
                                });
                            }
                            append += '</div></div></div>';
                        } else if($factor.type == 'Checkbox')
                        {
                            append += '<div class="kt-checkbox-inline">';
                            if($factor.value.length > 0){
                                $factor.value.forEach(function($value){
                                    append += '<label class="kt-checkbox"><input type="checkbox" name="factor_'+$factor.id+'[]" value="'+$value.id+'">'+$value.name+' <span></span> </label>';
                                });
                            }
                            append += '</div></div></div>';
                        } else if($factor.type == 'Input Field')
                        {
                            append += '<input type="text" class="form-control" id="'+$factor.name+'" name="factor_'+$factor.id+'"';
                            if($factor.name.indexOf('age') != -1 && $('#dob').val() != '') {
                                var dob = new Date($('#dob').val());
                                var today = new Date();
                                var age = Math.floor((today-dob) / (365.25 * 24 * 60 * 60 * 1000));
                                append += 'value="'+age+'"';
                            }
                            append += '></div></div>';
                        }

                    });
                    $('#factor_main').empty();


                    if( data.product.premium_type_id === 15)
                    {
                        append += '<div class="col-lg-12">' +
                                '<button type="button" id="calculate" class="btn btn-primary col-lg-2" style="float: left; clear: left;">Calculate Premium</button>' +
                                ' '+
                                '<div class="col-lg-10" id="premiumDiv" style="margin: 0.5% 0 0 20%;">' +
                                '</div></div>';
                    }
                    else if(data.product.premium_type_id === 11)
                    {
                        append += '<div class="col-lg-6">' +
                                '<label>Product Plans</label>';
                        append += '<select class="form-control kt_selectpicker plan" name="plan" data-live-search="true" title="Please choose Plan" id="planPremium">';
                        append += '<option value="0">Select Plan</option>';
                        if(data.plans.length > 0){
                            data.plans.forEach(function($value){
                                append += '<option value="'+$value.id+'">'+$value.name+' | Premium : '+$value.premium+' | Sum Assured : '+$value.sum_assured+'</option>';
                            });
                        }
                        append += '</select></div></div>';
                    }

                    /*-----------check activation code of product---------*/

                    if( data.product.has_activation_code == 1)
                    {
                        append += '<div class="col-lg-12" style="margin-top: 30px;">' +
                                    '<label>Do you have Activation Code?</label>' +
                                    '<br>'+
                                    '<label class="kt-radio">'+
                                    '<input type="radio" name="check_activation"  class="premiumField condition" value="Yes" >Yes<span></span>' +
                                    '</label>'+'    '+
                                    '<label class="kt-radio" style="margin-left: 10px;">'+
                                    '<input type="radio" name="check_activation" class="premiumField condition"  value="No" >No<span></span>' +
                                    '</label></div><br>'+
                                   '<div class="col-lg-10" >' +
                                    '<input type="text" name="activation_code" id="input_activation" class="form-control col-md-6" placeholder="Enter Activation Code" style="float: left; margin-top: 20px; clear: left; display:none;">'+
                                    '<input type="button" id="generate_code" value="Generate Activation Code" class="btn btn-primary col-md-3" style="float: left; clear: left; display:none; margin-top: 20px;">'+
                                    '<div class="col-lg-8" id="codeDiv" style="margin: 0.5% 0 0 20%;">' +
                                    '</div></div>';
                    }


                    $('#factor_main').html(append);

                    jQuery.validator.addMethod(
                            "notEqualTo",
                            function(elementValue,element,param) {
                                return elementValue != param;
                            },
                            "Value cannot be {0}"
                    );

                    // adding rules for inputs with class 'comment'
                    $("[name^='factor_']").each(function() {
                        $(this).rules("add",
                                {
                                    required: true,
                                    notEqualTo: 0,
                                    messages: {
                                        notEqualTo: "Please select value",
                                    }
                                })
                    });
                    if(data.product.premium_type_id === 11) {
                        $('.plan').rules('add',  { required: true, messages: { required: "Please Select Plan" } });
                        $('.plan').rules('add',  { notEqualTo: 0, messages: { required: "Please Select Plan" } });
                    }
                    KTFormControls.init();

                    if(data.product.has_vehicle == 1){
                        $('#vehicle_section').show();
                        $('.vehiclePlate').rules('add',  { required: true, messages: { required: "Please enter Vehicle No." } });
                        $('.chassisNo').rules('add',  { required: true, messages: { required: "Please enter Chassis No." } });
                        $('.odometer').rules('add',  { required: true, messages: { required: "Please enter Odometer" } });
                        $('.purpose').rules('add',  { required: true, messages: { required: "Please enter purpose" } });
                        $('.condition').rules('add',  { required: true, messages: { required: "Please select condition" } });
                        $('.vehicleDate').rules('add',  { required: true, messages: { required: "Please enter Vehicle purchase date" } });
                        $('.make').rules('add',  { required: true, messages: { required: "Please enter Make" } });
                        $('.model').rules('add',  { required: true, messages: { required: "Please enter Model" } });


                    } else {
                        $('#vehicle_section').hide();
                        $('.vehiclePlate').rules('remove',  'required');
                        $('.chassisNo').rules('remove',  'required');
                        $('.odometer').rules('remove',  'required');
                        $('.purpose').rules('remove',  'required');
                        $('.condition').rules('remove',  'required');
                        $('.vehicleDate').rules('remove',  'required');
                        $('.make').rules('remove',  'required');
                        $('.model').rules('remove',  'required');
                    }

                    //Coverage
                    coverage += '<tr>' +
                            '<th>Main</th>' +
                            '<th>Coverage Value</th>' +
                            '<th>Disc/Surcharge</th>' +
                            '<th>Flat/%</th>' +
                            '<th>Discount Value</th>' +
                            '</tr>';
                    data.coverage.forEach(function($cover){
                        coverage += '<tr>' +
                                '<td><input type="hidden" name="main[]" value="'+$cover.name+'">'+$cover.name+'</td>' +
                                '<td><input type="number" name="cover_value[]"></td>' +
                                '<td><select name="type[]" required><option value="0">Select Discount/Surcharge</option><option value="1">Discount</option><option value="2">Surcharge</option></select></td>' +
                                '<td><select name="disccount_type[]" required><option value="0">Select Flat/%</option><option value="1">Flat</option><option value="2">%</option></select></td>' +
                                '<td><input type="number" name="type_value[]"></td>' +
                                '</tr>';
                    });
                    $('#coverageDiv').empty();
                    $('#coverageDiv').append(coverage);


                    if(data.product.has_member == 1)
                        $('#members_section').show();
                    else
                        $('#members_section').hide();

                }else{
                    $('#factor_main').empty();
                    $('#factor_main').append("Nothing to Show");
                }


            },
        });

        $('.kt_selectpicker').selectpicker('render');
        $('.kt_selectpicker').selectpicker('refresh');

    });


    $('#addMember').click(function() {
        $('#addMemberDiv').toggle();
        $('#addMember').toggle();
    });




    "use strict";
    // Class definition

    var KTBootstrapDatepicker = function () {

        var arrows;
        if (KTUtil.isRTL()) {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        } else {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }

        // Private functions
        var demos = function () {
            // minimum setup
            $('.kt_datepicker_1').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'yyyy-mm-dd'
            });
        }

        return {
            // public functions
            init: function() {
                demos();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
    });

    //For Dynamic Fields
    $('.kt-repeater__add-data').on('click',".btn-success", function(){

        var arrows;
        if (KTUtil.isRTL()) {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        } else {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }

        $('.kt_datepicker_1').datepicker({
            rtl: KTUtil.isRTL(),
            todayHighlight: true,
            orientation: "bottom left",
            templates: arrows,
            format: 'yyyy-mm-dd'
        });

        KTBootstrapDatepicker.init();
    });


    // Avatar Class definition
    var KTAvatarDemo = function() {

        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('front');
                var avatar2 = new KTAvatar('back');
                var avatar3 = new KTAvatar('left');
                var avatar4 = new KTAvatar('right');
                var avatar5 = new KTAvatar('vehicleRegistration');
            }
        };
    }();

    // Class initialization on page load
    jQuery(document).ready(function() {
        KTAvatarDemo.init();
    });
</script>





<script>
$(document).ready(function(){
    $("#carSpinner").css("display", "none");
    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    $("#carMakeDropDown").change(function(){

    $("#carSpinner").css("display", "contents");
    $.ajax({
    /* the route pointing to the post function */
    url: '{{Route('getCarModel')}}',
            type: 'POST',
            /* send the csrf-token and the input to the controller */
            data: {
            _token: CSRF_TOKEN,
                    make:$('#carMakeDropDown option:selected').val()
            },
            dataType: 'JSON',
            /* remind that 'data' is the response of the AjaxController */
            success: function (data) {
            if (data) {
            $("#carModelDropDown").removeAttr('title');
            $("#carModelDropDown").removeAttr('disabled');
            $('#carModelDropDown').empty();
            $.each(data, function(key, value){
            $('#carModelDropDown').append('<option value="' + value.s_Variant + '">' + value.s_Variant + '</option>');
            $("#carModelDropDown").selectpicker('refresh');
            });
            $("#carSpinner").css("display", "none");
            } else {
            $('#carModelDropDown').empty();
            }
            }
    });
    });
    });

    $(document).ready(function() {
        var ajaxRequest;
        $('#omang').keyup(function() {
            var value = $(this).val();
            clearTimeout(ajaxRequest);
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.policy.checkUserOmang') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "omang": value
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if(data.count > 0) {
                            $('#omangError').css('display','block');
                        } else {
                            $('#omangError').css('display','none');
                        }
                    }
                });
            }, 500, value);
        });
    });

    $(document).ready(function() {
        var ajaxRequest;
        $('#passport').keyup(function() {
            var value = $(this).val();
            clearTimeout(ajaxRequest);
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.policy.checkUserPassport') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "passport": value
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if(data.count > 0) {
                            $('#passportError').css('display','block');
                        }
                        else {
                            $('#passportError').css('display','none');
                        }
                    }
                });
            }, 500, value);
        });
    });
</script>

<script type="text/javascript">
    function restrictAlphabets(event) {
        var key = event.keyCode;
        return ((key >= 48 && key <= 57) || key == 8 || key>=35 && key<=40 || key==46);
    };

</script>

<script type="text/javascript">


    $("#factor_main").on("change", "input[name=check_activation]:radio", function(){
        $Checkval =$(this).val();
        if($Checkval == 'Yes'){
            $('#input_activation').css('display','block');
            $('#generate_code').css('display','none');
            $('#activation_code_msg').css('display','none');

        }
        else{
            $('#input_activation').css('display','none');
            $('#generate_code').css('display','block');
        }
    });

     $(document).ready(function() {
     var ajaxRequest;
     $('#omang').keyup(function() {
     var value = $(this).val();
     clearTimeout(ajaxRequest);
     ajaxRequest = setTimeout(function(sn) {
         $.ajax({
     url: '{{ route('admin.policy.checkUserOmang') }}',
     data: {
     "_token": "{{ csrf_token() }}",
     "omang": value
     },
     type: 'post',
     datatype : 'json',
     success: function (data) {
     if(data.count > 0) {
     $('#omangError').css('display','block');
     } else {
     $('#omangError').css('display','none');
     }
     }
     });


     }, 500, value);
     });
     });


</script>





</body>
<!-- end::Body -->
</html>
