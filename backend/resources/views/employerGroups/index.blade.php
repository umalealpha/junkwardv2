<!DOCTYPE html>
<html lang="en">
@include('admin.layouts.header')

<link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<style>
    .btn-group .btn {
        margin-right: 2px;
    }
    .btn-group .btn:last-child {
        margin-right: 0;
    }
    .btn-group .btn i {
        margin-right: 4px;
    }
    .table td {
        vertical-align: middle;
    }
    .btn-group {
        white-space: nowrap;
    }
</style>

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
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

<div class="kt-grid kt-grid--hor kt-grid--root">
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">
        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')
    </div>

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">Employer Groups</h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="{{ route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Employer Groups</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ route('admin.employer-groups.create') }}" class="btn btn-sm btn-elevate btn-brand" data-toggle="kt-tooltip" title="Add Employer Group">
                        <span class="kt-opacity-11">Add Employer Group</span>&nbsp;
                        <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <table class="table table-striped table-bordered table-hover table-checkable" id="employer_groups_table">
                        <thead>
                            <tr>
                                <th>Employer ID</th>
                                <th>Employer Name</th>
                                <th>Industry</th>
                                <th>Address</th>
                                {{-- <th>Contact</th> --}}
                                <th>Contact Phone</th>
                                <th>Contact Email</th>
                                <th>Primary Agent Name</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>No. of Employees</th>
                                <th>HR Emails</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('includes.footer')
</div>

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>
    $(document).ready(function() {
        $('#employer_groups_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.employer-groups.index') }}",
            columns: [
                { data: 'employer_group_id', name: 'employer_group_id' },
                { data: 'name', name: 'name' },
                { data: 'industry', name: 'industry' },
                { data: 'address', name: 'address' },
                // { data: 'contact_details', name: 'contact_details' },
                { data: 'contact_phone', name: 'contact_phone' },
                { data: 'contact_email', name: 'contact_email' },
                { data: 'broker', name: 'broker' },
                { data: 'payment_method', name: 'payment_method' },
                { data: 'status', name: 'status' },
                { data: 'no_of_employees', name: 'no_of_employees' },
                { data: 'hr_emails', name: 'bulk_emails' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });

    function sendHrCredentials(id) {
        if (confirm('Are you sure you want to send HR login credentials to all HR emails for this employer group?')) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;
            button.classList.add('btn-warning');
            button.classList.remove('btn-success');
            
            // Send AJAX request
            $.ajax({
                url: '{{ route("admin.employer-groups.send-hr-credentials", ":id") }}'.replace(':id', id),
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message with more details
                        let message = 'Success: ' + response.message;
                        if (response.sent_emails && response.sent_emails.length > 0) {
                            message += '\n\nEmails sent to: ' + response.sent_emails.join(', ');
                        }
                        if (response.failed_emails && response.failed_emails.length > 0) {
                            message += '\n\nFailed emails:\n';
                            response.failed_emails.forEach(function(failed) {
                                message += '- ' + failed.email + ': ' + failed.error + '\n';
                            });
                        }
                        alert(message);
                        // Reload the table
                        $('#employer_groups_table').DataTable().ajax.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert('Error: ' + (response.message || 'Something went wrong'));
                },
                complete: function() {
                    // Reset button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                    button.classList.remove('btn-warning');
                    button.classList.add('btn-success');
                }
            });
        }
    }

    function sendOnboardingEmail(id) {
        if (confirm('Are you sure you want to send onboarding email to the contact email for this employer group?')) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;
            button.classList.add('btn-warning');
            button.classList.remove('btn-primary');
            
            // Send AJAX request
            $.ajax({
                url: '{{ route("admin.employer-groups.send-onboarding-email", ":id") }}'.replace(':id', id),
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Onboarding email sent successfully to: ' + response.data.contact_email);
                        // Reload the table
                        $('#employer_groups_table').DataTable().ajax.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert('Error: ' + (response.message || 'Something went wrong'));
                },
                complete: function() {
                    // Reset button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                    button.classList.remove('btn-warning');
                    button.classList.add('btn-primary');
                }
            });
        }
    }

    function deleteEmployerGroup(id) {
        if (confirm('Are you sure you want to delete this employer group?')) {
            // Create a form and submit it
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.employer-groups.destroy", ":id") }}'.replace(':id', id);
            
            // Add CSRF token
            var csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);
            
            // Add method override for DELETE
            var methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
</body>
</html>
