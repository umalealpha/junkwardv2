@php
//dd($coverages);
        // Helper function to parse comma-separated values and convert to float
        if (!function_exists('parseNumericValue')) {
            function parseNumericValue($value) {
                // Handle null, empty string, or false
                if ($value === null || $value === false || $value === '') return 0;
                
                // If already a number, return it
                if (is_numeric($value) && !is_string($value)) {
                    return (float)$value;
                }
                
                // Convert to string
                $value = trim((string)$value);
                
                // Handle empty after trim
                if ($value === '' || $value === '-') return 0;
                
                // Remove all commas (thousands separators)
                $value = str_replace(',', '', $value);
                
                // Remove currency symbols and spaces
                $value = str_replace(['P', 'p', '$', '€', '£', ' ', 'R'], '', $value);
                
                // Remove any non-numeric characters except decimal point and minus sign
                $cleaned = preg_replace('/[^0-9.-]/', '', $value);
                
                // Handle empty result or invalid formats
                if (empty($cleaned) || $cleaned === '-' || $cleaned === '.' || $cleaned === '-.') return 0;
                
                // Convert to float
                $result = (float)$cleaned;
                
                // Check for NaN or infinity
                if (is_nan($result) || is_infinite($result)) return 0;
                
                return $result;
            }
        }
        
        if (!function_exists('formatNumericValue')) {
            function formatNumericValue($value) {
                $num = parseNumericValue($value);
                return $num > 0 ? number_format($num, 2, '.', ',') : '';
            }
        }
        
        // Helper function to safely parse and format dates
        if (!function_exists('safeFormatDate')) {
            function safeFormatDate($dateValue, $format = 'd/m/Y') {
                if (empty($dateValue)) {
                    return '';
                }
                
                // Convert to string if not already
                $dateValue = (string) $dateValue;
                $dateValue = trim($dateValue);
                
                if (empty($dateValue)) {
                    return '';
                }
                
                try {
                    // If already in the desired format (d/m/Y), return as-is
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) {
                        return $dateValue;
                    }
                    
                    // Try parsing as d/m/Y format first (common display format)
                    if (strpos($dateValue, '/') !== false && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateValue)) {
                        try {
                            $parsed = \Carbon\Carbon::createFromFormat('d/m/Y', $dateValue);
                            return $parsed->format($format);
                        } catch (\Exception $e) {
                            // Continue to next format
                        }
                    }
                    
                    // Try parsing as Y-m-d format (database format)
                    if (strpos($dateValue, '-') !== false && preg_match('/^\d{4}-\d{2}-\d{2}/', $dateValue)) {
                        try {
                            $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', substr($dateValue, 0, 10));
                            return $parsed->format($format);
                        } catch (\Exception $e) {
                            // Continue to next format
                        }
                    }
                    
                    // Try Carbon's default parse (handles various formats like Y-m-d H:i:s)
                    try {
                        $parsed = \Carbon\Carbon::parse($dateValue);
                        return $parsed->format($format);
                    } catch (\Exception $e) {
                        // If all parsing fails, return the original value
                        return $dateValue;
                    }
                } catch (\Exception $e) {
                    // If all parsing fails, return the original value
                    return $dateValue;
                }
            }
        }
        // Helper function to parse comma-separated values and convert to float
                            if (!function_exists('parseNumericValue')) {
                                function parseNumericValue($value) {
                                    // Handle null, empty string, or false
                                    if ($value === null || $value === false || $value === '') return 0;
                                    
                                    // If already a number, return it
                                    if (is_numeric($value) && !is_string($value)) {
                                        return (float)$value;
                                    }
                                    
                                    // Convert to string
                                    $value = trim((string)$value);
                                    
                                    // Handle empty after trim
                                    if ($value === '' || $value === '-') return 0;
                                    
                                    // Remove all commas (thousands separators)
                                    $value = str_replace(',', '', $value);
                                    
                                    // Remove currency symbols and spaces
                                    $value = str_replace(['P', 'p', '$', '€', '£', ' ', 'R'], '', $value);
                                    
                                    // Remove any non-numeric characters except decimal point and minus sign
                                    $cleaned = preg_replace('/[^0-9.-]/', '', $value);
                                    
                                    // Handle empty result or invalid formats
                                    if (empty($cleaned) || $cleaned === '-' || $cleaned === '.' || $cleaned === '-.') return 0;
                                    
                                    // Convert to float
                                    $result = (float)$cleaned;
                                    
                                    // Check for NaN or infinity
                                    if (is_nan($result) || is_infinite($result)) return 0;
                                    
                                    return $result;
                                }
                            }
                            
                            if (!function_exists('formatNumericValue')) {
                                function formatNumericValue($value) {
                                    $num = parseNumericValue($value);
                                    return $num > 0 ? number_format($num, 2, '.', ',') : '';
                                }
                            }
                            
                            $isCarCoverage = ($coverages->coverage->s_CoverageCode == "CONTRACTORSALLRISKS" || $coverages->coverage->s_CoverageCode == "CAR");
                            $isEarCoverage = ($coverages->coverage->s_CoverageCode == "ERECTIONALLRISKS" || $coverages->coverage->s_CoverageCode == "EAR");
                            $isParCoverage = ($coverages->coverage->s_CoverageCode == "PLANTALLRISKS" || $coverages->coverage->s_CoverageCode == "PAR");
        @endphp
        
                        @if($isCarCoverage && $coverages->carCoverage)
                        @include('v2/livewire/pdf/contractors_all_risk')
                        @elseif($isEarCoverage && $coverages->earCoverage)
                        @include('v2/livewire/pdf/erection_all_risk')
                        @elseif($isParCoverage && $coverages->parCoverage)
                        @include('v2/livewire/pdf/plant_all_risk')
                        @endif
                       