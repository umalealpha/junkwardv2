<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ADGroupUploadedExcelFile extends Model
{
    use HasFactory;
    protected $table= "ad_group_uploaded_excel_files";
    protected $fillable = [
        'file_name',
        'file_path',
        'uploaded_by',
        'status',
        'remarks',
        'report_file',
        'invoice',
        'processed'
    ];
}
