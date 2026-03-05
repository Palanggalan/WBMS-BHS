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

// Get comprehensive postnatal records - ALL FIELDS RETAINED
$postnatalRecords = $pdo->prepare("
    SELECT 
        pn.*,
        b.first_name as baby_first_name,
        b.last_name as baby_last_name,
        b.birth_date as baby_birth_date,
        b.gender as baby_gender
    FROM postnatal_records pn
    JOIN birth_records b ON pn.baby_id = b.id
    WHERE pn.mother_id = ?
    ORDER BY pn.visit_date DESC, pn.visit_number DESC
");
$postnatalRecords->execute([$mother['id']]);
$records = $postnatalRecords->fetchAll(PDO::FETCH_ASSOC);

function displayData($value, $default = '—') {
    return !empty($value) && $value != '0000-00-00' ? htmlspecialchars($value) : $default;
}

function displayDate($date, $format = 'M d, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function calculateBabyAge($birthDate) {
    if (empty($birthDate) || $birthDate == '0000-00-00') return 'N/A';
    
    $birth = new DateTime($birthDate);
    $now = new DateTime();
    $interval = $birth->diff($now);
    
    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
    } else {
        return $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    }
}
?>

<div class="modal-header border-b border-slate-100 px-8 py-6 bg-white shrink-0">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-sm">
            <i class="fas fa-baby-carriage"></i>
        </div>
        <div>
            <h5 class="text-xl font-black text-slate-900 tracking-tight">Postnatal Care Records</h5>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-0.5"><?php echo count($records); ?> routine checks documented</p>
        </div>
    </div>
    <button type="button" class="w-10 h-10 rounded-xl hover:bg-slate-50 flex items-center justify-center text-slate-400 transition-colors" data-bs-dismiss="modal">
        <i class="fas fa-times"></i>
    </button>
</div>

<div class="modal-body p-8 bg-slate-50/30" style="max-height: 71vh; overflow-y: auto;">
    <?php if (!empty($records)): ?>
        <div class="space-y-4" id="postnatalAccordion">
            <?php foreach ($records as $index => $record): ?>
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden group">
                <button class="w-full px-6 py-5 flex items-center justify-between hover:bg-slate-50 transition-colors text-left" 
                        type="button" data-bs-toggle="collapse" 
                        data-bs-target="#postnatal<?php echo $record['id']; ?>">
                    <div class="flex items-center gap-5">
                        <div class="flex flex-col items-center justify-center w-14 h-14 bg-slate-50 rounded-2xl border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter leading-none mb-1 text-center">Visit</span>
                            <span class="text-lg font-black text-slate-700 leading-none">#<?php echo $record['visit_number']; ?></span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800"><?php echo displayDate($record['visit_date']); ?></p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest">Baby: <?php echo htmlspecialchars($record['baby_first_name']); ?></span>
                                <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Age: <?php echo calculateBabyAge($record['baby_birth_date']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <?php if ($index === 0): ?>
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase tracking-widest rounded-full">Latest</span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down text-slate-300 text-xs transition-transform duration-300 group-aria-expanded:rotate-180"></i>
                    </div>
                </button>
                
                <div id="postnatal<?php echo $record['id']; ?>" class="collapse" data-bs-parent="#postnatalAccordion">
                    <div class="px-6 pb-6 pt-2 border-t border-slate-50">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Mother's Vitals -->
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-user-nurse text-emerald-600 text-xs"></i>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Mother's Status</span>
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500">BP</span>
                                        <span class="text-xs font-black text-slate-800"><?php echo displayData($record['blood_pressure']); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500">Weight</span>
                                        <span class="text-xs font-black text-slate-800"><?php echo displayData($record['weight']); ?> kg</span>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-xl border border-slate-100 mt-2">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Postpartum Check</span>
                                    <div class="space-y-1">
                                        <p class="text-[11px] font-bold text-slate-700 flex justify-between">Uterus: <span class="text-emerald-600"><?php echo displayData($record['uterus_status']); ?></span></p>
                                        <p class="text-[11px] font-bold text-slate-700 flex justify-between">Lochia: <span class="text-emerald-600"><?php echo displayData($record['lochia_status']); ?></span></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Baby's Health -->
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-baby text-sky-500 text-xs"></i>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Baby's Status</span>
                                </div>
                                <div class="grid grid-cols-1 gap-2">
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500">Weight</span>
                                        <span class="text-xs font-black text-slate-800"><?php echo displayData($record['baby_weight']); ?> kg</span>
                                    </div>
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500">Feeding</span>
                                        <span class="text-xs font-black text-sky-600 lowercase"><?php echo displayData($record['feeding_method']); ?></span>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-xl border border-slate-100 mt-2">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Health Observations</span>
                                    <p class="text-[11px] text-slate-600 italic"><?php echo displayData($record['baby_issues'], 'No issues documented.'); ?></p>
                                </div>
                            </div>

                            <!-- Care & Counseling -->
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-comment-medical text-amber-500 text-xs"></i>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Counseling & Referral</span>
                                </div>
                                <div class="space-y-3">
                                    <div class="bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Counselling Topics</span>
                                        <p class="text-[11px] font-bold text-slate-700"><?php echo displayData($record['counseling_topics'], 'General postpartum care'); ?></p>
                                    </div>
                                    <?php if ($record['referral_needed']): ?>
                                    <div class="bg-rose-50 p-3 rounded-xl border border-rose-100">
                                        <span class="text-[9px] font-black text-rose-600 uppercase block mb-1 tracking-widest">Urgent Referral Needed</span>
                                        <p class="text-[11px] text-rose-800 font-bold"><?php echo displayData($record['referral_details']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Provider Recommendations -->
                        <div class="mt-6 bg-slate-50 rounded-2xl p-5 border border-slate-100">
                             <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-stethoscope text-indigo-500 text-xs"></i>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Provider Recommendations</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tight">Mother's Treatment</span>
                                    <p class="text-xs text-slate-900 font-black mt-1 leading-relaxed"><?php echo displayData($record['treatment'], 'No specific treatment prescribed.'); ?></p>
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tight">Baby's Treatment</span>
                                    <p class="text-xs text-slate-900 font-black mt-1 leading-relaxed"><?php echo displayData($record['baby_treatment'], 'No specific treatment prescribed.'); ?></p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($record['next_visit_date'])): ?>
                        <div class="mt-4 flex items-center justify-center py-3 bg-emerald-50 rounded-xl border border-emerald-100">
                            <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-calendar-check text-xs"></i>
                                Next Appointment: <?php echo displayDate($record['next_visit_date']); ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <div class="mt-4 flex justify-end">
                            <button
                                onclick="window.viewVisitDetails('postnatal', <?php echo $record['id']; ?>)"
                                class="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-emerald-700 active:scale-95 transition-all shadow-lg shadow-emerald-200">
                                <i class="fas fa-file-prescription"></i>
                                View Full Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-20 bg-white rounded-[2rem] border border-slate-100 border-dashed">
            <div class="w-20 h-20 bg-slate-50 text-slate-200 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                <i class="fas fa-baby-carriage"></i>
            </div>
            <h6 class="text-slate-400 font-black uppercase tracking-widest">No Records Found</h6>
            <p class="text-xs text-slate-300 mt-2 font-medium">Postnatal checkup records will appear here after your clinical visits.</p>
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
function printPostnatalRecords() {
    const modalContent = document.querySelector('#postnatalModal .modal-content').cloneNode(true);
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
            <title>My Postnatal Records</title>
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
            <h5 class="text-center mb-3">My Postnatal Records</h5>
            <p class="text-center text-muted small mb-4">Generated on: ${new Date().toLocaleDateString()}</p>
            ${modalContent.innerHTML}
            <div class="no-print text-center mt-4">
                <button onclick="window.print()" class="btn btn-sm btn-success me-2">Print</button>
                <button onclick="window.close()" class="btn btn-sm btn-secondary">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}
</script>