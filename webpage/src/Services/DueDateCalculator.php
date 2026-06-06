<?php
declare(strict_types=1);

namespace Moro\Services;

use DateInterval;
use DateTime;
use InvalidArgumentException;

final class DueDateCalculator
{
    public static function nextDue(string $anchorYmd, int $freqVal, string $freqUnit): string
    {
        if ($freqVal <= 0) {
            throw new InvalidArgumentException('Invalid frequency value');
        }

        $dt = DateTime::createFromFormat('Y-m-d', $anchorYmd);
        if (!$dt || $dt->format('Y-m-d') !== $anchorYmd) {
            throw new InvalidArgumentException('Invalid anchor date');
        }

        $intervalSpec = match ($freqUnit) {
            'days'   => "P{$freqVal}D",
            'weeks'  => "P{$freqVal}W",
            'months' => "P{$freqVal}M",
            'years'  => "P{$freqVal}Y",
            default  => throw new InvalidArgumentException('Invalid frequency unit'),
        };

        $dt->add(new DateInterval($intervalSpec));
        return $dt->format('Y-m-d');
    }

    public static function isValidYmd(string $date): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }
}
