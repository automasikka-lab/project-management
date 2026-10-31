<?php
require_once __DIR__ . '/config/auth.php';
logoutAdmin();
header('Location: ' . appBase() . '/index.php');
exit;
