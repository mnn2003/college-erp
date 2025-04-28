<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Fetch all subjects
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();

// Fetch all faculty
$faculty = $pdo->query("SELECT * FROM faculty ORDER BY full_name")->fetchAll();

// Fetch current assignments
$assignments = $pdo->query("
    SELECT fs.subject_id, fs.faculty_id, f.full_name
    FROM faculty_subjects fs
    JOIN faculty f ON fs.faculty_id = f.faculty_id
")->fetchAll(PDO::FETCH_GROUP);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_assignments = $_POST['faculty'] ?? [];
    
    // Clear existing assignments
    $pdo->query("TRUNCATE TABLE faculty_subjects");
    
    // Insert new assignments
    $stmt = $pdo->prepare("INSERT INTO faculty_subjects (faculty_id, subject_id) VALUES (?, ?)");
    foreach ($subject_assignments as $subject_id => $faculty_id) {
        if ($faculty_id > 0) {
            $stmt->execute([$faculty_id, $subject_id]);
        }
    }
    
    $_SESSION['success'] = "Faculty assignments updated successfully!";
    header("Location: assign_faculty.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Faculty to Courses</title>
    <script src="https://cdn.tailwindcss.com"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
<div class="md:flex">
<!-- Sidebar -->
<?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <h1 class="text-3xl font-bold mb-6">Assign Faculty to Courses</h1>
        
        <form method="post" class="bg-white p-6 rounded-lg shadow">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-2 px-4 text-left">Subject</th>
                        <th class="py-2 px-4 text-left">Assigned Faculty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subject): 
                        $current_faculty = $assignments[$subject['subject_id']][0]['faculty_id'] ?? 0;
                    ?>
                    <tr class="border-t">
                        <td class="py-3 px-4">
                            <?= htmlspecialchars($subject['subject_name']) ?> (<?= $subject['subject_code'] ?>)
                        </td>
                        <td class="py-3 px-4">
                            <select name="faculty[<?= $subject['subject_id'] ?>]" 
                                    class="border rounded px-3 py-1 w-full md:w-64">
                                <option value="0">-- Select Faculty --</option>
                                <?php foreach ($faculty as $f): ?>
                                <option value="<?= $f['faculty_id'] ?>" 
                                    <?= $f['faculty_id'] == $current_faculty ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['full_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Save Assignments
                </button>
            </div>
        </form>
    </div>
</div>	
</body>
</html>