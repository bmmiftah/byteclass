<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kelas';

    protected $fillable = [
        'guru_id',
        'nama',
        'tahun_ajaran',
        'semester',
    ];

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function siswas()
    {
        return $this->hasMany(Siswa::class);
    }

    public function denahs()
    {
        return $this->hasMany(Denah::class);
    }

    public function kelompokSesis()
    {
        return $this->hasMany(KelompokSesi::class);
    }

}