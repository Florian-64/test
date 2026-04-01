<?php
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid event.');
    header('Location: ' . url('catalog.php'));
    exit;
}

$stmt = db()->prepare(
    'SELECT e.*, u.username AS organizer, u.id AS organizer_id, c.name AS category_name
     FROM events e
     JOIN users u ON u.id = e.owner_id
     LEFT JOIN categories c ON c.id = e.category_id
     WHERE e.id = ?'
);
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    set_flash('error', 'Event not found.');
    header('Location: ' . url('catalog.php'));
    exit;
}

// Non-published events visible only to owner or admin
$user = current_user();
if ($event['status'] !== 'published') {
    if (!$user || ($user['id'] !== $event['organizer_id'] && $user['role'] !== 'admin')) {
        set_flash('error', 'Event not available.');
        header('Location: ' . url('catalog.php'));
        exit;
    }
}

$page_title = sanitize($event['title']) . ' — EventHub';
require_once __DIR__ . '/header.php';

// Tags
$tags = get_event_tags($id);

// Comments
$comments_stmt = db()->prepare(
    'SELECT cm.*, u.username FROM comments cm JOIN users u ON u.id = cm.user_id WHERE cm.event_id = ? ORDER BY cm.created_at ASC'
);
$comments_stmt->execute([$id]);
$comments = $comments_stmt->fetchAll();

// Polls
$polls_stmt = db()->prepare('SELECT * FROM polls WHERE event_id = ?');
$polls_stmt->execute([$id]);
$polls = $polls_stmt->fetchAll();

// RSVP counts
$rsvp_stmt = db()->prepare('SELECT status, COUNT(*) as cnt FROM rsvps WHERE event_id = ? GROUP BY status');
$rsvp_stmt->execute([$id]);
$rsvp_counts = [];
foreach ($rsvp_stmt->fetchAll() as $r) {
    $rsvp_counts[$r['status']] = $r['cnt'];
}

// Current user RSVP
$my_rsvp = null;
if ($user) {
    $rs = db()->prepare('SELECT * FROM rsvps WHERE event_id = ? AND user_id = ?');
    $rs->execute([$id, $user['id']]);
    $my_rsvp = $rs->fetch();
}

// Status transitions
$can_manage = $user && ($user['id'] === $event['organizer_id'] || $user['role'] === 'admin');
$transitions = $can_manage ? allowed_transitions($event['status']) : [];

// Status history
$hist_stmt = db()->prepare(
    'SELECT sh.*, u.username FROM status_history sh JOIN users u ON u.id = sh.changed_by WHERE sh.event_id = ? ORDER BY sh.changed_at DESC'
);
$hist_stmt->execute([$id]);
$history = $hist_stmt->fetchAll();
?>

<section class="section event-detail">
    <div class="detail-header">
        <div>
            <span class="event-category"><?= sanitize($event['category_name'] ?? 'Uncategorized') ?></span>
            <span class="event-status status-<?= $event['status'] ?>"><?= ucfirst($event['status']) ?></span>
        </div>
        <h1><?= sanitize($event['title']) ?></h1>
        <div class="detail-meta">
            <span>By <strong><?= sanitize($event['organizer']) ?></strong></span>
            <span><?= date('l, M j, Y \a\t g:i A', strtotime($event['event_date'])) ?></span>
            <span><?= sanitize($event['location']) ?></span>
        </div>
        <?php if ($tags): ?>
            <div class="tag-list">
                <?php foreach ($tags as $tag): ?>
                    <span class="tag"><?= sanitize($tag['name']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="detail-body">
        <div class="detail-main">
            <div class="description">
                <?= nl2br(sanitize($event['description'])) ?>
            </div>

            <!-- RSVP Section -->
            <?php if ($user && $event['status'] === 'published'): ?>
            <div class="card">
                <h3>RSVP</h3>
                <div class="rsvp-stats">
                    <span class="rsvp-going">Going: <?= $rsvp_counts['going'] ?? 0 ?></span>
                    <span class="rsvp-maybe">Maybe: <?= $rsvp_counts['maybe'] ?? 0 ?></span>
                    <span class="rsvp-not">Not Going: <?= $rsvp_counts['not_going'] ?? 0 ?></span>
                </div>
                <form method="POST" action="<?= url('actions/rsvp.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="btn-group">
                        <button name="status" value="going"
                            class="btn btn-sm <?= ($my_rsvp && $my_rsvp['status'] === 'going') ? 'btn-primary' : 'btn-outline' ?>">Going</button>
                        <button name="status" value="maybe"
                            class="btn btn-sm <?= ($my_rsvp && $my_rsvp['status'] === 'maybe') ? 'btn-primary' : 'btn-outline' ?>">Maybe</button>
                        <button name="status" value="not_going"
                            class="btn btn-sm <?= ($my_rsvp && $my_rsvp['status'] === 'not_going') ? 'btn-primary' : 'btn-outline' ?>">Not Going</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Polls -->
            <?php foreach ($polls as $poll): ?>
            <div class="card">
                <h3>Poll: <?= sanitize($poll['question']) ?></h3>
                <?php
                $opts_stmt = db()->prepare(
                    'SELECT po.*, COUNT(v.id) AS vote_count
                     FROM poll_options po
                     LEFT JOIN votes v ON v.option_id = po.id
                     WHERE po.poll_id = ?
                     GROUP BY po.id'
                );
                $opts_stmt->execute([$poll['id']]);
                $options = $opts_stmt->fetchAll();

                $my_vote = null;
                if ($user) {
                    $vs = db()->prepare('SELECT * FROM votes WHERE poll_id = ? AND user_id = ?');
                    $vs->execute([$poll['id'], $user['id']]);
                    $my_vote = $vs->fetch();
                }
                $total_votes = array_sum(array_column($options, 'vote_count'));
                ?>
                <?php if ($my_vote): ?>
                    <p class="poll-voted">You voted. Total votes: <?= $total_votes ?></p>
                    <?php foreach ($options as $opt): ?>
                        <?php $pct = $total_votes > 0 ? round(($opt['vote_count'] / $total_votes) * 100) : 0; ?>
                        <div class="poll-result">
                            <div class="poll-bar" style="width:<?= $pct ?>%"></div>
                            <span><?= sanitize($opt['option_text']) ?> — <?= $pct ?>% (<?= $opt['vote_count'] ?>)</span>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($user): ?>
                    <form method="POST" action="<?= url('actions/vote.php') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
                        <input type="hidden" name="event_id" value="<?= $id ?>">
                        <?php foreach ($options as $opt): ?>
                            <label class="poll-option">
                                <input type="radio" name="option_id" value="<?= $opt['id'] ?>" required>
                                <?= sanitize($opt['option_text']) ?>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-sm btn-primary">Vote</button>
                    </form>
                <?php else: ?>
                    <p><a href="<?= url('login.php') ?>">Log in</a> to vote.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Comments -->
            <div class="card">
                <h3>Comments (<?= count($comments) ?>)</h3>
                <?php if (empty($comments)): ?>
                    <p class="muted">No comments yet.</p>
                <?php endif; ?>
                <?php foreach ($comments as $cm): ?>
                    <div class="comment">
                        <div class="comment-header">
                            <strong><?= sanitize($cm['username']) ?></strong>
                            <time><?= date('M j, Y g:i A', strtotime($cm['created_at'])) ?></time>
                        </div>
                        <p><?= nl2br(sanitize($cm['body'])) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php if ($user): ?>
                <form method="POST" action="<?= url('actions/comment.php') ?>" class="comment-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <textarea name="body" rows="3" placeholder="Write a comment..." required></textarea>
                    <button type="submit" class="btn btn-sm btn-primary">Post Comment</button>
                </form>
                <?php else: ?>
                    <p><a href="<?= url('login.php') ?>">Log in</a> to comment.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="detail-sidebar">
            <?php if ($can_manage): ?>
            <div class="card">
                <h4>Manage Event</h4>
                <a href="<?= url('edit.php?id=' . $id) ?>" class="btn btn-outline btn-block btn-sm">Edit</a>

                <?php foreach ($transitions as $t): ?>
                    <form method="POST" action="<?= url('actions/change_status.php') ?>" style="margin-top:0.5rem">
                        <?= csrf_field() ?>
                        <input type="hidden" name="event_id" value="<?= $id ?>">
                        <button name="new_status" value="<?= $t ?>"
                            class="btn btn-block btn-sm <?= $t === 'cancelled' ? 'btn-danger' : 'btn-primary' ?>">
                            Mark as <?= ucfirst($t) ?>
                        </button>
                    </form>
                <?php endforeach; ?>

                <form method="POST" action="<?= url('actions/delete_event.php') ?>"
                      onsubmit="return confirm('Are you sure you want to delete this event? This cannot be undone.')"
                      style="margin-top:0.5rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger btn-block btn-sm">Delete Event</button>
                </form>
            </div>

            <!-- Invite User -->
            <div class="card">
                <h4>Invite User</h4>
                <form method="POST" action="<?= url('actions/invite.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="form-group">
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-block">Send Invite</button>
                </form>
            </div>

            <!-- Create Poll -->
            <div class="card">
                <h4>Create Poll</h4>
                <form method="POST" action="<?= url('actions/poll.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="form-group">
                        <input type="text" name="question" placeholder="Poll question" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="options[]" placeholder="Option 1" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="options[]" placeholder="Option 2" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="options[]" placeholder="Option 3 (optional)">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-block">Create Poll</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Status History -->
            <?php if (!empty($history)): ?>
            <div class="card">
                <h4>Status History</h4>
                <ul class="history-list">
                    <?php foreach ($history as $h): ?>
                        <li>
                            <span class="status-<?= $h['new_status'] ?>"><?= ucfirst($h['old_status']) ?> &rarr; <?= ucfirst($h['new_status']) ?></span>
                            <small>by <?= sanitize($h['username']) ?> on <?= date('M j, Y', strtotime($h['changed_at'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
