<?php
require_once 'functions.php';

requireLogin(); // Stellt sicher, dass der Benutzer eingeloggt ist

$current_user_id = getCurrentUserId();
$current_user_role = getCurrentUserRole();

// Alle Aufgaben holen (oder gefiltert)
$tasks = get_tasks(); // Passe Filter hier an, wenn du z.B. nur offene/mir zugewiesene sehen willst


include 'header.php'; // Dein Header (enthält Session-Start, requireLogin, display_flash_messages)

?>

<div class="container mt-4">
    <h2>Aufgabenübersicht</h2>

    <?php display_flash_messages(); // Zeigt Nachrichten an ?>

    <?php
    // Berechtigungsprüfung: Darf der Benutzer Aufgaben erstellen?
    if (hasPermission($current_user_role, 'create') || hasPermission($current_user_role, 'manage_tasks')) :
    ?>
        <p>
            <a href="task_add.php" class="btn btn-primary">Neue Aufgabe erstellen</a>
        </p>
    <?php endif; ?>


    <?php if (empty($tasks)): ?>
        <p>Es sind keine Aufgaben vorhanden.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Status</th>
                        <th>Priorität</th>
                        <th>Fällig bis</th>
                        <th>Erstellt von</th>
                        <th>Zugewiesen an (Init.)</th>
                        <th>Aktueller Bearbeiter</th>
                        <th>Erstellt am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><a href="task_view.php?id=<?php echo he($task['id']); ?>"><?php echo he($task['title']); ?></a></td>
                            <td><?php echo he($task['status']); ?></td>
                            <td><?php echo he($task['priority']); ?></td>
                            <td><?php echo he($task['due_date'] ?? 'N/A'); ?></td>
                            <td><?php echo he($task['created_by_username'] ?? 'Unbekannt'); ?></td>
                            <td><?php echo he($task['assigned_to_username'] ?? 'Nicht zugewiesen'); ?></td>
                            <td><?php echo he($task['current_handler_username'] ?? 'Niemand'); ?></td>
                            <td><?php echo he($task['created_at']); ?></td>
                            <td>
                                <?php
                                // Button "Übernehmen" anzeigen, wenn Aufgabe offen ist, keinen Bearbeiter hat
                                // UND der Benutzer berechtigt ist
                                if ($task['status'] == 'open' && (empty($task['current_handler']) || $task['current_handler'] == 0) && (hasPermission($current_user_role, 'edit') || hasPermission($current_user_role, 'manage_tasks')) ):
                                ?>
                                    <form action="task_process.php" method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="claim">
                                        <input type="hidden" name="task_id" value="<?php echo he($task['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Möchten Sie diese Aufgabe übernehmen?');">Übernehmen</button>
                                    </form>
                                <?php endif; ?>
                                <a href="task_view.php?id=<?php echo he($task['id']); ?>" class="btn btn-sm btn-info">Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>