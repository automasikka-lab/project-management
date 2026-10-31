<?php
// Salin file ini menjadi database.local.php lalu sesuaikan nilainya.
// database.local.php punya prioritas atas kredensial default di database.php.
//
// Contoh untuk MariaDB bawaan Synology (phpMyAdmin):
//   DB_HOST = localhost atau 127.0.0.1
//   DB_NAME = nama database yang Anda buat di phpMyAdmin
//   DB_USER = root (atau user lain yang Anda buat)
//   DB_PASS = password phpMyAdmin/MariaDB Anda

define('DB_HOST', 'localhost');     // 'localhost' atau '127.0.0.1'
define('DB_NAME', 'project_db');    // nama database di phpMyAdmin
define('DB_USER', 'root');          // user database
define('DB_PASS', '');              // password database
