<?php
declare(strict_types=1);

namespace Moro\Services;

use InvalidArgumentException;
use Moro\Repositories\HomeInquiryRepository;
use Moro\Repositories\HomeListingProfileRepository;

final class HomeInquiryService
{
    public function __construct(
        private HomeInquiryRepository $inquiries,
        private HomeListingProfileRepository $profiles
    ) {}

    public function submitInquiry(int $homeId, int $seekerUserId, array $input): int
    {
        if ($homeId <= 0 || $seekerUserId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        $detail = $this->profiles->findPublishedDetailByHomeId($homeId);
        if (!$detail) {
            throw new InvalidArgumentException('home_not_found');
        }

        $message = trim((string)($input['message'] ?? ''));
        if ($message === '') {
            throw new InvalidArgumentException('inquiry_message_required');
        }
        if (mb_strlen($message) > 3000) {
            throw new InvalidArgumentException('inquiry_message_too_long');
        }

        return $this->inquiries->insertInquiry($homeId, $seekerUserId, $message);
    }

    public function listForOwnerHome(int $homeId): array
    {
        if ($homeId <= 0) {
            return [];
        }

        return $this->inquiries->listForOwnerHome($homeId, 200);
    }

    public function respondAsOwner(int $homeId, int $inquiryId, array $input): void
    {
        if ($homeId <= 0 || $inquiryId <= 0) {
            throw new InvalidArgumentException('inquiry_required');
        }

        $row = $this->inquiries->findInHome($inquiryId, $homeId);
        if (!$row) {
            throw new InvalidArgumentException('unauthorized');
        }

        $response = trim((string)($input['owner_response'] ?? ''));
        if ($response === '') {
            throw new InvalidArgumentException('inquiry_response_required');
        }
        if (mb_strlen($response) > 3000) {
            throw new InvalidArgumentException('inquiry_response_too_long');
        }

        $state = trim((string)($input['state'] ?? 'responded'));
        $allowed = ['responded', 'closed'];
        if (!in_array($state, $allowed, true)) {
            $state = 'responded';
        }

        $this->inquiries->respondInHome($inquiryId, $homeId, $response, $state);
    }
}
