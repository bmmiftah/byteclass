<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class Rubrik extends Model
{
    use MilikGuru;
    
    protected $fillable = [
        'guru_id',
        'nama',
    ];

    public function kriterias()
    {
        return $this->hasMany(RubrikKriteria::class)->orderBy('urutan');
    }

    protected $casts = [
        'dibagikan' => 'boolean'
    ];

    public function guru() 
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}