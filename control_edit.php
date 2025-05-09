<?php
require_once 'functions.php';
requireLogin();

$control_db_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Die interne DB ID
if (!$control_db_id) {
    header('Location: controls.php?status=error_invalid_id');
    exit;
}

$pdo = getPDO();

// Aktuelle Control-Daten laden
$stmt = $pdo->prepare("SELECT * FROM controls WHERE id = ?");
$stmt->execute([$control_db_id]);
$control = $stmt->fetch();

if (!$control) {
    header('Location: controls.php?status=error_notfound');
    exit;
}

// Benutzer für Dropdown "Verantwortlicher"
$users = $pdo->query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username")->fetchAll();
// Dokumente für Dropdown "Verknüpftes Dokument"
$documents = $pdo->query("SELECT id, title FROM documents ORDER BY title")->fetchAll(); // TODO: Filtern nach relevanten Dokumenttypen?

// ENUM-Optionen aus der Datenbankstruktur (oder hier hartcodiert, wenn sie stabil sind)
$control_type_options = ['präventiv', 'detektiv', 'korrektiv', 'leitend', 'sonstiges'];
$implementation_status_options = ['nicht relevant', 'geplant', 'in Umsetzung', 'teilweise umgesetzt', 'vollständig umgesetzt', 'verworfen'];
$priority_options = ['niedrig', 'mittel', 'hoch', 'kritisch'];
$effectiveness_options = ['nicht bewertet', 'niedrig', 'mittel', 'hoch'];

$errors = [];
// Formularfelder mit Werten aus der Datenbank vorbefüllen
$control_id_iso = $control['control_id_iso'];
$name = $control['name'];
$description = $control['description'];
$source = $control['source'];
$control_type = $control['control_type'];
$implementation_status = $control['implementation_status'];
$status_description = $control['status_description'];
$justification_applicability = $control['justification_applicability'];
$priority = $control['priority'];
$owner_id = $control['owner_id'];
$responsible_department = $control['responsible_department'];
$effectiveness = $control['effectiveness'];
$effectiveness_review_notes = $control['effectiveness_review_notes'];
$last_review_date = $control['last_review_date'];
$next_review_date = $control['next_review_date'];
$linked_policy_document_id = $control['linked_policy_document_id'];
$notes = $control['notes'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Daten aus POST-Request übernehmen und validieren
    // (control_id_iso, name, description, source werden hier i.d.R. nicht geändert, da sie vom Import kommen)
    // $control_id_iso = trim($_POST['control_id_iso'] ?? $control['control_id_iso']); // Ggf. anpassbar machen?
    // $name = trim($_POST['name'] ?? $control['name']);
    // $description = trim($_POST['description'] ?? $control['description']);
    // $source = trim($_POST['source'] ?? $control['source']);

    $control_type_post = $_POST['control_type'] ?? '';
    $control_type = in_array($control_type_post, $control_type_options) ? $control_type_post : null;

    $implementation_status_post = $_POST['implementation_status'] ?? '';
    $implementation_status = in_array($implementation_status_post, $implementation_status_options) ? $implementation_status_post : $control['implementation_status'];

    $status_description = trim($_POST['status_description'] ?? '');
    $justification_applicability = trim($_POST['justification_applicability'] ?? '');

    $priority_post = $_POST['priority'] ?? '';
    $priority = in_array($priority_post, $priority_options) ? $priority_post : $control['priority'];

    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $responsible_department = trim($_POST['responsible_department'] ?? '');

    $effectiveness_post = $_POST['effectiveness'] ?? '';
    $effectiveness = in_array($effectiveness_post, $effectiveness_options) ? $effectiveness_post : $control['effectiveness'];
    
    $effectiveness_review_notes = trim($_POST['effectiveness_review_notes'] ?? '');

    $last_review_date_input = trim($_POST['last_review_date'] ?? '');
    $last_review_date = !empty($last_review_date_input) ? date('Y-m-d', strtotime($last_review_date_input)) : null;

    $next_review_date_input = trim($_POST['next_review_date'] ?? '');
    $next_review_date = !empty($next_review_date_input) ? date('Y-m-d', strtotime($next_review_date_input)) : null;
    
    $linked_policy_document_id = !empty($_POST['linked_policy_document_id']) ? (int)$_POST['linked_policy_document_id'] : null;
    $notes = trim($_POST['notes'] ?? '');

    // Validierung (Beispiel)
    // if (empty($name)) { $errors[] = 'Der Name des Controls ist erforderlich.'; }
    if (!empty($last_review_date_input) && !$last_review_date) { $errors[] = 'Letztes Review-Datum hat ein ungültiges Format.';}
    if (!empty($next_review_date_input) && !$next_review_date) { $errors[] = 'Nächstes Review-Datum hat ein ungültiges Format.';}


    if (empty($errors)) {
        try {
            $sql = "UPDATE controls SET
                        control_type = ?, implementation_status = ?, status_description = ?, justification_applicability = ?,
                        priority = ?, owner_id = ?, responsible_department = ?,
                        effectiveness = ?, effectiveness_review_notes = ?,
                        last_review_date = ?, next_review_date = ?,
                        linked_policy_document_id = ?, notes = ?,
                        updated_at = NOW()
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $control_type, $implementation_status, $status_description, $justification_applicability,
                $priority, $owner_id, $responsible_department,
                $effectiveness, $effectiveness_review_notes,
                $last_review_date, $next_review_date,
                $linked_policy_document_id, $notes,
                $control_db_id
            ]);
            log_audit_trail('UPDATE_CONTROL', 'Control', $control_db_id, ['control_id_iso' => $control_id_iso, 'status' => $implementation_status]);
            header('Location: control_view.php?id=' . $control_db_id . '&status=success_edit'); // Zurück zur Detailansicht
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Aktualisieren des Controls: ' . $e->getMessage();
            error_log("Control Edit Error: " . $e->getMessage());
        }
    }
}

include 'header.php';
?>

<h2>Control bearbeiten: <?php echo he($control['control_id_iso']); ?> - <?php echo he($control['name']); ?></h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <strong>Fehler:</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo he($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="control_edit.php?id=<?php echo he($control_db_id); ?>" method="post" class="edit-form">
    <fieldset>
        <legend>Basisinformationen (aus Import)</legend>
        <div class="form-group readonly">
            <label>Control ID (BSI/ISO):</label>
            <input type="text" value="<?php echo he($control_id_iso); ?>" readonly>
        </div>
        <div class="form-group readonly">
            <label>Name / Titel:</label>
            <input type="text" value="<?php echo he($name); ?>" readonly>
        </div>
        <div class="form-group readonly">
            <label>Beschreibung:</label>
            <textarea rows="5" readonly><?php echo he($description); ?></textarea>
        </div>
        <div class="form-group readonly">
            <label>Quelle:</label>
            <input type="text" value="<?php echo he($source); ?>" readonly>
        </div>
    </fieldset>

    <fieldset>
        <legend>ISMS Management Details (SoA-relevant)</legend>
        <div class="form-group">
            <label for="control_type">Control Typ:</label>
            <select id="control_type" name="control_type">
                <option value="">-- Bitte wählen --</option>
                <?php foreach ($control_type_options as $option): ?>
                    <option value="<?php echo he($option); ?>" <?php echo ($control_type === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="implementation_status">Implementierungsstatus:</label>
            <select id="implementation_status" name="implementation_status">
                <?php foreach ($implementation_status_options as $option): ?>
                    <option value="<?php echo he($option); ?>" <?php echo ($implementation_status === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="status_description">Statusbeschreibung / Begründung (z.B. für "nicht relevant"):</label>
            <textarea id="status_description" name="status_description" rows="3"><?php echo he($status_description); ?></textarea>
        </div>
         <div class="form-group">
            <label for="justification_applicability">Begründung der Anwendbarkeit (SoA):</label>
            <textarea id="justification_applicability" name="justification_applicability" rows="3"><?php echo he($justification_applicability); ?></textarea>
        </div>
        <div class="form-group">
            <label for="priority">Priorität:</label>
            <select id="priority" name="priority">
                <?php foreach ($priority_options as $option): ?>
                    <option value="<?php echo he($option); ?>" <?php echo ($priority === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="owner_id">Verantwortlicher (Owner):</label>
            <select id="owner_id" name="owner_id">
                <option value="">-- Nicht zugewiesen --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo he($user['id']); ?>" <?php echo ($owner_id == $user['id'] ? 'selected' : ''); ?>>
                        <?php echo he($user['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="responsible_department">Verantwortliche Abteilung:</label>
            <input type="text" id="responsible_department" name="responsible_department" value="<?php echo he($responsible_department); ?>">
        </div>
        <div class="form-group">
            <label for="effectiveness">Wirksamkeit:</label>
            <select id="effectiveness" name="effectiveness">
                <?php foreach ($effectiveness_options as $option): ?>
                    <option value="<?php echo he($option); ?>" <?php echo ($effectiveness === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="effectiveness_review_notes">Notizen zur Wirksamkeitsprüfung:</label>
            <textarea id="effectiveness_review_notes" name="effectiveness_review_notes" rows="3"><?php echo he($effectiveness_review_notes); ?></textarea>
        </div>
        <div class="form-group">
            <label for="last_review_date">Letzte Überprüfung am:</label>
            <input type="date" id="last_review_date" name="last_review_date" value="<?php echo he($last_review_date ? date('Y-m-d', strtotime($last_review_date)) : ''); ?>">
        </div>
        <div class="form-group">
            <label for="next_review_date">Nächste Überprüfung am:</label>
            <input type="date" id="next_review_date" name="next_review_date" value="<?php echo he($next_review_date ? date('Y-m-d', strtotime($next_review_date)) : ''); ?>">
        </div>
        <div class="form-group">
            <label for="linked_policy_document_id">Verknüpftes Richtliniendokument:</label>
            <select id="linked_policy_document_id" name="linked_policy_document_id">
                <option value="">-- Kein Dokument verknüpft --</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?php echo he($doc['id']); ?>" <?php echo ($linked_policy_document_id == $doc['id'] ? 'selected' : ''); ?>>
                        <?php echo he($doc['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="notes">Allgemeine Notizen:</label>
            <textarea id="notes" name="notes" rows="3"><?php echo he($notes); ?></textarea>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn">Änderungen speichern</button>
        <a href="control_view.php?id=<?php echo he($control_db_id); ?>" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>

<style>
    .edit-form fieldset {
        border: 1px solid #ccc;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .edit-form legend {
        font-weight: bold;
        color: #337ab7;
        padding: 0 5px;
    }
    .form-group.readonly input,
    .form-group.readonly textarea {
        background-color: #eee;
        cursor: not-allowed;
    }
    .form-actions {
        margin-top: 20px;
    }
</style>

<?php include 'footer.php'; ?>