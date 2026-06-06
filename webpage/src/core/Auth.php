<?php
declare(strict_types=1);

namespace Moro\Core;

use PDO;

/**
 * Authentication & authorization helper
 *
 * Responsibilities:
 * - User/session access
 * - Active home context
 * - Role resolution
 * - Guard methods
 */
final class Auth
{
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function activeHomeId(): ?int
    {
        return isset($_SESSION['active_home_id']) ? (int)$_SESSION['active_home_id'] : null;
    }

    public static function requireLogin(): int
    {
        $uid = self::userId();
        if ($uid === null) {
            Response::redirect('/public/login.php');
        }
        return $uid;
    }

    public static function requireActiveHome(): int
    {
        $hid = self::activeHomeId();
        if ($hid === null) {
            Response::redirect('/public/homes.php');
        }
        return $hid;
    }

    public static function roleOnHome(PDO $pdo, int $userId, int $homeId): string
    {
        $stmt = $pdo->prepare("
            SELECT role
            FROM home_permissions
            WHERE user_id = :uid AND home_id = :hid
            LIMIT 1
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':hid' => $homeId
        ]);

        return $stmt->fetchColumn() ?: 'viewer';
    }

    public static function requireOwner(string $role, string $returnToUrl): void
    {
        if ($role !== 'owner') {
            Response::redirectToUrl(
                $returnToUrl . (str_contains($returnToUrl, '?') ? '&' : '?') . 'err=unauthorized'
            );
        }
    }

    /**
     * @param string[] $allowedRoles
     */
    public static function hasAnyHomeRole(string $role, array $allowedRoles): bool
    {
        return in_array($role, $allowedRoles, true);
    }

    /**
     * @param string[] $allowedRoles
     */
    public static function requireHomeRole(string $role, array $allowedRoles, string $returnToUrl, string $errorCode = 'unauthorized'): void
    {
        if (!self::hasAnyHomeRole($role, $allowedRoles)) {
            Response::redirectToUrl(
                $returnToUrl . (str_contains($returnToUrl, '?') ? '&' : '?') . 'err=' . urlencode($errorCode)
            );
        }
    }

    public static function requireSeeker(string $role, string $returnToUrl): void
    {
        self::requireHomeRole($role, ['seeker'], $returnToUrl, 'seeker_role_required');
    }

    public static function isAdmin(): bool
    {
        return ((string)($_SESSION['user_role'] ?? '')) === 'admin';
    }

    public static function requireAdmin(string $returnToUrl): void
    {
        if (!self::isAdmin()) {
            Response::redirectToUrl(
                $returnToUrl . (str_contains($returnToUrl, '?') ? '&' : '?') . 'err=admin_required'
            );
        }
    }

    public static function canUsePortalRole(PDO $pdo, int $userId, ?int $homeId, string $portalRole): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (!in_array($portalRole, ['myhome', 'contracting', 'searching'], true)) {
            return false;
        }

        if (Config::relaxedPortalRoles()) {
            return true;
        }

        return match ($portalRole) {
            'myhome' => $homeId !== null && self::hasHomePermission($pdo, $userId, $homeId),
            'contracting' => self::hasContractorProfile($pdo, $userId),
            'searching' => $homeId !== null && self::roleOnHome($pdo, $userId, $homeId) === 'seeker',
            default => false,
        };
    }

    public static function resolveDefaultPortalRole(PDO $pdo, int $userId, ?int $homeId): string
    {
        $priority = ['myhome', 'contracting', 'searching'];
        foreach ($priority as $portalRole) {
            if (self::canUsePortalRole($pdo, $userId, $homeId, $portalRole)) {
                return $portalRole;
            }
        }

        return 'myhome';
    }

    public static function hasContractorProfile(PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM contractor_profiles WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function hasHomePermission(PDO $pdo, int $userId, int $homeId): bool
    {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM home_permissions
            WHERE user_id = :uid AND home_id = :hid
            LIMIT 1
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':hid' => $homeId,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    public static function requireContractorProfile(PDO $pdo, int $userId, string $returnToUrl): void
    {
        if (!self::hasContractorProfile($pdo, $userId)) {
            Response::redirectToUrl(
                $returnToUrl . (str_contains($returnToUrl, '?') ? '&' : '?') . 'err=contractor_profile_required'
            );
        }
    }

    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function requireCsrf(string $token): void
    {
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!$expected || !hash_equals($expected, $token)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }
    }
}
