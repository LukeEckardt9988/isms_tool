<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();

// Assets
$stmtAssets = $pdo->query("SELECT COUNT(*) as count FROM assets");
$assetCount = $stmtAssets->fetchColumn();

// Risiken
$stmtRisksOpen = $pdo->query("SELECT COUNT(*) as count FROM risks WHERE status NOT IN ('geschlossen', 'akzeptiert')");
$openRiskCount = $stmtRisksOpen->fetchColumn();
$stmtRisksTotal = $pdo->query("SELECT COUNT(*) as count FROM risks");
$totalRiskCount = $stmtRisksTotal->fetchColumn();


// Controls - Detailliertere Zählung
$stmtControlsTotal = $pdo->query("SELECT COUNT(*) as count FROM controls");
$totalControlsCount = $stmtControlsTotal->fetchColumn();

// Zählung für jeden Status
$control_status_counts = [];
$status_options_for_dashboard = ['geplant', 'in Umsetzung', 'teilweise umgesetzt', 'vollständig umgesetzt', 'nicht relevant', 'verworfen']; // Alle relevanten Status

foreach ($status_options_for_dashboard as $status_item) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM controls WHERE implementation_status = ?");
    $stmt->execute([$status_item]);
    $control_status_counts[$status_item] = $stmt->fetchColumn();
}

include 'header.php';
?>

<h2>Dashboard</h2>
<p>Willkommen, <?php echo he($_SESSION['username']); ?>!</p>

<div class="dashboard-grid">
    <div class="widget">
        <h3>Assets</h3>
        <p>Gesamt: <?php echo he($assetCount); ?></p>
        <a href="assets.php" class="btn">Assets verwalten</a>
    </div>
    <div class="widget">
        <h3>Risiken</h3>
        <p>Offen: <?php echo he($openRiskCount); ?> / Gesamt: <?php echo he($totalRiskCount); ?></p>
        <a href="risks.php" class="btn">Risiken verwalten</a>
    </div>
    <div class="widget" id="controls-widget"> <h3>Controls (Maßnahmen)</h3>
        <p>Gesamt: <?php echo he($totalControlsCount); ?></p>
        <?php foreach ($control_status_counts as $status => $count): ?>
            <p><?php echo he(ucfirst($status)); ?>: <?php echo he($count); ?></p>
        <?php endforeach; ?>
        <a href="controls.php" class="btn">Controls verwalten</a>
    </div>
    <div class="widget">
        <h3>Control-Status (Übersicht)</h3>
        <canvas id="controlStatusChart"></canvas>
    </div>
</div>

<style>
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    .widget { border: 1px solid #ccc; padding: 15px; border-radius: 5px; background-color: #f9f9f9;}
    .widget h3 { margin-top: 0; color: #337ab7; }
    .widget p { margin-bottom: 8px; }
    #controlStatusChart { max-width: 100%; max-height: 300px; } /* Stellt sicher, dass das Diagramm ins Widget passt */
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('controlStatusChart');
    if (ctx) {
        // PHP-Daten in JavaScript-Variablen übergeben
        const controlStatusData = <?php echo json_encode($control_status_counts); ?>;

        const labels = Object.keys(controlStatusData).map(status => {
            // Macht den ersten Buchstaben groß für die Labels
            return status.charAt(0).toUpperCase() + status.slice(1);
        });
        const dataValues = Object.values(controlStatusData);

        new Chart(ctx, {
            type: 'pie', // oder 'doughnut' für einen Ring
            data: {
                labels: labels,
                datasets: [{
                    label: 'Control Status',
                    data: dataValues,
                    backgroundColor: [ // Farben für die Segmente
                        'rgba(255, 99, 132, 0.7)',  // Rot (z.B. geplant)
                        'rgba(54, 162, 235, 0.7)', // Blau (z.B. in Umsetzung)
                        'rgba(255, 206, 86, 0.7)', // Gelb (z.B. teilweise umgesetzt)
                        'rgba(75, 192, 192, 0.7)', // Grün (z.B. vollständig umgesetzt)
                        'rgba(153, 102, 255, 0.7)',// Violett (z.B. nicht relevant)
                        'rgba(201, 203, 207, 0.7)' // Grau (z.B. verworfen)
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(201, 203, 207, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Erlaubt dem Diagramm, die Höhe des Canvas zu nutzen
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false, // Titel schon im Widget-Header
                        text: 'Übersicht Control Status'
                    }
                }
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>