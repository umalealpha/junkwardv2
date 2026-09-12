<?php

namespace AlphaDirect\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;

class DeviceExport implements WithHeadings
{
    public function headings(): array
    {
        return [
            "Device Type",
            "IMEI",
            "Make",
            "Model",
            "Phone Value"
        ];
    }
}
