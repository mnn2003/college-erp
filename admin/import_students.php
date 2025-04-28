<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        $file = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        
        // Remove header row
        array_shift($rows);
        
        $pdo->beginTransaction();
        
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty($row[0])) continue;
            
            // Insert user account
            $hashed_password = password_hash($row[2], PASSWORD_DEFAULT); // Assuming password is in column 3
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'student')");
            $stmt->execute([$row[0], $hashed_password, $row[1]]);
            $user_id = $pdo->lastInsertId();
            
            // Insert student record
            $stmt = $pdo->prepare("INSERT INTO students (user_id, full_name, roll_number, program, batch, contact) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $row[3], // full_name
                $row[4], // roll_number
                $row[5], // program
                $row[6], // batch
                $row[7]  // contact
            ]);
        }
        
        $pdo->commit();
        $message = "Successfully imported " . count($rows) . " students!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="md:flex">
<!-- Sidebar -->
<?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <h1 class="text-3xl font-bold mb-6">Import Students</h1>
        
        <?php if ($message): ?>
        <div class="mb-4 p-4 <?= strpos($message, 'Error') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?> rounded-lg">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Excel File</label>
                    <input type="file" name="excel_file" accept=".xlsx, .xls" required
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <a href="sample_student_import.xlsx" download 
                       class="text-blue-600 hover:underline">Download Sample Template</a>
                </div>
                <button type="submit" 
                        class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition duration-200">
                    Import Students
                </button>
            </form>
        </div>
    </div>
</div>	
</body>
</html>