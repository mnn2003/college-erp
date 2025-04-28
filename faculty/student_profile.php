<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only faculty can access
if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit();
}

// Get faculty details
$stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();

// Get student and course IDs from URL
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Verify faculty is assigned to this course
$stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ?");
$stmt->execute([$faculty['faculty_id'], $course_id]);
if ($stmt->fetchColumn() == 0) {
    $_SESSION['error'] = "You don't have permission to view this student";
    header("Location: students.php");
    exit();
}

// Get student details
$student = $pdo->prepare("
    SELECT s.*, p.program_name
    FROM students s
    JOIN programs p ON s.program = p.program_id
    WHERE s.student_id = ?
");
$student->execute([$student_id]);
$student = $student->fetch();

// Get course details
$course = $pdo->prepare("
    SELECT s.*, p.program_name
    FROM subjects s
    JOIN programs p ON s.program_id = p.program_id
    WHERE s.subject_id = ?
");
$course->execute([$course_id]);
$course = $course->fetch();

// Get attendance records
$attendance = $pdo->prepare("
    SELECT date, status
    FROM attendance
    WHERE student_id = ? AND course_id = ?
    ORDER BY date DESC
");
$attendance->execute([$student_id, $course_id]);
$attendance_records = $attendance->fetchAll();

// Calculate attendance stats
$total_classes = count($attendance_records);
$present_count = 0;
$absent_count = 0;
$late_count = 0;

foreach ($attendance_records as $record) {
    if ($record['status'] === 'Present') $present_count++;
    elseif ($record['status'] === 'Absent') $absent_count++;
    elseif ($record['status'] === 'Late') $late_count++;
}

$attendance_rate = $total_classes > 0 ? round(($present_count / $total_classes) * 100) : 0;

// Get assignments and grades
$assignments = $pdo->prepare("
    SELECT a.assignment_id, a.title, a.max_marks, 
       sub.marks, sub.feedback, sub.submitted_at
    FROM assignments a
    LEFT JOIN submissions sub ON a.assignment_id = sub.assignment_id AND sub.student_id = ?
    WHERE a.course_id = ?
    ORDER BY a.due_date DESC
");
$assignments->execute([$student_id, $course_id]);
$assignments = $assignments->fetchAll();

// Calculate average grade
$graded_assignments = array_filter($assignments, function($a) { return $a['marks'] !== null; });
$average_grade = 0;
if (count($graded_assignments) > 0) {
    $total_marks = array_sum(array_column($graded_assignments, 'marks'));
    $max_marks = array_sum(array_column($graded_assignments, 'max_marks'));
    $average_grade = $max_marks > 0 ? round(($total_marks / $max_marks) * 100, 1) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include '../includes/faculty_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold">Student Profile</h1>
                <div class="text-lg text-gray-600">
                    <?= htmlspecialchars($course['subject_code']) ?> - <?= htmlspecialchars($course['subject_name']) ?>
                </div>
            </div>
            <a href="students.php?course_id=<?= $course_id ?>" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> Back to Students
            </a>
        </div>
        
        <!-- Student Info Card -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="mb-4 md:mb-0">
                    <h2 class="text-2xl font-bold">
                        <?= htmlspecialchars($student['full_name']) ?>
                        <span class="text-lg font-normal text-gray-600 ml-2">
                            (<?= htmlspecialchars($student['roll_number']) ?>)
                        </span>
                    </h2>
                    <div class="text-gray-700 mt-2">
                        <span class="font-medium">Program:</span> <?= htmlspecialchars($student['program_name']) ?>
                        | <span class="font-medium">Batch:</span> <?= htmlspecialchars($student['batch']) ?>
                    </div>
                    <div class="text-gray-700 mt-1">
                        <span class="font-medium">Contact:</span> <?= htmlspecialchars($student['contact']) ?>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <div class="text-center bg-blue-50 p-3 rounded-lg">
                        <div class="text-blue-800 font-medium">Attendance</div>
                        <div class="text-2xl font-bold mt-1"><?= $attendance_rate ?>%</div>
                        <div class="text-xs text-gray-600"><?= $present_count ?>/<?= $total_classes ?> classes</div>
                    </div>
                    
                    <div class="text-center bg-green-50 p-3 rounded-lg">
                        <div class="text-green-800 font-medium">Average Grade</div>
                        <div class="text-2xl font-bold mt-1"><?= $average_grade ?>%</div>
                        <div class="text-xs text-gray-600"><?= count($graded_assignments) ?>/<?= count($assignments) ?> assignments</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Attendance Section -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b bg-gray-50">
                    <h2 class="text-xl font-semibold">Attendance Records</h2>
                </div>
                
                <?php if (empty($attendance_records)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <p>No attendance records found.</p>
                    </div>
                <?php else: ?>
                    <div class="p-4">
                        <canvas id="attendanceChart" height="200"></canvas>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-200">
                                <tr>
                                    <th class="py-2 px-4 text-left">Date</th>
                                    <th class="py-2 px-4 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_records as $record): ?>
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="py-2 px-4">
                                        <?= date('M j, Y', strtotime($record['date'])) ?>
                                    </td>
                                    <td class="py-2 px-4">
                                        <span class="px-2 py-1 rounded-full text-xs 
                                            <?= $record['status'] === 'Present' ? 'bg-green-100 text-green-800' : '' ?>
                                            <?= $record['status'] === 'Absent' ? 'bg-red-100 text-red-800' : '' ?>
                                            <?= $record['status'] === 'Late' ? 'bg-yellow-100 text-yellow-800' : '' ?>">
                                            <?= $record['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Assignments Section -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b bg-gray-50">
                    <h2 class="text-xl font-semibold">Assignments</h2>
                </div>
                
                <?php if (empty($assignments)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-tasks fa-2x mb-2"></i>
                        <p>No assignments for this course.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-200">
                                <tr>
                                    <th class="py-2 px-4 text-left">Assignment</th>
                                    <th class="py-2 px-4 text-left">Status</th>
                                    <th class="py-2 px-4 text-left">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="py-2 px-4">
                                        <div class="font-medium"><?= htmlspecialchars($assignment['title']) ?></div>
                                        <div class="text-xs text-gray-600">
                                            Max marks: <?= $assignment['max_marks'] ?>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4">
                                        <?php if ($assignment['submitted_at']): ?>
                                            <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                                Submitted
                                            </span>
                                            <div class="text-xs text-gray-600 mt-1">
                                                <?= date('M j', strtotime($assignment['submitted_at'])) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">
                                                Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-4">
                                        <?php if ($assignment['marks'] !== null): ?>
                                            <div class="font-medium">
                                                <?= $assignment['marks'] ?>/<?= $assignment['max_marks'] ?>
                                            </div>
                                            <div class="text-xs text-gray-600">
                                                <?= round(($assignment['marks'] / $assignment['max_marks']) * 100) ?>%
                                            </div>
                                        <?php elseif ($assignment['submitted_at']): ?>
                                            <span class="text-yellow-600 text-sm">Pending grading</span>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-sm">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="p-4 border-t">
                        <h3 class="font-medium mb-2">Grade Distribution</h3>
                        <canvas id="gradesChart" height="200"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Attendance Chart
        <?php if (!empty($attendance_records)): ?>
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [<?= $present_count ?>, <?= $absent_count ?>, <?= $late_count ?>],
                    backgroundColor: [
                        '#10B981',
                        '#EF4444',
                        '#F59E0B'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Grades Chart
        <?php if (!empty($assignments) && count($graded_assignments) > 0): ?>
        const gradesCtx = document.getElementById('gradesChart').getContext('2d');
        new Chart(gradesCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($graded_assignments as $assignment): ?>
                        '<?= addslashes($assignment['title']) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Marks Obtained',
                    data: [
                        <?php foreach ($graded_assignments as $assignment): ?>
                            <?= $assignment['marks'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#3B82F6'
                }, {
                    label: 'Max Marks',
                    data: [
                        <?php foreach ($graded_assignments as $assignment): ?>
                            <?= $assignment['max_marks'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#E5E7EB'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: false,
                    },
                    y: {
                        stacked: false,
                        beginAtZero: true,
                        max: <?= max(array_column($graded_assignments, 'max_marks')) + 5 ?>
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>