<?php
require_once 'functions.php'; // Beinhaltet getPDO() für epsa_isms
requireLogin();

$pdo = getPDO(); // Stellt Verbindung zur epsa_isms Datenbank her
$isms_assets = [];

try {
    // Liest jetzt aus der neu gefüllten 'assets' Tabelle in 'epsa_isms'
    $stmt = $pdo->query("
        SELECT 
            id,
            name,               -- Device Type
            asset_type,
            location,           -- Kombinierter Standort
            description,        -- Sammelfeld für Details
            inventory_id_extern,
            classification,
            status_isms,
            updated_at
        FROM assets  -- Die Tabelle in Ihrer epsa_isms Datenbank
        ORDER BY name ASC, location ASC
    ");
    $isms_assets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fehler beim Abrufen von ISMS Assets: " . $e->getMessage());
    echo "<p class='error'>Fehler beim Laden der Asset-Daten.</p>";
}

include 'header.php';
?>

<h2>Asset Management (ISMS Sicht)</h2>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ISMS ID</th>
                <th>Name (Typ)</th>
                <th>Asset Typ</th>
                <th>Standort</th>
                <th>Externe ID</th>
                <th>Status (ISMS)</th>
                <th>Klassifizierung</th>
                <th>Beschreibung (Details)</th>
                <th>Letzte Änderung</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($isms_assets)): ?>
                <tr>
                    <td colspan="10">Keine Assets gefunden.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($isms_assets as $asset): ?>
                <tr>
                    <td><?php echo he($asset['id']); ?></td>
                    <td><?php echo he($asset['name']); ?></td>
                    <td><?php echo he($asset['asset_type'] ?? 'N/A'); ?></td>
                    <td><?php echo he($asset['location'] ?? 'N/A'); ?></td>
                    <td><?php echo he($asset['inventory_id_extern'] ?? 'N/A'); ?></td>
                    <td><?php echo he($asset['status_isms'] ?? 'N/A'); ?></td>
                    <td><?php echo he($asset['classification'] ?? 'N/A'); ?></td>
                    <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo he($asset['description']); ?>">
                        <?php echo he(substr($asset['description'] ?? '', 0, 100)); echo (strlen($asset['description'] ?? '') > 100 ? '...' : ''); ?>
                    </td>
                    <td><?php echo he(date('d.m.Y H:i', strtotime($asset['updated_at']))); ?></td>
                    <td>
                        <a href="asset_view.php?id=<?php echo he($asset['id']); ?>" class="btn">Details</a>
                        <a href="asset_edit.php?id=<?php echo he($asset['id']); ?>" class="btn">Bearbeiten</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<style> /* Ggf. in style.css auslagern */
    .table-container { width: 100%; overflow-x: auto; }
    table { width: 100%; }
    td[title] { cursor: help; } /* Zeigt an, dass Hover mehr Infos gibt */
</style>
<?php include 'footer.php'; ?>