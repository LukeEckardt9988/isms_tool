<?php
require_once 'functions.php';
requireLogin();

 if (!hasPermission(getCurrentUserRole(), 'create')) {
        echo '<script>alert("Fehlende Autorisierung."); window.history.back();</script>';
        exit;
    }


$pdo = getPDO();
$users = $pdo->query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username")->fetchAll(); // Für Dropdown "Verantwortlicher"

$errors = [];
$name = '';
$description = '';
$asset_type = '';
$classification = 'intern';
$owner_id = null;
$location = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $asset_type = trim($_POST['asset_type'] ?? '');
    $classification = $_POST['classification'] ?? 'intern';
    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $location = trim($_POST['location'] ?? '');

    if (empty($name)) {
        $errors[] = 'Der Name des Assets ist erforderlich.';
    }
    if (empty($asset_type)) {
        $errors[] = 'Der Asset-Typ ist erforderlich.';
    }
    // Weitere Validierungen hier ...

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO assets (name, description, asset_type, classification, owner_id, location, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $asset_type, $classification, $owner_id, $location]);
            $asset_id = $pdo->lastInsertId();
            log_audit_trail('CREATE_ASSET', 'Asset', $asset_id, ['name' => $name, 'type' => $asset_type]);
            header('Location: assets.php?status=success_add');
            exit;
        } catch (PDOException $e) {
            // Im produktiven Einsatz: Fehler loggen
            $errors[] = 'Fehler beim Speichern des Assets: ' . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<h2>Neues Asset hinzufügen</h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <strong>Fehler:</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo he($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="asset_add.php" method="post">
    <div class="form-group">
        <label for="name">Name des Assets:</label>
        <input type="text" id="name" name="name" value="<?php echo he($name); ?>" required>
    </div>
    <div class="form-group">
        <label for="description">Beschreibung:</label>
        <textarea id="description" name="description"><?php echo he($description); ?></textarea>
    </div>
    <div class="form-group">
        <label for="asset_type">Asset-Typ (z.B. Hardware, Software, PCB-Design):</label>
        <input type="text" id="asset_type" name="asset_type" value="<?php echo he($asset_type); ?>" required>
    </div>
    <div class="form-group">
        <label for="classification">Klassifizierung:</label>
        <select id="classification" name="classification">
            <option value="öffentlich" <?php echo ($classification === 'öffentlich' ? 'selected' : ''); ?>>Öffentlich</option>
            <option value="intern" <?php echo ($classification === 'intern' ? 'selected' : ''); ?>>Intern</option>
            <option value="vertraulich" <?php echo ($classification === 'vertraulich' ? 'selected' : ''); ?>>Vertraulich</option>
            <option value="streng vertraulich" <?php echo ($classification === 'streng vertraulich' ? 'selected' : ''); ?>>Streng vertraulich</option>
        </select>
    </div>
    <div class="form-group">
        <label for="owner_id">Verantwortlicher:</label>
        <select id="owner_id" name="owner_id">
            <option value="">-- Bitte wählen --</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo he($user['id']); ?>" <?php echo ($owner_id == $user['id'] ? 'selected' : ''); ?>>
                    <?php echo he($user['username']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="location">Standort/Ablageort:</label>
        <input type="text" id="location" name="location" value="<?php echo he($location); ?>">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-icon" aria-label="Speichern"><i class="fas fa-save"></i></button>
        <a href="assets.php" class="btn btn-danger btn-icon" aria-label="Abbrechen"><i class="fas fa-times"></i></a>
    </div>
</form>

<?php include 'footer.php'; ?>