<?php
// payment_terms.php
include 'config.php';
checkAdmin();

if(!isset($_GET['res_id'])){
    header("Location: reservation.php");
    exit();
}

$res_id = $_GET['res_id'];
$alert_msg = "";

if(isset($_POST['save_terms'])){
    $type = $_POST['payment_type'];
    $months = $_POST['installment_months'] ?? NULL;
    $monthly = $_POST['monthly_payment'] ?? NULL;

    $stmt = $conn->prepare("UPDATE reservations SET payment_type=?, installment_months=?, monthly_payment=? WHERE id=?");
    $stmt->bind_param("sidi", $type, $months, $monthly, $res_id);
    if($stmt->execute()){
        $alert_msg = "Payment terms successfully saved!";
    }
}

// Fetch Reservation Data
$resData = $conn->query("
    SELECT r.*, u.fullname, l.block_no, l.lot_no, l.total_price 
    FROM reservations r 
    JOIN users u ON r.user_id = u.id 
    JOIN lots l ON r.lot_id = l.id 
    WHERE r.id='$res_id'
")->fetch_assoc();

if(!$resData) { die("Reservation not found."); }

$total_price = $resData['total_price'];
$down_payment = $total_price * 0.20; // Assuming 20% down payment
$balance = $total_price - $down_payment;

$monthly_12 = $balance / 12;
$monthly_24 = $balance / 24;
$monthly_36 = $balance / 36;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Terms | JEJ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modern.css">
    <style>
        body { background-color: #F7FAFC; padding: 40px; font-family: 'Montserrat', sans-serif; }
        .card { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto; }
        .breakdown-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px; }
        .plan-box { border: 1px solid #E2E8F0; padding: 20px; border-radius: 12px; text-align: center; background: #F9FAFB; }
        .plan-box h3 { color: #2D3748; margin-bottom: 5px; }
        .plan-box .price { font-size: 20px; font-weight: 800; color: #3182CE; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; margin-top: 8px; }
        .btn-save { background: #48BB78; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; margin-top: 20px; }
    </style>
</head>
<body>

    <a href="reservation.php" style="text-decoration:none; color:#718096; font-weight:700;"><i class="fa-solid fa-arrow-left"></i> Back to Reservations</a>

    <div class="card" style="margin-top: 20px;">
        <h2>Payment Terms Configuration</h2>
        <p><strong>Buyer:</strong> <?= $resData['fullname'] ?> | <strong>Lot:</strong> Block <?= $resData['block_no'] ?> Lot <?= $resData['lot_no'] ?></p>
        
        <?php if($alert_msg): ?>
            <div style="background: #C6F6D5; color: #2F855A; padding: 10px; border-radius: 8px; margin-bottom: 20px;"><?= $alert_msg ?></div>
        <?php endif; ?>

        <div style="background: #EBF8FF; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 700;">
                <span>Total Contract Price:</span>
                <span>₱<?= number_format($total_price, 2) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 15px; color: #4A5568; margin-top: 10px;">
                <span>Required Down Payment (20%):</span>
                <span>₱<?= number_format($down_payment, 2) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 15px; color: #E53E3E; margin-top: 5px; font-weight: 700;">
                <span>Remaining Balance:</span>
                <span>₱<?= number_format($balance, 2) ?></span>
            </div>
        </div>

        <h3 style="margin-bottom: 10px;">Installment Breakdown Estimates</h3>
        <div class="breakdown-grid">
            <div class="plan-box">
                <h3>12 Months</h3>
                <div class="price">₱<?= number_format($monthly_12, 2) ?>/mo</div>
            </div>
            <div class="plan-box">
                <h3>24 Months</h3>
                <div class="price">₱<?= number_format($monthly_24, 2) ?>/mo</div>
            </div>
            <div class="plan-box">
                <h3>36 Months</h3>
                <div class="price">₱<?= number_format($monthly_36, 2) ?>/mo</div>
            </div>
        </div>

        <hr style="border-top: 1px solid #E2E8F0; margin: 30px 0;">

        <form method="POST">
            <h3>Set Final Terms for Buyer</h3>
            <div style="margin-bottom: 15px;">
                <label style="font-weight: 700; font-size: 14px;">Select Payment Type</label>
                <select name="payment_type" id="payment_type" class="form-control" onchange="toggleInstallment()" required>
                    <option value="" disabled selected>Select...</option>
                    <option value="CASH" <?= $resData['payment_type'] == 'CASH' ? 'selected' : '' ?>>Spot Cash</option>
                    <option value="INSTALLMENT" <?= $resData['payment_type'] == 'INSTALLMENT' ? 'selected' : '' ?>>Installment</option>
                </select>
            </div>

            <div id="installment_fields" style="display: <?= $resData['payment_type'] == 'INSTALLMENT' ? 'block' : 'none' ?>;">
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: 700; font-size: 14px;">Months to Pay</label>
                    <select name="installment_months" id="installment_months" class="form-control" onchange="setMonthly()">
                        <option value="12" <?= $resData['installment_months'] == 12 ? 'selected' : '' ?>>12 Months</option>
                        <option value="24" <?= $resData['installment_months'] == 24 ? 'selected' : '' ?>>24 Months</option>
                        <option value="36" <?= $resData['installment_months'] == 36 ? 'selected' : '' ?>>36 Months</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: 700; font-size: 14px;">Monthly Amortization (PHP)</label>
                    <input type="number" step="0.01" name="monthly_payment" id="monthly_payment" class="form-control" value="<?= $resData['monthly_payment'] ?>">
                </div>
            </div>

            <button type="submit" name="save_terms" class="btn-save">Save Payment Terms</button>
        </form>
    </div>

    <script>
        const m12 = <?= $monthly_12 ?>;
        const m24 = <?= $monthly_24 ?>;
        const m36 = <?= $monthly_36 ?>;

        function toggleInstallment() {
            var type = document.getElementById('payment_type').value;
            document.getElementById('installment_fields').style.display = (type === 'INSTALLMENT') ? 'block' : 'none';
            if(type === 'INSTALLMENT' && !document.getElementById('monthly_payment').value) {
                setMonthly();
            }
        }

        function setMonthly() {
            var months = document.getElementById('installment_months').value;
            var input = document.getElementById('monthly_payment');
            if(months == 12) input.value = m12.toFixed(2);
            if(months == 24) input.value = m24.toFixed(2);
            if(months == 36) input.value = m36.toFixed(2);
        }
    </script>
</body>
</html>