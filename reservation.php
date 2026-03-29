<?php
// reservation.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    header("Location: login.php");
    exit();
}

$status_filter = $_GET['status'] ?? 'ALL';
$where_sql = "1";
if($status_filter != 'ALL'){
    $where_sql = "r.status = '$status_filter'";
}

// Alert Handling
$alert_msg = "";
$alert_type = "";
if(isset($_GET['msg'])){
    if($_GET['msg'] == 'approved') { 
        $alert_msg = "Reservation approved! An email has been sent to the buyer notifying them of their 20-day down payment deadline."; 
        $alert_type = "success"; 
    }
    if($_GET['msg'] == 'rejected') { 
        $alert_msg = "Reservation rejected. Lot returned to available inventory."; 
        $alert_type = "error"; 
    }
}

// Fetch Reservations
$query = "SELECT r.*, u.fullname, u.email as user_email, l.block_no, l.lot_no, l.total_price, l.location 
          FROM reservations r 
          JOIN users u ON r.user_id = u.id 
          JOIN lots l ON r.lot_id = l.id 
          WHERE $where_sql 
          ORDER BY r.reservation_date DESC";

$res = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reservations | JEJ Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modern.css">

    <style>
        body { background-color: #F7FAFC; display: flex; min-height: 100vh; overflow-x: hidden; }
        
        /* Layout & Sidebar */
        .sidebar { width: 260px; background: white; border-right: 1px solid #EDF2F7; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; }
        .brand-box { padding: 25px; border-bottom: 1px solid #EDF2F7; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { padding: 20px 10px; flex: 1; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: #718096; text-decoration: none; font-weight: 600; border-radius: 12px; margin-bottom: 5px; transition: all 0.2s; }
        .menu-link:hover, .menu-link.active { background: #F0FFF4; color: var(--primary); }
        .menu-link i { width: 20px; text-align: center; }
        .main-panel { margin-left: 260px; flex: 1; padding: 30px 40px; width: calc(100% - 260px); }

        /* Table */
        .table-container { background: white; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); overflow: hidden; margin-bottom: 30px; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid #EDF2F7; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; font-weight: 700; color: #718096; text-transform: uppercase; background: #F7FAFC; border-bottom: 1px solid #EDF2F7; }
        td { padding: 15px 25px; border-bottom: 1px solid #EDF2F7; color: #4A5568; font-size: 14px; vertical-align: top; }
        tr:hover td { background: #FCFFFF; }

        /* Tabs */
        .tabs { display: flex; gap: 10px; margin-bottom: 25px; }
        .tab-link { padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; color: #718096; background: white; border: 1px solid #E2E8F0; transition: 0.2s; }
        .tab-link.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2); }
        .tab-link:hover:not(.active) { background: #F7FAFC; }

        /* Badges & Buttons */
        .status-badge { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .status-PENDING { background: #FEEBC8; color: #C05621; }
        .status-APPROVED { background: #C6F6D5; color: #2F855A; }
        .status-REJECTED { background: #FED7D7; color: #C53030; }

        .btn-doc { 
            display: inline-flex; align-items: center; gap: 5px; padding: 8px 12px; 
            background: #EBF8FF; color: #3182CE; border: none;
            border-radius: 6px; font-size: 12px; font-weight: 700; 
            text-decoration: none; margin-right: 5px; margin-bottom: 5px; cursor: pointer; transition: 0.2s;
        }
        .btn-doc:hover { background: #BEE3F8; }
        
        .action-forms { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .btn-action { padding: 8px 12px; border:none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; color: white; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s;}
        .btn-approve { background: #48BB78; } .btn-approve:hover { opacity: 0.9; }
        .btn-reject { background: #FC8181; } .btn-reject:hover { opacity: 0.9; }
        .btn-receipt { background: #2D3748; color: white; text-decoration:none; } .btn-receipt:hover { opacity: 0.9; }

        /* Modal Styles */
        .doc-modal {
            display: none; position: fixed; z-index: 2000; inset: 0; 
            background-color: rgba(0,0,0,0.8); backdrop-filter: blur(2px); align-items: center; justify-content: center;
        }
        .doc-modal img { max-width: 90%; max-height: 90vh; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); object-fit: contain; }
        .doc-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer; transition: 0.2s; }
        .doc-close:hover { color: #FC8181; transform: scale(1.1); }
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
            <a href="admin.php?view=dashboard" class="menu-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="reservation.php" class="menu-link active"><i class="fa-solid fa-file-signature"></i> Reservations</a>
            <a href="master_list.php" class="menu-link"><i class="fa-solid fa-map-location-dot"></i> Master List / Map</a>
            <a href="admin.php?view=inventory" class="menu-link"><i class="fa-solid fa-list-check"></i> Inventory</a>
            <a href="financial.php" class="menu-link"><i class="fa-solid fa-coins"></i> Financials</a>
            <a href="payment_tracking.php" class="menu-link"><i class="fa-solid fa-file-invoice-dollar"></i> Payment Tracking</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">MANAGEMENT</small>
            <a href="accounts.php" class="menu-link"><i class="fa-solid fa-users-gear"></i> Accounts</a>
            <a href="audit_logs.php" class="menu-link"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</a>
            <a href="delete_history.php" class="menu-link"><i class="fa-solid fa-trash-can"></i> Delete History</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">SYSTEM</small>
            <a href="index.php" class="menu-link" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a>
            <a href="logout.php" class="menu-link" style="color: #E53E3E;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
        
        <div style="padding: 20px; border-top: 1px solid #EDF2F7;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 35px; height: 35px; background: var(--dark); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">A</div>
                <div style="line-height: 1.2;">
                    <strong style="display: block; font-size: 13px; color: var(--dark);">Administrator</strong>
                    <small style="font-size: 11px; color: #718096;">System Admin</small>
                </div>
            </div>
        </div>
    </div>

    <div class="main-panel">
        
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">Reservation Management</h1>
            <p style="color: #718096;">Review, approve, or reject property reservations.</p>
        </div>

        <?php if($alert_msg): ?>
            <div style="padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; font-size: 14px; background: <?= $alert_type=='success' ? '#F0FFF4' : '#FFF5F5' ?>; color: <?= $alert_type=='success' ? '#2F855A' : '#C53030' ?>; border: 1px solid <?= $alert_type=='success' ? '#C6F6D5' : '#FED7D7' ?>;">
                <i class="fa-solid <?= $alert_type=='success'?'fa-check-circle':'fa-exclamation-circle' ?>" style="margin-right: 8px;"></i>
                <?= $alert_msg ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <a href="reservation.php?status=ALL" class="tab-link <?= $status_filter=='ALL'?'active':'' ?>">All Requests</a>
            <a href="reservation.php?status=PENDING" class="tab-link <?= $status_filter=='PENDING'?'active':'' ?>">Pending</a>
            <a href="reservation.php?status=APPROVED" class="tab-link <?= $status_filter=='APPROVED'?'active':'' ?>">Approved</a>
            <a href="reservation.php?status=REJECTED" class="tab-link <?= $status_filter=='REJECTED'?'active':'' ?>">Rejected</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">Buyer Information</th>
                        <th style="width: 20%;">Property Details</th>
                        <th style="width: 20%;">Documents (Click to View)</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 25%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($res && $res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 800; color: var(--dark); margin-bottom: 5px; font-size: 14px;">
                                    <?= htmlspecialchars($row['fullname']) ?>
                                </div>
                                <div style="font-size:12px; color:#4A5568;"><i class="fa-solid fa-phone" style="font-size:11px; color:#A0AEC0; width: 15px;"></i> <?= $row['contact_number'] ?></div>
                                <div style="font-size:12px; color:#4A5568;"><i class="fa-solid fa-envelope" style="font-size:11px; color:#A0AEC0; width: 15px;"></i> <?= $row['email'] ?? $row['user_email'] ?></div>
                            </td>

                            <td>
                                <div style="font-weight: 800; color: var(--primary);">Block <?= $row['block_no'] ?>, Lot <?= $row['lot_no'] ?></div>
                                <div style="font-size: 12px; color: #718096;"><?= $row['location'] ?></div>
                                <div style="font-family: 'Open Sans', sans-serif; font-weight: 700; font-size: 13px; margin-top: 5px; color: var(--dark);">₱<?= number_format($row['total_price']) ?></div>
                            </td>

                            <td>
                                <button class="btn-doc" onclick="showDoc('uploads/<?= $row['payment_proof'] ?>')" title="View Proof of Payment">
                                    <i class="fa-solid fa-receipt"></i> Proof
                                </button>
                                <button class="btn-doc" onclick="showDoc('uploads/<?= $row['valid_id_file'] ?>')" title="View Valid ID">
                                    <i class="fa-solid fa-id-card"></i> ID
                                </button>
                                <button class="btn-doc" onclick="showDoc('uploads/<?= $row['selfie_with_id'] ?>')" title="View Selfie">
                                    <i class="fa-solid fa-camera"></i> Selfie
                                </button>
                            </td>

                            <td>
                                <span class="status-badge status-<?= $row['status'] ?>"><?= $row['status'] ?></span>
                            </td>

                            <td>
                                <div class="action-forms">
                                    
                                    <a href="receipt.php?id=<?= $row['id'] ?>" target="_blank" class="btn-action btn-receipt">
                                        <i class="fa-solid fa-print"></i> Receipt
                                    </a>

                                    <?php if($row['status'] == 'APPROVED'): ?>
                                        <a href="payment_terms.php?res_id=<?= $row['id'] ?>" class="btn-action" style="background: #3182CE; text-decoration: none;">
                                            <i class="fa-solid fa-calculator"></i> Terms
                                        </a>
                                    <?php endif; ?>

                                    <?php if($row['status'] == 'PENDING'): ?>
                                        <form action="actions.php" method="POST" style="margin: 0;" onsubmit="return confirm('Approve this reservation? This will record the income AND automatically email the buyer about their 20-day down payment deadline.')">
                                            <input type="hidden" name="action" value="approve_res">
                                            <input type="hidden" name="res_id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="lot_id" value="<?= $row['lot_id'] ?>">
                                            <button class="btn-action btn-approve" title="Approve"><i class="fa-solid fa-check"></i> Approve</button>
                                        </form>
                                        <form action="actions.php" method="POST" style="margin: 0;" onsubmit="return confirm('Reject this reservation?')">
                                            <input type="hidden" name="action" value="reject_res">
                                            <input type="hidden" name="res_id" value="<?= $row['id'] ?>">
                                            <button class="btn-action btn-reject" title="Reject"><i class="fa-solid fa-xmark"></i> Reject</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: #A0AEC0;">
                            <i class="fa-solid fa-folder-open" style="font-size: 30px; margin-bottom: 10px; display: block;"></i>
                            No reservations found.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="docModal" class="doc-modal" onclick="closeDoc()">
        <span class="doc-close">&times;</span>
        <img id="docImage" src="">
    </div>

    <script>
        function showDoc(src) {
            document.getElementById('docImage').src = src;
            document.getElementById('docModal').style.display = 'flex';
        }
        function closeDoc() {
            document.getElementById('docModal').style.display = 'none';
        }
        document.addEventListener('keydown', function(e){
            if(e.key === "Escape") closeDoc();
        });
    </script>

</body>
</html>