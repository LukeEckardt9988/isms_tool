<?php
require_once 'functions.php';
requireLogin();

// Überprüfen, ob der Benutzer ein Admin ist
if (getCurrentUserRole() !== 'admin') {
    http_response_code(403);
    echo "Nicht autorisiert.";
    exit;
}

include 'header.php';

try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT id, username, role, is_active FROM users");
    $users = $stmt->fetchAll();

    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $userIdToDelete = $_GET['id'];
        // Sicherheitsabfrage, um versehentliches Löschen zu verhindern
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userIdToDelete]);

        log_audit_trail('USER_DELETED', 'User', $userIdToDelete);

        $_SESSION['flash_success'] = "Benutzer wurde gelöscht.";
        header('Location: users.php');
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
        $userIdToToggle = $_GET['id'];
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$userIdToToggle]);

        $status = ($pdo->query("SELECT is_active FROM users WHERE id = " . (int)$userIdToToggle)->fetchColumn() ? 'aktiviert' : 'deaktiviert');
        log_audit_trail('USER_TOGGLED', 'User', $userIdToToggle, ['status' => $status]);

        $_SESSION['flash_success'] = "Benutzer wurde " . $status . ".";
        header('Location: users.php');
        exit;
    }


} catch (PDOException $e) {
    echo "<p class='error'>Datenbankfehler: " . he($e->getMessage()) . "</p>";
    error_log("Benutzerliste Fehler: " . $e->getMessage());
}

display_flash_messages(); // Flash Messages anzeigen
?>

<h2>Benutzerverwaltung</h2>

<p><a href="add_user.php" class="btn">Neuen Benutzer hinzufügen</a></p>

<?php if (empty($users)): ?>
    <p>Es sind keine Benutzer vorhanden.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Benutzername</th>
                <th>Rolle</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo he($user['id']); ?></td>
                    <td><?php echo he($user['username']); ?></td>
                    <td><?php echo he($user['role']); ?></td>
                    <td><?php echo $user['is_active'] ? 'Aktiv' : 'Deaktiviert'; ?></td>
                    <td>
                        <a href="user_edit.php?id=<?php echo he($user['id']); ?>">Bearbeiten</a> |
                        <a href="?action=delete&id=<?php echo he($user['id']); ?>" onclick="return confirm('Sind Sie sicher?')">Löschen</a> |
                        <a href="?action=toggle&id=<?php echo he($user['id']); ?>">
                            <?php echo $user['is_active'] ? 'Deaktivieren' : 'Aktivieren'; ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include 'footer.php'; ?>