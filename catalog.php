<?php
$page_title = 'Browse Events — EventHub';
require_once __DIR__ . '/header.php';

// ---- Filter / Search / Sort via GET ----
$search   = input('search');
$cat_id   = (int)input('category');
$status   = input('status');
$date_from = input('date_from');
$date_to   = input('date_to');
$sort      = input('sort');
$page      = max(1, (int)input('page', '1'));
$per_page  = 12;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(e.title LIKE ? OR e.description LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($cat_id > 0) {
    $where[]  = 'e.category_id = ?';
    $params[] = $cat_id;
}
if (in_array($status, ['draft','published','cancelled','completed'], true)) {
    $where[]  = 'e.status = ?';
    $params[] = $status;
} else {
    // Default: show published only for non-admins
    if (!is_admin()) {
        $where[] = 'e.status = "published"';
    }
}
if ($date_from !== '') {
    $where[]  = 'e.event_date >= ?';
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where[]  = 'e.event_date <= ?';
    $params[] = $date_to . ' 23:59:59';
}

$where_sql = implode(' AND ', $where);

// Count total
$count_stmt = db()->prepare("SELECT COUNT(*) FROM events e WHERE {$where_sql}");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();

$pg = paginate($total, $per_page, $page);

// Sort
$order = match($sort) {
    'name_asc'   => 'e.title ASC',
    'name_desc'  => 'e.title DESC',
    'date_desc'  => 'e.event_date DESC',
    default      => 'e.event_date ASC',
};

$sql = "SELECT e.*, u.username AS organizer, c.name AS category_name
        FROM events e
        JOIN users u ON u.id = e.owner_id
        LEFT JOIN categories c ON c.id = e.category_id
        WHERE {$where_sql}
        ORDER BY {$order}
        LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$categories = get_categories();

// Build base URL for pagination
$qs = $_GET;
unset($qs['page']);
$base_url = 'catalog.php?' . http_build_query($qs);
?>

<section class="section">
    <h1 class="page-title">Browse Events</h1>

    <!-- Filters -->
    <form method="GET" action="catalog.php" class="filter-bar">
        <div class="filter-group">
            <input type="text" name="search" placeholder="Search events..."
                   value="<?= sanitize($search) ?>" class="filter-input">
        </div>
        <div class="filter-group">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat_id === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= sanitize($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (is_admin()): ?>
        <div class="filter-group">
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach (['draft','published','cancelled','completed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
            <input type="date" name="date_from" value="<?= sanitize($date_from) ?>" placeholder="From">
        </div>
        <div class="filter-group">
            <input type="date" name="date_to" value="<?= sanitize($date_to) ?>" placeholder="To">
        </div>
        <div class="filter-group">
            <select name="sort">
                <option value="date_asc"  <?= $sort === 'date_asc' ? 'selected' : '' ?>>Date (earliest)</option>
                <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Date (latest)</option>
                <option value="name_asc"  <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('catalog.php') ?>" class="btn btn-outline btn-sm">Clear</a>
    </form>

    <!-- Results -->
    <?php if (empty($events)): ?>
        <div class="empty-state">
            <h3>No events found</h3>
            <p>Try adjusting your filters or search terms.</p>
        </div>
    <?php else: ?>
        <p class="result-count"><?= $total ?> event<?= $total !== 1 ? 's' : '' ?> found</p>
        <div class="event-grid">
            <?php foreach ($events as $ev): ?>
                <article class="event-card">
                    <div class="event-card-header">
                        <span class="event-category"><?= sanitize($ev['category_name'] ?? 'Uncategorized') ?></span>
                        <span class="event-status status-<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span>
                    </div>
                    <h3><a href="<?= url('detail.php?id=' . $ev['id']) ?>"><?= sanitize($ev['title']) ?></a></h3>
                    <p class="event-meta">
                        <span><?= date('M j, Y \a\t g:i A', strtotime($ev['event_date'])) ?></span>
                        <span><?= sanitize($ev['location']) ?></span>
                    </p>
                    <p class="event-excerpt"><?= sanitize(mb_strimwidth($ev['description'], 0, 120, '...')) ?></p>
                    <div class="event-card-footer">
                        <span class="event-organizer">By <?= sanitize($ev['organizer']) ?></span>
                        <a href="<?= url('detail.php?id=' . $ev['id']) ?>" class="btn btn-sm btn-outline">View</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?= pagination_html($pg, $base_url) ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
