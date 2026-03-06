<?php
require_once dirname(__FILE__) . '/../config/config.php';

// Check if user can register their own profile
if (!canRegisterOwnProfile()) {
    header("Location: ../login.php");
    exit();
}

redirectIfNotLoggedIn();

// Check if mother profile already exists
if (hasMotherProfile($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit();
}

global $pdo;

$message = '';
$error = '';

// Address options for Carmen, Cotabato
$addressOptions = [
    'Proper 1, Kibenes, Carmen, Cotabato',
    'Proper 2, Kibenes, Carmen, Cotabato',
    'Takpan, Carmen, Cotabato',
    'Kupayan, Carmen, Cotabato',
    'Kilaba, Carmen, Cotabato',
    'Baingkungan, Carmen, Cotabato',
    'Butuan, Carmen, Cotabato',
    'Sambayangan, Carmen, Cotabato',
    'Village, Carmen, Cotabato'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Personal Information
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $civilStatus = $_POST['civil_status'] ?? '';
    $nationality = trim($_POST['nationality'] ?? '');
    $religion = trim($_POST['religion'] ?? '');
    $education = $_POST['education'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    
    // Contact Information
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergencyContact = trim($_POST['emergency_contact'] ?? '');
    $emergencyPhone = trim($_POST['emergency_phone'] ?? '');
    
    // Medical Information
    $bloodType = $_POST['blood_type'] ?? '';
    $rhFactor = $_POST['rh_factor'] ?? '';
    
    // Husband/Father Information
    $husbandFirstName = trim($_POST['husband_first_name'] ?? '');
    $husbandMiddleName = trim($_POST['husband_middle_name'] ?? '');
    $husbandLastName = trim($_POST['husband_last_name'] ?? '');
    $husbandDateOfBirth = $_POST['husband_date_of_birth'] ?? '';
    $husbandOccupation = trim($_POST['husband_occupation'] ?? '');
    $husbandEducation = $_POST['husband_education'] ?? '';
    $husbandPhone = trim($_POST['husband_phone'] ?? '');
    $husbandCitizenship = trim($_POST['husband_citizenship'] ?? 'Filipino');
    $husbandReligion = trim($_POST['husband_religion'] ?? '');
    $marriageDate = $_POST['marriage_date'] ?? '';
    $marriagePlace = trim($_POST['marriage_place'] ?? '');
    
    // Pregnancy Information
    $lmp = $_POST['lmp'] ?? '';
    $gravida = $_POST['gravida'] ?? '';
    $para = $_POST['para'] ?? '';
    $abortions = $_POST['abortions'] ?? 0;
    $livingChildren = $_POST['living_children'] ?? '';
    $plannedPregnancy = $_POST['planned_pregnancy'] ?? '';
    $firstPrenatalVisit = $_POST['first_prenatal_visit'] ?? '';
    $referredBy = trim($_POST['referred_by'] ?? '');
    
    // Medical History Information
    $allergies = trim($_POST['allergies'] ?? '');
    $medicalConditions = trim($_POST['medical_conditions'] ?? '');
    $previousSurgeries = trim($_POST['previous_surgeries'] ?? '');
    $familyHistory = trim($_POST['family_history'] ?? '');
    $contraceptiveUse = trim($_POST['contraceptive_use'] ?? '');
    $previousComplications = trim($_POST['previous_complications'] ?? '');
    
    // Calculate EDC based on LMP (40 weeks)
    $edc = !empty($lmp) ? date('Y-m-d', strtotime($lmp . ' + 280 days')) : '';
    
    $userId = $_SESSION['user_id'];
    $registeredBy = $_SESSION['user_id']; // User registers their own profile
    
    try {
        $pdo->beginTransaction();
        
        // Check if mother profile already exists
        $checkStmt = $pdo->prepare("SELECT id FROM mothers WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        
        if ($checkStmt->fetch()) {
            $error = "You already have a mother profile. Please update your existing profile instead.";
            $pdo->rollBack();
        } else {
            // Update user information first
            $userSql = "UPDATE users SET 
                       first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ? 
                       WHERE id = ?";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->execute([$firstName, $middleName, $lastName, $email, $phone, $userId]);
            
            // Insert into mothers table
            $motherSql = "INSERT INTO mothers (
                user_id, first_name, middle_name, last_name, date_of_birth, civil_status, 
                nationality, religion, education, occupation, phone, email, address, 
                emergency_contact, emergency_phone, blood_type, rh_factor, registered_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $motherStmt = $pdo->prepare($motherSql);
            $motherStmt->execute([
                $userId, $firstName, $middleName, $lastName, $dateOfBirth, $civilStatus,
                $nationality, $religion, $education, $occupation, $phone, $email, $address,
                $emergencyContact, $emergencyPhone, $bloodType, $rhFactor, $registeredBy
            ]);
            
            $motherId = $pdo->lastInsertId();
            
            if ($motherId) {
                // Insert into husband_partners table if husband information is provided
                if (!empty($husbandFirstName) || !empty($husbandLastName)) {
                    $husbandSql = "INSERT INTO husband_partners (
                        mother_id, first_name, middle_name, last_name, date_of_birth, 
                        occupation, education, phone, citizenship, religion, marriage_date, marriage_place
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $husbandStmt = $pdo->prepare($husbandSql);
                    $husbandStmt->execute([
                        $motherId, $husbandFirstName, $husbandMiddleName, $husbandLastName,
                        $husbandDateOfBirth, $husbandOccupation, $husbandEducation, $husbandPhone,
                        $husbandCitizenship, $husbandReligion, $marriageDate, $marriagePlace
                    ]);
                }
                
                // Insert into pregnancy_details table
                $pregnancySql = "INSERT INTO pregnancy_details (
                    mother_id, lmp, edc, gravida, para, abortions, living_children, 
                    planned_pregnancy, first_prenatal_visit, referred_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $pregnancyStmt = $pdo->prepare($pregnancySql);
                $pregnancyStmt->execute([
                    $motherId, $lmp, $edc, $gravida, $para, $abortions, $livingChildren,
                    $plannedPregnancy, $firstPrenatalVisit, $referredBy
                ]);
                
                // Insert into medical_histories table
                $medicalSql = "INSERT INTO medical_histories (
                    mother_id, allergies, medical_conditions, previous_surgeries, 
                    family_history, contraceptive_use, previous_complications
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $medicalStmt = $pdo->prepare($medicalSql);
                $medicalStmt->execute([
                    $motherId, $allergies, $medicalConditions, $previousSurgeries,
                    $familyHistory, $contraceptiveUse, $previousComplications
                ]);
                
                $pdo->commit();
                
                $message = "
                <div class='alert alert-success'>
                    <h5><i class='fas fa-check-circle'></i> Mother Profile Created Successfully!</h5>
                    <strong>Name:</strong> $firstName " . ($middleName ? $middleName . ' ' : '') . "$lastName<br>
                    <strong>EDC:</strong> " . (!empty($edc) ? date('F j, Y', strtotime($edc)) : 'Not calculated') . "
                </div>";
                
                logActivity($userId, "Created own mother profile: $firstName " . ($middleName ? $middleName . ' ' : '') . "$lastName");
                
                // Redirect to dashboard after 3 seconds
                header("Refresh: 3; URL=../dashboard.php");
            } else {
                $pdo->rollBack();
                $error = "Failed to create mother profile. Please try again.";
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get current user data to pre-fill the form
$userId = $_SESSION['user_id'];
$userStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, email, phone FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mother Profile Registration | Kibenes eBirth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <?php include_once __DIR__ . '/../includes/tailwind_config.php'; ?>
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
<body class="bg-slate-50 min-h-full font-inter selection:bg-health-100 selection:text-health-700">
    <?php include_once INCLUDE_PATH . 'header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once INCLUDE_PATH . 'sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8 no-print">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                        <i class="fas fa-id-card text-health-600"></i>
                        Register Mother Profile
                    </h1>
                    <p class="text-slate-500 font-medium mt-1">
                        Create your official health tracking profile
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="px-4 py-2 bg-slate-50 rounded-xl border border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        Ref: WBMS-SR-<?= date('Y') ?>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 flex items-center gap-3 text-emerald-800 animate-in fade-in slide-in-from-top duration-500">
                    <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center text-sm">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="font-bold text-sm"><?php echo $message; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-100 rounded-2xl p-4 flex items-center gap-3 text-rose-800 animate-in fade-in slide-in-from-top duration-500">
                    <div class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center text-sm">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p class="font-bold text-sm"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-sky-50 border border-sky-100 rounded-2xl p-4 flex items-center gap-3 text-sky-800">
                <div class="w-8 h-8 bg-sky-100 rounded-lg flex items-center justify-center text-sm">
                    <i class="fas fa-info-circle"></i>
                </div>
                <p class="text-sm font-medium">Fields marked with <span class="text-rose-500 font-bold">*</span> are required for official health documentation.</p>
            </div>

            <form method="POST" action="" id="motherRegistrationForm" class="space-y-8" novalidate>
                            <!-- Personal Information -->
                <!-- Personal Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-pregnant text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Maternal Personal Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Primary identification information</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label for="first_name" class="form-label-premium">First Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="first_name" id="first_name" class="form-input-premium" 
                                   value="<?= htmlspecialchars($userData['first_name'] ?? $_POST['first_name'] ?? ''); ?>" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="first_name_warning">
                                Required field
                            </div>
                        </div>
                        <div>
                            <label for="middle_name" class="form-label-premium group flex items-center gap-2">
                                Middle Name
                                <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full font-black uppercase">Optional</span>
                            </label>
                            <input type="text" name="middle_name" id="middle_name" class="form-input-premium" 
                                   value="<?= htmlspecialchars($userData['middle_name'] ?? $_POST['middle_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="last_name" class="form-label-premium">Last Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="last_name" id="last_name" class="form-input-premium" 
                                   value="<?= htmlspecialchars($userData['last_name'] ?? $_POST['last_name'] ?? ''); ?>" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="last_name_warning">
                                Required field
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div>
                            <label for="date_of_birth" class="form-label-premium">Date of Birth <span class="text-rose-500">*</span></label>
                            <input type="date" name="date_of_birth" id="date_of_birth" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="date_of_birth_warning">
                                Required field
                            </div>
                        </div>
                        <div>
                            <label for="civil_status" class="form-label-premium">Civil Status <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <select name="civil_status" id="civil_status" class="form-input-premium appearance-none pr-10" required>
                                    <option value="">Select Status</option>
                                    <option value="Single" <?= ($_POST['civil_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?= ($_POST['civil_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                                    <option value="Divorced" <?= ($_POST['civil_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?= ($_POST['civil_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="Separated" <?= ($_POST['civil_status'] ?? '') == 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="civil_status_warning">
                                Required field
                            </div>
                        </div>
                        <div>
                            <label for="nationality" class="form-label-premium">Nationality</label>
                            <input type="text" name="nationality" id="nationality" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['nationality'] ?? 'Filipino'); ?>">
                        </div>
                        <div>
                            <label for="religion" class="form-label-premium">Religion</label>
                            <input type="text" name="religion" id="religion" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['religion'] ?? ''); ?>" placeholder="Roman Catholic">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="education" class="form-label-premium">Highest Education</label>
                            <div class="relative group">
                                <select name="education" id="education" class="form-input-premium appearance-none pr-10">
                                    <option value="">Select Level</option>
                                    <option value="No Formal Education" <?= ($_POST['education'] ?? '') == 'No Formal Education' ? 'selected' : ''; ?>>No Formal Education</option>
                                    <option value="Elementary" <?= ($_POST['education'] ?? '') == 'Elementary' ? 'selected' : ''; ?>>Elementary</option>
                                    <option value="High School" <?= ($_POST['education'] ?? '') == 'High School' ? 'selected' : ''; ?>>High School</option>
                                    <option value="College" <?= ($_POST['education'] ?? '') == 'College' ? 'selected' : ''; ?>>College</option>
                                    <option value="Vocational" <?= ($_POST['education'] ?? '') == 'Vocational' ? 'selected' : ''; ?>>Vocational</option>
                                    <option value="Post Graduate" <?= ($_POST['education'] ?? '') == 'Post Graduate' ? 'selected' : ''; ?>>Post Graduate</option>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label for="occupation" class="form-label-premium">Occupation</label>
                            <input type="text" name="occupation" id="occupation" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['occupation'] ?? ''); ?>" placeholder="Housewife, Teacher, etc.">
                        </div>
                    </div>
                </section>

                <!-- Contact Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-phone-office text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Contact & Address Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Reliable reachability and emergency details</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label for="phone" class="form-label-premium">Phone Number <span class="text-rose-500">*</span></label>
                            <input type="tel" name="phone" id="phone" class="form-input-premium" 
                                   value="<?= htmlspecialchars($userData['phone'] ?? $_POST['phone'] ?? ''); ?>" placeholder="09XXXXXXXXX" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="phone_warning">
                                11-digit number required
                            </div>
                        </div>
                        <div>
                            <label for="email" class="form-label-premium">Email Address</label>
                            <input type="email" name="email" id="email" class="form-input-premium" 
                                   value="<?= htmlspecialchars($userData['email'] ?? $_POST['email'] ?? ''); ?>" placeholder="name@example.com">
                        </div>
                        <div>
                            <label for="address" class="form-label-premium">Current Address <span class="text-rose-500">*</span></label>
                            <div class="relative group">
                                <select name="address" id="address" class="form-input-premium appearance-none pr-10" required>
                                    <option value="">Select Purok/Sitio</option>
                                    <?php foreach ($addressOptions as $addressOption): ?>
                                        <option value="<?= htmlspecialchars($addressOption); ?>" <?= ($_POST['address'] ?? '') == $addressOption ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($addressOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="address_warning">
                                Required field
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="emergency_contact" class="form-label-premium">Emergency Contact Person</label>
                            <input type="text" name="emergency_contact" id="emergency_contact" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>" placeholder="Full Name">
                        </div>
                        <div>
                            <label for="emergency_phone" class="form-label-premium">Emergency Phone</label>
                            <input type="tel" name="emergency_phone" id="emergency_phone" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['emergency_phone'] ?? ''); ?>" placeholder="09XXXXXXXXX">
                        </div>
                    </div>
                </section>
                            
                <!-- Medical Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-notes-medical text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Maternal Medical Profile</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Clinical history and health indicators</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="blood_type" class="form-label-premium">Blood Type</label>
                            <div class="relative group">
                                <select name="blood_type" id="blood_type" class="form-input-premium appearance-none pr-10">
                                    <option value="">Select Type</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bt): ?>
                                        <option value="<?= $bt; ?>" <?= ($_POST['blood_type'] ?? '') == $bt ? 'selected' : ''; ?>><?= $bt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label for="rh_factor" class="form-label-premium">RH Factor</label>
                            <div class="relative group">
                                <select name="rh_factor" id="rh_factor" class="form-input-premium appearance-none pr-10">
                                    <option value="">Select RH</option>
                                    <option value="Positive" <?= ($_POST['rh_factor'] ?? '') == 'Positive' ? 'selected' : ''; ?>>Positive</option>
                                    <option value="Negative" <?= ($_POST['rh_factor'] ?? '') == 'Negative' ? 'selected' : ''; ?>>Negative</option>
                                    <option value="Unknown" <?= ($_POST['rh_factor'] ?? '') == 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                        <div class="space-y-4">
                            <div>
                                <label for="allergies" class="form-label-premium">Allergies</label>
                                <textarea name="allergies" id="allergies" class="form-input-premium min-h-[100px]" placeholder="Food, drug, or environmental allergies..."><?= htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label for="medical_conditions" class="form-label-premium">Medical Conditions</label>
                                <textarea name="medical_conditions" id="medical_conditions" class="form-input-premium min-h-[100px]" placeholder="Hypertension, Diabetes, Asthma, etc..."><?= htmlspecialchars($_POST['medical_conditions'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label for="previous_surgeries" class="form-label-premium">Previous Surgeries</label>
                                <textarea name="previous_surgeries" id="previous_surgeries" class="form-input-premium min-h-[100px]" placeholder="List surgeries and years if possible..."><?= htmlspecialchars($_POST['previous_surgeries'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label for="family_history" class="form-label-premium">Family Medical History</label>
                                <textarea name="family_history" id="family_history" class="form-input-premium min-h-[100px]" placeholder="Genetic or familial health conditions..."><?= htmlspecialchars($_POST['family_history'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </section>
                            
                <!-- Husband/Father Information -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-mars text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Husband/Partner's Profile Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Retrieved from verified family records</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label for="husband_first_name" class="form-label-premium">Partner First Name</label>
                            <input type="text" name="husband_first_name" id="husband_first_name" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['husband_first_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="husband_middle_name" class="form-label-premium">Partner Middle Name</label>
                            <input type="text" name="husband_middle_name" id="husband_middle_name" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['husband_middle_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="husband_last_name" class="form-label-premium">Partner Last Name</label>
                            <input type="text" name="husband_last_name" id="husband_last_name" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['husband_last_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div>
                            <label for="husband_date_of_birth" class="form-label-premium">Partner Birthday</label>
                            <input type="date" name="husband_date_of_birth" id="husband_date_of_birth" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['husband_date_of_birth'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="husband_occupation" class="form-label-premium">Partner Occupation</label>
                            <input type="text" name="husband_occupation" id="husband_occupation" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['husband_occupation'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="husband_education" class="form-label-premium">Partner Education</label>
                            <div class="relative group">
                                <select name="husband_education" id="husband_education" class="form-input-premium appearance-none pr-10">
                                    <option value="">Select Level</option>
                                    <?php foreach (['No Formal Education', 'Elementary', 'High School', 'College', 'Vocational', 'Post Graduate'] as $edu): ?>
                                        <option value="<?= $edu; ?>" <?= ($_POST['husband_education'] ?? '') == $edu ? 'selected' : ''; ?>><?= $edu; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label for="husband_phone" class="form-label-premium">Partner Phone</label>
                            <input type="tel" name="husband_phone" id="husband_phone" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['husband_phone'] ?? ''); ?>" placeholder="09XXXXXXXXX">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label for="husband_citizenship" class="form-label-premium">Citizenship</label>
                            <input type="text" name="husband_citizenship" id="husband_citizenship" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['husband_citizenship'] ?? 'Filipino'); ?>">
                        </div>
                        <div>
                            <label for="husband_religion" class="form-label-premium">Religion</label>
                            <input type="text" name="husband_religion" id="husband_religion" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['husband_religion'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="marriage_date" class="form-label-premium group flex items-center gap-2">
                                Marriage Date
                                <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full font-black uppercase">If Applicable</span>
                            </label>
                            <input type="date" name="marriage_date" id="marriage_date" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['marriage_date'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label for="marriage_place" class="form-label-premium">Marriage Place</label>
                        <input type="text" name="marriage_place" id="marriage_place" class="form-input-premium" 
                               value="<?= htmlspecialchars($_POST['marriage_place'] ?? ''); ?>" placeholder="Municipality, Province">
                    </div>
                </section>
                            
                <!-- Obstetric History -->
                <section class="card-premium">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-baby text-health-600"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Pregnancy & Gestation Data</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">Clinical data for current and past pregnancies</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label for="lmp" class="form-label-premium">Last Menstrual Period <span class="text-rose-500">*</span></label>
                            <input type="date" name="lmp" id="lmp" class="form-input-premium font-black text-health-700" 
                                   value="<?= htmlspecialchars($_POST['lmp'] ?? ''); ?>" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="lmp_warning">
                                Required field
                            </div>
                        </div>
                        <div>
                            <label for="edc" class="form-label-premium text-emerald-700">Estimated Due Date</label>
                            <input type="date" name="edc" id="edc" class="form-input-premium border-emerald-100 bg-emerald-50/30 font-black text-emerald-700" 
                                   value="<?= htmlspecialchars($_POST['edc'] ?? ''); ?>" readonly>
                        </div>
                        <div>
                            <label for="first_prenatal_visit" class="form-label-premium">First Prenatal Visit</label>
                            <input type="date" name="first_prenatal_visit" id="first_prenatal_visit" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['first_prenatal_visit'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div>
                            <label for="gravida" class="form-label-premium">Gravida <span class="text-rose-500">*</span></label>
                            <input type="number" name="gravida" id="gravida" class="form-input-premium font-bold text-center" 
                                   value="<?= htmlspecialchars($_POST['gravida'] ?? '1'); ?>" min="1" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="gravida_warning">
                                Required
                            </div>
                        </div>
                        <div>
                            <label for="para" class="form-label-premium">Para <span class="text-rose-500">*</span></label>
                            <input type="number" name="para" id="para" class="form-input-premium font-bold text-center" 
                                   value="<?= htmlspecialchars($_POST['para'] ?? '0'); ?>" min="0" required>
                            <div class="hidden mt-2 p-2 bg-rose-50 text-rose-600 text-xs font-bold rounded-lg border border-rose-100" id="para_warning">
                                Required
                            </div>
                        </div>
                        <div>
                            <label for="abortions" class="form-label-premium">Abortions</label>
                            <input type="number" name="abortions" id="abortions" class="form-input-premium font-bold text-center" 
                                   value="<?= htmlspecialchars($_POST['abortions'] ?? '0'); ?>" min="0">
                        </div>
                        <div>
                            <label for="living_children" class="form-label-premium">Living Children</label>
                            <input type="number" name="living_children" id="living_children" class="form-input-premium font-bold text-center" 
                                   value="<?= htmlspecialchars($_POST['living_children'] ?? ''); ?>" min="0">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="previous_complications" class="form-label-premium text-rose-700">Previous Complications</label>
                            <textarea name="previous_complications" id="previous_complications" class="form-input-premium border-rose-100 min-h-[100px]" 
                                      placeholder="List any past pregnancy or labor issues..."><?= htmlspecialchars($_POST['previous_complications'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="contraceptive_use" class="form-label-premium">Contraceptive Use</label>
                            <textarea name="contraceptive_use" id="contraceptive_use" class="form-input-premium min-h-[100px]" 
                                      placeholder="History of contraceptive methods used..."><?= htmlspecialchars($_POST['contraceptive_use'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                        <div>
                            <label for="planned_pregnancy" class="form-label-premium">Planned Pregnancy</label>
                            <div class="relative group">
                                <select name="planned_pregnancy" id="planned_pregnancy" class="form-input-premium appearance-none pr-10">
                                    <option value="">Select</option>
                                    <option value="Yes" <?= ($_POST['planned_pregnancy'] ?? '') == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?= ($_POST['planned_pregnancy'] ?? '') == 'No' ? 'selected' : ''; ?>>No</option>
                                    <option value="Unsure" <?= ($_POST['planned_pregnancy'] ?? '') == 'Unsure' ? 'selected' : ''; ?>>Unsure</option>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-hover:text-health-500 transition-colors">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label for="referred_by" class="form-label-premium">Referred By</label>
                            <input type="text" name="referred_by" id="referred_by" class="form-input-premium" 
                                   value="<?= htmlspecialchars($_POST['referred_by'] ?? ''); ?>" placeholder="Facility or Practitioner Name">
                        </div>
                    </div>
                </section>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-8 pb-12">
                    <button type="button" onclick="window.history.back()" 
                            class="w-full sm:w-auto px-10 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold uppercase tracking-widest text-xs hover:bg-slate-200 transition-all active:scale-95">
                        Cancel Registration
                    </button>
                    <button type="submit" 
                            class="w-full sm:w-auto px-16 py-4 bg-health-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-health-700 shadow-xl shadow-health-200 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-save shadow-sm"></i>
                        Complete Registration
                    </button>
                </div>
                        </form>
                    </div>
                </div>
            </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('motherRegistrationForm');
            const lmpInput = document.getElementById('lmp');
            const edcInput = document.getElementById('edc');
            const phoneInput = document.getElementById('phone');

            // Set maximum dates to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_of_birth').max = today;
            document.getElementById('lmp').max = today;
            document.getElementById('first_prenatal_visit').max = today;
            document.getElementById('husband_date_of_birth').max = today;
            document.getElementById('marriage_date').max = today;

            // LMP to EDC Calculation (Naegele's Rule: +7 days, -3 months, +1 year)
            lmpInput.addEventListener('change', function() {
                if (this.value) {
                    const lmpDate = new Date(this.value);
                    const edcDate = new Date(lmpDate);
                    edcDate.setDate(lmpDate.getDate() + 7);
                    edcDate.setMonth(lmpDate.getMonth() - 3);
                    edcDate.setFullYear(lmpDate.getFullYear() + 1);
                    
                    edcInput.value = edcDate.toISOString().split('T')[0];
                    hideWarning('lmp');
                }
            });

            // Auto-calculate EDC if LMP is already filled on load
            if (lmpInput.value) {
                lmpInput.dispatchEvent(new Event('change'));
            }

            // Phone Validation (11 digits)
            [phoneInput, document.getElementById('husband_phone'), document.getElementById('emergency_phone')].forEach(input => {
                if (!input) return;
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11);
                    if (this.id === 'phone' && this.value.length === 11) {
                        hideWarning('phone');
                    }
                });
            });

            // Real-time validation for required fields
            const requiredFields = ['first_name', 'last_name', 'date_of_birth', 'civil_status', 'phone', 'address', 'lmp', 'gravida', 'para'];
            
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
                let isValid = true;
                if (field.value.trim() === '') {
                    isValid = false;
                } else if (field.id === 'phone' && field.value.length !== 11) {
                    isValid = false;
                }

                if (!isValid) {
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

            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !validateField(field)) {
                        isValid = false;
                    }
                });
                
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
