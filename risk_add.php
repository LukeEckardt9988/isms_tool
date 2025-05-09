<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();
$users = $pdo->query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username")->fetchAll(); // Für Dropdown "Risikoeigner"

// Definiere erlaubte Werte für ENUM-Felder für die Dropdowns
$likelihood_options = ['sehr gering', 'gering', 'mittel', 'hoch', 'sehr hoch'];
$impact_options = ['sehr gering', 'gering', 'mittel', 'hoch', 'sehr hoch'];
$status_options = ['identifiziert', 'analysiert', 'bewertet', 'in Behandlung', 'behandelt', 'akzeptiert', 'geschlossen'];
$treatment_options = ['vermeiden', 'mindern', 'übertragen', 'akzeptieren'];


$errors = [];
// Initialwerte für Formularfelder
$name = '';
$description = '';
$risk_source = '';
$likelihood = 'mittel';
$impact = 'mittel';
// risk_level wird oft berechnet oder später gesetzt
$status = 'identifiziert';
$treatment_option = '';
$treatment_plan = '';
$owner_id = null;
$review_date = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Daten aus POST-Request übernehmen
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $risk_source = trim($_POST['risk_source'] ?? '');
    $likelihood = in_array($_POST['likelihood'], $likelihood_options) ? $_POST['likelihood'] : 'mittel';
    $impact = in_array($_POST['impact'], $impact_options) ? $_POST['impact'] : 'mittel';
    $status = in_array($_POST['status'], $status_options) ? $_POST['status'] : 'identifiziert';
    $treatment_option = in_array($_POST['treatment_option'], $treatment_options) ? $_POST['treatment_option'] : null;
    $treatment_plan = trim($_POST['treatment_plan'] ?? '');
    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $review_date_input = trim($_POST['review_date'] ?? '');
    $review_date = !empty($review_date_input) ? date('Y-m-d', strtotime($review_date_input)) : null;


    // Validierung
    if (empty($name)) {
        $errors[] = 'Der Name des Risikos ist erforderlich.';
    }
    // Weitere Validierungen ...
    if (!empty($review_date_input) && !$review_date) {
        $errors[] = 'Das Review-Datum hat ein ungültiges Format.';
    }


    if (empty($errors)) {
        // TODO: Risikolevel berechnen, falls gewünscht (z.B. aus Likelihood und Impact)
        // $risk_level = calculateRiskLevel($likelihood, $impact); // Eigene Funktion dafür erstellen
        $risk_level_manual = $_POST['risk_level_manual'] ?? null; // Manuelle Eingabe oder Berechnung

        try {
            $sql = "INSERT INTO risks (name, description, risk_source, likelihood, impact, risk_level, status, treatment_option, treatment_plan, owner_id, review_date, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name, $description, $risk_source, $likelihood, $impact,
                $risk_level_manual, // Hier den manuellen oder berechneten Wert einsetzen
                $status, $treatment_option, $treatment_plan, $owner_id, $review_date
            ]);
            $risk_id = $pdo->lastInsertId();
            log_audit_trail('CREATE_RISK', 'Risk', $risk_id, ['name' => $name, 'status' => $status]);
            header('Location: risks.php?status=success_add');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern des Risikos: ' . $e->getMessage();
            error_log("Risk Add Error: " . $e->getMessage());
        }
    }
}

include 'header.php';
?>

<h2>Neues Risiko hinzufügen</h2>

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

<form action="risk_add.php" method="post">
    <div class="form-group">
        <label for="name">Name des Risikos:</label>
        <input type="text" id="name" name="name" value="<?php echo he($name); ?>" required>
    </div>
    <div class="form-group">
        <label for="description">Beschreibung des Risikos:</label>
        <textarea id="description" name="description"><?php echo he($description); ?></textarea>
    </div>
    <div class="form-group">
        <label for="risk_source">Risikoquelle (Bedrohung und/oder Schwachstelle):</label>
        <textarea id="risk_source" name="risk_source"><?php echo he($risk_source); ?></textarea>
    </div>

    <div class="form-group">
        <label for="likelihood">Eintrittswahrscheinlichkeit:</label>
        <select id="likelihood" name="likelihood">
            <?php foreach ($likelihood_options as $option): ?>
                <option value="<?php echo he($option); ?>" <?php echo ($likelihood === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="impact">Auswirkung / Schadenhöhe:</label>
        <select id="impact" name="impact">
            <?php foreach ($impact_options as $option): ?>
                <option value="<?php echo he($option); ?>" <?php echo ($impact === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="risk_level_manual">Risikolevel (manuell, z.B. Niedrig, Mittel, Hoch, Kritisch):</label>
        <input type="text" id="risk_level_manual" name="risk_level_manual" value="">
        <small>Wird später ggf. berechnet. Vorerst manuelle Eingabe möglich.</small>
    </div>

    <div class="form-group">
        <label for="status">Status:</label>
        <select id="status" name="status">
            <?php foreach ($status_options as $option): ?>
                <option value="<?php echo he($option); ?>" <?php echo ($status === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="treatment_option">Behandlungsoption:</label>
        <select id="treatment_option" name="treatment_option">
            <option value="">-- Bitte wählen --</option>
            <?php foreach ($treatment_options as $option): ?>
                <option value="<?php echo he($option); ?>" <?php echo ($treatment_option === $option ? 'selected' : ''); ?>><?php echo he(ucfirst($option)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="treatment_plan">Behandlungsplan / Maßnahmenbeschreibung:</label>
        <textarea id="treatment_plan" name="treatment_plan"><?php echo he($treatment_plan); ?></textarea>
    </div>
    <div class="form-group">
        <label for="owner_id">Risikoeigner:</label>
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
        <label for="review_date">Nächstes Review-Datum:</label>
        <input type="date" id="review_date" name="review_date" value="<?php echo he($review_date ? date('Y-m-d', strtotime($review_date)) : ''); ?>">
    </div>

    <button type="submit" class="btn">Risiko speichern</button>
    <a href="risks.php" class="btn" style="background-color: grey;">Abbrechen</a>
</form>

<?php include 'footer.php'; ?>