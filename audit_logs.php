<?php
// audit_logs.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    header("Location: login.php");
    exit();
}

// Fetch the latest 500 audit logs and join with the users table to get names
$query = "SELECT a.*, u.fullname, u.role FROM audit_logs a 
          LEFT JOIN users u ON a.user_id = u.id 
          ORDER BY a.created_at DESC LIMIT 500";
$logs = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs | JEJ Surveying</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modern.css">
    
    <style>
        body { background-color: #F7FAFC; display: flex; min-height: 100vh; overflow-x: hidden; }
        .sidebar { width: 260px; background: white; border-right: 1px solid #EDF2F7; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; }
        .brand-box { padding: 25px; border-bottom: 1px solid #EDF2F7; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { padding: 20px 10px; flex: 1; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: #718096; text-decoration: none; font-weight: 600; border-radius: 12px; margin-bottom: 5px; transition: all 0.2s; }
        .menu-link:hover, .menu-link.active { background: #F0FFF4; color: var(--primary); }
        .menu-link i { width: 20px; text-align: center; }
        .main-panel { margin-left: 260px; flex: 1; padding: 30px 40px; width: calc(100% - 260px); }
        
        .table-container { background: white; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); overflow: hidden; margin-bottom: 30px; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 18px; font-weight: 800; color: var(--dark); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; font-weight: 700; color: #718096; text-transform: uppercase; background: #F7FAFC; border-bottom: 1px solid #EDF2F7; }
        td { padding: 15px 25px; border-bottom: 1px solid #EDF2F7; color: #4A5568; font-size: 14px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #F7FAFC; }
        
        .badge-role { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; background: #EDF2F7; color: #4A5568; letter-spacing: 0.5px; }
        .badge-admin { background: #EBF8FF; color: #2B6CB0; }
        .badge-manager { background: #FAF5FF; color: #6B46C1; }
        
        .log-date { font-family: 'Open Sans', sans-serif; font-size: 13px; color: #718096; }
        .log-time { font-weight: 700; color: var(--dark); display: block; font-size: 12px; }
        .action-text { font-weight: 700; color: var(--primary); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand-box">
            <img src="assets/logo.png" style="height: 40px; width: auto; border-radius: 6px; margin-right: 10px;">
            <span style="font-size: 18px; font-weight: 800; color: var(--primary);">JEJ Admin</span>
        </div>
        
        <div class="sidebar-menu">
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-bottom: 10px;">MAIN MENU</small>
            
            <a href="admin.php?view=dashboard" class="menu-link">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
            <a href="reservation.php" class="menu-link">
                <i class="fa-solid fa-file-signature"></i> Reservations
            </a>
            <a href="admin.php?view=inventory" class="menu-link">
                <i class="fa-solid fa-list-check"></i> Inventory
            </a>
            <a href="financial.php" class="menu-link">
                <i class="fa-solid fa-coins"></i> Financials
            </a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">MANAGEMENT</small>
            <a href="accounts.php" class="menu-link">
                <i class="fa-solid fa-users-gear"></i> Accounts
            </a>
            <a href="audit_logs.php" class="menu-link active">
                <i class="fa-solid fa-clock-rotate-left"></i> Audit Logs
            </a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">SYSTEM</small>
            <a href="index.php" class="menu-link" target="_blank">
                <i class="fa-solid fa-globe"></i> View Website
            </a>
            <a href="logout.php" class="menu-link" style="color: #E53E3E;">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-panel">
        <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">System Audit Logs</h1>
                <p style="color: #718096;">Track user activities, transactions, and system modifications.</p>
            </div>
            <div>
                <button onclick="window.print()" style="background: white; border: 1px solid #E2E8F0; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; color: #4A5568;"><i class="fa-solid fa-print"></i> Print Logs</button>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <span class="table-title">Recent Activity (Latest 500)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>User Account</th>
                        <th>Action Performed</th>
                        <th>Additional Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if($logs && $logs->num_rows > 0):
                        while($row = $logs->fetch_assoc()): 
                            
                            // Determine badge color based on role
                            $role_class = 'badge-role';
                            if($row['role'] == 'SUPER ADMIN' || $row['role'] == 'ADMIN') $role_class .= ' badge-admin';
                            elseif($row['role'] == 'MANAGER') $role_class .= ' badge-manager';
                    ?>
                    <tr>
                        <td>
                            <span class="log-date"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                            <span class="log-time"><?= date('h:i A', strtotime($row['created_at'])) ?></span>
                        </td>
                        <td>
                            <strong style="color: var(--dark); display: block;"><?= htmlspecialchars($row['fullname'] ?? 'Unknown User') ?></strong>
                            <span class="<?= $role_class ?>"><?= $row['role'] ?? 'N/A' ?></span>
                        </td>
                        <td>
                            <span class="action-text"><?= htmlspecialchars($row['action']) ?></span>
                        </td>
                        <td style="font-size: 12px; color: #718096; max-width: 300px; line-height: 1.5;">
                            <?= htmlspecialchars($row['details']) ?>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #A0AEC0;">
                            <i class="fa-solid fa-folder-open" style="font-size: 30px; margin-bottom: 10px; display: block;"></i>
                            No audit logs recorded yet.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>