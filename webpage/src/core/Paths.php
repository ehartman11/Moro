<?php
declare(strict_types=1);

namespace Moro\Core;

final class Paths
{
    /**
     * Absolute filesystem root of the app (/webpage)
     */
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Browser-visible base URL
     */
    public static function baseUrl(): string
    {
        return Config::baseUrl();
    }
}
