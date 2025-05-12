<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();
$controls = [];

// Suchlogik
$search_term = trim($_GET['search'] ?? '');

// Sortierlogik (behalten wir bei, falls Sie sie später wieder aktivieren wollen oder für andere Spalten)
// Für Controls ist control_id_iso oft die primäre Sortierung.
$sortable_columns = ['control_id_iso', 'name', 'source', 'implementation_status', 'priority', 'description'];
$sort_column = $_GET['sort'] ?? 'control_id_iso'; // Standard-Sortierspalte
$sort_direction = $_GET['dir'] ?? 'ASC';   // Standard-Sortierrichtung

// Validierung der Sortierparameter
if (!in_array($sort_column, $sortable_columns)) {
    $sort_column = 'control_id_iso'; // Fallback
}
if (strtoupper($sort_direction) !== 'ASC' && strtoupper($sort_direction) !== 'DESC') {
    $sort_direction = 'ASC'; // Fallback
}
$next_sort_direction = ($sort_direction === 'ASC') ? 'DESC' : 'ASC';

// SQL-Abfrage vorbereiten
$sql = "
    SELECT id, control_id_iso, name, source, implementation_status, priority, description 
    FROM controls
";
$params = []; // Array für Parameter der Prepared Statements

// WHERE-Klausel für die Suche hinzufügen
if (!empty($search_term)) {
    // Spalten, die durchsucht werden sollen
    // 'control_id_iso', 'name', und 'description' sind hier die wichtigsten für Controls
    $search_columns_controls = ['control_id_iso', 'name', 'description', 'source', 'implementation_status'];

    $where_clauses_controls = [];
    foreach ($search_columns_controls as $column) {
        $where_clauses_controls[] = "`$column` LIKE ?";
        $params[] = "%" . $search_term . "%";
    }
    $sql .= " WHERE (" . implode(" OR ", $where_clauses_controls) . ")";
}

// ORDER BY-Klausel hinzufügen
$sql .= " ORDER BY `$sort_column` $sort_direction, control_id_iso ASC"; // Sekundäre Sortierung

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $controls = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fehler beim Abrufen von Controls: " . $e->getMessage());
    echo "<p class='error'>Fehler beim Laden der Control-Daten.</p>";
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

<h2>Control Management (Maßnahmen / Anforderungen)</h2>

<form action="controls.php" method="get" class="search-form">
    <div class="form-group">
        <input type="text" name="search" placeholder="Volltextsuche in Controls..." value="<?php echo he($search_term); ?>">
        <?php if (isset($_GET['sort'])): ?>
            <input type="hidden" name="sort" value="<?php echo he($_GET['sort']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['dir'])): ?>
            <input type="hidden" name="dir" value="<?php echo he($_GET['dir']); ?>">
        <?php endif; ?>
        <button type="submit" class="btn">Suchen</button>
        <?php if (!empty($search_term)): ?>
            <a href="controls.php<?php echo (isset($_GET['sort']) && isset($_GET['dir'])) ? '?sort=' . he($_GET['sort']) . '&dir=' . he($_GET['dir']) : ''; ?>" class="btn btn-secondary">Suche zurücksetzen</a>
        <?php endif; ?>
    </div>
</form>


<div class="table-container">
    <table>
        <thead>
            <tr>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=control_id_iso&dir=<?php echo ($sort_column === 'control_id_iso' ? $next_sort_direction : 'ASC'); ?>">Control ID<?php echo getSortArrow('control_id_iso', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=name&dir=<?php echo ($sort_column === 'name' ? $next_sort_direction : 'ASC'); ?>">Name / Titel<?php echo getSortArrow('name', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=description&dir=<?php echo ($sort_column === 'description' ? $next_sort_direction : 'ASC'); ?>">Beschreibung (Auszug)<?php echo getSortArrow('description', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=source&dir=<?php echo ($sort_column === 'source' ? $next_sort_direction : 'ASC'); ?>">Quelle<?php echo getSortArrow('source', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=implementation_status&dir=<?php echo ($sort_column === 'implementation_status' ? $next_sort_direction : 'ASC'); ?>">Status<?php echo getSortArrow('implementation_status', $sort_column, $sort_direction); ?></a></th>
                <th><a href="?search=<?php echo he($search_term); ?>&sort=priority&dir=<?php echo ($sort_column === 'priority' ? $next_sort_direction : 'ASC'); ?>">Priorität<?php echo getSortArrow('priority', $sort_column, $sort_direction); ?></a></th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($controls)): ?>
                <tr>
                    <td colspan="7">
                        <?php if (!empty($search_term)): ?>
                            Keine Controls für den Suchbegriff "<?php echo he($search_term); ?>" gefunden.
                        <?php else: ?>
                            Keine Controls gefunden.
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($controls as $control): ?>
                    <tr>
                        <td><?php echo he($control['control_id_iso']); ?></td>
                        <td><?php echo he($control['name']); ?></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo he($control['description']); ?>">
                            <?php
                            $desc_short = substr($control['description'] ?? '', 0, 70);
                            echo he($desc_short);
                            if (strlen($control['description'] ?? '') > 70) echo '...';
                            ?>
                        </td>
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


<?php include 'footer.php'; ?>