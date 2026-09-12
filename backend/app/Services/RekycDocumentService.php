<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\RekycDocument;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Exception;

class RekycDocumentService
{
    protected $securityService;
    protected $auditService;

    public function __construct(RekycSecurityService $securityService, RekycAuditService $auditService)
    {
        $this->securityService = $securityService;
        $this->auditService = $auditService;
    }

    /**
     * Process uploaded document for Re-KYC
     */
    public function processDocument(UploadedFile $file, int $linkId, string $documentType, array $metadata = []): array
    {
        try {
            $link = RekycLink::with(['customer', 'campaign'])->findOrFail($linkId);
            
            // Validate file
            $validation = $this->validateDocument($file, $documentType);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Document validation failed',
                    'errors' => $validation['errors']
                ];
            }

            // Generate secure file path
            $filePath = $this->generateSecureFilePath($link->customer_id, $documentType);
            
            // Store file securely
            $storedPath = $this->storeDocument($file, $filePath);
            
            // Generate file hash for integrity
            $fileHash = $this->generateFileHash($storedPath);
            
            // Process with OCR if enabled
            $ocrData = $this->processWithOCR($storedPath, $documentType);
            
            // Create document record
            $document = $this->createDocumentRecord($link, $documentType, $storedPath, $fileHash, $ocrData, $metadata);
            
            // Log audit trail
            $this->auditService->logActivity(
                $link->customer_id,
                'document_uploaded',
                'Document uploaded successfully',
                [
                    'document_type' => $documentType,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'ocr_enabled' => config('rekyc.ocr.enabled', false)
                ]
            );

            return [
                'success' => true,
                'message' => 'Document processed successfully',
                'data' => [
                    'document_id' => $document->id,
                    'file_path' => $this->securityService->encryptData($storedPath),
                    'file_hash' => $fileHash,
                    'ocr_data' => $ocrData,
                    'status' => $document->status
                ]
            ];

        } catch (Exception $e) {
            Log::error('Document processing failed: ' . $e->getMessage());
            
            $this->auditService->logActivity(
                $linkId,
                'document_upload_failed',
                'Document upload failed',
                ['error' => $e->getMessage()]
            );

            return [
                'success' => false,
                'message' => 'Document processing failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate uploaded document
     */
    protected function validateDocument(UploadedFile $file, string $documentType): array
    {
        $errors = [];
        
        // Check file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            $errors[] = 'File size exceeds 10MB limit';
        }

        // Check file type
        $allowedTypes = $this->getAllowedFileTypes($documentType);
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes);
        }

        // Check file extension
        $allowedExtensions = $this->getAllowedExtensions($documentType);
        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions)) {
            $errors[] = 'Invalid file extension. Allowed extensions: ' . implode(', ', $allowedExtensions);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get allowed file types for document type
     */
    protected function getAllowedFileTypes(string $documentType): array
    {
        $types = [
            'omang' => ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'],
            'passport' => ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'],
            'drivers_license' => ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'],
            'utility_bill' => ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'],
            'bank_statement' => ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'],
            'other' => ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf']
        ];

        return $types[$documentType] ?? $types['other'];
    }

    /**
     * Get allowed file extensions for document type
     */
    protected function getAllowedExtensions(string $documentType): array
    {
        return ['jpg', 'jpeg', 'png', 'pdf'];
    }

    /**
     * Generate secure file path
     */
    protected function generateSecureFilePath(int $customerId, string $documentType): string
    {
        $timestamp = now()->format('Y/m/d');
        $randomString = Str::random(32);
        
        return "rekyc/documents/{$timestamp}/{$customerId}/{$documentType}_{$randomString}";
    }

    /**
     * Store document securely
     */
    protected function storeDocument(UploadedFile $file, string $filePath): string
    {
        // Store original file
        $storedPath = $file->storeAs($filePath, $file->getClientOriginalName(), 'private');
        
        // Create thumbnail for images
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $this->createThumbnail($storedPath);
        }

        return $storedPath;
    }

    /**
     * Create thumbnail for image documents
     */
    protected function createThumbnail(string $filePath): void
    {
        try {
            $fullPath = storage_path('app/private/' . $filePath);
            $thumbnailPath = str_replace('.', '_thumb.', $filePath);
            
            Image::make($fullPath)
                ->resize(300, 300, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->save(storage_path('app/private/' . $thumbnailPath));
                
        } catch (Exception $e) {
            Log::warning('Failed to create thumbnail: ' . $e->getMessage());
        }
    }

    /**
     * Generate file hash for integrity verification
     */
    protected function generateFileHash(string $filePath): string
    {
        $content = Storage::disk('private')->get($filePath);
        return hash('sha256', $content);
    }

    /**
     * Process document with OCR
     */
    protected function processWithOCR(string $filePath, string $documentType): ?array
    {
        if (!config('rekyc.ocr.enabled', false)) {
            return null;
        }

        try {
            $ocrService = app(RekycOCRService::class);
            return $ocrService->extractData($filePath, $documentType);
        } catch (Exception $e) {
            Log::warning('OCR processing failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create document record in database
     */
    protected function createDocumentRecord(RekycLink $link, string $documentType, string $filePath, string $fileHash, ?array $ocrData, array $metadata): RekycDocument
    {
        return RekycDocument::create([
            'link_id' => $link->id,
            'customer_id' => $link->customer_id,
            'document_type' => $documentType,
            'file_path' => $this->securityService->encryptData($filePath),
            'file_name' => $this->securityService->encryptData(basename($filePath)),
            'file_hash' => $fileHash,
            'file_size' => Storage::disk('private')->size($filePath),
            'mime_type' => Storage::disk('private')->mimeType($filePath),
            'ocr_data' => $ocrData ? $this->securityService->encryptData(json_encode($ocrData)) : null,
            'metadata' => $metadata,
            'status' => $ocrData ? 'pending_verification' : 'uploaded',
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Get document by ID
     */
    public function getDocument(int $documentId): ?RekycDocument
    {
        return RekycDocument::find($documentId);
    }

    /**
     * Get documents for a link
     */
    public function getDocumentsForLink(int $linkId): array
    {
        $documents = RekycDocument::where('link_id', $linkId)->get();
        
        return $documents->map(function ($document) {
            return [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'file_name' => $this->securityService->decryptData($document->file_name),
                'file_size' => $document->file_size,
                'mime_type' => $document->mime_type,
                'status' => $document->status,
                'uploaded_at' => $document->uploaded_at,
                'verified_at' => $document->verified_at,
                'ocr_data' => $document->ocr_data ? 
                    json_decode($this->securityService->decryptData($document->ocr_data), true) : null
            ];
        })->toArray();
    }

    /**
     * Verify document
     */
    public function verifyDocument(int $documentId, string $status, ?string $notes = null): array
    {
        try {
            $document = RekycDocument::findOrFail($documentId);
            
            $document->update([
                'status' => $status,
                'verified_at' => now(),
                'verification_notes' => $notes
            ]);

            $this->auditService->logActivity(
                $document->customer_id,
                'document_verified',
                'Document verification completed',
                [
                    'document_id' => $documentId,
                    'status' => $status,
                    'notes' => $notes
                ]
            );

            return [
                'success' => true,
                'message' => 'Document verification completed',
                'data' => $document
            ];

        } catch (Exception $e) {
            Log::error('Document verification failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Document verification failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete document
     */
    public function deleteDocument(int $documentId): array
    {
        try {
            $document = RekycDocument::findOrFail($documentId);
            $filePath = $this->securityService->decryptData($document->file_path);
            
            // Delete file from storage
            Storage::disk('private')->delete($filePath);
            
            // Delete thumbnail if exists
            $thumbnailPath = str_replace('.', '_thumb.', $filePath);
            Storage::disk('private')->delete($thumbnailPath);
            
            // Delete database record
            $document->delete();

            $this->auditService->logActivity(
                $document->customer_id,
                'document_deleted',
                'Document deleted',
                ['document_id' => $documentId]
            );

            return [
                'success' => true,
                'message' => 'Document deleted successfully'
            ];

        } catch (Exception $e) {
            Log::error('Document deletion failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Document deletion failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get document statistics
     */
    public function getDocumentStatistics(int $campaignId = null): array
    {
        $query = RekycDocument::query();
        
        if ($campaignId) {
            $query->whereHas('link', function ($q) use ($campaignId) {
                $q->where('campaign_id', $campaignId);
            });
        }

        $total = $query->count();
        $byType = $query->groupBy('document_type')
            ->selectRaw('document_type, count(*) as count')
            ->pluck('count', 'document_type');
        
        $byStatus = $query->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status');

        return [
            'total_documents' => $total,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'ocr_enabled' => config('rekyc.ocr.enabled', false)
        ];
    }
}
