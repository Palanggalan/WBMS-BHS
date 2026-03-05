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
        <div class="space-y-4" id="prenatalAccordion">
            <?php foreach ($records as $index => $record): ?>
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden group">
                <button class="w-full px-6 py-5 flex items-center justify-between hover:bg-slate-50 transition-colors text-left" 
                        type="button" data-bs-toggle="collapse" 
                        data-bs-target="#prenatal<?php echo $record['id']; ?>">
                    <div class="flex items-center gap-5">
                        <div class="flex flex-col items-center justify-center w-14 h-14 bg-slate-50 rounded-2xl border border-slate-100 group-hover:bg-health-50 group-hover:border-health-100 transition-colors">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter leading-none mb-1">Visit</span>
                            <span class="text-lg font-black text-slate-700 leading-none">#<?php echo $record['visit_number']; ?></span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800"><?php echo displayDate($record['visit_date']); ?></p>
                            <p class="text-[10px] font-bold text-health-600 uppercase tracking-widest mt-1">Gestational Age: <?php echo displayData($record['gestational_age']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <?php if ($index === 0): ?>
                            <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-widest rounded-full border border-emerald-100">Latest</span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down text-slate-300 text-xs transition-transform duration-300 group-aria-expanded:rotate-180"></i>
                    </div>
                </button>
                
                <div id="prenatal<?php echo $record['id']; ?>" class="collapse" data-bs-parent="#prenatalAccordion">
                    <div class="px-6 pb-6 pt-2 border-t border-slate-50">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Clinical Vitals -->
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-heartbeat text-health-600 text-xs"></i>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Vital Signs</span>
                                </div>
                                <div class="grid grid-cols-1 gap-3">
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Blood Pressure</span>
                                        <span class="text-sm font-black text-slate-800"><?php echo displayData($record['blood_pressure']); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Weight</span>
                                        <span class="text-sm font-black text-slate-800"><?php echo displayData($record['weight']); ?> kg</span>
                                    </div>
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Temperature</span>
                                        <span class="text-sm font-black text-slate-800"><?php echo displayData($record['temperature']); ?> °C</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Laboratory Results -->
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-microscope text-sky-500 text-xs"></i>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Lab Report</span>
                                </div>
                                <div class="grid grid-cols-1 gap-3">
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Hemoglobin</span>
                                        <span class="text-sm font-black text-slate-800"><?php echo displayData($record['hb_level']); ?> g/dL</span>
                                    </div>
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Blood Sugar</span>
                                        <span class="text-sm font-black text-slate-800"><?php echo displayData($record['blood_sugar']); ?> mg/dL</span>
                                    </div>
                                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Blood Group</span>
                                        <span class="text-sm font-black text-health-600"><?php echo displayData($record['blood_group']); ?> (<?php echo displayData($record['rhesus_factor']); ?>)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Screening & Meds -->
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-shield-virus text-amber-500 text-xs"></i>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Screening & Meds</span>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex flex-wrap gap-2">
                                        <?php if ($record['iron_supplement']): ?><span class="px-2 py-1 bg-amber-50 text-amber-600 text-[9px] font-black rounded-lg border border-amber-100">IRON</span><?php endif; ?>
                                        <?php if ($record['folic_acid']): ?><span class="px-2 py-1 bg-emerald-50 text-emerald-600 text-[9px] font-black rounded-lg border border-emerald-100">FOLIC</span><?php endif; ?>
                                        <?php if ($record['calcium']): ?><span class="px-2 py-1 bg-sky-50 text-sky-600 text-[9px] font-black rounded-lg border border-sky-100">CA++</span><?php endif; ?>
                                    </div>
                                    <div class="bg-white p-3 rounded-xl border border-slate-100 mt-2">
                                        <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Infectious Disease Screening</span>
                                        <div class="flex justify-between text-[11px] font-black">
                                            <span class="text-slate-500">HIV: <?php echo displayData($record['hiv_status']); ?></span>
                                            <span class="text-slate-500">HepB: <?php echo displayData($record['hepatitis_b']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Clinical Findings -->
                        <div class="mt-6 bg-slate-50 rounded-2xl p-5 border border-slate-100">
                             <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-clipboard-check text-indigo-500 text-xs"></i>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Provider Assessment & Notes</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tight">Clinical Findings</span>
                                    <p class="text-xs text-slate-700 font-medium mt-1 leading-relaxed"><?php echo displayData($record['findings'], 'No significant findings documented.'); ?></p>
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tight">Diagnosis & Treatment</span>
                                    <p class="text-xs text-slate-900 font-black mt-1 leading-relaxed"><?php echo displayData($record['diagnosis']); ?></p>
                                    <p class="text-[11px] text-health-600 font-bold mt-1 italic"><?php echo displayData($record['treatment'], 'N/A'); ?></p>
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
                                onclick="window.viewVisitDetails('prenatal', <?php echo $record['id']; ?>)"
                                class="flex items-center gap-2 px-5 py-2.5 bg-health-600 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-health-700 active:scale-95 transition-all shadow-lg shadow-health-200">
                                <i class="fas fa-file-medical"></i>
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