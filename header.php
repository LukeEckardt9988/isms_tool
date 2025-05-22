<?php require_once 'functions.php'; ?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>epsa ISMS Tool</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <header>
        <img src="headerLogo.png" alt="">
        <h1>ISMS-TOOL</h1>
        <nav>
            <?php if (isLoggedIn()): ?>

                <a href="dashboard.php" aria-label="Dashboard">
                    <div><i class="fas fa-tachometer-alt"></i></div>
                    Dashboard
                </a>
                <a href="assets.php" aria-label="Assets">
                    <div><i class="fas fa-cubes"></i></div>
                    Assets
                </a>
                <a href="risks.php" aria-label="Risiken">
                    <div><i class="fas fa-exclamation-triangle"></i></div>
                    Risiken
                </a>
                <a href="controls.php" aria-label="Controls">
                    <div><i class="fas fa-shield-alt"></i></div>
                    Controls
                </a>
                 <a href="tasks.php" aria-label="Tasks">
                    <div><i class="fas fa-tasks"></i></div>
                    Aufgaben
                </a>
                <a href="documents.php" aria-label="Dokumente">
                    <div><i class="fas fa-file-alt"></i></div>
                    Dokumente
                </a>
                <?php if (getCurrentUserRole() === 'admin'): ?>
                    <a href="users.php" aria-label="Benutzer">
                        <div><i class="fas fa-users"></i></div>
                        Benutzer
                    </a>
                <?php endif; ?>
                <a href="logout.php" aria-label="Logout (<?php echo he($_SESSION['username']); ?>)">
                    <div><i class="fas fa-sign-out-alt"></i></div>
                    Logout (<?php echo he($_SESSION['username']); ?>)
                </a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </nav>
    </header>
    <main>