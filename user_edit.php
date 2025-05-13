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
$username = $role = '';
$userId = $_GET['id'] ?? null;

if (!$userId) {
    echo "<p class='error'>Ungültige Benutzer-ID.</p>";
    include 'footer.php';
    exit;
}

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? ''; // Neues Passwort ist optional

        // Validierung
        if (empty($username)) {
            $errors[] = "Benutzername ist erforderlich.";
        }
        if (empty($role)) {
            $errors[] = "Rolle ist erforderlich.";
        }
        if (!in_array($role, ['admin', 'manager', 'viewer'])) { // Corrected roles
            $errors[] = "Ungültige Rolle.";
        }

        if (empty($errors)) {
            // Update mit Passwortänderung
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $hashedPassword, $role, $userId]);
                log_audit_trail('USER_UPDATED', 'User', $userId, ['username' => $username, 'role' => $role, 'password_changed' => true]);
                $_SESSION['flash_success'] = "Benutzer wurde aktualisiert (mit Passwort).";

            } else {
                // Update ohne Passwortänderung
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $role, $userId]);
                log_audit_trail('USER_UPDATED', 'User', $userId, ['username' => $username, 'role' => $role, 'password_changed' => false]);
                $_SESSION['flash_success'] = "Benutzer wurde aktualisiert.";
            }

            header('Location: users.php');
            exit;
        }

    } else {
        // Benutzerdaten abrufen
        $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo "<p class='error'>Benutzer nicht gefunden.</p>";
            include 'footer.php';
            exit;
        }

        $username = $user['username'];
        $role = $user['role'];
    }

} catch (PDOException $e) {
    echo "<p class='error'>Datenbankfehler: " . he($e->getMessage()) . "</p>";
    error_log("Benutzer bearbeiten Fehler: " . $e->getMessage());
}
?>

<h2>Benutzer bearbeiten</h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo he($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="user_edit.php?id=<?php echo he($userId); ?>">
    <div class="form-group">
        <label for="username">Benutzername:</label>
        <input type="text" id="username" name="username" value="<?php echo he($username); ?>" required>
    </div>
    <div class="form-group">
        <label for="password">Neues Passwort (optional):</label>
        <input type="password" id="password" name="password">
        <small>Lassen Sie dieses Feld leer, um das aktuelle Passwort beizubehalten.</small>
    </div>
    <div class="form-group">
        <label for="role">Rolle:</label>
        <select id="role" name="role">
            <option value="admin" <?php if ($role === 'admin') echo 'selected'; ?>>Administrator</option>
            <option value="manager" <?php if ($role === 'manager') echo 'selected'; ?>>Manager</option>
            <option value="viewer" <?php if ($role === 'viewer') echo 'selected'; ?>>Betrachter</option>
        </select>
    </div>
    <button type="submit" class="btn">Änderungen speichern</button>
</form>

<p><a href="users.php">Zur Benutzerübersicht</a></p>

<?php include 'footer.php'; ?>