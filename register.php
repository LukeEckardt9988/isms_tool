<?php
// register.php (Neustart, User-Rolle Standard)
require_once 'db_config.php';
require_once 'functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php'); // Oder deine Hauptseite
    exit;
}

$page_title = "Benutzerregistrierung";
$errors = [];
$success_message = '';
$username_val = '';

define('DEFAULT_NEW_USER_ROLE_ID', 2); // ID für 'user'-Rolle (Standard)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getPDO();
    $username_val = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validierung
    if (empty($username_val)) $errors[] = 'Benutzername ist erforderlich.';
    elseif (strlen($username_val) < 3) $errors[] = 'Benutzername muss mindestens 3 Zeichen haben.';
    else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $username_val]);
            if ($stmt->fetch()) $errors[] = 'Benutzername bereits vergeben.';
        } catch (PDOException $e) {
            $errors[] = 'DB-Fehler (Benutzernamenprüfung).';
            error_log("Register username check error: " . $e->getMessage());
        }
    }

    if (empty($password)) $errors[] = 'Passwort ist erforderlich.';
    elseif (strlen($password) < 6) $errors[] = 'Passwort muss mindestens 6 Zeichen haben.';
    if ($password !== $confirm_password) $errors[] = 'Passwörter stimmen nicht überein.';

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            // In 'users'-Tabelle einfügen (minimale Felder)
            $stmt_user = $pdo->prepare(
                "INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)"
            );
            $stmt_user->execute([
                'username' => $username_val,
                'password_hash' => $password_hash
            ]);
            $user_id = $pdo->lastInsertId();

            // Standardrolle 'user' zuweisen
            $stmt_user_role = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
            $stmt_user_role->execute([
                'user_id' => $user_id,
                'role_id' => DEFAULT_NEW_USER_ROLE_ID
            ]);

            $pdo->commit();
            $success_message = 'Registrierung erfolgreich! Sie können sich jetzt anmelden.';
            log_audit_trail('USER_REGISTERED', 'User', $user_id, ['username' => $username_val, 'assigned_role_id' => DEFAULT_NEW_USER_ROLE_ID]);
            $username_val = ''; // Formular leeren
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Datenbankfehler bei der Registrierung. Code: ' . $e->getCode();
            error_log("Register DB transaction error: " . $e->getMessage() . " SQLSTATE: " . $e->getCode() . " Info: " . print_r($e->errorInfo, true));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?php echo he($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet"> <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f0f2f5; font-family: Arial, sans-serif; }
        .container { max-width: 400px; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        h2 { text-align: center; margin-bottom: 25px; color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h2><?php echo he($page_title); ?></h2>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo he($success_message); ?></div>
            <p class="text-center"><a href="login.php" class="btn btn-secondary">Zum Login</a></p>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): echo he($error) . "<br>"; endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="register.php" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Benutzername</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?php echo he($username_val); ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Passwort</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Passwort bestätigen</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Registrieren</button>
            </form>
        <?php endif; ?>
        <p class="mt-3 text-center">Bereits registriert? <a href="login.php">Hier anmelden</a>.</p>
    </div>
</body>
</html>