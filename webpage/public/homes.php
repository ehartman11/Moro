<?php
declare(strict_types=1);

/**
 * Homes management + active-home selection page.
 *
 * Responsibilities:
 * - Requires authentication.
 * - Allows a user to create a new home and grants owner permission.
 * - Lists all homes the user has access to.
 * - Allows user to set active home in session.
 */

require_once __DIR__ . '/../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Repositories\HomeInquiryRepository;
use Moro\Repositories\HomeListingProfileRepository;
use Moro\Services\HomeInquiryService;
use Moro\Services\HomeListingProfileService;

$pdo = Db::pdo();

$userId = Auth::requireLogin();

// Optional: allow return_to, but keep it safe-ish (must be within app)
$baseUrl = Paths::baseUrl();
$returnTo = $_GET['return_to'] ?? ($baseUrl . '/public/items/index.php');
if (!is_string($returnTo) || $returnTo === '') {
    $returnTo = $baseUrl . '/public/items/index.php';
}

$successCode = '';
$errorCode = '';

/* -----------------------------
   Handle home selection (GET)
------------------------------ */
if (isset($_GET['select_home'])) {
    $homeId = (int)($_GET['select_home'] ?? 0);

    if ($homeId <= 0) {
        $errorCode = 'home_invalid';
    } else {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM home_permissions
            WHERE user_id = :uid AND home_id = :hid
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId, ':hid' => $homeId]);

        if ($stmt->fetchColumn()) {
            $_SESSION['active_home_id'] = $homeId;
            Response::redirectToUrl($returnTo);
        } else {
            $errorCode = 'unauthorized';
        }
    }
}

/* -----------------------------
   Handle new home creation (POST)
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_home'])) {
    Auth::requireCsrf($_POST['csrf'] ?? '');

    $nickname = trim((string)($_POST['nickname'] ?? ''));
    $addr1    = trim((string)($_POST['address_line1'] ?? ''));
    $addr2    = trim((string)($_POST['address_line2'] ?? ''));
    $city     = trim((string)($_POST['city'] ?? ''));
    $state    = trim((string)($_POST['state'] ?? ''));
    $zip      = trim((string)($_POST['zip'] ?? ''));
    $yearRaw  = trim((string)($_POST['year_built'] ?? ''));

    $yearBuilt = null;
    if ($yearRaw !== '') {
        $y = (int)$yearRaw;
        // light validation: 1600..2100 (tune if you want)
        if ($y < 1600 || $y > 2100) {
            $errorCode = 'home_bad_year';
        } else {
            $yearBuilt = $y;
        }
    }

    if ($errorCode === '') {
        if ($addr1 === '' || $city === '' || $state === '' || $zip === '') {
            $errorCode = 'home_required';
        } else {
                $stmtDup = $pdo->prepare("
                    SELECT id
                    FROM homes
                    WHERE address_line1 = :addr1
                      AND city = :city
                      AND state = :state
                      AND zip = :zip
                      AND COALESCE(NULLIF(TRIM(address_line2), ''), '') = :addr2_norm
                    LIMIT 1
                ");
                $stmtDup->execute([
                    ':addr1' => $addr1,
                    ':city' => $city,
                    ':state' => $state,
                    ':zip' => $zip,
                    ':addr2_norm' => $addr2,
                ]);

                if ($stmtDup->fetchColumn()) {
                    $errorCode = 'home_duplicate_address';
                }
            }
        }

        if ($errorCode === '') {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO homes (owner_id, nickname, address_line1, address_line2, city, state, zip, year_built)
                    VALUES (:owner_id, :nickname, :addr1, :addr2, :city, :state, :zip, :year_built)
                ");
                $stmt->execute([
                    ':owner_id'   => $userId,
                    ':nickname'   => ($nickname !== '' ? $nickname : null),
                    ':addr1'      => $addr1,
                    ':addr2'      => ($addr2 !== '' ? $addr2 : null),
                    ':city'       => $city,
                    ':state'      => $state,
                    ':zip'        => $zip,
                    ':year_built' => $yearBuilt,
                ]);

                $homeId = (int)$pdo->lastInsertId();

                $stmtPerm = $pdo->prepare("
                    INSERT INTO home_permissions (home_id, user_id, role)
                    VALUES (:hid, :uid, 'owner')
                ");
                $stmtPerm->execute([
                    ':hid' => $homeId,
                    ':uid' => $userId,
                ]);

                $_SESSION['active_home_id'] = $homeId;

                $pdo->commit();

                // redirect to avoid resubmission
                Response::redirectToUrl($baseUrl . '/public/homes.php?added=1');
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $sqlState = (string)($e->errorInfo[0] ?? '');
                if ($sqlState === '23000') {
                    $errorCode = 'home_duplicate_address';
                } else {
                    $errorCode = 'home_create_failed';
                }
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errorCode = 'home_create_failed';
            }
        }
}

/* -----------------------------
   Fetch homes for user
------------------------------ */
$stmtHomes = $pdo->prepare("
    SELECT h.*, p.role
    FROM homes h
    JOIN home_permissions p ON p.home_id = h.id
    WHERE p.user_id = :uid
    ORDER BY h.id DESC
");
$stmtHomes->execute([':uid' => $userId]);
$homes = $stmtHomes->fetchAll(PDO::FETCH_ASSOC);

$activeHomeId = Auth::activeHomeId();
$activeHomeRole = null;
$listingProfile = null;
$listingVisibility = HomeListingProfileService::defaultVisibility();
$ownerInquiries = [];
if ($activeHomeId !== null) {
    $activeHomeRole = Auth::roleOnHome($pdo, $userId, $activeHomeId);
    if ($activeHomeRole === 'owner') {
        $listingService = new HomeListingProfileService(new HomeListingProfileRepository($pdo));
        $listingVm = $listingService->profileVm($activeHomeId);
        $listingProfile = $listingVm['profile'] ?? null;
        if (is_array($listingProfile['visibility_fields'] ?? null)) {
            $listingVisibility = array_merge($listingVisibility, $listingProfile['visibility_fields']);
        }

        $inquiryService = new HomeInquiryService(new HomeInquiryRepository($pdo), new HomeListingProfileRepository($pdo));
        $ownerInquiries = $inquiryService->listForOwnerHome($activeHomeId);
    }
}

/* -----------------------------
   UI flash
------------------------------ */
$flashSuccess = '';
$flashError = '';

if (isset($_GET['added'])) {
    $flashSuccess = 'Home added successfully.';
}

if (isset($_GET['listing_saved'])) {
    $flashSuccess = 'Listing profile saved.';
}
if (isset($_GET['inquiry_responded'])) {
    $flashSuccess = 'Inquiry response saved.';
}
if (isset($_GET['home_verification_submitted'])) {
    $flashSuccess = 'Ownership verification submitted for review.';
}

if ($errorCode !== '') {
    $flashError = match ($errorCode) {
        'home_required'      => 'Address Line 1, City, State, and ZIP are required.',
        'home_bad_year'      => 'Year Built must be a valid year.',
        'home_duplicate_address' => 'A home with that address already exists.',
        'home_verification_already_pending' => 'Ownership verification is already pending review.',
        'home_verification_already_verified' => 'This home is already verified.',
        'verification_doc_required' => 'A verification proof file is required.',
        'verification_doc_upload' => 'The verification file upload failed.',
        'verification_doc_too_large' => 'Verification file is too large (max 15MB).',
        'verification_doc_type_invalid' => 'Verification file type is invalid. Use PDF/JPG/PNG/WEBP.',
        'verification_submit_failed' => 'Unable to submit verification right now.',
        'home_invalid'       => 'Invalid home selection.',
        'portal_role_invalid' => 'Selected portal role is invalid.',
        'portal_role_unavailable' => 'That portal is not available in your current context.',
        'contractor_profile_required' => 'Complete your contractor profile to access Contracting.',
        'seeker_role_required' => 'Your current home context does not include seeker access.',
        'beds_invalid'       => 'Beds must be between 0 and 20.',
        'baths_invalid'      => 'Baths must be between 0 and 20.',
        'interior_sqft_invalid' => 'Interior square footage is invalid.',
        'floors_invalid'     => 'Floors must be between 1 and 10.',
        'garage_capacity_invalid' => 'Garage capacity is invalid.',
        'acreage_invalid'    => 'Acreage is invalid.',
        'year_built_invalid' => 'Year built override is invalid.',
        'listing_save_failed' => 'Unable to save listing profile.',
        'inquiry_required'   => 'Inquiry selection is invalid.',
        'inquiry_response_required' => 'A response message is required.',
        'inquiry_response_too_long' => 'Response message is too long.',
        'inquiry_response_failed' => 'Unable to save inquiry response.',
        'unauthorized'       => 'You do not have permission to access this home.',
        'home_create_failed' => 'Database error while creating the home.',
        default              => 'An error occurred.',
    };
}

if (isset($_GET['err']) && $flashError === '') {
    $flashError = match ((string)$_GET['err']) {
        'portal_role_invalid' => 'Selected portal role is invalid.',
        'portal_role_unavailable' => 'That portal is not available in your current context.',
        'contractor_profile_required' => 'Complete your contractor profile to access Contracting.',
        'seeker_role_required' => 'Your current home context does not include seeker access.',
        'home_duplicate_address' => 'A home with that address already exists.',
        'home_verification_already_pending' => 'Ownership verification is already pending review.',
        'home_verification_already_verified' => 'This home is already verified.',
        'verification_doc_required' => 'A verification proof file is required.',
        'verification_doc_upload' => 'The verification file upload failed.',
        'verification_doc_too_large' => 'Verification file is too large (max 15MB).',
        'verification_doc_type_invalid' => 'Verification file type is invalid. Use PDF/JPG/PNG/WEBP.',
        'verification_submit_failed' => 'Unable to submit verification right now.',
        'beds_invalid'       => 'Beds must be between 0 and 20.',
        'baths_invalid'      => 'Baths must be between 0 and 20.',
        'interior_sqft_invalid' => 'Interior square footage is invalid.',
        'floors_invalid'     => 'Floors must be between 1 and 10.',
        'garage_capacity_invalid' => 'Garage capacity is invalid.',
        'acreage_invalid'    => 'Acreage is invalid.',
        'year_built_invalid' => 'Year built override is invalid.',
        'listing_save_failed' => 'Unable to save listing profile.',
        'inquiry_required'   => 'Inquiry selection is invalid.',
        'inquiry_response_required' => 'A response message is required.',
        'inquiry_response_too_long' => 'Response message is too long.',
        'inquiry_response_failed' => 'Unable to save inquiry response.',
        default => '',
    };
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Homes</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/tables.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/homes.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<section>

    <h2 class="form-title">My Homes</h2>

    <?php if ($flashSuccess !== ''): ?>
        <div class="popup show" style="background:#4CAF50;"><?= h($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
        <div class="popup show" style="background:#e74c3c;"><?= h($flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($homes)): ?>
        <table class="homes-table">
            <tr>
                <th>Nickname</th>
                <th>Address</th>
                <th>Role</th>
                <th>Verification</th>
                <th>Action</th>
            </tr>
            <?php foreach ($homes as $home): ?>
                <?php $isActiveHome = ((int)$home['id'] === (int)$activeHomeId); ?>
                <?php $verificationStatus = (string)($home['owner_verification_status'] ?? 'unverified'); ?>
                <tr class="<?= $isActiveHome ? 'is-active-home' : '' ?>">
                    <td><?= h((string)($home['nickname'] ?? '—')) ?></td>
                    <td>
                        <?= h((string)$home['address_line1']) ?>,
                        <?= h((string)$home['city']) ?>,
                        <?= h((string)$home['state']) ?>
                    </td>
                    <td><?= h((string)($home['role'] ?? 'viewer')) ?></td>
                    <td>
                        <span class="verification-pill is-<?= h($verificationStatus) ?>"><?= h($verificationStatus) ?></span>
                    </td>
                    <td>
                        <?php if ($isActiveHome): ?>
                            <span class="active-home-pill">Active</span>
                        <?php else: ?>
                            <a class="set-active-link" href="<?= $baseUrl ?>/public/homes.php?select_home=<?= (int)$home['id'] ?>">
                                Set Active
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="muted">No homes added yet.</p>
    <?php endif; ?>

    <form method="POST" action="<?= $baseUrl ?>/public/homes.php">
        <h3>Add New Home</h3>
        <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">

        <div class="row">
            <label>Nickname</label>
            <input type="text" name="nickname">
        </div>

        <div class="row">
            <label>Address Line 1 *</label>
            <input type="text" name="address_line1" required>
        </div>

        <div class="row">
            <label>Address Line 2</label>
            <input type="text" name="address_line2">
        </div>

        <div class="row">
            <label>City *</label>
            <input type="text" name="city" required>
        </div>

        <div class="row">
            <label>State *</label>
            <input type="text" name="state" required>
        </div>

        <div class="row">
            <label>ZIP *</label>
            <input type="text" name="zip" required>
        </div>

        <div class="row">
            <label>Year Built</label>
            <input type="number" name="year_built" min="1600" max="2100">
        </div>

        <div class="row">
            <input type="hidden" name="create_home" value="1">
            <input type="submit" value="Add Home">
        </div>
    </form>

    <?php if ($activeHomeId !== null && $activeHomeRole === 'owner'): ?>
        <?php
            $activeHomeVerificationStatus = 'unverified';
            foreach ($homes as $home) {
                if ((int)$home['id'] === (int)$activeHomeId) {
                    $activeHomeVerificationStatus = (string)($home['owner_verification_status'] ?? 'unverified');
                    break;
                }
            }
        ?>

        <form class="verification-submit" method="post" action="<?= $baseUrl ?>/public/actions.php?action=homes.submit_ownership_verification" enctype="multipart/form-data">
            <input type="hidden" name="submit_home_verification" value="1">
            <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">
            <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/homes.php">

            <div class="verification-submit-inner">
                <div>
                    <strong>Active Home Verification</strong>
                    <p class="muted" style="margin:4px 0 0 0;">Status: <span class="verification-pill is-<?= h($activeHomeVerificationStatus) ?>"><?= h($activeHomeVerificationStatus) ?></span></p>
                </div>

                <?php if (!in_array($activeHomeVerificationStatus, ['pending_review', 'verified'], true)): ?>
                    <div class="verification-inputs">
                        <label for="home_doc_type">Proof Type</label>
                        <select id="home_doc_type" name="doc_type" required>
                            <option value="utility_bill">Utility bill</option>
                            <option value="property_tax">Property tax statement</option>
                            <option value="deed">Deed</option>
                            <option value="closing_statement">Closing statement</option>
                            <option value="other">Other</option>
                        </select>

                        <label for="home_verification_file">Proof File (PDF or image)</label>
                        <input id="home_verification_file" type="file" name="verification_file" accept="application/pdf,image/jpeg,image/png,image/webp" required>
                        <button type="submit">Submit for Verification</button>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <form method="POST" action="<?= $baseUrl ?>/public/actions.php?action=homes.save_listing_profile">
            <h3>Active Home Listing Profile</h3>
            <input type="hidden" name="save_listing_profile" value="1">
            <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">
            <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/homes.php">

            <div class="row">
                <label>Headline</label>
                <input type="text" name="headline" maxlength="140" value="<?= h((string)($listingProfile['headline'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[headline]" value="1" <?= !empty($listingVisibility['headline']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Summary</label>
                <textarea name="summary" rows="4"><?= h((string)($listingProfile['summary'] ?? '')) ?></textarea>
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[summary]" value="1" <?= !empty($listingVisibility['summary']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Beds</label>
                <input type="number" step="0.5" min="0" max="20" name="beds" value="<?= h((string)($listingProfile['beds'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[beds]" value="1" <?= !empty($listingVisibility['beds']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Baths</label>
                <input type="number" step="0.5" min="0" max="20" name="baths" value="<?= h((string)($listingProfile['baths'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[baths]" value="1" <?= !empty($listingVisibility['baths']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Interior Sq Ft</label>
                <input type="number" min="100" max="100000" name="interior_sqft" value="<?= h((string)($listingProfile['interior_sqft'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[interior_sqft]" value="1" <?= !empty($listingVisibility['interior_sqft']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Style</label>
                <input type="text" maxlength="80" name="style" value="<?= h((string)($listingProfile['style'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[style]" value="1" <?= !empty($listingVisibility['style']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Floors</label>
                <input type="number" min="1" max="10" name="floors" value="<?= h((string)($listingProfile['floors'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[floors]" value="1" <?= !empty($listingVisibility['floors']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Basement Type</label>
                <input type="text" maxlength="40" name="basement_type" value="<?= h((string)($listingProfile['basement_type'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[basement_type]" value="1" <?= !empty($listingVisibility['basement_type']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Garage Type</label>
                <input type="text" maxlength="40" name="garage_type" value="<?= h((string)($listingProfile['garage_type'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[garage_type]" value="1" <?= !empty($listingVisibility['garage_type']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Garage Capacity</label>
                <input type="number" step="0.5" min="0" max="20" name="garage_capacity" value="<?= h((string)($listingProfile['garage_capacity'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[garage_capacity]" value="1" <?= !empty($listingVisibility['garage_capacity']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Acreage</label>
                <input type="number" step="0.001" min="0" max="99999.999" name="acreage" value="<?= h((string)($listingProfile['acreage'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[acreage]" value="1" <?= !empty($listingVisibility['acreage']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row">
                <label>Year Built Override</label>
                <input type="number" min="1600" max="2100" name="year_built_override" value="<?= h((string)($listingProfile['year_built_override'] ?? '')) ?>">
                <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <input type="checkbox" name="visibility[year_built_override]" value="1" <?= !empty($listingVisibility['year_built_override']) ? 'checked' : '' ?>>
                    Visible to seekers
                </label>
            </div>

            <div class="row" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" id="is_published" name="is_published" value="1" <?= !empty($listingProfile['is_published']) ? 'checked' : '' ?>>
                <label for="is_published" style="margin:0;">Publish for seeker portal</label>
            </div>

            <div class="row">
                <input type="submit" value="Save Listing Profile">
            </div>

            <div class="row" style="margin-top:-6px;">
                <?php if (!empty($listingProfile['is_published'])): ?>
                    <a href="<?= $baseUrl ?>/public/seeker/view.php?home_id=<?= (int)$activeHomeId ?>" target="_blank" rel="noopener">
                        Preview as Seeker
                    </a>
                <?php else: ?>
                    <span class="muted">Publish this listing profile to enable seeker preview.</span>
                <?php endif; ?>
            </div>
        </form>

        <section style="margin-top:26px;">
            <h3>Active Home Inquiries</h3>

            <?php if (empty($ownerInquiries)): ?>
                <p class="muted">No seeker inquiries yet.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>From</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Opened</th>
                        <th>Respond</th>
                    </tr>
                    <?php foreach ($ownerInquiries as $inq): ?>
                        <tr>
                            <td><?= h(trim((string)($inq['fname'] ?? '') . ' ' . (string)($inq['lname'] ?? ''))) ?></td>
                            <td><?= nl2br(h((string)($inq['message'] ?? ''))) ?></td>
                            <td><?= h((string)($inq['state'] ?? 'open')) ?></td>
                            <td><?= h((string)($inq['opened_at'] ?? '—')) ?></td>
                            <td>
                                <form method="post" action="<?= $baseUrl ?>/public/actions.php?action=homes.respond_inquiry">
                                    <input type="hidden" name="respond_inquiry" value="1">
                                    <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">
                                    <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/homes.php">
                                    <input type="hidden" name="inquiry_id" value="<?= (int)$inq['id'] ?>">
                                    <textarea name="owner_response" rows="2" placeholder="Response message" required><?= h((string)($inq['owner_response'] ?? '')) ?></textarea>
                                    <select name="state">
                                        <option value="responded" <?= (string)($inq['state'] ?? '') === 'responded' ? 'selected' : '' ?>>responded</option>
                                        <option value="closed" <?= (string)($inq['state'] ?? '') === 'closed' ? 'selected' : '' ?>>closed</option>
                                    </select>
                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

</section>
</body>
</html>
