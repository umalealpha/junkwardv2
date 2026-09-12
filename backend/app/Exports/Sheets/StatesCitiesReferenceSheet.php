<?php

namespace AlphaDirect\Exports\Sheets;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StatesCitiesReferenceSheet implements FromQuery, WithHeadings, WithMapping, WithColumnWidths, WithTitle, WithStyles
{
    /**
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        // Use raw SQL to get all states and cities efficiently
        // This query will return all states (even without cities) and all cities
        // LEFT JOIN ensures states without cities still appear
        // Cities are mapped with their states
        return DB::table('states as s')
            ->leftJoin('cities as c', 's.id', '=', 'c.state_id')
            ->where('s.country_id', 28)
            ->select(
                's.name as state_name',
                DB::raw('COALESCE(c.name, "") as city_name')
            )
            ->orderBy('s.name')
            ->orderByRaw('COALESCE(c.name, "")');
    }

    public function headings(): array
    {
        return [
            'District',
            'City'
        ];
    }

    public function map($row): array
    {
        if (!$row) {
            return ['', ''];
        }
        
        return [
            $row->state_name ?? '',
            $row->city_name ?? '',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
        ];
    }

    public function title(): string
    {
        return "Districts & Cities";
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

