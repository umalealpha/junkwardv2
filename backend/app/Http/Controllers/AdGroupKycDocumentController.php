<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdGroupKycDocumentController extends Controller
{
    /**
     * Upload document
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Document upload not implemented yet'
        ], 501);
    }

    /**
     * Get documents for a link
     */
    public function getDocuments(Request $request, $linkId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Document retrieval not implemented yet'
        ], 501);
    }

    /**
     * Get specific document
     */
    public function getDocument(Request $request, $documentId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Document details not implemented yet'
        ], 501);
    }

    /**
     * Verify document
     */
    public function verifyDocument(Request $request, $documentId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Document verification not implemented yet'
        ], 501);
    }

    /**
     * Delete document
     */
    public function deleteDocument(Request $request, $documentId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Document deletion not implemented yet'
        ], 501);
    }

    /**
     * Validate OCR data
     */
    public function validateOCRData(Request $request, $documentId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'OCR validation not implemented yet'
        ], 501);
    }

    /**
     * Get document statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Document statistics not implemented yet'
        ], 501);
    }

    /**
     * Download document
     */
    public function downloadDocument(Request $request, $documentId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Document download not implemented yet'
        ], 501);
    }

    /**
     * Get document thumbnail
     */
    public function getThumbnail(Request $request, $documentId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Document thumbnail not implemented yet'
        ], 501);
    }
}