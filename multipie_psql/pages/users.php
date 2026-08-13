<?php
$q = trim((string)($_GET['q'] ?? ''));
$role = $_GET['role'] ?? 'all';
$status = $_GET['status'] ?? 'all';

$filteredUsers = array_values(array_filter($users, function (array $u) use ($q, $role, $status): bool {
    if ($role !== 'all' && strtolower((string)($u['role'] ?? '')) !== strtolower($role)) return false;
    if ($status !== 'all' && strtolower((string)($u['status'] ?? '')) !== strtolower($status)) return false;
    if ($q !== '') {
        $haystack = strtolower(implode(' ', [
            $u['id'] ?? '', $u['username'] ?? '', $u['first_name'] ?? '',
            $u['last_name'] ?? '', $u['mobile'] ?? '', $u['email'] ?? '', $u['uid'] ?? ''
        ]));
        if (strpos($haystack, strtolower($q)) === false) return false;
    }
    return true;
}));
$totalUsers = count($filteredUsers);
?>

<section class="view-header">
<div>
<h1>User Directory &amp; Learner Records <span class="count-pill-navy"><?= $totalUsers ?> Total</span></h1>
<p class="sub">Manage MultiPie learner identities, instructor permissions, and CMS administrative access.</p>
</div>
<a class="btn btn-orange" href="index.php?page=users"><svg class="icon icon-sm"><use href="#i-user-plus"/></svg>Add New User</a>
</section>

<form class="filter-bar" method="get" action="index.php">
<input type="hidden" name="page" value="users">
<div class="filter-controls">
<div class="search-box"><svg class="icon"><use href="#i-search"/></svg><input name="q" type="text" value="<?= e($q) ?>" placeholder="Search by ID, username, name, mobile, email, UID..."></div>
<select class="select-plain" name="role"><option value="all">All Roles</option><option value="user" <?= strtolower((string)$role)==='user'?'selected':'' ?>>User</option><option value="admin" <?= strtolower((string)$role)==='admin'?'selected':'' ?>>Admin</option></select>
<select class="select-plain" name="status"><option value="all">All Status</option><option value="active" <?= strtolower((string)$status)==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= strtolower((string)$status)==='inactive'?'selected':'' ?>>Inactive</option></select>
<button class="btn btn-outline btn-sm" type="submit">Filter</button>
<a class="btn btn-outline btn-sm" href="index.php?page=users"><svg class="icon icon-sm"><use href="#i-download"/></svg>Export CSV</a>
</div>
</form>

<div class="filter-count">Showing <b><?= $totalUsers ?></b> of <span><?= count($users) ?></span> Users</div>

<div class="table-wrap">
<table>
<thead><tr><th>ID</th><th>Username</th><th>First Name</th><th>Last Name</th><th>Mobile</th><th>Email</th><th>Role</th><th>Status</th><th>UID</th><th>Created</th><th>Updated</th></tr></thead>
<tbody>
<?php if (!$filteredUsers): ?><tr class="empty-row"><td colspan="11">No users found matching filter criteria.</td></tr><?php endif; ?>
<?php foreach ($filteredUsers as $u): ?>
<tr>
<td><?= e($u['id'] ?? '') ?></td>
<td><?= e($u['username'] ?? '') ?></td>
<td><?= e($u['first_name'] ?? '') ?></td>
<td><?= e($u['last_name'] ?? '') ?></td>
<td><?= e($u['mobile'] ?? '') ?></td>
<td><?= e($u['email'] ?? '') ?></td>
<td><span class="role-badge <?= e(ucfirst((string)($u['role'] ?? 'user'))) ?>"><?= e($u['role'] ?? '') ?></span></td>
<td><span class="status-badge <?= e(ucfirst((string)($u['status'] ?? 'active'))) ?>"><span class="dot-status <?= e(ucfirst((string)($u['status'] ?? 'active'))) ?>"></span><?= e($u['status'] ?? '') ?></span></td>
<td><?= e($u['uid'] ?? '') ?></td>
<td style="color:var(--slate-500);font-size:11px;"><?= e($u['created_at'] ?? '') ?></td>
<td style="color:var(--slate-500);font-size:11px;"><?= e($u['updated_at'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
