<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Nav;

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;

final class SwitchPortalController
{
    public function handle(): never
    {
        RequestContext::requirePost();

        $userId = Auth::requireLogin();
        $homeId = Auth::activeHomeId();
        $pdo = Db::pdo();

        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $returnTo = (string)($_POST['return_to'] ?? (Paths::baseUrl() . '/public/index.php'));
        $requestedRole = trim((string)($_POST['portal_role'] ?? ''));
        $allowedRoles = ['myhome', 'contracting', 'searching'];

        if (!in_array($requestedRole, $allowedRoles, true)) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=portal_role_invalid'));
        }

        if (!Auth::canUsePortalRole($pdo, $userId, $homeId, $requestedRole)) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=portal_role_unavailable'));
        }

        $_SESSION['active_portal_role'] = $requestedRole;

        $landing = match ($requestedRole) {
            'myhome' => ($homeId !== null)
                ? Paths::baseUrl() . '/public/items/index.php'
                : Paths::baseUrl() . '/public/homes.php',
            'contracting' => Paths::baseUrl() . '/public/contractor/index.php',
            'searching' => Paths::baseUrl() . '/public/seeker/index.php',
            default => Paths::baseUrl() . '/public/index.php',
        };

        Response::redirectToUrl($landing);
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
