<?php
require_once 'functions.php';
requireLogin();

$control_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$control_id) {
    $_SESSION['flash_error'] = "Ungültige Control-ID.";
    header('Location: controls.php');
    exit;
}

$pdo = getPDO();

// Control-Daten laden (Ihre bisherige Abfrage)
$stmt_control = $pdo->prepare("
    SELECT c.*, u.username as owner_name, d.title as linked_document_title
    FROM controls c
    LEFT JOIN users u ON c.owner_id = u.id
    LEFT JOIN documents d ON c.linked_policy_document_id = d.id 
    WHERE c.id = ?
");
$stmt_control->execute([$control_id]);
$control = $stmt_control->fetch();

if (!$control) {
    $_SESSION['flash_error'] = "Control nicht gefunden.";
    header('Location: controls.php');
    exit;
}

// NEU: Verknüpfte Assets für dieses Control laden
$stmt_linked_assets_ctrl = $pdo->prepare("
    SELECT a.id, a.name, a.inventory_id_extern
    FROM assets a
    JOIN asset_controls ac ON a.id = ac.asset_id
    WHERE ac.control_id = ?
    ORDER BY a.name ASC
");
$stmt_linked_assets_ctrl->execute([$control_id]);
$linked_assets_for_control = $stmt_linked_assets_ctrl->fetchAll();

// NEU: Verknüpfte Risiken, die durch dieses Control behandelt werden
$stmt_linked_risks_ctrl = $pdo->prepare("
    SELECT r.id, r.name, r.risk_level, r.status
    FROM risks r
    JOIN risk_controls rc ON r.id = rc.risk_id
    WHERE rc.control_id = ?
    ORDER BY r.risk_level DESC, r.name ASC
");
$stmt_linked_risks_ctrl->execute([$control_id]);
$linked_risks_for_control = $stmt_linked_risks_ctrl->fetchAll();

$page_title = "Control Details: " . he($control['control_id_iso']);
include 'header.php';
?>

<h2><?php echo $page_title; ?> - <?php echo he($control['name']); ?></h2>
<?php display_flash_messages(); ?>

<div class="details-container">
    <div class="actions-bar">
        <a href="controls.php" class="btn btn-secondary">&laquo; Zurück zur Control-Liste</a>
        <a href="control_edit.php?id=<?php echo he($control['id']); ?>" class="btn">Control bearbeiten & Dokument verknüpfen</a>
    </div>

    <fieldset class="metadata-section">
        <legend>Allgemeine Informationen</legend>
        <p><strong>Interne ID:</strong> <?php echo he($control['id']); ?></p>
        <p><strong>Control ID (BSI/ISO):</strong> <?php echo he($control['control_id_iso']); ?></p>
        <p><strong>Name / Titel:</strong> <?php echo he($control['name']); ?></p>
        <p><strong>Beschreibung:</strong> <?php echo nl2br(he($control['description'] ?? 'N/A')); ?></p>
        <p><strong>Quelle:</strong> <?php echo he($control['source'] ?? 'N/A'); ?></p>
    </fieldset>
    <fieldset class="metadata-section">
        <legend>ISMS Management Details</legend>
        <p><strong>Control Typ:</strong> <?php echo he($control['control_type'] ? ucfirst($control['control_type']) : 'N/A'); ?></p>
        <p><strong>Implementierungsstatus:</strong> <?php echo he(ucfirst($control['implementation_status'])); ?></p>
        <p><strong>Statusbeschreibung / Begründung:</strong> <?php echo nl2br(he($control['status_description'] ?? 'N/A')); ?></p>
        <p><strong>Begründung Anwendbarkeit (SoA):</strong> <?php echo nl2br(he($control['justification_applicability'] ?? 'N/A')); ?></p>
        <p><strong>Priorität:</strong> <?php echo he(ucfirst($control['priority'])); ?></p>
        <p><strong>Verantwortlicher (Owner):</strong> <?php echo he($control['owner_name'] ?? 'Nicht zugewiesen'); ?></p>
        <p><strong>Verantwortliche Abteilung:</strong> <?php echo he($control['responsible_department'] ?? 'N/A'); ?></p>
        <p><strong>Wirksamkeit:</strong> <?php echo he(ucfirst($control['effectiveness'])); ?></p>
        <p><strong>Notizen zur Wirksamkeitsprüfung:</strong> <?php echo nl2br(he($control['effectiveness_review_notes'] ?? 'N/A')); ?></p>
        <p><strong>Letzte Überprüfung am:</strong> <?php echo $control['last_review_date'] ? he(date('d.m.Y', strtotime($control['last_review_date']))) : 'N/A'; ?></p>
        <p><strong>Nächste Überprüfung am:</strong> <?php echo $control['next_review_date'] ? he(date('d.m.Y', strtotime($control['next_review_date']))) : 'N/A'; ?></p>
        <p><strong>Verknüpftes Richtliniendokument:</strong>
            <?php if ($control['linked_policy_document_id'] && $control['linked_document_title']): ?>
                <a href="document_details_view.php?id=<?php echo he($control['linked_policy_document_id']); ?>"><?php echo he($control['linked_document_title']); ?></a>
            <?php else: ?>
                N/A
            <?php endif; ?>
        </p>
        <p><strong>Allgemeine Notizen:</strong> <?php echo nl2br(he($control['notes'] ?? 'N/A')); ?></p>
    </fieldset>


    <fieldset class="linked-items-section">
        <legend>Assets, die durch dieses Control geschützt werden</legend>
        <?php if (!empty($linked_assets_for_control)): ?>
            <ul class="linked-list">
                <?php foreach ($linked_assets_for_control as $asset_item): ?>
                    <li>
                        <a href="asset_view.php?id=<?php echo he($asset_item['id']); ?>">
                            <?php echo he($asset_item['name']); ?> (Ext.ID: <?php echo he($asset_item['inventory_id_extern'] ?? 'N/A'); ?>)
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Dieses Control ist keinen Assets direkt zugeordnet.</p>
        <?php endif; ?>
    </fieldset>

    <fieldset class="linked-items-section">
        <legend>Risiken, die durch dieses Control behandelt werden</legend>
        <?php if (!empty($linked_risks_for_control)): ?>
            <ul class="linked-list">
                <?php foreach ($linked_risks_for_control as $risk_item): ?>
                    <li>
                        <a href="risk_view.php?id=<?php echo he($risk_item['id']); ?>">
                            <?php echo he($risk_item['name']); ?>
                        </a>
                        (Level: <?php echo he($risk_item['risk_level'] ?? 'N/A'); ?>, Status: <?php echo he(ucfirst($risk_item['status'] ?? 'N/A')); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Dieses Control ist keinen Risiken als Behandlungsmaßnahme direkt zugeordnet.</p>
        <?php endif; ?>
    </fieldset>
</div>



<?php include 'footer.php'; ?>