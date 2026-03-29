<?php
// delete_history.php
include 'config.php';

// Security Check: Only Admin and Super Admin should see deleted history
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN'])){
    header("Location: dashboard.php");
    exit();
}

$alert_msg = "";
$alert_type = "";

// --- RESTORE LOGIC ---
if(isset($_POST['action']) && $_POST['action'] == 'restore'){
    $history_id = (int)$_POST['history_id'];
    
    // Fetch the archived record
    $archived = $conn->query("SELECT * FROM delete_history WHERE id='$history_id'")->fetch_assoc();
    
    if($archived){
        $data = json_decode($archived['record_data'], true);
        $module = $archived['module_name'];
        $success = false;

        if($module == 'Property Inventory'){
            // Restore Lot
            $stmt = $conn->prepare("INSERT INTO lots (id, location, property_type, block_no, lot_no, area, price_per_sqm, total_price, status, property_overview, latitude, longitude, lot_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssidddssds", 
                $data['id'], $data['location'], $data['property_type'], $data['block_no'], $data['lot_no'], 
                $data['area'], $data['price_per_sqm'], $data['total_price'], $data['status'], 
                $data['property_overview'], $data['latitude'], $data['longitude'], $data['lot_image']
            );
            $success = $stmt->execute();
        } 
        elseif($module == 'User Accounts') {
            // Restore User (Set default password since we didn't save the old hash)
            $default_pass = md5('jej123456');
            $stmt = $conn->prepare("INSERT INTO users (id, fullname, phone, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $data['id'], $data['fullname'], $data['phone'], $data['email'], $default_pass, $data['role']);
            $success = $stmt->execute();
        }

        if($success){
            // Remove from archive since it is restored
            $conn->query("DELETE FROM delete_history WHERE id='$history_id'");
            
            // Log Activity
            logActivity($conn, $_SESSION['user_id'], "Restored Data", "Restored $module (ID: {$data['id']}) from Archive.");
            
            $alert_msg = "Record restored successfully!" . ($module == 'User Accounts' ? " Default password is 'jej123456'" : "");
            $alert_type = "success";
        } else {
            $alert_msg = "Failed to restore record. ID might already exist. Error: " . $conn->error;
            $alert_type = "error";
        }
    }
}

// --- PERMANENT DELETE (SOFT DELETE FROM ADMIN UI) ---
if(isset($_POST['action']) && $_POST['action'] == 'permanent_delete'){
    $history_id = (int)$_POST['history_id'];
    $admin_password_entered = md5($_POST['admin_password']);
    $admin_id = $_SESSION['user_id'];

    // Verify Admin Password
    $verify = $conn->query("SELECT password FROM users WHERE id='$admin_id'")->fetch_assoc();
    
    if($verify && $verify['password'] === $admin_password_entered){
        // Soft Delete: Hide from UI but keep in DB
        $conn->query("UPDATE delete_history SET is_hidden = 1 WHERE id='$history_id'");
        
        logActivity($conn, $_SESSION['user_id'], "Permanently Deleted Archive", "Hidden archive ID: $history_id from admin panel.");
        
        $alert_msg = "Record permanently removed from Admin Panel.";
        $alert_type = "success";
    } else {
        $alert_msg = "Incorrect Admin Password. Deletion aborted.";
        $alert_type = "error";
    }
}

// Fetch delete history (Only those NOT hidden)
$query = "SELECT d.*, u.fullname, u.role FROM delete_history d 
          LEFT JOIN users u ON d.deleted_by = u.id 
          WHERE d.is_hidden = 0 
          ORDER BY d.deleted_at DESC LIMIT 500";
$history = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete History | JEJ Surveying</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modern.css">
    <style>
        body { background-color: #F7FAFC; display: flex; min-height: 100vh; overflow-x: hidden; }
        .sidebar { width: 260px; background: white; border-right: 1px solid #EDF2F7; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; }
        .brand-box { padding: 25px; border-bottom: 1px solid #EDF2F7; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { padding: 20px 10px; flex: 1; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: #718096; text-decoration: none; font-weight: 600; border-radius: 12px; margin-bottom: 5px; transition: all 0.2s; }
        .menu-link:hover, .menu-link.active { background: #FFF5F5; color: #E53E3E; }
        .menu-link i { width: 20px; text-align: center; }
        .main-panel { margin-left: 260px; flex: 1; padding: 30px 40px; width: calc(100% - 260px); }
        
        .table-container { background: white; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); overflow: hidden; margin-bottom: 30px; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; font-weight: 700; color: #718096; text-transform: uppercase; background: #F7FAFC; border-bottom: 1px solid #EDF2F7; }
        td { padding: 15px 25px; border-bottom: 1px solid #EDF2F7; color: #4A5568; font-size: 14px; vertical-align: middle; }
        tr:hover td { background: #F7FAFC; }
        
        .badge-module { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; background: #EDF2F7; color: #4A5568; text-transform: uppercase; letter-spacing: 0.5px; }
        .log-date { font-family: 'Open Sans', sans-serif; font-size: 13px; color: #718096; }
        .log-time { font-weight: 700; color: var(--dark); display: block; font-size: 12px; }

        .btn-action { border: none; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-view { background: #EBF8FF; color: #3182CE; } .btn-view:hover { background: #BEE3F8; }
        .btn-restore { background: #C6F6D5; color: #2F855A; } .btn-restore:hover { background: #9AE6B4; }
        .btn-perm { background: #FED7D7; color: #C53030; } .btn-perm:hover { background: #FEB2B2; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background-color: #fefefe; padding: 30px; border-radius: 12px; border: 1px solid #888; width: 90%; max-width: 600px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); position: relative; }
        .close { color: #aaa; position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        pre { background: #EDF2F7; padding: 15px; border-radius: 8px; font-size: 13px; color: #2D3748; overflow-x: auto; font-family: monospace; white-space: pre-wrap; max-height: 400px;}
        
        .form-control { width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; background: #F9FAFB; font-family: inherit; font-size: 14px; margin-bottom: 15px; outline:none;}
        .form-control:focus { background: white; border-color: #E53E3E; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand-box">
            <img src="assets/logo.png" style="height: 40px; width: auto; border-radius: 6px; margin-right: 10px;">
            <span style="font-size: 18px; font-weight: 800; color: #E53E3E;">System Archives</span>
        </div>
        
        <div class="sidebar-menu">
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-bottom: 10px;">MAIN MENU</small>
            <a href="admin.php?view=dashboard" class="menu-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="reservation.php" class="menu-link"><i class="fa-solid fa-file-signature"></i> Reservations</a>
            <a href="admin.php?view=inventory" class="menu-link"><i class="fa-solid fa-list-check"></i> Inventory</a>
            <a href="financial.php" class="menu-link"><i class="fa-solid fa-coins"></i> Financials</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">MANAGEMENT</small>
            <a href="accounts.php" class="menu-link"><i class="fa-solid fa-users-gear"></i> Accounts</a>
            <a href="audit_logs.php" class="menu-link"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</a>
            <a href="delete_history.php" class="menu-link active" style="color: #E53E3E;"><i class="fa-solid fa-trash-can"></i> Delete History</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">SYSTEM</small>
            <a href="logout.php" class="menu-link" style="color: #E53E3E;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div class="main-panel">
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);"><i class="fa-solid fa-trash-can" style="color: #E53E3E; margin-right: 10px;"></i>Deleted Records Archive</h1>
            <p style="color: #718096;">Track, restore, or permanently hide deleted data.</p>
        </div>

        <?php if($alert_msg): ?>
            <div style="padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; font-size: 14px; background: <?= $alert_type=='success' ? '#F0FFF4' : '#FFF5F5' ?>; color: <?= $alert_type=='success' ? '#2F855A' : '#C53030' ?>; border: 1px solid <?= $alert_type=='success' ? '#C6F6D5' : '#FED7D7' ?>;">
                <i class="fa-solid <?= $alert_type=='success'?'fa-check-circle':'fa-exclamation-circle' ?>" style="margin-right: 8px;"></i>
                <?= $alert_msg ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Deleted Date</th>
                        <th>Module / Area</th>
                        <th>Original ID</th>
                        <th>Deleted By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($history && $history->num_rows > 0): ?>
                        <?php while($row = $history->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="log-date"><?= date('M d, Y', strtotime($row['deleted_at'])) ?></span>
                                <span class="log-time"><?= date('h:i A', strtotime($row['deleted_at'])) ?></span>
                            </td>
                            <td><span class="badge-module"><?= htmlspecialchars($row['module_name']) ?></span></td>
                            <td style="font-weight: 700;">#<?= $row['record_id'] ?></td>
                            <td>
                                <strong style="color: var(--dark); display: block;"><?= htmlspecialchars($row['fullname'] ?? 'Unknown User') ?></strong>
                                <span style="font-size: 11px; color: #718096;"><?= $row['role'] ?? 'N/A' ?></span>
                            </td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <button class="btn-action btn-view" onclick='viewData(<?= json_encode(htmlspecialchars($row['record_data'], ENT_QUOTES, 'UTF-8')) ?>)'>
                                        <i class="fa-solid fa-eye"></i> View
                                    </button>

                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to restore this record back to the live system?');">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="history_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn-action btn-restore"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    </form>

                                    <button class="btn-action btn-perm" onclick="openDeleteModal(<?= $row['id'] ?>)">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: #A0AEC0;"><i class="fa-solid fa-folder-open" style="font-size: 30px; margin-bottom: 10px; display: block;"></i> Recycle bin is empty.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="dataModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('dataModal')">&times;</span>
            <h3 style="margin-top: 0; color: #E53E3E;"><i class="fa-solid fa-database"></i> Archived Record Data</h3>
            <p style="font-size: 12px; color: #718096; margin-bottom: 15px;">This is the raw data captured at the exact moment it was deleted.</p>
            <pre id="jsonDataContent"></pre>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            <h3 style="margin-top: 0; color: #C53030;"><i class="fa-solid fa-triangle-exclamation"></i> Security Verification</h3>
            <p style="font-size: 13px; color: #4A5568; margin-bottom: 20px;">
                You are about to permanently hide this record from the Admin Panel. It will remain in the database backend for strict audit compliance, but cannot be restored from this interface.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="permanent_delete">
                <input type="hidden" name="history_id" id="hidden_history_id" value="">
                
                <label style="display:block; font-size:12px; font-weight:700; color:#718096; margin-bottom:5px;">Admin Password Required</label>
                <input type="password" name="admin_password" class="form-control" placeholder="Enter your password to confirm..." required>
                
                <button type="submit" style="background: #E53E3E; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 700; width: 100%; cursor: pointer;">
                    <i class="fa-solid fa-trash-can"></i> Confirm Permanent Removal
                </button>
            </form>
        </div>
    </div>

    <script>
        // View Data Modal Logic
        function viewData(rawData) {
            try {
                let decodedStr = document.createElement("textarea");
                decodedStr.innerHTML = rawData;
                let jsonObj = JSON.parse(decodedStr.value);
                document.getElementById('jsonDataContent').textContent = JSON.stringify(jsonObj, null, 4);
            } catch (e) {
                document.getElementById('jsonDataContent').textContent = "Error parsing data: " + rawData;
            }
            document.getElementById('dataModal').style.display = 'flex';
        }

        // Delete Password Modal Logic
        function openDeleteModal(historyId) {
            document.getElementById('hidden_history_id').value = historyId;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        // Shared Modal Close Logic
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('dataModal')) closeModal('dataModal');
            if (event.target == document.getElementById('deleteModal')) closeModal('deleteModal');
        }
    </script>
</body>
</html>