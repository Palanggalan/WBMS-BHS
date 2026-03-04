<?php
// get_immunization_history.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    echo "Unauthorized access";
    exit();
}

if (!isset($_GET['baby_id'])) {
    echo "No baby ID provided";
    exit();
}

$babyId = intval($_GET['baby_id']);

// Get Baby Details
$stmt = $pdo->prepare("SELECT first_name, last_name, birth_date FROM birth_records WHERE id = ?");
$stmt->execute([$babyId]);
$baby = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$baby) {
    echo "Baby not found";
    exit();
}

// Get Immunization Records
$stmt = $pdo->prepare("
    SELECT ir.*, v.vaccine_name, u.first_name as worker_first, u.last_name as worker_last
    FROM immunization_records ir
    JOIN vaccines v ON ir.vaccine_id = v.id
    LEFT JOIN users u ON ir.health_worker_id = u.id
    WHERE ir.baby_id = ?
    ORDER BY ir.date_given DESC
");
$stmt->execute([$babyId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Age
$dob = new DateTime($baby['birth_date']);
$now = new DateTime();
$age = $now->diff($dob);
$ageString = $age->y . "y " . $age->m . "m " . $age->d . "d";
?>

<!-- Patient Header Summary -->
<div class="flex items-center gap-6 p-6 mb-8 bg-slate-50/50 rounded-3xl border border-slate-100">
    <div class="w-16 h-16 bg-health-100 rounded-2xl flex items-center justify-center text-health-600 shadow-sm border border-health-200/50">
        <i class="fas fa-baby text-2xl"></i>
    </div>
    <div>
        <h4 class="text-2xl font-black text-slate-900 tracking-tight mb-1">
            <?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?>
        </h4>
        <div class="flex items-center gap-3">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-white px-2 py-0.5 rounded border border-slate-100">
                DOB: <?php echo date('M d, Y', strtotime($baby['birth_date'])); ?>
            </span>
            <span class="w-1 h-1 rounded-full bg-slate-200"></span>
            <span class="text-[10px] font-black text-health-600 uppercase tracking-widest bg-health-50 px-2 py-0.5 rounded border border-health-100">
                Age: <?php echo $ageString; ?>
            </span>
        </div>
    </div>
</div>

<!-- Timeline Table -->
<div class="relative overflow-hidden rounded-[2rem] border border-slate-100 bg-white">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Vaccine & Dose</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date Administered</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Next Due</th>
                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Provider</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-200">
                                    <i class="fas fa-syringe"></i>
                                </div>
                                <p class="text-[10px] font-bold text-slate-300 uppercase tracking-widest">No records yet</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $record): ?>
                    <tr class="hover:bg-slate-50/50 transition-all group">
                        <td class="px-6 py-5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-health-50 text-health-600 flex items-center justify-center text-xs font-bold border border-health-100">
                                    D<?php echo $record['dose_number']; ?>
                                </div>
                                <div>
                                    <div class="text-xs font-black text-slate-900 group-hover:text-health-700 transition-colors">
                                        <?php echo htmlspecialchars($record['vaccine_name']); ?>
                                    </div>
                                    <div class="text-[9px] font-bold text-slate-400 uppercase mt-0.5 leading-none italic">
                                        <?php echo htmlspecialchars($record['remarks'] ?? 'Normal Administration'); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            <div class="text-xs font-bold text-slate-700">
                                <?php echo date('M j, Y', strtotime($record['date_given'])); ?>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            <?php if ($record['next_dose_date']): ?>
                                <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-amber-50 rounded-lg border border-amber-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                    <span class="text-[10px] font-bold text-amber-700 uppercase tracking-tighter">
                                        <?php echo date('M j, Y', strtotime($record['next_dose_date'])); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <span class="text-[10px] font-bold text-slate-300 uppercase italic">Series Complete</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-5 text-right">
                            <div class="text-[10px] font-black text-slate-600 uppercase tracking-tight">
                                <?php echo htmlspecialchars($record['worker_first'] . ' ' . $record['worker_last']); ?>
                            </div>
                            <div class="text-[8px] font-bold text-slate-400 uppercase mt-0.5">Administered By</div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
