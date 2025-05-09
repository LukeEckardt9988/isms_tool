<?php

require_once 'functions.php';
requireLogin();


$risk_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);


if (!$risk_id) {
    header('Location: risks.php?status=error_invalid_id');
    exit;
}

$pdo = getPDO();

// Risikodaten abrufen, inklusive Name des Eigners
$sql = "
    SELECT r.*, u.username as owner_name
    FROM risks r
    LEFT JOIN users u ON r.owner_id = u.id
    WHERE r.id = ?
";

$stmt = $pdo->prepare($sql);

$stmt->execute([$risk_id]);

$risk = $stmt->fetch();

// Originalcode ab hier (ggf. die var_dumps oben entfernen, wenn Problem gefunden)
include 'header.php';
?>

<h2>Risiko Details: <?php echo he($risk['name']); ?></h2>

<div class="risk-details-container">
    <div class="detail-item">
        <span class="detail-label">ID:</span>
        <span class="detail-value"><?php echo he($risk['id']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Name:</span>
        <span class="detail-value"><?php echo he($risk['name']); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Beschreibung:</span>
        <span class="detail-value"><?php echo nl2br(he($risk['description'] ?? 'N/A')); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Risikoquelle:</span>
        <span class="detail-value"><?php echo nl2br(he($risk['risk_source'] ?? 'N/A')); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Eintrittswahrscheinlichkeit:</span>
        <span class="detail-value"><?php echo he(ucfirst($risk['likelihood'] ?? 'N/A')); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Auswirkung:</span>
        <span class="detail-value"><?php echo he(ucfirst($risk['impact'] ?? 'N/A')); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Risikolevel:</span>
        <span class="detail-value"><?php echo he($risk['risk_level'] ?? 'Nicht bewertet'); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Status:</span>
        <span class="detail-value"><?php echo he(ucfirst($risk['status'] ?? 'N/A')); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Behandlungsoption:</span>
        <span class="detail-value"><?php echo he($risk['treatment_option'] ? ucfirst($risk['treatment_option']) : 'N/A'); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Behandlungsplan:</span>
        <span class="detail-value"><?php echo nl2br(he($risk['treatment_plan'] ?? 'N/A')); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Risikoeigner:</span>
        <span class="detail-value"><?php echo he($risk['owner_name'] ?? 'Nicht zugewiesen'); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Nächstes Review am:</span>
        <span class="detail-value"><?php echo $risk['review_date'] ? he(date('d.m.Y', strtotime($risk['review_date']))) : 'N/A'; ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Erstellt am:</span>
        <span class="detail-value"><?php echo he(date('d.m.Y H:i:s', strtotime($risk['created_at']))); ?></span>
    </div>
    <div class="detail-item">
        <span class="detail-label">Letzte Änderung:</span>
        <span class="detail-value"><?php echo he(date('d.m.Y H:i:s', strtotime($risk['updated_at']))); ?></span>
    </div>
</div>

<div class="actions-bar">
    <a href="risk_edit.php?id=<?php echo he($risk['id']); ?>" class="btn">Risiko bearbeiten</a>
    <a href="risks.php" class="btn btn-secondary">Zurück zur Risiko-Liste</a>
</div>

<div class="linked-items">
    <h3>Verknüpfte Assets</h3>
    <p><em>Funktionalität wird später implementiert.</em></p>
</div>
<div class="linked-items">
    <h3>Verknüpfte Controls (Maßnahmen)</h3>
    <p><em>Funktionalität wird später implementiert.</em></p>
</div>

<style> /* Sie können diese Stile in Ihre style.css auslagern, wenn sie mehrfach verwendet werden */
    .risk-details-container { /* Ähnlich wie asset-details-container */
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
        width: 200px;
        vertical-align: top;
    }
    .detail-value {
        color: #555;
        display: inline-block;
        max-width: calc(100% - 220px);
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