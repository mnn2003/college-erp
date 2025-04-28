<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Fetch all students
$stmt = $pdo->query("
    SELECT s.*, p.program_name 
    FROM students s
    LEFT JOIN programs p ON s.program = p.program_id
");

$students = $stmt->fetchAll();

// Fetch all programs
$programs = $pdo->query("SELECT * FROM programs")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
<div class="md:flex">
<!-- Sidebar -->
<?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <h1 class="text-3xl font-bold mb-6">Manage Students</h1>
        
        <!-- Add New Student Button -->
        <div class="mb-6">
            <a href="add_student.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                + Add New Student
            </a>
        </div>
        
        <!-- Students Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Roll No.</th>
                        <th class="py-3 px-4 text-left">Name</th>
                        <th class="py-3 px-4 text-left">Program</th>
                        <th class="py-3 px-4 text-left">Batch</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr class="border-t">
                        <td class="py-3 px-4"><?= htmlspecialchars($student['roll_number']) ?></td>
                        <td class="py-3 px-4"><?= htmlspecialchars($student['full_name']) ?></td>
                        <td class="py-3 px-4"><?= htmlspecialchars($student['program_name'] ?? 'N/A') ?></td>
                        <td class="py-3 px-4"><?= htmlspecialchars($student['batch']) ?></td>
                        <td class="py-3 px-4">
                            <a href="edit_student.php?id=<?= $student['student_id'] ?>" 
                               class="text-blue-600 hover:underline mr-2">Edit</a>
                            <a href="assign_subjects.php?student_id=<?= $student['student_id'] ?>" 
                               class="text-green-600 hover:underline mr-2">Assign Subjects</a>
                            <a href="delete_student.php?id=<?= $student['student_id'] ?>" 
                               class="text-red-600 hover:underline" 
                               onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>	
</body>
</html>