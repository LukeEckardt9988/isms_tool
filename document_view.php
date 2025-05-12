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
// Lade Dokumentdetails und ggf. Namen des Uploaders/Owners
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

// Pfad zur Datei auf dem Server (aus der Datenbank)
// UPLOAD_DIR_DOCS sollte konsistent sein mit document_add.php
// Falls file_path bereits den vollen relativen Pfad enthält (z.B. "uploads/documents/xyz.pdf")
$file_on_server = $document['file_path'];

// Wenn file_path nur den Dateinamen enthält und UPLOAD_DIR_DOCS davor muss:
// define('UPLOAD_DIR_DOCS', 'uploads/documents/');
// $file_on_server = UPLOAD_DIR_DOCS . $document['stored_filename'];


$page_title = "Dokument: " . he($document['title']);
include 'header.php';
?>

<h2><?php echo $page_title; ?></h2>

<div class="document-view-container">
    <div class="document-actions">
        <a href="documents.php" class="btn btn-secondary">&laquo; Zurück zur Übersicht</a>
        <a href="document_edit.php?id=<?php echo he($document['id']); ?>" class="btn">Metadaten bearbeiten</a>
        <?php if (file_exists($file_on_server)): ?>
            <a href="download_document.php?id=<?php echo he($document['id']); ?>" class="btn btn-success">Dokument herunterladen (<?php echo he($document['original_filename']); ?>)</a>
        <?php else: ?>
            <span class="btn btn-disabled">Datei nicht auf Server verfügbar</span>
        <?php endif; ?>
    </div>

    <fieldset class="document-metadata">
        <legend>Dokumentinformationen</legend>
        <p><strong>Titel:</strong> <?php echo he($document['title']); ?></p>
        <p><strong>Beschreibung:</strong> <?php echo nl2br(he($document['description'] ?? 'Keine')); ?></p>
        <p><strong>Dokumententyp:</strong> <?php echo he($document['document_type'] ?? 'N/A'); ?></p>
        <p><strong>Version:</strong> <?php echo he($document['version'] ?? 'N/A'); ?></p>
        <p><strong>Status:</strong> <?php echo he(ucfirst($document['status'])); ?></p>
        <p><strong>Original-Dateiname:</strong> <?php echo he($document['original_filename']); ?></p>
        <p><strong>Gespeicherter Dateiname:</strong> <?php echo he($document['stored_filename'] ?? 'N/A'); ?></p>
        <p><strong>MIME-Typ:</strong> <?php echo he($document['mime_type'] ?? 'N/A'); ?></p>
        <p><strong>Dateigröße:</strong> <?php echo $document['file_size'] ? round($document['file_size'] / 1024, 1) . ' KB' : 'N/A'; ?></p>
        <p><strong>Verantwortlicher:</strong> <?php echo he($document['owner_username'] ?? 'N/A'); ?></p>
        <p><strong>Hochgeladen von:</strong> <?php echo he($document['uploader_username'] ?? 'N/A'); ?> am <?php echo he(date('d.m.Y H:i', strtotime($document['uploaded_at'] ?? $document['created_at']))); ?></p>
        <p><strong>Publikationsdatum:</strong> <?php echo $document['publication_date'] ? he(date('d.m.Y', strtotime($document['publication_date']))) : 'N/A'; ?></p>
        <p><strong>Nächstes Review:</strong> <?php echo $document['review_date'] ? he(date('d.m.Y', strtotime($document['review_date']))) : 'N/A'; ?></p>
    </fieldset>

    <?php if (file_exists($file_on_server)): ?>
        <fieldset class="document-preview">
            <legend>Vorschau / Inhalt</legend>
            <?php
            $file_extension = strtolower(pathinfo($document['original_filename'], PATHINFO_EXTENSION));
            $mime_type_for_preview = $document['mime_type'] ?? '';

            // Für PDFs und gängige Bildformate eine direkte Anzeige versuchen
            if ($file_extension === 'pdf' || strpos($mime_type_for_preview, 'application/pdf') !== false):
            ?>
                <p>Das Dokument wird als PDF angezeigt. Möglicherweise müssen Sie Browser-Plugins aktivieren oder das Zoomen anpassen.</p>
                <iframe src="serve_file.php?id=<?php echo he($document['id']); ?>&view=inline" width="100%" height="700px" style="border: 1px solid #ccc;">
                    Ihr Browser unterstützt keine eingebetteten PDFs. Sie können das Dokument <a href="download_document.php?id=<?php echo he($document['id']); ?>">hier herunterladen</a>.
                </iframe>
            <?php elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) || strpos($mime_type_for_preview, 'image/') === 0): ?>
                <img src="serve_file.php?id=<?php echo he($document['id']); ?>&view=inline" alt="<?php echo he($document['title']); ?>" style="max-width: 100%; height: auto; border: 1px solid #ccc;">
            <?php elseif (in_array($file_extension, ['txt']) || strpos($mime_type_for_preview, 'text/plain') !== false): ?>
                <pre style="white-space: pre-wrap; word-wrap: break-word; border: 1px solid #ccc; padding: 10px; max-height: 500px; overflow-y: auto;"><?php
                                                                                                                                                        // Vorsicht bei sehr großen Textdateien, ggf. nur einen Auszug laden
                                                                                                                                                        $content = file_get_contents($file_on_server);
                                                                                                                                                        echo he($content);
                                                                                                                                                        ?></pre>
            <?php else: ?>
                <p>Für diesen Dateityp (<?php echo he($document['original_filename']); ?>) ist keine direkte Vorschau im Browser verfügbar.</p>
                <p>Bitte <a href="download_document.php?id=<?php echo he($document['id']); ?>">laden Sie das Dokument herunter</a>, um es anzusehen.</p>
            <?php endif; ?>
        </fieldset>
    <?php else: ?>
        <p class="error">Die Datei zum Dokument konnte auf dem Server nicht gefunden werden.</p>
    <?php endif; ?>

</div>


<?php include 'footer.php'; ?>