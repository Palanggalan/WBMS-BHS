<?php
// family_planning.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: login.php");
    exit();
}

$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Query
$query = "
    SELECT fpr.*, m.first_name, m.last_name, m.phone, fpm.method_name 
    FROM family_planning_records fpr
    JOIN mothers m ON fpr.mother_id = m.id
    JOIN family_planning_methods fpm ON fpr.method_id = fpm.id
";

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(m.first_name LIKE :search OR m.last_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY fpr.registration_date DESC LIMIT :limit OFFSET :offset";

// Count
$countQuery = "
    SELECT COUNT(*) 
    FROM family_planning_records fpr
    JOIN mothers m ON fpr.mother_id = m.id
";
if (!empty($whereConditions)) {
    $countQuery .= " WHERE " . implode(" AND ", $whereConditions);
}
$stmt = $pdo->prepare($countQuery);
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}
$stmt->execute();
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Execute Main
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Family Planning - Kibenes eBirth</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <?php include_once __DIR__ . '/includes/tailwind_config.php'; ?>
</head>
<body class="bg-slate-50 font-inter text-slate-900 antialiased selection:bg-health-100 selection:text-health-700">
    <?php include_once 'includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 bg-health-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-health-200/50 text-2xl">
                        <i class="fas fa-venus-mars"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Family Planning</h1>
                        <p class="text-slate-500 font-medium mt-1">Reproductive health and services management</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="px-4 py-2 bg-slate-50 rounded-xl border border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        Total: <?= number_format($totalRecords) ?> Clients
                    </div>
                    <a href="forms/family_planning_form.php" class="inline-flex items-center gap-2 px-6 py-3.5 bg-health-600 text-white rounded-2xl font-bold transition-all hover:bg-health-700 shadow-lg shadow-health-200 active:scale-95 text-sm">
                        <i class="fas fa-plus"></i>
                        New Client Registration
                    </a>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
                <form method="GET" class="relative group">
                    <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-health-500 transition-colors"></i>
                    <input type="text" name="search" class="w-full pl-12 pr-4 py-3.5 rounded-2xl border border-slate-200 focus:border-health-500 focus:ring-4 focus:ring-health-500/10 outline-none transition-all duration-200 bg-slate-50/50" 
                           placeholder="Search client name..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 px-5 py-2 bg-slate-900 text-white rounded-xl font-bold text-xs hover:bg-slate-800 transition-all">
                        Search Profiles
                    </button>
                </form>
            </div>

            <!-- Records Table -->
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden min-h-[400px]">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Client Name</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Contraceptive Method</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Registration Date</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Next Service</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Clinical Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="5" class="px-8 py-12 text-center">
                                        <div class="flex flex-col items-center gap-3 text-slate-300">
                                            <i class="fas fa-folder-open text-3xl"></i>
                                            <p class="text-[10px] font-bold uppercase tracking-widest">No family planning records found</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $r): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-health-100 group-hover:text-health-600 transition-colors">
                                                <i class="fas fa-venus text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-black text-slate-900 leading-tight">
                                                    <?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?>
                                                </div>
                                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-tight mt-1">
                                                    <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($r['phone'] ?? 'No contact'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <span class="px-3 py-1 rounded-full bg-sky-50 text-sky-600 border border-sky-100 text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-1.5 shadow-sm">
                                            <i class="fas fa-shield-virus text-[8px]"></i>
                                            <?php echo htmlspecialchars($r['method_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <div class="text-sm font-bold text-slate-700"><?php echo date('M d, Y', strtotime($r['registration_date'])); ?></div>
                                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">Registered</div>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <?php if ($r['next_service_date']): ?>
                                            <div class="text-sm font-black text-slate-900"><?php echo date('M d, Y', strtotime($r['next_service_date'])); ?></div>
                                            <div class="text-[9px] font-bold text-emerald-500 uppercase tracking-tighter">Scheduled</div>
                                        <?php else: ?>
                                            <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">No follow-up</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <p class="text-xs text-slate-500 font-medium line-clamp-2 max-w-xs leading-relaxed italic">
                                            <?php echo !empty($r['remarks']) ? htmlspecialchars($r['remarks']) : 'No clinical notes recorded.'; ?>
                                        </p>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-black transition-all <?php echo ($i == $page) ? 'bg-health-600 text-white shadow-lg shadow-health-200' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
