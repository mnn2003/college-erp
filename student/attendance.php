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

// Get all subjects the student is enrolled in
$subjects = $pdo->prepare("
    SELECT sub.subject_id, sub.subject_code, sub.subject_name
    FROM subjects sub
    JOIN student_subjects ss ON sub.subject_id = ss.subject_id
    WHERE ss.student_id = ?
    ORDER BY sub.subject_name
");
$subjects->execute([$student['student_id']]);
$subjects = $subjects->fetchAll();

// Get attendance summary
$attendance = [];
if (!empty($subjects)) {
    foreach ($subjects as $subject) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'Present' THEN 1 END) AS present,
                COUNT(CASE WHEN status = 'Absent' THEN 1 END) AS absent,
                COUNT(CASE WHEN status = 'Late' THEN 1 END) AS late,
                COUNT(*) AS total
            FROM attendance
            WHERE student_id = ? AND course_id = ?
        ");
        $stmt->execute([$student['student_id'], $subject['subject_id']]);
        $attendance[$subject['subject_id']] = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">My Attendance</h1>
            <div class="bg-white px-4 py-2 rounded-lg shadow">
                <span class="font-medium">Roll No:</span> <?= htmlspecialchars($student['roll_number']) ?>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="p-4 border-b">
                <h2 class="text-xl font-semibold">
                    <?= htmlspecialchars($student['full_name']) ?> - <?= htmlspecialchars($student['program_name']) ?>
                </h2>
            </div>
            
            <?php if (empty($subjects)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-book-open fa-2x mb-2"></i>
                    <p>You are not enrolled in any subjects yet.</p>
                </div>
            <?php else: ?>
                <table class="min-w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-left">Subject</th>
                            <th class="py-3 px-4 text-left">Present</th>
                            <th class="py-3 px-4 text-left">Absent</th>
                            <th class="py-3 px-4 text-left">Late</th>
                            <th class="py-3 px-4 text-left">Total</th>
                            <th class="py-3 px-4 text-left">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): 
                            $stats = $attendance[$subject['subject_id']];
                            $percentage = $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100, 2) : 0;
                        ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <span class="font-medium"><?= htmlspecialchars($subject['subject_code']) ?></span>
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($subject['subject_name']) ?></div>
                            </td>
                            <td class="py-3 px-4 text-green-600"><?= $stats['present'] ?></td>
                            <td class="py-3 px-4 text-red-600"><?= $stats['absent'] ?></td>
                            <td class="py-3 px-4 text-yellow-600"><?= $stats['late'] ?></td>
                            <td class="py-3 px-4"><?= $stats['total'] ?></td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2.5 mr-2">
                                        <div class="bg-blue-600 h-2.5 rounded-full" 
                                             style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <?= $percentage ?>%
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="font-semibold mb-2">Attendance Legend</h3>
            <div class="flex space-x-4">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-600 rounded-full mr-2"></div>
                    <span>Present</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-red-600 rounded-full mr-2"></div>
                    <span>Absent</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-yellow-600 rounded-full mr-2"></div>
                    <span>Late</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>