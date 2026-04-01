<?php
require_once __DIR__ . '/functions.php';
session_destroy();
header('Location: ' . url('login.php'));
exit;
