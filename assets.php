<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();
$isms_assets = [];

// Suchlogik
$search_term = trim($_GET['search'] ?? '');

// Sortierlogik
$sortable_columns = ['name', 'location', 'inventory_id_extern', 'status_isms', 'classification', 'description']; // 'description' hinzugefügt
$sort_column = $_GET['sort'] ?? 'name';
$sort_direction = $_GET['dir'] ?? 'ASC';

if (!in_array($sort_column, $sortable_columns)) {
    $sort_column = 'name';
}
if (strtoupper($sort_direction) !== 'ASC' && strtoupper($sort_direction) !== 'DESC') {
    $sort_direction = 'ASC';
}
$next_sort_direction = ($sort_direction === 'ASC') ? 'DESC' : 'ASC';

// SQL-Abfrage vorbereiten
$sql = "
    SELECT 
        id, name, location, description, inventory_id_extern, 
        classification, status_isms, updated_at
    FROM assets
";
$params = []; // Array für Parameter der Prepared Statements

// WHERE-Klausel für die Suche hinzufügen
if (!empty($search_term)) {
    // Spalten, die durchsucht werden sollen
    // Wichtig: Die Spalten müssen in Ihrer `assets`-Tabelle in `epsa_isms` existieren!
    // Basierend auf der Transformation sollten Hostname, IP, MAC, S/N etc. in 'description' sein.
    // 'name' enthält den Gerätetyp, 'location' den Standort.
    $search_columns = ['name', 'location', 'description', 'inventory_id_extern', 'status_isms', 'classification'];

    $where_clauses = [];
    foreach ($search_columns as $column) {
        $where_clauses[] = "`$column` LIKE ?"; // Backticks für Spaltennamen
        $params[] = "%" . $search_term . "%";
    }
    $sql .= " WHERE (" . implode(" OR ", $where_clauses) . ")";
}

// ORDER BY-Klausel hinzufügen
// $sort_column und $sort_direction wurden bereits validiert
$sql .= " ORDER BY `$sort_column` $sort_direction, name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params); // Parameter an execute übergeben
    $isms_assets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fehler beim Abrufen von ISMS Assets: " . $e->getMessage());
    echo "<p class='error'>Fehler beim Laden der Asset-Daten.</p>";
}

include 'header.php';

function getSortArrow($column_name, $current_sort_column, $current_sort_direction)
{
    if ($column_name === $current_sort_column) {
        return $current_sort_direction === 'ASC' ? ' &#9650;' : ' &#9660;';
    }
    return '';
}
?>

<h2>Asset Management (Übersicht)</h2>
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="asset_add.php" class="btn btn-add">Neues Asset erstellen</a>
</div>

<form action="assets.php" method="get" class="search-form">
    <div class="form-group">
        <input type="text" name="search" placeholder="Volltextsuche..." value="<?php echo he($search_term); ?>">
        <?php if (isset($_GET['sort'])): ?>
            <input type="hidden" name="sort" value="<?php echo he($_GET['sort']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['dir'])): ?>
            <input type="hidden" name="dir" value="<?php echo he($_GET['dir']); ?>">
        <?php endif; ?>
        <button type="submit" class="btn">Suchen</button>
        <?php if (!empty($search_term)): ?>
            <a href="assets.php<?php echo (isset($_GET['sort']) && isset($_GET['dir'])) ? '?sort=' . he($_GET['sort']) . '&dir=' . he($_GET['dir']) : ''; ?>" class="btn btn-secondary">Suche zurücksetzen</a>
        <?php endif; ?>
    </div>
</form>


<div class="table-container">
    <table>
        <thead>
            <tr>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=name&dir=<?php echo ($sort_column === 'name' ? $next_sort_direction : 'ASC'); ?>">Name (Typ)<?php echo getSortArrow('name', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=location&dir=<?php echo ($sort_column === 'location' ? $next_sort_direction : 'ASC'); ?>">Standort<?php echo getSortArrow('location', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=description&dir=<?php echo ($sort_column === 'description' ? $next_sort_direction : 'ASC'); ?>">Beschreibung (Details)<?php echo getSortArrow('description', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=inventory_id_extern&dir=<?php echo ($sort_column === 'inventory_id_extern' ? $next_sort_direction : 'ASC'); ?>">Externe ID<?php echo getSortArrow('inventory_id_extern', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=status_isms&dir=<?php echo ($sort_column === 'status_isms' ? $next_sort_direction : 'ASC'); ?>">Status (ISMS)<?php echo getSortArrow('status_isms', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=classification&dir=<?php echo ($sort_column === 'classification' ? $next_sort_direction : 'ASC'); ?>">Klassifizierung<?php echo getSortArrow('classification', $sort_column, $sort_direction); ?></a></th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($isms_assets)): ?>
                <tr>
                    <td colspan="7">
                        <?php if (!empty($search_term)): ?>
                            Keine Assets für den Suchbegriff "<?php echo he($search_term); ?>" gefunden.
                        <?php else: ?>
                            Keine Assets gefunden.
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($isms_assets as $asset): ?>
                    <tr>
                        <td><?php echo he($asset['name']); ?></td>
                        <td><?php echo he($asset['location'] ?? 'N/A'); ?></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo he($asset['description']); ?>">
                            <?php
                            $desc_short = substr($asset['description'] ?? '', 0, 70); // Gekürzt für bessere Übersicht
                            echo he($desc_short);
                            if (strlen($asset['description'] ?? '') > 70) echo '...';
                            ?>
                        </td>
                        <td><?php echo he($asset['inventory_id_extern'] ?? 'N/A'); ?></td>
                        <td><?php echo he(ucfirst($asset['status_isms'] ?? 'N/A')); ?></td>
                        <td><?php echo he(ucfirst($asset['classification'] ?? 'N/A')); ?></td>
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
<style>
    .table-container {
        width: 100%;
        overflow-x: auto;
        margin-top: 15px;
    }

    table {
        width: 100%;
    }

    th a {
        text-decoration: none;
        color: inherit;
    }

    th a:hover {
        text-decoration: underline;
    }

    td[title] {
        cursor: help;
    }

    .search-form {
        margin-bottom: 20px;
    }

    .search-form .form-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .search-form input[type="text"] {
        flex-grow: 1;
    }

    .error {
        color: red;
        background-color: #ffe0e0;
        border: 1px solid red;
        padding: 10px;
        margin-bottom: 15px;
    }
</style>
<?php include 'footer.php'; ?>