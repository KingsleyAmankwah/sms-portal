<?php
require_once __DIR__ . '/../core/app-config.php';
session_start();
session_unset();
session_destroy();
header('Location: ' . INDEX_PAGE);
exit;