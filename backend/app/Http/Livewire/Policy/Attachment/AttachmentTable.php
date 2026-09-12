<?php

namespace AlphaDirect\Http\Livewire\Policy\Attachment;

use AlphaDirect\Ledger;
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Policy;
use AlphaDirect\PolicyAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class AttachmentTable extends DataTableComponent
{
    public Policy $policy;

    protected $listeners = [
        'deleteRecord'
    ];

    public function boot(): void
    {
        config(['livewire-tables.theme' => 'bootstrap-5']);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make('Name','name')
			->sortable(),
            Column::make('Document Type','type')
			->sortable(),

            Column::make('Attachment','attachment')
            ->sortable()
            ->searchable()
            ->format(function($value, $row) {
               // dd($row);
                if ($row->attachment == NULL)
                {
                    $images = '<img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="200px" height="auto" >';
                } else
                    {
                    $images = '';

                    // Safely unserialize attachment data
                    $attachmentData = null;
                    try {
                        $attachmentData = unserialize($row->attachment, ['allowed_classes' => false]);
                        // Check if unserialize returned false (error) or if data is not an array
                        if ($attachmentData === false || !is_array($attachmentData)) {
                            // Try to decode as JSON if unserialize fails
                            $jsonData = json_decode($row->attachment, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                                $attachmentData = $jsonData;
                            } else {
                                // If both fail, treat as single file path
                                $attachmentData = [$row->attachment];
                            }
                        }
                    } catch (\Exception $e) {
                        // If unserialize throws an exception, try JSON or treat as single file
                        $jsonData = json_decode($row->attachment, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                            $attachmentData = $jsonData;
                        } else {
                            // Fallback: treat as single file path
                            $attachmentData = [$row->attachment];
                        }
                    }

                    foreach ($attachmentData as $k => $file)
                    {
                        $images .= '
                                        <div class="kt-avatar" style="clear: left; display: inline-block" id="attachment_'. $row['id']. $k.'">
                                            <a href="' . \AlphaDirect\Helper::getCloudFrontURL($file) . '" target="_blank" download=""  >';
                        $images .=  '<img style="width: 100px;height: 100px;" src="';

                        if (pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                        {
                            $images .=  asset('images/pdf.ico') ;
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                        {
                            $images .=  asset('images/word.ico') ;
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                        {
                            $images .=  asset('images/excel.png') ;
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                        {
                            $images .=  \AlphaDirect\Helper::getCloudFrontURL($file) ;
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'gif')
                        {
                            $images .=  str_replace(env('AWS_URL'), env('AWS_CLOUDFRONT'), Storage::disk('s3')->url($file)) ;
                        }
                        else
                        {
                            $images .=  asset('images/doc.png');
                        }

                    $images .= '">';
                    $images .= '</a>';
                    $images .= '<span class="kt-avatar__cancel removeAttachment" data-toggle="kt-tooltip" title="delete attachment" data-claim-id="'. $row['id'].'"  data-id="'. $k.'" style="display:block; top: -5px; bottom: auto;text-align: center;"> <i class="fa fa-times" style=""></i>
                                </span>
                            </div>
                        ';
                    }
                    $images .= "</a>";
                }
                return $images;
            })
            ->html(),

            Column::make('Action','id')
            ->format(function($value,$row){
                return view('v2.tables.route-action', [
                    'id' => $row->id,
                    'action'=>$this->actionButton($row)
                ]);
            })->html(),
        ];
    }

    public function builder(): Builder
    {
        return PolicyAttachments::where('policy_id', $this->policy->id);
    }

    protected function actionButton($d){
        return [
            'delete' => [
                // 'fun' => 'deleteRuleGroup',
                // 'arg' => $d->id
                'arg' => "'deleteRecord',$d->id",
                'onClick' => "deleteRow",
            ]
        ];
    }

    public function deleteRecord($id)
    {
        if (PolicyAttachments::find($id)->delete()){
            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Deleted Successfully!']);
        }else{
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Something Went Wrong']);
        }
    }

}
