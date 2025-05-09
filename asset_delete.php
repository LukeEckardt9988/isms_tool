<?php
require_once 'functions.php';
requireLogin(); // Admin-Rolle wäre hier besser

$asset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$asset_id) {
    header('Location: assets.php');
    exit;
}

$pdo = getPDO();

// Optional: Asset-Details laden, um sie zu loggen, bevor sie gelöscht werden
$stmt_select = $pdo->prepare("SELECT name, asset_type FROM assets WHERE id = ?");
$stmt_select->execute([$asset_id]);
$asset_details = $stmt_select->fetch();

if ($asset_details) {
    try {
        // Zuerst Verknüpfungen in asset_risks löschen (falls ON DELETE CASCADE nicht gesetzt oder als zusätzliche Sicherheit)
        $stmt_link = $pdo->prepare("DELETE FROM asset_risks WHERE asset_id = ?");
        $stmt_link->execute([$asset_id]);

        // Dann das Asset selbst löschen
        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);

        if ($stmt->rowCount() > 0) {
            log_audit_trail('DELETE_ASSET', 'Asset', $asset_id, ['name' => $asset_details['name'], 'type' => $asset_details['asset_type']]);
            header('Location: assets.php?status=success_delete');
            exit;
        } else {
            // Sollte nicht passieren, wenn die ID gültig war und keine Fehler auftraten
            header('Location: assets.php?status=error_delete_notfound');
            exit;
        }
    } catch (PDOException $e) {
        // Fehlerbehandlung, z.B. wenn Foreign Key Constraints das Löschen verhindern
        error_log("Error deleting asset ID $asset_id: " . $e->getMessage());
        header('Location: assets.php?status=error_delete_db');
        exit;
    }
} else {
    // Asset nicht gefunden
    header('Location: assets.php?status=error_delete_notfound');
    exit;
}
?>