<?php
declare(strict_types=1);

namespace Moro\Services;

use InvalidArgumentException;
use Moro\Repositories\ContractorRepository;

final class ContractorService
{
    public function __construct(private ContractorRepository $contractors) {}

    public function profileVm(int $userId): array
    {
        $profile = $this->contractors->findProfileByUserId($userId);
        $contractorOptions = $this->contractors->listContractorOptions();

        return [
            'profile' => $profile,
            'categoriesText' => $profile ? implode(', ', (array)($profile['service_categories'] ?? [])) : '',
            'contractorOptions' => $contractorOptions,
        ];
    }

    public function saveProfile(int $userId, array $input): void
    {
        $businessName = trim((string)($input['business_name'] ?? ''));
        $displayName = trim((string)($input['display_name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $website = trim((string)($input['website'] ?? ''));
        $licenseNumber = trim((string)($input['license_number'] ?? ''));
        $licenseState = trim((string)($input['license_state'] ?? ''));
        $bio = trim((string)($input['bio'] ?? ''));
        $categoriesText = trim((string)($input['service_categories'] ?? ''));

        if ($businessName === '') {
            throw new InvalidArgumentException('business_name_required');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('invalid_email');
        }

        $categories = [];
        if ($categoriesText !== '') {
            $raw = array_filter(array_map('trim', explode(',', $categoriesText)), static fn(string $v): bool => $v !== '');
            $categories = array_values(array_unique($raw));
        }

        $this->contractors->upsertProfile($userId, [
            'business_name' => $businessName,
            'display_name' => ($displayName !== '' ? $displayName : null),
            'phone' => ($phone !== '' ? $phone : null),
            'email' => ($email !== '' ? $email : null),
            'website' => ($website !== '' ? $website : null),
            'service_categories' => $categories,
            'license_number' => ($licenseNumber !== '' ? $licenseNumber : null),
            'license_state' => ($licenseState !== '' ? $licenseState : null),
            'insured' => isset($input['insured']) ? 1 : 0,
            'bio' => ($bio !== '' ? $bio : null),
        ]);
    }
}
