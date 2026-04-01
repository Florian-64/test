<?php
require_once __DIR__ . '/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    set_flash('error', 'Event not found.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

$user = current_user();
if ($user['id'] !== $event['owner_id'] && $user['role'] !== 'admin') {
    set_flash('error', 'You can only edit your own events.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

$errors      = [];
$title       = $event['title'];
$description = $event['description'];
$location    = $event['location'];
$event_date  = date('Y-m-d\TH:i', strtotime($event['event_date']));
$category_id = (int)$event['category_id'];
$tag_ids     = array_column(get_event_tags($id), 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid request.';
    } else {
        $title       = input('title');
        $description = input('description');
        $location    = input('location');
        $event_date  = input('event_date');
        $category_id = (int)input('category_id');
        $tag_ids     = array_map('intval', $_POST['tags'] ?? []);

        if ($title === '')       $errors[] = 'Title is required.';
        if ($description === '') $errors[] = 'Description is required.';
        if ($location === '')    $errors[] = 'Location is required.';
        if ($event_date === '')  $errors[] = 'Event date is required.';

        if (empty($errors)) {
            $cat = $category_id > 0 ? $category_id : null;
            $upd = db()->prepare(
                'UPDATE events SET title = ?, description = ?, location = ?, event_date = ?, category_id = ? WHERE id = ?'
            );
            $upd->execute([$title, $description, $location, $event_date, $cat, $id]);

            // Sync tags
            db()->prepare('DELETE FROM event_tags WHERE event_id = ?')->execute([$id]);
            if ($tag_ids) {
                $tag_stmt = db()->prepare('INSERT IGNORE INTO event_tags (event_id, tag_id) VALUES (?, ?)');
                foreach ($tag_ids as $tid) {
                    $tag_stmt->execute([$id, $tid]);
                }
            }

            set_flash('success', 'Event updated.');
            header('Location: ' . url('detail.php?id=' . $id));
            exit;
        }
    }
}

$page_title = 'Edit Event — EventHub';
require_once __DIR__ . '/header.php';
$categories = get_categories();
$all_tags   = get_tags();
?>

<section class="section">
    <h1 class="page-title">Edit Event</h1>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <?php foreach ($errors as $e): ?>
                <div><?= sanitize($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="event-form" novalidate>
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group form-group-lg">
                <label for="title">Event Title *</label>
                <input type="text" id="title" name="title" value="<?= sanitize($title) ?>" required>
            </div>
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description *</label>
            <textarea id="description" name="description" rows="6" required><?= sanitize($description) ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="location">Location *</label>
                <input type="text" id="location" name="location" value="<?= sanitize($location) ?>" required>
            </div>
            <div class="form-group">
                <label for="event_date">Date &amp; Time *</label>
                <input type="datetime-local" id="event_date" name="event_date" value="<?= sanitize($event_date) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Tags</label>
            <div class="checkbox-group">
                <?php foreach ($all_tags as $tag): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>"
                            <?= in_array((int)$tag['id'], $tag_ids) ? 'checked' : '' ?>>
                        <?= sanitize($tag['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= url('detail.php?id=' . $id) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
