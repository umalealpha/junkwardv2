@extends('hr.layouts.app')

@section('title', 'Update Policies')
@section('page-title', 'Update Policies')

@section('content')
<!-- Policy Update Card -->
<div class="hr-card">
    <div class="hr-card-header">
        <h3 class="hr-card-title">
            <i class="flaticon2-edit" style="margin-right: 8px; color: #3b82f6;"></i>
            Policy Update Interface
        </h3>
        <div class="hr-card-actions">
            <button type="button" class="btn btn-success" id="saveChangesBtn" style="background-color: #10b981; border-color: #10b981; color: white; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
                <i class="flaticon2-check-mark" style="margin-right: 6px;"></i>
                Save Changes
            </button>
            <button type="button" class="btn btn-secondary" id="discardChangesBtn" style="margin-left: 10px;">
                <i class="flaticon2-delete" style="margin-right: 6px;"></i>
                Discard Changes
            </button>
        </div>
    </div>

    <div class="hr-card-body">
        <!-- Info Banner -->
        <div class="alert alert-info" role="alert">
            <i class="la la-info-circle" style="margin-right: 8px;"></i>
            <strong>Premium Update Interface:</strong> Click on any editable cell to modify customer details that affect premium calculations. Premium amounts are read-only. Changes are highlighted in yellow. Press Enter to save individual changes or use the "Save Changes" button to save all modifications.
        </div>

        <!-- Search and Filter -->
        <div class="mb-4">
            <div class="d-flex gap-4 align-items-end flex-wrap">
                <div class="hr-form-group" style="flex: 1; min-width: 300px;">
                    <label class="hr-form-label">
                        <i class="la la-search" style="margin-right: 6px; color: #1e40af;"></i>
                        Search Policies
                    </label>
                    <input type="text" class="hr-form-control" id="policySearch" placeholder="Search by policy number, customer name, or phone..." style="border-radius: 8px; border: 1px solid #e2e8f0; padding: 12px 16px; font-size: 16px;">
                </div>
                <div class="hr-form-group">
                    <label class="hr-form-label">Show</label>
                    <select class="hr-form-control" id="pageLength" style="border-radius: 8px; border: 1px solid #e2e8f0; padding: 12px 16px;">
                        <option value="10">10 entries</option>
                        <option value="25">25 entries</option>
                        <option value="50">50 entries</option>
                        <option value="100">100 entries</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Excel-like Table -->
        <div class="excel-table-container" style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
            <table class="excel-table" id="policyUpdateTable" style="width: 100%; border-collapse: collapse; background: white;">
                <thead style="background: #f8fafc; position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">ID</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">Policy Number</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">Customer Name</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">Plan Name</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">Premium</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">DOB</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">Gender</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">Beneficiary Name</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">Beneficiary DOB</th>
                        <th style="border: 1px solid #e2e8f0; padding: 12px 8px; text-align: center; font-weight: 600; color: #374151; background: #f1f5f9;">Beneficiary Gender</th>
                    </tr>
                </thead>
                <tbody id="policyUpdateTableBody">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="pagination-info" style="color: #6b7280; font-size: 14px;">
                Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> entries
            </div>
            <div class="pagination-controls">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="prevPage" disabled>
                    <i class="la la-angle-left"></i> Previous
                </button>
                <span class="mx-3" id="pageInfo">Page 1 of 1</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="nextPage" disabled>
                    Next <i class="la la-angle-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; text-align: center;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
        <p class="mt-3 mb-0">Saving changes...</p>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<style>
    .excel-table {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 13px;
        min-width: 1000px; /* Optimized width for 10 columns */
    }
    
    .excel-table td {
        border: 1px solid #e2e8f0;
        padding: 8px 12px;
        vertical-align: middle;
        position: relative;
        background: white;
        transition: background-color 0.2s ease;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 180px;
    }
    
    .excel-table td:hover {
        background: #f8fafc;
    }
    
    .beneficiary-item:hover {
        background: #d1d5db !important;
        transform: scale(1.02);
        transition: all 0.2s ease;
    }
    
    .beneficiary-edit-input {
        border: 1px solid #3b82f6;
        border-radius: 3px;
        outline: none;
    }
    
    .beneficiary-edit-input:focus {
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    
    .excel-table td.editable {
        cursor: pointer;
    }
    
    .excel-table td.editing {
        background: #fef3c7 !important;
        border: 2px solid #f59e0b !important;
    }
    
    .excel-table td.changed {
        background: #fef3c7 !important;
        border-left: 4px solid #f59e0b !important;
    }
    
    .excel-table input {
        border: none;
        background: transparent;
        width: 100%;
        height: 100%;
        padding: 4px 8px;
        font-size: 13px;
        outline: none;
    }
    
    .excel-table select {
        border: none;
        background: transparent;
        width: 100%;
        height: 100%;
        padding: 4px 8px;
        font-size: 13px;
        outline: none;
        cursor: pointer;
    }
    
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-0 { background: #fef2f2; color: #dc2626; }
    .status-1 { background: #f0fdf4; color: #16a34a; }
    .status-2 { background: #fef3c7; color: #d97706; }
    .status-3 { background: #f3f4f6; color: #6b7280; }
</style>

<script>
$(document).ready(function() {
    let currentPage = 1;
    let pageSize = 10;
    let totalRecords = 0;
    let totalPages = 0;
    let changes = {};
    let originalData = {};
    
    // Initialize table
    loadPolicyData();
    
    // Search functionality
    $('#policySearch').on('keyup', debounce(function() {
        currentPage = 1;
        loadPolicyData();
    }, 500));
    
    // Page size change
    $('#pageLength').on('change', function() {
        pageSize = parseInt($(this).val());
        currentPage = 1;
        loadPolicyData();
    });
    
    // Pagination
    $('#prevPage').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadPolicyData();
        }
    });
    
    $('#nextPage').on('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadPolicyData();
        }
    });
    
    // Save changes
    $('#saveChangesBtn').on('click', function() {
        saveChanges();
    });
    
    // Discard changes
    $('#discardChangesBtn').on('click', function() {
        if (Object.keys(changes).length > 0) {
            if (confirm('Are you sure you want to discard all changes?')) {
                changes = {};
                loadPolicyData();
            }
        }
    });
    
    function loadPolicyData() {
        console.log('Loading policy data...');
        $.ajax({
            url: '{{ route("hr.policy-update-data") }}',
            method: 'GET',
            data: {
                draw: 1,
                start: (currentPage - 1) * pageSize,
                length: pageSize,
                search: {
                    value: $('#policySearch').val()
                }
            },
            success: function(response) {
                console.log('Policy data loaded successfully:', response);
                totalRecords = response.iTotalRecords;
                totalPages = Math.ceil(totalRecords / pageSize);
                
                renderTable(response.aaData);
                updatePagination();
            },
            error: function(xhr, status, error) {
                console.error('Error loading policy data:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status,
                    url: '{{ route("hr.policy-update-data") }}'
                });
                
                let errorMessage = 'Error loading data: ' + error;
                if (xhr.status === 401) {
                    errorMessage = 'Authentication required. Please log in again.';
                } else if (xhr.status === 404) {
                    errorMessage = 'Route not found. Please check if the route is properly registered.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Check Laravel logs for details.';
                }
                
                $('#policyUpdateTableBody').html('<tr><td colspan="10" class="text-center text-danger">' + errorMessage + '</td></tr>');
            }
        });
    }
    
    function renderTable(data) {
        const tbody = $('#policyUpdateTableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="10" class="text-center text-muted">No policies found</td></tr>');
            return;
        }
        
        data.forEach(function(policy) {
            const rowId = `policy-${policy.id}`;
            originalData[policy.id] = { ...policy };
            
            const statusOptions = [
                { value: 0, text: 'Deactivated', class: 'status-0' },
                { value: 1, text: 'Activated', class: 'status-1' },
                { value: 2, text: 'Cancelled', class: 'status-2' },
                { value: 3, text: 'Expired', class: 'status-3' }
            ];
            
            const statusSelect = statusOptions.map(opt => 
                `<option value="${opt.value}" ${policy.status == opt.value ? 'selected' : ''}>${opt.text}</option>`
            ).join('');
            
            const genderOptions = [
                { value: 1, text: 'Male' },
                { value: 0, text: 'Female' }
            ];
            
            const genderSelect = genderOptions.map(opt => 
                `<option value="${opt.value}" ${policy.gender == opt.value ? 'selected' : ''}>${opt.text}</option>`
            ).join('');
            
            // Parse beneficiary data
            const beneficiaryNames = policy.beneficiary_names ? policy.beneficiary_names.split(',') : [];
            const beneficiaryDobs = policy.beneficiary_dobs ? policy.beneficiary_dobs.split(',') : [];
            const beneficiaryGenders = policy.beneficiary_genders ? policy.beneficiary_genders.split(',') : [];
            
            const row = `
                <tr id="${rowId}">
                    <td style="text-align: center; background: #f8fafc; font-weight: 600;">${policy.id}</td>
                    <td style="background: #f8fafc; font-family: monospace; font-weight: 600;">${policy.policy_number}</td>
                    <td class="editable" data-field="customer_name" data-policy-id="${policy.id}" data-original="${policy.customer_name}">
                        ${policy.customer_name || 'N/A'}
                    </td>
                    <td style="background: #f8fafc;">${policy.plan_name || 'N/A'}</td>
                    <td style="background: #f8fafc; font-weight: 600; color: #059669;">P ${parseFloat(policy.premium || 0).toFixed(2)}</td>
                    <td class="editable" data-field="customer_dob" data-policy-id="${policy.id}" data-original="${policy.dob}">
                        ${policy.dob || 'N/A'}
                    </td>
                    <td class="editable" data-field="customer_gender" data-policy-id="${policy.id}" data-original="${policy.gender}">
                        <select class="gender-select" data-field="customer_gender" data-policy-id="${policy.id}">
                            ${genderSelect}
                        </select>
                    </td>
                    <td class="beneficiary-editable" data-field="beneficiary_names" data-policy-id="${policy.id}" data-original="${policy.beneficiary_names || ''}" style="font-size: 12px; max-width: 200px;">
                        <div style="max-height: 60px; overflow-y: auto;">
                            ${beneficiaryNames.length > 0 ? 
                                beneficiaryNames.map((name, index) => 
                                    `<div class="beneficiary-item" data-index="${index}" style="margin: 2px 0; padding: 2px 4px; background: #e5e7eb; border-radius: 3px; font-size: 10px; cursor: pointer;" title="Click to edit">${name.trim()}</div>`
                                ).join('') : 
                                '<div style="margin: 2px 0; padding: 2px 4px; background: #f3f4f6; border-radius: 3px; font-size: 10px; color: #6b7280;">No beneficiaries</div>'
                            }
                        </div>
                    </td>
                    <td class="beneficiary-editable" data-field="beneficiary_dobs" data-policy-id="${policy.id}" data-original="${policy.beneficiary_dobs || ''}" style="font-size: 12px; max-width: 200px;">
                        <div style="max-height: 60px; overflow-y: auto;">
                            ${beneficiaryDobs.length > 0 ? 
                                beneficiaryDobs.map((dob, index) => 
                                    `<div class="beneficiary-item" data-index="${index}" style="margin: 2px 0; padding: 2px 4px; background: #e5e7eb; border-radius: 3px; font-size: 10px; cursor: pointer;" title="Click to edit">${dob.trim()}</div>`
                                ).join('') : 
                                '<div style="margin: 2px 0; padding: 2px 4px; background: #f3f4f6; border-radius: 3px; font-size: 10px; color: #6b7280;">N/A</div>'
                            }
                        </div>
                    </td>
                    <td class="beneficiary-editable" data-field="beneficiary_genders" data-policy-id="${policy.id}" data-original="${policy.beneficiary_genders || ''}" style="font-size: 12px; max-width: 200px;">
                        <div style="max-height: 60px; overflow-y: auto;">
                            ${beneficiaryGenders.length > 0 ? 
                                beneficiaryGenders.map((gender, index) => 
                                    `<div class="beneficiary-item" data-index="${index}" style="margin: 2px 0; padding: 2px 4px; background: #e5e7eb; border-radius: 3px; font-size: 10px; cursor: pointer;" title="Click to edit">${gender.trim() === '1' ? 'Male' : gender.trim() === '0' ? 'Female' : gender.trim()}</div>`
                                ).join('') : 
                                '<div style="margin: 2px 0; padding: 2px 4px; background: #f3f4f6; border-radius: 3px; font-size: 10px; color: #6b7280;">N/A</div>'
                            }
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
        
        // Bind edit events
        bindEditEvents();
    }
    
    function bindEditEvents() {
        // Customer name editing
        $('.editable[data-field="customer_name"]').on('click', function() {
            if (!$(this).hasClass('editing')) {
                startEditing($(this));
            }
        });
        
        // Customer DOB editing
        $('.editable[data-field="customer_dob"]').on('click', function() {
            if (!$(this).hasClass('editing')) {
                startEditing($(this));
            }
        });
        
        // Gender editing
        $('.gender-select').on('change', function() {
            const $td = $(this).closest('td');
            const policyId = $td.data('policy-id');
            const field = $td.data('field');
            const value = $(this).val();
            
            markAsChanged($td, policyId, field, value);
        });
        
        // Beneficiary editing
        $('.beneficiary-item').on('click', function() {
            startBeneficiaryEditing($(this));
        });
    }
    
    function startEditing($td) {
        $td.addClass('editing');
        const originalValue = $td.data('original');
        const field = $td.data('field');
        const policyId = $td.data('policy-id');
        
        let input;
        if (field === 'customer_dob') {
            input = `<input type="date" value="${originalValue || ''}" class="edit-input">`;
        } else {
            input = `<input type="text" value="${originalValue || ''}" class="edit-input">`;
        }
        
        $td.html(input);
        $td.find('.edit-input').focus().select();
        
        // Handle Enter key
        $td.find('.edit-input').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                finishEditing($td);
            }
        });
        
        // Handle blur
        $td.find('.edit-input').on('blur', function() {
            finishEditing($td);
        });
    }
    
    function finishEditing($td) {
        const field = $td.data('field');
        const policyId = $td.data('policy-id');
        const value = $td.find('.edit-input').val();
        
        $td.removeClass('editing');
        
        // Update display
        if (field === 'customer_dob') {
            $td.html(value || 'N/A');
        } else {
            $td.html(value || 'N/A');
        }
        
        markAsChanged($td, policyId, field, value);
    }
    
    function startBeneficiaryEditing($item) {
        const $td = $item.closest('td');
        const field = $td.data('field');
        const policyId = $td.data('policy-id');
        const index = $item.data('index');
        const originalValue = $td.data('original');
        const values = originalValue ? originalValue.split(',') : [];
        const currentValue = values[index] || '';
        
        // Create input based on field type
        let input;
        if (field === 'beneficiary_dobs') {
            input = `<input type="date" value="${currentValue}" class="beneficiary-edit-input" style="font-size: 10px; width: 100%; padding: 2px;">`;
        } else if (field === 'beneficiary_genders') {
            const genderOptions = [
                { value: '1', text: 'Male' },
                { value: '0', text: 'Female' }
            ];
            const genderSelect = genderOptions.map(opt => 
                `<option value="${opt.value}" ${currentValue === opt.value ? 'selected' : ''}>${opt.text}</option>`
            ).join('');
            input = `<select class="beneficiary-edit-input" style="font-size: 10px; width: 100%; padding: 2px;">${genderSelect}</select>`;
        } else {
            input = `<input type="text" value="${currentValue}" class="beneficiary-edit-input" style="font-size: 10px; width: 100%; padding: 2px;">`;
        }
        
        $item.html(input);
        $item.find('.beneficiary-edit-input').focus().select();
        
        // Handle Enter key and blur
        $item.find('.beneficiary-edit-input').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                finishBeneficiaryEditing($item, $td, field, policyId, index);
            }
        });
        
        $item.find('.beneficiary-edit-input').on('blur', function() {
            finishBeneficiaryEditing($item, $td, field, policyId, index);
        });
    }
    
    function finishBeneficiaryEditing($item, $td, field, policyId, index) {
        const value = $item.find('.beneficiary-edit-input').val();
        const originalValue = $td.data('original');
        const values = originalValue ? originalValue.split(',') : [];
        
        // Update the specific index
        values[index] = value;
        const newValue = values.join(',');
        
        // Update the display
        if (field === 'beneficiary_dobs') {
            $item.html(`<div class="beneficiary-item" data-index="${index}" style="margin: 2px 0; padding: 2px 4px; background: #e5e7eb; border-radius: 3px; font-size: 10px; cursor: pointer;" title="Click to edit">${value}</div>`);
        } else if (field === 'beneficiary_genders') {
            const displayValue = value === '1' ? 'Male' : value === '0' ? 'Female' : value;
            $item.html(`<div class="beneficiary-item" data-index="${index}" style="margin: 2px 0; padding: 2px 4px; background: #e5e7eb; border-radius: 3px; font-size: 10px; cursor: pointer;" title="Click to edit">${displayValue}</div>`);
        } else {
            $item.html(`<div class="beneficiary-item" data-index="${index}" style="margin: 2px 0; padding: 2px 4px; background: #e5e7eb; border-radius: 3px; font-size: 10px; cursor: pointer;" title="Click to edit">${value}</div>`);
        }
        
        // Update the data attribute
        $td.data('original', newValue);
        
        // Mark as changed
        markAsChanged($td, policyId, field, newValue);
        
        // Re-bind the click event
        $item.off('click').on('click', function() {
            startBeneficiaryEditing($(this));
        });
    }
    
    function markAsChanged($td, policyId, field, value) {
        const originalValue = $td.data('original');
        
        console.log('Marking as changed:', {
            policyId: policyId,
            field: field,
            value: value,
            originalValue: originalValue,
            isChanged: value != originalValue
        });
        
        if (value != originalValue) {
            $td.addClass('changed');
            
            if (!changes[policyId]) {
                changes[policyId] = {};
            }
            changes[policyId][field] = value;
            console.log('Added to changes:', changes);
        } else {
            $td.removeClass('changed');
            if (changes[policyId]) {
                delete changes[policyId][field];
                if (Object.keys(changes[policyId]).length === 0) {
                    delete changes[policyId];
                }
            }
            console.log('Removed from changes:', changes);
        }
        
        updateSaveButton();
    }
    
    function updateSaveButton() {
        const hasChanges = Object.keys(changes).length > 0;
        $('#saveChangesBtn').prop('disabled', !hasChanges);
        $('#discardChangesBtn').prop('disabled', !hasChanges);
    }
    
    function saveChanges() {
        if (Object.keys(changes).length === 0) {
            alert('No changes to save');
            return;
        }
        
        console.log('Changes to save:', changes);
        
        const updates = [];
        Object.keys(changes).forEach(policyId => {
            Object.keys(changes[policyId]).forEach(field => {
                updates.push({
                    policy_id: policyId,
                    field: field,
                    value: changes[policyId][field]
                });
            });
        });
        
        console.log('Updates array:', updates);
        
        $('#loadingOverlay').show();
        
        $.ajax({
            url: '{{ route("hr.policy-update-bulk") }}',
            method: 'POST',
            data: {
                updates: updates,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $('#loadingOverlay').hide();
                
                if (response.success) {
                    alert(`Success! ${response.message}`);
                    changes = {};
                    loadPolicyData();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                $('#loadingOverlay').hide();
                const response = xhr.responseJSON || {};
                alert('Error: ' + (response.message || 'Failed to save changes'));
            }
        });
    }
    
    function updatePagination() {
        const start = (currentPage - 1) * pageSize + 1;
        const end = Math.min(currentPage * pageSize, totalRecords);
        
        $('#showingStart').text(start);
        $('#showingEnd').text(end);
        $('#totalRecords').text(totalRecords);
        $('#pageInfo').text(`Page ${currentPage} of ${totalPages}`);
        
        $('#prevPage').prop('disabled', currentPage === 1);
        $('#nextPage').prop('disabled', currentPage === totalPages);
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
});
</script>
@endsection
