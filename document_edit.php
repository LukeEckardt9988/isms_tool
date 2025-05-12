<?php
require_once 'functions.php';
requireLogin();

$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$document_id && $_SERVER['REQUEST_METHOD'] !== 'POST') { // Erlaube POST auch ohne ID für den Fall, dass sie im Formular ist
    $_SESSION['flash_error'] = "Ungültige oder fehlende Dokumenten-ID.";
    header('Location: documents.php');
    exit;
}

$pdo = getPDO();

// Wenn POST-Request, ID aus POST nehmen, falls vorhanden (für den Fall, dass GET-ID fehlt, aber Formular gesendet wird)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'])) {
    $document_id_post = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
    if ($document_id_post) {
        $document_id = $document_id_post;
    }
}

if (!$document_id) { // Erneute Prüfung nach POST
    $_SESSION['flash_error'] = "Ungültige oder fehlende Dokumenten-ID.";
    header('Location: documents.php');
    exit;
}


// Lade bestehende Dokumentdaten
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$document_id]);
$document = $stmt->fetch();

if (!$document) {
    $_SESSION['flash_error'] = "Dokument nicht gefunden.";
    header('Location: documents.php');
    exit;
}

// Benutzer für Dropdown "Verantwortlicher"
$users = $pdo->query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username")->fetchAll();
// ENUM-Optionen für Status
$status_options_docs = ['Entwurf', 'In Prüfung', 'Genehmigt', 'Archiviert', 'Veraltet']; // Passen Sie dies an Ihre ENUM-Definition an


$errors = [];
$success_message = '';

// Formularfelder mit Werten aus der Datenbank oder POST vorbefüllen
$title = $_POST['title'] ?? ($document['title'] ?? '');
$description = $_POST['description'] ?? ($document['description'] ?? '');
$document_type = $_POST['document_type'] ?? ($document['document_type'] ?? '');
$version = $_POST['version'] ?? ($document['version'] ?? '');
$status = $_POST['status'] ?? ($document['status'] ?? 'Entwurf');
$owner_id = $_POST['owner_id'] ?? ($document['owner_id'] ?? null);
// publication_date und review_date aus DB oder POST holen und für Input-Feld formatieren
$publication_date_val = $_POST['publication_date'] ?? ($document['publication_date'] ? date('Y-m-d', strtotime($document['publication_date'])) : '');
$review_date_val = $_POST['review_date_for_doc'] ?? ($document['review_date'] ? date('Y-m-d', strtotime($document['review_date'])) : '');


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_document_meta') {
    // Validierung der Metadaten
    if (empty($title)) {
        $errors[] = 'Ein Titel für das Dokument ist erforderlich.';
    }
    // Validierung für Datumsformate
    $publication_date_to_save = !empty($publication_date_val) ? date('Y-m-d', strtotime($publication_date_val)) : null;
    if (!empty($publication_date_val) && !$publication_date_to_save) {
        $errors[] = 'Ungültiges Publikationsdatum.';
    }
    $review_date_to_save = !empty($review_date_val) ? date('Y-m-d', strtotime($review_date_val)) : null;
    if (!empty($review_date_val) && !$review_date_to_save) {
        $errors[] = 'Ungültiges Review-Datum.';
    }


    if (empty($errors)) {
        try {
            // UPDATE Logik für bestehendes Dokument (Metadaten)
            // Die Dateifelder (original_filename, stored_filename, file_path, mime_type, file_size)
            // werden hier NICHT geändert, da wir die Datei selbst nicht ersetzen.
            $sql = "UPDATE documents SET 
                        title = ?, 
                        description = ?, 
                        document_type = ?, 
                        version = ?, 
                        status = ?, 
                        owner_id = ?,
                        publication_date = ?,
                        review_date = ?,
                        updated_at = NOW() 
                    WHERE id = ?";
            $stmt_update = $pdo->prepare($sql);
            $stmt_update->execute([
                $title,
                $description ?: null,
                $document_type ?: null,
                $version ?: null,
                $status,
                $owner_id ?: null,
                $publication_date_to_save,
                $review_date_to_save,
                $document_id
            ]);
            log_audit_trail('UPDATE_DOCUMENT_META', 'Document', $document_id, ['title' => $title]);
            $_SESSION['flash_success'] = "Metadaten für Dokument '" . he($title) . "' erfolgreich aktualisiert.";
            header('Location: document_details_view.php?id=' . $document_id); // Zur Detailansicht
            exit;
        } catch (PDOException $e) {
            $errors[] = "Fehler beim Speichern der Dokument-Metadaten: " . $e->getMessage();
            error_log("Document Meta Edit Error: " . $e->getMessage());
        }
    }
}

$page_title = "Metadaten bearbeiten: " . he($document['title']);
include 'header.php';
?>

<h2><?php echo $page_title; ?></h2>

 <?php display_flash_messages(); // Funktion, um flash_success/error aus Session anzuzeigen 
?>

<?php if (!empty($errors)): ?>
    <div class="error"><strong>Fehler:</strong>
        <ul><?php foreach ($errors as $error) echo "<li>" . he($error) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<form action="document_edit.php?id=<?php echo he($document_id); ?>" method="post" class="edit-form">
    <input type="hidden" name="action" value="update_document_meta">
    <input type="hidden" name="document_id" value="<?php echo he($document_id); ?>"> 

    <fieldset>
        <legend>Dokumentinformationen (Datei bleibt unverändert)</legend>
        <div class="form-group">
            <label>Original-Dateiname:</label>
            <input type="text" value="<?php echo he($document['original_filename'] ?? 'N/A'); ?>" readonly style="background-color: #eee;">
        </div>
        <div class="form-group">
            <label>Gespeicherter Dateiname:</label>
            <input type="text" value="<?php echo he($document['stored_filename'] ?? 'N/A'); ?>" readonly style="background-color: #eee;">
        </div>
        <div class="form-group">
            <label>Gespeicherter Pfad:</label>
            <input type="text" value="<?php echo he($document['file_path'] ?? 'N/A'); ?>" readonly style="background-color: #eee;">
        </div>
        <div class="form-group">
            <label>MIME-Typ:</label>
            <input type="text" value="<?php echo he($document['mime_type'] ?? 'N/A'); ?>" readonly style="background-color: #eee;">
        </div>
        <div class="form-group">
            <label>Dateigröße:</label>
            <input type="text" value="<?php echo $document['file_size'] ? round($document['file_size'] / 1024, 1) . ' KB' : 'N/A'; ?>" readonly style="background-color: #eee;">
        </div>
    </fieldset>

    <fieldset>
        <legend>Bearbeitbare Metadaten</legend>
        <div class="form-group">
            <label for="title">Titel des Dokuments <span class="required">*</span>:</label>
            <input type="text" id="title" name="title" value="<?php echo he($title); ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Beschreibung (optional):</label>
            <textarea id="description" name="description" rows="3"><?php echo he($description); ?></textarea>
        </div>
        <div class="form-group">
            <label for="document_type">Dokumententyp (z.B. Richtlinie, Nachweis):</label>
            <input type="text" id="document_type" name="document_type" value="<?php echo he($document_type); ?>">
        </div>
        <div class="form-group">
            <label for="version">Version (optional):</label>
            <input type="text" id="version" name="version" value="<?php echo he($version); ?>">
        </div>
        <div class="form-group">
            <label for="status">Status:</label>
            <select id="status" name="status">
                <?php foreach ($status_options_docs as $status_option): ?>
                    <option value="<?php echo he($status_option); ?>" <?php echo ($status === $status_option ? 'selected' : ''); ?>><?php echo he(ucfirst($status_option)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="owner_id">Verantwortlicher (optional):</label>
            <select id="owner_id" name="owner_id">
                <option value="">-- Keiner ausgewählt --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo he($user['id']); ?>" <?php echo ($owner_id == $user['id'] ? 'selected' : ''); ?>><?php echo he($user['username']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="publication_date">Publikationsdatum (optional):</label>
            <input type="date" id="publication_date" name="publication_date" value="<?php echo he($publication_date_val); ?>">
        </div>
        <div class="form-group">
            <label for="review_date_for_doc">Nächstes Review-Datum (optional):</label>
            <input type="date" id="review_date_for_doc" name="review_date_for_doc" value="<?php echo he($review_date_val); ?>">
        </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-icon" aria-label="Speichern"><i class="fas fa-save"></i></button>
            <a href="documents.php" class="btn btn-danger btn-icon" aria-label="Abbrechen"><i class="fas fa-times"></i></a>
        </div>
    </fieldset>

   
</form>


<?php include 'footer.php'; ?>