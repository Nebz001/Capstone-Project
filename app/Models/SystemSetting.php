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
}
