<?php
// print_check_voucher.php
include 'config.php';
checkAdmin();

if(!isset($_GET['cv'])) die("No Check Voucher Number provided.");
$cv = $_GET['cv'];

$query = "SELECT t.*, c.name as category, u.fullname FROM transactions t 
          LEFT JOIN accounting_categories c ON t.category_id = c.id
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE t.or_number = ? AND t.is_check = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $cv);
$stmt->execute();
$res = $stmt->get_result();
if($res->num_rows == 0) die("Voucher not found or not a valid check transaction.");
$t = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check Voucher - <?= $cv ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Open Sans', sans-serif; background: #E2E8F0; display: flex; justify-content: center; padding: 40px; margin: 0; color: #000; }
        .voucher-card { background: #fff; width: 100%; max-width: 800px; padding: 40px; box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 1px; }
        .header h4 { margin: 5px 0 0 0; font-weight: 600; font-size: 14px; color: #4A5568; }
        .cv-meta { text-align: right; }
        .cv-meta div { font-size: 14px; margin-bottom: 5px; }
        .cv-meta strong { color: #E53E3E; font-size: 18px; }

        .details-grid { display: grid; grid-template-columns: 150px 1fr; gap: 10px 20px; margin-bottom: 30px; font-size: 14px; }
        .details-grid .label { font-weight: 700; color: #4A5568; text-transform: uppercase; font-size: 12px; align-self: center; }
        .details-grid .value { font-weight: 700; font-size: 16px; border-bottom: 1px solid #CBD5E0; padding-bottom: 5px; }
        .details-grid .amount-value { font-size: 20px; color: #000; border-bottom: 1px solid #CBD5E0; padding-bottom: 5px; font-weight: 800; }

        .particulars { border: 2px solid #000; min-height: 150px; padding: 20px; margin-bottom: 40px; }
        .particulars-title { font-weight: 800; text-transform: uppercase; margin-bottom: 15px; font-size: 14px; border-bottom: 1px solid #000; display: inline-block; padding-bottom: 5px; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px; text-align: center; }
        .sig-box { margin-top: 50px; }
        .sig-line { border-bottom: 1px solid #000; margin-bottom: 10px; height: 30px; }
        .sig-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #4A5568; }

        @media print {
            body { background: #fff; padding: 0; }
            .voucher-card { box-shadow: none; max-width: 100%; border: none; padding: 20px; }
            .no-print { display: none; }
        }
        
        .btn-print { display: block; width: 100%; text-align: center; background: #805AD5; color: white; border: none; padding: 15px; font-weight: 800; font-size: 16px; cursor: pointer; text-decoration: none; border-radius: 8px; margin-bottom: 20px;}
    </style>
</head>
<body>
    <div style="width: 100%; max-width: 800px;">
        <button class="no-print btn-print" onclick="window.print()">PRINT CHECK VOUCHER</button>
        
        <div class="voucher-card">
            <div class="header">
                <div>
                    <h1>CHECK VOUCHER</h1>
                    <h4>CAFE EMMANUEL / ECO LAND ACCOUNTING</h4>
                </div>
                <div class="cv-meta">
                    <div>No. <strong><?= $t['or_number'] ?></strong></div>
                    <div>Date: <strong><?= date('F d, Y', strtotime($t['transaction_date'])) ?></strong></div>
                </div>
            </div>

            <div class="details-grid">
                <div class="label">Pay To:</div>
                <div class="value"><?= htmlspecialchars($t['payee']) ?></div>

                <div class="label">Amount (PHP):</div>
                <div class="amount-value">₱ <?= number_format($t['amount'], 2) ?></div>

                <div class="label">Bank Name:</div>
                <div class="value"><?= htmlspecialchars($t['bank_name']) ?></div>

                <div class="label">Check Number:</div>
                <div class="value"><?= htmlspecialchars($t['check_number']) ?></div>
            </div>

            <div class="particulars">
                <div class="particulars-title">Particulars / Payment Details</div>
                <p style="margin: 0; font-size: 15px; line-height: 1.6; font-weight: 600;">
                    <?= nl2br(htmlspecialchars($t['description'])) ?>
                </p>
                <br>
                <div style="font-size: 12px; color: #718096; font-weight: 600;">Account Code/Category: <?= htmlspecialchars($t['category']) ?></div>
            </div>

            <div class="signatures">
                <div class="sig-box">
                    <div class="sig-line" style="font-weight: 800; font-size: 14px; display: flex; align-items: end; justify-content: center;"><?= htmlspecialchars($t['fullname']) ?></div>
                    <div class="sig-title">Prepared By</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-title">Approved By</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-title" style="color: #E53E3E;">Received By (Payee) & Date</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>