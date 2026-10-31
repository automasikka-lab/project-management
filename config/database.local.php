<?php
// Konfigurasi database lokal (Synology phpMyAdmin / MariaDB).
// File ini punya prioritas atas default di database.php.

define('DB_HOST', 'localhost');          // 'localhost' atau '127.0.0.1' — sama saja, kode otomatis fallback
define('DB_NAME', 'project_db');         // harus sama dengan nama database di phpMyAdmin
define('DB_USER', 'root');               // user database
define('DB_PASS', 'Kkaeng123@#$');       // password database (password phpMyAdmin/MariaDB)
