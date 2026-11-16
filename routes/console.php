<?php

use Illuminate\Support\Facades\Schedule;

// Refresh KPI data every 30 minutes (run `php artisan schedule:work` to keep it going).
Schedule::command('kpi:sync --days=2')->everyThirtyMinutes()->withoutOverlapping();
