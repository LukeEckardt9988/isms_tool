<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO(); // Stellt Verbindung zur 'epsa_isms' Datenbank her

// Assets Zählung
$stmtAssets = $pdo->query("SELECT COUNT(*) as count FROM assets");
$assetCount = $stmtAssets->fetchColumn();

// Risiken Zählung
$stmtRisksOpen = $pdo->query("SELECT COUNT(*) as count FROM risks WHERE status NOT IN ('geschlossen', 'akzeptiert')");
$openRiskCount = $stmtRisksOpen->fetchColumn();
$stmtRisksTotal = $pdo->query("SELECT COUNT(*) as count FROM risks");
$totalRiskCount = $stmtRisksTotal->fetchColumn();

// Controls - Zählung für das Kreisdiagramm
$control_status_counts_for_chart = [];
// Definieren Sie hier die Status, die im Diagramm erscheinen sollen,
// in der gewünschten Reihenfolge (beeinflusst auch Farbzuweisung)
$status_options_for_control_chart = ['geplant', 'in Umsetzung', 'teilweise umgesetzt', 'vollständig umgesetzt', 'nicht relevant', 'verworfen'];

foreach ($status_options_for_control_chart as $status_item) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM controls WHERE implementation_status = ?");
    $stmt->execute([$status_item]);
    $count = $stmt->fetchColumn();
    // Entscheidung: Nur Status mit Controls > 0 ins Diagramm oder alle?
    // Für ein vollständiges Bild ist es oft besser, alle definierten Statuskategorien zu haben, auch wenn sie 0 sind.
    $control_status_counts_for_chart[$status_item] = $count ?? 0;
}
$totalControlsCount = array_sum(array_values($control_status_counts_for_chart));

// Daten für Risikolevel-Radar-Chart
$risk_level_categories_for_chart = ['Niedrig', 'Mittel', 'Hoch', 'Kritisch']; // Passen Sie dies an Ihre Risikolevel-Definition an
$risk_level_counts_for_chart = [];
foreach ($risk_level_categories_for_chart as $level) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM risks WHERE risk_level = ? AND status NOT IN ('geschlossen', 'akzeptiert')"); // Nur offene Risiken
    $stmt->execute([$level]);
    $risk_level_counts_for_chart[$level] = $stmt->fetchColumn() ?? 0;
}
$totalOpenRisksForChart = array_sum(array_values($risk_level_counts_for_chart));

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
    <div class="widget chart-widget">
        <h3>Übersicht Control-Status (Gesamt: <?php echo he($totalControlsCount); ?>)</h3>
        <div class="chart-container">
            <canvas id="controlStatusChart"></canvas>
        </div>
        <div style="text-align: center; margin-top: auto; padding-top:15px;">
            <a href="controls.php" class="btn">Controls verwalten</a>
        </div>
    </div>

    <div class="widget chart-widget">
        <h3>Risikolevel Verteilung (Offene Risiken: <?php echo he($totalOpenRisksForChart); ?>)</h3>
        <div class="chart-container">
            <canvas id="riskLevelRadarChart"></canvas>
        </div>
        <div style="text-align: center; margin-top: auto; padding-top:15px;">
            <a href="risks.php" class="btn">Risiken verwalten</a>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sicherstellen, dass ChartDataLabels global verfügbar ist
        // (wird automatisch von Chart.js gemacht, wenn das Plugin geladen ist)

        // Kreisdiagramm für Control-Status
        const ctxControl = document.getElementById('controlStatusChart');
        const controlStatusDataPHP = <?php echo json_encode($control_status_counts_for_chart); ?>;

        if (ctxControl && controlStatusDataPHP && Object.keys(controlStatusDataPHP).length > 0) {
            const controlLabels = Object.keys(controlStatusDataPHP).map(status => status.charAt(0).toUpperCase() + status.slice(1));
            const controlDataValues = Object.values(controlStatusDataPHP);

            // Nur zeichnen, wenn es tatsächlich Werte > 0 gibt oder Sie leere Diagramme zeigen wollen
            // if (controlDataValues.some(v => v > 0)) { // Wenn nur bei > 0 gezeichnet werden soll
            new Chart(ctxControl, {
                type: 'pie',
                data: {
                    labels: controlLabels,
                    datasets: [{
                        label: 'Control Status',
                        data: controlDataValues,
                        backgroundColor: [ // Stellen Sie sicher, dass Sie genug Farben für Ihre Status haben
                            'rgba(255, 99, 132, 0.8)', // Rot (z.B. geplant)
                            'rgba(54, 162, 235, 0.8)', // Blau (z.B. in Umsetzung)
                            'rgba(255, 159, 64, 0.8)', // Orange (z.B. teilweise umgesetzt)
                            'rgba(75, 192, 192, 0.8)', // Grün (z.B. vollständig umgesetzt)
                            'rgba(153, 102, 255, 0.8)', // Violett (z.B. nicht relevant)
                            'rgba(201, 203, 207, 0.8)' // Grau (z.B. verworfen)
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        datalabels: { // Konfiguration für chartjs-plugin-datalabels
                            display: true,
                            color: '#fff',
                            font: {
                                weight: 'bold',
                                size: 11,
                            },
                            formatter: (value, context) => {
                                const total = context.chart.data.datasets[0].data.reduce((acc, val) => acc + val, 0);
                                const percentage = total > 0 ? ((value / total) * 100) : 0;
                                if (value === 0 && total > 0) return '0%'; // Zeige 0% wenn Wert 0 ist aber andere Werte existieren
                                if (value === 0) return ''; // Verstecke Label wenn Wert und Total 0 sind

                                if (total > 0 && percentage < 7 && value !== 0) return value; // Absolute Zahl für kleine Segmente
                                if (total > 0 && percentage >= 7) return percentage.toFixed(0) + '%';
                                return value; // Fallback, wenn Total 0 ist (zeigt absoluten Wert)
                            },
                            anchor: 'center',
                            align: 'center'
                        }
                    }
                },
                plugins: [ChartDataLabels] // Plugin registrieren
            });
            // } // Ende der if (controlDataValues.some(v => v > 0)) Bedingung
        }

        // Radar-Chart für Risikolevel
        const ctxRiskRadar = document.getElementById('riskLevelRadarChart');
        const riskLevelDataPHP = <?php echo json_encode($risk_level_counts_for_chart); ?>;

        if (ctxRiskRadar && riskLevelDataPHP && Object.keys(riskLevelDataPHP).length > 0) {
            const riskLabels = Object.keys(riskLevelDataPHP).map(label => label.charAt(0).toUpperCase() + label.slice(1));
            const riskDataValues = Object.values(riskLevelDataPHP);

            // if (riskDataValues.some(v => v > 0)) { // Wenn nur bei > 0 gezeichnet werden soll
            new Chart(ctxRiskRadar, {
                type: 'radar',
                data: {
                    labels: riskLabels,
                    datasets: [{
                        label: 'Anzahl offener Risiken',
                        data: riskDataValues,
                        fill: true,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgb(54, 162, 235)',
                        pointBackgroundColor: 'rgb(54, 162, 235)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgb(54, 162, 235)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    elements: {
                        line: {
                            borderWidth: 2
                        }
                    },
                    scales: {
                        r: {
                            angleLines: {
                                display: true
                            },
                            suggestedMin: 0,
                            ticks: {
                                // Dynamische Schrittweite, um Überlappung zu vermeiden
                                callback: function(value, index, values) {
                                    // Zeige nur jeden n-ten Tick, wenn viele Ticks da wären, oder ganze Zahlen
                                    if (values.length > 5 && value % (Math.ceil(Math.max(...values) / 5) || 1) !== 0 && value !== 0) {
                                        return '';
                                    }
                                    return Number.isInteger(value) ? value : '';
                                },
                                stepSize: Math.max(1, Math.ceil(Math.max(...riskDataValues, 1) / 4)), // Mind. 4 Schritte oder Schrittweite 1
                                backdropColor: 'rgba(255, 255, 255, 0.75)'
                            },
                            pointLabels: {
                                font: {
                                    size: 12,
                                    weight: '500'
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        datalabels: {
                            display: true,
                            color: 'rgb(54, 162, 235)',
                            align: 'end',
                            anchor: 'end',
                            font: {
                                weight: 'bold',
                                size: 11
                            },
                            formatter: (value) => {
                                return value > 0 ? value : '';
                            }
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
            // } // Ende der if (riskDataValues.some(v => v > 0)) Bedingung
        }
    });
</script>

<?php include 'footer.php'; ?>