<?php

namespace App\Imports;

use App\Models\Siswa;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SiswaImport implements ToModel, WithHeadingRow
{
    public function __construct(private int $kelasId)
    {
    }

    public function model(array $row)
    {
        if (empty($row['nama'])) {
            return null; // lewati baris kosong
        }

        return new Siswa([
            'kelas_id'       => $this->kelasId,
            'nis'            => $row['nis'] ?? null,
            'nama'           => trim($row['nama']),
            'nama_panggilan' => $row['nama_panggilan'] ?? null,
            'gender'         => strtoupper(substr($row['gender'] ?? '', 0, 1)) ?: null,
        ]);
    }
}