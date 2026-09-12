<!DOCTYPE html>
<html lang="en">
<head>
    <!-- <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Form - Travel Coverage Details</title>
     <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"> -->
    <style>
        /* Base styles provided by the user and enhanced for responsiveness/aesthetics */
        .form-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06);
        }
        
        .form-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0;
        }
        
        .form-section {
            padding: 1.5rem;
        }
        
        .form-row {
            display: flex;
            flex-direction: column; /* Default stack on mobile */
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fafafa;
        }

        @media (min-width: 640px) {
             .form-row {
                flex-direction: row; /* Horizontal layout on larger screens */
                align-items: center;
             }
             .form-label {
                width: 150px; /* Fixed width for labels on desktop */
                flex-shrink: 0;
             }
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #4b5563;
        }
        
        .form-input, .form-select {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
            width: 100%; /* Make inputs full width in container */
        }
        
        .form-select {
            background: white;
        }
        
        .btn-add {
            background: #10b981; /* Green for Add */
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-add:hover {
            background: #059669;
        }
        
        .btn-remove {
            background: #ef4444;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-remove:hover {
            background: #dc2626;
        }
        
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3b82f6; /* Blue border for sections */
        }
        
        .table-header {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px 4px 0 0;
        }
        
        .coverage-grid {
             /* Define 4 columns based on the user's original table-row definition */
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 120px;
        }

        /* Override user's .table-row to use the coverage-grid layout */
        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 120px; /* Description, Sum, Premium, Actions */
            border: 1px solid #d1d5db;
            border-top: none;
        }
        
        .table-cell {
            padding: 0.75rem;
            border-right: 1px solid #d1d5db;
            display: flex;
            align-items: center;
        }
        
        .table-cell:last-child {
            border-right: none;
        }
        
        .table-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
            background: white;
        }

        /* Mobile Adjustments for Table (Stack columns 2x2 on small screens) */
        @media (max-width: 768px) {
            .table-row, .coverage-grid {
                grid-template-columns: 1fr 1fr; 
            }
            .table-header .coverage-grid {
                grid-template-columns: 1fr 1fr;
            }
            .table-cell {
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
            .table-cell:nth-child(even) {
                border-right: 1px solid #d1d5db;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <!-- Main Form Container -->
        <div class="form-container">
            
           
            <!-- Form Content -->
            <div class="form-section">
                
                <!-- Travel Details Section -->
                <div class="mb-8">
                    <h2 class="section-title">Travel Details</h2>
                    
                    <!-- Date of Travel -->
                    <div class="form-row">
                        <label for="date-of-travel" class="form-label">Date of travel:</label>
                        <input type="date" id="date-of-travel" class="form-input">
                    </div>

                    <!-- Passport Number -->
                    <div class="form-row">
                        <label for="passport-number" class="form-label">Passport number:</label>
                        <input type="text" id="passport-number" placeholder="Enter passport number" class="form-input">
                    </div>

                    <!-- Type of Cover -->
                    <div class="form-row">
                        <label for="type-of-cover" class="form-label">Type of cover: (Individual/family/corporate)</label>
                        <select id="type-of-cover" class="form-select">
                            <option value="individual">Individual</option>
                            <option value="family">Family</option>
                            <option value="corporate">Corporate</option>
                        </select>
                    </div>

                    <!-- Purpose of the Trip -->
                    <div class="form-row">
                        <label for="purpose-of-trip" class="form-label">Purpose of the trip: (leisure/school/work/others)</label>
                        <select id="purpose-of-trip" class="form-select">
                            <option value="leisure">Leisure</option>
                            <option value="school">School</option>
                            <option value="work">Work</option>
                            <option value="others">Others</option>
                        </select>
                    </div>

                    <!-- Destination -->
                    <div class="form-row">
                        <label for="destination" class="form-label">Destination:</label>
                        <input type="text" id="destination" placeholder="Country" class="form-input">
                    </div>
                </div>


                <!-- Coverage Details Table Section -->
                <div class="mb-8">
                    <h2 class="section-title">Coverage Details</h2>
                    
                    <!-- Horizontal Header -->
                <!-- Horizontal Header -->
                    <div class="table-header flex gap-4 bg-gray-200 font-semibold border border-gray-300 rounded-t p-2">
                        <div class="table-row"> 
                        <div class="table-cell">Description</div>
                        <div class="table-cell">Sum Insured</div>
                        <div class="table-cell">Premium</div>
                        <div class="table-cell">Actions</div>
                        </div>
                    </div>
                    
                    <!-- Coverage Rows Container -->
                    <div id="coverage-details-rows" class="space-y-0">
                        <!-- Coverage Data based on the image -->
                        <div class="table-row">
                            <div class="table-cell"><input type="text" value="Personal assistance" class="table-input font-medium"></div>
                            <div class="table-cell"><input type="text" placeholder="50000" class="table-input"></div>
                            <div class="table-cell"><input type="text" placeholder="5.00" class="table-input"></div>
                            <div class="table-cell justify-center">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCoverageRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCoverageRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-row">
                            <div class="table-cell"><input type="text" value="Medical transportation and repatriation" class="table-input font-medium"></div>
                            <div class="table-cell"><input type="text" placeholder="100000" class="table-input"></div>
                            <div class="table-cell"><input type="text" placeholder="10.00" class="table-input"></div>
                            <div class="table-cell justify-center">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCoverageRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCoverageRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-row">
                            <div class="table-cell"><input type="text" value="Medical expenses" class="table-input font-medium"></div>
                            <div class="table-cell"><input type="text" placeholder="500000" class="table-input"></div>
                            <div class="table-cell"><input type="text" placeholder="25.00" class="table-input"></div>
                            <div class="table-cell justify-center">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCoverageRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCoverageRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Added a generic row to show the Add functionality -->
                         <div class="table-row">
                            <div class="table-cell"><input type="text" value="Personal accident" class="table-input font-medium"></div>
                            <div class="table-cell"><input type="text" placeholder="10000" class="table-input"></div>
                            <div class="table-cell"><input type="text" placeholder="8.00" class="table-input"></div>
                            <div class="table-cell justify-center">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCoverageRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCoverageRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
             
                </div>
            </div>
        </div>
    </div>

    <script>
        // Container ID for coverage rows
        const coverageContainerId = 'coverage-details-rows';
        
        // Function to create a new row template
        function createNewCoverageRow() {
            return `
                <div class="table-row">
                    <div class="table-cell">
                        <input type="text" placeholder="Enter description" class="table-input">
                    </div>
                    <div class="table-cell">
                        <input type="text" placeholder="Enter sum insured" class="table-input">
                    </div>
                    <div class="table-cell">
                        <input type="text" placeholder="Enter premium" class="table-input">
                    </div>
                    <div class="table-cell justify-center">
                        <div class="btn-group">
                            <button type="button" class="btn-add" onclick="addCoverageRow()">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button type="button" class="btn-remove" onclick="removeCoverageRow(this)">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // Function to add a new coverage row
        function addCoverageRow() {
            const container = document.getElementById(coverageContainerId);
            const newRow = document.createElement('div');
            newRow.innerHTML = createNewCoverageRow();
            // Append the first child element which is the actual .table-row
            container.appendChild(newRow.firstElementChild); 
        }
        
        // Function to remove a coverage row
        function removeCoverageRow(button) {
            const container = document.getElementById(coverageContainerId);
            const rows = container.getElementsByClassName('table-row');
            
            // Allow removal only if more than one row exists
            if (rows.length > 1) { 
                const row = button.closest('.table-row');
                row.remove();
            } else {
                // Inform the user, avoiding the problematic alert() function
                console.warn('Cannot remove the last coverage row.');
                const header = document.querySelector('.form-header');
                const message = document.createElement('p');
                message.className = 'text-red-500 text-sm mt-2 p-2 bg-red-100 rounded';
                message.textContent = 'At least one coverage row must remain.';
                header.appendChild(message);
                setTimeout(() => message.remove(), 3000);
            }
        }

        // Mapping the original function names from the user's snippet to the new functions
        window.addCyberLiabilityRow = addCoverageRow;
        window.removeCyberLiabilityRow = removeCoverageRow;
    </script>
</body>
</html>
