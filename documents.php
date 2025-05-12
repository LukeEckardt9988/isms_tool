<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();
$search_term = trim($_GET['search'] ?? '');

// ... (SQL-Abfrage für Dokumente bleibt gleich) ...
$sql = "SELECT d.*, u_owner.username as owner_username, u_uploader.username as uploader_username 
        FROM documents d
        LEFT JOIN users u_owner ON d.owner_id = u_owner.id
        LEFT JOIN users u_uploader ON d.uploaded_by_user_id = u_uploader.id";
$params = [];
// ... (Suchlogik bleibt gleich) ...
if (!empty($search_term)) {
    $sql .= " WHERE (d.title LIKE ? OR d.description LIKE ? OR d.original_filename LIKE ? OR d.document_type LIKE ?)";
    $params[] = "%" . $search_term . "%";
    $params[] = "%" . $search_term . "%";
    $params[] = "%" . $search_term . "%";
    $params[] = "%" . $search_term . "%";
}
$sql .= " ORDER BY d.title ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();


include 'header.php';
?>

<h2>Dokumentenmanagement</h2>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="document_add.php" class="btn btn-add">Neues Dokument hochladen</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Titel</th>
                <th>Typ</th>
                <th>Version</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($documents)): ?>
                <tr>
                    <td colspan="6">Keine Dokumente gefunden.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td>
                            <?php // Link zum direkten Anzeigen im Browser (inline) 
                            ?>
                            <a href="serve_file.php?id=<?php echo he($doc['id']); ?>&view=inline" target="_blank" title="Im Browser öffnen: <?php echo he($doc['title']); ?>">
                                <?php echo he($doc['title']); ?>
                            </a>
                        </td>
                        <td><?php echo he($doc['document_type'] ?? 'N/A'); ?></td>
                        <td><?php echo he($doc['version'] ?? 'N/A'); ?></td>
                        <td><?php echo he(ucfirst($doc['status'])); ?></td>
                        
                        <td>
                            <a href="document_details_view.php?id=<?php echo he($doc['id']); ?>" class="btn">Metadaten</a>
                            <a href="document_delete.php?id=<?php echo he($doc['id']); ?>" class="btn btn-danger" onclick="return confirm('Sind Sie sicher...?');">Löschen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include 'footer.php'; ?>