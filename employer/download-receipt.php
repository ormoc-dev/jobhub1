<?php
include '../config.php';
requireRole('employer');

// Get subscription ID from URL
$subscriptionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$subscriptionId) {
    die('Invalid subscription ID.');
}

// Get subscription details with user and plan information
$stmt = $pdo->prepare("
    SELECT us.*, sp.plan_name, sp.plan_type, sp.price, sp.description,
           u.email, u.username,
           c.company_name, c.contact_first_name, c.contact_last_name, 
           c.contact_email, c.contact_number, c.location_address
    FROM user_subscriptions us
    JOIN subscription_plans sp ON us.plan_id = sp.id
    JOIN users u ON us.user_id = u.id
    LEFT JOIN companies c ON u.id = c.user_id
    WHERE us.id = ? AND us.user_id = ?
");
$stmt->execute([$subscriptionId, $_SESSION['user_id']]);
$subscription = $stmt->fetch();

if (!$subscription) {
    die('Subscription not found or you do not have permission to view this receipt.');
}

// Only allow download if payment is paid
if ($subscription['payment_status'] != 'paid') {
    die('Receipt is only available for paid subscriptions.');
}

// Generate receipt HTML
$invoiceNumber = 'INV-' . str_pad($subscription['id'], 6, '0', STR_PAD_LEFT);
$contactName = trim(($subscription['contact_first_name'] ?? '') . ' ' . ($subscription['contact_last_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($invoiceNumber); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background: #f5f5f5;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #0d6efd;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .info-section {
            flex: 1;
        }
        .info-section h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 10px;
            border-bottom: 2px solid #eee;
            padding-bottom: 5px;
        }
        .info-section p {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        .invoice-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .invoice-details h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .total-section {
            background: #0d6efd;
            color: white;
            padding: 20px;
            border-radius: 5px;
            text-align: right;
            margin-top: 20px;
        }
        .total-section .total-label {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .total-section .total-amount {
            font-size: 32px;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-paid {
            background: #10b981;
            color: white;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>WORKLINK</h1>
            <p>Subscription Receipt</p>
        </div>

        <div class="receipt-info">
            <div class="info-section">
                <h3>Bill To:</h3>
                <p><strong><?php echo htmlspecialchars($subscription['company_name'] ?? 'N/A'); ?></strong></p>
                <?php if ($contactName): ?>
                    <p><?php echo htmlspecialchars($contactName); ?></p>
                <?php endif; ?>
                <p><?php echo htmlspecialchars($subscription['contact_email'] ?? $subscription['email']); ?></p>
                <p><?php echo htmlspecialchars($subscription['contact_number'] ?? 'N/A'); ?></p>
                <?php if ($subscription['location_address']): ?>
                    <p><?php echo htmlspecialchars($subscription['location_address']); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-section">
                <h3>Invoice Details:</h3>
                <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoiceNumber); ?></p>
                <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($subscription['created_at'])); ?></p>
                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($subscription['transaction_id'] ?? 'N/A'); ?></p>
                <p>
                    <strong>Status:</strong> 
                    <span class="status-badge status-paid">PAID</span>
                </p>
            </div>
        </div>

        <div class="invoice-details">
            <h3>Subscription Details</h3>
            <div class="detail-row">
                <span class="detail-label">Plan Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($subscription['plan_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Plan Type:</span>
                <span class="detail-value"><?php echo ucfirst($subscription['plan_type']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Description:</span>
                <span class="detail-value"><?php echo htmlspecialchars($subscription['description']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Start Date:</span>
                <span class="detail-value"><?php echo date('F d, Y', strtotime($subscription['start_date'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">End Date:</span>
                <span class="detail-value"><?php echo $subscription['end_date'] ? date('F d, Y', strtotime($subscription['end_date'])) : 'N/A'; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value"><?php echo ucfirst($subscription['payment_method'] ?? 'Manual'); ?></span>
            </div>
        </div>

        <div class="total-section">
            <div class="total-label">Total Amount Paid</div>
            <div class="total-amount">₱<?php echo number_format($subscription['price'], 2); ?></div>
        </div>

        <div class="footer">
            <p>Thank you for your subscription!</p>
            <p>This is a computer-generated receipt. No signature required.</p>
            <p>For inquiries, please contact WORKLINK support.</p>
        </div>

        <div class="no-print" style="text-align: center; margin-top: 30px;">
            <button onclick="window.print()" class="btn btn-primary" style="padding: 10px 30px; background: #0d6efd; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button onclick="window.close()" class="btn btn-secondary" style="padding: 10px 30px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-left: 10px;">
                Close
            </button>
        </div>
    </div>

    <script>
        // Auto-print option (commented out - uncomment if you want auto-print)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>

