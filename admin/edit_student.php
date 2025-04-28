<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Fetch all programs for dropdown
$programs = $pdo->query("SELECT * FROM programs ORDER BY program_name")->fetchAll();

// Get student ID from query string
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid student ID.";
    header("Location: manage_students.php");
    exit();
}
$student_id = (int)$_GET['id'];

// Fetch student details
$stmt = $pdo->prepare("SELECT s.*, u.email, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $_SESSION['error'] = "Student not found.";
    header("Location: manage_students.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    // Collect and sanitize input data
    $full_name = trim($_POST['full_name']);
    $roll_number = trim($_POST['roll_number']);
    $email = trim($_POST['email']);
    $program_id = (int)$_POST['program_id'];
    $batch = (int)$_POST['batch'];
    $contact = trim($_POST['contact']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    $errors = [];
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    if (empty($roll_number)) {
        $errors[] = "Roll number is required";
    } else {
        // Check if roll number already exists for another student
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE roll_number = ? AND student_id != ?");
        $stmt->execute([$roll_number, $student_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Roll number already exists";
        }
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $student['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already registered";
        }
    }
    if ($program_id <= 0) {
        $errors[] = "Please select a valid program";
    }
    if ($batch < 2000 || $batch > date('Y') + 5) {
        $errors[] = "Invalid batch year";
    }
    if (empty($username)) {
        $errors[] = "Username is required";
    } else {
        // Check if username exists for another user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $student['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already taken";
        }
    }
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }

    // If no errors, proceed with database operations
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update user account
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?" . (!empty($password) ? ", password = ?" : "") . " WHERE id = ?");
            $params = [$username, $email];
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $params[] = $hashed_password;
            }
            $params[] = $student['user_id'];
            $stmt->execute($params);

            // Update student record
            $stmt = $pdo->prepare("UPDATE students SET full_name = ?, roll_number = ?, program = ?, batch = ?, contact = ? WHERE student_id = ?");
            $stmt->execute([$full_name, $roll_number, $program_id, $batch, $contact, $student_id]);

            $pdo->commit();
            $_SESSION['success'] = "Student updated successfully!";
            header("Location: manage_students.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error updating student: " . $e->getMessage();
            header("Location: edit_student.php?id=" . $student_id);
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: edit_student.php?id=" . $student_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
<div class="md:flex">
<!-- Sidebar -->
<?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">Edit Student</h1>
            <a href="manage_students.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> Back to Students
            </a>
        </div>
        <!-- Error Messages -->
        <?php if (isset($_SESSION['errors'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php unset($_SESSION['errors']); ?>
            </div>
        <?php endif; ?>
        <!-- Success Message -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <!-- Edit Student Form -->
        <div class="bg-white p-6 rounded-lg shadow">
            <form method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold border-b pb-2">Personal Information</h3>
                        <div>
                            <label class="block text-gray-700 mb-2">Full Name*</label>
                            <input type="text" name="full_name" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="<?= isset($_SESSION['form_data']['full_name']) ? htmlspecialchars($_SESSION['form_data']['full_name']) : htmlspecialchars($student['full_name']) ?>">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Roll Number*</label>
                            <input type="text" name="roll_number" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="<?= isset($_SESSION['form_data']['roll_number']) ? htmlspecialchars($_SESSION['form_data']['roll_number']) : htmlspecialchars($student['roll_number']) ?>">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Contact Number</label>
                            <input type="text" name="contact"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="<?= isset($_SESSION['form_data']['contact']) ? htmlspecialchars($_SESSION['form_data']['contact']) : htmlspecialchars($student['contact']) ?>">
                        </div>
                    </div>
                    <!-- Academic Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold border-b pb-2">Academic Information</h3>
                        <div>
                            <label class="block text-gray-700 mb-2">Program*</label>
                            <select name="program_id" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Program</option>
                                <?php foreach ($programs as $program): ?>
                                <option value="<?= $program['program_id'] ?>"
                                    <?= (isset($_SESSION['form_data']['program_id']) && $_SESSION['form_data']['program_id'] == $program['program_id']) || $student['program'] == $program['program_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($program['program_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Batch Year*</label>
                            <select name="batch" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Batch</option>
                                <?php for ($year = date('Y') + 1; $year >= 2000; $year--): ?>
                                <option value="<?= $year ?>"
                                    <?= (isset($_SESSION['form_data']['batch']) && $_SESSION['form_data']['batch'] == $year) || $student['batch'] == $year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Login Credentials -->
                    <div class="space-y-4 md:col-span-2">
                        <h3 class="text-lg font-semibold border-b pb-2">Login Credentials</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Email*</label>
                                <input type="email" name="email" required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="<?= isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : htmlspecialchars($student['email']) ?>">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Username*</label>
                                <input type="text" name="username" required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="<?= isset($_SESSION['form_data']['username']) ? htmlspecialchars($_SESSION['form_data']['username']) : htmlspecialchars($student['username']) ?>">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Password (min 8 chars)</label>
                                <input type="password" name="password"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Confirm Password</label>
                                <input type="password" name="confirm_password"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="reset" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        <i class="fas fa-redo mr-2"></i> Reset
                    </button>
                    <button type="submit" name="update_student" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>	
    <?php unset($_SESSION['form_data']); ?>
</body>
</html>