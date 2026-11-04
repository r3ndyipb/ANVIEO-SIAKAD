<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('siakad:sync-feeder-lecturers')
    ->dailyAt('00:00')
    ->timezone('Asia/Jayapura')
    ->withoutOverlapping();

Schedule::command('siakad:sync-feeder-student-registrations')
    ->dailyAt('00:30')
    ->timezone('Asia/Jayapura')
    ->withoutOverlapping();
