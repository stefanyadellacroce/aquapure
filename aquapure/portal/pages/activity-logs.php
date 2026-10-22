<?php
if (!is_admin()) {
    http_response_code(403);
    exit("Admin access required.");
}

$query = trim((string) ($_GET["q"] ?? ""));
$sql = "SELECT a.*, u.full_name, u.username
        FROM activity_logs a
        LEFT JOIN users u ON u.user_id = a.user_id";
$params = [];
if ($query !== "") {
    $sql .= " WHERE a.action LIKE ? OR a.details LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?";
    $term = "%" . $query . "%";
    $params = [$term, $term, $term, $term];
}
$sql .= " ORDER BY a.created_at DESC, a.activity_id DESC LIMIT 250";
$statement = db()->prepare($sql);
$statement->execute($params);
$activities = $statement->fetchAll();
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Administrator only</p>
        <h1 class="section-title mb-1">Activity logs</h1>
        <p class="text-secondary mb-0">Recent account, order, payment, and inventory activity.</p>
    </div>
</header>

<section class="panel-card">
    <form method="get" class="row g-2 mb-3 filter-bar">
        <input type="hidden" name="page" value="activity-logs">
        <div class="col-sm-8 col-lg-5">
            <label class="form-label" for="activitySearch">Search activity</label>
            <div class="search-box w-100"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" id="activitySearch" name="q" value="<?= e($query) ?>" placeholder="Search action, details, or user"></div>
        </div>
        <div class="col-sm-auto">
            <button class="btn btn-outline-primary">Search</button>
            <?php if ($query !== ""): ?>
                <a class="btn btn-light" href="<?= e(url("portal/index.php?page=activity-logs")) ?>">Clear</a>
            <?php endif; ?>
        </div>
    </form>
    <p class="small text-secondary">Showing up to 250 matching entries, newest first.</p>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Activity Logs table">
        <table data-layout="cards" role="table" class="table align-middle mb-0"><caption class="visually-hidden">Activity Logs</caption>
            <thead>
                <tr><th scope="col">Date</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Details</th><th scope="col">IP address</th></tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td class="text-nowrap" data-column-label="Date"><div class="table-cell-value"><?= e(date("M d, Y g:i A", strtotime($activity["created_at"]))) ?></div></td>
                        <td data-column-label="User"><div class="table-cell-value"><?= e($activity["full_name"] ?? "System") ?><div class="small text-secondary"><?= e($activity["username"] ?? "") ?></div></div></td>
                        <td data-column-label="Action"><div class="table-cell-value"><?= e($activity["action"]) ?></div></td>
                        <td data-column-label="Details"><div class="table-cell-value"><?= e($activity["details"] ?? "—") ?></div></td>
                        <td class="small text-secondary" data-column-label="IP address"><div class="table-cell-value"><?= e($activity["ip_address"] ?? "—") ?></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$activities): ?>
                    <tr class="table-empty-row"><td colspan="5" class="empty-state"><?= $query !== "" ? "No activity matches this search. Clear the search to see recent entries." : "No activity has been recorded yet." ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
