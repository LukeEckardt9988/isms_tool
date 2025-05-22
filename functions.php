<?php
// Stelle sicher, dass die Session gestartet ist und db_config.php eingebunden ist.
// Das sollte bereits in deinem header.php oder am Anfang jeder Seite passieren.
session_start(); // Sollte nur einmal ganz am Anfang der Session gestartet werden
 require_once 'db_config.php'; 



function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole()
{
    return $_SESSION['user_role'] ?? null;
}

// Simples HTML Escaping
function he($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Nutzt PDO, wie in deiner Vorlage
function log_audit_trail($action, $entity_type = null, $entity_id = null, $details = null)
{
    // Kein Login-Check hier, da requireLogin oder andere Checks dies bereits tun sollten,
    // bevor log_audit_trail aufgerufen wird für benutzerbezogene Aktionen.
    // Allerdings wollen wir vielleicht auch Systemaktionen loggen, daher user_id NULL erlauben.
    // Der ursprüngliche Check `if (!isLoggedIn()) return;` war ok, wenn nur eingeloggte User geloggt werden sollen.
    // Belassen wir es vorerst, um das Verhalten nicht zu ändern.
     if (!isLoggedIn() && $entity_type !== 'system') return; // Logge nur eingeloggte oder Systemaktionen

    $pdo = getPDO();
    $sql = "INSERT INTO audit_trails (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            getCurrentUserId(), // Nutze deine Funktion
            $action,
            $entity_type,
            $entity_id,
            is_array($details) ? json_encode($details) : $details,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
        ]);
    } catch (PDOException $e) {
        // Fehler beim Loggen behandeln (z.B. in eine Datei schreiben)
        error_log("Audit Trail Error: " . $e->getMessage());
        // In einer Produktionsumgebung möchtest du vielleicht nicht die DB-Fehlermeldung anzeigen
    }
}



#################################################################################################################################
#################################################################################################################################
##################### VORHANDENE FUNKTIONEN #####################################################################################

/**
 * Berechnet das Risikolevel basierend auf Eintrittswahrscheinlichkeit und Auswirkung.
 * @param string $likelihood Die ausgewählte Eintrittswahrscheinlichkeit (z.B. 'mittel').
 * @param string $impact Die ausgewählte Auswirkung (z.B. 'hoch').
 * @return string Das berechnete Risikolevel (z.B. 'Niedrig', 'Mittel', 'Hoch', 'Kritisch').
 */
// In functions.php
function calculateRiskLevel($likelihood, $impact) {
    $likelihood_values = [
        'sehr gering' => 1, 'gering' => 2, 'mittel' => 3, 'hoch' => 4, 'sehr hoch' => 5
    ];
    $impact_values = [
        'sehr gering' => 1, 'gering' => 2, 'mittel' => 3, 'hoch' => 4, 'sehr hoch' => 5
    ];

    $numeric_likelihood = $likelihood_values[strtolower($likelihood ?? '')] ?? 1;
    $numeric_impact = $impact_values[strtolower($impact ?? '')] ?? 1;

    $risk_score = $numeric_likelihood * $numeric_impact;

    if ($risk_score <= 4) return 'Niedrig';
    if ($risk_score <= 9) return 'Mittel';
    if ($risk_score <= 16) return 'Hoch';
    return 'Kritisch';
}


#################################################################################################################################
#################################################################################################################################
##################### FLASH MESSAGES ############################################################################################

function display_flash_messages() {
    // Nutzt he() und die von dir verwendeten Session-Variablen
    // Ändere die Ausgabe von Bootstrap alert divs zu deinen p Tags mit error/success Klassen

    if (isset($_SESSION['flash_success'])) {
        // Geändertes HTML und Klasse
        echo '<p class="success">' . he($_SESSION['flash_success']) . '</p>';
        unset($_SESSION['flash_success']);
    }
    if (isset($_SESSION['flash_error'])) {
        // Geändertes HTML und Klasse
        echo '<p class="error">' . he($_SESSION['flash_error']) . '</p>';
        unset($_SESSION['flash_error']);
    }
     // Wenn du flash_info oder flash_warning nutzt, musst du hier auch entsprechende divs/p mit Klassen definieren
     if (isset($_SESSION['flash_info'])) {
        echo '<p class="info">' . he($_SESSION['flash_info']) . '</p>'; // Füge .info Style in CSS hinzu
        unset($_SESSION['flash_info']);
    }
     if (isset($_SESSION['flash_warning'])) {
        echo '<p class="warning">' . he($_SESSION['flash_warning']) . '</p>'; // Füge .warning Style in CSS hinzu
        unset($_SESSION['flash_warning']);
    }
}

// Helper, um Flash Messages zu setzen
function set_flash_message($type, $message) {
    if (in_array($type, ['success', 'error', 'info', 'warning'])) {
        $_SESSION['flash_' . $type] = $message;
    } else {
        // Standardmäßig als error, wenn Typ unbekannt
         $_SESSION['flash_error'] = $message;
    }
}


#################################################################################################################################
#################################################################################################################################
##################### BERECHTIGUNGEN ############################################################################################

// Nutzt deine vorhandene Logik
function hasPermission($role, $action) {
    $permissions = [
        'admin' => ['view', 'create', 'edit', 'delete', 'manage_users', 'manage_tasks'], // 'manage_tasks' hinzugefügt
        'manager' => ['view', 'create', 'edit', 'delete', 'manage_tasks'], // 'manage_tasks' hinzugefügt
        'editor' => ['view', 'create', 'edit'], // Beispiel: Wenn es eine 'editor' Rolle gäbe
        'viewer' => ['view']
    ];

    if (!isset($permissions[$role])) {
        return false; // Unbekannte Rolle
    }

    return in_array($action, $permissions[$role]);
}

// Helper-Funktion zur Überprüfung, ob der aktuelle Benutzer Admin ist
function is_admin($user_id = null) {
    if ($user_id === null) {
        $user_id = getCurrentUserId();
    }
    if ($user_id === null) {
        return false; // Kein Benutzer eingeloggt
    }
    // Hol die Rolle des Benutzers aus der DB, falls nicht in Session (besser in Session speichern nach Login)
    $pdo = getPDO();
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        return $user && $user['role'] === 'admin';
    } catch (PDOException $e) {
        error_log("Database error checking admin status: " . $e->getMessage());
        return false;
    }
}


#################################################################################################################################
#################################################################################################################################
##################### HELPER FÜR ISMS ELEMENTE (ANPASSEN!) ######################################################################

// --- HELPER-FUNKTIONEN ZUM HOLEN VON ISMS-ELEMENTEN FÜR DROPDOWNS/MULTISELECTS ---
// MUSST DU ANPASSEN, UM DEINE TABELLENSTRUKTUR UND FELDNAMEN ZU NUTZEN!
// Diese Funktionen müssen ID und einen Anzeigenamen (z.B. Name, Title, etc.) zurückgeben

function get_assets() {
    $pdo = getPDO();
    try {
        // PASSE DIE SPALTENNAMEN 'id' und 'name' AN DEINE ASSETS-TABELLE AN!
        $stmt = $pdo->query("SELECT id, name FROM assets ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching assets: " . $e->getMessage());
        return [];
    }
}

function get_risks() {
    $pdo = getPDO();
    try {
        // PASSE DIE SPALTENNAMEN 'id' und 'title' AN DEINE RISKS-TABELLE AN!
        $stmt = $pdo->query("SELECT id, title FROM risks ORDER BY title");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching risks: " . $e->getMessage());
        return [];
    }
}

function get_controls() {
    $pdo = getPDO();
    try {
        // PASSE DIE SPALTENNAMEN 'id' und 'name' (oder 'control_id'/'description') AN DEINE CONTROLS-TABELLE AN!
        $stmt = $pdo->query("SELECT id, name FROM controls ORDER BY name"); // Beispiel
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching controls: " . $e->getMessage());
        return [];
    }
}

function get_documents() {
    $pdo = getPDO();
    try {
        // PASSE DIE SPALTENNAMEN 'id' und 'title' AN DEINE DOCUMENTS-TABELLE AN!
        $stmt = $pdo->query("SELECT id, title FROM documents ORDER BY title");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching documents: " . $e->getMessage());
        return [];
    }
}


function get_users() {
    $pdo = getPDO(); // Nutze deine vorhandene getPDO() Funktion
    $users = [];

    // Abfrage, um alle Benutzer mit ihrer ID und ihrem Benutzernamen zu holen.
    // PASSE DIE SPALTENNAMEN 'id' und 'username' AN DEINE USERS-TABELLE AN!
    $sql = "SELECT id, username FROM users ORDER BY username";

    try {
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fehler beim Holen der Benutzer loggen
        error_log("Database error fetching users: " . $e->getMessage());
        // Im Fehlerfall eine leere Liste zurückgeben
        $users = [];
    }

    return $users;
}


// --- HELPER-FUNKTIONEN ZUM HOLEN EINZELNER ISMS-ELEMENTE FÜR LINKS AUF TASK-VIEW SEITE ---
// MUSST DU ANPASSEN!

function get_asset_name_by_id($id) {
    $pdo = getPDO();
     try {
        $stmt = $pdo->prepare("SELECT name FROM assets WHERE id = ?"); // Passe 'name' und Tabelle an
        $stmt->execute([$id]);
        $result = $stmt->fetchColumn();
        return $result ? $result : 'Unbekanntes Asset';
    } catch (PDOException $e) {
        error_log("Database error fetching asset name: " . $e->getMessage());
        return 'Fehler beim Asset-Namen';
    }
}

// Ändere den Namen der Funktion und die Spalte in der Abfrage
function get_risk_name_by_id($id) {
    $pdo = getPDO();
     try {
        // PASSE DIE SPALTE VON 'title' AUF 'name' AN!
        $stmt = $pdo->prepare("SELECT name FROM risks WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetchColumn();
        return $result ? $result : 'Unbekanntes Risiko'; // Gib den Namen zurück
    } catch (PDOException $e) {
        error_log("Database error fetching risk name: " . $e->getMessage());
        return 'Fehler beim Risiko-Namen';
    }
}

function get_control_name_by_id($id) {
    $pdo = getPDO();
     try {
        $stmt = $pdo->prepare("SELECT name FROM controls WHERE id = ?"); // Passe 'name' und Tabelle an
        $stmt->execute([$id]);
        $result = $stmt->fetchColumn();
        return $result ? $result : 'Unbekanntes Control';
    } catch (PDOException $e) {
        error_log("Database error fetching control name: " . $e->getMessage());
        return 'Fehler beim Control-Namen';
    }
}

function get_document_title_by_id($id) {
    $pdo = getPDO();
     try {
        $stmt = $pdo->prepare("SELECT title FROM documents WHERE id = ?"); // Passe 'title' und Tabelle an
        $stmt->execute([$id]);
        $result = $stmt->fetchColumn();
        return $result ? $result : 'Unbekanntes Dokument';
    } catch (PDOException $e) {
        error_log("Database error fetching document title: " . $e->getMessage());
        return 'Fehler beim Dokument-Titel';
    }
}


#################################################################################################################################
#################################################################################################################################
##################### NEUE TASK FUNKTIONEN (INTEGRIERT MIT PDO & AUDIT TRAIL) ###################################################

// Holt alle oder gefilterte Aufgaben
function get_tasks($filter = []) {
    $pdo = getPDO();
    $tasks = [];
    $where_clauses = [];
    $params = [];
    $types = ''; // PDO benötigt keine Typen-Strings wie mysqli bind_param

    $sql = "SELECT t.*, u_created.username AS created_by_username,
                   u_assigned.username AS assigned_to_username,
                   u_handler.username AS current_handler_username
            FROM tasks t
            LEFT JOIN users u_created ON t.created_by = u_created.id
            LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
            LEFT JOIN users u_handler ON t.current_handler = u_handler.id";

    // Filter-Logik mit Prepared Statements
    if (!empty($filter)) {
        // Beispiel: Filter nach Status
        if (isset($filter['status'])) {
            $where_clauses[] = "t.status = ?";
            $params[] = $filter['status'];
        }
        // Beispiel: Filter nach Bearbeiter (aktuellem Handler)
         if (isset($filter['current_handler'])) {
            $where_clauses[] = "t.current_handler = ?";
            $params[] = $filter['current_handler'];
        }
         // Beispiel: Nur Aufgaben anzeigen, bei denen der Benutzer Ersteller ODER zugewiesen ODER aktueller Bearbeiter ist (falls benötigt)
         if (isset($filter['involved_user_id'])) {
              $where_clauses[] = "(t.created_by = ? OR t.assigned_to = ? OR t.current_handler = ?)";
              $params[] = $filter['involved_user_id'];
              $params[] = $filter['involved_user_id'];
              $params[] = $filter['involved_user_id'];
         }
        // ... weitere Filter (z.B. nach Fälligkeit, Priorität, Titel-Suche) ...

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
    }

    // Standard-Sortierung
    $sql .= " ORDER BY FIELD(t.priority, 'high', 'medium', 'low'), t.due_date ASC, t.created_at DESC"; // Sortiert nach Priorität (Hoch zuerst) dann Fälligkeit

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params); // PDO execute nimmt ein Array von Parametern

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database error fetching tasks: " . $e->getMessage());
        return [];
    }
}

// Holt eine einzelne Aufgabe mit Verknüpfungen und relevanter Historie (aus audit_trails)
function get_task_by_id($task_id) {
    $pdo = getPDO();
    $task = null;

    // Aufgabe Details holen
    $sql = "SELECT t.*, u_created.username AS created_by_username,
                   u_assigned.username AS assigned_to_username,
                   u_handler.username AS current_handler_username
            FROM tasks t
            LEFT JOIN users u_created ON t.created_by = u_created.id
            LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
            LEFT JOIN users u_handler ON t.current_handler = u_handler.id
            WHERE t.id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            // Jetzt verknüpfte Elemente holen
            $items_sql = "SELECT * FROM task_items WHERE task_id = ?";
            $items_stmt = $pdo->prepare($items_sql);
            $items_stmt->execute([$task_id]);
            $task['linked_items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Relevante Historie aus audit_trails holen
            // Wir suchen Einträge, die sich auf diese spezifische Aufgabe beziehen
            $history_sql = "SELECT at.*, u.username
                            FROM audit_trails at
                            LEFT JOIN users u ON at.user_id = u.id
                            WHERE at.entity_type = 'task' AND at.entity_id = ?
                            ORDER BY at.timestamp ASC";
            $history_stmt = $pdo->prepare($history_sql);
            $history_stmt->execute([$task_id]);
            $task['history'] = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

        }

    } catch (PDOException $e) {
        error_log("Database error fetching task " . $task_id . ": " . $e->getMessage());
        return null;
    }
    return $task;
}

// Erstellt eine neue Aufgabe
function create_task($data) {
    $pdo = getPDO();
    $current_user_id = getCurrentUserId();

    $sql = "INSERT INTO tasks (title, description, priority, due_date, created_by, assigned_to, current_handler, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    try {
        $pdo->beginTransaction(); // Transaktion starten für atomares Speichern

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['priority'],
            empty($data['due_date']) ? null : $data['due_date'], // PDO speichert leere Daten als NULL
            $data['created_by'],
            empty($data['assigned_to']) ? null : $data['assigned_to'],
            empty($data['assigned_to']) ? null : $data['assigned_to'], // Am Anfang ist current_handler gleich assigned_to
             'open' // Neue Aufgaben sind immer 'open'
        ]);

        $task_id = $pdo->lastInsertId();

        // Verknüpfte Elemente speichern
        if (isset($data['linked_items']) && is_array($data['linked_items'])) {
            $item_sql = "INSERT INTO task_items (task_id, item_type, item_id) VALUES (?, ?, ?)";
            $item_stmt = $pdo->prepare($item_sql);

            foreach($data['linked_items'] as $item_type => $item_ids) {
                if (is_array($item_ids)) {
                    foreach($item_ids as $item_id) {
                        // Füge hier eine VALIDIERUNG hinzu, ob item_id für item_type existiert!
                         if (!empty($item_id)) { // Keine leeren IDs speichern
                             $item_stmt->execute([$task_id, $item_type, $item_id]);
                         }
                    }
                }
            }
        }

        // Log Aktion
        log_audit_trail('task_created', 'task', $task_id, ['title' => $data['title'], 'assigned_to' => $data['assigned_to']]);
         if (!empty($data['assigned_to'])) {
              log_audit_trail('task_assigned', 'task', $task_id, ['user_id' => $data['assigned_to'], 'assigned_by' => $current_user_id]);
         }


        $pdo->commit(); // Transaktion abschließen
        return $task_id; // Gibt die ID der neuen Aufgabe zurück

    } catch (PDOException $e) {
        $pdo->rollBack(); // Transaktion rückgängig machen bei Fehler
        error_log("Error creating task: " . $e->getMessage());
        return false;
    }
}

// Aktualisiert eine bestehende Aufgabe
function update_task($task_id, $data) {
    $pdo = getPDO();
    $current_user_id = getCurrentUserId();

     // Optional: Alte Task-Daten holen für Audit Trail Details
     $old_task = get_task_by_id($task_id);
     if (!$old_task) return false; // Task nicht gefunden

    $sql = "UPDATE tasks SET
                title = ?,
                description = ?,
                priority = ?,
                due_date = ?,
                status = ?,
                assigned_to = ?,
                last_updated_at = CURRENT_TIMESTAMP -- Spalte last_updated_at in der DB muss vorhanden sein
            WHERE id = ?";

    try {
        $pdo->beginTransaction(); // Transaktion starten

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['priority'],
            empty($data['due_date']) ? null : $data['due_date'],
            $data['status'],
            empty($data['assigned_to']) ? null : $data['assigned_to'],
            $task_id
        ]);

        // Verknüpfte Elemente aktualisieren: Einfachste Methode ist Löschen und neu einfügen
        $delete_items_sql = "DELETE FROM task_items WHERE task_id = ?";
        $delete_items_stmt = $pdo->prepare($delete_items_sql);
        $delete_items_stmt->execute([$task_id]);

        if (isset($data['linked_items']) && is_array($data['linked_items'])) {
            $item_sql = "INSERT INTO task_items (task_id, item_type, item_id) VALUES (?, ?, ?)";
            $item_stmt = $pdo->prepare($item_sql);
            foreach($data['linked_items'] as $item_type => $item_ids) {
                 if (is_array($item_ids)) {
                    foreach($item_ids as $item_id) {
                        // Füge hier eine VALIDIERUNG hinzu, ob item_id für item_type existiert!
                        if (!empty($item_id)) { // Keine leeren IDs speichern
                           $item_stmt->execute([$task_id, $item_type, $item_id]);
                        }
                    }
                 }
            }
        }

        // Log Aktion
        // Detaillierterer Log wäre hier gut, z.B. was genau geändert wurde
        $changes = array_diff_assoc($data, $old_task); // Funktioniert nicht perfekt für alle Feldtypen
        $log_details = ['changed_fields' => $changes]; // Vereinfacht


        log_audit_trail('task_edited', 'task', $task_id, $log_details);

        // Log für Statusänderung separat (könnte auch im Haupt-Edit-Log stehen, aber separat ist klarer)
        if ($old_task['status'] !== $data['status']) {
             log_audit_trail('task_status_changed', 'task', $task_id, ['old_status' => $old_task['status'], 'new_status' => $data['status']]);
        }
         // Log für assigned_to Änderung
         if ($old_task['assigned_to'] !== $data['assigned_to']) {
              log_audit_trail('task_reassigned_initial', 'task', $task_id, ['old_assigned_to' => $old_task['assigned_to'], 'new_assigned_to' => $data['assigned_to']]);
         }
         // Änderungen am current_handler werden durch claim/reassign geloggt

        $pdo->commit(); // Transaktion abschließen
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack(); // Transaktion rückgängig machen
        error_log("Error updating task " . $task_id . ": " . $e->getMessage());
        return false;
    }
}

// Löscht eine Aufgabe
function delete_task($task_id) {
    $pdo = getPDO();
    $current_user_id = getCurrentUserId();

    // Optional: Task-Daten holen für Audit Trail Details
    $task = get_task_by_id($task_id);
    if (!$task) {
         // Wenn Task nicht existiert, wurde er vielleicht schon gelöscht -> Erfolg melden?
         // Oder explizit melden, dass er nicht gefunden wurde.
         // Hier: Fehler melden, wenn er zum Zeitpunkt des Aufrufs nicht da ist.
         error_log("Attempted to delete non-existent task ID: " . $task_id);
         return false;
    }


    // Durch ON DELETE CASCADE in den FKs sollten task_items und relevante audit_trails Einträge (wenn FK gesetzt)
    // automatisch gelöscht werden. Wenn audit_trails keinen FK hat, müssen wir manuell löschen oder es im Log belassen.
    // Gehen wir davon aus, audit_trails behält die Logs, auch wenn die Entität gelöscht ist (oft gewünscht).
    // task_items löschen wir explizit oder per CASCADE.
    $sql = "DELETE FROM tasks WHERE id = ?";

    try {
         $pdo->beginTransaction(); // Transaktion starten

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$task_id]);

         // Log Aktion (Nach dem Löschen der Task-Tabelle, aber innerhalb der Transaktion)
         // Loggen BEVOR das Element gelöscht wird, falls Audit-Trail FK zur Task-Tabelle hat.
         // Wenn Audit-Trail keinen FK hat (wie in deiner Vorlage), kann es danach geloggt werden.
         // Gehen wir von deiner Vorlage aus, wo entity_id nur ein INT ist ohne FK zur tasks Tabelle.
         log_audit_trail('task_deleted', 'task', $task_id, ['title' => $task['title']]);

        $pdo->commit(); // Transaktion abschließen

         // Prüfen, ob die Löschung erfolgreich war (mehr als 0 Zeilen betroffen)
         return $stmt->rowCount() > 0;


    } catch (PDOException $e) {
        $pdo->rollBack(); // Transaktion rückgängig machen
        error_log("Error deleting task " . $task_id . ": " . $e->getMessage());
        return false;
    }
}

// Benutzer übernimmt eine Aufgabe
function claim_task($task_id, $user_id) {
    $pdo = getPDO();

    // Nur übernehmen, wenn Status 'open' und kein aktueller Bearbeiter gesetzt ist
    $sql = "UPDATE tasks SET current_handler = ?, status = 'in_progress', last_updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'open' AND (current_handler IS NULL OR current_handler = 0)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $task_id]);

        if ($stmt->rowCount() > 0) {
             log_audit_trail('task_claimed', 'task', $task_id, ['user_id' => $user_id]);
             return true;
        } else {
            // Aufgabe konnte nicht übernommen werden (z.B. schon in Bearbeitung)
             return false;
        }

    } catch (PDOException $e) {
        error_log("Error claiming task " . $task_id . " by user " . $user_id . ": " . $e->getMessage());
        return false;
    }
}

// Benutzer übergibt eine Aufgabe an einen anderen Benutzer
function reassign_task($task_id, $new_user_id, $acting_user_id) {
    $pdo = getPDO();

    // Hier findet NUR die DB-Operation statt.
    // Die Berechtigungsprüfung (darf der agierende Benutzer übergeben?) muss VORHER in task_process.php erfolgen.
    // Optional kann die Funktion prüfen, ob die Task existiert.

     $sql = "UPDATE tasks SET current_handler = ?, last_updated_at = CURRENT_TIMESTAMP
            WHERE id = ?"; // Keine Statusänderung beim Übergeben, nur Bearbeiter

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([empty($new_user_id) ? null : $new_user_id, $task_id]);

         if ($stmt->rowCount() > 0) {
              log_audit_trail('task_reassigned', 'task', $task_id, ['old_handler' => null, 'new_handler' => $new_user_id, 'reassigned_by' => $acting_user_id]); // Alte Handler ID müsste man vorher holen für detaillierteren Log
              return true;
         } else {
             // Aufgabe nicht gefunden oder kein Bearbeiterwechsel nötig
             return false;
         }


    } catch (PDOException $e) {
        error_log("Error reassigning task " . $task_id . " to user " . $new_user_id . " by user " . $acting_user_id . ": " . $e->getMessage());
        return false;
    }
}

// Benutzer schließt eine Aufgabe ab
function complete_task($task_id, $user_id) {
    $pdo = getPDO();

     // Hier findet NUR die DB-Operation statt.
     // Die Berechtigungsprüfung (darf der Benutzer abschließen?) muss VORHER in task_process.php erfolgen.
     // Optional kann die Funktion prüfen, ob die Task existiert und noch nicht abgeschlossen ist.

    $sql = "UPDATE tasks SET status = 'completed', completed_at = CURRENT_TIMESTAMP, last_updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status != 'completed' AND status != 'cancelled'"; // Nur abschließen, wenn nicht schon abgeschlossen/abgebrochen

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$task_id]);

        if ($stmt->rowCount() > 0) {
            log_audit_trail('task_completed', 'task', $task_id, ['completed_by' => $user_id]);
            return true;
        } else {
            // Aufgabe nicht gefunden oder konnte nicht abgeschlossen werden (z.B. Status schon abgeschlossen)
             return false;
        }

    } catch (PDOException $e) {
        error_log("Error completing task " . $task_id . " by user " . $user_id . ": " . $e->getMessage());
        return false;
    }
}

// Optional: Funktion zum Abbrechen einer Aufgabe
function cancel_task($task_id, $user_id) {
     $pdo = getPDO();

     // Berechtigungsprüfung (darf der Benutzer abbrechen?) muss VORHER in task_process.php erfolgen.

     $sql = "UPDATE tasks SET status = 'cancelled', completed_at = CURRENT_TIMESTAMP, last_updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status != 'completed' AND status != 'cancelled'"; // Nur abbrechen, wenn nicht schon abgeschlossen/abgebrochen


    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$task_id]);

        if ($stmt->rowCount() > 0) {
            log_audit_trail('task_cancelled', 'task', $task_id, ['cancelled_by' => $user_id]);
            return true;
        } else {
             return false;
        }

    } catch (PDOException $e) {
        error_log("Error cancelling task " . $task_id . " by user " . $user_id . ": " . $e->getMessage());
        return false;
    }
}


?>