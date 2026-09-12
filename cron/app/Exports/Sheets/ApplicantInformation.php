<?php
namespace AlphaDirect\Exports\Sheets;

use AlphaDirect\Policy;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ApplicantInformation implements FromView,WithColumnWidths,WithStyles,WithEvents
{
    public Policy $policy;

    public function __construct($policy)
    {
        $this->policy = $policy;
    }
    public function view(): View
    {
        return view('v2.exports.applicant_information', [
            'policy' => $this->policy
        ]);
    }
//    public function collection()
//    {
//        return new Collection([
//            [
//                [],
//                ['','User detail']
//            ]
//        ]);
//    }
    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 40,
            'C' => 30,
            'D' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            'B2' => ['font' => ['bold' => true,'size'=>18],'height'=>"150"],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getDelegate()->getRowDimension('2')->setRowHeight(30);

                $drop_column = 'C';

                // set dropdown options
                $options = ['Organisation','Person'];

                // set dropdown list for first data row
                $validation = $event->sheet->getCell("{$drop_column}3")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST );
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION );
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Input error');
                $validation->setError('Value is not in list.');
                $validation->setPromptTitle('Select Entity Type');
                $validation->setPrompt('Fill below detail if Entity Type is Organisation');
                $validation->setFormula1(sprintf('"%s"',implode(',',$options)));

            },
        ];
    }
}
