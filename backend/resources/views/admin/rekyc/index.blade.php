<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{ asset('css/rekyc-fixes.css') }}" rel="stylesheet" type="text/css" />

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* Re-KYC Admin Custom Styles */
.kt-widget1 {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e4e6ef;
    transition: all 0.3s ease;
}

.kt-widget1:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.kt-widget1__title {
    font-size: 2rem;
    margin-bottom: 5px;
    font-weight: 700;
}

.kt-widget1__desc {
    font-size: 14px;
    opacity: 0.8;
}

.kt-widget1__number {
    font-size: 2.5rem;
    opacity: 0.3;
}

.kt-widget4__progress {
    margin-top: 5px;
}

.kt-widget4__progress-wrapper {
    position: relative;
    height: 6px;
    background: #f4f5f8;
    border-radius: 3px;
    overflow: hidden;
}

.kt-widget4__progress-value {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.kt-widget4__progress-label {
    font-size: 12px;
    font-weight: 600;
    margin-top: 5px;
}

.kt-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.kt-badge--success {
    background-color: #1dc9b7;
    color: #fff;
}

.kt-badge--info {
    background-color: #5d78ff;
    color: #fff;
}

.kt-badge--secondary {
    background-color: #74788d;
    color: #fff;
}

.kt-badge--brand {
    background-color: #5867dd;
    color: #fff;
}

.kt-badge--inline {
    display: inline-block;
    margin-right: 5px;
}

.kt-datatable__cell {
    white-space: nowrap;
}

.kt-datatable__cell-wrapper {
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-clean {
    background: transparent;
    border: none;
    padding: 8px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.btn-clean:hover {
    background: #f4f5f8;
}

table th {
    background: #f8f9fa;
    border-bottom: 2px solid #e4e6ef;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 12px;
}

table td {
    vertical-align: middle;
    border-bottom: 1px solid #f4f5f8;
}

table tbody tr:hover {
    background: #f8f9fa;
}
</style>

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
     <!--If Password default, show edit details -->
     @if (Auth::user()->password == null)
        @include('includes.reset')
     @else
     @endif
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Re-KYC Management Dashboard
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> 
                    <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> 
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Re-KYC Management</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <button type="button" class="btn btn-sm btn-elevate btn-brand btn-elevate" data-toggle="modal" data-target="#createCampaignModal">
                        <span class="kt-opacity-11">Create Campaign</span>&nbsp; 
                        <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <!-- Statistics Cards -->
                    <div class="row kt-margin-b-20">
                        <div class="col-lg-3 col-6">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #5867dd; font-weight: 600;">{{ $stats['total_campaigns'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Total Campaigns</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-brand" style="font-size: 2.5rem;">
                                        <i class="flaticon2-bell-2"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['active_campaigns'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Active Campaigns</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-success" style="font-size: 2.5rem;">
                                        <i class="flaticon2-check-mark"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #ffb822; font-weight: 600;">{{ $stats['total_links'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Total Links</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-warning" style="font-size: 2.5rem;">
                                        <i class="flaticon2-link"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #fd27eb; font-weight: 600;">{{ $stats['completion_rate'] ?? 0 }}%</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 14px;">Completion Rate</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-danger" style="font-size: 2.5rem;">
                                        <i class="flaticon2-pie-chart"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Stats -->
                    <div class="row kt-margin-b-20">
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #5867dd; font-weight: 600;">{{ $stats['sent_links'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Sent</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-brand" style="font-size: 1.8rem;">
                                        <i class="flaticon2-send"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['opened_links'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Opened</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-success" style="font-size: 1.8rem;">
                                        <i class="flaticon2-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #1dc9b7; font-weight: 600;">{{ $stats['completed_links'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Completed</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-success" style="font-size: 1.8rem;">
                                        <i class="flaticon2-check-mark"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #ffb822; font-weight: 600;">{{ $stats['pending_links'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Pending</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-warning" style="font-size: 1.8rem;">
                                        <i class="flaticon2-time"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #fd27eb; font-weight: 600;">{{ $stats['expired_links'] ?? 0 }}</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Expired</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-danger" style="font-size: 1.8rem;">
                                        <i class="flaticon2-warning"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="kt-widget1">
                                <div class="kt-widget1__item">
                                    <div class="kt-widget1__info">
                                        <h3 class="kt-widget1__title" style="color: #74788d; font-weight: 600;">{{ $stats['response_time'] ?? 0 }}m</h3>
                                        <span class="kt-widget1__desc" style="color: #74788d; font-size: 12px;">Avg Response</span>
                                    </div>
                                    <span class="kt-widget1__number kt-font-dark" style="font-size: 1.8rem;">
                                        <i class="flaticon2-time-1"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campaigns Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover table-checkable" id="campaignsTable">
                            <thead>
                                <tr>
                                    <th style="color: #74788d; font-weight: 600;">ID</th>
                                    <th style="color: #74788d; font-weight: 600;">Name</th>
                                    <th style="color: #74788d; font-weight: 600;">Status</th>
                                    <th style="color: #74788d; font-weight: 600;">Links</th>
                                    <th style="color: #74788d; font-weight: 600;">Completion Rate</th>
                                    <th style="color: #74788d; font-weight: 600;">Created</th>
                                    <th style="color: #74788d; font-weight: 600;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campaigns as $campaign)
                                <tr>
                                    <td style="color: #5d78ff; font-weight: 600;">{{ $campaign->id }}</td>
                                    <td>
                                        <strong style="color: #2c3e50; font-weight: 600;">{{ $campaign->name }}</strong>
                                        @if($campaign->description)
                                            <br><small style="color: #74788d;">{{ Str::limit($campaign->description, 50) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-{{ $campaign->status === 'active' ? 'success' : ($campaign->status === 'completed' ? 'info' : 'secondary') }}" 
                                              style="font-size: 12px; padding: 6px 12px; border-radius: 15px;">
                                            <i class="fas fa-{{ $campaign->status === 'active' ? 'check-circle' : ($campaign->status === 'completed' ? 'info-circle' : 'pause-circle') }} mr-1"></i>
                                            {{ strtoupper($campaign->status) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="badge badge-primary mb-1" style="font-size: 12px; padding: 6px 12px; border-radius: 15px;">
                                                <i class="fas fa-link mr-1"></i>
                                                {{ $campaign->links_count }} Links
                                            </span>
                                            @if($campaign->activities_count > 0)
                                                <small class="text-muted" style="font-size: 10px;">
                                                    <i class="fas fa-history mr-1"></i>
                                                    {{ $campaign->activities_count }} activities
                                                </small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="kt-widget4__progress">
                                            <div class="kt-widget4__progress-wrapper">
                                                <div class="kt-widget4__progress-info">
                                                    <span class="kt-widget4__progress-label" style="color: #74788d;">{{ $campaign->completion_rate }}%</span>
                                                </div>
                                                <div class="kt-widget4__progress-value kt-bg-{{ $campaign->completion_rate >= 80 ? 'success' : ($campaign->completion_rate >= 50 ? 'warning' : 'danger') }}" 
                                                     style="width: {{ $campaign->completion_rate }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color: #74788d;">{{ $campaign->created_at->format('M d, Y H:i') }}</td>
                                    <td>
                                        <div class="kt-datatable__cell">
                                            <span class="kt-datatable__cell-wrapper">
                                                <a href="{{ route('admin.rekyc.campaign.show', $campaign->id) }}" 
                                                   class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View Details">
                                                    <i class="fas fa-eye" style="color: #5d78ff;"></i>
                                                </a>
                                                <a href="{{ route('admin.rekyc.campaign.edit', $campaign->id) }}" 
                                                   class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit Campaign">
                                                    <i class="fas fa-edit" style="color: #ffb822;"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-clean btn-icon btn-icon-md" 
                                                        onclick="sendEscalations({{ $campaign->id }})" title="Send Escalations">
                                                    <i class="fas fa-exclamation-triangle" style="color: #fd27eb;"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center" style="color: #74788d; padding: 40px;">No campaigns found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-wrapper">
                        {{ $campaigns->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    @if($recentActivities->count() > 0)
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-history mr-2 text-primary"></i>
                            Recent Activities
                        </h3>
                        <span class="badge badge-primary">{{ $recentActivities->count() }} activities</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($recentActivities as $activity)
                        <div class="list-group-item border-0 py-3">
                            <div class="d-flex align-items-start">
                                <div class="flex-shrink-0 me-3">
                                    <div class="bg-{{ $activity->action === 'link_sent' ? 'primary' : ($activity->action === 'link_opened' ? 'info' : ($activity->action === 'notification_resent' ? 'warning' : 'success')) }} rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <i class="fas fa-{{ $activity->action === 'link_sent' ? 'paper-plane' : ($activity->action === 'link_opened' ? 'eye' : ($activity->action === 'notification_resent' ? 'redo' : 'check-circle')) }} text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0 text-dark">
                                            {{ ucwords(str_replace('_', ' ', $activity->action)) }}
                                            @if($activity->link && $activity->link->customer)
                                                <span class="text-muted">- {{ $activity->link->customer->firstName }} {{ $activity->link->customer->lastName }}</span>
                                            @endif
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-clock mr-1"></i>
                                            {{ $activity->created_at->format('M d, H:i') }}
                                        </small>
                                    </div>
                                    <p class="mb-0 text-muted small">{{ $activity->description }}</p>
                                    @if($activity->link)
                                        <div class="mt-2">
                                            <span class="badge badge-light">
                                                <i class="fas fa-link mr-1"></i>
                                                Link #{{ $activity->link->id }}
                                            </span>
                                            @if($activity->link->campaign)
                                                <span class="badge badge-secondary ml-1">
                                                    <i class="fas fa-bullhorn mr-1"></i>
                                                    {{ $activity->link->campaign->name }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if(!$loop->last)
                            <hr class="my-0">
                        @endif
                        @endforeach
                    </div>
                </div>
                <div class="card-footer bg-light text-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Showing latest {{ $recentActivities->count() }} activities
                    </small>
                </div>
            </div>
        </div>
    </div>
    @endif
    @include('includes.footer')
    <!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->

<!-- Create Campaign Modal -->
<div class="modal fade" id="createCampaignModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Re-KYC Campaign</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="{{ route('admin.rekyc.campaign.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Campaign Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="escalation_days">Escalation Days</label>
                                <input type="number" class="form-control" id="escalation_days" name="escalation_days" value="7" min="1" max="30">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reminder_days">Reminder Days</label>
                                <input type="text" class="form-control" id="reminder_days" name="reminder_days" placeholder="3,7,14" 
                                       title="Comma-separated days for reminders">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Notifications Modal -->
<div class="modal fade" id="bulkNotificationsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Bulk Notifications</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="bulkNotificationsForm">
                <div class="modal-body">
                    <input type="hidden" id="bulk_campaign_id" name="campaign_id">
                    <div class="form-group">
                        <label>Select Links</label>
                        <div id="linksList" class="form-check">
                            <!-- Links will be loaded here -->
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Channels</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="channel_email">
                            <label class="form-check-label" for="channel_email">Email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="whatsapp" id="channel_whatsapp">
                            <label class="form-check-label" for="channel_whatsapp">WhatsApp</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="sms" id="channel_sms">
                            <label class="form-check-label" for="channel_sms">SMS</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="custom_message">Custom Message (Optional)</label>
                        <textarea class="form-control" id="custom_message" name="message" rows="3" 
                                  placeholder="Add a custom message to the notification..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Notifications</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')

<link rel="stylesheet" href="{{ asset('admin/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" href="{{ asset('admin/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">

<script src="{{ asset('admin/plugins/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#campaignsTable').DataTable({
        "responsive": true,
        "autoWidth": false,
        "pageLength": 10,
        "order": [[0, "desc"]],
        "language": {
            "lengthMenu": "Show _MENU_ campaigns per page",
            "zeroRecords": "No campaigns found",
            "info": "Showing _START_ to _END_ of _TOTAL_ campaigns",
            "infoEmpty": "No campaigns available",
            "infoFiltered": "(filtered from _MAX_ total campaigns)",
            "search": "Search campaigns:",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "columnDefs": [
            { "orderable": false, "targets": [6] }, // Actions column
            { "className": "text-center", "targets": [0, 2, 3, 4] }, // Center align ID, Status, Links, Completion Rate
            { "width": "60px", "targets": [0] }, // ID column width
            { "width": "80px", "targets": [2] }, // Status column width
            { "width": "100px", "targets": [3] }, // Links column width
            { "width": "120px", "targets": [4] }, // Completion Rate column width
            { "width": "140px", "targets": [5] }, // Created column width
            { "width": "120px", "targets": [6] }  // Actions column width
        ],
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "drawCallback": function(settings) {
            // Re-initialize any custom functionality after table redraw
            console.log('DataTable redrawn');
        }
    });

    // Bulk notifications form submission
    $('#bulkNotificationsForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const selectedLinks = $('input[name="link_ids[]"]:checked').map(function() {
            return this.value;
        }).get();
        
        if (selectedLinks.length === 0) {
            alert('Please select at least one link');
            return;
        }
        
        const channels = $('input[name="channels[]"]:checked').map(function() {
            return this.value;
        }).get();
        
        if (channels.length === 0) {
            alert('Please select at least one channel');
            return;
        }
        
        // Add selected links to form data
        selectedLinks.forEach(linkId => {
            formData.append('link_ids[]', linkId);
        });
        
        // Send AJAX request
        $.ajax({
            url: '{{ route("admin.rekyc.bulk-notifications") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Bulk notifications sent successfully!');
                    $('#bulkNotificationsModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Error: ' + (response.message || 'Failed to send notifications'));
            }
        });
    });
});

function sendBulkNotifications(campaignId) {
    $('#bulk_campaign_id').val(campaignId);
    
    // Load campaign links
    $.ajax({
        url: '{{ route("admin.rekyc.campaign.links") }}',
        type: 'GET',
        data: { campaign_id: campaignId },
        success: function(response) {
            const linksList = $('#linksList');
            linksList.empty();
            
            if (response.links && response.links.length > 0) {
                response.links.forEach(link => {
                    const linkHtml = `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="link_ids[]" value="${link.id}" id="link_${link.id}">
                            <label class="form-check-label" for="link_${link.id}">
                                ${link.customer ? link.customer.name : 'Unknown Customer'} 
                                <small class="text-muted">(${link.status})</small>
                            </label>
                        </div>
                    `;
                    linksList.append(linkHtml);
                });
            } else {
                linksList.html('<p class="text-muted">No links found for this campaign</p>');
            }
            
            $('#bulkNotificationsModal').modal('show');
        },
        error: function() {
            alert('Failed to load campaign links');
        }
    });
}


function sendEscalations(campaignId) {
    if (confirm('Send escalation notifications to all expired links in this campaign?')) {
        $.ajax({
            url: '{{ route("admin.rekyc.send-escalations") }}',
            type: 'POST',
            data: {
                campaign_id: campaignId,
                channels: ['email', 'whatsapp', 'sms'],
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert('Escalation notifications sent successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = JSON.parse(xhr.responseText);
                alert('Error: ' + (response.message || 'Failed to send escalations'));
            }
        });
    }
}
</script>
</body>
</html>
