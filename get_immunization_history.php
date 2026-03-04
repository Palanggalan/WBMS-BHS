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
<div class="flex items-center gap-4 p-5 bg-slate-50 border-b border-slate-100">
    <div class="w-12 h-12 bg-health-100 rounded-xl flex items-center justify-center text-health-600 border border-health-200/30">
        <i class="fas fa-baby text-lg"></i>
    </div>
    <div class="flex-1">
        <div class="flex items-center justify-between w-full">
            <h4 class="text-xl font-black text-slate-900 tracking-tight">
                <?php echo htmlspecialchars($baby['first_name'] . ' ' . $baby['last_name']); ?>
            </h4>
            <span class="text-[9px] font-black text-health-600 uppercase tracking-widest bg-health-50 px-2 py-1 rounded border border-health-100">
                Age: <?php echo $ageString; ?>
            </span>
        </div>
        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">
            DOB: <?php echo date('M d, Y', strtotime($baby['birth_date'])); ?>
        </div>
    </div>
</div>

<!-- Timeline Table -->
<div class="p-6">
    <div class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-5 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Vaccine & Dose</th>
                        <th class="px-5 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Date</th>
                        <th class="px-5 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Next Due</th>
                        <th class="px-5 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Provider</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <i class="fas fa-syringe text-slate-200"></i>
                                <p class="text-[9px] font-bold text-slate-300 uppercase tracking-widest">No history recorded</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $record): ?>
                    <tr class="hover:bg-slate-50/50 transition-all">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-lg bg-health-50 text-health-600 flex items-center justify-center text-[10px] font-black border border-health-100">
                                    D<?php echo $record['dose_number']; ?>
                                </div>
                                <div>
                                    <div class="text-xs font-black text-slate-900 leading-tight">
                                        <?php echo htmlspecialchars($record['vaccine_name']); ?>
                                    </div>
                                    <div class="text-[8px] font-bold text-slate-400 uppercase italic">
                                        <?php echo htmlspecialchars($record['remarks'] ?: 'Standard Admin'); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <div class="text-[11px] font-bold text-slate-700">
                                <?php echo date('M j, Y', strtotime($record['date_given'])); ?>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php if ($record['next_dose_date']): ?>
                                <span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-[9px] font-black rounded border border-amber-100 uppercase tracking-tighter">
                                    <?php echo date('M j, Y', strtotime($record['next_dose_date'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[9px] font-bold text-slate-300 uppercase italic">Complete</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="text-[9px] font-black text-slate-600 uppercase">
                                <?php echo htmlspecialchars($record['worker_first'] . ' ' . $record['worker_last']); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
