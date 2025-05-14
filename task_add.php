<?php
require_once 'functions.php';
requireLogin();

$current_user_id = getCurrentUserId();
$current_user_role = getCurrentUserRole();

// Berechtigungsprüfung: Darf der Benutzer Aufgaben erstellen?
if (!hasPermission($current_user_role, 'create') && !hasPermission($current_user_role, 'manage_tasks')) {
     // Statt Alert, Flash Message nutzen und weiterleiten
    set_flash_message('error', 'Fehlende Autorisierung.');
    header('Location: tasks.php'); // Zurück zur Aufgabenliste
    exit();
}

$pdo = getPDO(); // DB-Verbindung holen

// --- Daten für die Checkbox-Listen und Dropdowns holen ---
// Nutze deine implementierten Funktionen oder direkte PDO-Abfragen wie in risk_add.php

// Für Benutzer (Dropdown Zugewiesen an)
$users = $pdo->query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username")->fetchAll(PDO::FETCH_ASSOC); // Oder nutze get_users()

// Für Assets (Checkboxen)
// PASSE DIE SPALTENNAMEN 'id' und 'name' AN DEINE ASSETS-TABELLE AN!
$stmt_all_assets = $pdo->query("SELECT id, name FROM assets ORDER BY name ASC");
$all_assets_for_checkboxes = $stmt_all_assets->fetchAll(PDO::FETCH_ASSOC); // Sollte ID und Namen haben

// Für Risiken (Checkboxen)
// PASSE DIE SPALTENNAMEN 'id' und 'name' AN DEINE RISKS-TABELLE AN! <-- Hinweis im Kommentar angepasst
$stmt_all_risks = $pdo->query("SELECT id, name FROM risks ORDER BY name ASC"); // <-- 'title' wurde zu 'name' geändert
$all_risks_for_checkboxes = $stmt_all_risks->fetchAll(PDO::FETCH_ASSOC);

// Für Controls (Checkboxen) - Annahme: Gruppierung nach den ersten Ziffern wie bei Risiken
// PASSE DIE SPALTENNAMEN 'id', 'control_id_iso', 'name' AN DEINE CONTROLS-TABELLE AN!
$stmt_controls_all = $pdo->query("SELECT id, control_id_iso, name FROM controls ORDER BY control_id_iso ASC");
$all_controls_raw = $stmt_controls_all->fetchAll(PDO::FETCH_ASSOC);
$grouped_controls_for_checkboxes = [];
foreach ($all_controls_raw as $control_item_loop) {
    $category = strtoupper(explode('.', $control_item_loop['control_id_iso'] ?? '0.0')[0]); // Handle potential missing control_id_iso
    if (!isset($grouped_controls_for_checkboxes[$category])) $grouped_controls_for_checkboxes[$category] = [];
    $grouped_controls_for_checkboxes[$category][] = $control_item_loop;
}
ksort($grouped_controls_for_checkboxes);


// Für Dokumente (Checkboxen)
// PASSE DIE SPALTENNAMEN 'id' und 'title' AN DEINE DOCUMENTS-TABELLE AN!
$stmt_all_documents = $pdo->query("SELECT id, title FROM documents ORDER BY title ASC");
$all_documents_for_checkboxes = $stmt_all_documents->fetchAll(PDO::FETCH_ASSOC); // Sollte ID und Titel haben


// --- Initialwerte für Formularfelder (oder Werte aus POST, falls Formular erneut wegen Fehlern geladen wird) ---
// Diese Logik sorgt dafür, dass die eingegebenen Daten bei Validierungsfehlern erhalten bleiben
$name = trim($_POST['title'] ?? ''); // Task-Titel entspricht Risk-Name
$description = trim($_POST['description'] ?? '');
$due_date = trim($_POST['due_date'] ?? '');
$priority = $_POST['priority'] ?? 'medium';
$assigned_to = $_POST['assigned_to'] ?? null; // Ursprünglich zugewiesen

// Für Checkboxen: Initialisierung mit POST-Daten bei Fehler, sonst leer
$selected_asset_ids = $_POST['asset_ids'] ?? [];
$selected_risk_ids = $_POST['risk_ids'] ?? [];
$selected_control_ids = $_POST['control_ids'] ?? [];
$selected_document_ids = $_POST['document_ids'] ?? [];


// --- Hier würden normalerweise POST-Verarbeitung und Validierung stattfinden ---
// --- Das machen wir aber zentral in task_process.php ---
// --- Wenn wir hier sind, wurde die Seite entweder frisch geladen oder task_process.php hat wegen eines Fehlers hierhin zurückgeleitet ---

$page_title = "Neue Aufgabe erstellen";
include 'header.php'; // Dein Header
?>

<div class="container mt-4">
    <h2><?php echo he($page_title); ?></h2>

    <?php display_flash_messages(); // Zeigt Nachrichten an, die z.B. von task_process.php gesetzt wurden ?>

    <form action="task_process.php" method="post" class="edit-form"> <input type="hidden" name="action" value="add">

        <fieldset>
            <legend>Aufgabendetails</legend>
            <div class="form-group">
                <label for="title">Titel:</label>
                <input type="text" id="title" name="title" value="<?php echo he($name); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Beschreibung:</label>
                <textarea id="description" name="description" rows="3"><?php echo he($description); ?></textarea>
            </div>
            <div class="form-group">
                <label for="due_date">Fällig bis:</label>
                <input type="date" id="due_date" name="due_date" value="<?php echo he($due_date); ?>">
            </div>
            <div class="form-group">
                <label for="priority">Priorität:</label>
                <select id="priority" name="priority">
                    <option value="low" <?php if ($priority == 'low') echo ' selected'; ?>>Niedrig</option>
                    <option value="medium" <?php if ($priority == 'medium') echo ' selected'; ?>>Mittel</option>
                    <option value="high" <?php if ($priority == 'high') echo ' selected'; ?>>Hoch</option>
                </select>
            </div>
            <div class="form-group">
                <label for="assigned_to">Ursprünglich zugewiesen an:</label>
                <select id="assigned_to" name="assigned_to">
                    <option value="">--Wählen--</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo he($user['id']); ?>"
                        <?php if ((string)$assigned_to === (string)$user['id']) echo ' selected'; ?>>
                        <?php echo he($user['username'] ?? 'Unbekannt'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <fieldset>
            <legend>Verknüpfte ISMS-Elemente (Optional)</legend>

            <div class="form-group checkbox-list-container">
                <p><small>Wählen Sie Assets aus, die mit dieser Aufgabe verknüpft sind.</small></p>
                <?php if (empty($all_assets_for_checkboxes)): ?>
                <p>Keine Assets zur Auswahl vorhanden.</p>
                <?php else: ?>
                <?php foreach ($all_assets_for_checkboxes as $asset_item): ?>
                <label class="checkbox-item">
                    <input type="checkbox" name="asset_ids[]" value="<?php echo he($asset_item['id']); ?>"
                        <?php echo in_array($asset_item['id'], $selected_asset_ids) ? 'checked' : ''; ?>>
                    <?php echo he($asset_item['name'] ?? 'Unbenannt'); ?> (ID: <?php echo he($asset_item['id']); ?>)
                </label>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="form-group checkbox-list-container">
                <p><small>Wählen Sie Risiken aus, die mit dieser Aufgabe verknüpft sind.</small></p>
                <?php if (empty($all_risks_for_checkboxes)): ?>
                <p>Keine Risiken zur Auswahl vorhanden.</p>
                <?php else: ?>
                <?php foreach ($all_risks_for_checkboxes as $risk_item): ?>
                <label class="checkbox-item">
                    <input type="checkbox" name="risk_ids[]" value="<?php echo he($risk_item['id']); ?>"
                        <?php echo in_array($risk_item['id'], $selected_risk_ids) ? 'checked' : ''; ?>>
                    <?php echo he($risk_item['name'] ?? 'Unbetitelt'); ?> (ID: <?php echo he($risk_item['id']); ?>)
                </label>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="form-group checkbox-list-container">
                <p><small>Wählen Sie Controls aus, die mit dieser Aufgabe verknüpft sind.</small></p>
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
                        <?php echo he($control_item['control_id_iso'] ?? 'N/A'); ?> - <?php echo he(substr($control_item['name'] ?? 'Unbenannt', 0, 60));
echo (strlen($control_item['name'] ?? '') > 60 ? '...' : ''); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                 
            </div>

            <div class="form-group checkbox-list-container">
                <p><small>Wählen Sie Dokumente aus, die mit dieser Aufgabe verknüpft sind.</small></p>
                <?php if (empty($all_documents_for_checkboxes)): ?>
                <p>Keine Dokumente zur Auswahl vorhanden.</p>
                <?php else: ?>
                <?php foreach ($all_documents_for_checkboxes as $document_item): ?>
                <label class="checkbox-item">
                    <input type="checkbox" name="document_ids[]" value="<?php echo he($document_item['id']); ?>"
                        <?php echo in_array($document_item['id'], $selected_document_ids) ? 'checked' : ''; ?>>
                    <?php echo he($document_item['title'] ?? 'Unbetitelt'); ?> (ID:
                    <?php echo he($document_item['id']); ?>)
                </label>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </fieldset>

        <div class="form-actions"> <button type="submit" class="btn">Aufgabe erstellen</button> <a href="tasks.php"
                class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>

</div>

<?php include 'footer.php'; ?>