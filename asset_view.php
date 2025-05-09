<?php
require_once 'functions.php';
requireLogin();

$asset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$asset_id) {
    // Wenn keine ID übergeben wurde oder die ID ungültig ist, zurück zur Asset-Liste
    header('Location: assets.php?status=error_invalid_id');
    exit;
}

$pdo = getPDO();

// Asset-Daten abrufen, inklusive Name des Verantwortlichen
$stmt = $pdo->prepare("
    SELECT a.*, u.username as owner_name
    FROM assets a
    LEFT JOIN users u ON a.owner_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$asset_id]);
$asset = $stmt->fetch();

if (!$asset) {
    // Wenn kein Asset mit dieser ID gefunden wurde, zurück zur Asset-Liste
    header('Location: assets.php?status=error_notfound');
    exit;
}

include 'header.php';
?>

<h2>Asset Details: <?php echo he($asset['name']); ?></h2>

<div class="asset-details-container">
    <div class="detail-item">
        <span class="detail-label">ID:</span>
        <span class="detail-value"><?php echo he($asset['id']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Name:</span>
        <span class="detail-value"><?php echo he($asset['name']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Beschreibung:</span>
        <span class="detail-value"><?php echo nl2br(he($asset['description'] ?? 'Keine Beschreibung vorhanden.')); // nl2br für Zeilenumbrüche ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Asset-Typ:</span>
        <span class="detail-value"><?php echo he($asset['asset_type']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Klassifizierung:</span>
        <span class="detail-value"><?php echo he($asset['classification']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Verantwortlicher (Owner):</span>
        <span class="detail-value"><?php echo he($asset['owner_name'] ?? 'Nicht zugewiesen'); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Standort/Ablageort:</span>
        <span class="detail-value"><?php echo he($asset['location'] ?? 'N/A'); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Erstellt am:</span>
        <span class="detail-value"><?php echo he(date('d.m.Y H:i:s', strtotime($asset['created_at']))); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Letzte Änderung:</span>
        <span class="detail-value"><?php echo he(date('d.m.Y H:i:s', strtotime($asset['updated_at']))); ?></span>
    </div>
</div>

<div class="actions-bar">
    <a href="asset_edit.php?id=<?php echo he($asset['id']); ?>" class="btn">Asset bearbeiten</a>
    <a href="assets.php" class="btn btn-secondary">Zurück zur Asset-Liste</a>
</div>

<div class="linked-items">
    <h3>Verknüpfte Risiken</h3>
    <p><em>Funktionalität wird später implementiert.</em></p>
    </div>


<style>
    .asset-details-container {
        background-color: #f9f9f9;
        border: 1px solid #eee;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .detail-item {
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px dotted #ddd;
    }
    .detail-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .detail-label {
        font-weight: bold;
        color: #333;
        display: inline-block;
        width: 200px; /* Feste Breite für die Labels */
        vertical-align: top;
    }
    .detail-value {
        color: #555;
        display: inline-block;
        max-width: calc(100% - 220px); /* Restliche Breite für den Wert */
        vertical-align: top;
    }
    .actions-bar {
        margin-top: 20px;
        margin-bottom: 30px;
    }
    .actions-bar .btn {
        margin-right: 10px;
    }
    .btn-secondary {
        background-color: #6c757d;
    }
    .linked-items {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ccc;
    }
</style>

<?php include 'footer.php'; ?>