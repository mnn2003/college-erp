<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only faculty can access
if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit();
}

// Get faculty details
$stmt = $pdo->prepare("
    SELECT f.*, COUNT(fs.subject_id) AS assigned_courses
    FROM faculty f
    LEFT JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id
    WHERE f.user_id = ?
    GROUP BY f.faculty_id
");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();


// Get upcoming assignments to grade
$assignments = $pdo->prepare("
    SELECT a.*, s.subject_name, COUNT(sub.submission_id) AS submissions_count
    FROM assignments a
    JOIN subjects s ON a.course_id = s.subject_id
    LEFT JOIN submissions sub ON a.assignment_id = sub.assignment_id AND sub.marks IS NULL
    WHERE a.faculty_id = ? AND a.due_date <= CURDATE()
    GROUP BY a.assignment_id
    ORDER BY a.due_date
    LIMIT 5
");
$assignments->execute([$faculty['faculty_id']]);
$assignments = $assignments->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/faculty_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <h1 class="text-3xl font-bold mb-6">Faculty Dashboard</h1>
        
        <!-- Welcome Card -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-2">Welcome, <?= htmlspecialchars($faculty['full_name']) ?>!</h2>
            <div class="flex flex-wrap gap-4">
                <div class="bg-blue-50 p-3 rounded-lg flex-1 min-w-[200px]">
                    <div class="text-blue-600 font-medium">Department</div>
                    <div><?= htmlspecialchars($faculty['department']) ?></div>
                </div>
                <div class="bg-green-50 p-3 rounded-lg flex-1 min-w-[200px]">
                    <div class="text-green-600 font-medium">Assigned Courses</div>
                    <div><?= $faculty['assigned_courses'] ?></div>
                </div>
                <div class="bg-purple-50 p-3 rounded-lg flex-1 min-w-[200px]">
                    <div class="text-purple-600 font-medium">Contact</div>
                    <div><?= htmlspecialchars($faculty['contact']) ?></div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Assignments to Grade -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4 flex justify-between items-center">
                    <span>Assignments to Grade</span>
                    <a href="assignments.php" class="text-sm text-blue-600 hover:underline">View All</a>
                </h2>
                
                <?php if (empty($assignments)): ?>
                    <div class="text-center py-4 text-gray-500">
                        <p>No assignments to grade.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($assignments as $assignment): ?>
                        <div class="border-b pb-4 last:border-b-0 last:pb-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-medium"><?= htmlspecialchars($assignment['title']) ?></h3>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <?= htmlspecialchars($assignment['subject_name']) ?>
                                    </div>
                                </div>
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                    <?= $assignment['submissions_count'] ?> to grade
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">
                                Due: <?= date('M j, Y', strtotime($assignment['due_date'])) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>