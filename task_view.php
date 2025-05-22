<?php
require_once 'functions.php';

requireLogin(); // Stellt sicher, dass der Benutzer eingeloggt ist

$current_user_id = getCurrentUserId();
$current_user_role = getCurrentUserRole();

// Task ID aus der URL holen
$task_id = $_GET['id'] ?? null;

if (!$task_id) {
    set_flash_message('error', 'Keine Aufgaben-ID angegeben.');
    header('Location: tasks.php');
    exit();
}

$task = get_task_by_id($task_id); // Holt Task-Details, Linked Items und History (aus audit_trails)
$users = get_users(); // Brauchen wir für das Übergeben-Dropdown

if (!$task) {
    set_flash_message('error', 'Aufgabe mit dieser ID nicht gefunden.');
    header('Location: tasks.php');
    exit();
}

// Berechtigungen für Aktionen prüfen (basierend auf Rolle UND Task-Zuweisung)
$can_edit = ($task['created_by'] == $current_user_id ||
              $task['current_handler'] == $current_user_id ||
              hasPermission($current_user_role, 'manage_tasks'));

$can_claim = ($task['status'] == 'open' && (empty($task['current_handler']) || $task['current_handler'] == 0) && (hasPermission($current_user_role, 'edit') || hasPermission($current_user_role, 'manage_tasks')) );

$can_reassign_complete = ($task['current_handler'] == $current_user_id ||
                           hasPermission($current_user_role, 'manage_tasks'));

$can_cancel = (hasPermission($current_user_role, 'manage_tasks') || $task['created_by'] == $current_user_id);

$can_delete = hasPermission($current_user_role, 'manage_tasks');


include 'header.php'; // Dein Header
?>

<div class="container mt-4">
    <h2>Aufgabe: <?php echo he($task['title']); ?></h2>

    <?php display_flash_messages(); // Zeigt Nachrichten an ?>

    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title"><?php echo he($task['title']); ?></h5>
            <p class="card-text"><strong>Beschreibung:</strong><br><?php echo nl2br(he($task['description'])); ?></p>
            <p class="card-text"><strong>Status:</strong> <?php echo he($task['status']); ?></p>
            <p class="card-text"><strong>Priorität:</strong> <?php echo he($task['priority']); ?></p>
            <p class="card-text"><strong>Fällig bis:</strong> <?php echo he($task['due_date'] ?? 'N/A'); ?></p>
            <p class="card-text"><strong>Erstellt am:</strong> <?php echo he($task['created_at']); ?></p>
            <p class="card-text"><strong>Erstellt von:</strong> <?php echo he($task['created_by_username'] ?? 'Unbekannt'); ?></p>
            <p class="card-text"><strong>Ursprünglich zugewiesen an:</strong> <?php echo he($task['assigned_to_username'] ?? 'Nicht zugewiesen'); ?></p>
            <p class="card-text"><strong>Aktueller Bearbeiter:</strong> <?php echo he($task['current_handler_username'] ?? 'Niemand'); ?></p>
             <?php if ($task['status'] == 'completed' && $task['completed_at']): ?>
                <p class="card-text"><strong>Abgeschlossen am:</strong> <?php echo he($task['completed_at']); ?></p>
            <?php endif; ?>
            <?php if ($task['last_updated_at']): ?>
                 <p class="card-text"><strong>Zuletzt aktualisiert:</strong> <?php echo he($task['last_updated_at']); ?></p>
            <?php endif; ?>


            <div class="mt-3">
                 <?php if ($can_edit): ?>
                    <a href="task_edit.php?id=<?php echo he($task['id']); ?>" class="btn btn-sm btn-warning me-2">Bearbeiten</a>
                <?php endif; ?>

                <?php
                 if ($can_claim && $task['status'] == 'open' && (empty($task['current_handler']) || $task['current_handler'] == 0) ):
                ?>
                     <form action="task_process.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="claim">
                        <input type="hidden" name="task_id" value="<?php echo he($task['id']); ?>">
                        <button type="submit" class="btn btn-sm btn-success me-2" onclick="return confirm('Möchten Sie diese Aufgabe übernehmen?');">Übernehmen</button>
                    </form>
                <?php endif; ?>

                <?php
                if ($can_reassign_complete && $task['status'] != 'completed' && $task['status'] != 'cancelled'):
                ?>

                     <form action="task_process.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="task_id" value="<?php echo he($task['id']); ?>">
                        <button type="submit" class="btn btn-sm btn-primary me-2" onclick="return confirm('Möchten Sie diese Aufgabe als abgeschlossen markieren?');">Abschließen</button>
                    </form>
                <?php endif; ?>

                 <?php
                 if ($can_cancel && $task['status'] != 'completed' && $task['status'] != 'cancelled'):
                 ?>
                     <form action="task_process.php" method="post" style="display:inline;">
                         <input type="hidden" name="action" value="cancel">
                         <input type="hidden" name="task_id" value="<?php echo he($task['id']); ?>">
                         <button type="submit" class="btn btn-sm btn-warning me-2" onclick="return confirm('Möchten Sie diese Aufgabe wirklich abbrechen?');">Abbrechen</button>
                     </form>
                 <?php endif; ?>


                 <?php if ($can_delete): ?>
                     <form action="task_process.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="task_id" value="<?php echo he($task['id']); ?>">
                        <button type="submit" class="btn btn-sm btn-danger me-2" onclick="return confirm('Sind Sie sicher, dass Sie diese Aufgabe löschen möchten?');">Löschen</button>
                    </form>
                <?php endif; ?>

                <a href="tasks.php" class="btn btn-sm btn-secondary">Zurück zur Liste</a>

            </div>
        </div>
    </div>

    <div class="modal fade" id="reassignModal" tabindex="-1" aria-labelledby="reassignModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="reassignModalLabel">Aufgabe übergeben</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="task_process.php" method="post">
              <input type="hidden" name="action" value="reassign">
              <input type="hidden" name="task_id" value="<?php echo he($task['id']); ?>">
              <div class="modal-body">
                  <div class="form-group mb-3">
                      <label for="new_handler_id" class="form-label">Neue/r Bearbeiter/in:</label>
                      <select class="form-control" id="new_handler_id" name="new_handler_id" required>
                          <option value="">Bitte auswählen</option>
                           <?php foreach ($users as $user): ?>
                            <option value="<?php echo he($user['id']); ?>" <?php echo ((string)($task['current_handler'] ?? '') === (string)$user['id']) ? 'selected' : ''; ?>>
                                <?php echo he($user['username'] ?? 'Unbekannt'); ?>
                            </option>
                          <?php endforeach; ?>
                           <option value="0">Niemandem zuweisen</option>
                      </select>
                  </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                <button type="submit" class="btn btn-primary">Übergeben</button>
              </div>
          </form>
        </div>
      </div>
    </div>


    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Verknüpfte ISMS-Elemente</h5>
            <?php if (empty($task['linked_items'])): ?>
                <p>Keine Elemente verknüpft.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($task['linked_items'] as $item): ?>
                        <li>
                            <?php echo he(ucfirst($item['item_type'])); ?>:
                            <?php
                                // Helferfunktionen verwenden, um Namen zu holen und Links zu erstellen
                                $item_name = 'ID ' . he($item['item_id']); // Fallback-Name
                                $item_link = '#'; // Fallback Link
                                $item_id = he($item['item_id']);

                                switch ($item['item_type']) {
                                    case 'asset':
                                         // Stelle sicher, dass get_asset_name_by_id existiert und korrekte Spalte holt
                                         $item_name = get_asset_name_by_id($item['item_id']);
                                         $item_link = 'asset_view.php?id=' . $item_id;
                                        break;
                                    case 'risk':
                                         // Stelle sicher, dass get_risk_name_by_id existiert und korrekte Spalte holt (name statt title)
                                         $item_name = get_risk_name_by_id($item['item_id']);
                                         $item_link = 'risk_view.php?id=' . $item_id;
                                        break;
                                     case 'control':
                                         // Stelle sicher, dass get_control_name_by_id existiert und korrekte Spalte holt
                                         $item_name = get_control_name_by_id($item['item_id']);
                                         $item_link = 'control_view.php?id=' . $item_id;
                                        break;
                                    case 'document':
                                         // Stelle sicher, dass get_document_title_by_id existiert und korrekte Spalte holt (vermutlich title)
                                         $item_name = get_document_title_by_id($item['item_id']);
                                         $item_link = 'document_view.php?id=' . $item_id;
                                        break;
                                    default:
                                         $item_name = 'Unbekannter Elementtyp ID ' . $item_id;
                                         break;
                                }
                            ?>
                             <a href="<?php echo he($item_link); ?>"><?php echo he($item_name); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

     <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Checkliste / Unteraufgaben (aus Beschreibung)</h5>
            <?php
            $checklist_content = trim($task['description']);
            $checklist_items = explode("\n", $checklist_content);
            $checklist_items = array_filter($checklist_items, 'trim');

            if (!empty($checklist_items)):
            ?>
                <p>Liste abgeleitet aus der Beschreibung:</p>
                <ul>
                    <?php foreach ($checklist_items as $item): ?>
                        <li>
                             <input type="checkbox" disabled>
                            <?php echo he(trim($item)); ?>
                        </li>
                    <?php endforeach; ?>
                     <li><em>(Basierend auf Beschreibung; Fortschritt wird hier nicht gespeichert)</em></li>
                </ul>
            <?php else: ?>
                <p>Keine spezifische Checkliste in der Beschreibung hinterlegt.</p>
            <?php endif; ?>
        </div>
    </div>


     <?php if (!empty($task['history'])): ?>
         <div class="card mb-3">
             <div class="card-body">
                 <h5 class="card-title">Aufgaben-Historie</h5>
                 <ul class="list-group list-group-flush">
                     <?php foreach ($task['history'] as $history_entry): ?>
                         <li class="list-group-item">
                             <strong><?php echo he($history_entry['timestamp']); ?>:</strong>
                             Benutzer "<?php echo he($history_entry['username'] ?? 'System'); ?>"
                             hat Aktion "<?php echo he($history_entry['action']); ?>"
                             mit Entität "<?php echo he($history_entry['entity_type']); ?>" (ID: <?php echo he($history_entry['entity_id']); ?>) durchgeführt.
                             <?php
                             $details = $history_entry['details'];
                             $decoded_details = json_decode($details, true);
                             if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_details)):
                             ?>
                                 (Details: <?php echo he(json_encode($decoded_details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>)
                             <?php elseif (!empty($details)): ?>
                                 (Details: <?php echo he($details); ?>)
                             <?php endif; ?>
                         </li>
                     <?php endforeach; ?>
                 </ul>
             </div>
         </div>
     <?php endif; ?>


</div>

<?php include 'footer.php'; ?>