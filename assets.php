<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();
$stmt = $pdo->query("SELECT a.id, a.name, a.asset_type, a.classification, u.username as owner_name, a.updated_at
                     FROM assets a
                     LEFT JOIN users u ON a.owner_id = u.id
                     ORDER BY a.name ASC");
$assets = $stmt->fetchAll();

include 'header.php';
?>

<h2>Asset Management</h2>
<a href="asset_add.php" class="btn btn-add">Neues Asset hinzufügen</a>

<?php if (isset($_GET['status']) && $_GET['status'] == 'success_add'): ?>
    <p class="success">Asset erfolgreich hinzugefügt.</p>
<?php elseif (isset($_GET['status']) && $_GET['status'] == 'success_edit'): ?>
    <p class="success">Asset erfolgreich aktualisiert.</p>
<?php elseif (isset($_GET['status']) && $_GET['status'] == 'success_delete'): ?>
    <p class="success">Asset erfolgreich gelöscht.</p>
<?php endif; ?>


<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Typ</th>
            <th>Klassifizierung</th>
            <th>Verantwortlicher</th>
            <th>Letzte Änderung</th>
            <th>Aktionen</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($assets)): ?>
            <tr>
                <td colspan="7">Keine Assets gefunden.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($assets as $asset): ?>
            <tr>
                <td><?php echo he($asset['id']); ?></td>
                <td><?php echo he($asset['name']); ?></td>
                <td><?php echo he($asset['asset_type']); ?></td>
                <td><?php echo he($asset['classification']); ?></td>
                <td><?php echo he($asset['owner_name'] ?? 'N/A'); ?></td>
                <td><?php echo he(date('d.m.Y H:i', strtotime($asset['updated_at']))); ?></td>
                <td>
                    <a href="asset_view.php?id=<?php echo he($asset['id']); ?>" class="btn">Details</a> <a href="asset_edit.php?id=<?php echo he($asset['id']); ?>" class="btn">Bearbeiten</a>
                    <a href="asset_delete.php?id=<?php echo he($asset['id']); ?>" class="btn btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie dieses Asset löschen möchten?');">Löschen</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'footer.php'; ?>