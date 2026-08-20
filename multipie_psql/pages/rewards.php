<?php

/*
|--------------------------------------------------------------------------
| REWARDS (Single Database: multipie_main_prod)
|--------------------------------------------------------------------------
*/

$perPage = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));

$usernameFilter = trim((string)($_GET['username'] ?? ''));
$statusFilter   = trim((string)($_GET['status'] ?? ''));

$rewards = [];
$totalRewards = 0;
$totalPages = 1;
$dbError = null;

$rewardTypeMap = [
    0 => 'march_bonanza',
    1 => 'referral',
    2 => 'cashback',
    3 => 'bonus'
];

$rewardStatusMap = [
    0 => 'pending',
    1 => 'eligible',
    2 => 'claimed',
    3 => 'revoked'
];

function rewardTypeLabel($value): string
{
    global $rewardTypeMap;
    return $rewardTypeMap[(int)$value] ?? (string)$value;
}

function rewardStatusLabel($value): string
{
    global $rewardStatusMap;
    return $rewardStatusMap[(int)$value] ?? (string)$value;
}

function rewardsPageUrl(int $page, string $username, string $status): string
{
    $params = array_filter([
        'page'     => 'rewards',
        'p'        => $page,
        'username' => $username !== '' ? $username : null,
        'status'   => $status !== '' ? $status : null,
    ], fn($v) => $v !== null);

    return 'index.php?' . http_build_query($params);
}

try {
    $pdo = getAppDb();
    $where = [];
    $params = [];

    // Filter by Username / Display Name
    if ($usernameFilter !== '') {
        $where[] = '(u.username ILIKE :username OR u.display_name ILIKE :username)';
        $params[':username'] = addcslashes($usernameFilter, '%_') . '%';
    }

    // Filter by Status
    if ($statusFilter !== '') {
        $statusValue = null;
        foreach ($rewardStatusMap as $number => $label) {
            if (strcasecmp($label, $statusFilter) === 0) {
                $statusValue = (int)$number;
                break;
            }
        }

        if ($statusValue === null && is_numeric($statusFilter)) {
            $statusValue = (int)$statusFilter;
        }

        if ($statusValue !== null) {
            $where[] = 'r.status = :status';
            $params[':status'] = $statusValue;
        }
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count Query
    $countSql = "
        SELECT COUNT(*)
        FROM public.rewards r
        LEFT JOIN public.users u ON u.id = r.user_id
        {$whereSql}
    ";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalRewards = (int)$countStmt->fetchColumn();

    $totalPages  = max(1, (int)ceil($totalRewards / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset      = ($currentPage - 1) * $perPage;

    // Fetch Rewards & User Data (confirmed_at removed)
    $sql = "
        SELECT 
            r.id, 
            r.amount, 
            r.reward_type, 
            r.status, 
            r.voucher_id, 
            r.user_id,
            r.expiry_date, 
            r.eligible_on, 
            r.claimed_on, 
            r.meta_info AS reward_meta_info, 
            r.created_at, 
            r.updated_at,
            u.username,
            u.display_name,
            u.email,
            u.mobile,
            u.meta_info AS user_meta_info
        FROM public.rewards r
        LEFT JOIN public.users u ON u.id = r.user_id
        {$whereSql}
        ORDER BY r.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<!-- ============================================================
     HEADER
============================================================ -->
<section class="view-header">
    <div>
        <h1>
            List of all Rewards
            <span class="count-pill-navy"><?= number_format($totalRewards) ?> Total</span>
        </h1>
        <p class="sub">Manage MultiPie reward records, referrers, vouchers and reward status.</p>
    </div>
</section>

<!-- ============================================================
     ERROR
============================================================ -->
<?php if ($dbError): ?>
    <div class="alert-box">
        <strong>PostgreSQL connection failed</strong>
        <p><?= e($dbError) ?></p>
    </div>
<?php endif; ?>

<!-- ============================================================
     FILTER
============================================================ -->
<form class="filter-bar" method="get" action="index.php">
    <input type="hidden" name="page" value="rewards">
    <div class="filter-controls">
        <input type="text" name="username" value="<?= e($usernameFilter) ?>" placeholder="User Username starts with">
        <select name="status" class="select-plain">
            <option value="">-- All --</option>
            <?php foreach ($rewardStatusMap as $label): ?>
                <option value="<?= e($label) ?>" <?= strcasecmp($statusFilter, $label) === 0 ? 'selected' : '' ?>>
                    <?= e(ucfirst($label)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Search</button>
        <a href="index.php?page=rewards" class="btn btn-outline btn-sm">View All</a>
    </div>
</form>

<!-- ============================================================
     COUNT
============================================================ -->
<div class="filter-count">
    Showing <b><?= $totalRewards > 0 ? ($offset + 1) : 0 ?></b> to <b><?= min($offset + $perPage, $totalRewards) ?></b> of <span><?= number_format($totalRewards) ?></span> Rewards
</div>

<!-- ============================================================
     TABLE
============================================================ -->
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Referrer</th>
                <th>Referred</th>
                <th>User Ids</th>
                <th>View Referrer</th>
                <th>Voucher</th>
                <th>Eligible On</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rewards)): ?>
                <tr class="empty-row">
                    <td colspan="9">No rewards found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rewards as $reward): 
                    $userId = (int)($reward['user_id'] ?? 0);
                    $hasUser = !empty($reward['username']) || !empty($reward['display_name']);
                    $username = $reward['username'] ?? ($reward['display_name'] ?? ($userId ? 'User #' . $userId : '-'));

                    $userData = $hasUser ? [
                        'id'           => $userId,
                        'username'     => $reward['username'],
                        'display_name' => $reward['display_name'],
                        'email'        => $reward['email'],
                        'mobile'       => $reward['mobile'],
                        'meta_info'    => $reward['user_meta_info']
                    ] : null;
                    
                    $meta = [];
                    if (!empty($reward['reward_meta_info'])) {
                        $decoded = json_decode($reward['reward_meta_info'], true);
                        if (is_array($decoded)) $meta = $decoded;
                    }

                    $referredUserIds = $meta['referred_user_ids'] ?? ($meta['referred_users'] ?? ($meta['referred_user_id'] ?? '-'));
                    if (is_array($referredUserIds)) {
                        $referredUserIds = implode(', ', $referredUserIds);
                    }

                    $rewardType   = rewardTypeLabel($reward['reward_type'] ?? '');
                    $rewardStatus = rewardStatusLabel($reward['status'] ?? '');
                    
                    $eligibleOn = $reward['eligible_on'] ?? '';
                    if ($eligibleOn !== '') {
                        $ts = strtotime($eligibleOn);
                        if ($ts) $eligibleOn = date('d M Y H:i', $ts);
                    }
                ?>
                    <tr>
                        <td><?= e($rewardType) ?></td>
                        <td>
                            <?php if ($hasUser): ?>
                                <span class="reward-referrer-name"><?= e($username) ?></span>
                                <span class="reward-user-id">- <?= e($userId) ?></span>
                            <?php else: ?>
                                <?= $userId ? 'User - ' . $userId : '-' ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($referredUserIds) ?></td>
                        <td><?= e($userId) ?></td>
                        <td>
                            <?php if ($userData): ?>
                                <button type="button" class="table-link-button open-user-btn" data-user='<?= htmlspecialchars(json_encode($userData), ENT_QUOTES, 'UTF-8') ?>'>
                                    View
                                </button>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($reward['voucher_id']) ? e($reward['voucher_id']) : '-' ?></td>
                        <td><?= e($eligibleOn) ?></td>
                        <td>
                            <span class="status-badge">
                                <span class="dot-status"></span>
                                <?= e(ucfirst($rewardStatus)) ?>
                            </span>
                        </td>
                        <td>
                            <span style="color:var(--slate-500); font-size:12px;">Revoke action to come</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ============================================================
     PAGINATION
============================================================ -->
<?php if ($totalPages > 1): 
    $startPage = max(1, $currentPage - 2);
    $endPage   = min($totalPages, $currentPage + 2);
?>
    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a class="pagination-btn" href="<?= e(rewardsPageUrl($currentPage - 1, $usernameFilter, $statusFilter)) ?>">Previous</a>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a class="pagination-btn <?= $i === $currentPage ? 'active' : '' ?>" href="<?= e(rewardsPageUrl($i, $usernameFilter, $statusFilter)) ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a class="pagination-btn" href="<?= e(rewardsPageUrl($currentPage + 1, $usernameFilter, $statusFilter)) ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ============================================================
     USER MODAL
============================================================ -->
<div id="rewardUserModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width:1050px;">
        <div class="modal-header">
            <div>
                <h2>Viewing User</h2>
                <p>User information associated with this reward.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeRewardUserModal()">×</button>
        </div>

        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group"><label>User</label><input id="rewardViewUsername" type="text" readonly></div>
                <div class="form-group"><label>Email</label><input id="rewardViewEmail" type="text" readonly></div>
                <div class="form-group"><label>Mobile</label><input id="rewardViewMobile" type="text" readonly></div>
                <div class="form-group"><label>Display Name</label><input id="rewardViewDisplayName" type="text" readonly></div>
                <div class="form-group"><label>User ID</label><input id="rewardViewId" type="text" readonly></div>
            </div>

            <div class="detail-section" style="margin-top:30px;">
                <h3>Connected Accounts</h3>
                <div id="rewardConnectedAccounts" class="detail-value">- none -</div>
            </div>

            <div class="detail-section" style="margin-top:25px;">
                <h3>Demographics</h3>
                <div class="detail-grid">
                    <div><strong>Age group</strong><span id="rewardAgeGroup">-</span></div>
                    <div><strong>Preferred Instruments</strong><span id="rewardPreferredInstruments">-</span></div>
                    <div><strong>Investing Experience</strong><span id="rewardInvestingExperience">-</span></div>
                    <div><strong>City</strong><span id="rewardCity">-</span></div>
                </div>
            </div>

            <div class="detail-section" style="margin-top:30px;">
                <h3>Reports</h3>
                <p class="sub">Reports associated with this user are displayed here when available.</p>
            </div>

            <div class="detail-section" style="margin-top:30px;">
                <h3>Rewards</h3>
                <p class="sub">Reward records associated with this user.</p>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeRewardUserModal()">Close</button>
        </div>
    </div>
</div>

<style>
.reward-referrer-name { color: #1e293b; font-weight: 500; }
.reward-user-id { color: #64748b; margin-left: 3px; }
.table-link-button { border: 0; background: transparent; color: #063b75; cursor: pointer; font-size: 13px; font-weight: 600; padding: 0; }
.table-link-button:hover { text-decoration: underline; }
.text-muted { color: var(--slate-500); }
</style>

<script>
const userModal = document.getElementById('rewardUserModal');

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.open-user-btn');
    if (btn) {
        const user = JSON.parse(btn.dataset.user || '{}');
        openRewardUserModal(user);
    }
});

function openRewardUserModal(user) {
    document.getElementById('rewardViewUsername').value     = user.username || '';
    document.getElementById('rewardViewEmail').value        = user.email || '';
    document.getElementById('rewardViewMobile').value       = user.mobile || '';
    document.getElementById('rewardViewDisplayName').value  = user.display_name || '';
    document.getElementById('rewardViewId').value           = user.id || '';

    let meta = {};
    if (user.meta_info) {
        try {
            meta = typeof user.meta_info === 'string' ? JSON.parse(user.meta_info) : user.meta_info;
        } catch (e) {
            meta = {};
        }
    }

    const connectedAccounts = meta.connected_accounts ?? meta.connectedAccounts ?? '- none -';
    document.getElementById('rewardConnectedAccounts').textContent = Array.isArray(connectedAccounts) 
        ? connectedAccounts.join(', ') 
        : connectedAccounts;

    document.getElementById('rewardAgeGroup').textContent = meta.age_group ?? meta.ageGroup ?? '-';
    
    const prefInst = meta.preferred_instruments ?? meta.preferredInstruments ?? '-';
    document.getElementById('rewardPreferredInstruments').textContent = Array.isArray(prefInst) 
        ? prefInst.join(', ') 
        : prefInst;

    document.getElementById('rewardInvestingExperience').textContent = meta.investing_experience ?? meta.investingExperience ?? '-';
    document.getElementById('rewardCity').textContent = meta.city ?? '-';

    userModal.style.display = 'flex';
}

function closeRewardUserModal() {
    userModal.style.display = 'none';
}

document.addEventListener('click', function(event) {
    if (event.target === userModal) {
        closeRewardUserModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && userModal.style.display === 'flex') {
        closeRewardUserModal();
    }
});
</script>