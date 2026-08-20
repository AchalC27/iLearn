<?php
/*
|--------------------------------------------------------------------------
| TOPICS (Read-Only View)
|--------------------------------------------------------------------------
*/

$pdo = getAppDb();
$perPage = 10;
$page = max(1, (int)($_GET['p'] ?? 1));
$titleFilter = trim((string)($_GET['title'] ?? ''));

$topics = [];
$totalTopics = 0;
$totalPages = 1;
$offset = 0;
$dbError = null;

function topicStatusLabel($status): string {
    return (int)$status === 1 ? 'Active' : 'Inactive';
}

function topicPageUrl(int $page, string $title): string {
    $params = ['page' => 'topics', 'p' => $page];
    if ($title !== '') {
        $params['title'] = $title;
    }
    return 'index.php?' . http_build_query($params);
}

try {
    $where = [];
    $params = [];

    if ($titleFilter !== '') {
        $where[] = 'title ILIKE :title';
        $params[':title'] = '%' . $titleFilter . '%';
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count Total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM public.topics {$whereSql}");
    $countStmt->execute($params);
    $totalTopics = (int)$countStmt->fetchColumn();

    // Pagination bounds
    $totalPages = max(1, (int)ceil($totalTopics / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // Fetch Records
    $sql = "
        SELECT id, title, parent_topic_id, status, created_at, updated_at
        FROM public.topics
        {$whereSql}
        ORDER BY title ASC NULLS LAST, id ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<!-- ==================== PAGE HEADER ==================== -->
<section class="view-header">
    <div>
        <h1>
            List of Topics
            <span class="count-pill-navy"><?= number_format($totalTopics) ?> Total</span>
        </h1>
        <p class="sub">Manage MultiPie topics.</p>
    </div>
    <button type="button" class="btn btn-orange" id="btnNewTopic">+ New Topic</button>
</section>

<!-- ==================== DATABASE ERROR ==================== -->
<?php if ($dbError): ?>
    <div class="alert-box">
        <strong>PostgreSQL connection failed</strong>
        <p><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
<?php endif; ?>

<!-- ==================== FILTER ==================== -->
<form class="filter-bar" method="get" action="index.php">
    <input type="hidden" name="page" value="topics">
    <div class="filter-controls">
        <input 
            type="text" 
            name="title" 
            value="<?= htmlspecialchars($titleFilter, ENT_QUOTES, 'UTF-8') ?>" 
            placeholder="Title contains"
        >
        <button type="submit" class="btn btn-outline btn-sm">Search</button>
        <a href="index.php?page=topics" class="btn btn-outline btn-sm">View All</a>
    </div>
</form>

<!-- ==================== RESULT COUNT ==================== -->
<div class="filter-count">
    Showing 
    <b><?= $totalTopics > 0 ? ($offset + 1) : 0 ?> to <?= min($offset + $perPage, $totalTopics) ?></b> 
    of 
    <span><?= number_format($totalTopics) ?></span> Topics
</div>

<!-- ==================== TOPICS TABLE ==================== -->
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Logo</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($topics)): ?>
                <tr class="empty-row">
                    <td colspan="4">No topics found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($topics as $topic): 
                    $status = topicStatusLabel($topic['status'] ?? 0);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($topic['title'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>-</td>
                        <td>
                            <span class="status-badge <?= strtolower($status) ?>">
                                <span class="dot-status <?= strtolower($status) ?>"></span>
                                <?= $status ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button 
                                    type="button" 
                                    class="mini-btn js-edit-topic" 
                                    data-topic="<?= htmlspecialchars(json_encode($topic), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ==================== PAGINATION ==================== -->
<?php if ($totalPages > 1): 
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a class="pagination-btn" href="<?= htmlspecialchars(topicPageUrl($page - 1, $titleFilter), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        <?php endif; ?>

        <?php if ($startPage > 1): ?>
            <a class="pagination-btn" href="<?= htmlspecialchars(topicPageUrl(1, $titleFilter), ENT_QUOTES, 'UTF-8') ?>">1</a>
            <?php if ($startPage > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a 
                class="pagination-btn <?= $i === $page ? 'active' : '' ?>" 
                href="<?= htmlspecialchars(topicPageUrl($i, $titleFilter), ENT_QUOTES, 'UTF-8') ?>"
            >
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
            <a class="pagination-btn" href="<?= htmlspecialchars(topicPageUrl($totalPages, $titleFilter), ENT_QUOTES, 'UTF-8') ?>"><?= $totalPages ?></a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
            <a class="pagination-btn" href="<?= htmlspecialchars(topicPageUrl($page + 1, $titleFilter), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ==================== MODAL ==================== -->
<div id="topicModal" class="user-popup-overlay" hidden>
    <div class="user-popup" role="dialog" aria-modal="true">
        <div class="user-popup-header">
            <div>
                <h2 id="topicModalTitle">New Topic</h2>
                <p>Enter topic information for display only.</p>
            </div>
            <button type="button" class="user-popup-close js-modal-close">&times;</button>
        </div>

        <div class="user-popup-grid">
            <div class="field">
                <label for="topicModalId">ID</label>
                <input type="text" id="topicModalId" disabled>
            </div>
            <div class="field">
                <label for="topicModalTitleField">Title</label>
                <input type="text" id="topicModalTitleField" placeholder="Enter topic title">
            </div>
            <div class="field">
                <label for="topicModalParent">Parent Topic ID</label>
                <input type="number" id="topicModalParent" placeholder="Enter parent topic ID">
            </div>
            <div class="field">
                <label for="topicModalStatus">Status</label>
                <select id="topicModalStatus">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="field">
                <label for="topicModalCreated">Created</label>
                <input type="text" id="topicModalCreated" disabled>
            </div>
            <div class="field">
                <label for="topicModalUpdated">Updated</label>
                <input type="text" id="topicModalUpdated" disabled>
            </div>
        </div>

        <div class="user-popup-footer">
            <button type="button" class="btn btn-outline js-modal-close">Close</button>
            <button type="button" class="btn btn-orange" id="btnSaveTopic">Save Topic</button>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('topicModal');
    const fields = {
        titleHeader: document.getElementById('topicModalTitle'),
        id: document.getElementById('topicModalId'),
        title: document.getElementById('topicModalTitleField'),
        parent: document.getElementById('topicModalParent'),
        status: document.getElementById('topicModalStatus'),
        created: document.getElementById('topicModalCreated'),
        updated: document.getElementById('topicModalUpdated')
    };

    const toggleModal = (show) => {
        modal.hidden = !show;
        document.body.style.overflow = show ? 'hidden' : '';
    };

    const setModalData = (data = {}) => {
        const isEdit = Boolean(data.id);
        fields.titleHeader.textContent = isEdit ? 'Edit Topic' : 'New Topic';
        fields.id.value = data.id ?? 'Auto generated';
        fields.title.value = data.title ?? '';
        fields.parent.value = data.parent_topic_id ?? '';
        fields.status.value = data.status ?? '1';
        fields.created.value = data.created_at ?? 'Not created yet';
        fields.updated.value = data.updated_at ?? 'Not updated yet';
        toggleModal(true);
    };

    document.getElementById('btnNewTopic')?.addEventListener('click', () => setModalData());

    document.querySelectorAll('.js-edit-topic').forEach(btn => {
        btn.addEventListener('click', () => {
            try {
                const topic = JSON.parse(btn.dataset.topic);
                setModalData(topic);
            } catch (err) {
                console.error('Failed to parse topic data', err);
            }
        });
    });

    document.querySelectorAll('.js-modal-close').forEach(btn => {
        btn.addEventListener('click', () => toggleModal(false));
    });

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) toggleModal(false);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) toggleModal(false);
    });

    document.getElementById('btnSaveTopic')?.addEventListener('click', () => {
        alert('Save is currently disabled. No changes have been made to the database.');
    });
})();
</script>