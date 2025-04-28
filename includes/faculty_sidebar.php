<div class="fixed left-0 top-0 h-full w-64 bg-gray-800 text-white">
    <div class="p-4 border-b border-gray-700 flex items-center space-x-3">
        <div class="bg-green-500 rounded-full w-10 h-10 flex items-center justify-center">
            <span class="font-semibold">F</span>
        </div>
        <div>
            <h2 class="text-xl font-semibold">College ERP</h2>
            <p class="text-xs text-gray-400">Faculty Portal</p>
        </div>
    </div>
    
    <nav class="p-4">
        <ul class="space-y-2">
            <li>
                <a href="dashboard.php" 
                   class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
            </li>
            
            <li>
                <a href="attendance.php" 
                   class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-clipboard-check mr-3"></i>
                    Attendance
                </a>
            </li>
            
            <li>
                <a href="timetable.php" 
                   class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'timetable.php' ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-calendar-alt mr-3"></i>
                    Timetable
                </a>
            </li>
            
            <li>
                <a href="assignments.php" 
                   class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'assignments.php' ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-tasks mr-3"></i>
                    Assignments
                </a>
            </li>
            
            <li>
                <a href="students.php" 
                   class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'students.php' ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-users mr-3"></i>
                    My Students
                </a>
            </li>
            
            <li class="pt-4 border-t border-gray-700 mt-4">
                <a href="../logout.php" 
                   class="flex items-center px-4 py-2 rounded-lg hover:bg-red-600 transition bg-red-700">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </nav>
</div>