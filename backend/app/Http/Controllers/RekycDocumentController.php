<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Services\RekycDocumentService;
use AlphaDirect\Services\RekycOCRService;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycDocument;
use AlphaDirect\Helper;

class RekycDocumentController extends Controller
{
    protected $documentService;
    protected $ocrService;

    public function __construct(RekycDocumentService $documentService, RekycOCRService $ocrService)
    {
        $this->documentService = $documentService;
        $this->ocrService = $ocrService;
    }

    /**
     * Upload document for Re-KYC
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'link_id' => 'required|exists:rekyc_links,id',
            'document_type' => 'required|in:omang,passport,drivers_license,utility_bill,bank_statement,other',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('document');
            $linkId = $request->link_id;
            $documentType = $request->document_type;
            $metadata = $request->get('metadata', []);

            $result = $this->documentService->processDocument(
                $file,
                $linkId,
                $documentType,
                $metadata
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('Document upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Document upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get documents for a link
     */
    public function getDocuments(Request $request, $linkId): JsonResponse
    {
        try {
            $link = RekycLink::findOrFail($linkId);
            $documents = $this->documentService->getDocumentsForLink($linkId);

            return response()->json([
                'success' => true,
                'data' => [
                    'link_id' => $linkId,
                    'customer_id' => $link->customer_id,
                    'documents' => $documents
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get documents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific document
     */
    public function getDocument(Request $request, $documentId): JsonResponse
    {
        try {
            $document = $this->documentService->getDocument($documentId);
            
            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'file_name' => app(\AlphaDirect\Services\RekycSecurityService::class)->decryptData($document->file_name),
                    'file_size' => $document->file_size,
                    'mime_type' => $document->mime_type,
                    'status' => $document->status,
                    'file_path' => Helper::getCloudFrontURL($document->file_path),
                    'uploaded_at' => $document->uploaded_at,
                    'verified_at' => $document->verified_at,
                    'ocr_data' => $document->ocr_data ? 
                        json_decode(app(\AlphaDirect\Services\RekycSecurityService::class)->decryptData($document->ocr_data), true) : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get document: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify document
     */
    public function verifyDocument(Request $request, $documentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected,pending',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->documentService->verifyDocument(
                $documentId,
                $request->status,
                $request->notes
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('Document verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Document verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete document
     */
    public function deleteDocument(Request $request, $documentId): JsonResponse
    {
        try {
            $result = $this->documentService->deleteDocument($documentId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('Document deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Document deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate OCR data against customer data
     */
    public function validateOCRData(Request $request, $documentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_data' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $document = RekycDocument::findOrFail($documentId);
            
            if (!$document->ocr_data) {
                return response()->json([
                    'success' => false,
                    'message' => 'No OCR data available for this document'
                ], 400);
            }

            $ocrData = json_decode(app(\AlphaDirect\Services\RekycSecurityService::class)->decryptData($document->ocr_data), true);
            $validation = $this->ocrService->validateExtractedData(
                $ocrData['extracted_data'] ?? [],
                $request->customer_data
            );

            return response()->json([
                'success' => true,
                'data' => $validation
            ]);

        } catch (\Exception $e) {
            Log::error('OCR validation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'OCR validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'nullable|exists:rekyc_campaigns,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $statistics = $this->documentService->getDocumentStatistics($request->campaign_id);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get document statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get document statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download document (secure) - API version
     */
    public function downloadDocument(Request $request, $documentId): JsonResponse
    {
        try {
            $document = RekycDocument::findOrFail($documentId);
            $filePath = app(\AlphaDirect\Services\RekycSecurityService::class)->decryptData($document->file_path);
            
            if (!Storage::disk('private')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $downloadUrl = Storage::disk('private')->temporaryUrl($filePath, now()->addMinutes(30));

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $downloadUrl,
                    'expires_at' => now()->addMinutes(30)->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Document download failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Document download failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download document (secure) - Web version
     */
    public function downloadDocumentWeb(Request $request, $documentId)
    {
        try {
            $document = RekycDocument::findOrFail($documentId);
            $filePath = app(\AlphaDirect\Services\RekycSecurityService::class)->decryptData($document->file_path);
            
            // Check if file exists in S3
            if (!Storage::disk('s3')->exists($filePath)) {
                abort(404, 'File not found');
            }

            // Get file content from S3
            $fileContent = Storage::disk('s3')->get($filePath);
            $mimeType = $document->mime_type ?: Storage::disk('s3')->mimeType($filePath);
            
            // Return the image with proper headers
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . app(\AlphaDirect\Services\RekycSecurityService::class)->decryptData($document->file_name) . '"')
                ->header('Cache-Control', 'public, max-age=3600');

        } catch (\Exception $e) {
            Log::error('Document download failed: ' . $e->getMessage());
            abort(500, 'Document download failed');
        }
    }

    /**
     * Get document thumbnail
     */
    public function getThumbnail(Request $request, $documentId): JsonResponse
    {
        try {
            $document = RekycDocument::findOrFail($documentId);
            $filePath = app(\AlphaDirect\Services\RekycSecurityService::class)->decryptData($document->file_path);
            $thumbnailPath = str_replace('.', '_thumb.', $filePath);
            
            if (!Storage::disk('private')->exists($thumbnailPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thumbnail not available'
                ], 404);
            }

            $thumbnailUrl = Storage::disk('private')->temporaryUrl($thumbnailPath, now()->addMinutes(30));

            return response()->json([
                'success' => true,
                'data' => [
                    'thumbnail_url' => $thumbnailUrl,
                    'expires_at' => now()->addMinutes(30)->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Thumbnail retrieval failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Thumbnail retrieval failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
