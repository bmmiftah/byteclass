<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class Mapel extends Model
{
    use MilikGuru;
    
    protected $fillable	= [
        'guru_id', 
        'nama'
    ];
}
