<?php

use Illuminate\Support\Facades\Schedule;

// Refresh KPI data every 1 hour (run `php artisan schedule:work` to keep it going).
Schedule::command('kpi:sync --days=2')->hourly()->withoutOverlapping();
