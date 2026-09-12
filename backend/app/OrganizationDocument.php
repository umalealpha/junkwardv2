<?php

namespace AlphaDirect;

use AlphaDirect\OrganizationDocument as organizationdoc;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Yajra\DataTables\DataTables;

class OrganizationDocument extends Model
{
    //
    protected $fillable = ['title', 'slug', 'slug', 'text', 'region', 'createdBy'];

    protected $tables = 'organization_documents';

    protected function CreatedBy()
    {
        return $this->belongsTo('AlphaDirect\User', 'createdBy');
    }

    protected function RegionDocument()
    {
        return $this->belongsTo('AlphaDirect\Region', 'region');
    }

    public function scopeorgaizationDocumentData($query)
    {
        try {
            //code...
            $documents = OrganizationDocument::get(['id', 'title', 'slug', 'text', 'createdBy', 'region', 'created_at']);
            return DataTables::of($documents)

                ->editColumn('created_at', function ($documents) {
                    return $documents->created_at->diffForHumans();
                })

                ->editColumn('createdBy', function ($documents) {
                    return $documents->CreatedBy->firstName;
                })

                ->editColumn('region', function ($documents) {
                    return $documents->RegionDocument->name;
                })

                ->addColumn('actions', function ($documents) {
                    $actions = '<a href="' . route('organizationdocument.edit', $documents->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                            <i class="la la-edit"></i>
                        </a>
                        <a href="" value="' . $documents->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                            <i class="la la-trash"></i>
                        </a>';
                    return $actions;
                })
                ->rawColumns(['actions'])
                ->make(true);
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function storeDocument($document)
    {
        try {

            $organizationDocument = new organizationdoc();
            $organizationDocument->title = $document->title;
            $organizationDocument->slug = $document->slug;
            $organizationDocument->text = $document->text;
            $organizationDocument->region = $document->region;
            $organizationDocument->createdBy = auth()->user()->id;
            $organizationDocument->save();
            return response()->json(['status' => 'success'], 200); //return success response
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function updateDocument($id, $document)
    {
        try {
            $organizationDocument = OrganizationDocument::findOrFail($id);
            $organizationDocument->title = $document->title;
            $organizationDocument->slug = $document->slug;
            $organizationDocument->text = $document->text;
            $organizationDocument->save();
            return response()->json(['status' => 'success'], 200); //return success response
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage()], 500);
        }

    }

    public function destroyDocument($id)
    {
        try {
            //code...
            $organizationDocument = OrganizationDocument::where('id', $id)->first(); //get the document using ID

            if ($organizationDocument) {
                //Execute the delete operation on the specific document
                $organizationDocument->delete();
                return response()->json(['status' => 'success'], 200); //return success response
            }

        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }
}
