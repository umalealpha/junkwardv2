<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">

<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed">
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{ asset('images/logo.png') }}"/>
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

    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">

        {{-- Subheader --}}
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Credentials Vault</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{ Route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link">Dashboard</a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Credentials Vault</span>
                </div>
            </div>
        </div>
        {{-- end Subheader --}}

        {{-- begin Content --}}
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

            @if (session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    {{ session('info') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif

            {{-- ================================================================
                 STATE 1: PIN ENTRY (Vault Locked)
                 ================================================================ --}}
            @if (!$isUnlocked)

            <div class="d-flex justify-content-center align-items-center" style="min-height:75vh;">
                <div class="card shadow-sm" style="width:100%;max-width:420px;">
                    <div class="card-body p-5">

                        {{-- Lock icon --}}
                        <div class="text-center mb-4">
                            <i class="fa fa-lock" style="font-size:56px;color:#b0b0b0;"></i>
                            <h4 class="mt-3 mb-1 font-weight-bold">Credentials Vault</h4>
                            <p class="text-muted mb-0" style="font-size:0.93rem;">
                                @if ($lockedUntil)
                                    Your vault is temporarily locked.
                                @elseif (!$hasPinSet)
                                    Create a PIN to secure your vault.
                                @else
                                    Enter your PIN to access
                                @endif
                            </p>
                        </div>

                        {{-- Locked-out banner --}}
                        @if ($lockedUntil)
                            <div class="alert alert-danger text-center" id="lockout-banner">
                                <i class="fa fa-ban mr-1"></i>
                                Too many failed attempts.<br>
                                Try again in <strong id="lockout-countdown">{{ $lockedMinutesLeft }} minute(s)</strong>.
                            </div>
                        @endif

                        {{-- Error area (AJAX errors) --}}
                        <div id="pin-error" class="alert alert-danger d-none"></div>

                        {{-- ── SET PIN FORM ── --}}
                        @if (!$hasPinSet && !$lockedUntil)
                        <form id="set-pin-form">
                            @csrf
                            <div class="form-group">
                                <label class="font-weight-semibold">New PIN <small class="text-muted">(6 digits)</small></label>
                                <input type="password" class="form-control form-control-lg text-center font-weight-bold letter-spacing-wide"
                                       id="new_pin" name="new_pin" maxlength="6" inputmode="numeric" autocomplete="new-password"
                                       placeholder="••••••" style="font-size:1.4rem;letter-spacing:0.5rem;">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-semibold">Confirm PIN</label>
                                <input type="password" class="form-control form-control-lg text-center font-weight-bold"
                                       id="new_pin_confirmation" name="new_pin_confirmation" maxlength="6" inputmode="numeric" autocomplete="new-password"
                                       placeholder="••••••" style="font-size:1.4rem;letter-spacing:0.5rem;">
                            </div>

                            {{-- Numpad --}}
                            <div id="numpad" class="mt-3" data-target="new_pin">
                                @include('admin.vault._numpad')
                            </div>

                            <button type="submit" class="btn btn-primary btn-block btn-lg mt-3" id="set-pin-btn">
                                <i class="fa fa-key mr-1"></i> Set PIN
                            </button>
                        </form>

                        {{-- ── UNLOCK FORM ── --}}
                        @elseif ($hasPinSet && !$lockedUntil)
                        <form id="unlock-form">
                            @csrf
                            <div class="form-group">
                                <label class="font-weight-semibold">Enter PIN</label>
                                <input type="password" class="form-control form-control-lg text-center font-weight-bold"
                                       id="pin_input" name="pin" maxlength="6" inputmode="numeric" autocomplete="off"
                                       placeholder="••••••" autofocus style="font-size:1.8rem;letter-spacing:0.7rem;">
                            </div>

                            {{-- Numpad --}}
                            <div id="numpad" data-target="pin_input" class="mt-2">
                                @include('admin.vault._numpad')
                            </div>

                            <button type="submit" class="btn btn-success btn-block btn-lg mt-3" id="unlock-btn">
                                <i class="fa fa-unlock-alt mr-1"></i> Unlock
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="#" class="text-muted small" id="forgot-pin-link">Forgot PIN?</a>
                            <div id="forgot-pin-info" class="alert alert-warning mt-2 d-none" style="font-size:0.85rem;">
                                <i class="fa fa-exclamation-triangle mr-1"></i>
                                Please contact your system administrator to reset the vault PIN.
                            </div>
                        </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- ================================================================
                 STATE 2: VAULT UNLOCKED
                 ================================================================ --}}
            @else

            {{-- ── Vault Header Bar ── --}}
            <div class="kt-portlet mb-3" style="border-left:4px solid #28a745;">
                <div class="kt-portlet__body py-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <div class="d-flex align-items-center">
                            <i class="fa fa-lock-open mr-2" style="color:#28a745;font-size:1.4rem;"></i>
                            <div>
                                <h5 class="mb-0 font-weight-bold">Credentials Vault</h5>
                                <small class="text-muted">All values are encrypted at rest</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-md-0" style="gap:8px;">
                            <span id="auto-lock-timer" class="badge badge-secondary py-2 px-3" style="font-size:0.9rem;">
                                <i class="fa fa-clock mr-1"></i>
                                Auto-locks in <span id="timer-display">--:--</span>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#changePinModal">
                                <i class="fa fa-key mr-1"></i>Change PIN
                            </button>
                            <form method="POST" action="{{ route('admin.vault.lock') }}" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fa fa-lock mr-1"></i>Lock Now
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Category Tabs ── --}}
            <div class="kt-portlet">
                <div class="kt-portlet__body p-0">

                    <ul class="nav nav-tabs nav-tabs-line" id="vault-tabs" style="padding:0 20px;background:#fafafa;border-bottom:1px solid #eee;">
                        <li class="nav-item">
                            <a class="nav-link {{ request('category') === null || request('category') === '' ? 'active' : '' }}"
                               href="#tab-all" data-toggle="tab" data-category="all">
                                <i class="fa fa-th-list mr-1"></i>All
                            </a>
                        </li>
                        @foreach ($categories as $catKey => $catLabel)
                        <li class="nav-item">
                            <a class="nav-link {{ request('category') === $catKey ? 'active' : '' }}"
                               href="#tab-{{ $catKey }}" data-toggle="tab" data-category="{{ $catKey }}">
                                {{ $catLabel }}
                                @if (!empty($groupedByCategory[$catKey]))
                                    <span class="badge badge-secondary ml-1">{{ count($groupedByCategory[$catKey]) }}</span>
                                @endif
                            </a>
                        </li>
                        @endforeach
                    </ul>

                    <div class="tab-content p-4">

                        {{-- ALL tab --}}
                        <div class="tab-pane {{ request('category') === null || request('category') === '' ? 'active' : '' }}" id="tab-all">
                            @foreach ($categories as $catKey => $catLabel)
                                @if (!empty($groupedByCategory[$catKey]))
                                    @include('admin.vault._category_table', [
                                        'catKey'   => $catKey,
                                        'catLabel' => $catLabel,
                                        'rows'     => $groupedByCategory[$catKey],
                                    ])
                                @endif
                            @endforeach

                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-success" id="save-all-btn">
                                    <i class="fa fa-save mr-1"></i>Save All Changes
                                </button>
                            </div>
                        </div>

                        {{-- Per-category tabs --}}
                        @foreach ($categories as $catKey => $catLabel)
                        <div class="tab-pane {{ request('category') === $catKey ? 'active' : '' }}" id="tab-{{ $catKey }}">
                            @if (!empty($groupedByCategory[$catKey]))
                                @include('admin.vault._category_table', [
                                    'catKey'   => $catKey,
                                    'catLabel' => $catLabel,
                                    'rows'     => $groupedByCategory[$catKey],
                                ])
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button" class="btn btn-success save-category-btn" data-category="{{ $catKey }}">
                                        <i class="fa fa-save mr-1"></i>Save {{ $catLabel }}
                                    </button>
                                </div>
                            @else
                                <div class="text-center text-muted py-5">
                                    <i class="fa fa-inbox fa-2x mb-3 d-block"></i>
                                    No credentials in this category yet.
                                </div>
                            @endif
                        </div>
                        @endforeach

                    </div>{{-- /tab-content --}}
                </div>
            </div>

            {{-- ── Change PIN Modal ── --}}
            <div class="modal fade" id="changePinModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa fa-key mr-2"></i>Change Vault PIN</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div id="change-pin-error" class="alert alert-danger d-none"></div>
                            <div id="change-pin-success" class="alert alert-success d-none"></div>
                            <div class="form-group">
                                <label class="font-weight-semibold">Current PIN</label>
                                <input type="password" class="form-control" id="cp_current" maxlength="6" inputmode="numeric" placeholder="••••••">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-semibold">New PIN</label>
                                <input type="password" class="form-control" id="cp_new" maxlength="6" inputmode="numeric" placeholder="••••••">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-semibold">Confirm New PIN</label>
                                <input type="password" class="form-control" id="cp_confirm" maxlength="6" inputmode="numeric" placeholder="••••••">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="change-pin-save-btn">
                                <i class="fa fa-save mr-1"></i>Save New PIN
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @endif {{-- isUnlocked --}}

        </div>
        {{-- end Content --}}
    </div>

    @endif {{-- password null --}}

    <!-- begin:: Footer -->
    @include('includes.footer')
    <!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->

<div id="kt_scrolltop" class="kt-scrolltop"><i class="la la-arrow-up"></i></div>

{{-- Toast container --}}
<div id="vault-toast-container" style="position:fixed;top:80px;right:20px;z-index:9999;min-width:280px;"></div>

@include('admin.layouts.scripts')

<script>
(function () {
    'use strict';

    // ── CSRF helper ──────────────────────────────────────────────────────────
    var CSRF = $('meta[name="csrf-token"]').attr('content');

    // ── Routes ───────────────────────────────────────────────────────────────
    var ROUTES = {
        unlock:        '{{ route("admin.vault.unlock") }}',
        setPin:        '{{ route("admin.vault.set-pin") }}',
        changePin:     '{{ route("admin.vault.change-pin") }}',
        save:          '{{ route("admin.vault.save") }}',
        getCredential: '{{ route("admin.vault.get-credential") }}',
    };

    // ── Toast ────────────────────────────────────────────────────────────────
    function showToast(msg, type) {
        type = type || 'success';
        var bg = type === 'success' ? '#28a745' : (type === 'warning' ? '#ffc107' : '#dc3545');
        var color = type === 'warning' ? '#212529' : '#fff';
        var $t = $('<div>')
            .css({background: bg, color: color, borderRadius: '6px', padding: '12px 18px',
                  marginBottom: '8px', boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                  fontSize: '0.9rem', opacity: 0, transition: 'opacity 0.3s'})
            .html('<i class="fa fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' mr-2"></i>' + msg);
        $('#vault-toast-container').append($t);
        setTimeout(function () { $t.css('opacity', 1); }, 10);
        setTimeout(function () { $t.css('opacity', 0); setTimeout(function () { $t.remove(); }, 350); }, 3500);
    }

    // ── Numpad logic ─────────────────────────────────────────────────────────
    function initNumpad($numpad) {
        if (!$numpad.length) return;

        var targetId = $numpad.attr('data-target') || 'pin_input';
        var activeTarget = targetId;

        // Track which input is focused within the set-pin form
        $('#new_pin, #new_pin_confirmation').on('focus', function () {
            activeTarget = $(this).attr('id');
        });

        $numpad.on('click', '.numpad-btn', function () {
            var val = $(this).data('val');
            var $input = $('#' + activeTarget);
            if (!$input.length) $input = $('#' + targetId);

            if (val === 'back') {
                $input.val($input.val().slice(0, -1));
            } else {
                if ($input.val().length < 6) {
                    $input.val($input.val() + val);
                }
            }
            $input.trigger('input');
        });
    }

    // ── SET PIN ──────────────────────────────────────────────────────────────
    $('#set-pin-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#set-pin-btn');
        var pin  = $('#new_pin').val();
        var conf = $('#new_pin_confirmation').val();

        $('#pin-error').addClass('d-none').text('');

        if (pin.length !== 6 || !/^\d{6}$/.test(pin)) {
            $('#pin-error').removeClass('d-none').text('PIN must be exactly 6 digits.');
            return;
        }
        if (pin !== conf) {
            $('#pin-error').removeClass('d-none').text('PINs do not match.');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Setting PIN...');

        $.ajax({
            url: ROUTES.setPin,
            method: 'POST',
            data: { _token: CSRF, new_pin: pin, new_pin_confirmation: conf },
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    $('#pin-error').removeClass('d-none').text(res.message || 'Error.');
                    $btn.prop('disabled', false).html('<i class="fa fa-key mr-1"></i>Set PIN');
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error.';
                $('#pin-error').removeClass('d-none').text(msg);
                $btn.prop('disabled', false).html('<i class="fa fa-key mr-1"></i>Set PIN');
            }
        });
    });

    // ── UNLOCK ───────────────────────────────────────────────────────────────
    $('#unlock-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#unlock-btn');
        var pin  = $('#pin_input').val();

        $('#pin-error').addClass('d-none').text('');

        if (pin.length !== 6) {
            $('#pin-error').removeClass('d-none').text('Please enter your 6-digit PIN.');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Unlocking...');

        $.ajax({
            url: ROUTES.unlock,
            method: 'POST',
            data: { _token: CSRF, pin: pin },
            success: function (res) {
                if (res.success) {
                    showToast('Vault unlocked!', 'success');
                    setTimeout(function () { window.location.reload(); }, 500);
                } else {
                    $('#pin-error').removeClass('d-none').text(res.message || 'Incorrect PIN.');
                    $('#pin_input').val('').focus();
                    $btn.prop('disabled', false).html('<i class="fa fa-unlock-alt mr-1"></i>Unlock');
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error.';
                var isLocked = xhr.responseJSON && xhr.responseJSON.locked;
                $('#pin-error').removeClass('d-none').text(msg);
                $('#pin_input').val('');
                $btn.prop('disabled', false).html('<i class="fa fa-unlock-alt mr-1"></i>Unlock');
                if (isLocked) {
                    $btn.prop('disabled', true);
                    setTimeout(function () { window.location.reload(); }, 1000);
                }
            }
        });
    });

    // ── Forgot PIN ───────────────────────────────────────────────────────────
    $('#forgot-pin-link').on('click', function (e) {
        e.preventDefault();
        $('#forgot-pin-info').toggleClass('d-none');
    });

    // ── REVEAL credential ────────────────────────────────────────────────────
    $(document).on('click', '.btn-reveal', function () {
        var $btn  = $(this);
        var key   = $btn.data('key');
        var $cell = $btn.closest('.value-cell');
        var $mask = $cell.find('.value-mask');
        var $val  = $cell.find('.value-revealed');

        if ($val.length && $val.is(':visible')) {
            $val.hide();
            $mask.show();
            $btn.html('<i class="fa fa-eye mr-1"></i>Reveal');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: ROUTES.getCredential,
            method: 'POST',
            data: { _token: CSRF, setting_key: key },
            success: function (res) {
                if (res.success) {
                    if ($val.length === 0) {
                        $val = $('<span class="value-revealed text-monospace small" style="word-break:break-all;display:none;"></span>');
                        $cell.append($val);
                    }
                    var display = res.value ? res.value : '<em class="text-muted">Not set</em>';
                    $val.html(display).show();
                    $mask.hide();
                    $btn.prop('disabled', false).html('<i class="fa fa-eye-slash mr-1"></i>Hide');

                    // Auto-remask after 30 seconds
                    setTimeout(function () {
                        $val.hide();
                        $mask.show();
                        $btn.html('<i class="fa fa-eye mr-1"></i>Reveal');
                    }, 30000);
                } else {
                    showToast(res.message || 'Error retrieving credential.', 'error');
                    $btn.prop('disabled', false).html('<i class="fa fa-eye mr-1"></i>Reveal');
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error.';
                showToast(msg, 'error');
                $btn.prop('disabled', false).html('<i class="fa fa-eye mr-1"></i>Reveal');
            }
        });
    });

    // ── INLINE EDIT ──────────────────────────────────────────────────────────
    $(document).on('click', '.btn-edit-cred', function () {
        var $btn  = $(this);
        var key   = $btn.data('key');
        var $row  = $btn.closest('tr');
        var $cell = $row.find('.value-cell');

        if ($row.hasClass('editing')) return;

        $row.addClass('editing');
        $cell.find('.value-mask, .value-revealed, .btn-reveal').hide();

        var $input = $('<input type="text" class="form-control form-control-sm vault-inline-input" '
            + 'placeholder="Enter value..." autocomplete="off" data-key="' + key + '"'
            + ' style="min-width:220px;display:inline-block;">');

        var $saveBtn   = $('<button class="btn btn-sm btn-success btn-inline-save ml-1"><i class="fa fa-check"></i> Save</button>');
        var $cancelBtn = $('<button class="btn btn-sm btn-secondary btn-inline-cancel ml-1"><i class="fa fa-times"></i></button>');
        var $editWrap  = $('<span class="inline-edit-wrap d-inline-flex align-items-center"></span>');

        $editWrap.append($input).append($saveBtn).append($cancelBtn);
        $cell.append($editWrap);
        $input.focus();

        $btn.hide();

        // Cancel
        $cancelBtn.on('click', function () {
            $editWrap.remove();
            $cell.find('.value-mask, .btn-reveal').show();
            $row.removeClass('editing');
            $btn.show();
        });

        // Save inline
        $saveBtn.on('click', function () {
            var val = $input.val();
            saveSingleCredential(key, val, function (ok, msg) {
                if (ok) {
                    showToast('Saved!', 'success');
                    $editWrap.remove();
                    $cell.find('.value-mask').html(val ? '<span class="text-muted">••••••••••</span>' : '<span class="text-muted">Not set</span>');
                    $cell.find('.value-mask, .btn-reveal').show();
                    $row.removeClass('editing');
                    $btn.show();
                } else {
                    showToast(msg || 'Save failed.', 'error');
                }
            });
        });
    });

    function saveSingleCredential(key, value, cb) {
        var data = { _token: CSRF, credentials: {} };
        data.credentials[key] = value;
        $.ajax({
            url: ROUTES.save,
            method: 'POST',
            data: data,
            success: function (res) { cb(res.success, res.message); },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error.';
                cb(false, msg);
            }
        });
    }

    // ── SAVE ALL (batch) ─────────────────────────────────────────────────────
    function collectAndSave($scope, $btn) {
        var credentials = {};
        $scope.find('.vault-inline-input').each(function () {
            credentials[$(this).data('key')] = $(this).val();
        });
        // Also collect any visible edited inputs in table rows
        $scope.find('input[data-key]').each(function () {
            credentials[$(this).data('key')] = $(this).val();
        });

        if (Object.keys(credentials).length === 0) {
            showToast('No changes to save. Use the Edit button on a row first.', 'warning');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Saving...');

        $.ajax({
            url: ROUTES.save,
            method: 'POST',
            data: { _token: CSRF, credentials: credentials },
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>' + $btn.data('label'));
                if (res.success) {
                    showToast(res.message || 'Saved!', 'success');
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    showToast(res.message || 'Save failed.', 'error');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>' + $btn.data('label'));
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error.';
                showToast(msg, 'error');
            }
        });
    }

    $('#save-all-btn').data('label', 'Save All Changes').on('click', function () {
        collectAndSave($('#tab-all'), $(this));
    });

    $(document).on('click', '.save-category-btn', function () {
        var cat   = $(this).data('category');
        var $pane = $('#tab-' + cat);
        $(this).data('label', $(this).text().trim());
        collectAndSave($pane, $(this));
    });

    // ── CHANGE PIN modal ─────────────────────────────────────────────────────
    $('#change-pin-save-btn').on('click', function () {
        var $btn    = $(this);
        var current = $('#cp_current').val();
        var newPin  = $('#cp_new').val();
        var confirm = $('#cp_confirm').val();

        $('#change-pin-error').addClass('d-none').text('');
        $('#change-pin-success').addClass('d-none').text('');

        if (!/^\d{6}$/.test(current) || !/^\d{6}$/.test(newPin) || !/^\d{6}$/.test(confirm)) {
            $('#change-pin-error').removeClass('d-none').text('All PINs must be exactly 6 digits.');
            return;
        }
        if (newPin !== confirm) {
            $('#change-pin-error').removeClass('d-none').text('New PIN and confirmation do not match.');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Saving...');

        $.ajax({
            url: ROUTES.changePin,
            method: 'POST',
            data: { _token: CSRF, current_pin: current, new_pin: newPin, new_pin_confirmation: confirm },
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save New PIN');
                if (res.success) {
                    $('#change-pin-success').removeClass('d-none').text(res.message || 'PIN changed!');
                    $('#cp_current, #cp_new, #cp_confirm').val('');
                    setTimeout(function () { $('#changePinModal').modal('hide'); }, 1500);
                } else {
                    $('#change-pin-error').removeClass('d-none').text(res.message || 'Error.');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save New PIN');
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error.';
                $('#change-pin-error').removeClass('d-none').text(msg);
            }
        });
    });

    $('#changePinModal').on('hidden.bs.modal', function () {
        $('#change-pin-error, #change-pin-success').addClass('d-none').text('');
        $('#cp_current, #cp_new, #cp_confirm').val('');
    });

    // ── AUTO-LOCK COUNTDOWN ───────────────────────────────────────────────────
    @if ($isUnlocked)
    var remainingSeconds = {{ (int) $remainingSeconds }};
    var $timerDisplay   = $('#timer-display');
    var $timerBadge     = $('#auto-lock-timer');

    function updateTimer() {
        if (remainingSeconds <= 0) {
            $timerDisplay.text('00:00');
            showToast('Vault has auto-locked.', 'warning');
            setTimeout(function () { window.location.reload(); }, 800);
            return;
        }
        var m = Math.floor(remainingSeconds / 60);
        var s = remainingSeconds % 60;
        $timerDisplay.text((m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s);

        if (remainingSeconds <= 180) {
            $timerBadge.removeClass('badge-secondary').addClass('badge-danger');
        } else {
            $timerBadge.removeClass('badge-danger').addClass('badge-secondary');
        }

        remainingSeconds--;
    }

    updateTimer();
    setInterval(updateTimer, 1000);
    @endif

    // ── Init numpad ───────────────────────────────────────────────────────────
    initNumpad($('#numpad'));

    // ── Open correct tab from URL param ──────────────────────────────────────
    var urlParams  = new URLSearchParams(window.location.search);
    var catParam   = urlParams.get('category');
    if (catParam && catParam !== '') {
        var $tabLink = $('[data-category="' + catParam + '"]');
        if ($tabLink.length) $tabLink.tab('show');
    }

}());
</script>

</body>
</html>
