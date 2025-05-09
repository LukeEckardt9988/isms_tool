<?php
session_start();

require_once 'db_config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

// Simples HTML Escaping
function he($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function log_audit_trail($action, $entity_type = null, $entity_id = null, $details = null) {
    if (!isLoggedIn()) return; // Nur für eingeloggte Benutzer Aktionen loggen

    $pdo = getPDO();
    $sql = "INSERT INTO audit_trails (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $entity_type,
            $entity_id,
            is_array($details) ? json_encode($details) : $details,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
        ]);
    } catch (PDOException $e) {
        // Fehler beim Loggen behandeln (z.B. in eine Datei schreiben)
        error_log("Audit Trail Error: " . $e->getMessage());
    }
}
?>