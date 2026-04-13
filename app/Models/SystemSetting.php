<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /** @var string|null Cached for the remainder of the request after first read. */
    private static ?string $activeSemesterCache = null;
    /** @var string|null Cached for the remainder of the request after first read. */
    private static ?string $activeAcademicYearCache = null;

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function put(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        if ($key === 'active_semester') {
            self::$activeSemesterCache = null;
        }
        if ($key === 'active_academic_year') {
            self::$activeAcademicYearCache = null;
        }
    }

    /**
     * Official active term for the current academic year (term_1 | term_2 | term_3).
     */
    public static function activeSemester(): string
    {
        if (self::$activeSemesterCache !== null) {
            return self::$activeSemesterCache;
        }

        $v = static::getValue('active_semester');

        return self::$activeSemesterCache = in_array($v, ['term_1', 'term_2', 'term_3'], true) ? $v : 'term_1';
    }

    public static function activeSemesterLabel(): string
    {
        return match (static::activeSemester()) {
            'term_1' => '1st Term',
            'term_2' => '2nd Term',
            'term_3' => '3rd Term',
            default => '1st Term',
        };
    }

    /**
     * Official active academic year (YYYY-YYYY), e.g. 2025-2026.
     */
    public static function activeAcademicYear(): string
    {
        if (self::$activeAcademicYearCache !== null) {
            return self::$activeAcademicYearCache;
        }

        $value = (string) static::getValue('active_academic_year', '');
        if (preg_match('/^\d{4}-\d{4}$/', $value) !== 1) {
            return self::$activeAcademicYearCache = static::defaultAcademicYear();
        }

        [$start, $end] = array_map('intval', explode('-', $value, 2));

        return self::$activeAcademicYearCache = ($end === ($start + 1))
            ? $value
            : static::defaultAcademicYear();
    }

    public static function defaultAcademicYear(): string
    {
        $startYear = (int) now()->format('Y');

        return $startYear.'-'.($startYear + 1);
    }
}
