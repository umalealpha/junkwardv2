<?php

namespace AlphaDirect\Http\Traits\Excel;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class ExportTrait
{
    public static function generateDropDown(AfterSheet $event,$dropDownCell,$options){
        $validation = $event->sheet->getCell("{$dropDownCell}")->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST );
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION );
        $validation->setShowDropDown(true);
        $validation->setFormula1(sprintf('"%s"',implode(',',$options)));
    }
}
