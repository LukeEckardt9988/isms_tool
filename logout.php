<?php
require_once 'functions.php'; // Stellt sicher, dass session_start() hier bereits aufgerufen wurde
error_log("--- Logout-Seite geladen ---");

if (isLoggedIn()) {
    error_log("Logout.php: Benutzer (ID: {$_SESSION['user_id']}) wird ausgeloggt.");
    log_audit_trail('LOGOUT');
} else {
    error_log("Logout.php: Kein Benutzer eingeloggt, trotzdem Logout-Seite aufgerufen.");
}

// 1. Alle Session-Variablen löschen
$_SESSION = array();
error_log("Logout.php: \$_SESSION geleert.");

// 2. Session-Cookie löschen (falls verwendet)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
    error_log("Logout.php: Session-Cookie-Löschung angefordert.");
}

// 3. Session zerstören
session_destroy();
error_log("Logout.php: session_destroy() aufgerufen.");

header('Location: login.php?status=loggedout'); // Optional: Status für Feedback
exit;
?>