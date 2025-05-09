<?php
require_once 'functions.php';
requireLogin();

$control_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Interne DB-ID
if (!$control_id) {
    header('Location: controls.php?status=error_invalid_id');
    exit;
}

$pdo = getPDO();

// Control-Daten abrufen, inklusive Name des Verantwortlichen (owner)
// und Name des verknüpften Dokuments (linked_policy_document)
$stmt = $pdo->prepare("
    SELECT c.*, u.username as owner_name, d.title as linked_document_title
    FROM controls c
    LEFT JOIN users u ON c.owner_id = u.id
    LEFT JOIN documents d ON c.linked_policy_document_id = d.id
    WHERE c.id = ?
");
$stmt->execute([$control_id]);
$control = $stmt->fetch();

if (!$control) {
    header('Location: controls.php?status=error_notfound');
    exit;
}

include 'header.php';
?>

<h2>Control Details: <?php echo he($control['control_id_iso']); ?> - <?php echo he($control['name']); ?></h2>

<div class="control-details-container">
    <div class="detail-section">
        <h3>Allgemeine Informationen</h3>
        <div class="detail-item">
            <span class="detail-label">Interne ID:</span>
            <span class="detail-value"><?php echo he($control['id']); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Control ID (BSI/ISO):</span>
            <span class="detail-value"><?php echo he($control['control_id_iso']); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Name / Titel:</span>
            <span class="detail-value"><?php echo he($control['name']); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Beschreibung:</span>
            <span class="detail-value description-box"><?php echo nl2br(he($control['description'] ?? 'Keine Beschreibung vorhanden.')); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Quelle:</span>
            <span class="detail-value"><?php echo he($control['source']); ?></span>
        </div>
    </div>

    <div class="detail-section">
        <h3>ISMS Management Details</h3>
        <div class="detail-item">
            <span class="detail-label">Control Typ:</span>
            <span class="detail-value"><?php echo he($control['control_type'] ? ucfirst($control['control_type']) : 'N/A'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Implementierungsstatus:</span>
            <span class="detail-value"><?php echo he(ucfirst($control['implementation_status'])); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Statusbeschreibung / Begründung:</span>
            <span class="detail-value description-box"><?php echo nl2br(he($control['status_description'] ?? 'N/A')); ?></span>
        </div>
         <div class="detail-item">
            <span class="detail-label">Begründung Anwendbarkeit (SoA):</span>
            <span class="detail-value description-box"><?php echo nl2br(he($control['justification_applicability'] ?? 'N/A')); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Priorität:</span>
            <span class="detail-value"><?php echo he(ucfirst($control['priority'])); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Verantwortlicher (Owner):</span>
            <span class="detail-value"><?php echo he($control['owner_name'] ?? 'Nicht zugewiesen'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Verantwortliche Abteilung:</span>
            <span class="detail-value"><?php echo he($control['responsible_department'] ?? 'N/A'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Wirksamkeit:</span>
            <span class="detail-value"><?php echo he(ucfirst($control['effectiveness'])); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Notizen zur Wirksamkeitsprüfung:</span>
            <span class="detail-value description-box"><?php echo nl2br(he($control['effectiveness_review_notes'] ?? 'N/A')); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Letzte Überprüfung am:</span>
            <span class="detail-value"><?php echo $control['last_review_date'] ? he(date('d.m.Y', strtotime($control['last_review_date']))) : 'N/A'; ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Nächste Überprüfung am:</span>
            <span class="detail-value"><?php echo $control['next_review_date'] ? he(date('d.m.Y', strtotime($control['next_review_date']))) : 'N/A'; ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Verknüpftes Richtliniendokument:</span>
            <span class="detail-value">
                <?php if ($control['linked_policy_document_id'] && $control['linked_document_title']): ?>
                    <a href="document_view.php?id=<?php echo he($control['linked_policy_document_id']); ?>"><?php echo he($control['linked_document_title']); ?></a>
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Allgemeine Notizen:</span>
            <span class="detail-value description-box"><?php echo nl2br(he($control['notes'] ?? 'N/A')); ?></span>
        </div>
    </div>

    <div class="detail-section">
        <h3>Zeitstempel</h3>
        <div class="detail-item">
            <span class="detail-label">Erstellt am:</span>
            <span class="detail-value"><?php echo he(date('d.m.Y H:i:s', strtotime($control['created_at']))); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Letzte Änderung:</span>
            <span class="detail-value"><?php echo he(date('d.m.Y H:i:s', strtotime($control['updated_at']))); ?></span>
        </div>
    </div>
</div>

<div class="actions-bar">
    <a href="control_edit.php?id=<?php echo he($control['id']); ?>" class="btn">Control bearbeiten</a>
    <a href="controls.php" class="btn btn-secondary">Zurück zur Control-Liste</a>
</div>

<div class="linked-items">
    <h3>Risiken, die durch dieses Control behandelt werden</h3>
    <p><em>Funktionalität wird später implementiert.</em></p>
</div>
<div class="linked-items">
    <h3>Assets, die durch dieses Control geschützt werden</h3>
    <p><em>Funktionalität wird später implementiert.</em></p>
</div>


<style>
    .control-details-container {
        background-color: #f9f9f9;
        border: 1px solid #eee;
        padding: 10px 20px; /* Etwas weniger Padding oben/unten */
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .detail-section {
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e0e0e0;
    }
    .detail-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .detail-section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #337ab7; /* Blaue Akzentfarbe für Überschriften */
        font-size: 1.1em;
    }
    .detail-item {
        margin-bottom: 8px;
        padding-bottom: 8px;
        /* border-bottom: 1px dotted #ddd; */ /* Entfernt für sauberere Sektionen */
        display: flex; /* Flexbox für Label und Wert */
        flex-wrap: wrap; /* Umbruch bei Bedarf */
    }
    .detail-item:last-child {
        /* border-bottom: none; */
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .detail-label {
        font-weight: bold;
        color: #333;
        min-width: 220px; /* Mindestbreite für die Labels */
        padding-right: 10px; /* Abstand zum Wert */
        flex-shrink: 0; /* Verhindert, dass Label schrumpft */
    }
    .detail-value {
        color: #555;
        flex-grow: 1; /* Wert nimmt restlichen Platz ein */
    }
    .description-box { /* Spezielles Styling für längere Textfelder */
        background-color: #fff;
        border: 1px solid #ddd;
        padding: 8px;
        border-radius: 3px;
        min-height: 40px;
        white-space: pre-wrap; /* Erhält Zeilenumbrüche und Leerzeichen */
        word-break: break-word;
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