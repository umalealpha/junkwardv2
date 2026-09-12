<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-aside--minimize kt-page--loading">

<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
    <div class="kt-header-mobile__logo">
        <a><img alt="Logo" src="{{ asset('images/logo.png') }}"/></a>
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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')
    </div>

    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Report Email Recipients</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link">Dashboard</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ route('admin.cron.index') }}" class="kt-subheader__breadcrumbs-link">Cron Mails</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Report Recipients</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('admin.cron.index') }}" class="btn btn-sm btn-elevate btn-default">
                        <i class="flaticon2-left-arrow kt-padding-r-5"></i> Back to Cron Mails
                    </a>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

            <!-- Global alert -->
            <div id="page-alert" style="display:none; position:sticky; top:70px; z-index:999;" class="alert alert-dismissible fade show" role="alert">
                <span id="page-alert-msg"></span>
                <button type="button" class="close" onclick="jQuery('#page-alert').hide()"><span>&times;</span></button>
            </div>

            <p class="text-muted" style="margin-bottom:1.5rem;">
                Manage the email addresses that receive each automated finance report.
                <strong>Master Recipients</strong> (starred section) receive <em>every</em> report automatically.
                Recipients marked <strong>inactive</strong> are kept for reference but will not receive emails.
            </p>

            @foreach($grouped as $key => $group)
            @php $isMaster = ($key === '__all__'); @endphp
            <div class="kt-portlet kt-portlet--mobile {{ $isMaster ? 'kt-portlet--border-bottom-brand' : '' }}"
                 id="section-{{ $key }}"
                 style="{{ $isMaster ? 'border: 2px solid #5867dd; box-shadow: 0 2px 12px rgba(88,103,221,0.12);' : '' }}">
                <div class="kt-portlet__head" style="{{ $isMaster ? 'background: linear-gradient(90deg,#5867dd11,#fff);' : '' }}">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            {{ $group['label'] }}
                            &nbsp;·&nbsp;
                            <span class="badge badge-success" id="count-active-{{ $key }}">
                                {{ $group['stakeholders']->where('active', 1)->count() }} active
                            </span>
                            <span class="badge badge-secondary" id="count-total-{{ $key }}"
                                  style="display:{{ $group['stakeholders']->count() > 0 ? 'inline-block' : 'none' }}">
                                {{ $group['stakeholders']->count() }} total
                            </span>
                        </h3>
                    </div>
                    @if($isMaster)
                    <div class="kt-portlet__head-toolbar">
                        <span class="badge badge-brand" style="font-size:0.85em; padding:5px 10px;">
                            <i class="flaticon2-notification" style="font-size:0.85em;"></i> Added to every report
                        </span>
                    </div>
                    @endif
                </div>
                <div class="kt-portlet__body">

                    <!-- Recipients table -->
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="table-{{ $key }}">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:28%">Name</th>
                                    <th style="width:37%">Email</th>
                                    <th style="width:15%">Status</th>
                                    <th style="width:20%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-{{ $key }}">
                                @forelse($group['stakeholders'] as $s)
                                <tr id="row-{{ $s->id }}">
                                    <td class="align-middle">{{ $s->name ?: '—' }}</td>
                                    <td class="align-middle"><code>{{ $s->email }}</code></td>
                                    <td class="align-middle">
                                        <span class="badge badge-{{ $s->active ? 'success' : 'secondary' }} status-badge" id="badge-{{ $s->id }}">
                                            {{ $s->active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <button type="button"
                                            data-id="{{ $s->id }}"
                                            data-report="{{ $key }}"
                                            data-active="{{ $s->active ? '1' : '0' }}"
                                            class="btn btn-sm btn-{{ $s->active ? 'warning' : 'success' }} js-toggle-btn"
                                            title="{{ $s->active ? 'Deactivate' : 'Activate' }}">
                                            {{ $s->active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button type="button"
                                            data-id="{{ $s->id }}"
                                            data-report="{{ $key }}"
                                            class="btn btn-sm btn-danger js-delete-btn"
                                            title="Remove">
                                            <i class="flaticon2-delete"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr class="empty-row" id="empty-{{ $key }}">
                                    <td colspan="4" class="text-center text-muted py-3">No recipients yet. Add one below.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Add recipient form -->
                    <div class="kt-separator kt-separator--dashed kt-separator--sm"></div>
                    <div class="row align-items-center">
                        <div class="col-md-3 mb-2">
                            <input type="text" id="name_{{ $key }}" placeholder="Name (optional)"
                                class="form-control form-control-sm" style="max-width:100%;" />
                        </div>
                        <div class="col-md-4 mb-2">
                            <input type="email" id="email_{{ $key }}" placeholder="email@alphadirect.co.bw"
                                class="form-control form-control-sm" style="max-width:100%;" />
                        </div>
                        <div class="col-md-3 mb-2">
                            <button type="button"
                                data-report="{{ $key }}"
                                class="btn btn-sm btn-brand js-add-btn">
                                <i class="flaticon2-add-1 kt-padding-r-5"></i> Add Recipient
                            </button>
                        </div>
                        <div class="col-md-2 mb-2 text-right text-muted" style="font-size:0.78em;">
                            <code>{{ $key }}</code>
                        </div>
                    </div>

                </div>
            </div>
            @endforeach

        </div>
        <!-- end:: Content -->
    </div>
    @endif

    @include('includes.footer')
</div>
<!-- end:: Root -->

@include('admin.layouts.scripts')

<script>
(function($) {
    'use strict';

    var CSRF = '{{ csrf_token() }}';
    var BASE = '{{ url("/admin/cron/stakeholders") }}';

    /* ── Alert helper ─────────────────────────────────── */
    function showAlert(msg, type) {
        $('#page-alert')
            .removeClass('alert-success alert-danger alert-warning alert-info')
            .addClass('alert-' + type)
            .show();
        $('#page-alert-msg').text(msg);
        clearTimeout(window._alertTimer);
        window._alertTimer = setTimeout(function() { $('#page-alert').fadeOut(400); }, 5000);
    }

    /* ── Active-count badge update ────────────────────── */
    function updateCounts(reportType) {
        var tbody = $('#tbody-' + reportType);
        var rows  = tbody.find('tr[id^="row-"]');
        var active = tbody.find('.badge-success.status-badge').length;
        $('#count-active-' + reportType).text(active + ' active');
        var totalBadge = $('#count-total-' + reportType);
        if (rows.length > 0) {
            totalBadge.text(rows.length + ' total').show();
        } else {
            totalBadge.hide();
        }
    }

    /* ── Build a new table row from server data ──────── */
    function buildRow(s, reportType) {
        var activeCls = s.active ? 'success' : 'secondary';
        var btnCls    = s.active ? 'warning' : 'success';
        var btnTxt    = s.active ? 'Deactivate' : 'Activate';
        var badgeTxt  = s.active ? 'Active' : 'Inactive';
        return $('<tr>').attr('id', 'row-' + s.id).append(
            $('<td class="align-middle">').text(s.name || '—'),
            $('<td class="align-middle">').append($('<code>').text(s.email)),
            $('<td class="align-middle">').append(
                $('<span>').addClass('badge badge-' + activeCls + ' status-badge')
                           .attr('id', 'badge-' + s.id)
                           .text(badgeTxt)
            ),
            $('<td class="align-middle">').append(
                $('<button type="button">').addClass('btn btn-sm btn-' + btnCls + ' js-toggle-btn')
                    .attr({'data-id': s.id, 'data-report': reportType,
                           'data-active': s.active ? '1' : '0', 'title': btnTxt})
                    .text(btnTxt),
                ' ',
                $('<button type="button">').addClass('btn btn-sm btn-danger js-delete-btn')
                    .attr({'data-id': s.id, 'data-report': reportType, 'title': 'Remove'})
                    .append($('<i class="flaticon2-delete">'))
            )
        );
    }

    /* ── ADD recipient ────────────────────────────────── */
    $(document).on('click', '.js-add-btn', function() {
        var reportType = $(this).data('report');
        var name  = $('#name_'  + reportType).val().toString().trim();
        var email = $('#email_' + reportType).val().toString().trim();
        if (!email) { showAlert('Please enter an email address.', 'warning'); return; }

        var $btn = $(this).prop('disabled', true).text('Adding…');

        $.ajax({
            url:      BASE,
            type:     'POST',
            dataType: 'json',
            headers:  { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            data:     { _token: CSRF, report_type: reportType, name: name, email: email },
            success: function(resp) {
                if (!resp.success) { showAlert(resp.error || 'Failed to add.', 'danger'); return; }
                var s = resp.stakeholder;
                $('#empty-' + reportType).remove();
                buildRow(s, reportType).appendTo('#tbody-' + reportType);
                $('#name_'  + reportType).val('');
                $('#email_' + reportType).val('');
                updateCounts(reportType);
                showAlert('Recipient added: ' + s.email, 'success');
            },
            error: function(xhr) {
                var d = xhr.responseJSON;
                var msg = (d && (d.error || d.message)) ? (d.error || d.message)
                        : (xhr.status === 422 ? 'Validation failed — check the email.' : 'Server error (' + xhr.status + ')');
                showAlert(msg, 'danger');
            },
            complete: function() { $btn.prop('disabled', false).html('<i class="flaticon2-add-1 kt-padding-r-5"></i> Add Recipient'); }
        });
    });

    /* ── TOGGLE active/inactive ───────────────────────── */
    $(document).on('click', '.js-toggle-btn', function() {
        var $btn       = $(this);
        var id         = $btn.data('id');
        var reportType = $btn.data('report');
        var wasActive  = $btn.data('active') === '1' || $btn.data('active') === 1;

        $btn.prop('disabled', true).text('…');

        $.ajax({
            url:      BASE + '/' + id + '/toggle',
            type:     'POST',
            dataType: 'json',
            headers:  { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            data:     { _token: CSRF },
            success: function(resp) {
                var active = resp.active === 1 || resp.active === true;
                var badge  = $('#badge-' + id);
                if (active) {
                    badge.removeClass('badge-secondary').addClass('badge-success').text('Active');
                    $btn.removeClass('btn-success').addClass('btn-warning')
                        .text('Deactivate').data('active', '1');
                } else {
                    badge.removeClass('badge-success').addClass('badge-secondary').text('Inactive');
                    $btn.removeClass('btn-warning').addClass('btn-success')
                        .text('Activate').data('active', '0');
                }
                updateCounts(reportType);
                showAlert('Recipient ' + (active ? 'activated' : 'deactivated') + '.', 'success');
            },
            error: function(xhr) {
                var d = xhr.responseJSON;
                showAlert((d && d.error) ? d.error : 'Failed to update status (' + xhr.status + ').', 'danger');
                $btn.text(wasActive ? 'Deactivate' : 'Activate');
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });

    /* ── DELETE recipient ─────────────────────────────── */
    $(document).on('click', '.js-delete-btn', function() {
        if (!confirm('Remove this recipient? This cannot be undone.')) return;
        var $btn       = $(this);
        var id         = $btn.data('id');
        var reportType = $btn.data('report');

        $btn.prop('disabled', true);

        $.ajax({
            url:      BASE + '/' + id,
            type:     'POST',
            dataType: 'json',
            headers:  { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            data:     { _token: CSRF, _method: 'DELETE' },
            success: function() {
                var tbody = $('#tbody-' + reportType);
                $('#row-' + id).remove();
                if (tbody.find('tr[id^="row-"]').length === 0) {
                    tbody.append(
                        '<tr class="empty-row" id="empty-' + reportType + '">' +
                        '<td colspan="4" class="text-center text-muted py-3">No recipients yet. Add one below.</td>' +
                        '</tr>'
                    );
                }
                updateCounts(reportType);
                showAlert('Recipient removed.', 'warning');
            },
            error: function(xhr) {
                var d = xhr.responseJSON;
                showAlert((d && d.error) ? d.error : 'Failed to remove (' + xhr.status + ').', 'danger');
                $btn.prop('disabled', false);
            }
        });
    });

})(jQuery);
</script>

</body>
</html>
