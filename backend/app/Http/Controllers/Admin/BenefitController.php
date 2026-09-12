<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Models\Benefit;
use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use AlphaDirect\Models\BenefitType;
use AlphaDirect\Config;

class BenefitController extends Controller
{
    public function index()
    {
        $benefits = Benefit::all();
        return view('admin.benefits.index', compact('benefits'));
    }

    public function create()
    {
        $types = json_decode(Config::where('key', 'benefit_types')->first()->value);
        return view('admin.benefits.create', compact('types'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'tag' => 'required|string|max:255',
                'type' => 'nullable|string|max:255',
                'status' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'price' => 'nullable|numeric',
                'point' => 'nullable|integer',
            ]);

            $data = $validated;
            if (isset($data['status'])) {
                $data['status'] = $data['status'] == '1' ? 1 : 0;
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $name = time() . '_' . $file->getClientOriginalName();
                $filePath = 'Benefits/' . $name;
                
                // Upload image to S3
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $data['image'] = $filePath;
            }

            Benefit::create($data);
            return redirect()->route('benefits.index')->with('success', 'Benefit created successfully.');
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error creating benefit: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show($id)
    {
        $benefit = Benefit::findOrFail($id);
        return view('admin.benefits.show', compact('benefit'));
    }

    public function edit($id)
    {
        $benefit = Benefit::findOrFail($id);
        //$types = config('benefit_types');
        $types = json_decode(Config::where('key', 'benefit_types')->first()->value);
       //dd($types);
        return view('admin.benefits.edit', compact('benefit', 'types'));
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'tag' => 'required|string|max:255',
                'type' => 'nullable|string|max:255',
                'status' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'price' => 'nullable|numeric',
                'point' => 'nullable|integer',
            ]);

            $data = $validated;
            if (isset($data['status'])) {
                $data['status'] = $data['status'] == '1' ? 1 : 0;
            }
            $benefit = Benefit::findOrFail($id);

            // Handle image upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $name = time() . '_' . $file->getClientOriginalName();
                $filePath = 'Benefits/' . $name;
                
                // Upload new image to S3
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $data['image'] = $filePath;
            } else {
                // Keep existing image if no new image is uploaded
                $data['image'] = $benefit->image;
            }

            $benefit->update($data);
            return redirect()->route('benefits.index')->with('success', 'Benefit updated successfully.');
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error updating benefit: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        $benefit = Benefit::findOrFail($id);
        $benefit->delete();
        return redirect()->route('benefits.index')->with('success', 'Benefit deleted successfully.');
    }
} 