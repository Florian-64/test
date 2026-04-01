<?php
/**
 * functions.php — Helpers: auth, roles, validation, notifications
 */

require_once __DIR__ . '/config.php';

// =============================================================
//  Authentication helpers
// =============================================================

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user ?: null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        header('Location: ' . url('login.php'));
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        $_SESSION['flash_error'] = 'You do not have permission to perform this action.';
        header('Location: ' . url('index.php'));
        exit;
    }
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function is_organizer(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['organizer', 'admin'], true);
}

// =============================================================
//  Input helpers
// =============================================================

function input(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $_GET[$key] ?? $default);
}

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// =============================================================
//  Flash messages
// =============================================================

function set_flash(string $type, string $message): void
{
    $_SESSION["flash_{$type}"] = $message;
}

function get_flash(string $type): ?string
{
    $msg = $_SESSION["flash_{$type}"] ?? null;
    unset($_SESSION["flash_{$type}"]);
    return $msg;
}

function render_flashes(): string
{
    $html = '';
    foreach (['success', 'error', 'info'] as $type) {
        $msg = get_flash($type);
        if ($msg) {
            $html .= '<div class="flash flash-' . $type . '">' . sanitize($msg) . '</div>';
        }
    }
    return $html;
}

// =============================================================
//  Event status transitions
// =============================================================

function allowed_transitions(string $current): array
{
    $map = [
        'draft'     => ['published'],
        'published' => ['cancelled', 'completed'],
        'cancelled' => [],
        'completed' => [],
    ];
    return $map[$current] ?? [];
}

function transition_event_status(int $event_id, string $new_status, int $user_id): bool
{
    $stmt = db()->prepare('SELECT status FROM events WHERE id = ?');
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    if (!$event) return false;

    $old = $event['status'];
    if (!in_array($new_status, allowed_transitions($old), true)) {
        return false;
    }

    db()->beginTransaction();
    try {
        $upd = db()->prepare('UPDATE events SET status = ? WHERE id = ?');
        $upd->execute([$new_status, $event_id]);

        $hist = db()->prepare(
            'INSERT INTO status_history (event_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)'
        );
        $hist->execute([$event_id, $old, $new_status, $user_id]);

        db()->commit();
        return true;
    } catch (Exception $e) {
        db()->rollBack();
        return false;
    }
}

// =============================================================
//  Notification helpers
// =============================================================

function create_notification(int $user_id, string $type, string $message, string $link = ''): void
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$user_id, $type, $message, $link]);
}

function unread_notification_count(int $user_id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

// =============================================================
//  Pagination helper
// =============================================================

function paginate(int $total, int $per_page, int $current_page): array
{
    $total_pages = max(1, (int)ceil($total / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    return [
        'offset'       => $offset,
        'per_page'     => $per_page,
        'current_page' => $current_page,
        'total_pages'  => $total_pages,
        'total'        => $total,
    ];
}

function pagination_html(array $pg, string $base_url): string
{
    if ($pg['total_pages'] <= 1) return '';
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $pg['total_pages']; $i++) {
        $sep = strpos($base_url, '?') !== false ? '&' : '?';
        $link = $base_url . $sep . 'page=' . $i;
        $cls  = $i === $pg['current_page'] ? ' class="active"' : '';
        $html .= '<a href="' . sanitize($link) . '"' . $cls . '>' . $i . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

// =============================================================
//  Category / Tag helpers
// =============================================================

function get_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
}

function get_tags(): array
{
    return db()->query('SELECT * FROM tags ORDER BY name ASC')->fetchAll();
}

function get_event_tags(int $event_id): array
{
    $stmt = db()->prepare(
        'SELECT t.* FROM tags t JOIN event_tags et ON et.tag_id = t.id WHERE et.event_id = ?'
    );
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

// =============================================================
//  CSRF protection
// =============================================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals(csrf_token(), $token);
}
