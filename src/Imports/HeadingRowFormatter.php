<?php

namespace Maatwebsite\Excel\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class HeadingRowFormatter
{
    /**
     * @const string
     */
    const FORMATTER_NONE = 'none';

    /**
     * @const string
     */
    const FORMATTER_SLUG = 'slug';

    /**
     * @var string
     */
    protected static $formatter;

    /**
     * @var callable[]
     */
    protected static $customFormatters = [];

    /**
     * @var array
     */
    protected static $defaultFormatters = [
        self::FORMATTER_NONE,
        self::FORMATTER_SLUG,
    ];

    /**
     * @param  array  $headings
     * @return array
     */
    public static function format(array $headings): array
    {
        return (new Collection($headings))->map(function ($value, $key) {
            return static::callFormatter($value, $key);
        })->toArray();
    }

    /**
     * @param  string  $name
     */
    public static function default(?string $name = null)
    {
        if (null !== $name && !isset(static::$customFormatters[$name]) && !in_array($name, static::$defaultFormatters, true)) {
            throw new InvalidArgumentException(sprintf('Formatter "%s" does not exist', $name));
        }

        static::$formatter = $name;
    }

    /**
     * @param  string  $name
     * @param  callable  $formatter
     */
    public static function extend(string $name, callable $formatter)
    {
        static::$customFormatters[$name] = $formatter;
    }

    /**
     * Reset the formatter.
     */
    public static function reset()
    {
        static::default();
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    protected static function callFormatter($value, $key=null)
    {
        static::$formatter = static::$formatter ?? config('excel.imports.heading_row.formatter', self::FORMATTER_SLUG);

        // Call custom formatter
        if (isset(static::$customFormatters[static::$formatter])) {
            $formatter = static::$customFormatters[static::$formatter];

            return $formatter($value, $key);
        }

        if (empty($value)) {
            return $key;
        }

        if (static::$formatter === self::FORMATTER_SLUG) {
            return Str::slug($value, '_');
        }

        // No formatter (FORMATTER_NONE)
        return $value;
    }
}
