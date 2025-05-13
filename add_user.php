<?php
require_once 'functions.php';
requireLogin();

// Überprüfen, ob der Benutzer ein Admin ist
if (getCurrentUserRole() !== 'admin') {
    http_response_code(403);
    echo "Nicht autorisiert.";
    exit;
}

include 'header.php';

$errors = [];
$username = $email = $password = $role = ''; // E-Mail initialisieren

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';      // E-Mail abrufen
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validierung
    if (empty($username)) {
        $errors[] = "Benutzername ist erforderlich.";
    }
    if (empty($email)) {
        $errors[] = "E-Mail ist erforderlich.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Ungültige E-Mail-Adresse.";
    }
    if (empty($password)) {
        $errors[] = "Passwort ist erforderlich.";
    }
    if (empty($role)) {
        $errors[] = "Rolle ist erforderlich.";
    }
    if (!in_array($role, ['admin', 'manager', 'viewer'])) {
        $errors[] = "Ungültige Rolle.";
    }

    if (empty($errors)) {
        try {
            $pdo = getPDO();

            // **NEU:** Prüfen, ob die E-Mail bereits existiert
            $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt_check_email->execute([$email]);
            $email_exists = (bool) $stmt_check_email->fetchColumn();

            if ($email_exists) {
                $errors[] = "E-Mail existiert bereits.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");  // E-Mail hinzufügen
                $stmt_insert->execute([$username, $email, $hashedPassword, $role]); // E-Mail einfügen

                log_audit_trail('USER_ADDED', 'User', $pdo->lastInsertId(), ['username' => $username, 'email' => $email, 'role' => $role]); // E-Mail loggen

                $_SESSION['flash_success'] = "Benutzer '$username' wurde hinzugefügt.";
                header('Location: users.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "Datenbankfehler: " . $e->getMessage();
            error_log("Benutzer hinzufügen Fehler: " . $e->getMessage());
        }
    }
}
?>

<h2>Neuen Benutzer hinzufügen</h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo he($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="add_user.php">
    <div class="form-group">
        <label for="username">Benutzername:</label>
        <input type="text" id="username" name="username" value="<?php echo he($username); ?>" required>
    </div>
    <div class="form-group">
        <label for="email">E-Mail:</label>
        <input type="email" id="email" name="email" value="<?php echo he($email); ?>" required>
    </div>
    <div class="form-group">
        <label for="password">Passwort:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div class="form-group">
        <label for="role">Rolle:</label>
        <select id="role" name="role">
            <option value="admin" <?php if ($role === 'admin') echo 'selected'; ?>>Administrator</option>
            <option value="manager" <?php if ($role === 'manager') echo 'selected'; ?>>Manager</option>
            <option value="viewer" <?php if ($role === 'viewer') echo 'selected'; ?>>Betrachter</option>
        </select>
    </div>
    <button type="submit" class="btn">Benutzer hinzufügen</button>
</form>

<p><a href="users.php">Zur Benutzerübersicht</a></p>

<?php include 'footer.php'; ?>