<?php
// immunization_records.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Query to get babies and their latest immunization status
// We use a LEFT JOIN on a subquery to get the latest immunization date
$query = "
    SELECT 
        br.id as baby_id,
        br.first_name, 
        br.last_name, 
        br.birth_date, 
        br.gender,
        m.first_name as mother_first_name,
        m.last_name as mother_last_name,
        m.phone as mother_phone,
        MAX(ir.date_given) as last_vaccine_date,
        MAX(ir.next_dose_date) as next_due_date,
        COUNT(ir.id) as vaccine_count
    FROM birth_records br
    JOIN mothers m ON br.mother_id = m.id
    LEFT JOIN immunization_records ir ON br.id = ir.baby_id
";

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(br.first_name LIKE :search OR br.last_name LIKE :search 
                          OR m.first_name LIKE :search OR m.last_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " GROUP BY br.id, br.first_name, br.last_name, br.birth_date, br.gender, m.first_name, m.last_name, m.phone";
$query .= " ORDER BY br.birth_date DESC LIMIT :limit OFFSET :offset";

// Count for pagination
$countQuery = "SELECT COUNT(DISTINCT br.id) FROM birth_records br JOIN mothers m ON br.mother_id = m.id";
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

// Execute Main Query
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$babies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Immunization Records - Kibenes eBirth</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <?php include_once __DIR__ . '/includes/tailwind_config.php'; ?>
    <style type="text/tailwindcss">
        @layer components {
            .status-badge {
                @apply px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm;
            }
            .status-up-to-date {
                @apply bg-emerald-50 text-emerald-600 border border-emerald-100;
            }
            .status-due {
                @apply bg-amber-50 text-amber-600 border border-amber-100;
            }
            .status-overdue {
                @apply bg-rose-50 text-rose-600 border border-rose-100;
            }
            .table-container-premium {
                @apply bg-white rounded-[2rem] border border-slate-100 shadow-sm shadow-slate-200/50 overflow-hidden;
            }
            .search-input-premium {
                @apply w-full pl-12 pr-4 py-3.5 rounded-2xl border border-slate-200 focus:border-health-500 focus:ring-4 focus:ring-health-500/10 outline-none transition-all duration-200 bg-slate-50/50;
            }
        }
    </style>
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
                        <i class="fas fa-syringe"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Immunization Records</h1>
                        <p class="text-slate-500 font-medium mt-1">Vaccination tracking and schedule monitoring</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="px-4 py-2 bg-slate-50 rounded-xl border border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        Total: <?= number_format($totalRecords) ?> Profiles
                    </div>
                    <a href="forms/immunization_form.php" class="inline-flex items-center gap-2 px-6 py-3.5 bg-health-600 text-white rounded-2xl font-bold transition-all hover:bg-health-700 shadow-lg shadow-health-200 active:scale-95 text-sm">
                        <i class="fas fa-plus"></i>
                        Record New Vaccine
                    </a>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
                <form method="GET" class="relative group">
                    <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-health-500 transition-colors"></i>
                    <input type="text" name="search" class="search-input-premium" 
                           placeholder="Search infant name or mother's name..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 px-5 py-2 bg-slate-900 text-white rounded-xl font-bold text-xs hover:bg-slate-800 transition-all">
                        Search
                    </button>
                </form>
            </div>

            <!-- Babies Table -->
            <div class="table-container-premium min-h-[400px]">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Infant Details</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Maternal Contact</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Growth Progress</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Last Vaccine</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Next Due</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($babies)): ?>
                                <tr>
                                    <td colspan="6" class="px-8 py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300">
                                                <i class="fas fa-folder-open text-2xl"></i>
                                            </div>
                                            <p class="text-slate-400 font-bold text-sm uppercase tracking-widest">No matching records found</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($babies as $baby): 
                                    $dob = new DateTime($baby['birth_date']);
                                    $now = new DateTime();
                                    $age = $now->diff($dob);
                                    
                                    $ageString = "";
                                    if ($age->y > 0) $ageString .= $age->y . "y ";
                                    if ($age->m > 0) $ageString .= $age->m . "m ";
                                    if ($age->d > 0) $ageString .= $age->d . "d";
                                    if (empty($ageString)) $ageString = "Newborn";

                                    $statusClass = 'status-up-to-date';
                                    $statusLabel = 'Up to Date';
                                    $statusIcon = 'fa-check';

                                    if ($baby['next_due_date']) {
                                        $dueDate = new DateTime($baby['next_due_date']);
                                        if ($dueDate < $now) {
                                            $statusClass = 'status-overdue';
                                            $statusLabel = 'Overdue';
                                            $statusIcon = 'fa-exclamation-circle';
                                        } elseif ($dueDate <= (clone $now)->modify('+7 days')) {
                                            $statusClass = 'status-due';
                                            $statusLabel = 'Due Soon';
                                            $statusIcon = 'fa-clock';
                                        }
                                    }
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-health-100 group-hover:text-health-600 transition-colors">
                                                <i class="fas fa-baby"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-black text-slate-900 leading-tight">
                                                    <?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?>
                                                </div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo $baby['gender']; ?></span>
                                                    <span class="w-1 h-1 rounded-full bg-slate-200"></span>
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo date('M d, Y', strtotime($baby['birth_date'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-sm font-medium text-slate-600">
                                        <div class="font-bold text-slate-800"><?php echo htmlspecialchars($baby['mother_first_name'] . ' ' . $baby['mother_last_name']); ?></div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-tight mt-0.5"><?php echo htmlspecialchars($baby['mother_phone'] ?? 'No contact'); ?></div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="text-sm font-black text-slate-700"><?php echo $ageString; ?></div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-tight"><?php echo $baby['vaccine_count']; ?> doses total</div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <?php if ($baby['last_vaccine_date']): ?>
                                            <div class="text-sm font-bold text-slate-700"><?php echo date('M j, Y', strtotime($baby['last_vaccine_date'])); ?></div>
                                        <?php else: ?>
                                            <span class="text-[10px] font-black text-slate-300 uppercase italic">Not Started</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <?php if ($baby['next_due_date']): ?>
                                            <div class="status-badge <?php echo $statusClass; ?>">
                                                <i class="fas <?php echo $statusIcon; ?>"></i>
                                                <?php echo date('M j, Y', strtotime($baby['next_due_date'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">No Date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" class="view-history w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center hover:bg-health-600 hover:text-white transition-all active:scale-95" 
                                                    data-baby-id="<?php echo $baby['baby_id']; ?>" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#historyModal"
                                                    title="View History">
                                                <i class="fas fa-list-ul"></i>
                                            </button>
                                            <a href="forms/immunization_form.php?baby_id=<?php echo $baby['baby_id']; ?>" 
                                               class="w-9 h-9 rounded-xl bg-health-50 text-health-600 flex items-center justify-center hover:bg-health-600 hover:text-white transition-all active:scale-95" 
                                               title="Add Record">
                                                <i class="fas fa-plus"></i>
                                            </a>
                                            <a href="infant_growth_chart.php?baby_id=<?php echo $baby['baby_id']; ?>" 
                                               class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all active:scale-95" 
                                               title="Growth Chart">
                                                <i class="fas fa-chart-line"></i>
                                            </a>
                                        </div>
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
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">
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

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-[2rem] shadow-2xl overflow-hidden">
                <div class="modal-header bg-slate-50 border-b border-slate-100 p-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-health-100 rounded-2xl flex items-center justify-center text-health-600">
                            <i class="fas fa-history text-xl"></i>
                        </div>
                        <div>
                            <h5 class="text-xl font-black text-slate-900 leading-tight">Vaccination History</h5>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1 text-left">Detailed administration timeline</p>
                        </div>
                    </div>
                    <button type="button" class="w-10 h-10 rounded-xl hover:bg-slate-100 flex items-center justify-center text-slate-400 transition-colors" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body p-8">
                    <div id="historyContent" class="min-h-[200px] flex items-center justify-center">
                        <div class="flex flex-col items-center gap-4">
                            <div class="w-12 h-12 border-4 border-health-100 border-t-health-600 rounded-full animate-spin"></div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Retrieving history...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const historyModal = document.getElementById('historyModal');
            historyModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const babyId = button.getAttribute('data-baby-id');
                const contentDiv = document.getElementById('historyContent');
                
                contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div>';

                fetch('get_immunization_history.php?baby_id=' + babyId)
                    .then(response => response.text())
                    .then(html => {
                        contentDiv.innerHTML = html;
                    })
                    .catch(err => {
                        contentDiv.innerHTML = '<p class="text-danger">Error loading history.</p>';
                    });
            });
        });
    </script>
</body>
</html>
