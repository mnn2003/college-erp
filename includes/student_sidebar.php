<script src="https://unpkg.com/lucide@latest"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        lucide.createIcons();
    });
</script>
<div class="fixed left-0 top-0 h-full w-64 bg-gray-800 text-white">
    <div class="p-4 border-b border-gray-700">
        <h2 class="text-xl font-semibold">College ERP</h2>
        <p class="text-sm text-gray-400">Student Portal</p>
    </div>
    <nav class="p-4">
        <ul class="space-y-2">
            <li>
                <a href="dashboard.php" 
                   class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-gray-700' : '' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="timetable.php" 
                   class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'timetable.php' ? 'bg-gray-700' : '' ?>">
                    <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                    <span>Full Timetable</span>
                </a>
            </li>
            <li>
                <a href="attendance.php" 
                   class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'bg-gray-700' : '' ?>">
                    <i data-lucide="check-square" class="w-5 h-5"></i>
                    <span>Attendance</span>
                </a>
            </li>
            <li>
                <a href="assignments.php" 
                   class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'assignments.php' ? 'bg-gray-700' : '' ?>">
                    <i data-lucide="file-text" class="w-5 h-5"></i>
                    <span>Assignments</span>
                </a>
            </li>
            <li class="pt-4 border-t border-gray-700 mt-4">
                <a href="../logout.php" 
                   class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-red-600 transition bg-red-700 text-center justify-center">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</div>
