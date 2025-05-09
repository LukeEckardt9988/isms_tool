<?php
require_once 'functions.php';
requireLogin(); // Admin-Rolle oder spezifische Berechtigung wäre hier besser

$risk_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$risk_id) {
    header('Location: risks.php?status=error_invalid_id');
    exit;
}

$pdo = getPDO();

// Optional: Risikodetails laden, um sie zu loggen, bevor sie gelöscht werden
$stmt_select = $pdo->prepare("SELECT name, status FROM risks WHERE id = ?");
$stmt_select->execute([$risk_id]);
$risk_details = $stmt_select->fetch();

if ($risk_details) {
    try {
        // Zuerst Verknüpfungen in asset_risks löschen
        $stmt_link_asset = $pdo->prepare("DELETE FROM asset_risks WHERE risk_id = ?");
        $stmt_link_asset->execute([$risk_id]);

        // Zuerst Verknüpfungen in risk_controls löschen
        $stmt_link_control = $pdo->prepare("DELETE FROM risk_controls WHERE risk_id = ?");
        $stmt_link_control->execute([$risk_id]);

        // Dann das Risiko selbst löschen
        $stmt_delete_risk = $pdo->prepare("DELETE FROM risks WHERE id = ?");
        $stmt_delete_risk->execute([$risk_id]);

        if ($stmt_delete_risk->rowCount() > 0) {
            log_audit_trail('DELETE_RISK', 'Risk', $risk_id, ['name' => $risk_details['name'], 'status' => $risk_details['status']]);
            header('Location: risks.php?status=success_delete');
            exit;
        } else {
            // Sollte nicht passieren, wenn die ID gültig war und keine Fehler auftraten
            header('Location: risks.php?status=error_delete_notfound');
            exit;
        }
    } catch (PDOException $e) {
        // Fehlerbehandlung, z.B. wenn Foreign Key Constraints das Löschen verhindern
        error_log("Error deleting risk ID $risk_id: " . $e->getMessage());
        header('Location: risks.php?status=error_delete_db');
        exit;
    }
} else {
    // Risiko nicht gefunden
    header('Location: risks.php?status=error_delete_notfound');
    exit;
}
?>