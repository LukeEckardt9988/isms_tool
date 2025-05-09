<?php require_once 'functions.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>epsa ISMS Tool</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1><a href="index.php">epsa ISMS Tool</a></h1>
        <nav>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="assets.php">Assets</a>
                <a href="risks.php">Risiken</a>
                <a href="controls.php">Controls</a>
                <a href="documents.php">Dokumente</a>
                <?php if (getCurrentUserRole() === 'admin'): ?>
                    <a href="users.php">Benutzer</a> <?php endif; ?>
                <a href="logout.php">Logout (<?php echo he($_SESSION['username']); ?>)</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </nav>
    </header>
    <main>