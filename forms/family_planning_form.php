<?php
// forms/family_planning_form.php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAuthorized(['admin', 'midwife'])) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';
$mothers = $pdo->query("SELECT id, first_name, last_name FROM mothers ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$methods = $pdo->query("SELECT * FROM family_planning_methods")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motherId = $_POST['mother_id'];
    $methodId = $_POST['method_id'];
    $regDate = $_POST['registration_date'];
    $nextDate = !empty($_POST['next_service_date']) ? $_POST['next_service_date'] : null;
    $remarks = $_POST['remarks'];
    $workerId = $_SESSION['user_id'];

    if ($nextDate && $regDate && strtotime($nextDate) <= strtotime($regDate)) {
        $error = "Next service date must be after the registration date.";
    } elseif (empty($motherId) || empty($methodId) || empty($regDate)) {
        $error = "Please fill in required fields.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO family_planning_records (mother_id, method_id, registration_date, next_service_date, remarks, health_worker_id) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$motherId, $methodId, $regDate, $nextDate, $remarks, $workerId])) {
            $message = "Record saved successfully!";
             echo "<script>setTimeout(function() { window.location.href = '../family_planning.php'; }, 1500);</script>";
        } else {
            $error = "Failed to save record.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Planning Registration | Kibenes eBirth</title>
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Components -->
    <?php include_once __DIR__ . '/../includes/tailwind_config.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style type="text/tailwindcss">
        @layer components {
            .section-header {
                @apply flex items-center gap-3 py-4 border-b border-slate-100 mb-6;
            }
            .section-icon {
                @apply w-10 h-10 bg-health-50 text-health-600 rounded-xl flex items-center justify-center text-lg;
            }
            .form-input-premium {
                @apply w-full px-4 py-3 rounded-2xl border border-slate-200 focus:border-health-500 focus:ring-4 focus:ring-health-500/10 outline-none transition-all duration-200 bg-white;
            }
            .form-label-premium {
                @apply block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1;
            }
            .card-premium {
                @apply bg-white rounded-[2rem] border border-slate-100 shadow-sm shadow-slate-200/50 p-8;
            }
            .info-box {
                @apply bg-slate-50 rounded-2xl p-6 border border-slate-100;
            }
        }
    </style>
</head>
<body class="bg-slate-50 font-inter text-slate-900 antialiased selection:bg-health-100 selection:text-health-700">
    <?php include_once '../includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 bg-health-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-health-200/50 text-2xl">
                        <i class="fas fa-venus"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Family Planning Registration</h1>
                        <p class="text-slate-500 font-medium mt-1">Professional reproductive health enrollment</p>
                    </div>
                </div>
                <a href="../family_planning.php" 
                   class="inline-flex items-center gap-2 px-6 py-3.5 bg-slate-50 text-slate-600 rounded-2xl font-bold transition-all hover:bg-slate-100 border border-slate-200 active:scale-95 text-xs uppercase tracking-widest">
                    <i class="fas fa-arrow-left"></i>
                    Back to Services
                </a>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 flex items-center gap-3 text-emerald-800 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center text-sm">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <p class="font-bold text-sm"><?php echo $message; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-100 rounded-2xl p-4 flex items-center gap-3 text-rose-800 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center text-sm">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p class="font-bold text-sm"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" id="fpForm" class="space-y-8">
                <!-- Registration Primary Details -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-check text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Client & Method Selection</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Primary registration parameters</p>
                        </div>
                    </div>

                    <?php 
                    $motherId = $_GET['mother_id'] ?? '';
                    $selectedMother = null;
                    if ($motherId) {
                        foreach ($mothers as $m) {
                            if ($m['id'] == $motherId) {
                                $selectedMother = $m;
                                break;
                            }
                        }
                    }
                    ?>

                    <?php if ($selectedMother): ?>
                        <div class="info-box flex flex-col md:flex-row items-start md:items-center gap-6 mb-8 mt-8">
                            <div class="w-16 h-16 bg-health-600 text-white rounded-2xl flex items-center justify-center text-2xl shadow-lg shadow-health-200">
                                <i class="fas fa-hospital-user"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-1">
                                    <p class="text-xs text-health-600 font-black uppercase tracking-[0.2em]">Verified Client Profile</p>
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                </div>
                                <h3 class="text-2xl font-black text-slate-900 tracking-tight">
                                    <?php echo htmlspecialchars($selectedMother['first_name'] . ' ' . $selectedMother['last_name']); ?>
                                </h3>
                                <div class="flex flex-wrap gap-4 mt-2">
                                    <div class="flex items-center gap-2 text-slate-500 font-bold text-xs uppercase tracking-widest">
                                        <i class="fas fa-fingerprint text-health-500"></i>
                                        Patient ID: WBMS-MTR-<?php echo str_pad($selectedMother['id'], 4, '0', STR_PAD_LEFT); ?>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-500 font-bold text-xs uppercase tracking-widest">
                                        <i class="fas fa-check-double text-emerald-500"></i>
                                        Identity Verified
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="mother_id" value="<?php echo $motherId; ?>">
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 <?php echo $selectedMother ? 'md:grid-cols-1' : 'md:grid-cols-2'; ?> gap-8 mt-8">
                        <?php if (!$selectedMother): ?>
                        <div>
                            <label for="mother_id" class="form-label-premium">Client Name (Mother) <span class="text-rose-500 font-black">*</span></label>
                            <div class="relative group">
                                <select name="mother_id" id="mother_id" class="form-input-premium appearance-none pr-12" required>
                                    <option value="">Select Mother Profile</option>
                                    <?php foreach ($mothers as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" <?php echo ($motherId == $m['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="mother_id_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Required field</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label for="method_id" class="form-label-premium">Contraceptive Method <span class="text-rose-500 font-black">*</span></label>
                            <div class="relative group">
                                <select name="method_id" id="method_id" class="form-input-premium appearance-none pr-12" required>
                                    <option value="">Select Method</option>
                                    <?php foreach ($methods as $method): ?>
                                        <option value="<?php echo $method['id']; ?>"><?php echo htmlspecialchars($method['method_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="method_id_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Required field</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Scheduling & Dates -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-calendar-alt text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Visit & Follow-up Scheduling</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Registration and routine timing</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                        <div>
                            <label for="registration_date" class="form-label-premium">Registration Date <span class="text-rose-500 font-black">*</span></label>
                            <div class="relative group">
                                <i class="fas fa-calendar-plus absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-health-500 transition-colors"></i>
                                <input type="date" name="registration_date" id="registration_date" class="form-input-premium pl-12 font-black text-health-700" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="registration_date_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Required field</span>
                            </div>
                        </div>
                        <div>
                            <label for="next_service_date" class="form-label-premium flex items-center gap-2">
                                Next Follow-up Date
                                <span class="text-[10px] bg-slate-100 text-slate-400 px-2 py-0.5 rounded-full font-black uppercase">Optional</span>
                            </label>
                            <div class="relative group">
                                <i class="fas fa-calendar-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-sky-500 transition-colors"></i>
                                <input type="date" name="next_service_date" id="next_service_date" class="form-input-premium pl-12 border-sky-50 focus:border-sky-500 focus:ring-sky-500/10">
                            </div>
                            <div class="hidden mt-2 p-3 bg-rose-50 text-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl border border-rose-100 items-center gap-2" id="next_service_date_warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Must be after registration date</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Additional Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-clipboard-list text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Clinical Remarks & Observations</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Patient history or provider notes</p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <textarea name="remarks" id="remarks" class="form-input-premium min-h-[160px] py-5 px-6 leading-relaxed" placeholder="Enter clinical notes, patient preferences, method specific observations, or historical relevant data..."></textarea>
                    </div>
                </section>

                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8">
                    <button type="button" onclick="window.history.back()" 
                            class="w-full sm:w-auto px-10 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-[10px] hover:bg-slate-200 transition-all active:scale-95">
                        Cancel Registration
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-16 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] hover:bg-health-700 shadow-xl shadow-health-200/50 transition-all active:scale-95 flex items-center justify-center gap-3">
                        <i class="fas fa-save shadow-sm text-sm"></i>
                        Save Family Planning Record
                    </button>
                </div>
            </form>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('fpForm');
            const regDateInput = document.getElementById('registration_date');
            const nextDateInput = document.getElementById('next_service_date');

            // Real-time validation for required fields
            const requiredFields = ['mother_id', 'method_id', 'registration_date'];
            
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
                const regDate = new Date(regDateInput.value);
                const nextDate = nextDateInput.value ? new Date(nextDateInput.value) : null;
                
                if (nextDate && nextDate <= regDate) {
                    showWarning('next_service_date');
                    return false;
                } else {
                    hideWarning('next_service_date');
                    return true;
                }
            }

            regDateInput.addEventListener('change', validateDates);
            nextDateInput.addEventListener('change', validateDates);

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
