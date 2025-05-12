<?php
require_once 'functions.php';
requireLogin();

$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$document_id) {
    $_SESSION['flash_error'] = "Ungültige Dokumenten-ID.";
    header('Location: documents.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("
    SELECT d.*, u_owner.username as owner_username, u_uploader.username as uploader_username 
    FROM documents d
    LEFT JOIN users u_owner ON d.owner_id = u_owner.id
    LEFT JOIN users u_uploader ON d.uploaded_by_user_id = u_uploader.id
    WHERE d.id = ?
");
$stmt->execute([$document_id]);
$document = $stmt->fetch();

if (!$document) {
    $_SESSION['flash_error'] = "Dokument nicht gefunden.";
    header('Location: documents.php');
    exit;
}

// Annahme: file_path in DB enthält den relativen Pfad und Dateinamen
$file_on_server = $document['file_path'];

$page_title = "Metadaten: " . he($document['title']);
include 'header.php';
?>

<h2><?php echo $page_title; ?></h2>

<div class="document-view-container">
    <div class="document-actions">
        <a href="documents.php" class="btn btn-secondary">&laquo; Zurück zur Übersicht</a>
        <a href="document_edit.php?id=<?php echo he($document['id']); ?>" class="btn">Metadaten bearbeiten</a>
        <?php if (file_exists($file_on_server)): ?>
            <a href="serve_file.php?id=<?php echo he($document['id']); ?>&view=attachment" class="btn btn-success">Herunterladen</a>
        <?php else: ?>
            <span class="btn btn-disabled">Datei nicht verfügbar</span>
        <?php endif; ?>
    </div>

    <fieldset class="document-metadata">
        <legend>Dokumentinformationen</legend>
        <p><strong>Titel:</strong> <?php echo he($document['title']); ?></p>
        <p><strong>Beschreibung:</strong> <?php echo nl2br(he($document['description'] ?? 'Keine')); ?></p>
        <p><strong>Dokumententyp:</strong> <?php echo he($document['document_type'] ?? 'N/A'); ?></p>
        <p><strong>Version:</strong> <?php echo he($document['version'] ?? 'N/A'); ?></p>
        <p><strong>Status:</strong> <?php echo he(ucfirst($document['status'])); ?></p>
        <p><strong>Original-Dateiname:</strong> <?php echo he($document['original_filename'] ?? 'N/A'); ?></p>
        <p><strong>Gespeicherter Dateiname:</strong> <?php echo he($document['stored_filename'] ?? 'N/A'); ?></p>
        <p><strong>MIME-Typ:</strong> <?php echo he($document['mime_type'] ?? 'N/A'); ?></p>
        <p><strong>Dateigröße:</strong> <?php echo $document['file_size'] ? round($document['file_size'] / 1024, 1) . ' KB' : 'N/A'; ?></p>
        <p><strong>Verantwortlicher:</strong> <?php echo he($document['owner_username'] ?? 'N/A'); ?></p>
        <p><strong>Hochgeladen von:</strong> <?php echo he($document['uploader_username'] ?? 'N/A'); ?> am <?php echo he(date('d.m.Y H:i', strtotime($document['uploaded_at'] ?? $document['created_at']))); ?></p>
        <p><strong>Publikationsdatum:</strong> <?php echo $document['publication_date'] ? he(date('d.m.Y', strtotime($document['publication_date']))) : 'N/A'; ?></p>
        <p><strong>Nächstes Review:</strong> <?php echo $document['review_date'] ? he(date('d.m.Y', strtotime($document['review_date']))) : 'N/A'; ?></p>
        <p><strong>Gespeicherter Pfad:</strong> <?php echo he($document['file_path'] ?? 'N/A'); ?></p>
    </fieldset>
</div>
<style>
    /* ... Ihre Styles ... */
</style>
<?php include 'footer.php'; ?>