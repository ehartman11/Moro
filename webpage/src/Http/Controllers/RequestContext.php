<?php
declare(strict_types=1);

namespace Moro\Http\Controllers;

use PDO;
use Moro\Core\Auth;
use Moro\Core\Db;

final class RequestContext
{
    public static function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }
    }

    /**
     * @return array{pdo: PDO, userId: int, homeId: int, role: string}
     */
    public static function homeContext(): array
    {
        $pdo = Db::pdo();
        $userId = Auth::requireLogin();
        $homeId = Auth::requireActiveHome();
        $role = Auth::roleOnHome($pdo, $userId, $homeId);

        return [
            'pdo' => $pdo,
            'userId' => $userId,
            'homeId' => $homeId,
            'role' => $role,
        ];
    }

    /**
     * @return array{pdo: PDO, userId: int, homeId: int, role: string, returnTo: string}
     */
    public static function ownerContext(array $input, string $defaultReturnTo): array
    {
        $ctx = self::homeContext();
        $returnTo = (string)($input['return_to'] ?? $defaultReturnTo);

        Auth::requireOwner($ctx['role'], $returnTo);

        $ctx['returnTo'] = $returnTo;
        return $ctx;
    }

    /**
     * @return array{pdo: PDO, userId: int, homeId: int, role: string, returnTo: string}
     */
    public static function contractorContext(array $input, string $defaultReturnTo): array
    {
        $ctx = self::homeContext();
        $returnTo = (string)($input['return_to'] ?? $defaultReturnTo);

        Auth::requireContractorProfile($ctx['pdo'], (int)$ctx['userId'], $returnTo);

        $ctx['returnTo'] = $returnTo;
        return $ctx;
    }

    /**
     * @return array{pdo: PDO, userId: int, homeId: int, role: string, returnTo: string}
     */
    public static function seekerContext(array $input, string $defaultReturnTo): array
    {
        $ctx = self::homeContext();
        $returnTo = (string)($input['return_to'] ?? $defaultReturnTo);

        Auth::requireSeeker((string)$ctx['role'], $returnTo);

        $ctx['returnTo'] = $returnTo;
        return $ctx;
    }

    /**
     * @return array{pdo: PDO, userId: int, homeId: int, role: string, returnTo: string, activePortalRole: string}
     */
    public static function portalContext(array $input, string $defaultReturnTo): array
    {
        $ctx = self::homeContext();
        $returnTo = (string)($input['return_to'] ?? $defaultReturnTo);

        $activePortalRole = (string)($_SESSION['active_portal_role'] ?? '');
        if (!Auth::canUsePortalRole($ctx['pdo'], (int)$ctx['userId'], (int)$ctx['homeId'], $activePortalRole)) {
            $activePortalRole = Auth::resolveDefaultPortalRole($ctx['pdo'], (int)$ctx['userId'], (int)$ctx['homeId']);
            $_SESSION['active_portal_role'] = $activePortalRole;
        }

        $ctx['returnTo'] = $returnTo;
        $ctx['activePortalRole'] = $activePortalRole;
        return $ctx;
    }
}