<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

// Ekspor tabel umum: judul kolom + baris data
class TabelExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function __construct(private array $judul, private array $baris)
    {
    }

    public function array(): array
    {
        return $this->baris;
    }

    public function headings(): array
    {
        return $this->judul;
    }
}