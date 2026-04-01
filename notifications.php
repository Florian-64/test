<?php
$page_title = 'Notifications — EventHub';
require_once __DIR__ . '/header.php';
require_login();

$uid = $_SESSION['user_id'];

// Mark all as read if requested
if (isset($_GET['mark_read'])) {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    set_flash('success', 'All notifications marked as read.');
    header('Location: ' . url('notifications.php'));
    exit;
}

// Fetch notifications
$page     = max(1, (int)input('page', '1'));
$per_page = 20;

$count_stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$count_stmt->execute([$uid]);
$total = (int)$count_stmt->fetchColumn();
$pg = paginate($total, $per_page, $page);

$stmt = db()->prepare(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?'
);
$stmt->execute([$uid, $pg['per_page'], $pg['offset']]);
$notifications = $stmt->fetchAll();
?>

<section class="section">
    <div class="card-header-row">
        <h1 class="page-title">Notifications</h1>
        <?php if ($total > 0): ?>
            <a href="<?= url('notifications.php?mark_read=1') ?>" class="btn btn-outline btn-sm">Mark All as Read</a>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <h3>No notifications</h3>
            <p>You're all caught up.</p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notifications as $n): ?>
                <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>">
                    <div class="notification-body">
                        <span class="notif-type notif-<?= sanitize($n['type']) ?>"><?= ucfirst(str_replace('_', ' ', $n['type'])) ?></span>
                        <p><?= sanitize($n['message']) ?></p>
                        <time><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></time>
                    </div>
                    <?php if ($n['link']): ?>
                        <a href="<?= url($n['link']) ?>" class="btn btn-sm btn-outline">View</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?= pagination_html($pg, 'notifications.php') ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
