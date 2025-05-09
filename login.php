<?php
require_once 'functions.php';
error_log("--- Login-Seite geladen ---"); // Marker für jeden Aufruf

if (isLoggedIn()) {
    error_log("Login.php: Bereits eingeloggt (User ID: {$_SESSION['user_id']}). Leite zu dashboard.php");
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Login.php: POST-Request empfangen.");
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    error_log("Login.php: Versuchter Benutzername: '{$username}'");

    if (empty($username) || empty($password)) {
        $error = 'Benutzername und Passwort sind erforderlich.';
        error_log("Login.php: Fehler - {$error}");
    } else {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM users WHERE username = ?");
            error_log("Login.php: SQL-Query vorbereitet für Benutzer: {$username}");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                error_log("Login.php: Benutzer '{$username}' in DB gefunden. ID: {$user['id']}, Aktiv: {$user['is_active']}");
                error_log("Login.php: DB-Passwort-Hash: {$user['password_hash']}");
                // Temporär das eingegebene Passwort loggen (ENTFERNEN SIE DIES IM PRODUKTIVBETRIEB!)
                // error_log("Login.php: Eingegebenes Passwort (plaintext, NUR ZUM DEBUGGEN): '{$password}'");

                if ($user['is_active']) {
                    if (password_verify($password, $user['password_hash'])) {
                        error_log("Login.php: Passwort für '{$username}' ist KORREKT.");
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        error_log("Login.php: Session gesetzt: " . print_r($_SESSION, true));
                        log_audit_trail('LOGIN_SUCCESS');
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        error_log("Login.php: Passwort für '{$username}' ist FALSCH.");
                        $error = 'Ungültige Anmeldedaten oder Benutzerkonto deaktiviert.';
                    }
                } else {
                    error_log("Login.php: Benutzerkonto '{$username}' ist INAKTIV.");
                    $error = 'Ungültige Anmeldedaten oder Benutzerkonto deaktiviert.';
                }
            } else {
                error_log("Login.php: Benutzer '{$username}' NICHT in DB gefunden.");
                $error = 'Ungültige Anmeldedaten oder Benutzerkonto deaktiviert.';
            }
        } catch (PDOException $e) {
            error_log("Login.php: Datenbankfehler: " . $e->getMessage());
            $error = 'Datenbankfehler beim Login.';
        }
        // Fehler auch loggen, wenn $error gesetzt wurde
        if (!empty($error)) {
            log_audit_trail('LOGIN_FAILURE', 'User', null, ['username_attempt' => $username, 'reason' => $error]);
            error_log("Login.php: Finaler Fehler für '{$username}': {$error}");
        }
    }
}

include 'header.php';
?>

<h2>Login</h2>

<?php if ($error): ?>
    <p class="error"><?php echo he($error); ?></p>
<?php endif; ?>

<form action="login.php" method="post">
    <div class="form-group">
        <label for="username">Benutzername:</label>
        <input type="text" id="username" name="username" required>
    </div>
    <div class="form-group">
        <label for="password">Passwort:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn">Login</button>
</form>

<?php include 'footer.php'; ?>