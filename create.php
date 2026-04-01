<?php
require_once __DIR__ . '/functions.php';
require_login();
if (!is_organizer()) {
    set_flash('error', 'Only organizers can create events.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

$errors     = [];
$title      = '';
$description = '';
$location   = '';
$event_date = '';
$category_id = '';
$tag_ids    = [];

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
        elseif (strtotime($event_date) < time()) $errors[] = 'Event date must be in the future.';

        if (empty($errors)) {
            $stmt = db()->prepare(
                'INSERT INTO events (owner_id, category_id, title, description, location, event_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, "draft")'
            );
            $cat = $category_id > 0 ? $category_id : null;
            $stmt->execute([$_SESSION['user_id'], $cat, $title, $description, $location, $event_date]);
            $event_id = (int)db()->lastInsertId();

            // Tags
            if ($tag_ids) {
                $tag_stmt = db()->prepare('INSERT IGNORE INTO event_tags (event_id, tag_id) VALUES (?, ?)');
                foreach ($tag_ids as $tid) {
                    $tag_stmt->execute([$event_id, $tid]);
                }
            }

            // Log initial status
            $hist = db()->prepare(
                'INSERT INTO status_history (event_id, old_status, new_status, changed_by) VALUES (?, "", "draft", ?)'
            );
            $hist->execute([$event_id, $_SESSION['user_id']]);

            set_flash('success', 'Event created as draft.');
            header('Location: ' . url('detail.php?id=' . $event_id));
            exit;
        }
    }
}

$page_title = 'Create Event — EventHub';
require_once __DIR__ . '/header.php';
$categories = get_categories();
$all_tags   = get_tags();
?>

<section class="section">
    <h1 class="page-title">Create Event</h1>

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
            <button type="submit" class="btn btn-primary">Create Event</button>
            <a href="<?= url('dashboard.php') ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
