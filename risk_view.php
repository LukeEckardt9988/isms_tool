<?php
require_once 'functions.php';
requireLogin();

$risk_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$risk_id) {
    $_SESSION['flash_error'] = "Ungültige Risiko-ID.";
    header('Location: risks.php');
    exit;
}

$pdo = getPDO();

// Risikodaten laden (Ihre bisherige Abfrage)
$stmt_risk = $pdo->prepare("
    SELECT r.*, u.username as owner_name
    FROM risks r
    LEFT JOIN users u ON r.owner_id = u.id
    WHERE r.id = ?
");
$stmt_risk->execute([$risk_id]);
$risk = $stmt_risk->fetch();

if (!$risk) {
    $_SESSION['flash_error'] = "Risiko nicht gefunden.";
    header('Location: risks.php');
    exit;
}

// NEU: Verknüpfte Assets für dieses Risiko laden
$stmt_linked_assets = $pdo->prepare("
    SELECT a.id, a.name, a.inventory_id_extern
    FROM assets a
    JOIN asset_risks ar ON a.id = ar.asset_id
    WHERE ar.risk_id = ?
    ORDER BY a.name ASC
");
$stmt_linked_assets->execute([$risk_id]);
$linked_assets = $stmt_linked_assets->fetchAll();

// NEU: Verknüpfte Controls für dieses Risiko laden
$stmt_linked_controls_risk = $pdo->prepare("
    SELECT c.id, c.control_id_iso, c.name
    FROM controls c
    JOIN risk_controls rc ON c.id = rc.control_id
    WHERE rc.risk_id = ?
    ORDER BY c.control_id_iso ASC
");
$stmt_linked_controls_risk->execute([$risk_id]);
$linked_controls_for_risk = $stmt_linked_controls_risk->fetchAll();


$page_title = "Risiko Details: " . he($risk['name']);
include 'header.php';
?>

<h2><?php echo $page_title; ?></h2>
<?php display_flash_messages(); ?>

<div class="details-container">
    <div class="actions-bar">
        <a href="risks.php" class="btn btn-secondary">&laquo; Zurück zur Risiko-Liste</a>
        <a href="risk_edit.php?id=<?php echo he($risk['id']); ?>" class="btn">Risiko & Verknüpfungen bearbeiten</a>
    </div>

    <fieldset class="metadata-section">
        <legend>Risikoinformationen</legend>
        <p><strong>ID:</strong> <?php echo he($risk['id']); ?></p>
        <p><strong>Name:</strong> <?php echo he($risk['name']); ?></p>
        <p><strong>Beschreibung:</strong> <?php echo nl2br(he($risk['description'] ?? 'N/A')); ?></p>
        <p><strong>Risikoquelle:</strong> <?php echo nl2br(he($risk['risk_source'] ?? 'N/A')); ?></p>
        <p><strong>Eintrittswahrscheinlichkeit:</strong> <?php echo he(ucfirst($risk['likelihood'] ?? 'N/A')); ?></p>
        <p><strong>Auswirkung:</strong> <?php echo he(ucfirst($risk['impact'] ?? 'N/A')); ?></p>
        <p><strong>Risikolevel:</strong> <?php echo he($risk['risk_level'] ?? 'Nicht bewertet'); ?></p>
        <p><strong>Status:</strong> <?php echo he(ucfirst($risk['status'] ?? 'N/A')); ?></p>
        <p><strong>Behandlungsoption:</strong> <?php echo he($risk['treatment_option'] ? ucfirst($risk['treatment_option']) : 'N/A'); ?></p>
        <p><strong>Behandlungsplan:</strong> <?php echo nl2br(he($risk['treatment_plan'] ?? 'N/A')); ?></p>
        <p><strong>Risikoeigner:</strong> <?php echo he($risk['owner_name'] ?? 'Nicht zugewiesen'); ?></p>
        <p><strong>Nächstes Review am:</strong> <?php echo $risk['review_date'] ? he(date('d.m.Y', strtotime($risk['review_date']))) : 'N/A'; ?></p>
        <p><strong>Erstellt am:</strong> <?php echo he(date('d.m.Y H:i', strtotime($risk['created_at']))); ?></p>
        <p><strong>Letzte Änderung:</strong> <?php echo he(date('d.m.Y H:i', strtotime($risk['updated_at']))); ?></p>
    </fieldset>

    <fieldset class="linked-items-section">
        <legend>Betroffene Assets</legend>
        <?php if (!empty($linked_assets)): ?>
            <ul class="linked-list">
                <?php foreach ($linked_assets as $asset_item): ?>
                    <li>
                        <a href="asset_view.php?id=<?php echo he($asset_item['id']); ?>">
                            <?php echo he($asset_item['name']); ?> (Ext.ID: <?php echo he($asset_item['inventory_id_extern'] ?? 'N/A'); ?>)
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Dieses Risiko ist keinen spezifischen Assets direkt zugeordnet.</p>
        <?php endif; ?>
    </fieldset>

    <fieldset class="linked-items-section">
        <legend>Zugeordnete Controls (Behandlungsmaßnahmen)</legend>
        <?php if (!empty($linked_controls_for_risk)): ?>
            <ul class="linked-list">
                <?php foreach ($linked_controls_for_risk as $control_item): ?>
                    <li>
                        <a href="control_view.php?id=<?php echo he($control_item['id']); ?>">
                            <?php echo he($control_item['control_id_iso']); ?> - <?php echo he($control_item['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Für dieses Risiko sind keine Controls als Behandlungsmaßnahmen direkt verknüpft.</p>
        <?php endif; ?>
    </fieldset>
</div>


<?php include 'footer.php'; ?>