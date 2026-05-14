<?php

declare(strict_types=1);

if (!function_exists('format_date_id')) {
    /** Format date as DD/MM/YYYY (Asia/Jakarta). */
    function format_date_id(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }
        try {
            $dt = new DateTimeImmutable($datetime, new DateTimeZone('Asia/Jakarta'));
            return $dt->format('d/m/Y');
        } catch (Exception) {
            return '';
        }
    }
}

if (!function_exists('format_datetime_id')) {
    /** Format date-time as DD/MM/YYYY HH:mm (Asia/Jakarta). */
    function format_datetime_id(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }
        try {
            $dt = new DateTimeImmutable($datetime, new DateTimeZone('Asia/Jakarta'));
            return $dt->format('d/m/Y H:i');
        } catch (Exception) {
            return '';
        }
    }
}

if (!function_exists('format_lead_time')) {
    /** Format seconds as HH:mm:ss. */
    function format_lead_time(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
