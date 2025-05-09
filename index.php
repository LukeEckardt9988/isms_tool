<?php
require_once 'functions.php'; // session_start() hier

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: login.php'); // Hier landen Sie nach dem Logout
    exit;
}
?>