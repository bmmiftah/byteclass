<?php

namespace App\Models\Concerns;

trait MilikGuru
{
    /**
     * ID guru pemilik data ini.
     * - Model yang punya kolom guru_id sendiri: tidak perlu apa-apa lagi.
     * - Model lain: isi $pemilikVia dengan rantai relasi menuju Kelas, mis. 'kelas' atau 'denah.kelas'.
     * Mengembalikan null bila pemiliknya sudah tidak ada (mis. kelasnya terhapus).
     */
    public function pemilikId(): ?int
    {
        $obj = $this;

        if (property_exists($this, 'pemilikVia') && is_string($this->pemilikVia) && $this->pemilikVia !== '') {
            foreach (explode('.', $this->pemilikVia) as $relasi) {
                $obj = $obj?->{$relasi};
            }
        }

        $id = $obj?->guru_id;

        return $id === null ? null : (int) $id;
    }
}