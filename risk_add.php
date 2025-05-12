<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();
$is_new_risk = true; // Diese Seite ist immer für neue Risiken

// Lade alle Assets für die Checkbox-Auswahl
$stmt_all_assets = $pdo->query("SELECT id, name, inventory_id_extern FROM assets ORDER BY name ASC");
$all_assets_for_checkboxes = $stmt_all_assets->fetchAll(PDO::FETCH_ASSOC);

// Lade alle CONTROLS für die Checkbox-Auswahl (gruppiert)
$stmt_controls_all = $pdo->query("SELECT id, control_id_iso, name FROM controls ORDER BY control_id_iso ASC");
$all_controls_raw = $stmt_controls_all->fetchAll(PDO::FETCH_ASSOC);
$grouped_controls_for_checkboxes = [];
foreach ($all_controls_raw as $control_item_loop) {
    $category = strtoupper(explode('.', $control_item_loop['control_id_iso'])[0]);
    if (!isset($grouped_controls_for_checkboxes[$category])) $grouped_controls_for_checkboxes[$category] = [];
    $grouped_controls_for_checkboxes[$category][] = $control_item_loop;
}
ksort($grouped_controls_for_checkboxes);

// Benutzer für Dropdown Risikoeigner
$users = $pdo->query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username")->fetchAll();

// ENUM-Optionen
$likelihood_options = ['sehr gering', 'gering', 'mittel', 'hoch', 'sehr hoch'];
$impact_options = ['sehr gering', 'gering', 'mittel', 'hoch', 'sehr hoch'];
$status_options = ['identifiziert', 'analysiert', 'bewertet', 'in Behandlung', 'behandelt', 'akzeptiert', 'geschlossen'];
$treatment_options = ['vermeiden', 'mindern', 'übertragen', 'akzeptieren'];

$errors = [];
// Initialwerte für Formularfelder (oder Werte aus POST, falls Formular erneut wegen Fehlern geladen wird)
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$risk_source = trim($_POST['risk_source'] ?? '');
$likelihood = $_POST['likelihood'] ?? 'mittel';
$impact = $_POST['impact'] ?? 'mittel';
$status = $_POST['status'] ?? 'identifiziert';
$treatment_option = $_POST['treatment_option'] ?? '';
$treatment_plan = trim($_POST['treatment_plan'] ?? '');
$owner_id = $_POST['owner_id'] ?? null;
$review_date = $_POST['review_date'] ?? '';

// Für Checkboxen: Initialisierung mit POST-Daten bei Fehler, sonst leer
$selected_asset_ids = $_POST['asset_ids'] ?? [];
$selected_control_ids = $_POST['control_ids'] ?? [];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_risk') {
    // Datenübernahme und Validierung
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $risk_source = trim($_POST['risk_source'] ?? '');
    $likelihood = $_POST['likelihood'] ?? 'mittel';
    $impact = $_POST['impact'] ?? 'mittel';
    $status = $_POST['status'] ?? 'identifiziert';
    $treatment_option_post = $_POST['treatment_option'] ?? '';
    $treatment_option = in_array($treatment_option_post, $treatment_options) ? $treatment_option_post : null;
    $treatment_plan = trim($_POST['treatment_plan'] ?? '');
    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $review_date_input = trim($_POST['review_date'] ?? '');
    $review_date = !empty($review_date_input) ? date('Y-m-d', strtotime($review_date_input)) : null;

    // Übernahme der ausgewählten IDs für Assets und Controls aus Checkboxen
    $selected_asset_ids_post = isset($_POST['asset_ids']) && is_array($_POST['asset_ids'])
        ? array_map('intval', $_POST['asset_ids'])
        : [];
    $selected_control_ids_post = isset($_POST['control_ids']) && is_array($_POST['control_ids'])
        ? array_map('intval', $_POST['control_ids'])
        : [];

    if (empty($name)) $errors[] = "Name des Risikos ist erforderlich.";
    if (!empty($review_date_input) && !$review_date) {
        $errors[] = 'Das Review-Datum hat ein ungültiges Format.';
    }
    // Weitere Validierungen...

    if (empty($errors)) {
        $calculated_risk_level = calculateRiskLevel($likelihood, $impact);
        $pdo->beginTransaction();
        try {
            $sql_risk = "INSERT INTO risks (name, description, risk_source, likelihood, impact, risk_level, status, treatment_option, treatment_plan, owner_id, review_date, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt_risk = $pdo->prepare($sql_risk);
            $stmt_risk->execute([
                $name,
                $description,
                $risk_source,
                $likelihood,
                $impact,
                $calculated_risk_level,
                $status,
                $treatment_option,
                $treatment_plan,
                $owner_id,
                $review_date
            ]);
            $new_risk_id = $pdo->lastInsertId();
            log_audit_trail('CREATE_RISK', 'Risk', $new_risk_id, ['name' => $name]);

            // Asset-Verknüpfungen speichern
            if (!empty($selected_asset_ids_post)) {
                $stmt_insert_asset_link = $pdo->prepare("INSERT INTO asset_risks (risk_id, asset_id) VALUES (?, ?)");
                foreach (array_unique($selected_asset_ids_post) as $asset_id_to_link) {
                    // Erlaube positive IDs und spezielle IDs (z.B. -1 für "Alle Assets"), aber nicht 0
                    if ($asset_id_to_link != 0) {
                        $stmt_insert_asset_link->execute([$new_risk_id, $asset_id_to_link]);
                    }
                }
            }

            // Control-Verknüpfungen speichern
            if (!empty($selected_control_ids_post)) {
                $stmt_insert_control_link = $pdo->prepare("INSERT INTO risk_controls (risk_id, control_id) VALUES (?, ?)");
                foreach (array_unique($selected_control_ids_post) as $control_id_to_link) {
                    if ($control_id_to_link > 0) $stmt_insert_control_link->execute([$new_risk_id, $control_id_to_link]);
                }
            }

            $pdo->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Risiko '" . he($name) . "' erfolgreich erstellt."];
            header('Location: risks.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Fehler beim Speichern: " . $e->getMessage();
            error_log("Risk Add Error: " . $e->getMessage());
            // POST-Werte für Checkboxen für erneute Anzeige beibehalten
            $selected_asset_ids = $selected_asset_ids_post;
            $selected_control_ids = $selected_control_ids_post;
        }
    } else {
        // Bei Validierungsfehlern der Risiko-Metadaten die POST-Werte für Checkboxen beibehalten
        $selected_asset_ids = $selected_asset_ids_post;
        $selected_control_ids = $selected_control_ids_post;
    }
}

$page_title = "Neues Risiko erstellen";
include 'header.php';
?>

<h2><?php echo $page_title; ?></h2>

<?php display_flash_messages(); // Stellen Sie sicher, dass diese Funktion in functions.php existiert 
?>
<?php if (!empty($errors)): ?>
    <div class="error"><strong>Fehler:</strong>
        <ul><?php foreach ($errors as $e) echo "<li>" . he($e) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<form action="risk_add.php" method="post" class="edit-form">
    <input type="hidden" name="action" value="create_risk">

    <fieldset>
        <legend>Risikodetails</legend>
        <div class="form-group"><label for="name">Name:</label><input type="text" id="name" name="name" value="<?php echo he($name); ?>" required></div>
        <div class="form-group"><label for="description">Beschreibung:</label><textarea id="description" name="description" rows="3"><?php echo he($description); ?></textarea></div>
        <div class="form-group"><label for="risk_source">Risikoquelle:</label><textarea id="risk_source" name="risk_source" rows="3"><?php echo he($risk_source); ?></textarea></div>
        <div class="form-group"><label for="likelihood">Wahrscheinlichkeit:</label><select id="likelihood" name="likelihood"><?php foreach ($likelihood_options as $o): ?><option value="<?php echo he($o); ?>" <?php if ($likelihood == $o) echo ' selected'; ?>><?php echo he(ucfirst($o)); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="impact">Auswirkung:</label><select id="impact" name="impact"><?php foreach ($impact_options as $o): ?><option value="<?php echo he($o); ?>" <?php if ($impact == $o) echo ' selected'; ?>><?php echo he(ucfirst($o)); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Berechnetes Level:</label><input type="text" value="<?php echo he(calculateRiskLevel($likelihood, $impact)); ?>" readonly"></div>
        <div class="form-group"><label for="status">Status:</label><select id="status" name="status"><?php foreach ($status_options as $o): ?><option value="<?php echo he($o); ?>" <?php if ($status == $o) echo ' selected'; ?>><?php echo he(ucfirst($o)); ?></option><?php endforeach; ?></select></div>
    </fieldset>

    <fieldset>
        <legend>Betroffene Assets</legend>
        <div class="form-group checkbox-list-container">
            <p><small>Wählen Sie die Assets aus, die von diesem Risiko betroffen sind. Sie können hier auch "Alle Assets (Global)" auswählen.</small></p>
            <?php if (empty($all_assets_for_checkboxes)): ?>
                <p>Keine Assets zur Auswahl vorhanden.</p>
            <?php else: ?>
                <?php foreach ($all_assets_for_checkboxes as $asset_item): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="asset_ids[]" value="<?php echo he($asset_item['id']); ?>"
                            <?php echo in_array($asset_item['id'], $selected_asset_ids) ? 'checked' : ''; ?>>
                        <?php echo he($asset_item['name']); ?>
                        <?php if ($asset_item['inventory_id_extern'] && $asset_item['inventory_id_extern'] !== 'GLOBAL_ALL_ASSETS'): ?>
                            (Ext.ID: <?php echo he($asset_item['inventory_id_extern']); ?>)
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Risikobehandlung</legend>
        <div class="form-group"><label for="treatment_option">Behandlungsoption:</label><select id="treatment_option" name="treatment_option">
                <option value="">--Wählen--</option><?php foreach ($treatment_options as $o): ?><option value="<?php echo he($o); ?>" <?php if ($treatment_option == $o) echo ' selected'; ?>><?php echo he(ucfirst($o)); ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="treatment_plan">Behandlungsplan:</label><textarea id="treatment_plan" name="treatment_plan" rows="3"><?php echo he($treatment_plan); ?></textarea></div>
    </fieldset>

    <fieldset>
        <legend>Zugeordnete Controls (Maßnahmen)</legend>
        <div class="form-group checkbox-list-container">
            <p><small>Wählen Sie die Controls aus, die zur Behandlung dieses Risikos beitragen.</small></p>
            <?php if (empty($all_controls_raw)): ?>
                <p>Keine Controls zur Auswahl vorhanden.</p>
            <?php else: ?>
                <?php foreach ($grouped_controls_for_checkboxes as $category => $controls_in_category): ?>
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
    </fieldset>

    <fieldset>
        <legend>Verantwortlichkeiten</legend>
        <div class="form-group"><label for="owner_id">Risikoeigner:</label><select id="owner_id" name="owner_id">
                <option value="">--Wählen--</option><?php foreach ($users as $u): ?><option value="<?php echo he($u['id']); ?>" <?php if ($owner_id == $u['id']) echo ' selected'; ?>><?php echo he($u['username']); ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="review_date">Nächstes Review:</label><input type="date" id="review_date" name="review_date" value="<?php echo he($review_date); ?>"></div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn">Risiko erstellen</button>
        <a href="risks.php" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>


<?php include 'footer.php'; ?>