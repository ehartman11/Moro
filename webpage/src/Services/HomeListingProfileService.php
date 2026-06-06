<?php
declare(strict_types=1);

namespace Moro\Services;

use DateTime;
use InvalidArgumentException;
use Moro\Repositories\HomeListingProfileRepository;

final class HomeListingProfileService
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

    public function profileVm(int $homeId): array
    {
        $profile = $this->profiles->findByHomeId($homeId);

        return [
            'profile' => $profile,
        ];
    }

    public function saveProfile(int $homeId, int $updatedByUserId, array $input): void
    {
        if ($homeId <= 0 || $updatedByUserId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        $beds = $this->normalizeDecimal($input['beds'] ?? null, 0.0, 20.0, 'beds_invalid');
        $baths = $this->normalizeDecimal($input['baths'] ?? null, 0.0, 20.0, 'baths_invalid');
        $interiorSqft = $this->normalizeInt($input['interior_sqft'] ?? null, 100, 100000, 'interior_sqft_invalid');
        $floors = $this->normalizeInt($input['floors'] ?? null, 1, 10, 'floors_invalid');
        $garageCapacity = $this->normalizeDecimal($input['garage_capacity'] ?? null, 0.0, 20.0, 'garage_capacity_invalid');
        $acreage = $this->normalizeDecimal($input['acreage'] ?? null, 0.0, 99999.999, 'acreage_invalid');
        $yearBuiltOverride = $this->normalizeInt($input['year_built_override'] ?? null, 1600, 2100, 'year_built_invalid');

        $style = $this->normalizeText($input['style'] ?? null, 80);
        $basementType = $this->normalizeText($input['basement_type'] ?? null, 40);
        $garageType = $this->normalizeText($input['garage_type'] ?? null, 40);
        $headline = $this->normalizeText($input['headline'] ?? null, 140);
        $summary = $this->normalizeText($input['summary'] ?? null, 5000);

        $isPublished = !empty($input['is_published']) ? 1 : 0;
        $publishedAt = $isPublished ? (new DateTime())->format('Y-m-d H:i:s') : null;
        $visibilityFields = $this->normalizeVisibility($input['visibility'] ?? null);

        $this->profiles->upsertByHomeId($homeId, $updatedByUserId, [
            'beds' => $beds,
            'baths' => $baths,
            'interior_sqft' => $interiorSqft,
            'style' => $style,
            'floors' => $floors,
            'basement_type' => $basementType,
            'garage_type' => $garageType,
            'garage_capacity' => $garageCapacity,
            'acreage' => $acreage,
            'year_built_override' => $yearBuiltOverride,
            'headline' => $headline,
            'summary' => $summary,
            'visibility_fields' => $visibilityFields,
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ]);
    }

    public static function defaultVisibility(): array
    {
        $visibility = [];
        foreach (self::VISIBILITY_KEYS as $key) {
            $visibility[$key] = true;
        }

        return $visibility;
    }

    private function normalizeVisibility(mixed $input): array
    {
        $visibility = self::defaultVisibility();
        if (!is_array($input)) {
            return $visibility;
        }

        foreach (self::VISIBILITY_KEYS as $key) {
            $visibility[$key] = !empty($input[$key]);
        }

        return $visibility;
    }

    private function normalizeText(mixed $value, int $maxLength): ?string
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

    private function normalizeInt(mixed $value, int $min, int $max, string $errorCode): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException($errorCode);
        }

        $int = (int)$value;
        if ($int < $min || $int > $max) {
            throw new InvalidArgumentException($errorCode);
        }

        return $int;
    }

    private function normalizeDecimal(mixed $value, float $min, float $max, string $errorCode): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException($errorCode);
        }

        $decimal = (float)$value;
        if ($decimal < $min || $decimal > $max) {
            throw new InvalidArgumentException($errorCode);
        }

        return (string)$decimal;
    }
}
