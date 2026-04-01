<?php
$page_title = 'Dashboard — EventHub';
require_once __DIR__ . '/header.php';
require_login();

$user = current_user();
$uid  = $user['id'];

// My events (if organizer/admin)
$my_events = [];
if (is_organizer()) {
    $stmt = db()->prepare(
        'SELECT e.*, c.name AS category_name FROM events e LEFT JOIN categories c ON c.id = e.category_id WHERE e.owner_id = ? ORDER BY e.created_at DESC'
    );
    $stmt->execute([$uid]);
    $my_events = $stmt->fetchAll();
}

// My RSVPs
$rsvp_stmt = db()->prepare(
    'SELECT r.*, e.title, e.event_date, e.location, e.status AS event_status
     FROM rsvps r
     JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ?
     ORDER BY e.event_date ASC'
);
$rsvp_stmt->execute([$uid]);
$my_rsvps = $rsvp_stmt->fetchAll();

// Pending invitations
$inv_stmt = db()->prepare(
    'SELECT i.*, e.title, u.username AS sender_name
     FROM invitations i
     JOIN events e ON e.id = i.event_id
     JOIN users u ON u.id = i.sender_id
     WHERE i.recipient_id = ? AND i.status = "pending"
     ORDER BY i.created_at DESC'
);
$inv_stmt->execute([$uid]);
$pending_invites = $inv_stmt->fetchAll();
?>

<section class="section">
    <div class="dashboard-header">
        <h1>Dashboard</h1>
        <p>Welcome, <strong><?= sanitize($user['username']) ?></strong>
            <span class="role-badge role-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
        </p>
    </div>

    <!-- Pending Invitations -->
    <?php if ($pending_invites): ?>
    <div class="card">
        <h2>Pending Invitations</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>From</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_invites as $inv): ?>
                    <tr>
                        <td><a href="<?= url('detail.php?id=' . $inv['event_id']) ?>"><?= sanitize($inv['title']) ?></a></td>
                        <td><?= sanitize($inv['sender_name']) ?></td>
                        <td><?= date('M j, Y', strtotime($inv['created_at'])) ?></td>
                        <td>
                            <form method="POST" action="<?= url('actions/respond_invite.php') ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>">
                                <button name="response" value="accepted" class="btn btn-sm btn-primary">Accept</button>
                                <button name="response" value="maybe" class="btn btn-sm btn-outline">Maybe</button>
                                <button name="response" value="declined" class="btn btn-sm btn-danger">Decline</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- My Events -->
    <?php if (is_organizer()): ?>
    <div class="card">
        <div class="card-header-row">
            <h2>My Events</h2>
            <a href="<?= url('create.php') ?>" class="btn btn-primary btn-sm">Create Event</a>
        </div>
        <?php if (empty($my_events)): ?>
            <p class="muted">You haven't created any events yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_events as $ev): ?>
                        <tr>
                            <td><a href="<?= url('detail.php?id=' . $ev['id']) ?>"><?= sanitize($ev['title']) ?></a></td>
                            <td><?= date('M j, Y', strtotime($ev['event_date'])) ?></td>
                            <td><span class="event-status status-<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span></td>
                            <td><?= sanitize($ev['category_name'] ?? '—') ?></td>
                            <td>
                                <a href="<?= url('edit.php?id=' . $ev['id']) ?>" class="btn btn-sm btn-outline">Edit</a>
                                <form method="POST" action="<?= url('actions/delete_event.php') ?>"
                                      onsubmit="return confirm('Delete this event?')" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- My RSVPs -->
    <div class="card">
        <h2>My RSVPs</h2>
        <?php if (empty($my_rsvps)): ?>
            <p class="muted">No RSVPs yet. <a href="<?= url('catalog.php') ?>">Browse events</a> to get started.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Your RSVP</th>
                            <th>Event Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_rsvps as $r): ?>
                        <tr>
                            <td><a href="<?= url('detail.php?id=' . $r['event_id']) ?>"><?= sanitize($r['title']) ?></a></td>
                            <td><?= date('M j, Y', strtotime($r['event_date'])) ?></td>
                            <td><?= sanitize($r['location']) ?></td>
                            <td><span class="rsvp-badge rsvp-<?= $r['status'] ?>"><?= ucfirst(str_replace('_', ' ', $r['status'])) ?></span></td>
                            <td><span class="event-status status-<?= $r['event_status'] ?>"><?= ucfirst($r['event_status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
