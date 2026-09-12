<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\DeduplicationChecks;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeduplicationStatsService
{
    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats(): array
    {
        $totalChecks = DeduplicationChecks::count();
        $activeChecks = DeduplicationChecks::where('status', 'active')->count();
        $completedChecks = DeduplicationChecks::where('status', 'completed')->count();
        $suspendedChecks = DeduplicationChecks::where('status', 'suspended')->count();
        $expiredChecks = DeduplicationChecks::where('status', 'expired')->count();
        
        $uploadedDocuments = DeduplicationChecks::where('document_upload_status', 'uploaded')->count();
        $verifiedDocuments = DeduplicationChecks::where('manual_verification_status', 'approved')->count();
        $pendingVerification = DeduplicationChecks::where('manual_verification_status', 'pending')->count();
        $rejectedDocuments = DeduplicationChecks::where('manual_verification_status', 'rejected')->count();
        
        $completionRate = $totalChecks > 0 ? round(($completedChecks / $totalChecks) * 100, 1) : 0;
        $uploadRate = $totalChecks > 0 ? round(($uploadedDocuments / $totalChecks) * 100, 1) : 0;
        $verificationRate = $uploadedDocuments > 0 ? round(($verifiedDocuments / $uploadedDocuments) * 100, 1) : 0;
        
        // Calculate average response time (from link creation to upload)
        $avgResponseTime = DeduplicationChecks::whereNotNull('link_opened_at')
            ->whereNotNull('bank_statement_upload_url')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, link_opened_at)) as avg_time')
            ->value('avg_time') ?? 0;

        // Calculate average processing time (from upload to verification)
        $avgProcessingTime = DeduplicationChecks::whereNotNull('bank_statement_upload_url')
            ->whereNotNull('verified_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, updated_at, verified_at)) as avg_time')
            ->value('avg_time') ?? 0;

        // Get today's statistics
        $todayChecks = DeduplicationChecks::whereDate('created_at', Carbon::today())->count();
        $todayUploads = DeduplicationChecks::whereDate('updated_at', Carbon::today())
            ->where('document_upload_status', 'uploaded')->count();
        $todayVerifications = DeduplicationChecks::whereDate('verified_at', Carbon::today())
            ->where('manual_verification_status', 'approved')->count();

        // Get weekly statistics
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $weeklyChecks = DeduplicationChecks::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $weeklyUploads = DeduplicationChecks::whereBetween('updated_at', [$weekStart, $weekEnd])
            ->where('document_upload_status', 'uploaded')->count();

        return [
            'total_checks' => $totalChecks,
            'active_checks' => $activeChecks,
            'completed_checks' => $completedChecks,
            'suspended_checks' => $suspendedChecks,
            'expired_checks' => $expiredChecks,
            'uploaded_documents' => $uploadedDocuments,
            'verified_documents' => $verifiedDocuments,
            'pending_verification' => $pendingVerification,
            'rejected_documents' => $rejectedDocuments,
            'completion_rate' => $completionRate,
            'upload_rate' => $uploadRate,
            'verification_rate' => $verificationRate,
            'avg_response_time' => round($avgResponseTime, 1),
            'avg_processing_time' => round($avgProcessingTime, 1),
            'today_checks' => $todayChecks,
            'today_uploads' => $todayUploads,
            'today_verifications' => $todayVerifications,
            'weekly_checks' => $weeklyChecks,
            'weekly_uploads' => $weeklyUploads,
        ];
    }

    /**
     * Get statistics for a specific check
     */
    public function getCheckStats(DeduplicationChecks $check): array
    {
        $daysSinceCreated = $check->created_at->diffInDays(Carbon::now());
        $daysSinceOpened = $check->link_opened_at ? $check->link_opened_at->diffInDays(Carbon::now()) : null;
        $daysSinceUploaded = $check->bank_statement_upload_url ? $check->updated_at->diffInDays(Carbon::now()) : null;
        $daysSinceVerified = $check->verified_at ? $check->verified_at->diffInDays(Carbon::now()) : null;

        return [
            'days_since_created' => $daysSinceCreated,
            'days_since_opened' => $daysSinceOpened,
            'days_since_uploaded' => $daysSinceUploaded,
            'days_since_verified' => $daysSinceVerified,
            'otp_attempts' => $check->otp_attempts,
            'is_expired' => $check->isExpired(),
            'has_document' => !empty($check->bank_statement_upload_url),
            'file_size' => $check->bank_statement_file_size,
            'verification_status' => $check->manual_verification_status,
            'is_suspended' => $check->is_suspended,
            'suspension_days' => $check->suspended_at ? $check->suspended_at->diffInDays(Carbon::now()) : null,
        ];
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities(int $limit = 10): \Illuminate\Support\Collection
    {
        return DeduplicationChecks::with(['customer'])
            ->where(function($query) {
                $query->whereNotNull('link_opened_at')
                    ->orWhere('document_upload_status', 'uploaded')
                    ->orWhere('manual_verification_status', 'approved')
                    ->orWhere('manual_verification_status', 'rejected');
            })
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($check) {
                $activity = 'Check created';
                $icon = 'plus-circle';
                $color = 'primary';
                
                if ($check->manual_verification_status === 'rejected') {
                    $activity = 'Document rejected';
                    $icon = 'times-circle';
                    $color = 'danger';
                } elseif ($check->manual_verification_status === 'approved') {
                    $activity = 'Document verified';
                    $icon = 'check-double';
                    $color = 'success';
                } elseif ($check->document_upload_status === 'uploaded') {
                    $activity = 'Document uploaded';
                    $icon = 'upload';
                    $color = 'info';
                } elseif ($check->link_opened_at) {
                    $activity = 'Link opened';
                    $icon = 'eye';
                    $color = 'warning';
                }

                return (object) [
                    'id' => $check->id,
                    'activity' => $activity,
                    'icon' => $icon,
                    'color' => $color,
                    'customer_name' => $check->customer ? $check->customer->firstName . ' ' . $check->customer->lastName : 'Unknown',
                    'created_at' => $check->updated_at
                ];
            });
    }

    /**
     * Get activity logs for a specific check
     */
    public function getCheckActivityLogs(DeduplicationChecks $check): \Illuminate\Support\Collection
    {
        $logs = [];
        
        if ($check->created_at) {
            $logs[] = [
                'action' => 'created',
                'description' => 'Deduplication check created',
                'timestamp' => $check->created_at,
                'icon' => 'plus-circle'
            ];
        }
        
        if ($check->link_opened_at) {
            $logs[] = [
                'action' => 'opened',
                'description' => 'Link opened by customer',
                'timestamp' => $check->link_opened_at,
                'icon' => 'eye'
            ];
        }
        
        if ($check->otp_verified_at) {
            $logs[] = [
                'action' => 'otp_verified',
                'description' => 'OTP verified successfully',
                'timestamp' => $check->otp_verified_at,
                'icon' => 'check-circle'
            ];
        }
        
        if ($check->document_upload_status === 'uploaded') {
            $logs[] = [
                'action' => 'document_uploaded',
                'description' => 'Bank statement uploaded',
                'timestamp' => $check->updated_at,
                'icon' => 'upload'
            ];
        }
        
        if ($check->verified_at) {
            $logs[] = [
                'action' => 'verified',
                'description' => 'Document verified by ' . ($check->verifier ? $check->verifier->name : 'Admin'),
                'timestamp' => $check->verified_at,
                'icon' => 'check-double'
            ];
        }

        if ($check->is_suspended && $check->suspended_at) {
            $logs[] = [
                'action' => 'suspended',
                'description' => 'Check suspended - ' . ($check->suspension_reason ?? 'No reason provided'),
                'timestamp' => $check->suspended_at,
                'icon' => 'pause-circle'
            ];
        }

        return collect($logs)->sortByDesc('timestamp');
    }

    /**
     * Get monthly statistics for charts
     */
    public function getMonthlyStats(int $months = 12): array
    {
        $stats = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            
            $monthStats = [
                'month' => $date->format('M Y'),
                'checks_created' => DeduplicationChecks::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                'documents_uploaded' => DeduplicationChecks::whereBetween('updated_at', [$monthStart, $monthEnd])
                    ->where('document_upload_status', 'uploaded')->count(),
                'documents_verified' => DeduplicationChecks::whereBetween('verified_at', [$monthStart, $monthEnd])
                    ->where('manual_verification_status', 'approved')->count(),
                'checks_completed' => DeduplicationChecks::whereBetween('updated_at', [$monthStart, $monthEnd])
                    ->where('status', 'completed')->count(),
            ];
            
            $stats[] = $monthStats;
        }
        
        return $stats;
    }

    /**
     * Get status distribution
     */
    public function getStatusDistribution(): array
    {
        return [
            'active' => DeduplicationChecks::where('status', 'active')->count(),
            'completed' => DeduplicationChecks::where('status', 'completed')->count(),
            'suspended' => DeduplicationChecks::where('status', 'suspended')->count(),
            'expired' => DeduplicationChecks::where('status', 'expired')->count(),
            'cancelled' => DeduplicationChecks::where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Get document status distribution
     */
    public function getDocumentStatusDistribution(): array
    {
        return [
            'pending' => DeduplicationChecks::where('document_upload_status', 'pending')->count(),
            'uploaded' => DeduplicationChecks::where('document_upload_status', 'uploaded')->count(),
            'processing' => DeduplicationChecks::where('document_upload_status', 'processing')->count(),
            'verified' => DeduplicationChecks::where('document_upload_status', 'verified')->count(),
            'rejected' => DeduplicationChecks::where('document_upload_status', 'rejected')->count(),
            'failed' => DeduplicationChecks::where('document_upload_status', 'failed')->count(),
        ];
    }

    /**
     * Get verification status distribution
     */
    public function getVerificationStatusDistribution(): array
    {
        return [
            'pending' => DeduplicationChecks::where('manual_verification_status', 'pending')->count(),
            'approved' => DeduplicationChecks::where('manual_verification_status', 'approved')->count(),
            'rejected' => DeduplicationChecks::where('manual_verification_status', 'rejected')->count(),
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        // Average time from creation to link opening
        $avgTimeToOpen = DeduplicationChecks::whereNotNull('link_opened_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, link_opened_at)) as avg_time')
            ->value('avg_time') ?? 0;

        // Average time from link opening to document upload
        $avgTimeToUpload = DeduplicationChecks::whereNotNull('link_opened_at')
            ->whereNotNull('bank_statement_upload_url')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, link_opened_at, updated_at)) as avg_time')
            ->value('avg_time') ?? 0;

        // Average time from upload to verification
        $avgTimeToVerify = DeduplicationChecks::whereNotNull('bank_statement_upload_url')
            ->whereNotNull('verified_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, updated_at, verified_at)) as avg_time')
            ->value('avg_time') ?? 0;

        // Success rates
        $totalWithLinks = DeduplicationChecks::whereNotNull('link_opened_at')->count();
        $totalWithUploads = DeduplicationChecks::whereNotNull('bank_statement_upload_url')->count();
        $totalWithVerification = DeduplicationChecks::whereNotNull('verified_at')->count();

        $linkOpenRate = $totalWithLinks > 0 ? round(($totalWithLinks / DeduplicationChecks::count()) * 100, 1) : 0;
        $uploadRate = $totalWithUploads > 0 ? round(($totalWithUploads / $totalWithLinks) * 100, 1) : 0;
        $verificationRate = $totalWithVerification > 0 ? round(($totalWithVerification / $totalWithUploads) * 100, 1) : 0;

        return [
            'avg_time_to_open_minutes' => round($avgTimeToOpen, 1),
            'avg_time_to_upload_minutes' => round($avgTimeToUpload, 1),
            'avg_time_to_verify_minutes' => round($avgTimeToVerify, 1),
            'link_open_rate' => $linkOpenRate,
            'upload_rate' => $uploadRate,
            'verification_rate' => $verificationRate,
            'total_processed' => DeduplicationChecks::count(),
            'total_with_links' => $totalWithLinks,
            'total_with_uploads' => $totalWithUploads,
            'total_with_verification' => $totalWithVerification,
        ];
    }

    /**
     * Get bank-wise statistics
     */
    public function getBankWiseStats(): array
    {
        return DeduplicationChecks::select('bank_name', DB::raw('count(*) as count'))
            ->whereNotNull('bank_name')
            ->groupBy('bank_name')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'bank_name' => $item->bank_name,
                    'count' => $item->count,
                    'percentage' => round(($item->count / DeduplicationChecks::whereNotNull('bank_name')->count()) * 100, 1)
                ];
            })
            ->toArray();
    }

    /**
     * Get daily statistics for the last 30 days
     */
    public function getDailyStats(int $days = 30): array
    {
        $stats = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            
            $dayStats = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('D'),
                'checks_created' => DeduplicationChecks::whereBetween('created_at', [$dayStart, $dayEnd])->count(),
                'links_opened' => DeduplicationChecks::whereBetween('link_opened_at', [$dayStart, $dayEnd])->count(),
                'documents_uploaded' => DeduplicationChecks::whereBetween('updated_at', [$dayStart, $dayEnd])
                    ->where('document_upload_status', 'uploaded')->count(),
                'documents_verified' => DeduplicationChecks::whereBetween('verified_at', [$dayStart, $dayEnd])
                    ->where('manual_verification_status', 'approved')->count(),
            ];
            
            $stats[] = $dayStats;
        }
        
        return $stats;
    }
}
