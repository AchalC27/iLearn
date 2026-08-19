<?php
/*
 |--------------------------------------------------------------------------
 | Showcases
 |--------------------------------------------------------------------------
 | Database: multipie_main_prod
 | Table:    public.showcases
 |
 | This page is intentionally READ-ONLY.
 | Add / Edit / Delete UI is present, but no corporate database mutation is
 | performed from these buttons.
 |--------------------------------------------------------------------------
 */

$dbError = null;
$showcases = [];
$totalShowcases = 0;
$perPage = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$totalPages = 1;

try {
    $pdo = getAppDb();

    $totalShowcases = (int)$pdo->query(
        'SELECT COUNT(*) FROM public.showcases'
    )->fetchColumn();

    $totalPages = max(1, (int)ceil($totalShowcases / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $perPage;

    $stmt = $pdo->prepare('
        SELECT
            id,
            title,
            display_type,
            display_sequence,
            item_id,
            item_type,
            url_slug,
            meta_info,
            created_at,
            updated_at,
            status
        FROM public.showcases
        ORDER BY display_sequence ASC NULLS LAST, id ASC
        LIMIT :limit OFFSET :offset
    ');

    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $showcases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $showcases = [];
    $totalShowcases = 0;
    $totalPages = 1;
    $currentPage = 1;
}

function showcaseStatusLabel($status): string
{
    return (int)$status === 0 ? 'Active' : 'Inactive';
}

function showcaseDate($value): string
{
    if (!$value) {
        return '-';
    }

    try {
        return (new DateTime((string)$value))->format('d M Y');
    } catch (Throwable $e) {
        return (string)$value;
    }
}

function showcaseMeta($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function showcaseQuery(array $overrides = []): string
{
    $query = [
        'page' => 'showcases',
        'p' => (int)($_GET['p'] ?? 1),
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query($query);
}
?>

<section class="view-header">
    <div>
        <h1>
            Showcases
            <span class="count-pill-navy"><?= number_format($totalShowcases) ?> Total</span>
        </h1>
        <p class="sub">Manage MultiPie showcases and their display configuration.</p>
    </div>

    <button type="button" class="btn btn-orange" onclick="openShowcaseModal('add')">
        <span style="font-size:18px;margin-right:6px;">+</span>
        Add New Showcase
    </button>
</section>

<?php if ($dbError): ?>
    <div class="alert-box">
        <div class="row">
            <div>
                <h4>PostgreSQL connection failed</h4>
                <p><?= e($dbError) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="filter-count">
    Showing
    <b>
        <?= $totalShowcases > 0 ? (($currentPage - 1) * $perPage + 1) : 0 ?>
        -
        <?= min($currentPage * $perPage, $totalShowcases) ?>
    </b>
    of
    <span><?= number_format($totalShowcases) ?></span>
    Showcases
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Status</th>
                <th>Display Type</th>
                <th>Sequence</th>
                <th>Item Type</th>
                <th class="right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$showcases): ?>
            <tr class="empty-row">
                <td colspan="6">
                    <?= $dbError ? 'Unable to load showcases from PostgreSQL.' : 'No showcases found.' ?>
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($showcases as $showcase): ?>
            <?php
                $statusLabel = showcaseStatusLabel($showcase['status'] ?? null);
                $meta = showcaseMeta($showcase['meta_info'] ?? null);
                $showcaseForJs = $showcase;
                $showcaseForJs['_meta'] = $meta;
            ?>
            <tr>
                <td>
                    <strong><?= e($showcase['title'] ?? '-') ?></strong>
                </td>

                <td>
                    <span class="status-badge <?= e($statusLabel) ?>">
                        <span class="dot-status <?= e($statusLabel) ?>"></span>
                        <?= e($statusLabel) ?>
                    </span>
                </td>

                <td><?= e($showcase['display_type'] ?? '-') ?></td>
                <td><?= e($showcase['display_sequence'] ?? '-') ?></td>
                <td><?= e($showcase['item_type'] ?? '-') ?></td>

                <td class="right">
                    <div class="row-actions">
                        <button
                            type="button"
                            class="mini-btn"
                            onclick='openShowcaseModal("edit", <?= json_encode(
                                $showcaseForJs,
                                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                            ) ?>)'
                        >Edit</button>
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
            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(showcaseQuery(['p' => $currentPage - 1])) ?>"
            >Previous</a>
        <?php endif; ?>

        <span class="pagination-info">
            Page <?= $currentPage ?> of <?= $totalPages ?>
        </span>

        <?php if ($currentPage < $totalPages): ?>
            <a
                class="btn btn-outline btn-sm"
                href="index.php?<?= e(showcaseQuery(['p' => $currentPage + 1])) ?>"
            >Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ================================================================
     SHOWCASE MODAL
     UI only. Save/Update does NOT write to PostgreSQL.
================================================================ -->
<div id="showcase-modal" class="user-popup-overlay" hidden>
    <div
        class="user-popup showcase-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="showcase-modal-title"
    >
        <div class="user-popup-header">
            <div>
                <h2 id="showcase-modal-title">Add New Showcase</h2>
                <p id="showcase-modal-subtitle">
                    Enter showcase information for display only.
                </p>
            </div>

            <button
                type="button"
                class="user-popup-close"
                onclick="closeShowcaseModal()"
                aria-label="Close"
            >&times;</button>
        </div>

        <div class="user-popup-grid showcase-form-grid">
            <div class="field">
                <label for="showcase-id">ID</label>
                <input id="showcase-id" type="text" value="Auto generated" disabled>
            </div>

            <div class="field">
                <label for="showcase-title">Title</label>
                <input id="showcase-title" type="text" placeholder="Enter showcase title">
            </div>

            <div class="field">
                <label for="showcase-status">Status</label>
                <select id="showcase-status">
                    <option value="0">Active</option>
                    <option value="1">Inactive</option>
                </select>
            </div>

            <div class="field">
                <label for="showcase-display-type">Display Type</label>
                <input id="showcase-display-type" type="number" placeholder="Display type">
            </div>

            <div class="field">
                <label for="showcase-sequence">Display Sequence</label>
                <input id="showcase-sequence" type="number" placeholder="Display sequence">
            </div>

            <div class="field">
                <label for="showcase-item-type">Item Type</label>
                <input id="showcase-item-type" type="text" placeholder="Product / MutualFund / ...">
            </div>

            <div class="field">
                <label for="showcase-item-id">Item ID</label>
                <input id="showcase-item-id" type="number" placeholder="Item ID">
            </div>

            <div class="field">
                <label for="showcase-url-slug">URL Slug</label>
                <input id="showcase-url-slug" type="text" placeholder="URL slug">
            </div>

            <div class="field">
                <label for="showcase-created">Created</label>
                <input id="showcase-created" type="text" value="Not created yet" disabled>
            </div>

            <div class="field">
                <label for="showcase-updated">Updated</label>
                <input id="showcase-updated" type="text" value="Not updated yet" disabled>
            </div>

            <!-- Fields visible in the existing Showcase admin UI. These are
                 display-only client fields and are not written to the DB. -->
            <div class="field">
                <label for="showcase-share-text">Share Text</label>
                <input id="showcase-share-text" type="text" placeholder="Share text">
            </div>

            <div class="field">
                <label for="showcase-popup-text">Popup Text</label>
                <input id="showcase-popup-text" type="text" placeholder="Popup text">
            </div>

            <div class="field">
                <label for="showcase-action-tagline">Action Tagline</label>
                <input id="showcase-action-tagline" type="text" placeholder="Action tagline">
            </div>

            <div class="field">
                <label for="showcase-action-text">Action Text</label>
                <input id="showcase-action-text" type="text" placeholder="Action text">
            </div>

            <div class="field">
                <label for="showcase-button-text">Button Text</label>
                <input id="showcase-button-text" type="text" placeholder="Button text">
            </div>

            <div class="field">
                <label for="showcase-show-amount">Show Amount Field</label>
                <select id="showcase-show-amount">
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>

            <div class="field">
                <label for="showcase-interested-desc">Interested Popup Description</label>
                <textarea id="showcase-interested-desc" rows="3" placeholder="Interested popup description"></textarea>
            </div>

            <div class="field">
                <label for="showcase-redirect-url">Redirect URL</label>
                <input id="showcase-redirect-url" type="text" placeholder="Redirect URL">
            </div>

            <div class="field field-full">
                <label for="showcase-meta-info">Meta Info</label>
                <textarea id="showcase-meta-info" rows="5" placeholder="JSON meta information"></textarea>
            </div>
        </div>

        <div class="user-popup-footer">
            <button
                type="button"
                class="btn btn-outline"
                onclick="closeShowcaseModal()"
            >Close</button>

            <button
                type="button"
                class="btn btn-navy"
                onclick="displayOnlyShowcaseSave()"
            >
                <span id="showcase-save-text">Save Showcase</span>
            </button>
        </div>
    </div>
</div>

<script>
function openShowcaseModal(mode, showcase = null)
{
    const modal = document.getElementById('showcase-modal');
    const title = document.getElementById('showcase-modal-title');
    const subtitle = document.getElementById('showcase-modal-subtitle');
    const saveText = document.getElementById('showcase-save-text');

    const id = document.getElementById('showcase-id');
    const titleInput = document.getElementById('showcase-title');
    const status = document.getElementById('showcase-status');
    const displayType = document.getElementById('showcase-display-type');
    const sequence = document.getElementById('showcase-sequence');
    const itemType = document.getElementById('showcase-item-type');
    const itemId = document.getElementById('showcase-item-id');
    const urlSlug = document.getElementById('showcase-url-slug');
    const created = document.getElementById('showcase-created');
    const updated = document.getElementById('showcase-updated');
    const shareText = document.getElementById('showcase-share-text');
    const popupText = document.getElementById('showcase-popup-text');
    const actionTagline = document.getElementById('showcase-action-tagline');
    const actionText = document.getElementById('showcase-action-text');
    const buttonText = document.getElementById('showcase-button-text');
    const showAmount = document.getElementById('showcase-show-amount');
    const interestedDesc = document.getElementById('showcase-interested-desc');
    const redirectUrl = document.getElementById('showcase-redirect-url');
    const metaInfo = document.getElementById('showcase-meta-info');

    if (mode === 'add') {
        title.textContent = 'Add New Showcase';
        subtitle.textContent = 'Enter showcase information for display only.';
        saveText.textContent = 'Save Showcase';

        id.value = 'Auto generated';
        titleInput.value = '';
        status.value = '0';
        displayType.value = '';
        sequence.value = '';
        itemType.value = '';
        itemId.value = '';
        urlSlug.value = '';
        created.value = 'Not created yet';
        updated.value = 'Not updated yet';
        shareText.value = '';
        popupText.value = '';
        actionTagline.value = '';
        actionText.value = '';
        buttonText.value = '';
        showAmount.value = 'yes';
        interestedDesc.value = '';
        redirectUrl.value = '';
        metaInfo.value = '';
    } else {
        if (!showcase) return;

        title.textContent = 'Edit Showcase';
        subtitle.textContent = 'Existing showcase information — display only.';
        saveText.textContent = 'Save Changes';

        const meta = showcase._meta || {};

        id.value = showcase.id ?? '';
        titleInput.value = showcase.title ?? '';
        status.value = String(showcase.status ?? 0);
        displayType.value = showcase.display_type ?? '';
        sequence.value = showcase.display_sequence ?? '';
        itemType.value = showcase.item_type ?? '';
        itemId.value = showcase.item_id ?? '';
        urlSlug.value = showcase.url_slug ?? '';
        created.value = formatShowcaseDate(showcase.created_at);
        updated.value = formatShowcaseDate(showcase.updated_at);

        shareText.value = meta.share_text ?? meta.shareText ?? '';
        popupText.value = meta.popup_text ?? meta.popupText ?? '';
        actionTagline.value = meta.action_tagline ?? meta.actionTagline ?? '';
        actionText.value = meta.action_text ?? meta.actionText ?? '';
        buttonText.value = meta.button_text ?? meta.buttonText ?? '';

        const amount = meta.show_amount_field ?? meta.showAmountField;
        showAmount.value = amount === false || amount === 'no' ? 'no' : 'yes';

        interestedDesc.value =
            meta.interested_popup_desc ??
            meta.interestedPopupDesc ??
            '';

        redirectUrl.value = meta.redirect_url ?? meta.redirectUrl ?? '';
        metaInfo.value = formatMetaInfo(showcase.meta_info);
    }

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
}

function closeShowcaseModal()
{
    const modal = document.getElementById('showcase-modal');
    if (!modal) return;

    modal.hidden = true;
    document.body.style.overflow = '';
}

function displayOnlyShowcaseSave()
{
    // Intentionally empty: this page must not modify corporate data.
}

function formatShowcaseDate(value)
{
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

function formatMetaInfo(value)
{
    if (!value) return '';

    if (typeof value === 'string') {
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch (e) {
            return value;
        }
    }

    try {
        return JSON.stringify(value, null, 2);
    } catch (e) {
        return '';
    }
}

/* Close when clicking the dark overlay. */
document.addEventListener('click', function(event) {
    const modal = document.getElementById('showcase-modal');

    if (modal && event.target === modal) {
        closeShowcaseModal();
    }
});

/* Escape closes the popup. */
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('showcase-modal');

        if (modal && !modal.hidden) {
            closeShowcaseModal();
        }
    }
});
</script>
