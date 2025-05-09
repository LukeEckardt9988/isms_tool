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

// Controls - Zählung für das Diagramm
$control_status_counts_for_chart = [];
// Die Status, die wir im Diagramm sehen wollen (und ihre Reihenfolge)
// Passen Sie diese an die ENUM-Werte in Ihrer DB und die gewünschte Darstellung an
$status_options_for_chart = ['geplant', 'in Umsetzung', 'teilweise umgesetzt', 'vollständig umgesetzt', 'nicht relevant', 'verworfen']; 

foreach ($status_options_for_chart as $status_item) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM controls WHERE implementation_status = ?");
    $stmt->execute([$status_item]);
    // Nur Status mit mehr als 0 Controls ins Diagramm aufnehmen, um es übersichtlich zu halten (optional)
    $count = $stmt->fetchColumn();
    if ($count > 0) {
         $control_status_counts_for_chart[$status_item] = $count;
    }
}
$totalControlsCount = array_sum($control_status_counts_for_chart); // Gesamtzahl basierend auf gefilterten Status

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
    <div class="widget chart-widget"> <h3>Übersicht Control-Status</h3>
        <?php if (!empty($control_status_counts_for_chart)): ?>
            <canvas id="controlStatusChart"></canvas>
        <?php else: ?>
            <p>Keine Control-Daten für das Diagramm verfügbar.</p>
        <?php endif; ?>
        <div style="text-align: center; margin-top: 15px;"> <a href="controls.php" class="btn">Controls verwalten</a>
        </div>
    </div>
</div>

<style>
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    .widget { border: 1px solid #ccc; padding: 15px; border-radius: 5px; background-color: #f9f9f9; display: flex; flex-direction: column;}
    .widget h3 { margin-top: 0; color: #337ab7; text-align: center; }
    .widget p { margin-bottom: 8px; }
    .chart-widget { min-height: 380px; /* Mindesthöhe für das Diagramm-Widget */ }
    #controlStatusChart { max-width: 100%; margin: 0 auto; /* Zentriert das Canvas, falls es schmaler ist */ max-height: 300px; /* Höhe des Diagramms */ display: block; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script> <script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('controlStatusChart');
    if (ctx) {
        const controlStatusData = <?php echo json_encode($control_status_counts_for_chart); ?>;
        
        const labels = Object.keys(controlStatusData).map(status => {
            return status.charAt(0).toUpperCase() + status.slice(1);
        });
        const dataValues = Object.values(controlStatusData);

        // Überprüfen, ob überhaupt Daten vorhanden sind, um Fehler zu vermeiden
        if (dataValues.reduce((a, b) => a + b, 0) > 0) { // Prüft, ob die Summe der Werte > 0 ist
            new Chart(ctx, {
                type: 'pie', // oder 'doughnut'
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Control Status',
                        data: dataValues,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',  // Rot (z.B. geplant)
                            'rgba(54, 162, 235, 0.8)', // Blau (z.B. in Umsetzung)
                            'rgba(255, 159, 64, 0.8)', // Orange (z.B. teilweise umgesetzt)
                            'rgba(75, 192, 192, 0.8)', // Grün (z.B. vollständig umgesetzt)
                            'rgba(153, 102, 255, 0.8)',// Violett (z.B. nicht relevant)
                            'rgba(201, 203, 207, 0.8)' // Grau (z.B. verworfen)
                            // Fügen Sie mehr Farben hinzu, falls Sie mehr Status haben
                        ],
                        borderColor: '#fff', // Weiße Ränder für bessere Trennung
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom', // Legende unten für mehr Platz
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        title: {
                            display: false, // Titel wird bereits im Widget-Header angezeigt
                            // text: 'Übersicht Control Status' 
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed;
                                    }
                                    const total = context.dataset.data.reduce((acc, value) => acc + value, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) + '%' : '0%';
                                    label += ` (${percentage})`;
                                    return label;
                                }
                            }
                        },
                        datalabels: { // Konfiguration für chartjs-plugin-datalabels
                            display: true, // true, um Labels anzuzeigen; 'auto' für automatische Entscheidung
                            color: '#fff', // Farbe der Zahlen
                            font: {
                                weight: 'bold',
                                size: 12,
                            },
                            formatter: (value, context) => {
                                // Zeige den Wert nur an, wenn er nicht 0 ist (optional)
                                // if (value === 0) return ''; 
                                
                                const total = context.chart.data.datasets[0].data.reduce((acc, val) => acc + val, 0);
                                const percentage = total > 0 ? ((value / total) * 100) : 0;
                                // Zeige nur Prozentsatz an, wenn er signifikant ist (z.B. > 5%)
                                if (percentage < 5 && value !== 0) return value; // Zeige kleine Werte als absolute Zahl
                                if (percentage >=5) return percentage.toFixed(0) + '%';
                                return ''; // Verstecke Label für 0-Werte oder sehr kleine Prozentsätze
                            },
                            anchor: 'center', // Position des Labels
                            align: 'center'   // Ausrichtung des Labels
                        }
                    }
                },
                plugins: [ChartDataLabels] // Plugin registrieren
            });
        } else {
            // Optional: Nachricht anzeigen, wenn keine Daten für das Diagramm vorhanden sind
            // (wird bereits durch PHP oben abgedeckt)
             ctx.getContext('2d').fillText('Keine Daten für das Diagramm vorhanden.', ctx.width / 2, ctx.height / 2);
             ctx.textAlign = 'center';
        }
    } else {
        console.error("Canvas Element mit ID 'controlStatusChart' nicht gefunden.");
    }
});
</script>

<?php include 'footer.php'; ?>