<?php
// payment_tracking.php
include 'config.php';
checkAdmin();

$alert_msg = "";
$alert_type = "";

// --- HANDLE SEND REMINDER EMAIL ---
if(isset($_POST['send_reminder'])){
    $res_id = $_POST['res_id'];
    
    // Fetch reservation details for the email
    $resData = $conn->query("
        SELECT r.*, u.email, u.fullname, l.block_no, l.lot_no, l.total_price 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        JOIN lots l ON r.lot_id = l.id 
        WHERE r.id='$res_id'
    ")->fetch_assoc();

    if($resData){
        $down_payment = number_format($resData['total_price'] * 0.20, 2);
        
        // Calculate exactly when the 20 days ends for the email text
        $res_time = strtotime($resData['reservation_date']);
        $deadline_exact = date('F j, Y \a\t g:i A', strtotime('+20 days', $res_time));
        
        require 'PHPMailer/Exception.php';
        require 'PHPMailer/PHPMailer.php';
        require 'PHPMailer/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            
            // Your email credentials
            $mail->Username   = 'publicotavern@gmail.com'; 
            $mail->Password   = 'xcvgrzzsjvnbtsti';    
            
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // Bypass SSL certificate verification for local environments
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom('publicotavern@gmail.com', 'JEJ Surveying');
            $mail->addAddress($resData['email']);

            $mail->isHTML(true);
            $mail->Subject = 'URGENT: Down Payment Reminder - JEJ Surveying';
            $mail->Body    = "Hello {$resData['fullname']},<br><br>
                              This is an urgent reminder regarding your approved reservation for <b>Block {$resData['block_no']} Lot {$resData['lot_no']}</b>.<br><br>
                              To fully secure your property, please settle your 20% Down Payment amounting to <b>₱{$down_payment}</b>.<br><br>
                              <b style='color:red;'>Your payment deadline is strictly on or before: {$deadline_exact}</b>.<br><br>
                              If you have already paid, please disregard this email.<br><br>
                              Thank you,<br>JEJ Surveying Team";

            $mail->send();
            $alert_msg = "Reminder email sent successfully to " . $resData['fullname'];
            $alert_type = "success";
        } catch (Exception $e) {
            $alert_msg = "Failed to send email. Mailer Error: {$mail->ErrorInfo}";
            $alert_type = "error";
        }
    }
}

// --- FETCH APPROVED RESERVATIONS ---
$query = "SELECT r.*, u.fullname, u.email as user_email, l.block_no, l.lot_no, l.total_price 
          FROM reservations r 
          JOIN users u ON r.user_id = u.id 
          JOIN lots l ON r.lot_id = l.id 
          WHERE r.status = 'APPROVED' 
          ORDER BY r.reservation_date DESC";
$res = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Tracking | JEJ Admin</title>
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
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 20px; font-size: 11px; font-weight: 700; color: #718096; text-transform: uppercase; background: #F7FAFC; border-bottom: 1px solid #EDF2F7; }
        td { padding: 15px 20px; border-bottom: 1px solid #EDF2F7; color: #4A5568; font-size: 13px; vertical-align: top; }
        tr:hover td { background: #FCFFFF; }

        .btn-reminder { background: #E53E3E; color: white; border: none; padding: 8px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; transition: 0.2s;}
        .btn-reminder:hover { background: #C53030; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .badge-cash { background: #C6F6D5; color: #2F855A; }
        .badge-install { background: #BEE3F8; color: #2B6CB0; }
        .badge-none { background: #EDF2F7; color: #4A5568; }
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
            <a href="reservation.php" class="menu-link"><i class="fa-solid fa-file-signature"></i> Reservations</a>
            <a href="admin.php?view=inventory" class="menu-link"><i class="fa-solid fa-list-check"></i> Inventory</a>
            <a href="financial.php" class="menu-link"><i class="fa-solid fa-coins"></i> Financials</a>
            <a href="payment_tracking.php" class="menu-link active"><i class="fa-solid fa-file-invoice-dollar"></i> Payment Tracking</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">MANAGEMENT</small>
            <a href="accounts.php" class="menu-link"><i class="fa-solid fa-users-gear"></i> Accounts</a>
            <a href="audit_logs.php" class="menu-link"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</a>

            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">SYSTEM</small>
            <a href="index.php" class="menu-link" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a>
            <a href="logout.php" class="menu-link" style="color: #E53E3E;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div class="main-panel">
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">Payment Tracking</h1>
            <p style="color: #718096;">Track buyer payment terms and exact down payment deadlines.</p>
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
                        <th>Buyer Info</th>
                        <th>Property</th>
                        <th>Financials & Deadline</th>
                        <th>Terms Setup</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($res && $res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()):
                            $dp = $row['total_price'] * 0.20;
                            $balance = $row['total_price'] - $dp;

                            if (!empty($row['reservation_date'])) {
                                $res_time = strtotime($row['reservation_date']);
                                $deadline_timestamp = strtotime('+20 days', $res_time);
                                $deadline_formatted = date('M d, Y g:i A', $deadline_timestamp);
                                $is_overdue = time() > $deadline_timestamp;
                            } else {
                                $deadline_formatted = "Date Error";
                                $is_overdue = false;
                            }

                            // Check if DP is paid for this reservation.
                            $dp_paid = false;
                            $dp_amount = round($row['total_price'] * 0.20, 2);
                            $res_id = (int)$row['id'];
                            $desc_like = "%Down Payment%Res#$res_id%";
                            $dp_query = $conn->prepare("SELECT id FROM transactions WHERE type='INCOME' AND description LIKE ? AND ABS(amount - ?) < 1 LIMIT 1");
                            $dp_query->bind_param("sd", $desc_like, $dp_amount);
                            $dp_query->execute();
                            $dp_result = $dp_query->get_result();
                            if ($dp_result && $dp_result->num_rows > 0) {
                                $dp_paid = true;
                            }
                            $dp_query->close();
                        ?>
                        <tr>
                            <td>
                                <strong style="color: var(--dark);"><?= htmlspecialchars($row['fullname']) ?></strong><br>
                                <span style="font-size: 11px; color: #718096;"><?= $row['email'] ?? $row['user_email'] ?></span>
                            </td>
                            <td>
                                <strong style="color: var(--primary);">Block <?= $row['block_no'] ?> Lot <?= $row['lot_no'] ?></strong>
                            </td>
                            <td>
                                <div style="font-size: 12px; margin-bottom: 4px;">TCP: <strong>₱<?= number_format($row['total_price'], 2) ?></strong></div>
                                <div style="font-size: 12px; margin-bottom: 4px; color: #E53E3E;">20% DP: <strong>₱<?= number_format($dp, 2) ?></strong></div>
                                <div style="font-size: 11px; color: #718096; margin-bottom: 4px;">Bal: ₱<?= number_format($balance, 2) ?></div>

                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #E2E8F0;">
                                    <div style="font-size: 10px; font-weight: 700; color: #A0AEC0; text-transform: uppercase; margin-bottom: 3px;">DP Deadline:</div>
                                    <div style="font-size: 12px; font-weight: <?= $is_overdue ? '800' : '700' ?>; color: <?= $is_overdue ? '#E53E3E' : '#2D3748' ?>;">
                                        <i class="fa-regular fa-clock" style="margin-right: 3px;"></i> <?= $deadline_formatted ?>
                                    </div>
                                    <?php if($is_overdue): ?>
                                        <span style="background: #FED7D7; color: #C53030; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; margin-top: 5px; display: inline-block;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Overdue
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if($row['payment_type'] == 'CASH'): ?>
                                    <span class="badge badge-cash">Spot Cash</span>
                                <?php elseif($row['payment_type'] == 'INSTALLMENT'): ?>
                                    <span class="badge badge-install">Installment (<?= $row['installment_months'] ?> mos)</span><br>
                                    <span style="font-size: 11px; color: #718096; display:inline-block; margin-top:4px;">₱<?= number_format($row['monthly_payment'], 2) ?>/mo</span>
                                <?php else: ?>
                                    <span class="badge badge-none">Not Set Yet</span><br>
                                    <a href="payment_terms.php?res_id=<?= $row['id'] ?>" style="font-size: 11px; color: #3182CE; display:inline-block; margin-top:4px; text-decoration:none; font-weight:700;">Set Now</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dp_paid): ?>
                                    <button type="button" class="btn-reminder" style="background:#3182CE;" onclick="showBillingModal(<?= (int)$row['id'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>')">
                                        <i class="fa-solid fa-file-invoice"></i> Show Billing
                                    </button>
                                <?php else: ?>
                                    <form method="POST" onsubmit="return confirm('Send an urgent reminder email to this buyer including the exact deadline?');">
                                        <input type="hidden" name="res_id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" name="send_reminder" class="btn-reminder">
                                            <i class="fa-solid fa-envelope"></i> Remind DP
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 30px; color: #A0AEC0;">No approved reservations found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="billingModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:white; border-radius:12px; max-width:900px; width:96vw; max-height:88vh; overflow:auto; margin:40px auto; padding:24px; position:relative;">
                <button onclick="closeBillingModal()" style="position:absolute; top:10px; right:15px; background:none; border:none; font-size:22px; color:#E53E3E; cursor:pointer;">&times;</button>
                <h2 style="font-size:20px; font-weight:800; margin-bottom:10px;">Statement of Account</h2>
                <div id="billingContent">
                    <div style="text-align:center; color:#A0AEC0;">Loading...</div>
                </div>
            </div>
        </div>

        <script>
        function showBillingModal(resId, buyerName) {
            var modal = document.getElementById('billingModal');
            var content = document.getElementById('billingContent');
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align:center; color:#A0AEC0;">Loading statement for ' + buyerName + '...</div>';

            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'statement_of_account.php?res_id=' + encodeURIComponent(resId), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    content.innerHTML = xhr.responseText;
                } else {
                    content.innerHTML = '<div style="color:#E53E3E;">Failed to load billing info.</div>';
                }
            };
            xhr.onerror = function() {
                content.innerHTML = '<div style="color:#E53E3E;">Server communication error while loading billing info.</div>';
            };
            xhr.send();
        }

        function closeBillingModal() {
            document.getElementById('billingModal').style.display = 'none';
        }

        window.addEventListener('click', function(e) {
            var modal = document.getElementById('billingModal');
            if (e.target === modal) closeBillingModal();
        });
        </script>
    </div>
</body>
</html>