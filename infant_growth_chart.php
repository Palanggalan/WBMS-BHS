<?php
/**
 * infant_growth_chart.php - WHO-Standard Growth Engine
 * Upgraded with percentile shading and premium UI
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin', 'midwife', 'mother'])) {
    header("Location: login.php");
    exit();
}

$babyId = $_GET['baby_id'] ?? '';
if (empty($babyId)) die("Baby ID is required.");

global $pdo;

// 1. Get Baby Info
$stmt = $pdo->prepare("SELECT * FROM birth_records WHERE id = ?");
$stmt->execute([$babyId]);
$baby = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$baby) die("Baby not found.");

// 2. Get Postnatal Growth Data
$stmt = $pdo->prepare("
    SELECT visit_date, baby_weight, baby_height 
    FROM postnatal_records 
    WHERE baby_id = ? 
    ORDER BY visit_date ASC
");
$stmt->execute([$babyId]);
$measurements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. WHO Metadata (Simplified Weight-for-age 0-12 months for demonstration)
$isMale = (strtolower($baby['gender']) === 'male');

// WHO Weight-for-age (Simplified Data Points for 0-12 months)
// Format: Month => [P3, P15, P50, P85, P97]
$whoBoys = [
    0 => [2.5, 2.9, 3.3, 3.9, 4.4],
    1 => [3.4, 3.9, 4.5, 5.1, 5.8],
    2 => [4.3, 4.9, 5.6, 6.3, 7.1],
    3 => [5.0, 5.7, 6.4, 7.2, 8.0],
    4 => [5.6, 6.3, 7.0, 7.8, 8.7],
    6 => [6.4, 7.1, 7.9, 8.8, 9.8],
    9 => [7.1, 8.0, 8.9, 9.9, 11.0],
    12 => [7.7, 8.6, 9.6, 10.8, 12.0]
];

$whoGirls = [
    0 => [2.4, 2.8, 3.2, 3.7, 4.2],
    1 => [3.2, 3.6, 4.2, 4.8, 5.5],
    2 => [3.9, 4.5, 5.1, 5.8, 6.6],
    3 => [4.5, 5.2, 5.8, 6.6, 7.5],
    4 => [5.0, 5.7, 6.4, 7.3, 8.2],
    6 => [5.7, 6.5, 7.3, 8.2, 9.3],
    9 => [6.5, 7.4, 8.2, 9.3, 10.5],
    12 => [7.0, 7.9, 8.9, 10.1, 11.5]
];

$standards = $isMale ? $whoBoys : $whoGirls;
$p3 = []; $p15 = []; $p50 = []; $p85 = []; $p97 = []; $labels = [];

foreach ($standards as $month => $vals) {
    $labels[] = "Mo $month";
    $p3[] = $vals[0]; $p15[] = $vals[1]; $p50[] = $vals[2]; $p85[] = $vals[3]; $p97[] = $vals[4];
}

// Prepare Baby's Data
$babyPoints = [];
// Birth point (Month 0)
$babyPoints[] = ['x' => 'Mo 0', 'y' => $baby['birth_weight']];

foreach ($measurements as $m) {
    if (!empty($m['baby_weight'])) {
        $visitDate = new DateTime($m['visit_date']);
        $birthDate = new DateTime($baby['birth_date']);
        $diff = $birthDate->diff($visitDate);
        $months = floor(($diff->days) / 30.41);
        
        // Only plot if within the standard range (0-12 months)
        if ($months <= 12) {
            $babyPoints[] = ['x' => "Mo $months", 'y' => $m['baby_weight']];
        }
    }
}

// Recent Growth Logic
$currentWeight = !empty($measurements) ? end($measurements)['baby_weight'] : $baby['birth_weight'];
$weightGain = $currentWeight - $baby['birth_weight'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Growth Chart - <?= htmlspecialchars($baby['first_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 font-inter text-slate-900 antialiased selection:bg-health-100 selection:text-health-700">
    <?php include_once 'includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <header class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 bg-health-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-health-200/50 text-2xl">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Growth Monitoring</h1>
                        <p class="text-slate-500 font-medium mt-1">WHO Standardized Weight-for-Age Analytics</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="history.back()" class="inline-flex items-center gap-2 px-6 py-3.5 bg-slate-50 text-slate-600 rounded-2xl font-bold transition-all hover:bg-slate-100 border border-slate-200 active:scale-95 text-sm">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </button>
                    <button onclick="window.print()" class="inline-flex items-center gap-2 px-6 py-3.5 bg-slate-900 text-white rounded-2xl font-bold transition-all hover:bg-slate-800 shadow-lg active:scale-95 text-sm">
                        <i class="fas fa-print"></i>
                        Export PDF
                    </button>
                </div>
            </header>

            <!-- Bento Stat Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Profile Card -->
                <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm relative overflow-hidden group hover:border-health-100 transition-colors">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-health-50 rounded-full -mr-16 -mt-16 transition-transform group-hover:scale-110"></div>
                    <div class="relative flex items-center gap-4">
                        <div class="w-12 h-12 bg-health-50 rounded-2xl flex items-center justify-center text-health-600">
                            <i class="fas fa-id-card text-xl"></i>
                        </div>
                        <div>
                            <div class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Patient Profile</div>
                            <div class="text-lg font-black text-slate-900 leading-tight"><?= htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?></div>
                            <div class="text-[10px] font-bold text-slate-500 mt-1 uppercase tracking-tighter">
                                <?= ucfirst($baby['gender']); ?> • Born <?= date('M d, Y', strtotime($baby['birth_date'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Latest Weight -->
                <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm relative overflow-hidden group hover:border-emerald-100 transition-colors">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full -mr-16 -mt-16 transition-transform group-hover:scale-110"></div>
                    <div class="relative flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600">
                            <i class="fas fa-weight-scale text-xl"></i>
                        </div>
                        <div>
                            <div class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Current Weight</div>
                            <div class="text-3xl font-black text-slate-900 tracking-tighter leading-none"><?= $currentWeight; ?> <span class="text-sm font-bold text-slate-400 ml-1">kg</span></div>
                            <div class="text-[10px] font-bold text-emerald-500 mt-2 uppercase tracking-tight">Latest Recorded Data</div>
                        </div>
                    </div>
                </div>

                <!-- Growth Gain -->
                <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm relative overflow-hidden group hover:border-sky-100 transition-colors">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-sky-50 rounded-full -mr-16 -mt-16 transition-transform group-hover:scale-110"></div>
                    <div class="relative flex items-center gap-4">
                        <div class="w-12 h-12 bg-sky-50 rounded-2xl flex items-center justify-center text-sky-600">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <div>
                            <div class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Total Weight Gain</div>
                            <div class="text-3xl font-black text-slate-900 tracking-tighter leading-none">+<?= number_format($weightGain, 2); ?> <span class="text-sm font-bold text-slate-400 ml-1">kg</span></div>
                            <div class="text-[10px] font-bold text-sky-500 mt-2 uppercase tracking-tight">Since Birth (<?= $baby['birth_weight']; ?> kg)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Chart Area -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <div class="lg:col-span-3">
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h2 class="text-xl font-black text-slate-900">Weight-for-Age Analytics</h2>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Growth Percentile Shading (0-12 Months)</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-health-600 animate-pulse"></span>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Real-time Data</span>
                            </div>
                        </div>
                        <div class="h-[500px] w-full relative">
                            <canvas id="growthChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-6">
                    <!-- Legend Card -->
                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-6 px-2">Legend Guide</h3>
                        <div class="space-y-4">
                            <div class="flex items-center gap-3 p-3 bg-rose-50/50 border border-rose-100 rounded-2xl transition-all hover:bg-rose-50">
                                <div class="w-2 h-8 bg-rose-500 rounded-full"></div>
                                <div>
                                    <div class="text-[10px] font-black text-rose-600 uppercase">Borderline</div>
                                    <div class="text-[9px] font-bold text-slate-400">P3 / P97 Standard</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-emerald-50/50 border border-emerald-100 rounded-2xl transition-all hover:bg-emerald-50">
                                <div class="w-2 h-8 bg-emerald-500 rounded-full"></div>
                                <div>
                                    <div class="text-[10px] font-black text-emerald-600 uppercase">Optimal</div>
                                    <div class="text-[9px] font-bold text-slate-400">P15-P85 Distribution</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-slate-900 border border-slate-800 rounded-2xl">
                                <div class="w-3 h-3 bg-white rounded-full ml-1"></div>
                                <div>
                                    <div class="text-[10px] font-black text-white uppercase tracking-tight">Patient Record</div>
                                    <div class="text-[9px] font-bold text-slate-400">Individual History</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clinical Tip -->
                    <div class="bg-health-600 p-8 rounded-[2rem] text-white shadow-xl shadow-health-200 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-white/10 rounded-full transition-transform group-hover:scale-150"></div>
                        <div class="relative">
                            <i class="fas fa-info-circle text-2xl mb-4"></i>
                            <h3 class="text-sm font-black uppercase tracking-widest mb-2">Growth Tip</h3>
                            <p class="text-[11px] leading-relaxed font-medium text-health-50 opacity-90">
                                Healthy growth usually follows parallel to the curves. Sudden drops or spikes should be immediately reviewed by a midwife or pediatrician.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('growthChart').getContext('2d');
        const chartData = {
            labels: <?= json_encode($labels); ?>,
            datasets: [
                {
                    label: 'Baby Weight',
                    data: <?= json_encode($babyPoints); ?>,
                    borderColor: '#1e293b',
                    backgroundColor: '#1e293b',
                    borderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    z: 10,
                    type: 'line'
                },
                {
                    label: '97th Percentile',
                    data: <?= json_encode($p97); ?>,
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: false
                },
                {
                    label: '85th Percentile',
                    data: <?= json_encode($p85); ?>,
                    borderColor: '#10b981',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: '-1',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)'
                },
                {
                    label: '50th Percentile (Median)',
                    data: <?= json_encode($p50); ?>,
                    borderColor: '#10b981',
                    borderDash: [5, 5],
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false
                },
                {
                    label: '15th Percentile',
                    data: <?= json_encode($p15); ?>,
                    borderColor: '#10b981',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: '-1',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)'
                },
                {
                    label: '3rd Percentile',
                    data: <?= json_encode($p3); ?>,
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: '-1',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)'
                }
            ]
        };

        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { family: 'Inter', weight: 'bold', size: 12 },
                        bodyFont: { family: 'Inter', size: 11 },
                        padding: 12,
                        cornerRadius: 12,
                        displayColors: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: 'rgba(241, 245, 249, 0.5)', drawBorder: false },
                        ticks: { color: '#94a3b8', font: { family: 'Inter', weight: '600', size: 10 } },
                        title: { display: true, text: 'Weight (kg)', color: '#64748b', font: { family: 'Inter', weight: '700', size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { family: 'Inter', weight: '600', size: 10 } },
                        title: { display: true, text: 'Age (Months)', color: '#64748b', font: { family: 'Inter', weight: '700', size: 11 } }
                    }
                }
            }
        });
    </script>
</body>
</html>
