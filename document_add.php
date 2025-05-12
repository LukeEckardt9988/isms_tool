<?php
require_once 'functions.php';
requireLogin();

define('UPLOAD_DIR_DOCS', 'uploads/documents/'); // Sicherstellen, dass dies existiert und beschreibbar ist
if (!is_dir(UPLOAD_DIR_DOCS)) {
    if (!mkdir(UPLOAD_DIR_DOCS, 0755, true)) {
        die("FEHLER: Upload-Verzeichnis konnte nicht erstellt werden. Bitte manuell anlegen und Berechtigungen prüfen: " . UPLOAD_DIR_DOCS);
    }
}

$pdo = getPDO();
$users = $pdo->query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username")->fetchAll(); // Für Verantwortlichen-Dropdown

$errors = [];
$success_message = '';

// Initialwerte
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$document_type = $_POST['document_type'] ?? '';
$version = $_POST['version'] ?? '';
$status = $_POST['status'] ?? 'Entwurf';
$owner_id = $_POST['owner_id'] ?? null;
// ... weitere Felder für valid_from, valid_to, review_date können hier initialisiert werden

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $document_type = trim($_POST['document_type'] ?? '');
    $version = trim($_POST['version'] ?? '');
    $status = $_POST['status'] ?? 'Entwurf';
    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;

    // Validierung der Metadaten
    if (empty($title)) {
        $errors[] = 'Ein Titel für das Dokument ist erforderlich.';
    }
    if (empty($_FILES['document_file']) || $_FILES['document_file']['error'] == UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Es wurde keine Datei zum Hochladen ausgewählt.';
    } elseif ($_FILES['document_file']['error'] != UPLOAD_ERR_OK) {
        $errors[] = 'Fehler beim Datei-Upload: Code ' . $_FILES['document_file']['error'];
    }

    if (empty($errors)) {
        $file_tmp_path = $_FILES['document_file']['tmp_name'];
        $original_file_name = basename($_FILES['document_file']['name']);
        $file_size = $_FILES['document_file']['size'];
        $file_type = $_FILES['document_file']['type']; // MIME-Typ vom Browser

        // Dateiendung extrahieren und Dateinamen sanieren/eindeutig machen
        $file_extension = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
        $safe_original_name_part = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($original_file_name, PATHINFO_FILENAME));
        // Eindeutiger Name, um Überschreibungen zu verhindern und Sicherheit zu erhöhen
        $stored_file_name = uniqid('doc_', true) . '_' . $safe_original_name_part . '.' . $file_extension;
        $destination_path_on_server = UPLOAD_DIR_DOCS . $stored_file_name; // Der Pfad, wo die Datei auf dem Server landet
        $file_path_for_db = UPLOAD_DIR_DOCS . $stored_file_name; // Kann gleich sein oder relativ zum Webroot, je nach Download-Logik

        // Erlaubte Dateitypen (Beispiel, anpassen!)
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'zip'];
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Ungültiger Dateityp '$file_extension'. Erlaubt sind: " . implode(', ', $allowed_extensions);
        }
        // Maximale Dateigröße (z.B. 20MB)
        $max_file_size = 20 * 1024 * 1024;
        if ($file_size > $max_file_size) {
            $errors[] = "Datei ist zu groß (Max: " . ($max_file_size / 1024 / 1024) . " MB).";
        }
        if ($file_size === 0) {
            $errors[] = "Die hochgeladene Datei ist leer.";
        }


        if (empty($errors)) {
            if (move_uploaded_file($file_tmp_path, $destination_path_on_server)) {
                try {
                    $sql = "INSERT INTO documents (title, description, document_type, version, status, original_filename, stored_filename, file_path, mime_type, file_size, owner_id, uploaded_by_user_id, uploaded_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $title, $description ?: null, $document_type ?: null, $version ?: null, $status,
                        $original_file_name, $stored_file_name, $file_path_for_db, $file_type, $file_size,
                        $owner_id, getCurrentUserId()
                    ]);
                    $new_doc_id = $pdo->lastInsertId();
                    log_audit_trail('UPLOAD_DOCUMENT', 'Document', $new_doc_id, ['filename' => $original_file_name, 'title' => $title]);
                    $_SESSION['flash_success'] = "Dokument '".he($title)."' erfolgreich hochgeladen!";
                    header('Location: documents.php'); // Zurück zur Liste
                    exit;

                } catch (PDOException $e) {
                    $errors[] = "Datenbankfehler beim Speichern des Dokuments: " . $e->getMessage();
                    // Ggf. hochgeladene Datei wieder löschen, wenn DB-Eintrag fehlschlägt
                    if (file_exists($destination_path_on_server)) {
                        unlink($destination_path_on_server);
                    }
                }
            } else {
                $errors[] = "Fehler beim Verschieben der hochgeladenen Datei. Überprüfen Sie die Berechtigungen des Upload-Verzeichnisses.";
            }
        }
    }
}

$page_title = "Neues Dokument hochladen";
include 'header.php';
?>

<h2><?php echo $page_title; ?></h2>

<?php if (!empty($errors)): ?>
    <div class="error"><strong>Fehler:</strong><ul><?php foreach ($errors as $error) echo "<li>".he($error)."</li>"; ?></ul></div>
<?php endif; ?>
<?php if (!empty($success_message)): ?>
    <p class="success"><?php echo he($success_message); ?></p>
<?php endif; ?>


<form action="document_add.php" method="post" enctype="multipart/form-data" class="edit-form">
    <input type="hidden" name="action" value="upload_document">
    <fieldset>
        <legend>Dokumentdetails</legend>
        <div class="form-group">
            <label for="title">Titel des Dokuments <span class="required">*</span>:</label>
            <input type="text" id="title" name="title" value="<?php echo he($title); ?>" required>
        </div>
        <div class="form-group">
            <label for="document_file">Datei auswählen <span class="required">*</span>:</label>
            <input type="file" id="document_file" name="document_file" required>
            <small>Max. Dateigröße: <?php echo ($max_file_size ?? 20 * 1024 * 1024) / 1024 / 1024; ?> MB. Erlaubte Typen: pdf, doc(x), xls(x), ppt(x), txt, jpg, png, zip.</small>
        </div>
        <div class="form-group">
            <label for="description">Beschreibung (optional):</label>
            <textarea id="description" name="description" rows="3"><?php echo he($description); ?></textarea>
        </div>
        <div class="form-group">
            <label for="document_type">Dokumententyp (z.B. Richtlinie, Nachweis, Verfahren):</label>
            <input type="text" id="document_type" name="document_type" value="<?php echo he($document_type); ?>">
        </div>
        <div class="form-group">
            <label for="version">Version (optional):</label>
            <input type="text" id="version" name="version" value="<?php echo he($version); ?>">
        </div>
        <div class="form-group">
            <label for="status">Status:</label>
            <select id="status" name="status">
                <option value="Entwurf" <?php echo ($status === 'Entwurf' ? 'selected' : ''); ?>>Entwurf</option>
                <option value="In Prüfung" <?php echo ($status === 'In Prüfung' ? 'selected' : ''); ?>>In Prüfung</option>
                <option value="Genehmigt" <?php echo ($status === 'Genehmigt' ? 'selected' : ''); ?>>Genehmigt</option>
                <option value="Archiviert" <?php echo ($status === 'Archiviert' ? 'selected' : ''); ?>>Archiviert</option>
                <option value="Veraltet" <?php echo ($status === 'Veraltet' ? 'selected' : ''); ?>>Veraltet</option>
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
        </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn">Dokument hochladen und speichern</button>
        <a href="documents.php" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>
<style> /* Ggf. in Ihre Haupt-style.css auslagern */
    .required { color: red; }
    .edit-form fieldset { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
    .edit-form legend { font-weight: bold; color: #337ab7; padding: 0 5px; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: .3rem; font-weight: bold; }
    .form-group input[type="text"], .form-group input[type="file"], .form-group textarea, .form-group select {
        width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;
    }
    .form-group small { display: block; margin-top: 4px; font-size: 0.85em; color: #666; }
    .form-actions { margin-top: 20px; }
    .success { color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 10px; border-radius: 4px; margin-bottom: 15px;}
</style>
<?php include 'footer.php'; ?>