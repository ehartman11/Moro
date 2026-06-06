<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Repositories\ContractorRepository;
use Moro\Services\ContractorService;

final class ContractorProfileController
{
    private ContractorService $service;

    public function __construct(?ContractorService $service = null)
    {
        if ($service !== null) {
            $this->service = $service;
            return;
        }

        $this->service = new ContractorService(
            new ContractorRepository(Db::pdo())
        );
    }

    public function index(int $userId, array $query): array
    {
        $vm = $this->service->profileVm($userId);

        $saved = isset($query['saved']);
        $errCode = isset($query['err']) ? (string)$query['err'] : '';

        $flashError = '';
        if ($errCode !== '') {
            $flashError = match ($errCode) {
                'business_name_required' => 'Business name is required.',
                'invalid_email' => 'Email format is invalid.',
                'profile_save_failed' => 'Unable to save contractor profile.',
                default => 'An error occurred.',
            };
        }

        return [
            'profile' => $vm['profile'],
            'categoriesText' => $vm['categoriesText'],
            'contractorOptions' => $vm['contractorOptions'] ?? [],
            'baseUrl' => Paths::baseUrl(),
            'csrf' => Auth::csrfToken(),
            'flashSuccess' => $saved ? 'Contractor profile saved.' : '',
            'flashError' => $flashError,
        ];
    }

    public function handleSave(): never
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        $userId = Auth::requireLogin();
        Auth::requireCsrf($_POST['csrf'] ?? '');

        $returnTo = Paths::baseUrl() . '/public/contractor/index.php';

        try {
            $this->service->saveProfile($userId, $_POST);
            Response::redirectToUrl($returnTo . '?saved=1');
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = ['business_name_required', 'invalid_email'];
            if (!in_array($code, $allowed, true)) {
                $code = 'profile_save_failed';
            }
            Response::redirectToUrl($returnTo . '?err=' . urlencode($code));
        } catch (\Throwable) {
            Response::redirectToUrl($returnTo . '?err=profile_save_failed');
        }
    }
}
