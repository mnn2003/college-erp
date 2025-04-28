<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<!-- Layout Wrapper -->
<div class="md:flex">

    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <h1 class="text-3xl font-bold mb-6">Admin Dashboard</h1>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <!-- Students Card -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h3 class="text-lg font-semibold">Total Students</h3>
                <p class="text-2xl font-bold text-blue-600">
                    <?php 
                    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
                    echo $stmt->fetchColumn();
                    ?>
                </p>
            </div>

            <!-- Faculty Card -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h3 class="text-lg font-semibold">Total Faculty</h3>
                <p class="text-2xl font-bold text-green-600">
                    <?php 
                    $stmt = $pdo->query("SELECT COUNT(*) FROM faculty");
                    echo $stmt->fetchColumn();
                    ?>
                </p>
            </div>

            <!-- Courses Card -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h3 class="text-lg font-semibold">Total Courses</h3>
                <p class="text-2xl font-bold text-purple-600">
                    <?php 
                    $stmt = $pdo->query("SELECT COUNT(*) FROM subjects");
                    echo $stmt->fetchColumn();
                    ?>
                </p>
            </div>

            <!-- Classrooms Card -->
            <div class="bg-white p-4 rounded-lg shadow">
                <h3 class="text-lg font-semibold">Classrooms</h3>
                <p class="text-2xl font-bold text-orange-600">
                    <?php 
                    $stmt = $pdo->query("SELECT COUNT(*) FROM classrooms");
                    echo $stmt->fetchColumn();
                    ?>
                </p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <a href="manage_students.php" class="bg-blue-600 text-white p-4 rounded-lg shadow text-center hover:bg-blue-700 transition">
                Manage Students
            </a>
            <a href="manage_faculty.php" class="bg-green-600 text-white p-4 rounded-lg shadow text-center hover:bg-green-700 transition">
                Manage Faculty
            </a>
            <a href="manage_courses.php" class="bg-purple-600 text-white p-4 rounded-lg shadow text-center hover:bg-purple-700 transition">
                Manage Courses
            </a>
            <a href="manage_timetable.php" class="bg-orange-600 text-white p-4 rounded-lg shadow text-center hover:bg-orange-700 transition">
                Manage Timetable
            </a>
        </div>
    </div>

</div>
</body>
</html>
