<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::unguarded(fn () => User::updateOrCreate(
            ['email' => 'bagus.haqqi@alizhar.sch.id'],
            [
                'name' => 'Bagus Miftah',
                'password' => 'Kuyangterbang',
                'peran' => 'admin',
            ]
        ));
    }
}