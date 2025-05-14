<?php
// Stelle sicher, dass functions.php eingebunden ist und getPDO() verfügbar macht
require_once 'functions.php';

// requireLogin(); // Login-Check sollte im header.php oder am Anfang jeder geschützten Seite passieren

// Prüfen, ob der Benutzer eingeloggt ist
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$current_user_id = getCurrentUserId();
$current_user_role = getCurrentUserRole();

// Aktion bestimmen
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$task_id = $_POST['task_id'] ?? $_GET['id'] ?? null;

// Standard-Weiterleitung bei Erfolg oder nach Liste-Aktionen
$redirect_url = 'tasks.php';
// Bei Task-spezifischen Aktionen versuchen, zur Detailseite zurückzuleiten
if ($task_id) {
    $existing_task = get_task_by_id($task_id); // Prüfen, ob die Task existiert
    if ($existing_task) {
         $redirect_url = 'task_view.php?id=' . urlencode($task_id);
    } else {
         $redirect_url = 'tasks.php';
         if ($action !== 'delete') {
               set_flash_message('error', 'Die angeforderte Aufgabe wurde nicht gefunden.');
         }
    }
}


switch ($action) {
    case 'add':
        // Berechtigungsprüfung: Darf der Benutzer Aufgaben erstellen?
        if (!hasPermission($current_user_role, 'create') && !hasPermission($current_user_role, 'manage_tasks')) {
            set_flash_message('error', 'Sie haben keine Berechtigung, Aufgaben zu erstellen.');
            header('Location: tasks.php');
            exit();
        }

        // Formular für neue Aufgabe wurde gesendet
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'due_date' => trim($_POST['due_date'] ?? ''),
            'priority' => trim($_POST['priority'] ?? 'medium'),
            'assigned_to' => $_POST['assigned_to'] ?? null,
            'created_by' => $current_user_id,
            // NEU: Checkbox-Arrays direkt aus POST holen
            'asset_ids' => $_POST['asset_ids'] ?? [],
            'risk_ids' => $_POST['risk_ids'] ?? [],
            'control_ids' => $_POST['control_ids'] ?? [],
            'document_ids' => $_POST['document_ids'] ?? [],
        ];

        // Einfache Validierung
        if (empty($data['title'])) {
            set_flash_message('error', 'Titel der Aufgabe darf nicht leer sein.');
            header('Location: task_add.php');
            exit();
        }

        $new_task_id = create_task($data); // create_task muss diese Datenstruktur verarbeiten
        if ($new_task_id) {
            set_flash_message('success', 'Aufgabe erfolgreich erstellt!');
            $redirect_url = 'task_view.php?id=' . urlencode($new_task_id);
        } else {
            set_flash_message('error', 'Fehler beim Erstellen der Aufgabe.');
             $redirect_url = 'task_add.php';
        }
        break;

    case 'edit':
        if (!$task_id) {
             set_flash_message('error', 'Keine Aufgaben-ID zum Bearbeiten angegeben.');
             $redirect_url = 'tasks.php';
             break;
        }

        $task = get_task_by_id($task_id);
        if (!$task) {
             set_flash_message('error', 'Aufgabe nicht gefunden.');
             $redirect_url = 'tasks.php';
             break;
        }

        // Echte Berechtigungsprüfung
        $is_authorized = ($task['created_by'] == $current_user_id ||
                           $task['current_handler'] == $current_user_id ||
                           hasPermission($current_user_role, 'manage_tasks')
                          );

        if (!$is_authorized) {
             set_flash_message('error', 'Sie haben keine Berechtigung, diese Aufgabe zu bearbeiten.');
             $redirect_url = 'task_view.php?id=' . urlencode($task_id);
             break;
        }

        // Daten für Update holen (alle Felder aus dem Formular)
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'due_date' => trim($_POST['due_date'] ?? ''),
            'priority' => trim($_POST['priority'] ?? 'medium'),
            'status' => trim($_POST['status'] ?? $task['status']),
            'assigned_to' => $_POST['assigned_to'] ?? $task['assigned_to'],
            // NEU: Checkbox-Arrays direkt aus POST holen
            'asset_ids' => $_POST['asset_ids'] ?? [],
            'risk_ids' => $_POST['risk_ids'] ?? [],
            'control_ids' => $_POST['control_ids'] ?? [],
            'document_ids' => $_POST['document_ids'] ?? [],
        ];

        // Einfache Validierung
        if (empty($data['title'])) {
            set_flash_message('error', 'Titel der Aufgabe darf nicht leer sein.');
            $redirect_url = 'task_edit.php?id=' . urlencode($task_id);
            break;
        }

        if (update_task($task_id, $data)) { // update_task muss diese Datenstruktur verarbeiten
            set_flash_message('success', 'Aufgabe erfolgreich aktualisiert!');
            $redirect_url = 'task_view.php?id=' . urlencode($task_id);
        } else {
            set_flash_message('error', 'Fehler beim Aktualisieren der Aufgabe.');
            $redirect_url = 'task_edit.php?id=' . urlencode($task_id);
        }
        break;

    case 'delete':
        if (!$task_id) {
             set_flash_message('error', 'Keine Aufgaben-ID zum Löschen angegeben.');
             $redirect_url = 'tasks.php';
             break;
        }

         $task = get_task_by_id($task_id);
         if (!$task) {
             set_flash_message('error', 'Aufgabe nicht gefunden.');
             $redirect_url = 'tasks.php';
             break;
         }

         // Echte Berechtigungsprüfung
         $is_authorized = (hasPermission($current_user_role, 'manage_tasks') || $task['created_by'] == $current_user_id);

         if (!$is_authorized) {
              set_flash_message('error', 'Sie haben keine Berechtigung, diese Aufgabe zu löschen.');
              $redirect_url = 'task_view.php?id=' . urlencode($task_id);
              break;
         }


        if (delete_task($task_id)) {
            set_flash_message('success', 'Aufgabe erfolgreich gelöscht.');
            $redirect_url = 'tasks.php';
        } else {
            set_flash_message('error', 'Fehler beim Löschen der Aufgabe.');
        }
        break;


    case 'claim':
        if (!$task_id) {
            set_flash_message('error', 'Keine Aufgaben-ID zum Übernehmen angegeben.');
            $redirect_url = 'tasks.php';
            break;
        }

         $task = get_task_by_id($task_id);
         if (!$task) {
             set_flash_message('error', 'Aufgabe nicht gefunden.');
             $redirect_url = 'tasks.php';
             break;
         }

        // Berechtigungsprüfung: Darf der Benutzer Aufgaben überhaupt "bearbeiten" oder "verwalten"?
         if (!hasPermission($current_user_role, 'edit') && !hasPermission($current_user_role, 'manage_tasks')) {
             set_flash_message('error', 'Sie haben keine Berechtigung, Aufgaben zu bearbeiten oder zu übernehmen.');
             $redirect_url = 'tasks.php';
             break;
         }

        if (claim_task($task_id, $current_user_id)) {
            set_flash_message('success', 'Aufgabe erfolgreich übernommen!');
            $redirect_url = 'task_view.php?id=' . urlencode($task_id);
        } else {
            set_flash_message('warning', 'Aufgabe konnte nicht übernommen werden (möglicherweise schon in Bearbeitung oder abgeschlossen).');
             // Die Weiterleitung hier bleibt wie Standard oder auf die Seite, von der die Aktion kam
        }
        break;

    case 'reassign':
        if (!$task_id || !isset($_POST['new_handler_id'])) {
            set_flash_message('error', 'Unzureichende Daten zum Übergeben der Aufgabe.');
             $redirect_url = 'tasks.php';
            if ($task_id) $redirect_url = 'task_view.php?id=' . urlencode($task_id);
            break;
        }
         $new_handler_id = $_POST['new_handler_id'];

         $task = get_task_by_id($task_id);
         if (!$task) {
             set_flash_message('error', 'Aufgabe nicht gefunden.');
             $redirect_url = 'tasks.php';
             break;
         }

         // Echte Berechtigungsprüfung
         $is_authorized = ($task['current_handler'] == $current_user_id ||
                           hasPermission($current_user_role, 'manage_tasks')
                          );

         if (!$is_authorized) {
              set_flash_message('error', 'Sie haben keine Berechtigung, diese Aufgabe zu übergeben.');
              $redirect_url = 'task_view.php?id=' . urlencode($task_id);
              break;
         }

        $new_handler_id_val = ($new_handler_id === '0' || $new_handler_id === '' || $new_handler_id === null) ? null : (int)$new_handler_id;


        if (reassign_task($task_id, $new_handler_id_val, $current_user_id)) {
            set_flash_message('success', 'Aufgabe erfolgreich übergeben!');
        } else {
            set_flash_message('error', 'Fehler beim Übergeben der Aufgabe.');
        }
         $redirect_url = 'task_view.php?id=' . urlencode($task_id);
        break;

    case 'complete':
         if (!$task_id) {
            set_flash_message('error', 'Keine Aufgaben-ID zum Abschließen angegeben.');
            $redirect_url = 'tasks.php';
             break;
        }

         $task = get_task_by_id($task_id);
         if (!$task) {
             set_flash_message('error', 'Aufgabe nicht gefunden.');
             $redirect_url = 'tasks.php';
             break;
         }

         // Echte Berechtigungsprüfung
         $is_authorized = ($task['current_handler'] == $current_user_id ||
                           hasPermission($current_user_role, 'manage_tasks')
                          );

         if (!$is_authorized) {
              set_flash_message('error', 'Sie haben keine Berechtigung, diese Aufgabe abzuschließen.');
              $redirect_url = 'task_view.php?id=' . urlencode($task_id);
              break;
         }

        if (complete_task($task_id, $current_user_id)) {
            set_flash_message('success', 'Aufgabe erfolgreich abgeschlossen!');
        } else {
            set_flash_message('error', 'Fehler beim Abschließen der Aufgabe.');
        }
         $redirect_url = 'task_view.php?id=' . urlencode($task_id);
        break;

     case 'cancel':
         if (!$task_id) {
            set_flash_message('error', 'Keine Aufgaben-ID zum Abbrechen angegeben.');
            $redirect_url = 'tasks.php';
             break;
        }

         $task = get_task_by_id($task_id);
         if (!$task) {
             set_flash_message('error', 'Aufgabe nicht gefunden.');
             $redirect_url = 'tasks.php';
             break;
         }

         // Echte Berechtigungsprüfung
         $is_authorized = (hasPermission($current_user_role, 'manage_tasks') || $task['created_by'] == $current_user_id);

         if (!$is_authorized) {
              set_flash_message('error', 'Sie haben keine Berechtigung, diese Aufgabe abzubrechen.');
              $redirect_url = 'task_view.php?id=' . urlencode($task_id);
              break;
         }

        if (cancel_task($task_id, $current_user_id)) {
            set_flash_message('success', 'Aufgabe erfolgreich abgebrochen!');
        } else {
            set_flash_message('error', 'Fehler beim Abbrechen der Aufgabe.');
        }
         $redirect_url = 'task_view.php?id=' . urlencode($task_id);
        break;


    default:
        set_flash_message('error', 'Unbekannte Aktion angefordert.');
        $redirect_url = 'tasks.php';
        break;
}

header('Location: ' . $redirect_url);
exit();
?>