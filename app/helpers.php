<?php

use Illuminate\Support\Carbon;

if (! function_exists('kpi_num')) {
    function kpi_num($n): string
    {
        return $n === null ? '—' : number_format((float) $n);
    }
}

if (! function_exists('kpi_pct')) {
    function kpi_pct($ratio, int $decimals = 1): string
    {
        return $ratio === null ? '—' : number_format($ratio * 100, $decimals).'%';
    }
}

if (! function_exists('kpi_peso')) {
    function kpi_peso($n): string
    {
        return '₱'.number_format((float) $n, 0);
    }
}

if (! function_exists('kpi_time')) {
    /** Format a UTC datetime in the dashboard timezone. */
    function kpi_time(?Carbon $dt, string $format = 'M j, g:i A'): string
    {
        return $dt ? $dt->copy()->setTimezone(config('kpi.timezone'))->format($format) : '—';
    }
}

if (! function_exists('kpi_page_avatar')) {
    /** Serves cached static avatar in /avatars/{id}.jpg using a single per-request directory map (zero per-row stat/HTTP overhead). */
    function kpi_page_avatar(?string $fbPageId): string
    {
        if (! $fbPageId) {
            return '';
        }
        static $present = null;
        if ($present === null) {
            $present = [];
            $dir = public_path('avatars');
            if (is_dir($dir)) {
                foreach (scandir($dir) ?: [] as $f) {
                    if (str_ends_with($f, '.jpg')) {
                        $present[$f] = true;
                    }
                }
            }
        }
        $clean = preg_replace('/[^0-9A-Za-z_-]/', '', $fbPageId);
        if (isset($present["{$clean}.jpg"])) {
            return "/avatars/{$clean}.jpg";
        }

        return PHP_OS_FAMILY === 'Windows' ? '' : route('page.avatar', ['pageId' => $clean]);
    }
}
