<?php
require_once 'functions.php';
requireLogin();

$asset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$asset_id) {
    $_SESSION['flash_error'] = "Ungültige Asset-ID.";
    header('Location: assets.php');
    exit;
}

$pdo = getPDO();

// Asset-Daten abrufen
$stmt_asset = $pdo->prepare("SELECT * FROM assets WHERE id = ?"); // Annahme: Owner-Info nicht primär hier
$stmt_asset->execute([$asset_id]);
$asset = $stmt_asset->fetch();

if (!$asset) {
    $_SESSION['flash_error'] = "Asset nicht gefunden.";
    header('Location: assets.php');
    exit;
}

// NEU: Verknüpfte Risiken für dieses Asset laden
$stmt_linked_risks = $pdo->prepare("
    SELECT r.id, r.name, r.risk_level, r.status
    FROM risks r
    JOIN asset_risks ar ON r.id = ar.risk_id
    WHERE ar.asset_id = ?
    ORDER BY r.risk_level DESC, r.name ASC
");
$stmt_linked_risks->execute([$asset_id]);
$linked_risks = $stmt_linked_risks->fetchAll();

// NEU: Verknüpfte Controls für dieses Asset laden
$stmt_linked_controls = $pdo->prepare("
    SELECT c.id, c.control_id_iso, c.name
    FROM controls c
    JOIN asset_controls ac ON c.id = ac.control_id
    WHERE ac.asset_id = ?
    ORDER BY c.control_id_iso ASC
");
$stmt_linked_controls->execute([$asset_id]);
$linked_controls = $stmt_linked_controls->fetchAll();


$page_title = "Asset Details: " . he($asset['name'] ?? $asset['inventory_id_extern']);
include 'header.php';
?>

<h2><?php echo $page_title; ?></h2>
<?php display_flash_messages(); // Flash-Nachrichten anzeigen 
?>

<div class="details-container">
    <div class="actions-bar">
        <a href="assets.php" class="btn btn-secondary">&laquo; Zurück zur Asset-Liste</a>
        <a href="asset_edit.php?id=<?php echo he($asset['id']); ?>" class="btn">ISMS-Details & Controls bearbeiten</a>
    </div>

    <fieldset class="metadata-section">
        <legend>Asset-Basisinformationen (aus Inventar)</legend>
        <p><strong>Name (Gerätetyp):</strong> <?php echo he($asset['name'] ?? 'N/A'); ?></p>
        <p><strong>Standort:</strong> <?php echo he($asset['location'] ?? 'N/A'); ?></p>
        <p><strong>Beschreibung (Details aus Inventar):</strong> <?php echo nl2br(he($asset['description'] ?? 'N/A')); ?></p>
        <p><strong>Externe Inventar-ID:</strong> <?php echo he($asset['inventory_id_extern'] ?? 'N/A'); ?></p>
    </fieldset>

    <fieldset class="metadata-section">
        <legend>ISMS-Spezifische Informationen</legend>
        <p><strong>Interne ISMS ID:</strong> <?php echo he($asset['id']); ?></p>
        <p><strong>Klassifizierung:</strong> <?php echo he(ucfirst($asset['classification'] ?? 'N/A')); ?></p>
        <p><strong>Status (ISMS-Sicht):</strong> <?php echo he(ucfirst($asset['status_isms'] ?? 'N/A')); ?></p>
        <p><strong>Erstellt am:</strong> <?php echo he(date('d.m.Y H:i', strtotime($asset['created_at']))); ?></p>
        <p><strong>Letzte ISMS-Änderung:</strong> <?php echo he(date('d.m.Y H:i', strtotime($asset['updated_at']))); ?></p>
    </fieldset>

    <fieldset class="linked-items-section">
        <legend>Verknüpfte Risiken</legend>
        <?php if (!empty($linked_risks)): ?>
            <ul class="linked-list">
                <?php foreach ($linked_risks as $risk_item): ?>
                    <li>
                        <a href="risk_view.php?id=<?php echo he($risk_item['id']); ?>">
                            <?php echo he($risk_item['name']); ?>
                        </a>
                        (Level: <?php echo he($risk_item['risk_level'] ?? 'N/A'); ?>, Status: <?php echo he(ucfirst($risk_item['status'] ?? 'N/A')); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Für dieses Asset sind keine Risiken direkt verknüpft.</p>
        <?php endif; ?>
    </fieldset>

    <fieldset class="linked-items-section">
        <legend>Verknüpfte Controls (Sicherheitsmaßnahmen)</legend>
        <?php if (!empty($linked_controls)): ?>
            <ul class="linked-list">
                <?php foreach ($linked_controls as $control_item): ?>
                    <li>
                        <a href="control_view.php?id=<?php echo he($control_item['id']); ?>">
                            <?php echo he($control_item['control_id_iso']); ?> - <?php echo he($control_item['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Für dieses Asset sind keine Controls direkt verknüpft.</p>
        <?php endif; ?>
    </fieldset>
</div>

<style>
    .details-container {
        padding: 15px;
    }

    .actions-bar {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .actions-bar .btn {
        margin-right: 10px;
    }

    .metadata-section,
    .linked-items-section {
        border: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        background-color: #fdfdfd;
    }

    .metadata-section legend,
    .linked-items-section legend {
        font-weight: bold;
        color: #337ab7;
        padding: 0 5px;
        font-size: 1.1em;
    }

    .metadata-section p {
        margin: 5px 0 10px;
    }

    .linked-list {
        list-style: disc;
        margin-left: 20px;
        padding-left: 0;
    }

    .linked-list li {
        margin-bottom: 5px;
    }

    /* Styles für Flash Messages (success, error) sollten global in style.css sein */
</style>

<?php include 'footer.php'; ?>