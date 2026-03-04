<?php
// forms/immunization_form.php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';
$babyId = $_GET['baby_id'] ?? '';
$vaccines = [];

// Fetch Vaccines
$stmt = $pdo->query("SELECT * FROM vaccines ORDER BY recommended_age_weeks ASC");
$vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Babies
$babies = $pdo->query("SELECT id, first_name, last_name, birth_date FROM birth_records ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $babyId = $_POST['baby_id'];
    $vaccineId = $_POST['vaccine_id'];
    $doseNumber = $_POST['dose_number'];
    $dateGiven = $_POST['date_given'];
    $nextDueDate = !empty($_POST['next_due_date']) ? $_POST['next_due_date'] : null;
    $remarks = $_POST['remarks'];
    $recordedBy = $_SESSION['user_id'];

    if ($nextDueDate && $dateGiven && strtotime($nextDueDate) <= strtotime($dateGiven)) {
        $error = "Next due date must be after the date given.";
    } elseif (empty($babyId) || empty($vaccineId) || empty($dateGiven)) {
        $error = "Please fill in all required fields.";
    } else {
        $sql = "INSERT INTO immunization_records (baby_id, vaccine_id, dose_number, date_given, next_dose_date, remarks, health_worker_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$babyId, $vaccineId, $doseNumber, $dateGiven, $nextDueDate, $remarks, $recordedBy])) {
            $message = "Immunization record added successfully!";
            // Redirect to list after success
            echo "<script>
                setTimeout(function() {
                    window.location.href = '../immunization_records.php';
                }, 1500);
            </script>";
        } else {
            $error = "Failed to add record.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Immunization | Kibenes eBirth</title>
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Components -->
    <?php include_once __DIR__ . '/../includes/tailwind_config.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-slate-50 font-inter text-slate-900 antialiased selection:bg-health-100 selection:text-health-700">
    <?php include_once '../includes/header.php'; ?>

    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <header class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 bg-health-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-health-200/50 text-2xl">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Record Immunization</h1>
                        <p class="text-slate-500 font-medium mt-1">New vaccination administration entry</p>
                    </div>
                </div>
                <a href="../immunization_records.php" 
                   class="inline-flex items-center gap-2 px-6 py-3.5 bg-slate-50 text-slate-600 rounded-2xl font-bold transition-all hover:bg-slate-100 border border-slate-200 active:scale-95 text-sm">
                    <i class="fas fa-arrow-left"></i>
                    Back to Records
                </a>
            </header>

            <?php if ($message): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center gap-4 text-emerald-700 font-bold animate-in fade-in slide-in-from-top-4 duration-500">
                    <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-4 bg-rose-50 border border-rose-100 rounded-2xl flex items-center gap-4 text-rose-700 font-bold animate-in fade-in slide-in-from-top-4 duration-500">
                    <i class="fas fa-exclamation-circle text-rose-500 text-xl"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="immunizationForm" class="space-y-8">
                <!-- Patient & Vaccine Selection -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-baby text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Primary Information</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Infant and vaccine identification</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                        <div>
                            <label for="baby_id" class="form-label-premium">Infant Profile <span class="text-rose-500 font-black">*</span></label>
                            <div class="relative group">
                                <select name="baby_id" id="baby_id" class="form-input-premium appearance-none pr-12" required>
                                    <option value="">Select an infant</option>
                                    <?php foreach ($babies as $baby): ?>
                                        <option value="<?php echo $baby['id']; ?>" <?php echo ($babyId == $baby['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($baby['last_name'] . ', ' . $baby['first_name']); ?>
                                            (Born: <?php echo date('M d, Y', strtotime($baby['birth_date'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="baby_id_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Please select an infant profile</span>
                            </div>
                        </div>

                        <div>
                            <label for="vaccine_id" class="form-label-premium">Vaccine Type <span class="text-rose-500 font-black">*</span></label>
                            <div class="relative group">
                                <select name="vaccine_id" id="vaccine_id" class="form-input-premium appearance-none pr-12" required>
                                    <option value="">Select vaccine</option>
                                    <?php foreach ($vaccines as $vac): ?>
                                        <option value="<?php echo $vac['id']; ?>">
                                            <?php echo htmlspecialchars($vac['vaccine_name']); ?> 
                                            (Target: <?php echo $vac['recommended_age_weeks']; ?> weeks)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="vaccine_id_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Please select a vaccine type</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Dose Administration Details -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-vial text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Administration Details</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Dose timing and scheduling</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-8">
                        <div>
                            <label for="dose_number" class="form-label-premium">Dose Number <span class="text-rose-500 font-black">*</span></label>
                            <div class="relative group">
                                <i class="fas fa-hashtag absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-health-500 transition-colors"></i>
                                <input type="number" name="dose_number" id="dose_number" class="form-input-premium pl-12 font-bold" value="1" min="1" required>
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="dose_number_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Required</span>
                            </div>
                        </div>

                        <div>
                            <label for="date_given" class="form-label-premium">Date Administered <span class="text-rose-500 font-black">*</span></label>
                            <div class="relative group">
                                <i class="fas fa-calendar-day absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-health-500 transition-colors"></i>
                                <input type="date" name="date_given" id="date_given" class="form-input-premium pl-12 font-black text-health-700" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="date_given_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Required</span>
                            </div>
                        </div>

                        <div>
                            <label for="next_due_date" class="form-label-premium flex items-center gap-2">
                                Next Dose Due 
                                <span class="text-[9px] bg-slate-100 text-slate-400 px-2 py-0.5 rounded-full font-black uppercase tracking-tighter">Optional</span>
                            </label>
                            <div class="relative group">
                                <i class="fas fa-calendar-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-emerald-500 transition-colors"></i>
                                <input type="date" name="next_due_date" id="next_due_date" class="form-input-premium pl-12 border-emerald-50 focus:border-emerald-500 focus:ring-emerald-500/10">
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="next_due_date_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Must be after administration date</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Additional Notes -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-comment-medical text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Clinical Remarks</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Notes on reaction or observations</p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <textarea name="remarks" id="remarks" class="form-input-premium min-h-[140px] py-5 px-6 leading-relaxed" placeholder="Record any reactions, side effects, patient response or special instructions here..."></textarea>
                    </div>
                </section>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8 pb-12">
                    <button type="button" onclick="window.history.back()" 
                            class="w-full sm:w-auto px-10 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-[10px] hover:bg-slate-200 transition-all active:scale-95">
                        Discard Changes
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-16 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] hover:bg-health-700 shadow-xl shadow-health-200/50 transition-all active:scale-95 flex items-center justify-center gap-3">
                        <i class="fas fa-save shadow-sm text-sm"></i>
                        Save Immunization Record
                    </button>
                </div>
            </form>
            </form>
        </main>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('immunizationForm');
            const dateGivenInput = document.getElementById('date_given');
            const nextDueDateInput = document.getElementById('next_due_date');

            // Real-time validation for required fields
            const requiredFields = ['baby_id', 'vaccine_id', 'dose_number', 'date_given'];
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field) return;

                field.addEventListener('blur', function() {
                    validateField(this);
                });
                
                field.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        hideWarning(this.id);
                    }
                });
            });

            function validateField(field) {
                if (field.value.trim() === '') {
                    showWarning(field.id);
                    return false;
                }
                hideWarning(field.id);
                return true;
            }

            function showWarning(fieldId) {
                const warning = document.getElementById(fieldId + '_warning');
                const field = document.getElementById(fieldId);
                if (warning) warning.classList.remove('hidden');
                if (field) {
                    field.classList.add('border-rose-500', 'ring-2', 'ring-rose-200');
                    field.classList.add('animate-shake');
                    setTimeout(() => field.classList.remove('animate-shake'), 500);
                }
            }

            function hideWarning(fieldId) {
                const warning = document.getElementById(fieldId + '_warning');
                const field = document.getElementById(fieldId);
                if (warning) warning.classList.add('hidden');
                if (field) field.classList.remove('border-rose-500', 'ring-2', 'ring-rose-200');
            }

            function validateDates() {
                const dateGiven = new Date(dateGivenInput.value);
                const nextDate = nextDueDateInput.value ? new Date(nextDueDateInput.value) : null;
                
                if (nextDate && nextDate <= dateGiven) {
                    showWarning('next_due_date');
                    return false;
                } else {
                    hideWarning('next_due_date');
                    return true;
                }
            }

            dateGivenInput.addEventListener('change', validateDates);
            nextDueDateInput.addEventListener('change', validateDates);

            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !validateField(field)) {
                        isValid = false;
                    }
                });
                
                if (!validateDates()) {
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    
                    // Find first error and scroll
                    const firstError = document.querySelector('[id$="_warning"]:not(.hidden)');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        const errorField = document.getElementById(firstError.id.replace('_warning', ''));
                        if (errorField) {
                            errorField.classList.add('animate-pulse');
                            setTimeout(() => errorField.classList.remove('animate-pulse'), 1000);
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
