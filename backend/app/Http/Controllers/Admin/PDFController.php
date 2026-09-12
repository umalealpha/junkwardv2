<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
// use Webklex\PDFMerger\PDFMerger;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

class PDFController extends Controller
{
    public function index(){
        return view('admin.notes.mergePDF');
    }

    public function store(Request $request){

        $this->validate($request, [
                'filenames' => 'required',
                'filenames.*' => 'mimes:pdf'
        ]);
         if($request->hasFile('filenames')){
            $pdf = PDFMerger::init();
            foreach ($request->file('filenames') as $key => $value) {
                $pdf->addPDF($value->getPathName(), 'all');
            }
            $fileName = time().'.pdf';
            $pdf->merge();
            $pdf->save(public_path($fileName));
        }
        return response()->download(public_path($fileName));
    }


    public function pdfMerge(array $filenames){
        //dd($filenames);
        // if(isset($filenames)){
            $pdf = PDFMerger::init();
            foreach ($filenames as $value) {
                $pdf->addPDF($value, 'all');
            }
            $fileName = time().'.pdf';
            $pdf->merge();
            $pdf->save(public_path($fileName));
            $dd =Storage::disk('s3')->put($fileName, $pdf->output(), 'public');
            dd($dd);
           // return response()->download($fileName);

            // Storage::disk('s3')->put($fileName, file_get_contents($fileName), 'public');
            // return Storage::disk('s3')->download($fileName);

            // dd($fileName, file_get_contents($fileName),$dd);
        // }
        //return response()->download(public_path($fileName));
    }
}
