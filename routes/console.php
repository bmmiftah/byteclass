<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use	Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:jalankan	--simpan=7')->dailyAt('02:00');

Schedule::command('tugas:bersihkan')->dailyAt('02:00');

Schedule::command('pengingat:kirim')->everyFiveMinutes()->withoutOverlapping();
