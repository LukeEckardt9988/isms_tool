<?php
require_once 'functions.php';
requireLogin();

$asset_id = null;
// ID aus GET holen, wenn die Seite zum Bearbeiten aufgerufen wird
if (isset($_GET['id'])) {
    $asset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
}
// ID aus POST holen, wenn das Formular abgeschickt wird
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asset_id'])) {
    $asset_id_post = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
    if ($asset_id_post) {
        $asset_id = $asset_id_post;
    }
}

if (!$asset_id) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Ungültige oder fehlende Asset-ID.'];
    header('Location: assets.php');
    exit;
}

$pdo = getPDO();

// Asset-Daten laden
$stmt_asset_load = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
$stmt_asset_load->execute([$asset_id]);
$asset = $stmt_asset_load->fetch();

if (!$asset) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => "Asset mit ID " . he($asset_id) . " nicht gefunden."];
    header('Location: assets.php');
    exit;
}

// Lade alle RISIKEN für die Checkbox-Auswahl (sortiert)
$stmt_all_risks = $pdo->query("SELECT id, name, risk_level FROM risks ORDER BY risk_level DESC, name ASC");
$all_risks = $stmt_all_risks->fetchAll();

// Lade alle CONTROLS für die Checkbox-Auswahl und gruppiere sie (sortiert)
$stmt_controls_all = $pdo->query("SELECT id, control_id_iso, name FROM controls ORDER BY control_id_iso ASC");
$all_controls_raw = $stmt_controls_all->fetchAll();
$grouped_controls = [];
foreach ($all_controls_raw as $control_item_loop) {
    $category = strtoupper(explode('.', $control_item_loop['control_id_iso'])[0]);
    if (!isset($grouped_controls[$category])) $grouped_controls[$category] = [];
    $grouped_controls[$category][] = $control_item_loop;
}
ksort($grouped_controls);

// Bestehende Verknüpfungen laden
$stmt_linked_risks = $pdo->prepare("SELECT risk_id FROM asset_risks WHERE asset_id = ?");
$stmt_linked_risks->execute([$asset_id]);
$linked_risk_ids = $stmt_linked_risks->fetchAll(PDO::FETCH_COLUMN); // Gibt ein Array von IDs zurück

$stmt_linked_controls = $pdo->prepare("SELECT control_id FROM asset_controls WHERE asset_id = ?");
$stmt_linked_controls->execute([$asset_id]);
$linked_control_ids = $stmt_linked_controls->fetchAll(PDO::FETCH_COLUMN); // Gibt ein Array von IDs zurück


$errors = [];
// Formularfelder mit Werten aus der Datenbank oder POST vorbefüllen
$classification = $_POST['classification'] ?? ($asset['classification'] ?? 'intern');
$status_isms = $_POST['status_isms'] ?? ($asset['status_isms'] ?? 'aktiv');
// Die Spalte 'isms_description_notes' wurde entfernt, um den vorherigen Fehler zu vermeiden.
// Wenn Sie diese Spalte in Ihrer Datenbank haben, fügen Sie sie hier und im UPDATE wieder hinzu.
// $isms_description_notes = $_POST['isms_description_notes'] ?? ($asset['isms_description_notes'] ?? '');

// Für Mehrfachauswahl (initialisiert mit den aktuellen DB-Werten oder POST-Werten)
$selected_risk_ids = $_POST['risk_ids'] ?? $linked_risk_ids;
$selected_control_ids = $_POST['control_ids'] ?? $linked_control_ids;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_asset_details') {
    // Übernahme der POST-Werte für Asset-Metadaten
    $classification = $_POST['classification'] ?? 'intern';
    $status_isms = $_POST['status_isms'] ?? 'aktiv';
    // $isms_description_notes = trim($_POST['isms_description_notes'] ?? ''); // Falls Sie diese Spalte haben

    // Übernahme der ausgewählten IDs für Risiken und Controls aus den Checkboxen
    // Wenn keine Checkbox in einer Gruppe ausgewählt wurde, ist der entsprechende $_POST-Schlüssel nicht gesetzt.
    $selected_risk_ids_post = $_POST['risk_ids'] ?? []; // Fallback auf leeres Array
    $selected_control_ids_post = $_POST['control_ids'] ?? []; // Fallback auf leeres Array

    // Validierung der Asset-Metadaten (Beispiele)
    if (empty($classification)) $errors[] = "Klassifizierung ist erforderlich.";
    if (empty($status_isms)) $errors[] = "ISMS-Status ist erforderlich.";

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Asset-Metadaten aktualisieren
            // Stellen Sie sicher, dass Ihre `assets`-Tabelle die Spalten classification und status_isms hat.
            $sql_update_asset = "UPDATE assets SET 
                                    classification = ?, 
                                    status_isms = ?, 
                                    -- isms_description_notes = ?,  // Falls Sie diese Spalte haben
                                    updated_at = NOW() 
                                 WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update_asset);
            $params_asset_update = [
                $classification,
                $status_isms,
                // $isms_description_notes, // Falls Sie diese Spalte haben
                $asset_id
            ];
            $stmt_update->execute($params_asset_update);
            log_audit_trail('UPDATE_ASSET_ISMS', 'Asset', $asset_id, ['classification' => $classification]);

            // Risiko-Verknüpfungen aktualisieren (alte löschen, alle neu ausgewählten einfügen)
            $stmt_delete_risk_links = $pdo->prepare("DELETE FROM asset_risks WHERE asset_id = ?");
            $stmt_delete_risk_links->execute([$asset_id]);
            if (!empty($selected_risk_ids_post) && is_array($selected_risk_ids_post)) {
                $stmt_insert_risk_link = $pdo->prepare("INSERT INTO asset_risks (asset_id, risk_id) VALUES (?, ?)");
                foreach ($selected_risk_ids_post as $risk_id_to_link) {
                    if (is_numeric($risk_id_to_link)) { // Kleine Sicherheitsprüfung
                        $stmt_insert_risk_link->execute([$asset_id, (int)$risk_id_to_link]);
                    }
                }
            }

            // Control-Verknüpfungen aktualisieren (alte löschen, alle neu ausgewählten einfügen)
            $stmt_delete_control_links = $pdo->prepare("DELETE FROM asset_controls WHERE asset_id = ?");
            $stmt_delete_control_links->execute([$asset_id]);
            if (!empty($selected_control_ids_post) && is_array($selected_control_ids_post)) {
                $stmt_insert_control_link = $pdo->prepare("INSERT INTO asset_controls (asset_id, control_id) VALUES (?, ?)");
                foreach ($selected_control_ids_post as $control_id_to_link) {
                    if (is_numeric($control_id_to_link)) { // Kleine Sicherheitsprüfung
                        $stmt_insert_control_link->execute([$asset_id, (int)$control_id_to_link]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Asset-Details und Verknüpfungen erfolgreich aktualisiert."];
            header('Location: asset_view.php?id=' . $asset_id);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Fehler beim Speichern: " . $e->getMessage();
            error_log("Asset Edit Error (ID: $asset_id): " . $e->getMessage());
            // Wichtig: Die $selected_..._ids für die erneute Anzeige des Formulars korrekt setzen!
            $selected_risk_ids = $selected_risk_ids_post;
            $selected_control_ids = $selected_control_ids_post;
        }
    } else {
        // Bei Validierungsfehlern der Asset-Metadaten die POST-Werte für die Checkboxen beibehalten
        $selected_risk_ids = $selected_risk_ids_post;
        $selected_control_ids = $selected_control_ids_post;
    }
}

$page_title = "Asset bearbeiten (ISMS & Verknüpfungen): " . he($asset['name'] ?? $asset['inventory_id_extern']);
include 'header.php';
?>

<h2><?php echo $page_title; ?></h2>

<?php display_flash_messages(); ?>
<?php if (!empty($errors)): ?>
    <div class="error"><strong>Fehler:</strong>
        <ul><?php foreach ($errors as $error) echo "<li>" . he($error) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<form action="asset_edit.php?id=<?php echo he($asset_id); ?>" method="post" class="edit-form">
    <input type="hidden" name="action" value="update_asset_details">
    <input type="hidden" name="asset_id" value="<?php echo he($asset_id); ?>">

    <fieldset>
        <legend>Asset-Basisinformationen (aus Inventar)</legend>
        <div class="form-group"><label>Asset Name (Typ):</label><input type="text" value="<?php echo he($asset['name'] ?? 'N/A'); ?>" ></div>
        <div class="form-group"><label>Standort:</label><input type="text" value="<?php echo he($asset['location'] ?? 'N/A'); ?>" ></div>
        <div class="form-group"><label>Beschreibung (Details):</label><textarea rows="3" ><?php echo he($asset['description'] ?? 'N/A'); ?></textarea></div>
        <div class="form-group"><label>Externe ID:</label><input type="text" value="<?php echo he($asset['inventory_id_extern'] ?? 'N/A'); ?>" readonly></div>
    </fieldset>

    <fieldset>
        <legend>ISMS-Spezifische Informationen</legend>
        <div class="form-group">
            <label for="classification">Klassifizierung:</label>
            <select id="classification" name="classification">
                <?php $classification_options = ['öffentlich', 'intern', 'vertraulich', 'streng vertraulich']; ?>
                <?php foreach ($classification_options as $option): ?>
                    <option value="<?php echo he($option); ?>" <?php echo ($classification === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="status_isms">Status (ISMS-Sicht):</label>
            <select id="status_isms" name="status_isms">
                <?php $status_isms_options = ['aktiv', 'inaktiv', 'in Wartung', 'ausgemustert', 'unbekannt']; ?>
                <?php foreach ($status_isms_options as $option): ?>
                    <option value="<?php echo he($option); ?>" <?php echo ($status_isms === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </fieldset>

    <fieldset>
        <legend>Verknüpfte Risiken</legend>
        <div class="form-group checkbox-list-container">
            <p><small>Wählen Sie die Risiken aus, die dieses Asset betreffen.</small></p>
            <?php if (empty($all_risks)): ?>
                <p>Keine Risiken zur Auswahl vorhanden.</p>
            <?php else: ?>
                <?php foreach ($all_risks as $risk_item): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="risk_ids[]" value="<?php echo he($risk_item['id']); ?>"
                            <?php echo in_array($risk_item['id'], $selected_risk_ids) ? 'checked' : ''; ?>>
                        <?php echo he($risk_item['name']); ?> (Level: <?php echo he($risk_item['risk_level'] ?? 'N/A'); ?>)
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Zugeordnete Controls (Sicherheitsmaßnahmen)</legend>
        <div class="form-group checkbox-list-container">
            <p><small>Wählen Sie die Controls aus, die für dieses Asset relevant sind oder hier umgesetzt werden.</small></p>
            <?php if (empty($all_controls_raw)): ?>
                <p>Keine Controls zur Auswahl vorhanden.</p>
            <?php else: ?>
                <?php foreach ($grouped_controls as $category => $controls_in_category): ?>
                    <div class="control-category">
                        <h4><?php echo he($category); ?> (<?php echo count($controls_in_category); ?>)</h4>
                        <?php foreach ($controls_in_category as $control_item): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="control_ids[]" value="<?php echo he($control_item['id']); ?>"
                                    <?php echo in_array($control_item['id'], $selected_control_ids) ? 'checked' : ''; ?>>
                                <?php echo he($control_item['control_id_iso']); ?> - <?php echo he(substr($control_item['name'], 0, 60));
                                                                                        echo (strlen($control_item['name']) > 60 ? '...' : ''); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
         <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-icon" aria-label="Speichern"><i class="fas fa-save"></i></button>
            <a href="asset_view.php" class="btn btn-danger btn-icon" aria-label="Abbrechen"><i class="fas fa-times"></i></a>
        </div>
    </fieldset>


</form>

<?php include 'footer.php'; ?>