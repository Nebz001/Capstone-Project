<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Display helpers for Philippine (Asia/Manila) local time.
 *
 * Pass Carbon instances from Eloquent / aggregates as returned by the framework
 * (they represent the stored instant in the application timezone). This class
 * converts to Asia/Manila only for formatting, without changing stored values.
 *
 * If the database stores UTC while `APP_TIMEZONE` remains `UTC`, formatting here
 * still shifts the instant to Philippine local time for the admin UI.
 */
final class ManilaDateTime
{
    public const TZ = 'Asia/Manila';

    public static function inManila(?CarbonInterface $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::instance($value)->timezone(self::TZ);
    }

    /**
     * Format a DATE-cast column (calendar day; no timezone conversion).
     */
    public static function formatSubmissionDate(?CarbonInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        return $date->format('M d, Y');
    }

    public static function formatLastUpdatedDateLine(?CarbonInterface $instant): ?string
    {
        if ($instant === null) {
            return null;
        }

        return self::inManila($instant)->format('M d, Y');
    }

    public static function formatLastUpdatedTimeLine(?CarbonInterface $instant): ?string
    {
        if ($instant === null) {
            return null;
        }

        return self::inManila($instant)->format('h:i A').' PHT';
    }

    /**
     * Single-line resubmitted / audit text (registration review UI).
     */
    public static function formatDateTimeLine(?CarbonInterface $instant): string
    {
        if ($instant === null) {
            return '—';
        }

        $m = self::inManila($instant);

        return $m->format('M d, Y').' · '.$m->format('h:i A').' PHT';
    }
}
