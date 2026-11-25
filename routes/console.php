<?php

use App\Console\Commands\CheckSendingDomains;
use App\Console\Commands\GenerateWeeklyReports;
use App\Jobs\RecordQueueHeartbeat;
use App\Support\Heartbeats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateWeeklyReports::class)->weeklyOn(1, '9:00');

Schedule::command(CheckSendingDomains::class)->everyTenMinutes()->withoutOverlapping();

// The heartbeats that fp:status and fp:doctor read: one for the scheduler,
// and one small queued job for the queue worker.
Schedule::call(function () {
    Heartbeats::recordScheduler();
    RecordQueueHeartbeat::dispatch();
})->everyMinute()->name('fp:heartbeat');
