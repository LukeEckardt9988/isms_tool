<?php
session_start();

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

function log_audit_trail($action, $entity_type = null, $entity_id = null, $details = null)
{
    if (!isLoggedIn()) return; // Nur für eingeloggte Benutzer Aktionen loggen

    $pdo = getPDO();
    $sql = "INSERT INTO audit_trails (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $entity_type,
            $entity_id,
            is_array($details) ? json_encode($details) : $details,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
        ]);
    } catch (PDOException $e) {
        // Fehler beim Loggen behandeln (z.B. in eine Datei schreiben)
        error_log("Audit Trail Error: " . $e->getMessage());
    }
}



#################################################################################################################################
#################################################################################################################################
#################################################################################################################################

/**
 * Berechnet das Risikolevel basierend auf Eintrittswahrscheinlichkeit und Auswirkung.
 * * @param string $likelihood Die ausgewählte Eintrittswahrscheinlichkeit (z.B. 'mittel').
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
    // Alternative komplexere Matrix als Array:
    // $riskMatrix = [ // likelihood als Zeilenindex, impact als Spaltenindex (0-basiert)
    // // Auswirkung: Sehr Gering (0), Gering (1), Mittel (2), Hoch (3), Sehr Hoch (4)
    //     /* SG (0) */ ['Niedrig', 'Niedrig', 'Mittel', 'Mittel', 'Hoch'],
    //     /* G  (1) */ ['Niedrig', 'Mittel',  'Mittel', 'Hoch',   'Hoch'],
    //     /* M  (2) */ ['Mittel',  'Mittel',  'Hoch',   'Hoch',   'Kritisch'],
    //     /* H  (3) */ ['Mittel',  'Hoch',    'Hoch',   'Kritisch','Kritisch'],
    //     /* SH (4) */ ['Hoch',    'Hoch',    'Kritisch','Kritisch','Kritisch']
    // ];
    // // $numeric_likelihood - 1 und $numeric_impact - 1 für Array-Indizes verwenden
    // return $riskMatrix[$numeric_likelihood - 1][$numeric_impact - 1];


#################################################################################################################################
#################################################################################################################################
#################################################################################################################################

//Mit der document_edit.php können Sie nun die beschreibenden Informationen zu Ihren hochgeladenen Dokumenten pflegen.


function display_flash_messages() {
    if (isset($_SESSION['flash_success'])) {
        echo '<p class="success">' . he($_SESSION['flash_success']) . '</p>';
        unset($_SESSION['flash_success']); // Nachricht nach Anzeige löschen
    }
    if (isset($_SESSION['flash_error'])) {
        echo '<p class="error">' . he($_SESSION['flash_error']) . '</p>';
        unset($_SESSION['flash_error']);
    }
}


#################################################################################################################################
#################################################################################################################################
#################################################################################################################################



 function hasPermission($role, $action) {
        $permissions = [
            'admin' => ['view', 'create', 'edit', 'delete', 'manage_users'],
            'manager' => ['view', 'create', 'edit', 'delete'],
            'viewer' => ['view']
        ];

        if (!isset($permissions[$role])) {
            return false; // Unbekannte Rolle
        }

        return in_array($action, $permissions[$role]);
    }



#################################################################################################################################
#################################################################################################################################
#################################################################################################################################