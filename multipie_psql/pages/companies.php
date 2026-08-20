<?php

$perPage = 25;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$nameStartsWith = trim((string)($_GET['name_starts_with'] ?? ''));
$bseTicker = trim((string)($_GET['bse_ticker'] ?? ''));
$nseTicker = trim((string)($_GET['nse_ticker'] ?? ''));
$status = strtolower(trim((string)($_GET['status'] ?? 'all')));

$status = in_array($status, ['0', '1'], true) ? $status : 'all';

/*
|--------------------------------------------------------------------------
| Build WHERE conditions
|--------------------------------------------------------------------------
*/
$where = [];
$params = [];

if ($nameStartsWith !== '') {
    $where[] = "COALESCE(name, '') ILIKE :name_prefix";
    $params[':name_prefix'] = $nameStartsWith . '%';
}

if ($bseTicker !== '') {
    $where[] = "COALESCE(bse_ticker, '') ILIKE :bse_ticker";
    $params[':bse_ticker'] = $bseTicker;
}

if ($nseTicker !== '') {
    $where[] = "COALESCE(nse_ticker, '') ILIKE :nse_ticker";
    $params[':nse_ticker'] = $nseTicker;
}

if ($status !== 'all') {
    $where[] = "status = :status";
    $params[':status'] = (int)$status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/*
|--------------------------------------------------------------------------
| Database execution
|--------------------------------------------------------------------------
*/
$dbError = null;
$totalCompanies = 0;
$companies = [];

try {
    $pdo = getAppDb();

    // 1. Total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM public.companies {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalCompanies = (int)$countStmt->fetchColumn();

    // 2. Pagination calculation
    $totalPages = max(1, (int)ceil($totalCompanies / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    // 3. Page fetch
    $sql = "
        SELECT
            id,
            name,
            relevance,
            status,
            nse_ticker,
            bse_ticker,
            meta_info,
            created_at,
            updated_at,
            size_classification,
            sector_id,
            icon_url,
            instrument_id,
            short_name,
            series,
            url_slug,
            seo_desc,
            COALESCE(meta_info->>'isin', meta_info->>'ISIN', '') AS isin
        FROM public.companies
        {$whereSql}
        ORDER BY name ASC NULLS LAST, id ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $totalCompanies = 0;
    $totalPages = 1;
}

/*
|--------------------------------------------------------------------------
| View Helpers
|--------------------------------------------------------------------------
*/
function multipieCompanyStatusLabel($status): string {
    return ((int)$status === 0) ? 'Active' : 'Inactive';
}

function multipieFormatDate($value): string {
    if (empty($value)) return '-';
    try {
        return (new DateTimeImmutable((string)$value))->format('d M Y');
    } catch (Throwable $e) {
        return (string)$value;
    }
}

function multipieCompanyQuery(array $overrides = []): string {
    $query = array_merge([
        'page' => 'companies',
        'name_starts_with' => (string)($_GET['name_starts_with'] ?? ''),
        'bse_ticker' => (string)($_GET['bse_ticker'] ?? ''),
        'nse_ticker' => (string)($_GET['nse_ticker'] ?? ''),
        'status' => (string)($_GET['status'] ?? 'all'),
    ], $overrides);

    return http_build_query(array_filter($query, static fn($v) => $v !== '' && $v !== 'all'));
}
?>

<section class="view-header">
    <div>
        <h1>
            Companies Directory &amp; Corporate Records
            <span class="count-pill-navy"><?= number_format($totalCompanies) ?> Total</span>
        </h1>
        <p class="sub">Manage MultiPie company profiles, listed market tickers, and directory records.</p>
    </div>
    <button class="btn btn-orange" type="button" onclick="openAddCompanyModal()">
        <svg class="icon icon-sm"><use href="#i-plus"/></svg>
        Add New Company
    </button>
</section>

<?php if ($dbError): ?>
    <div class="alert-box">
        <div class="row">
            <div>
                <h4>PostgreSQL connection failed</h4>
                <p><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<form class="filter-bar" method="get" action="index.php">
    <input type="hidden" name="page" value="companies">
    <div class="filter-controls">
        <div class="search-box">
            <svg class="icon"><use href="#i-search"/></svg>
            <input name="name_starts_with" type="text" value="<?= htmlspecialchars($nameStartsWith, ENT_QUOTES, 'UTF-8') ?>" placeholder="Name starts with...">
        </div>

        <input class="input-plain" name="bse_ticker" type="text" value="<?= htmlspecialchars($bseTicker, ENT_QUOTES, 'UTF-8') ?>" placeholder="BSE Ticker">
        <input class="input-plain" name="nse_ticker" type="text" value="<?= htmlspecialchars($nseTicker, ENT_QUOTES, 'UTF-8') ?>" placeholder="NSE Ticker">

        <select class="select-plain" name="status">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
            <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Active</option>
            <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <button class="btn btn-outline btn-sm" type="submit">Filter</button>
        <a class="btn btn-outline btn-sm" href="index.php?<?= htmlspecialchars(multipieCompanyQuery(['export' => 'csv']), ENT_QUOTES, 'UTF-8') ?>">
            <svg class="icon icon-sm"><use href="#i-download"/></svg>
            Export CSV
        </a>
    </div>
</form>

<div class="filter-count">
    Showing
    <b><?= $totalCompanies ? (($currentPage - 1) * $perPage + 1) : 0 ?> - <?= min($currentPage * $perPage, $totalCompanies) ?></b>
    of <span><?= number_format($totalCompanies) ?></span> Companies
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>BSE Ticker</th>
                <th>NSE Ticker</th>
                <th>ISIN</th>
                <th>URL Slug</th>
                <th>Status</th>
                <th>Created</th>
                <th>Updated</th>
                <th class="right">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$companies): ?>
            <tr class="empty-row">
                <td colspan="10"><?= $dbError ? 'Unable to load companies from PostgreSQL.' : 'No companies found matching the selected filters.' ?></td>
            </tr>
        <?php endif; ?>

        <?php foreach ($companies as $c): 
            $statusLabel = multipieCompanyStatusLabel($c['status'] ?? 1);
        ?>
            <tr>
                <td><?= htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><strong><?= htmlspecialchars($c['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></td>
                <td><?= htmlspecialchars($c['bse_ticker'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($c['nse_ticker'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($c['isin'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($c['url_slug'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <span class="status-badge <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="dot-status <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>"></span>
                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td><?= htmlspecialchars(multipieFormatDate($c['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(multipieFormatDate($c['updated_at']), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="right">
                    <div class="row-actions">
                        <button type="button" class="mini-btn" onclick='openCompanyModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>Edit</button>
                        <button type="button" class="mini-btn danger" disabled title="Delete is intentionally disabled for corporate data.">Delete</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a class="btn btn-outline btn-sm" href="index.php?<?= htmlspecialchars(multipieCompanyQuery(['p' => $currentPage - 1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>

        <span class="pagination-info">Page <?= $currentPage ?> of <?= $totalPages ?></span>

        <?php if ($currentPage < $totalPages): ?>
            <a class="btn btn-outline btn-sm" href="index.php?<?= htmlspecialchars(multipieCompanyQuery(['p' => $currentPage + 1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ================================================================
     ADD NEW COMPANY MODAL
     ================================================================ -->
<div id="add-company-modal" class="user-popup-overlay" hidden>
    <div class="user-popup wide" role="dialog" aria-modal="true" aria-labelledby="add-company-modal-title">
        <div class="user-popup-header">
            <div>
                <h2 id="add-company-modal-title">Add New Company</h2>
                <p>Enter company information for display only.</p>
            </div>
            <button type="button" class="user-popup-close" onclick="closeAddCompanyModal()" aria-label="Close">&times;</button>
        </div>
        <form id="add-company-form" class="user-popup-grid">
            <div class="field"><label for="add-company-id">ID</label><input id="add-company-id" type="text" value="Auto generated" disabled></div>
            <div class="field"><label for="add-company-name">Name</label><input id="add-company-name" name="name" type="text" placeholder="Enter company name"></div>
            <div class="field"><label for="add-company-bse">BSE Ticker</label><input id="add-company-bse" name="bse_ticker" type="text" placeholder="Enter BSE ticker"></div>
            <div class="field"><label for="add-company-nse">NSE Ticker</label><input id="add-company-nse" name="nse_ticker" type="text" placeholder="Enter NSE ticker"></div>
            <div class="field"><label for="add-company-isin">ISIN</label><input id="add-company-isin" name="isin" type="text" placeholder="Enter ISIN"></div>
            <div class="field"><label for="add-company-short-name">Short Name</label><input id="add-company-short-name" name="short_name" type="text" placeholder="Enter short name"></div>
            <div class="field"><label for="add-company-slug">URL Slug</label><input id="add-company-slug" name="url_slug" type="text" placeholder="Enter URL slug"></div>
            <div class="field"><label for="add-company-series">Series</label><input id="add-company-series" name="series" type="text" placeholder="Enter series"></div>
            <div class="field"><label for="add-company-relevance">Relevance</label><input id="add-company-relevance" name="relevance" type="number" placeholder="0"></div>
            <div class="field">
                <label for="add-company-status">Status</label>
                <select id="add-company-status" name="status">
                    <option value="0">Active</option>
                    <option value="1">Inactive</option>
                </select>
            </div>
            <div class="field"><label for="add-company-instrument">Instrument ID</label><input id="add-company-instrument" name="instrument_id" type="text" placeholder="Enter instrument ID"></div>
            <div class="field"><label for="add-company-sector">Sector ID</label><input id="add-company-sector" name="sector_id" type="text" placeholder="Enter sector ID"></div>
            <div class="field"><label for="add-company-created">Created</label><input id="add-company-created" type="text" value="Not created yet" disabled></div>
            <div class="field"><label for="add-company-updated">Updated</label><input id="add-company-updated" type="text" value="Not updated yet" disabled></div>
            <div class="field full"><label for="add-company-seo">SEO Description</label><textarea id="add-company-seo" name="seo_desc" rows="3" placeholder="Enter SEO description"></textarea></div>
        </form>
        <div class="user-popup-footer">
            <button type="button" class="btn btn-outline" onclick="closeAddCompanyModal()">Close</button>
            <button type="button" class="btn btn-navy" disabled title="Company creation is intentionally disabled for corporate data.">Save Company</button>
        </div>
    </div>
</div>

<!-- ================================================================
     EDIT COMPANY MODAL
     ================================================================ -->
<div id="company-modal" class="user-popup-overlay" hidden>
    <div class="user-popup wide" role="dialog" aria-modal="true" aria-labelledby="company-modal-title">
        <div class="user-popup-header">
            <div>
                <h2 id="company-modal-title">Edit Company</h2>
                <p>Existing company information — display only.</p>
            </div>
            <button type="button" class="user-popup-close" onclick="closeCompanyModal()" aria-label="Close">&times;</button>
        </div>
        <div class="user-popup-grid">
            <div class="field"><label>ID</label><input id="modal-id" disabled type="text"></div>
            <div class="field"><label>Name</label><input id="modal-name" type="text"></div>
            <div class="field"><label>BSE Ticker</label><input id="modal-bse-ticker" type="text"></div>
            <div class="field"><label>NSE Ticker</label><input id="modal-nse-ticker" type="text"></div>
            <div class="field"><label>ISIN</label><input id="modal-isin" type="text"></div>
            <div class="field"><label>Short Name</label><input id="modal-short-name" type="text"></div>
            <div class="field"><label>URL Slug</label><input id="modal-url-slug" type="text"></div>
            <div class="field"><label>Series</label><input id="modal-series" type="text"></div>
            <div class="field"><label>Relevance</label><input id="modal-relevance" type="number"></div>
            <div class="field">
                <label>Status</label>
                <select id="modal-status">
                    <option value="0">Active</option>
                    <option value="1">Inactive</option>
                </select>
            </div>
            <div class="field"><label>Instrument ID</label><input id="modal-instrument-id" type="text"></div>
            <div class="field"><label>Sector ID</label><input id="modal-sector-id" type="text"></div>
            <div class="field"><label>Created</label><input id="modal-created" disabled type="text"></div>
            <div class="field"><label>Updated</label><input id="modal-updated" disabled type="text"></div>
            <div class="field full"><label>SEO Description</label><textarea id="modal-seo-desc" rows="3"></textarea></div>
        </div>
        <div class="user-popup-footer">
            <button type="button" class="btn btn-outline" onclick="closeCompanyModal()">Close</button>
            <button type="button" class="btn btn-navy" disabled title="Save is intentionally disabled.">Save Changes</button>
        </div>
    </div>
</div>

<script>
const companyEditModal = document.getElementById('company-modal');
const companyAddModal = document.getElementById('add-company-modal');
const companyAddForm = document.getElementById('add-company-form');

const companyModalFields = {
    'modal-id': 'id',
    'modal-name': 'name',
    'modal-bse-ticker': 'bse_ticker',
    'modal-nse-ticker': 'nse_ticker',
    'modal-isin': 'isin',
    'modal-short-name': 'short_name',
    'modal-url-slug': 'url_slug',
    'modal-series': 'series',
    'modal-relevance': 'relevance',
    'modal-status': 'status',
    'modal-instrument-id': 'instrument_id',
    'modal-sector-id': 'sector_id',
    'modal-created': 'created_at',
    'modal-updated': 'updated_at',
    'modal-seo-desc': 'seo_desc'
};

function toggleCompanyModal(modal, show) {
    if (!modal) return;
    modal.hidden = !show;
    document.body.style.overflow = show ? 'hidden' : '';
}

function openAddCompanyModal() {
    if (companyAddForm) companyAddForm.reset();
    toggleCompanyModal(companyAddModal, true);
}

function closeAddCompanyModal() {
    toggleCompanyModal(companyAddModal, false);
}

function openCompanyModal(company) {
    for (const [elementId, key] of Object.entries(companyModalFields)) {
        const el = document.getElementById(elementId);
        if (el) {
            el.value = company[key] ?? '';
        }
    }
    toggleCompanyModal(companyEditModal, true);
}

function closeCompanyModal() {
    toggleCompanyModal(companyEditModal, false);
}

[companyEditModal, companyAddModal].forEach(modal => {
    modal?.addEventListener('click', e => {
        if (e.target === modal) toggleCompanyModal(modal, false);
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeCompanyModal();
        closeAddCompanyModal();
    }
});
</script>