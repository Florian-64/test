<?php
$page_title = 'Admin Panel — EventHub';
require_once __DIR__ . '/header.php';
require_role('admin');

$tab = input('tab') ?: 'users';

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = input('action');

    // Update user role
    if ($action === 'update_role') {
        $uid  = (int)input('user_id');
        $role = input('role');
        if (in_array($role, ['user', 'organizer', 'admin'], true) && $uid !== $_SESSION['user_id']) {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
            set_flash('success', 'User role updated.');
        }
        header('Location: ' . url('admin.php?tab=users'));
        exit;
    }

    // Delete user
    if ($action === 'delete_user') {
        $uid = (int)input('user_id');
        if ($uid !== $_SESSION['user_id']) {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            set_flash('success', 'User deleted.');
        }
        header('Location: ' . url('admin.php?tab=users'));
        exit;
    }

    // Add category
    if ($action === 'add_category') {
        $name = input('name');
        if ($name !== '') {
            $stmt = db()->prepare('INSERT IGNORE INTO categories (name) VALUES (?)');
            $stmt->execute([$name]);
            set_flash('success', 'Category added.');
        }
        header('Location: ' . url('admin.php?tab=categories'));
        exit;
    }

    // Delete category
    if ($action === 'delete_category') {
        $cid = (int)input('category_id');
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$cid]);
        set_flash('success', 'Category deleted.');
        header('Location: ' . url('admin.php?tab=categories'));
        exit;
    }

    // Add tag
    if ($action === 'add_tag') {
        $name = input('name');
        if ($name !== '') {
            db()->prepare('INSERT IGNORE INTO tags (name) VALUES (?)')->execute([$name]);
            set_flash('success', 'Tag added.');
        }
        header('Location: ' . url('admin.php?tab=tags'));
        exit;
    }

    // Delete tag
    if ($action === 'delete_tag') {
        $tid = (int)input('tag_id');
        db()->prepare('DELETE FROM tags WHERE id = ?')->execute([$tid]);
        set_flash('success', 'Tag deleted.');
        header('Location: ' . url('admin.php?tab=tags'));
        exit;
    }

    // Delete event (admin moderation)
    if ($action === 'delete_event') {
        $eid = (int)input('event_id');
        db()->prepare('DELETE FROM events WHERE id = ?')->execute([$eid]);
        set_flash('success', 'Event deleted.');
        header('Location: ' . url('admin.php?tab=events'));
        exit;
    }

    // Export CSV
    if ($action === 'export_csv') {
        $events = db()->query(
            'SELECT e.id, e.title, e.description, e.location, e.event_date, e.status,
                    u.username AS organizer, c.name AS category
             FROM events e
             JOIN users u ON u.id = e.owner_id
             LEFT JOIN categories c ON c.id = e.category_id
             ORDER BY e.event_date DESC'
        )->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=events_export_' . date('Ymd') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Title', 'Description', 'Location', 'Date', 'Status', 'Organizer', 'Category']);
        foreach ($events as $ev) {
            fputcsv($out, $ev);
        }
        fclose($out);
        exit;
    }
}

// ---- Fetch data for tabs ----
$users      = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
$categories = get_categories();
$all_tags   = get_tags();
$all_events = db()->query(
    'SELECT e.*, u.username AS organizer FROM events e JOIN users u ON u.id = e.owner_id ORDER BY e.created_at DESC'
)->fetchAll();
?>

<section class="section">
    <h1 class="page-title">Admin Panel</h1>

    <div class="tabs">
        <a href="<?= url('admin.php?tab=users') ?>"      class="tab <?= $tab === 'users' ? 'active' : '' ?>">Users (<?= count($users) ?>)</a>
        <a href="<?= url('admin.php?tab=events') ?>"     class="tab <?= $tab === 'events' ? 'active' : '' ?>">Events (<?= count($all_events) ?>)</a>
        <a href="<?= url('admin.php?tab=categories') ?>" class="tab <?= $tab === 'categories' ? 'active' : '' ?>">Categories</a>
        <a href="<?= url('admin.php?tab=tags') ?>"       class="tab <?= $tab === 'tags' ? 'active' : '' ?>">Tags</a>
    </div>

    <!-- USERS TAB -->
    <?php if ($tab === 'users'): ?>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= sanitize($u['username']) ?></td>
                        <td><?= sanitize($u['email']) ?></td>
                        <td>
                            <form method="POST" action="" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="role" onchange="this.form.submit()" <?= $u['id'] === $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                    <?php foreach (['user', 'organizer', 'admin'] as $r): ?>
                                        <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" action="" class="inline-form"
                                  onsubmit="return confirm('Delete this user?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- EVENTS TAB -->
    <?php if ($tab === 'events'): ?>
    <div class="card">
        <div class="card-header-row">
            <h3>All Events</h3>
            <form method="POST" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="export_csv">
                <button type="submit" class="btn btn-sm btn-primary">Export CSV</button>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>Title</th><th>Organizer</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($all_events as $ev): ?>
                    <tr>
                        <td><?= $ev['id'] ?></td>
                        <td><a href="<?= url('detail.php?id=' . $ev['id']) ?>"><?= sanitize($ev['title']) ?></a></td>
                        <td><?= sanitize($ev['organizer']) ?></td>
                        <td><?= date('M j, Y', strtotime($ev['event_date'])) ?></td>
                        <td><span class="event-status status-<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span></td>
                        <td>
                            <a href="<?= url('edit.php?id=' . $ev['id']) ?>" class="btn btn-sm btn-outline">Edit</a>
                            <form method="POST" action="" class="inline-form"
                                  onsubmit="return confirm('Delete this event?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- CATEGORIES TAB -->
    <?php if ($tab === 'categories'): ?>
    <div class="card">
        <h3>Categories</h3>
        <form method="POST" action="" class="inline-add-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_category">
            <input type="text" name="name" placeholder="New category name" required>
            <button type="submit" class="btn btn-sm btn-primary">Add</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= $cat['id'] ?></td>
                        <td><?= sanitize($cat['name']) ?></td>
                        <td>
                            <form method="POST" action="" class="inline-form"
                                  onsubmit="return confirm('Delete this category?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAGS TAB -->
    <?php if ($tab === 'tags'): ?>
    <div class="card">
        <h3>Tags</h3>
        <form method="POST" action="" class="inline-add-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_tag">
            <input type="text" name="name" placeholder="New tag name" required>
            <button type="submit" class="btn btn-sm btn-primary">Add</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($all_tags as $tag): ?>
                    <tr>
                        <td><?= $tag['id'] ?></td>
                        <td><?= sanitize($tag['name']) ?></td>
                        <td>
                            <form method="POST" action="" class="inline-form"
                                  onsubmit="return confirm('Delete this tag?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_tag">
                                <input type="hidden" name="tag_id" value="<?= $tag['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
