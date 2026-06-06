<?php
declare(strict_types=1);

namespace Moro\Services;

use InvalidArgumentException;
use Moro\Repositories\HomeListingProfileRepository;

final class SeekerOverviewService
{
    /** @var string[] */
    private const VISIBILITY_KEYS = [
        'headline',
        'summary',
        'beds',
        'baths',
        'interior_sqft',
        'style',
        'floors',
        'basement_type',
        'garage_type',
        'garage_capacity',
        'acreage',
        'year_built_override',
    ];

    public function __construct(private HomeListingProfileRepository $profiles) {}

    public function overviewVm(array $query, int $seekerUserId): array
    {
        $city = $this->normalizeFilter($query['city'] ?? null, 80);
        $state = $this->normalizeFilter($query['state'] ?? null, 40);
        $zip = $this->normalizeFilter($query['zip'] ?? null, 20);
        $minBeds = $this->normalizeDecimalFilter($query['min_beds'] ?? null, 0.0, 20.0);
        $minBaths = $this->normalizeDecimalFilter($query['min_baths'] ?? null, 0.0, 20.0);

        $rows = $this->profiles->listPublishedByLocation($city, $state, $zip, $minBeds, $minBaths, $seekerUserId, 200);
        foreach ($rows as &$row) {
            $visibility = $this->resolveVisibility($row['visibility_fields'] ?? null);
            foreach (self::VISIBILITY_KEYS as $key) {
                if (!$visibility[$key]) {
                    $row[$key] = null;
                }
            }
            $row['visibility_fields'] = $visibility;
        }
        unset($row);

        return [
            'filters' => [
                'city' => $city ?? '',
                'state' => $state ?? '',
                'zip' => $zip ?? '',
                'min_beds' => $minBeds ?? '',
                'min_baths' => $minBaths ?? '',
            ],
            'rows' => $rows,
        ];
    }

    private function normalizeDecimalFilter(mixed $value, float $min, float $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $decimal = (float)$value;
        if ($decimal < $min || $decimal > $max) {
            return null;
        }

        return (string)$decimal;
    }

    public function detailVm(int $homeId): array
    {
        if ($homeId <= 0) {
            throw new InvalidArgumentException('home_required');
        }

        $detail = $this->profiles->findPublishedDetailByHomeId($homeId);
        if (!$detail) {
            throw new InvalidArgumentException('home_not_found');
        }

        $visibility = $this->resolveVisibility($detail['visibility_fields'] ?? null);
        foreach (self::VISIBILITY_KEYS as $key) {
            if (!$visibility[$key]) {
                $detail[$key] = null;
            }
        }
        $detail['visibility_fields'] = $visibility;
        $summary = $this->profiles->trustSummaryForHome($homeId);

        return [
            'detail' => $detail,
            'summary' => $summary,
        ];
    }

    private function resolveVisibility(mixed $raw): array
    {
        $visibility = [];
        foreach (self::VISIBILITY_KEYS as $key) {
            $visibility[$key] = true;
        }

        if (!is_array($raw)) {
            return $visibility;
        }

        foreach (self::VISIBILITY_KEYS as $key) {
            if (array_key_exists($key, $raw)) {
                $visibility[$key] = (bool)$raw[$key];
            }
        }

        return $visibility;
    }

    private function normalizeFilter(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }
}
