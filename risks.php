<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();
// Daten abrufen, inklusive Name des Risikoeigners
$stmt = $pdo->query("
    SELECT r.id, r.name, r.risk_level, r.status, u.username as owner_name, r.review_date, r.updated_at
    FROM risks r
    LEFT JOIN users u ON r.owner_id = u.id
    ORDER BY r.risk_level DESC, r.name ASC
");
$risks = $stmt->fetchAll();

include 'header.php';
?>

<h2>Risk Management</h2>
<a href="risk_add.php" class="btn btn-add">Neues Risiko hinzufügen</a>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success_add'): ?>
        <p class="success">Risiko erfolgreich hinzugefügt.</p>
    <?php elseif ($_GET['status'] == 'success_edit'): ?>
        <p class="success">Risiko erfolgreich aktualisiert.</p>
    <?php elseif ($_GET['status'] == 'success_delete'): ?>
        <p class="success">Risiko erfolgreich gelöscht.</p>
    <?php elseif ($_GET['status'] == 'error_invalid_id' || $_GET['status'] == 'error_notfound'): ?>
        <p class="error">Fehler: Ungültige ID oder Risiko nicht gefunden.</p>
    <?php endif; ?>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name des Risikos</th>
            <th>Level</th>
            <th>Status</th>
            <th>Eigner</th>
            <th>Nächstes Review</th>
            <th>Letzte Änderung</th>
            <th>Aktionen</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($risks)): ?>
            <tr>
                <td colspan="8">Keine Risiken gefunden.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($risks as $risk): ?>
            <tr>
                <td><?php echo he($risk['id']); ?></td>
                <td><?php echo he($risk['name']); ?></td>
                <td><?php echo he($risk['risk_level'] ?? 'N/A'); ?></td>
                <td><?php echo he($risk['status']); ?></td>
                <td><?php echo he($risk['owner_name'] ?? 'N/A'); ?></td>
                <td><?php echo $risk['review_date'] ? he(date('d.m.Y', strtotime($risk['review_date']))) : 'N/A'; ?></td>
                <td><?php echo he(date('d.m.Y H:i', strtotime($risk['updated_at']))); ?></td>
                <td>
                    <a href="risk_view.php?id=<?php echo he($risk['id']); ?>" class="btn">Details</a> <a href="risk_edit.php?id=<?php echo he($risk['id']); ?>" class="btn">Bearbeiten</a> <a href="risk_delete.php?id=<?php echo he($risk['id']); ?>" class="btn btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie dieses Risiko löschen möchten?');">Löschen</a> </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'footer.php'; ?>