<?php require_once __DIR__ . '/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($page_title ?? 'Event Manager') ?></title>
    <link rel="stylesheet" href="<?= url('style.css') ?>">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a href="<?= url('index.php') ?>" class="logo">EventHub</a>
        <nav class="main-nav">
            <a href="<?= url('catalog.php') ?>">Events</a>
            <?php if (is_logged_in()): ?>
                <?php if (is_organizer()): ?>
                    <a href="<?= url('create.php') ?>">Create Event</a>
                <?php endif; ?>
                <a href="<?= url('dashboard.php') ?>">Dashboard</a>
                <a href="<?= url('notifications.php') ?>" class="nav-notif">
                    Notifications
                    <?php $nc = unread_notification_count($_SESSION['user_id']); if ($nc > 0): ?>
                        <span class="badge"><?= $nc ?></span>
                    <?php endif; ?>
                </a>
                <?php if (is_admin()): ?>
                    <a href="<?= url('admin.php') ?>">Admin</a>
                <?php endif; ?>
                <a href="<?= url('logout.php') ?>">Log Out</a>
            <?php else: ?>
                <a href="<?= url('login.php') ?>">Log In</a>
                <a href="<?= url('register.php') ?>" class="btn btn-sm btn-primary">Sign Up</a>
            <?php endif; ?>
        </nav>
        <button class="menu-toggle" aria-label="Toggle menu">&#9776;</button>
    </div>
</header>

<main class="container">
    <?= render_flashes() ?>
