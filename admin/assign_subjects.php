<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$student_id = $_GET['student_id'] ?? 0;

// Fetch student details
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Fetch all subjects in student's program
$subjects = $pdo->prepare("
    SELECT s.* 
    FROM subjects s
    WHERE s.program_id = ?
    ORDER BY s.semester
");
$subjects->execute([$student['program']]);
$all_subjects = $subjects->fetchAll();

// Fetch already assigned subjects
$assigned_subjects = $pdo->prepare("
    SELECT subject_id 
    FROM student_subjects 
    WHERE student_id = ?
");
$assigned_subjects->execute([$student_id]);
$assigned = $assigned_subjects->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_subjects = $_POST['subjects'] ?? [];
    
    // Delete existing assignments
    $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?")
        ->execute([$student_id]);
    
    // Insert new assignments
    $stmt = $pdo->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
    foreach ($selected_subjects as $subject_id) {
        $stmt->execute([$student_id, $subject_id]);
    }
    
    $_SESSION['success'] = "Subjects assigned successfully!";
    header("Location: manage_students.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects</title>
    <script src="https://cdn.tailwindcss.com"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
<div class="md:flex">
<!-- Sidebar -->
<?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <h1 class="text-3xl font-bold mb-6">
            Assign Subjects to: <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['roll_number']) ?>)
        </h1>
        
        <form method="post" class="bg-white p-6 rounded-lg shadow">
            <div class="mb-4">
                <h3 class="text-lg font-semibold mb-2">Available Subjects</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($all_subjects as $subject): ?>
                    <div class="flex items-center">
                        <input type="checkbox" name="subjects[]" value="<?= $subject['subject_id'] ?>" 
                               id="subject_<?= $subject['subject_id'] ?>"
                               <?= in_array($subject['subject_id'], $assigned) ? 'checked' : '' ?>
                               class="mr-2">
                        <label for="subject_<?= $subject['subject_id'] ?>">
                            <?= htmlspecialchars($subject['subject_name']) ?> (Sem <?= $subject['semester'] ?>)
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Save Assignments
                </button>
                <a href="manage_students.php" class="ml-4 text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</div>	
</body>
</html>