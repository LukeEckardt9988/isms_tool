<?php
// Stelle sicher, dass header.php den Session-Start und requireLogin() macht
require_once 'functions.php';

requireLogin(); // Stellt sicher, dass der Benutzer eingeloggt ist

$current_user_id = getCurrentUserId();
$current_user_role = getCurrentUserRole();

// Task ID aus der URL holen
$task_id = $_GET['id'] ?? null;

if (!$task_id) {
    // Keine Task ID angegeben
    set_flash_message('error', 'Keine Aufgaben-ID zum Bearbeiten angegeben.');
    header('Location: tasks.php');
    exit();
}

$task = get_task_by_id($task_id);

if (!$task) {
    // Aufgabe nicht gefunden
    set_flash_message('error', 'Aufgabe mit dieser ID nicht gefunden.');
    header('Location: tasks.php');
    exit();
}

// Echte Berechtigungsprüfung: Darf der aktuelle Benutzer diese spezifische Task bearbeiten?
// (Ersteller ODER aktueller Bearbeiter ODER Admin/Manager mit 'manage_tasks' Permission)
$is_authorized = ($task['created_by'] == $current_user_id ||
                   $task['current_handler'] == $current_user_id ||
                   hasPermission($current_user_role, 'manage_tasks')
                  );


if (!$is_authorized) {
    set_flash_message('error', 'Sie haben keine Berechtigung, diese Aufgabe zu bearbeiten.');
    header('Location: task_view.php?id=' . he($task['id'])); // Weiterleitung zur Detailansicht
    exit();
}


// Nachrichten aus der Session anzeigen und löschen
// Bereits in header.php via display_flash_messages() gemacht


// Daten für Dropdowns/Multiselects holen (Deine implementierten Funktionen)
$users = get_users();
$assets = get_assets();
$risks = get_risks();
$controls = get_controls();
$documents = get_documents();

// Vorhandene verknüpfte IDs extrahieren für die Vorauswahl in den Multiselects
$linked_asset_ids = [];
$linked_risk_ids = [];
$linked_control_ids = [];
$linked_document_ids = [];

if (isset($task['linked_items']) && is_array($task['linked_items'])) {
    foreach($task['linked_items'] as $item) {
        switch($item['item_type']) {
            case 'asset': $linked_asset_ids[] = $item['item_id']; break;
            case 'risk': $linked_risk_ids[] = $item['item_id']; break;
            case 'control': $linked_control_ids[] = $item['item_id']; break;
            case 'document': $linked_document_ids[] = $item['item_id']; break;
        }
    }
}


include 'header.php'; // Dein Header (enthält Session-Start, requireLogin, display_flash_messages)
?>

<div class="container mt-4">
    <h2>Aufgabe bearbeiten: <?php echo he($task['title']); ?></h2>

     <?php display_flash_messages(); // Zeigt Nachrichten an ?>


    <form action="task_process.php" method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="task_id" value="<?php echo he($task['id']); ?>">

        <div class="form-group mb-3">
            <label for="title" class="form-label">Titel der Aufgabe:</label>
            <input type="text" class="form-control" id="title" name="title" value="<?php echo he($task['title']); ?>" required>
        </div>

        <div class="form-group mb-3">
            <label for="description" class="form-label">Beschreibung:</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?php echo he($task['description']); ?></textarea>
        </div>

         <div class="form-group mb-3">
            <label for="due_date" class="form-label">Fällig bis:</label>
            <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo he($task['due_date']); ?>">
        </div>

        <div class="form-group mb-3">
            <label for="priority" class="form-label">Priorität:</label>
            <select class="form-control" id="priority" name="priority">
                <option value="low" <?php echo $task['priority'] == 'low' ? 'selected' : ''; ?>>Niedrig</option>
                <option value="medium" <?php echo $task['priority'] == 'medium' ? 'selected' : ''; ?>>Mittel</option>
                <option value="high" <?php echo $task['priority'] == 'high' ? 'selected' : ''; ?>>Hoch</option>
            </select>
        </div>

         <div class="form-group mb-3">
            <label for="status" class="form-label">Status:</label>
            <select class="form-control" id="status" name="status">
                 <option value="open" <?php echo $task['status'] == 'open' ? 'selected' : ''; ?>>Offen</option>
                 <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Bearbeitung</option>
                 <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Abgeschlossen</option>
                 <option value="cancelled" <?php echo $task['status'] == 'cancelled' ? 'selected' : ''; ?>>Abgebrochen</option>
            </select>
        </div>


         <div class="form-group mb-3">
            <label for="assigned_to" class="form-label">Ursprünglich zugewiesen an:</label>
            <select class="form-control" id="assigned_to" name="assigned_to">
                <option value="">Bitte auswählen</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo he($user['id']); ?>" <?php echo $task['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo he($user['username'] ?? 'Unbekannt'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <hr class="my-4">
        <h4>Verknüpfte ISMS-Elemente (Optional)</h4>
         <p>Halten Sie Strg/Cmd gedrückt, um mehrere Elemente auszuwählen.</p>

        <div class="form-group mb-3">
            <label for="linked_assets" class="form-label">Verknüpfte Assets:</label>
            <select multiple class="form-control" id="linked_assets" name="linked_items[asset][]" size="7"> <?php foreach ($assets as $asset): ?>
                    <option value="<?php echo he($asset['id']); ?>" <?php echo in_array($asset['id'], $linked_asset_ids) ? 'selected' : ''; ?>>
                        <?php echo he($asset['name'] ?? 'Unbenannt'); ?> (ID: <?php echo he($asset['id']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label for="linked_risks" class="form-label">Verknüpfte Risiken:</label>
            <select multiple class="form-control" id="linked_risks" name="linked_items[risk][]" size="7"> <?php foreach ($risks as $risk): ?>
                    <option value="<?php echo he($risk['id']); ?>" <?php echo in_array($risk['id'], $linked_risk_ids) ? 'selected' : ''; ?>>
                        <?php echo he($risk['title'] ?? 'Unbetitelt'); ?> (ID: <?php echo he($risk['id']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

         <div class="form-group mb-3">
            <label for="linked_controls" class="form-label">Verknüpfte Controls:</label>
            <select multiple class="form-control" id="linked_controls" name="linked_items[control][]" size="7"> <?php foreach ($controls as $control): ?>
                    <option value="<?php echo he($control['id']); ?>" <?php echo in_array($control['id'], $linked_control_ids) ? 'selected' : ''; ?>>
                        <?php echo he($control['name'] ?? 'Unbenannt'); ?> (ID: <?php echo he($control['id']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

         <div class="form-group mb-3">
            <label for="linked_documents" class="form-label">Verknüpfte Dokumente:</label>
            <select multiple class="form-control" id="linked_documents" name="linked_items[document][]" size="7"> <?php foreach ($documents as $document): ?>
                    <option value="<?php echo he($document['id']); ?>" <?php echo in_array($document['id'], $linked_document_ids) ? 'selected' : ''; ?>>
                        <?php echo he($document['title'] ?? 'Unbetitelt'); ?> (ID: <?php echo he($document['id']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>


        <button type="submit" class="btn btn-primary mt-3">Aufgabe speichern</button>
         <a href="task_view.php?id=<?php echo he($task['id']); ?>" class="btn btn-secondary mt-3">Abbrechen</a>
    </form>

</div>

<?php include 'footer.php'; // Dein Footer ?>