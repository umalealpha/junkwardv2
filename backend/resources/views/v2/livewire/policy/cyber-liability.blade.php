<!DOCTYPE html>
<html lang="en">
<head>
    <!-- <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Form - Cyber Liability</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"> -->
    <style>
        .form-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fafafa;
        }
        
        .form-input {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .form-select {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
            background: white;
        }
        
        .btn-add {
            background: #8b5cf6;
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
            background: #7c3aed;
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
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table-header {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px 4px 0 0;
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 120px;
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
        }
        
        .notes-section {
            margin-top: 2rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f9fafb;
        }
        
        .notes-textarea {
            width: 100%;
            min-height: 100px;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
            resize: vertical;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <!-- Main Form Container -->
        <div class="form-container">
  

            <!-- Form Content -->
            <div class="form-section">
                <!-- Cyber Liability Table Section -->
                <div class="mb-8">
                
             <!-- Horizontal Header -->
                    <div class="table-header flex gap-4 bg-gray-200 font-semibold border border-gray-300 rounded-t p-2">
                        <div class="table-row"> 
                        <div class="table-cell">Description</div>
                        <div class="table-cell">Sum Insured</div>
                        <div class="table-cell">Premium</div>
                        <div class="table-cell">Actions</div>
                        </div>
                    </div>

                    
                    <div id="cyber-liability-rows" class="space-y-0">
                        <div class="table-row">
                            <div class="table-cell">
                                <input type="text" name="cyber_description[]" value="Hardware" class="table-input">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_sum_insured[]" class="table-input" placeholder="Enter amount">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_premium[]" class="table-input" placeholder="Enter premium">
                            </div>
                            <div class="table-cell">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCyberLiabilityRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCyberLiabilityRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-row">
                            <div class="table-cell">
                                <input type="text" name="cyber_description[]" value="Data corruption and extra costs" class="table-input">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_sum_insured[]" class="table-input" placeholder="Enter amount">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_premium[]" class="table-input" placeholder="Enter premium">
                            </div>
                            <div class="table-cell">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCyberLiabilityRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCyberLiabilityRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-row">
                            <div class="table-cell">
                                <input type="text" name="cyber_description[]" value="Cyber crime" class="table-input">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_sum_insured[]" class="table-input" placeholder="Enter amount">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_premium[]" class="table-input" placeholder="Enter premium">
                            </div>
                            <div class="table-cell">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCyberLiabilityRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCyberLiabilityRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-row">
                            <div class="table-cell">
                                <input type="text" name="cyber_description[]" value="Cyber liability" class="table-input">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_sum_insured[]" class="table-input" placeholder="Enter amount">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_premium[]" class="table-input" placeholder="Enter premium">
                            </div>
                            <div class="table-cell">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCyberLiabilityRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCyberLiabilityRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-row">
                            <div class="table-cell">
                                <input type="text" name="cyber_description[]" value="Data breach expense" class="table-input">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_sum_insured[]" class="table-input" placeholder="Enter amount">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_premium[]" class="table-input" placeholder="Enter premium">
                            </div>
                            <div class="table-cell">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCyberLiabilityRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCyberLiabilityRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-row">
                            <div class="table-cell">
                                <input type="text" name="cyber_description[]" value="Cyber event- loss of income" class="table-input">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_sum_insured[]" class="table-input" placeholder="Enter amount">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_premium[]" class="table-input" placeholder="Enter premium">
                            </div>
                            <div class="table-cell">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCyberLiabilityRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCyberLiabilityRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-row">
                            <div class="table-cell">
                                <input type="text" name="cyber_description[]" value="Business Interruption" class="table-input">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_sum_insured[]" class="table-input" placeholder="Enter amount">
                            </div>
                            <div class="table-cell">
                                <input type="text" name="cyber_premium[]" class="table-input" placeholder="Enter premium">
                            </div>
                            <div class="table-cell">
                                <div class="btn-group">
                                    <button type="button" class="btn-add" onclick="addCyberLiabilityRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn-remove" onclick="removeCyberLiabilityRow(this)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Excesses Section -->
                <div class="mt-8">
                    <h2 class="section-title">Excesses</h2>
                    
                    <div class="table-header">
                        <div class="grid grid-cols-3 gap-4 table-cell">
                            <div>Excesses</div>
                            <div>Min %</div>
                            <div>Minimum Amount</div>
                        </div>
                    </div>
                    
                    <div class="table-row">
                        <div class="table-cell">
                            <input type="text" name="excesses[]" class="table-input" placeholder="Enter excesses">
                        </div>
                        <div class="table-cell">
                            <input type="text" name="min_percentage[]" class="table-input" placeholder="Enter min %">
                        </div>
                        <div class="table-cell">
                            <input type="text" name="min_amount[]" class="table-input" placeholder="Enter minimum amount">
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Cyber Liability section functions
        function addCyberLiabilityRow() {
            const container = document.getElementById('cyber-liability-rows');
            const newRow = document.createElement('div');
            newRow.className = 'table-row';
            newRow.innerHTML = `
                <div class="table-cell">
                    <input type="text" name="cyber_description[]" class="table-input" placeholder="Enter description">
                </div>
                <div class="table-cell">
                    <input type="text" name="cyber_sum_insured[]" class="table-input" placeholder="Enter amount">
                </div>
                <div class="table-cell">
                    <input type="text" name="cyber_premium[]" class="table-input" placeholder="Enter premium">
                </div>
                <div class="table-cell">
                    <div class="btn-group">
                        <button type="button" class="btn-add" onclick="addCyberLiabilityRow()">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn-remove" onclick="removeCyberLiabilityRow(this)">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(newRow);
        }
        
        function removeCyberLiabilityRow(button) {
            const container = document.getElementById('cyber-liability-rows');
            if (container.children.length > 1) {
                const row = button.closest('.table-row');
                row.remove();
            } else {
                alert('At least one cyber liability row must remain.');
            }
        }
    </script>
</body>
</html>
