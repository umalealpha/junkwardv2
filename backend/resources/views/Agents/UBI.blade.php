<!DOCTYPE html>

<html lang="en" >
    <!-- begin::Head -->
   @include('Agents.Layout.header')

    <!-- end::Head -->
    <!-- begin::Body -->

        <!-- end:: Header Mobile -->
        <!-- begin:: Root -->
        <div class="kt-grid kt-grid--hor kt-grid--root">
            <!-- begin:: Page -->
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

      @include('Agents.Layout.sidebar')
           @include('Agents.Layout.topNav')
                        <!-- begin:: Content -->
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                            <!--begin::Dashboard 4-->
                            <!--begin::Row-->
                            <div class="row">
                                <div class="col-lg-3 col-xl-3 order-lg-1 order-xl-1 iconspeed">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">Your Score</h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fluid">
                                            <div class="kt-widget-19">
                                                <div class="kt-widget-19__title">
                                                    <div class="kt-widget-19__label"><span>150+</span><small>km/h</small></div>
                                                    <img class="kt-widget-19__bg" src="{{asset('media/misc/iconbox_bg.png')}}" alt="bg"/>
                                                </div>
                                                <div class="kt-widget-19__data">
                                                    <!--Doc: For the chart bars you can use state helper classes: kt-bg-success, kt-bg-info, kt-bg-danger. Refer: components/custom/colors.html -->
                                                    <div class="kt-widget-19__chart">
														<i class="flaticon-placeholder"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <div class="col-lg-3 col-xl-3 order-lg-1 order-xl-1 iconspeed">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Total Km Covered
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body--fluid">
                                            <div class="kt-widget-21">
                                                <div class="kt-widget-21__title">
                                                    <div class="kt-widget-21__label"><span>7000+</span><small>RPM</small></div>
                                                    <img src="{{asset('media/misc/iconbox_bg.png')}}" class="kt-widget-21__bg" alt="bg"/>
                                                </div>
                                                <div class="kt-widget-21__data">
													<i class="flaticon-dashboard"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div> 
                                <div class="col-lg-6">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Last Ride Details
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
											<ul class="list-group">
                                               <li class="list-group-item d-flex justify-content-between align-items-center">KM's covered <span class="badge badge-primary badge-pill">94 Km</span> </li>
                                               <li class="list-group-item d-flex justify-content-between align-items-center"> Total Premium for ride <span class="badge badge-primary badge-pill">950P</span> </li>
                                               <li class="list-group-item d-flex justify-content-between align-items-center"> Total duration Covered in month <span class="badge badge-primary badge-pill">1000</span> </li>
                                               <li class="list-group-item d-flex justify-content-between align-items-center"> Total  Premium for this month <span class="badge badge-primary badge-pill">17500P</span> </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                            </div>
                            <div class="row">
                                <!--- search --->
                                <div class="col-lg-12">
                                <div class="kt-portlet">
                                        <div class="kt-portlet__head">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Categories
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body">
                                            <div class="kt-notification-v2 categories" >
                                                <a href="#" class="kt-notification-v2__item alert-success">
                                                    <div class="kt-notification-v2__item-icon"> <i class="flaticon-light kt-font-success"></i> </div>
                                                    <div class="kt-notification-v2__item-wrapper">
                                                        <div class="kt-notification-v2__item-title">Acceleration</div>
                                                        <div class="kt-notification-v2__item-desc"> 50 </div>
                                                    </div>
                                                </a>
                                                <a href="#" class="kt-notification-v2__item alert-danger">
                                                    <div class="kt-notification-v2__item-icon"> <i class="flaticon-time-1 kt-font-danger"></i> </div>
                                                    <div class="kt-notification-v2__item-wrapper">
                                                        <div class="kt-notification-v2__item-title">Braking </div>
                                                        <div class="kt-notification-v2__item-desc"> 70 </div>
                                                    </div>
                                                </a>
                                                <a href="#" class="kt-notification-v2__item alert-info">
                                                    <div class="kt-notification-v2__item-icon"> <i class="flaticon-dashboard kt-font-primary"></i> </div>
                                                    <div class="kt-notification-v2__item-wrapper">
                                                        <div class="kt-notification-v2__item-title">Cornering </div>
                                                        <div class="kt-notification-v2__item-desc"> 80 </div>
                                                    </div>
                                                </a>
                                                <a href="#" class="kt-notification-v2__item alert-dark">
                                                    <div class="kt-notification-v2__item-icon"> <i class="flaticon-time kt-font-brand"></i> </div>
                                                    <div class="kt-notification-v2__item-wrapper">
                                                        <div class="kt-notification-v2__item-title"> Lane Handing</div>
                                                        <div class="kt-notification-v2__item-desc"> 60 </div>
                                                    </div>
                                                </a>
                                                <a href="#" class="kt-notification-v2__item alert-warning">
                                                    <div class="kt-notification-v2__item-icon"> <i class="flaticon-stopwatch kt-font-warning"></i> </div>
                                                    <div class="kt-notification-v2__item-wrapper">
                                                        <div class="kt-notification-v2__item-title"> Speeding </div>
                                                        <div class="kt-notification-v2__item-desc"> 70 </div>
                                                    </div>
                                                </a>

                                            <div class="ps__rail-x" style="left: 0px; bottom: -548px;"><div class="ps__thumb-x" tabindex="0" style="left: 0px; width: 0px;"></div></div><div class="ps__rail-y" style="top: 548px; height: 550px; right: 0px;"><div class="ps__thumb-y" tabindex="0" style="top: 275px; height: 275px;"></div></div></div>
                                        </div>
                                    </div>
                                </div>
                                <!---/ search --->
                            </div>
                            <div class="row">
                                <!--- map --->
                                <div class="col-lg-12 order-lg-1 order-xl-1">
                                    <!--begin::Portlet-->
                                    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head--lg kt-portlet__head kt-portlet__head--break-sm">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                   Smart-view <small>auto last 24 Location Report</small>
                                                </h3>
                                            </div>
                                        </div>
                                        <div class="kt-portlet__body kt-portlet__body">
                                        <div class="col-md-12">
                                        <div class="row">
											<div class="col-md-12">
												<div class="row">
													<div class="col-md-6 datecheck">
														<div class="row">
														<div class="form-group col-md-8">
														<label for="example-date-input">Date</label>
															<input class="form-control" type="date" value="2011-08-19" id="example-date-input">
														</div>
														<div class="form-group col-md-4">
															<label>This month</label>
															<div class="kt-checkbox-list">
																<label class="kt-checkbox">
																	<input type="checkbox">
																	<span></span> 
																</label>
															</div>
														</div>
														</div>
													</div>
													
													<div class="col-md-3">
														<div class="form-group">
														<label for="example-date-input">Date</label>
															<input class="form-control" type="date" value="2011-08-19" id="example-date-input">
														</div>
													</div>
													<div class="col-md-3">
														<div class="form-group">
														<label for="exampleInputPassword1">Search</label>
														<input type="text" class="form-control" id="exampleInputPassword1" placeholder="Search">
													</div>
													</div>
													</div>
											</div>
											<div class="col-lg-4 col-xl-4 order-lg-1 order-xl-1 three">
												<!--begin::Portlet-->
												<div class="kt-portlet kt-portlet--fit kt-portlet--height-fluid">
													<div class="kt-portlet__body kt-portlet__body--fluid">
														<div class="kt-widget-3 kt-widget-3--brand">
															<div class="kt-widget-3__content">
															<div class="kt-widget-3__content-title">Braking Score </div>
																<div class="kt-widget-3__content-info">
																	<div class="kt-widget-3__content-section">
																		<div class="kt-widget-3__content-desc">
																			<i class="la la-arrow-right"></i>
																		</div>
																	</div>
																	<div class="kt-widget-3__content-section">
																		<span class="kt-widget-3__content-bedge"></span> 
																		<span class="kt-widget-3__content-number">1<span></span></span>
																	</div>
																</div>
																<div class="kt-widget-3__content-stats">
																	<div class="kt-widget-3">
																	<p>Per 1000 miles</p>
																		<button type="button" class="btn btn-secondary btn-elevate btn-pill">View Details</button>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
												<!--end::Portlet-->
											</div>
											<div class="col-lg-4 col-xl-4 order-lg-1 order-xl-1 three">
												<!--begin::Portlet-->
												<div class="kt-portlet kt-portlet--fit kt-portlet--height-fluid">
													<div class="kt-portlet__body kt-portlet__body--fluid">
														<div class="kt-widget-3 kt-widget-3--danger">
															<div class="kt-widget-3__content">
															<div class="kt-widget-3__content-title">Mirror Check</div>
																<div class="kt-widget-3__content-info">
																	<div class="kt-widget-3__content-section">
																		<div class="kt-widget-3__content-desc">
																		<i class="la la-arrow-right"></i>
																		</div>
																	</div>
																	<div class="kt-widget-3__content-section ">
																		<span class="kt-widget-3__content-number">71<span></span></span>
																	</div>
																</div>
																<div class="kt-widget-3__content-stats">
																	<div class="kt-widget-3">
																	<p>Out of 100</p>
																		<button type="button" class="btn btn-secondary btn-elevate btn-pill">View Details</button>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
												<!--end::Portlet-->
											</div>
											<div class="col-lg-4 col-xl-4 order-lg-1 order-xl-1 three">
												<!--begin::Portlet-->
												<div class="kt-portlet kt-portlet--fit kt-portlet--height-fluid">
													<div class="kt-portlet__body kt-portlet__body--fluid">
														<div class="kt-widget-3 kt-widget-3--success">
															<div class="kt-widget-3__content">
															<div class="kt-widget-3__content-title">Speeding</div>
																<div class="kt-widget-3__content-info">
																	<div class="kt-widget-3__content-section">
																		<div class="kt-widget-3__content-desc">
																		<i class="la la-arrow-right"></i>
																		</div>
																	</div>
																	<div class="kt-widget-3__content-section">
																		<span class="kt-widget-3__content-number">5<span>%</span></span>
																	</div>
																</div>
																<div class="kt-widget-3">
																<p>% of time spent over 67 MPH</p>
																	<button type="button" class="btn btn-secondary btn-elevate btn-pill">View Details</button>
																</div>
															</div>
														</div>
													</div>
												</div>
												<!--end::Portlet-->
											</div>
											</div>
											<iframe src="https://www.google.com/maps/d/embed?mid=1GC-26pGg4Z58eto8mukpi6dyUj4&hl=en" width="100%s" height="480" frameborder="0" border-width="0px"></iframe>
                                       	</div>
                                        </div>
                                    </div>
                                    <!--end::Portlet-->
                                </div>
                                <!---/ map --->
                            </div>
<!---new--->
 <div class="row newdriver">
 	<div class="col-lg-6 order-lg-1 order-xl-1">
 	<div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head">
												<div class="form-group vehiclecomb">
													<select class="form-control" id="exampleSelect1">
														<option value="0">Vehicle list 1</option>
														<option value="1">Vehicle list 2</option>
														<option value="2">Vehicle list 3</option>
														<option value="3">Vehicle list 4</option>
														<option value="4">Vehicle list 5</option>
													</select>
												</div>
                                        </div>
                                        <div class="kt-portlet__body list">
                                            <div class="kt-widget-18">
                                                <div class="kt-widget-18__summary">
                                                    <div class="kt-widget-18__total">List</div>
                                                    <div class="kt-widget-18__label">7,300</div>
                                                </div>
                                                <div class="kt-widget-18__item">
                                                    <div class="kt-widget-18__legend kt-bg-brand"><i class="la la-edit"></i></div>
                                                    <div class="kt-widget-18__desc">
                                                        <a href="">
                                                            <div class="kt-widget-18__title"> DBG256F </div>
                                                        </a>
                                                        <div class="kt-widget-18__desc">Out of 100<br>
														% of time spent over 67 MPH</div>
                                                    </div>
                                                    <div class="kt-widget-18__orders"> <span>3,244</span> Orders </div>
                                                </div>
                                                <div class="kt-widget-18__item">
                                                    <div class="kt-widget-18__legend kt-bg-success"><i class="la la-edit"></i></div>
                                                    <div class="kt-widget-18__desc">
                                                        <a href="">
                                                            <div class="kt-widget-18__title"> B2B2C </div>
                                                        </a>
                                                        <div class="kt-widget-18__desc">Out of 90<br>
														5% of time spent over 53 MPH</div>
                                                    </div>
                                                    <div class="kt-widget-18__orders"> <span>962</span> Orders </div>
                                                </div>
                                                <div class="kt-widget-18__item">
                                                    <div class="kt-widget-18__legend kt-bg-danger"><i class="la la-edit"></i></div>
                                                    <div class="kt-widget-18__desc">
                                                        <a href="">
                                                            <div class="kt-widget-18__title"> DFGDF6786 </div>
                                                        </a>
                                                        <div class="kt-widget-18__desc">Out of 70<br>
														10% of time spent over 55 MPH</div>
                                                    </div>
                                                    <div class="kt-widget-18__orders"> <span>2,750</span> Orders </div>
                                                </div>
                                                <div class="kt-widget-18__item">
                                                    <div class="kt-widget-18__legend kt-bg-info"><i class="la la-edit"></i></div>
                                                    <div class="kt-widget-18__desc">
                                                        <a href="">
                                                            <div class="kt-widget-18__title"> GFHGHJ45 </div>
                                                        </a>
                                                        <div class="kt-widget-18__desc">Out of 60<br>
														2% of time spent over 53 MPH</div>
                                                    </div>
                                                    <div class="kt-widget-18__orders"> <span>890</span> Orders </div>
                                                </div>
                                                <div class="kt-widget-18__item">
                                                    <div class="kt-widget-18__legend kt-bg-dark"><i class="la la-edit"></i></div>
                                                    <div class="kt-widget-18__desc">
                                                        <a href="">
                                                            <div class="kt-widget-18__title"> MJBKJGH5 </div>
                                                        </a>
                                                        <div class="kt-widget-18__desc">Out of 80<br>
														5% of time spent over 34 MPH</div>
                                                    </div>
                                                    <div class="kt-widget-18__orders"> <span>1,644</span> Orders </div>
                                                </div>
                                                <div class="kt-widget-18__item">
                                                    <div class="kt-widget-18__legend kt-bg-warning"><i class="la la-edit"></i></div>
                                                    <div class="kt-widget-18__desc">
                                                        <a href="">
                                                            <div class="kt-widget-18__title"> ESDFV524 </div>
                                                        </a>
                                                        <div class="kt-widget-18__desc">Out of 90<br>
														4% of time spent over 24 MPH</div>
                                                    </div>
                                                    <div class="kt-widget-18__orders"> <span>1,644</span> Orders </div>
                                                </div>

                                                <!--
			<div class="kt-widget-18__item">
				<div class="kt-widget-18__legend kt-bg-success"></div>
				<div class="kt-widget-18__desc">
					<a href=""><div class="kt-widget-18__title">
						Dashboard System
					</div></a>
					<div class="kt-widget-18__desc">
						Angular, Oracle, Java
					</div>
				</div>
				<div class="kt-widget-18__orders">
					<span>560</span> Orders
				</div>
			</div>
			-->
                                            </div>
                                        </div>
                                    </div>
	</div>
	<div class="col-lg-6 order-lg-1 order-xl-1">
	 <div class="col-lg-12 order-lg-1 order-xl-1">
 <!--begin::Portlet-->
 <div class="kt-portlet kt-portlet--height-fluid">
 <div class="kt-portlet__head kt-portlet__head">
    <div class="kt-portlet__head-label">
     <h3 class="kt-portlet__head-title">Author Sales</h3></div>                                    
 </div>
 <div class="kt-portlet__body kt-portlet__body--fluid">
<div class="list-group">
<a href="#" class="list-group-item list-group-item-action active"> Cras justo odio <span class="badge badge-primary badge-pill">14</span></a> 
<a href="#" class="list-group-item list-group-item-action">Dapibus ac facilisis in<span class="badge badge-primary badge-pill">14</span></a> 
<a href="#" class="list-group-item list-group-item-action">Morbi leo risus <span class="badge badge-primary badge-pill">14</span></a> 
<a href="#" class="list-group-item list-group-item-action">Porta ac consectetur ac <span class="badge badge-primary badge-pill">14</span></a> 
<a href="#" class="list-group-item list-group-item-action disabled">Vestibulum at eros <span class="badge badge-primary badge-pill">14</span></a> </div>                                
 </div>
 </div>
 <!--end::Portlet-->
 </div>
	 <div class="col-lg-12 order-lg-1 order-xl-1">
 <!--begin::Portlet-->
  <div class="kt-portlet kt-portlet--height-fluid">
   <div class="kt-portlet__head kt-portlet__head">
       <div class="kt-portlet__head-label">
         <h3 class="kt-portlet__head-title">Technologies</h3></div>
       </div>
   <div class="kt-portlet__body kt-portlet__body--fluid">
<div class="list-group"> <a href="#" class="list-group-item list-group-item-action active"> Cras justo odio </a> <a href="#" class="list-group-item list-group-item-action">Dapibus ac facilisis in</a> <a href="#" class="list-group-item list-group-item-action">Morbi leo risus</a> <a href="#" class="list-group-item list-group-item-action">Porta ac consectetur ac</a> <a href="#" class="list-group-item list-group-item-action disabled">Vestibulum at eros</a> </div>                              
   </div>
   </div>
 <!--end::Portlet-->
 </div>
	</div>
</div>
<!---/new--->
        <!-- begin:: Scrolltop -->
        <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
        <!-- end:: Scrolltop -->


                
                
                
                
                
        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script>
            var KTAppOptions = {

    "colors": {

        "state": {

            "brand": "#5d78ff",

            "metal": "#c4c5d6",

            "light": "#ffffff",

            "accent": "#00c5dc",

            "primary": "#5867dd",

            "success": "#34bfa3",

            "info": "#36a3f7",

            "warning": "#ffb822",

            "danger": "#fd3995",

            "focus": "#9816f4"
        },

        "base": {

            "label": [

                "#c5cbe3",

                "#a1a8c3",

                "#3d4465",

                "#3e4466"
            ],

            "shape": [

                "#f0f3ff",

                "#d9dffa",

                "#afb4d4",

                "#646c9a"
            ]
        }
    }
};
        </script>
        <!-- end::Global Config -->
              @include('Agents.Layout.scripts')

    </body>
    <!-- end::Body -->
</html>