<?php
require_once dirname(__FILE__) . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAuthorized(['mother'])) {
    header('HTTP/1.1 403 Forbidden');
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

global $pdo;

$userId = $_SESSION['user_id'];

// Get mother's ID
$motherStmt = $pdo->prepare("SELECT id FROM mothers WHERE user_id = ?");
$motherStmt->execute([$userId]);
$mother = $motherStmt->fetch(PDO::FETCH_ASSOC);

if (!$mother) {
    echo '<div class="alert alert-danger">Mother profile not found</div>';
    exit();
}

// FIXED QUERY - Only join with the latest pregnancy details to avoid duplicates
$prenatalRecords = $pdo->prepare("
    SELECT 
        pr.*,
        m.first_name as mother_first_name,
        m.last_name as mother_last_name,
        latest_pd.lmp,
        latest_pd.edc,
        latest_pd.gravida,
        latest_pd.para
    FROM prenatal_records pr
    JOIN mothers m ON pr.mother_id = m.id
    LEFT JOIN (
        SELECT mother_id, lmp, edc, gravida, para
        FROM pregnancy_details
        WHERE (mother_id, created_at) IN (
            SELECT mother_id, MAX(created_at)
            FROM pregnancy_details
            GROUP BY mother_id
        )
    ) latest_pd ON pr.mother_id = latest_pd.mother_id
    WHERE pr.mother_id = ? 
    ORDER BY pr.visit_date DESC, pr.visit_number DESC
");
$prenatalRecords->execute([$mother['id']]);
$records = $prenatalRecords->fetchAll(PDO::FETCH_ASSOC);

function displayData($value, $default = '—') {
    return !empty($value) && $value != '0000-00-00' ? htmlspecialchars($value) : $default;
}

function displayDate($date, $format = 'M d, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}
?>

<div class="modal-header border-b border-slate-100 px-8 py-6 bg-white shrink-0">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-health-50 text-health-600 rounded-2xl flex items-center justify-center text-xl shadow-sm">
            <i class="fas fa-file-medical"></i>
        </div>
        <div>
            <h5 class="text-xl font-black text-slate-900 tracking-tight">Prenatal Health History</h5>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-0.5"><?php echo count($records); ?> clinical visits documented</p>
        </div>
    </div>
    <button type="button" class="w-10 h-10 rounded-xl hover:bg-slate-50 flex items-center justify-center text-slate-400 transition-colors" data-bs-dismiss="modal">
        <i class="fas fa-times"></i>
    </button>
</div>

<div class="modal-body p-8 bg-slate-50/30" style="max-height: 70vh; overflow-y: auto;">
    <?php if (!empty($records)): ?>
        <div class="space-y-3">
            <?php foreach ($records as $index => $record): ?>
            <button
                onclick="window.viewVisitDetails('prenatal', <?php echo $record['id']; ?>)"
                class="w-full bg-white hover:bg-health-50 border border-slate-100 hover:border-health-200 rounded-3xl px-6 py-5 flex items-center justify-between group transition-all duration-200 hover:shadow-lg hover:shadow-health-100/40 text-left active:scale-[0.99]">
                <div class="flex items-center gap-5">
                    <div class="flex flex-col items-center justify-center w-14 h-14 bg-slate-50 group-hover:bg-health-100 rounded-2xl border border-slate-100 group-hover:border-health-200 transition-colors shrink-0">
                        <span class="text-[10px] font-black text-slate-400 group-hover:text-health-600 uppercase tracking-tighter leading-none mb-1">Visit</span>
                        <span class="text-lg font-black text-slate-700 group-hover:text-health-700 leading-none">#<?php echo $record['visit_number']; ?></span>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-bold text-slate-800"><?php echo displayDate($record['visit_date']); ?></p>
                        <p class="text-[10px] font-bold text-health-600 uppercase tracking-widest mt-1">AOG: <?php echo displayData($record['gestational_age']); ?> &nbsp;·&nbsp; BP: <?php echo displayData($record['blood_pressure']); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <?php if ($index === 0): ?>
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-widest rounded-full border border-emerald-100">Latest</span>
                    <?php endif; ?>
                    <div class="w-9 h-9 rounded-xl bg-health-50 group-hover:bg-health-600 flex items-center justify-center transition-all">
                        <i class="fas fa-arrow-right text-health-600 group-hover:text-white text-xs transition-colors"></i>
                    </div>
                </div>
            </button>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-20 bg-white rounded-[2rem] border border-slate-100 border-dashed">
            <div class="w-20 h-20 bg-slate-50 text-slate-200 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                <i class="fas fa-folder-open"></i>
            </div>
            <h6 class="text-slate-400 font-black uppercase tracking-widest">No Records Found</h6>
            <p class="text-xs text-slate-300 mt-2 font-medium">Your prenatal health records will appear here as they are filed.</p>
        </div>
    <?php endif; ?>
</div>

<div class="modal-footer px-8 py-5 border-t border-slate-100 bg-white shrink-0">
    <button type="button" class="px-6 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-slate-200 transition-all" data-bs-dismiss="modal">Close History</button>
</div>

<style>
.modal-body::-webkit-scrollbar { width: 4px; }
.modal-body::-webkit-scrollbar-track { background: transparent; }
.modal-body::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>

<script>
function printPrenatalRecords() {
    const modalContent = document.querySelector('#prenatalModal .modal-content').cloneNode(true);
    const printWindow = window.open('', '_blank');
    
    // Remove buttons and make accordion expanded
    const footer = modalContent.querySelector('.modal-footer');
    if (footer) footer.remove();
    
    // Expand all accordion items for printing
    const accordionItems = modalContent.querySelectorAll('.accordion-collapse');
    accordionItems.forEach(item => {
        item.classList.add('show');
    });
    
    // Remove accordion toggle functionality for print
    const accordionButtons = modalContent.querySelectorAll('.accordion-button');
    accordionButtons.forEach(button => {
        button.classList.remove('collapsed');
        button.setAttribute('aria-expanded', 'true');
    });
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>My Prenatal Records</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 15px; font-size: 12px; }
                .accordion-item { margin-bottom: 10px; border: 1px solid #dee2e6 !important; }
                .accordion-button { display: none !important; }
                .accordion-collapse { display: block !important; }
                .badge { font-size: 0.7rem; }
                @media print {
                    .no-print { display: none; }
                    body { font-size: 11px; }
                }
            </style>
        </head>
        <body>
            <h5 class="text-center mb-3">My Prenatal Records</h5>
            <p class="text-center text-muted small mb-4">Generated on: ${new Date().toLocaleDateString()}</p>
            ${modalContent.innerHTML}
            <div class="no-print text-center mt-4">
                <button onclick="window.print()" class="btn btn-sm btn-primary me-2">Print</button>
                <button onclick="window.close()" class="btn btn-sm btn-secondary">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}
</script>