<?php
require_once 'functions.php';
requireLogin();

$risk_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$risk_id) {
    header('Location: risks.php?status=error_invalid_id');
    exit;
}

$pdo = getPDO();

// Aktuelle Risikodaten laden
$stmt = $pdo->prepare("SELECT * FROM risks WHERE id = ?");
$stmt->execute([$risk_id]);
$risk = $stmt->fetch();

if (!$risk) {
    header('Location: risks.php?status=error_notfound');
    exit;
}

// Benutzer für Dropdown "Risikoeigner" laden
$users = $pdo->query("SELECT id, username FROM users WHERE is_active = TRUE ORDER BY username")->fetchAll();

// Definiere erlaubte Werte für ENUM-Felder für die Dropdowns
$likelihood_options = ['sehr gering', 'gering', 'mittel', 'hoch', 'sehr hoch'];
$impact_options = ['sehr gering', 'gering', 'mittel', 'hoch', 'sehr hoch'];
$status_options = ['identifiziert', 'analysiert', 'bewertet', 'in Behandlung', 'behandelt', 'akzeptiert', 'geschlossen'];
$treatment_options = ['vermeiden', 'mindern', 'übertragen', 'akzeptieren'];

$errors = [];
// Formularfelder mit Werten aus der Datenbank vorbefüllen
$name = $risk['name'];
$description = $risk['description'];
$risk_source = $risk['risk_source'];
$likelihood = $risk['likelihood'];
$impact = $risk['impact'];
$risk_level_manual = $risk['risk_level']; // Das Feld hieß 'risk_level' in der DB
$status = $risk['status'];
$treatment_option = $risk['treatment_option'];
$treatment_plan = $risk['treatment_plan'];
$owner_id = $risk['owner_id'];
$review_date = $risk['review_date'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Daten aus POST-Request übernehmen
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $risk_source = trim($_POST['risk_source'] ?? '');
    $likelihood = in_array($_POST['likelihood'], $likelihood_options) ? $_POST['likelihood'] : $risk['likelihood'];
    $impact = in_array($_POST['impact'], $impact_options) ? $_POST['impact'] : $risk['impact'];
    $status = in_array($_POST['status'], $status_options) ? $_POST['status'] : $risk['status'];
    $treatment_option_post = $_POST['treatment_option'] ?? '';
    $treatment_option = in_array($treatment_option_post, $treatment_options) ? $treatment_option_post : null;
    if ($treatment_option_post === '' && $risk['treatment_option'] === null) { // Fall: war null, bleibt null wenn leer gesendet
        $treatment_option = null;
    } elseif ($treatment_option_post === '' && $risk['treatment_option'] !== null) { // Fall: war gesetzt, wird auf null gesetzt wenn leer gesendet
         $treatment_option = null; // explizit null setzen, wenn leer gewünscht ist und vorher ein Wert da war. Oder Default beibehalten.
    }


    $treatment_plan = trim($_POST['treatment_plan'] ?? '');
    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $review_date_input = trim($_POST['review_date'] ?? '');
    $review_date = !empty($review_date_input) ? date('Y-m-d', strtotime($review_date_input)) : null;
    $risk_level_manual = trim($_POST['risk_level_manual'] ?? $risk['risk_level']);


    // Validierung
    if (empty($name)) {
        $errors[] = 'Der Name des Risikos ist erforderlich.';
    }
    if (!empty($review_date_input) && !$review_date) {
        $errors[] = 'Das Review-Datum hat ein ungültiges Format.';
    }
    // Weitere Validierungen ...

    if (empty($errors)) {
        // TODO: Risikolevel berechnen, falls gewünscht
        // $risk_level_calculated = calculateRiskLevel($likelihood, $impact);
        // Entscheiden, ob $risk_level_manual oder $risk_level_calculated verwendet wird

        try {
            $sql = "UPDATE risks SET
                        name = ?, description = ?, risk_source = ?,
                        likelihood = ?, impact = ?, risk_level = ?,
                        status = ?, treatment_option = ?, treatment_plan = ?,
                        owner_id = ?, review_date = ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name, $description, $risk_source,
                $likelihood, $impact, $risk_level_manual, // Hier den manuellen oder berechneten Wert einsetzen
                $status, $treatment_option, $treatment_plan,
                $owner_id, $review_date,
                $risk_id
            ]);
            log_audit_trail('UPDATE_RISK', 'Risk', $risk_id, ['name' => $name, 'status' => $status]);
            header('Location: risks.php?status=success_edit');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Aktualisieren des Risikos: ' . $e->getMessage();
            error_log("Risk Edit Error: " . $e->getMessage());
        }
    }
}

include 'header.php';
?>

<h2>Risiko bearbeiten: <?php echo he($risk['name']); ?></h2>

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

<form action="risk_edit.php?id=<?php echo he($risk_id); ?>" method="post">
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
        <input type="text" id="risk_level_manual" name="risk_level_manual" value="<?php echo he($risk_level_manual); ?>">
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

    <button type="submit" class="btn">Änderungen speichern</button>
    <a href="risks.php" class="btn" style="background-color: grey;">Abbrechen</a>
</form>

<?php include 'footer.php'; ?>