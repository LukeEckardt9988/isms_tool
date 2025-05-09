<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();
// Daten abrufen
$stmt = $pdo->query("
    SELECT id, control_id_iso, name, source, implementation_status, priority
    FROM controls
    ORDER BY control_id_iso ASC
");
$controls = $stmt->fetchAll();

include 'header.php';
?>

<h2>Control Management (Maßnahmen / Anforderungen)</h2>
<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success_edit'): ?>
        <p class="success">Control erfolgreich aktualisiert.</p>
    <?php elseif ($_GET['status'] == 'error_invalid_id' || $_GET['status'] == 'error_notfound'): ?>
        <p class="error">Fehler: Ungültige ID oder Control nicht gefunden.</p>
    <?php endif; ?>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Interne ID</th>
                <th>Control ID (BSI/ISO)</th>
                <th>Name / Titel</th>
                <th>Quelle</th>
                <th>Implementierungsstatus</th>
                <th>Priorität</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($controls)): ?>
                <tr>
                    <td colspan="7">Keine Controls gefunden. Bitte führen Sie den Import aus dem BSI Kompendium durch oder legen Sie manuell Controls an.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($controls as $control): ?>
                <tr>
                    <td><?php echo he($control['id']); ?></td>
                    <td><?php echo he($control['control_id_iso']); ?></td>
                    <td><?php echo he($control['name']); ?></td>
                    <td><?php echo he($control['source']); ?></td>
                    <td><?php echo he(ucfirst($control['implementation_status'])); ?></td>
                    <td><?php echo he(ucfirst($control['priority'])); ?></td>
                    <td>
                        <a href="control_view.php?id=<?php echo he($control['id']); ?>" class="btn">Details</a>
                        <a href="control_edit.php?id=<?php echo he($control['id']); ?>" class="btn">Bearbeiten</a>
                        </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .table-container {
        width: 100%;
        overflow-x: auto; /* Für Tabellen, die breiter als der Bildschirm sind */
    }
    table {
        width: 100%;
        min-width: 800px; /* Mindestbreite, bevor Scrollen beginnt */
    }
</style>

<?php include 'footer.php'; ?>