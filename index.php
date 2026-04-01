<?php
$page_title = 'EventHub — Discover Events';
require_once __DIR__ . '/header.php';

// Fetch upcoming published events
$stmt = db()->prepare(
    'SELECT e.*, u.username AS organizer, c.name AS category_name
     FROM events e
     JOIN users u ON u.id = e.owner_id
     LEFT JOIN categories c ON c.id = e.category_id
     WHERE e.status = "published" AND e.event_date >= NOW()
     ORDER BY e.event_date ASC
     LIMIT 6'
);
$stmt->execute();
$featured = $stmt->fetchAll();

// Stats
$total_events = db()->query('SELECT COUNT(*) FROM events WHERE status = "published"')->fetchColumn();
$total_users  = db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_cats   = db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
?>

<section class="hero">
    <div class="hero-content">
        <h1>Discover, Create &amp; Manage Events</h1>
        <p>Your all-in-one platform for organizing and attending events that matter.</p>
        <div class="hero-actions">
            <a href="<?= url('catalog.php') ?>" class="btn btn-primary btn-lg">Browse Events</a>
            <?php if (!is_logged_in()): ?>
                <a href="<?= url('register.php') ?>" class="btn btn-outline btn-lg">Get Started</a>
            <?php elseif (is_organizer()): ?>
                <a href="<?= url('create.php') ?>" class="btn btn-outline btn-lg">Create Event</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="stats-bar">
    <div class="stat-item">
        <span class="stat-number"><?= $total_events ?></span>
        <span class="stat-label">Published Events</span>
    </div>
    <div class="stat-item">
        <span class="stat-number"><?= $total_users ?></span>
        <span class="stat-label">Members</span>
    </div>
    <div class="stat-item">
        <span class="stat-number"><?= $total_cats ?></span>
        <span class="stat-label">Categories</span>
    </div>
</section>

<?php if ($featured): ?>
<section class="section">
    <h2 class="section-title">Upcoming Events</h2>
    <div class="event-grid">
        <?php foreach ($featured as $ev): ?>
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
    <div class="section-cta">
        <a href="<?= url('catalog.php') ?>" class="btn btn-outline">View All Events</a>
    </div>
</section>
<?php else: ?>
<section class="section empty-state">
    <h2>No Upcoming Events</h2>
    <p>Check back soon or create your own event!</p>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>