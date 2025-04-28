<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only students can access
if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Get student details
$stmt = $pdo->prepare("
    SELECT s.*, p.program_name 
    FROM students s
    JOIN programs p ON s.program = p.program_id
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Get today's classes
$today = date('l'); // e.g., "Monday"
$stmt = $pdo->prepare("
    SELECT t.*, s.subject_name, s.subject_code, f.full_name AS faculty_name, r.room_name
    FROM timetable t
    JOIN subjects s ON t.course_id = s.subject_id
    JOIN faculty f ON t.faculty_id = f.faculty_id
    JOIN classrooms r ON t.room = r.room_name
    JOIN student_subjects ss ON s.subject_id = ss.subject_id
    WHERE ss.student_id = ? AND t.day = ?
    ORDER BY t.start_time
");
$stmt->execute([$student['student_id'], $today]);
$todays_classes = $stmt->fetchAll();

// Get all enrolled subjects
$stmt = $pdo->prepare("
    SELECT s.* 
    FROM subjects s
    JOIN student_subjects ss ON s.subject_id = ss.subject_id
    WHERE ss.student_id = ?
    ORDER BY s.semester, s.subject_name
");
$stmt->execute([$student['student_id']]);
$subjects = $stmt->fetchAll();

// Get attendance summary
$stmt = $pdo->prepare("
    SELECT s.subject_name, 
           COUNT(CASE WHEN a.status = 'Present' THEN 1 END) AS present_count,
           COUNT(*) AS total_classes,
           (COUNT(CASE WHEN a.status = 'Present' THEN 1 END) / COUNT(*)) * 100 AS attendance_percentage
    FROM attendance a
    JOIN subjects s ON a.course_id = s.subject_id
    WHERE a.student_id = ?
    GROUP BY s.subject_name
");
$stmt->execute([$student['student_id']]);
$attendance = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Student Sidebar -->
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <h1 class="text-3xl font-bold mb-6">Student Dashboard</h1>
        
        <!-- Welcome Card -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-2">Welcome, <?= htmlspecialchars($student['full_name']) ?>!</h2>
            <p class="text-gray-700">
                Roll Number: <?= htmlspecialchars($student['roll_number']) ?> | 
                Program: <?= htmlspecialchars($student['program_name']) ?> | 
                Batch: <?= htmlspecialchars($student['batch']) ?>
            </p>
        </div>
        
        <!-- Today's Classes -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-4">Today's Classes (<?= date('F j, Y') ?>)</h2>
            
            <?php if (empty($todays_classes)): ?>
                <p class="text-gray-500">No classes scheduled for today.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($todays_classes as $class): ?>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($class['subject_name']) ?></h3>
                        <p class="text-gray-600"><?= htmlspecialchars($class['subject_code']) ?></p>
                        <div class="mt-2">
                            <p><span class="font-medium">Time:</span> <?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?></p>
                            <p><span class="font-medium">Faculty:</span> <?= htmlspecialchars($class['faculty_name']) ?></p>
                            <p><span class="font-medium">Room:</span> <?= htmlspecialchars($class['room_name']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Attendance Summary -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-4">Attendance Summary</h2>
            
            <?php if (empty($attendance)): ?>
                <p class="text-gray-500">No attendance records found.</p>
            <?php else: ?>
                <table class="min-w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-2 px-4 text-left">Subject</th>
                            <th class="py-2 px-4 text-left">Present</th>
                            <th class="py-2 px-4 text-left">Total Classes</th>
                            <th class="py-2 px-4 text-left">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $record): ?>
                        <tr class="border-t">
                            <td class="py-2 px-4"><?= htmlspecialchars($record['subject_name']) ?></td>
                            <td class="py-2 px-4"><?= $record['present_count'] ?></td>
                            <td class="py-2 px-4"><?= $record['total_classes'] ?></td>
                            <td class="py-2 px-4">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-blue-600 h-2.5 rounded-full" 
                                         style="width: <?= $record['attendance_percentage'] ?>%"></div>
                                </div>
                                <?= round($record['attendance_percentage'], 2) ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Enrolled Subjects -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Your Subjects</h2>
            
            <?php if (empty($subjects)): ?>
                <p class="text-gray-500">No subjects enrolled.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($subjects as $subject): ?>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($subject['subject_name']) ?></h3>
                        <p class="text-gray-600"><?= htmlspecialchars($subject['subject_code']) ?></p>
                        <div class="mt-2">
                            <p><span class="font-medium">Semester:</span> <?= $subject['semester'] ?></p>
                            <p><span class="font-medium">Credits:</span> <?= $subject['credits'] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>