<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class KycUploadController extends Controller
{
    public function index()
    {
        // Fetch all files under "kyc/" folder from S3
        $files = Storage::disk('s3')->files('kyc');

        // Build CloudFront URLs for each file
        $fileUrls = [];
        foreach ($files as $file) {
            $fileUrls[] = \AlphaDirect\Helper::getCloudFrontURL($file) . '?v=' . time();
        }

        return view('kyc_upload', compact('fileUrls'));
    }

    public function listFiles()
    {
        // $files = Storage::disk('s3')->files('kyc_uploads');
        // $urls = array_map(function ($file) {
        //     return Helper::getCloudFrontURL($file);
        // }, $files);

        $files = DB::table('kyc_documents')->get();
        return response()->json([
                "files" => $files
            ]);
        // return response()->json(['files' => $urls]);
    }


    public function upload(Request $request)
    {
        $uploadDir = storage_path("app/chunks/");
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName    = $request->input("fileName", "unknown");
        $chunkIndex  = intval($request->input("chunkIndex", 0));
        $totalChunks = intval($request->input("totalChunks", 1));

        $tmpFile   = $request->file("file")->getPathname();
        $chunkFile = $uploadDir . $fileName . "_part" . $chunkIndex;

        // Save current chunk locally
        move_uploaded_file($tmpFile, $chunkFile);

        // If last chunk, merge all parts
        if ($chunkIndex == $totalChunks - 1) {
            $finalPath = $uploadDir . uniqid() . "_" . basename($fileName);
            $out = fopen($finalPath, "ab");

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $uploadDir . $fileName . "_part" . $i;
                $in = fopen($chunkFile, "rb");
                stream_copy_to_stream($in, $out);
                fclose($in);
                unlink($chunkFile); // cleanup
            }
            fclose($out);

            // ✅ Upload merged file to S3
            $s3Path = "kyc_uploads/" . basename($finalPath);
            Storage::disk('s3')->put($s3Path, file_get_contents($finalPath), 'public');

            // Delete local merged file
            unlink($finalPath);

            $fullName = $request->input("full_name", "");
            $country  = $request->input("country", "");
            $idType   = $request->input("id_type", "");

            // return response()->json([
            //     "status"     => "completed",
            //     "s3_url"     => Storage::disk('s3')->url($s3Path),
            //     "full_name"  => $fullName,
            //     "country"    => $country,
            //     "id_type"    => $idType,
            // ]);

            // After uploading to S3 and before returning JSON
            DB::table('kyc_documents')->insert([
                'full_name' => $fullName,
                'country'   => $country,
                'id_type'   => $idType,
                'file_url'  => $s3Path,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                "status"     => "completed",
                "s3_url"     => $s3Path,
                "full_name"  => $fullName,
                "country"    => $country,
                "id_type"    => $idType,
            ]);

        }

        return response()->json([
            "status" => "chunk_received",
            "chunk"  => $chunkIndex,
        ]);
    }
}
