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

// Get student's assignments
$assignments = $pdo->prepare("
    SELECT 
        a.assignment_id,
        a.title,
        a.description,
        a.due_date,
        a.max_marks,
        sub.subject_code,
        sub.subject_name,
        f.full_name AS faculty_name,
        s.submission_id,
        s.submitted_at,
        s.marks,
        s.feedback
    FROM assignments a
    JOIN subjects sub ON a.course_id = sub.subject_id
    JOIN faculty f ON a.faculty_id = f.faculty_id
    JOIN student_subjects ss ON sub.subject_id = ss.subject_id
    LEFT JOIN submissions s ON a.assignment_id = s.assignment_id AND s.student_id = ?
    WHERE ss.student_id = ?
    ORDER BY a.due_date DESC
");
$assignments->execute([$student['student_id'], $student['student_id']]);
$assignments = $assignments->fetchAll();

// Categorize assignments
$pending = [];
$submitted = [];
$graded = [];

foreach ($assignments as $assignment) {
    if ($assignment['submission_id'] === null) {
        $pending[] = $assignment;
    } elseif ($assignment['marks'] === null) {
        $submitted[] = $assignment;
    } else {
        $graded[] = $assignment;
    }
}

function formatAssignmentCard($assignment) {
    $dueDate = new DateTime($assignment['due_date']);
    $now = new DateTime();
    $isLate = $now > $dueDate;
    ?>
    <div class="border rounded-lg p-4 mb-4 hover:shadow-md transition <?= $isLate ? 'border-red-200 bg-red-50' : 'border-gray-200' ?>">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="font-semibold text-lg"><?= htmlspecialchars($assignment['title']) ?></h3>
                <div class="text-sm text-gray-600 mt-1">
                    <span class="font-medium"><?= htmlspecialchars($assignment['subject_code']) ?></span> - 
                    <?= htmlspecialchars($assignment['subject_name']) ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">
                    <i class="fas fa-user-tie mr-1"></i>
                    <?= htmlspecialchars($assignment['faculty_name']) ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm <?= $isLate ? 'text-red-600' : 'text-gray-600' ?>">
                    Due: <?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?>
                </div>
                <?php if ($isLate): ?>
                    <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full">Late</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($assignment['description'])): ?>
            <div class="mt-3 text-sm">
                <?= nl2br(htmlspecialchars($assignment['description'])) ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-4 pt-3 border-t flex justify-between items-center">
            <div>
                <?php if ($assignment['submission_id'] !== null): ?>
                    <span class="text-sm text-green-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        Submitted on <?= date('M j, Y', strtotime($assignment['submitted_at'])) ?>
                    </span>
                <?php else: ?>
                    <span class="text-sm text-yellow-600">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Pending submission
                    </span>
                <?php endif; ?>
            </div>
            
            <div>
                <?php if ($assignment['marks'] !== null): ?>
                    <span class="font-medium">
                        Grade: <?= $assignment['marks'] ?>/<?= $assignment['max_marks'] ?>
                    </span>
                <?php elseif ($assignment['submission_id'] !== null): ?>
                    <span class="text-sm text-blue-600">
                        <i class="fas fa-hourglass-half mr-1"></i>
                        Grading in progress
                    </span>
                <?php else: ?>
                    <a href="submit_assignment.php?id=<?= $assignment['assignment_id'] ?>" 
					   class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">
						Submit Assignment
					</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($assignment['feedback'])): ?>
            <div class="mt-3 p-3 bg-gray-50 rounded border border-gray-200">
                <h4 class="font-medium text-sm mb-1">Feedback:</h4>
                <p class="text-sm"><?= nl2br(htmlspecialchars($assignment['feedback'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">My Assignments</h1>
            <div class="bg-white px-4 py-2 rounded-lg shadow">
                <span class="font-medium"><?= htmlspecialchars($student['full_name']) ?></span>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow overflow-hidden p-6">
            <?php if (empty($assignments)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-tasks fa-2x mb-2"></i>
                    <p>No assignments found.</p>
                </div>
            <?php else: ?>
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">Pending Submission</h2>
                    <?php if (empty($pending)): ?>
                        <div class="text-center py-4 text-gray-500">
                            <i class="fas fa-check-circle fa-lg mb-2"></i>
                            <p>All assignments submitted!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending as $assignment): ?>
                            <?php formatAssignmentCard($assignment); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">Submitted (Pending Grading)</h2>
                    <?php if (empty($submitted)): ?>
                        <div class="text-center py-4 text-gray-500">
                            <p>No assignments awaiting grading.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($submitted as $assignment): ?>
                            <?php formatAssignmentCard($assignment); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">Graded Assignments</h2>
                    <?php if (empty($graded)): ?>
                        <div class="text-center py-4 text-gray-500">
                            <p>No graded assignments yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($graded as $assignment): ?>
                            <?php formatAssignmentCard($assignment); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>