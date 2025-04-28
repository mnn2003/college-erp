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
    SELECT s.student_id, s.roll_number, s.full_name, p.program_name 
    FROM students s
    JOIN programs p ON s.program = p.program_id
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Get student's timetable
$timetable = $pdo->prepare("
    SELECT 
        t.day,
        t.start_time,
        t.end_time,
        sub.subject_code,
        sub.subject_name,
        t.room,
        f.full_name AS faculty_name
    FROM timetable t
    JOIN subjects sub ON t.course_id = sub.subject_id
    JOIN faculty f ON t.faculty_id = f.faculty_id
    JOIN student_subjects ss ON sub.subject_id = ss.subject_id
    WHERE ss.student_id = ?
    ORDER BY 
        CASE t.day
            WHEN 'Monday' THEN 1
            WHEN 'Tuesday' THEN 2
            WHEN 'Wednesday' THEN 3
            WHEN 'Thursday' THEN 4
            WHEN 'Friday' THEN 5
            WHEN 'Saturday' THEN 6
        END,
        t.start_time
");
$timetable->execute([$student['student_id']]);
$timetable = $timetable->fetchAll();

// Group timetable by day
$timetable_by_day = [];
foreach ($timetable as $entry) {
    $timetable_by_day[$entry['day']][] = $entry;
}
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">My Timetable</h1>
            <div class="bg-white px-4 py-2 rounded-lg shadow">
                <span class="font-medium"><?= htmlspecialchars($student['program_name']) ?></span>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (empty($timetable)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                    <p>Your timetable is not available yet.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4">
                    <?php foreach ($days as $day): ?>
                        <?php if (isset($timetable_by_day[$day])): ?>
                            <div class="border rounded-lg overflow-hidden">
                                <div class="bg-blue-600 text-white p-3">
                                    <h3 class="font-semibold text-center"><?= $day ?></h3>
                                </div>
                                <div class="divide-y">
                                    <?php foreach ($timetable_by_day[$day] as $class): ?>
                                        <div class="p-3 hover:bg-gray-50">
                                            <div class="flex justify-between">
                                                <span class="font-medium">
                                                    <?= htmlspecialchars($class['subject_code']) ?>
                                                </span>
                                                <span class="text-sm text-gray-600">
                                                    <?= date('h:i A', strtotime($class['start_time'])) ?> - 
                                                    <?= date('h:i A', strtotime($class['end_time'])) ?>
                                                </span>
                                            </div>
                                            <div class="text-sm mt-1">
                                                <?= htmlspecialchars($class['subject_name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-user-tie mr-1"></i>
                                                <?= htmlspecialchars($class['faculty_name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                <?= htmlspecialchars($class['room']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>