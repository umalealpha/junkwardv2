<!DOCTYPE html>

<html lang="en" >
    <!-- begin::Head -->
    <head>
             @include('Admin.Layout.header')
             <link rel="stylesheet" type="text/css" href="{{ asset('css\GraphiteLoader.css') }}">


    </head>
    <!-- end::Head -->
    <!-- begin::Body -->

        <!-- end:: Header Mobile -->
        <!-- begin:: Root -->
        <div class="kt-grid kt-grid--hor kt-grid--root">
            <!-- begin:: Page -->
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

                @include('Admin.Layout.sidebar')

                @include('Admin.Layout.topNav')


                    <!-- begin:: Content -->
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                            <div class="kt-portlet">
                                <div class="kt-portlet__body kt-portlet__body--fit">
                                    <div class="kt-wizard-v3" id="kt_wizard_v3" data-ktwizard-state="step-first">
                                    <div class="loading">Loading&#8230;</div>

                                       
                                        <!--begin: Form Wizard Form-->

                                        <form id="kt_form" name="EditRPclientForm" class="kt-form" action="{{ \Config::get('values.graphite_url') }}'/realpay/addClient" method="POST" >
                                        {{csrf_field()}}
                                            <input name="red" type="hidden" value="/admin/AllClients"/>
                                            <!--begin: Form Wizard Step 1-->
                                            <div class="kt-wizard-v3__content" data-ktwizard-type="step-content" data-ktwizard-state="current" style="width:80%">
                                               <h3 class="kt-section__title" >Edit Client Billing information</h3>
                                                <div class="kt-heading kt-heading--md">Customer Details</div>
                                                <div class="kt-separator kt-separator--height-xs"></div>
                                                <div class="kt-form__section kt-form__section--first">
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                    <div class="form-group">
                                                             <label>Policy Number</label>
                                                             
                                                             <input type="text" class="form-control" name="clientNumber" placeholder="Policy Number" title="Clients Number" readonly> 

                                                        </div>

                                                    </div>
                                                    </div>
                                                  <div class="row">
                                                    <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label>First Name</label>
                                                        <input type="text" class="form-control" name="fname" placeholder="First Name" title="Customers First Name" required> 
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Omang ID</label>
                                                        <input type="text" class="form-control" name="idNumber" placeholder="Omang ID" title="Customers Omang ID Number" required> 
                                                    </div>

                                                    <div class="form-group">
                                                         <label>Bank Branch Number</label>

                                                       
							                                    <select class="form-control kt_selectpicker" name="bankBranchNum" data-live-search="true" id="bankBranchDropDown">
                                                                   
                                                                </select>       
                                                        
                                                    </div>

                                                  
                                                        <input type="hidden" class="form-control" name="empCode" value="OT">
                                                        

                                                        <div class="form-group">
                                                        <!-- 12: FNB Botswana 17:Capital Bank Botswana 18:Bank Gaborone 14:Barclays Botswana13:Stanbic Botswana 15:Bank ABC 19:Standard Chartered Botswana -->
                                                            <label for="exampleSelect1">Bank Account Type:</label>
							                                    <select class="form-control" name="bankAccountType"  required>
                                                                    <option value="">Please Select the Bank Account Type</option>
                                                                    <option value=1>Cheque</option>
                                                                    <option value=2 >Savings</option>
                                                                    
                                                                </select>


															</div>
                                                    </div>
														<div class="col-lg-6">

                                                        <div class="form-group">
                                                             <label>Last Name</label>
                                                             <input type="text" class="form-control" name="lname" title="Customers Last Name" placeholder="Last Name"> 
                                                        </div>


                                                        <div class="form-group">
                                                        <!-- 12: FNB Botswana 17:Capital Bank Botswana 18:Bank Gaborone 14:Barclays Botswana13:Stanbic Botswana 15:Bank ABC 19:Standard Chartered Botswana -->
                                                            <label for="exampleSelect1">Bank Name:</label>

							                                    <select class="form-control" name="bankNum" data-live-search="true" id="bankNumDropDown">

                                                                </select>


															</div>

                                                            <div class="form-group">
                                                             <label>Clients Bank Account Number</label>
                                                             
                                                             <input type="text" class="form-control" name="bankAccountNum" placeholder="Bank Account Number" title="Clients Bank Account Number" required> 

                                                        </div>
                                                       

                                                          
                        
                                                        

														</div>
                                                    </div>
           
                                                  <br>
                                                   <div class="row">
                                                    <div class="col-lg-12">
                                                    <input type="hidden" name="claim_id">
													 <button id="esetForm" type="button" class="btn btn-success btn-lg btn-block">Edit Client</button>
                                                     </form>
                                                     
 													</div>
                                                    </div>
                                              
                                                  <div class="row">
                                                  	<div class="col-lg-8 newsearch1">
													<div class="form-group ">
												
                                                	</div>
                                                  </div>
                                                  </div>
                                                </div>
                                            </div>
                                            <!--end: Form Wizard Step 1-->

                                            <!--begin: Form Actions -->
                                            <div class="kt-form__actions">
                                                <div class="btn btn-outline-brand btn-md btn-tall btn-wide btn-bold btn-upper" data-ktwizard-type="action-prev"> Previous </div>
                                                <div class="btn btn-brand btn-md btn-tall btn-wide btn-bold btn-upper" data-ktwizard-type="action-submit"> Submit </div>
                                                
                                            </div>
                                            <!--end: Form Actions -->
                                        </form>
                                        <!--end: Form Wizard Form-->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end:: Content -->
                    </div>
                    <!-- begin:: Footer -->
                    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
                        <div class="kt-footer__copyright"> 2018&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct</a> </div>
                        <div class="kt-footer__menu"> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">About</a> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">Team</a> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">Contact</a> </div>
                    </div>
                    <!-- end:: Footer -->
                </div>
                <!-- end:: Wrapper -->
            </div>
            <!-- end:: Page -->
        </div>
        <!-- end:: Root -->
        <!-- begin:: Topbar Offcanvas Panels -->
        <!-- begin::Offcanvas Toolbar Quick Actions -->
        <div id="kt_offcanvas_toolbar_quick_actions" class="kt-offcanvas-panel">
            <div class="kt-offcanvas-panel__head">
                <h3 class="kt-offcanvas-panel__title">
                    Quick Actions 
                </h3>
                <a href="#" class="kt-offcanvas-panel__close" id="kt_offcanvas_toolbar_quick_actions_close"><i class="flaticon2-delete"></i></a>
            </div>
            <div class="kt-offcanvas-panel__body">
                <div class="kt-grid-nav-v2">
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-box"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Orders</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon-download-1"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Uploades</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-supermarket"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Products</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-avatar"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Customers</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-list"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Blog Posts</div>
                    </a>
                    <a href="#" class="kt-grid-nav-v2__item">
                        <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-settings"></i></div>
                        <div class="kt-grid-nav-v2__item-title">Settings</div>
                    </a>
                </div>
            </div>
        </div>
        <!-- end::Offcanvas Toolbar Quick Actions -->
        <!-- end:: Topbar Offcanvas Panels -->
        <!-- begin:: Quick Panel -->
        <div id="kt_quick_panel" class="kt-offcanvas-panel">
            <div class="kt-offcanvas-panel__nav">
                <ul class="nav nav-pills" role="tablist">
                    <li class="nav-item active"> <a class="nav-link active" data-toggle="tab" href="#kt_quick_panel_tab_notifications" role="tab">Notifications</a> </li>
                    <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#kt_quick_panel_tab_actions" role="tab">Actions</a> </li>
                    <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#kt_quick_panel_tab_settings" role="tab">Settings</a> </li>
                </ul>
                <button class="kt-offcanvas-panel__close" id="kt_quick_panel_close_btn"><i class="flaticon2-delete"></i></button>
            </div>
            <div class="kt-offcanvas-panel__body">
                <div class="tab-content">
                    <div class="tab-pane fade show kt-offcanvas-panel__content kt-scroll active" id="kt_quick_panel_tab_notifications" role="tabpanel">
                        <!--Begin::Timeline -->
                        <div class="kt-timeline">
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--success">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-feed kt-font-success"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">02:30 PM</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> KeenThemes created new layout whith tens of new options for Keen Admin panel </a> 
                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--danger">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-safe-shield-protection kt-font-danger"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">01:20 AM</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> New secyrity alert by Firewall & order to take aktion on User Preferences </a> 
                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--brand">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon2-box kt-font-brand"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">Yestardey</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> FlyMore design mock-ups been uploadet by designers Bob, Naomi, Richard </a> 
                                <div class="kt-timeline__item-info"> PSD, Sketch, AJ </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--warning">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-pie-chart-1 kt-font-warning"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">Aug 13,2018</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text">
                                    Meeting with Ken Digital Corp ot Unit14, 3 Edigor Buildings, George Street, Loondon
                                    <br>
                                    England, BA12FJ 
                                </a>
                                <div class="kt-timeline__item-info"> Meeting, Customer </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--info">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon"> <i class="flaticon-notepad kt-font-info"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">May 09, 2018</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> KeenThemes created new layout whith tens of new options for Keen Admin panel </a> 
                                <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                            </div>
                            <!--End::Item -->
                            <!--Begin::Item -->
                            <div class="kt-timeline__item kt-timeline__item--accent">
                                <div class="kt-timeline__item-section">
                                    <div class="kt-timeline__item-section-border">
                                        <div class="kt-timeline__item-section-icon" > <i class="flaticon-bell kt-font-success"></i> </div>
                                    </div>
                                    <span class="kt-timeline__item-datetime">01:20 AM</span> 
                                </div>
                                <a href="" class="kt-timeline__item-text"> New secyrity alert by Firewall & order to take aktion on User Preferences </a> 
                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                            </div>
                            <!--End::Item -->
                        </div>
                        <!--End::Timeline -->
                    </div>
                    <div class="tab-pane fade kt-offcanvas-panel__content kt-scroll" id="kt_quick_panel_tab_actions" role="tabpanel">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-success">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <span class="kt-portlet__head-icon kt-hide"><i class="flaticon-stopwatch"></i></span>
                                    <h3 class="kt-portlet__head-title">
                                        Recent Bills
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-focus">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <span class="kt-portlet__head-icon kt-hide"><i class="flaticon-stopwatch"></i></span>
                                    <h3 class="kt-portlet__head-title">
                                        Latest Orders
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-info">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Latest Invoices
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-warning">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        New Comments
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--solid-brand">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Recent Posts
                                    </h3>
                                </div>
                                <div class="kt-portlet__head-toolbar">
                                    <div class="kt-portlet__head-group">
                                        <div class="dropdown dropdown-inline">
                                            <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a> 
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#">Separated link</a> 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <div class="tab-pane fade kt-offcanvas-panel__content kt-scroll" id="kt_quick_panel_tab_settings" role="tabpanel">
                        <form class="kt-form">
                            <div class="kt-heading kt-heading--space-sm">Notifications</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable notifications:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_1">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable audit log:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_2">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Notify on new orders:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_2">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>
                            <div class="kt-heading kt-heading--space-sm">Orders</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable order tracking:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_3">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable orders reports:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_3">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Allow order status auto update:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--danger">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_4">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>
                            <div class="kt-heading kt-heading--space-sm">Customers</div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable customer singup:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_5">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-xs row">
                                <label class="col-8 col-form-label">Enable customers reporting:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" name="quick_panel_notifications_5">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group form-group-last form-group-xs row">
                                <label class="col-8 col-form-label">Notifiy on new customer registration:</label>
                                <div class="col-4 kt-align-right">
                                    <span class="kt-switch kt-switch--sm kt-switch--success">
                                        <label>
                                            <input type="checkbox" checked="checked" name="quick_panel_notifications_6">
                                            <span></span> 
                                        </label>
                                    </span>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- end:: Quick Panel -->
        <!-- begin:: Scrolltop -->
        <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
        <!-- end:: Scrolltop -->
<!-- Large Modal1 -->
 <div class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
   <div class="modal-dialog modal-xl">
     <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title">Add Policy</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
        </div>
        <div class="modal-body">
                                             <div class="row">
												  <div class="col-md-3 form-group">
														<label>First Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="First Name"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Last Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Last Name"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Policy No :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Policy No"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Agency Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Agency Name"> 
													</div>
                                              </div>
                                             <div class="row">
												  <div class="col-md-3 form-group">
														<label for="exampleSelect1">App. Status</label>
															<select class="form-control" id="exampleSelect1">
																<option >Select</option>
																<option value="1">Open</option>
																<option value="1">Pending</option>
																<option value="1">Closed</option>
															</select>
													</div>
												  <div class="col-md-3 form-group">
														<label>Motor Reg. No :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Motor Reg. No"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Agency Code :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Agency Code"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Submitted By :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Submitted By"> 
													</div>
                                              </div>
        </div>
       <div class="modal-footer">
       <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
       <button type="button" class="btn btn-outline-brand">Save changes</button>
    </div>
  </div>
</div>
</div>
<!-- /Large Modal1 -->
<!-- Large Modal 2-->
 <div class="modal fade bd-example-modal-xl1" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
   <div class="modal-dialog modal-xl">
     <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title">Add Policy</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
        </div>
        <div class="modal-body">
                                             <div class="row">
												  <div class="col-md-3 form-group">
														<label>First Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="First Name"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Last Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Last Name"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Policy No :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Policy No"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Agency Name :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Agency Name"> 
													</div>
                                              </div>
                                             <div class="row">
												  <div class="col-md-3 form-group">
														<label for="exampleSelect1">App. Status</label>
															<select class="form-control" id="exampleSelect1">
																<option >Select</option>
																<option value="1">Open</option>
																<option value="1">Pending</option>
																<option value="1">Closed</option>
															</select>
													</div>
												  <div class="col-md-3 form-group">
														<label>Motor Reg. No :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Motor Reg. No"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Agency Code :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Agency Code"> 
													</div>
												  <div class="col-md-3 form-group">
														<label>Submitted By :</label>
														<input type="text" class="form-control" aria-describedby="emailHelp" placeholder="Submitted By"> 
													</div>
                                              </div>
        </div>
       <div class="modal-footer">
       <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
       <button type="button" class="btn btn-outline-brand">Save changes</button>
    </div>
  </div>
</div>
</div>
<!-- /Large Modal 2-->           
                
                
                
        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script>
        function datePicker(evt) {
			$('input[data-relmax]').each(function () {
				let oldVal = $(this).prop('value');
				let relmax = $(this).data('relmax');
				let max = new Date();
				max.setFullYear(max.getFullYear() + relmax);
				$.prop(this, 'max', $(this).prop('valueAsDate', max).val());
				$.prop(this, 'value', oldVal);
			});
		}
            let KTAppOptions = {

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

     @include('Admin.Layout.scripts')

     <script type="text/javascript">
        function enableDisableSend(oFld) {
            console.log('printin');
            causeOfDamage = oFld.form.causeOfDamage.value;

            if (causeOfDamage == '') {
                oFld.form.write.disabled = TRUE;
                document.getElementById("submitForm").className = 'comment_popup_button_disabled';
            } else {
                oFld.form.write.disabled = FALSE;
                document.getElementById("submitForm").className = 'comment_popup_button_active';
            }
        }






        
         function authorizeClaim() {
            $.ajax({
               type:'POST',
               url:'{{Route('authorizeClaim')}}',
               data:$('#step_2').serialize(),
               success:function(data) {
                   console.log('Completed');
                   }
            });
         }
      

        //console.log($(".kt-wizard-v3__nav-items").find(".kt-wizard-v3__nav-item"));
  
        @if(Session::has('policySaved'))
        Toastify({
            text: "{{ Session::get('submittedClaim') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
            }).showToast();   
        @endif

        $(document).ready(function() {
            let branches = []; 
            var urlValue = '{{ \Config::get('values.graphite_url') }}'
            function loadBankInformation(client) {
                $.ajax({
                    /* the route pointing to the post function */
                    url: urlValue+'realpay/getBanks',
                    type: 'GET',
                    /* send the csrf-token and the input to the controller */
                    data: {},
                    dataType: 'JSON',
                    /* remind that 'data' is the response of the AjaxController */
                    success: function (data) {
                        if(data) {
                            console.log(data);
                            let currentBank;
                    
                            $.each(data, function(key, value) {
                                if(client['ns0:bankNum']==value["ns0:bankNum"]) {
                                    currentBank = value['ns0:bankNum'];
                                }
                                $('#bankNumDropDown').append('<option value="' + value["ns0:bankNum"] + '"' + (client['ns0:bankNum']==value["ns0:bankNum"]?"selected='selected'":'') + '>' + value["ns0:bankDesc"] + '</option>');
                                $("#bankNumDropDown").selectpicker('refresh');
                            });

                            $.ajax({
                                /* the route pointing to the post function */
                                url: urlValue+'realpay/getBankBranches',
                                type: 'GET',
                                /* send the csrf-token and the input to the controller */
                                data: {},
                                dataType: 'JSON',
                                /* remind that 'data' is the response of the AjaxController */
                                success: function (data) { 
                                    if(data) {
                                        console.log(data);
                                        branches = data;
                                        // const bankNum = $(this).val();
                                        let filterArray = [];
                                        branches.forEach((bank) => {
                                            if(bank['ns0:bankNum']==currentBank) {
                                                filterArray.push(bank);
                                            }
                                        });
                                        
                                        $('#bankBranchDropDown').empty();
                                        filterArray.forEach((value) => {
                                            $('#bankBranchDropDown').append('<option value="'+value["ns0:bankBranchNum"] + (client['ns0:bankBranchNum']==value["ns0:bankBranchNum"]?"selected='selected'":'') +'">' + value["ns0:bankBranchDesc"] +'</option>');
                                            $("#bankBranchDropDown").selectpicker('refresh');
                                        });
                                        $('.loading').css("display","none"); 
                                    }
                                },
                                fail: function() {
                                    $('.loading').css("display","none"); 
                                }
                            });
                        }
                    },
                    fail: function() {
                        $('.loading').css("display","none"); 
                    }
                });
            }
            $.urlParam = function(name) {
                var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
                if (results==null) {
                    return null;
                }
                return decodeURI(results[1]) || 0;
            }
            $.ajax({
                /* the route pointing to the post function */
                url: urlValue+'realpay/getClients',
                type: 'GET',
                /* send the csrf-token and the input to the controller */
                data: {clientNumber: decodeURIComponent($.urlParam("id"))},
                dataType: 'JSON',
                /* remind that 'data' is the response of the AjaxController */
                success: function (data) { 
                    if(data) {
                        loadBankInformation(data);
                        $('[name="fname"]').val(data['ns0:clientName'].split(' ')[0]);
                        $('[name="lname"]').val(data['ns0:clientName'].split(' ')[1]);

                        $('[name="idNumber"]').val(data['ns0:idNumber']);
                        $('[name="bankNum"]').val(data['ns0:bankNum']);
                        $('[name="bankBranchNum"]').val(data['ns0:bankBranchNum']);
                        $('[name="bankAccountNum"]').val(data['ns0:bankAccountNum']);
                        $('[name="bankAccountType"]').val(data['ns0:bankAccountType']);
                        $('[name="clientNumber"]').val(data['ns0:clientNumber']);
                        
                    }
                },
                fail: function() {
                    $('.loading').css("display","none"); 
                }
            });
                //for when the user selects the bank it brings up only and all the branches of tha bank
            $("#bankNumDropDown").change(function(){
                const bankNum = $(this).val();
                let filterArray = [];
                branches.forEach((bank)=>{
                    if(bank['ns0:bankNum']==bankNum) {
                        filterArray.push(bank);
                    }
                });
                
                $('#bankBranchDropDown').empty();
                filterArray.forEach((value) => {
                    $('#bankBranchDropDown').append('<option value="'+value["ns0:bankBranchNum"]+'">' + value["ns0:bankBranchDesc"] +'</option>');
                    $("#bankBranchDropDown").selectpicker('refresh');
                });
            });
       });  


        // var dropup = (datatable.getPageSize() - index) <= 4 ? 'dropup' : '';

        // let currentEditData;
        // $(".showEditModal").on("click", function() {
        //     console.log("Click");
        //     currentEditData = JSON.parse($(this).data("data")+"\"}");
        //     //
        //     $("#id").val(currentEditData.id);
        //     $("#firstName").val(currentEditData.firstName);
        //     $("#lastName").val(currentEditData.lastName);
        //     $("#cellphone").val(currentEditData.cellphone);
        //     $("#dob").val(currentEditData.dob);
        //     $("#licensePlate").val(currentEditData.licensePlate);
        //     $("#omang").val(currentEditData.omang);
        //     $("#billing").val(currentEditData.billing);
        //     $("#billingCell").val(currentEditData.billingCell);
        //     $("#bankName").val(currentEditData.bankName);
        //     $("#branchCode").val(currentEditData.branchCode);
        //     $("#accountNumber").val(currentEditData.accountNumber);
        // });

    //     $('#s').click(function(e){
    
    // Swal.fire({
    //             title: 'Are you sure?',
    //             text: "You are about to pay for a Clients Installment!",
    //             type: 'question',
                
    //             showCancelButton: true,
    //             confirmButtonColor: '#3085d6',
    //             cancelButtonColor: '#d33',
    //             confirmButtonText: 'Yes, Pay For Installment!'
    //             }).then((result) => {
    //             if (result.value) {
    //                 document.getElementById('kt_form').submit();
                    
    //                 Swal.fire({
    //                 title:'PAID',
    //                 text:'Installment Paid',
    //                 type:'success'
    //                 }) .then((result) => {

                        
    //                     window.location.reload();
    //                 })
                    
    //             }
    //                 })


    //    });


        $('#esetForm').click(function(e){
    
    Swal.fire({
                title: 'Are you sure?',
                text: "You are about to edit a Client on RealPay!",
                type: 'question',
                
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Edit Client!'
                }).then((result) => {
                if (result.value) {
                    document.getElementById('kt_form').submit();

                    Swal.fire({
                    title:'EDITED',
                    text:'Client Edited',
                    type:'success'
                    }).then((result) => {
                        // location.href = "www.yoursite.com";
                        // window.location.reload();
                        history.go(-1);
                        window.location.reload(); //reloads the page

                    })
                }
                    })


       });
    </script>

    </body>
    <!-- end::Body -->
</html>