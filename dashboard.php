<?php
require_once 'functions.php';
requireLogin();

$pdo = getPDO();

// 1. Asset-Beziehungen
$stmtAssets = $pdo->query("SELECT COUNT(*) as count FROM assets");
$assetCount = $stmtAssets->fetchColumn();

// 2. Risiken
$stmtRisksOpen = $pdo->query("SELECT COUNT(*) as count FROM risks WHERE status NOT IN ('geschlossen', 'akzeptiert')");
$openRiskCount = $stmtRisksOpen->fetchColumn();
$stmtRisksTotal = $pdo->query("SELECT COUNT(*) as count FROM risks");
$totalRiskCount = $stmtRisksTotal->fetchColumn();

// 3. Controls
$control_status_counts_for_chart = [];
$status_options_for_control_chart = ['geplant', 'in Umsetzung', 'teilweise umgesetzt', 'vollständig umgesetzt', 'nicht relevant', 'verworfen'];
foreach ($status_options_for_control_chart as $status_item) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM controls WHERE implementation_status = ?");
    $stmt->execute([$status_item]);
    $count = $stmt->fetchColumn();
    $control_status_counts_for_chart[$status_item] = $count ?? 0;
}
$totalControlsCount = array_sum(array_values($control_status_counts_for_chart));

// 4. Risikolevel
$risk_level_categories_for_chart = ['Niedrig', 'Mittel', 'Hoch', 'Kritisch'];
$risk_level_counts_for_chart = [];
foreach ($risk_level_categories_for_chart as $level) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM risks WHERE risk_level = ? AND status NOT IN ('geschlossen', 'akzeptiert')");
    $stmt->execute([$level]);
    $risk_level_counts_for_chart[$level] = $stmt->fetchColumn() ?? 0;
}
$totalOpenRisksForChart = array_sum(array_values($risk_level_counts_for_chart));

// 5.  Fällige Control-Überprüfungen (angepasst für Y-Achse)
$stmtReviewDates = $pdo->query("SELECT id, control_id_iso, next_review_date FROM controls WHERE next_review_date IS NOT NULL");
$reviewDates = $stmtReviewDates->fetchAll(PDO::FETCH_ASSOC);

$dataPoints = [];
$minDateTimestamp = null;
$maxDateTimestamp = null;
$todayTimestamp = time();
$overdueCount = 0;
$datesCount = []; // Array zum Zählen der Controls pro Datum

foreach ($reviewDates as $row) {
    $dateTimestamp = strtotime($row['next_review_date']);
    if ($dateTimestamp === false) {
        error_log("Ungültiges Datum gefunden: " . $row['next_review_date'] . " für Control: " . $row['control_id_iso']);
        continue;
    }
    if ($minDateTimestamp === null || $dateTimestamp < $minDateTimestamp) {
        $minDateTimestamp = $dateTimestamp;
    }
    if ($maxDateTimestamp === null || $dateTimestamp > $maxDateTimestamp) {
        $maxDateTimestamp = $dateTimestamp;
    }

    // Zählen der Controls pro Datum
    if (!isset($datesCount[$dateTimestamp])) {
        $datesCount[$dateTimestamp] = 0;
    }
    $datesCount[$dateTimestamp]++;

    $dataPoints[] = [
        'id' => $row['id'],
        'label' => $row['control_id_iso'],
        'date' => $dateTimestamp
    ];

    if ($dateTimestamp < $todayTimestamp) {
        $overdueCount++;
    }
}

// Daten für JavaScript (Timeline)
$timelineChartData = json_encode([
    'dataPoints' => $dataPoints,
    'minDate' => $minDateTimestamp,
    'maxDate' => $maxDateTimestamp,
    'now' => $todayTimestamp,
    'overdueCount' => $overdueCount,
    'datesCount' => $datesCount // Hinzugefügt
]);

include 'header.php';
?>

<h2>Dashboard</h2>
<p>Willkommen, <?php echo he($_SESSION['username']); ?>!</p>

<div class="dashboard-grid">
    <div class="widget chart-widget">
        <h3>Asset-Beziehungen</h3>
        <div class="chart-container">
            <canvas id="assetRelationshipsChart"></canvas>
        </div>
        <div class="widget-footer">
            <a href="assets.php" class="btn">Assets verwalten</a>
        </div>
    </div>

    <div class="widget chart-widget">
        <h3>Übersicht Control-Status (Gesamt: <?php echo he($totalControlsCount); ?>)</h3>
        <div class="chart-container">
            <canvas id="controlStatusChart"></canvas>
        </div>
        <div class="widget-footer">
            <a href="controls.php" class="btn">Controls verwalten</a>
        </div>
    </div>

    <div class="widget chart-widget">
        <h3>Risikolevel Verteilung (Offene Risiken: <?php echo he($totalOpenRisksForChart); ?>)</h3>
        <div class="chart-container">
            <canvas id="riskLevelRadarChart"></canvas>
        </div>
        <div class="widget-footer">
            <a href="risks.php" class="btn">Risiken verwalten</a>
        </div>
    </div>

    <div class="widget">
        <h3>Control-Timeline (<?php echo he($overdueCount); ?> ausstehend)</h3>
        <div class="chart-container">
            <canvas id="controlReviewTimelineChart"></canvas>
        </div>
    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // Kreisdiagramm für Control-Status
        const ctxControl = document.getElementById('controlStatusChart');
        const controlStatusDataPHP = <?php echo json_encode($control_status_counts_for_chart); ?>;
        if (ctxControl && controlStatusDataPHP && Object.keys(controlStatusDataPHP).length > 0) {
            const controlLabels = Object.keys(controlStatusDataPHP).map(status => status.charAt(0).toUpperCase() + status.slice(1));
            const controlDataValues = Object.values(controlStatusDataPHP);
            new Chart(ctxControl, {
                type: 'pie',
                data: {
                    labels: controlLabels,
                    datasets: [{
                        label: 'Control Status',
                        data: controlDataValues,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 159, 64, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(201, 203, 207, 0.8)'
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
                        datalabels: {
                            display: true,
                            color: '#fff',
                            font: {
                                weight: 'bold',
                                size: 11,
                            },
                            formatter: (value, context) => {
                                const total = context.chart.data.datasets[0].data.reduce((acc, val) => acc + val, 0);
                                const percentage = total > 0 ? ((value / total) * 100) : 0;
                                if (value === 0 && total > 0) return '0%';
                                if (value === 0) return '';
                                if (total > 0 && percentage < 7 && value !== 0) return value;
                                if (total > 0 && percentage >= 7) return percentage.toFixed(0) + '%';
                                return value;
                            },
                            anchor: 'center',
                            align: 'center'
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        }

        // Radar-Chart für Risikolevel
        const ctxRiskRadar = document.getElementById('riskLevelRadarChart');
        const riskLevelDataPHP = <?php echo json_encode($risk_level_counts_for_chart); ?>;
        if (ctxRiskRadar && riskLevelDataPHP && Object.keys(riskLevelDataPHP).length > 0) {
            const riskLabels = Object.keys(riskLevelDataPHP).map(label => label.charAt(0).toUpperCase() + label.slice(1));
            const riskDataValues = Object.values(riskLevelDataPHP);
            new Chart(ctxRiskRadar, {
                type: 'radar',
                data: {
                    labels: riskLabels,
                    datasets: [{
                        label: 'Anzahl offener Risiken',
                        data: riskDataValues,
                        fill: true,
                        backgroundColor: 'rgba(242, 243, 243, 0.2)',
                        borderColor: 'rgb(63, 163, 230)',
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
                                display: true,
                                color: 'rgba(173, 173, 173, 0.73)'
                            },
                            grid: {
                                color: 'rgba(194, 194, 194, 0.66)'
                            },
                            suggestedMin: 0,
                            ticks: {
                                callback: function(value, index, values) {
                                    if (values.length > 5 && value % (Math.ceil(Math.max(...values) / 5) || 1) !== 0 && value !== 0) {
                                        return '';
                                    }
                                    return Number.isInteger(value) ? value : '';
                                },
                                stepSize: Math.max(1, Math.ceil(Math.max(...riskDataValues, 1) / 4)),
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
        }

     // Timeline-Chart für Control-Überprüfungen
        const ctxTimeline = document.getElementById('controlReviewTimelineChart');
        const timelineDataPHP = JSON.parse('<?php echo $timelineChartData; ?>');

        if (ctxTimeline && timelineDataPHP && timelineDataPHP.dataPoints.length > 0) {
            const chartWidth = ctxTimeline.offsetWidth;
            const minDate = timelineDataPHP.minDate;
            const maxDate = timelineDataPHP.maxDate;
            const now = timelineDataPHP.now;
            const datesCount = timelineDataPHP.datesCount; // Hinzugefügt

            function formatDate(timestamp) {
                const date = new Date(timestamp * 1000);
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            function scaleDate(date, minDate, maxDate, chartWidth) {
                if (minDate === maxDate) {
                    return chartWidth / 2;
                }
                return ((date - minDate) / (maxDate - minDate)) * chartWidth;
            }

            // Funktion zur Berechnung der Y-Position basierend auf der Anzahl der Controls am Datum
            function scaleY(date, datesCount) {
                const maxOffset = 2; // Maximale vertikale Auslenkung
                const baseOffset = 1;  // Grundlinie
                const count = datesCount[date] || 1; // Standardwert 1, falls Datum nicht vorhanden
                return baseOffset + (count - 1) * 0.1; // Erhöhe Y leicht für jedes Control am gleichen Tag
            }

            const timelineData = {
                datasets: [{
                    data: timelineDataPHP.dataPoints.map(point => ({
                        x: scaleDate(point.date, minDate, maxDate, chartWidth),
                        y: scaleY(point.date, datesCount), // Verwendung der neuen scaleY Funktion
                        label: point.label,
                        date: formatDate(point.date),
                        id: point.id
                    })),
                    pointBackgroundColor: timelineDataPHP.dataPoints.map(point => point.date < now ? 'red' : 'green'),
                    pointRadius: 8,
                    pointHoverRadius: 12,
                    showLine: false, // Keine Linie mehr
                    borderColor: 'rgba(0, 0, 0, 0.2)',
                    borderWidth: 1
                }]
            };

            const timelineConfig = {
                type: 'scatter', // Zurück zu Scatter, da wir keine Linie wollen
                data: timelineData,
                options: {
                    scales: {
                        x: {
                            type: 'linear',
                            min: 0,
                            max: chartWidth,
                            display: false
                        },
                        y: {
                            min: 0,
                            max: 2,
                            display: false
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const dataPoint = context.dataset.data[context.dataIndex];
                                    return `${dataPoint.label} (Fällig: ${dataPoint.date})`;
                                }
                            }
                        },
                        onclick: (event, elements) => {
                            if (elements.length > 0) {
                                const clickedIndex = elements[0].index;
                                if (elements[0].element) {
                                    const controlId = elements[0].element.dataset.id;
                                    if (controlId) {
                                        window.location.href = `control_edit.php?id=${controlId}`;
                                    }
                                }
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'nearest',
                        intersect: true
                    }
                },
                plugins: [{
                    id: 'addControlIdToPoints',
                    beforeRender: chart => {
                        if (chart.data && chart.data.datasets && chart.data.datasets.length > 0) {
                            chart.data.datasets.forEach(dataset => {
                                if (dataset.data) {
                                    dataset.data.forEach((dataPoint, index) => {
                                        try {
                                            if (chart.getDatasetMeta(0) && chart.getDatasetMeta(0).data[index]) {
                                                chart.getDatasetMeta(0).data[index].dataset.id = dataPoint.id;
                                            }
                                        } catch (error) {
                                            console.error("Fehler beim Setzen der ID für Datenpunkt:", error);
                                        }
                                    });
                                }
                            });
                        }
                    }
                }]
            };

            try {
                const myChart = new Chart(ctxTimeline, timelineConfig);

                // Alternativer Klick-Handler (zusätzlich)
                ctxTimeline.onclick = function(evt) {
                    const points = myChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                    if (points && points.length > 0 && points[0].element) {
                        const firstPoint = points[0];
                        const controlId = myChart.data.datasets[firstPoint.datasetIndex].data[firstPoint.index].id;
                        if (controlId) {
                            window.location.href = `control_edit.php?id=${controlId}`;
                        }
                    }
                };
            } catch (error) {
                console.error("Fehler beim Initialisieren des Timeline-Charts:", error);
            }
        }

    });
</script>

<?php include 'footer.php'; ?>