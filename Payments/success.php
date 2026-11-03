<?php
// Payment id (common name)
$payment_id = $_GET['payment_id'] ?? 'N/A';

// Payer name: accept multiple possible keys (payer, name)
$name = $_GET['name'] ?? $_GET['payer'] ?? 'Customer';

// Amounts: accept different names used earlier (amount_php, peso, total)
$amount_php = $_GET['amount_php'] ?? $_GET['peso'] ?? $_GET['total'] ?? '0.00';
$amount_usd = $_GET['amount_usd'] ?? $_GET['usd'] ?? $_GET['amount'] ?? '0.00';

// Normalize numeric formatting
$amount_php = is_numeric($amount_php) ? number_format((float)$amount_php, 2, '.', '') : $amount_php;
$amount_usd = is_numeric($amount_usd) ? number_format((float)$amount_usd, 2, '.', '') : $amount_usd;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Payment Successful</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            text-align: center;
            background-color: #f6fff8;
            margin-top: 80px;
        }
        .box {
            display: inline-block;
            background: white;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.08);
            max-width: 420px;
        }
        h1 { color: #2ecc71; margin-bottom: 8px; }
        p { color: #333; margin: 8px 0; }
        .muted { color: #666; font-size: 0.94rem; }
        .small { font-size: 0.88rem; color:#555; }
        .btn { display:inline-block; margin-top:14px; padding:10px 16px; border-radius:8px; background:#2b7a78; color:white; text-decoration:none; }
    </style>
</head>
<body>
    <div class="box">
        <h1>✅ Payment Successful!</h1>

        <p class="muted">Thank you, <strong><?php echo htmlspecialchars($name); ?></strong></p>

        <p>Payment ID: <strong><?php echo htmlspecialchars($payment_id); ?></strong></p>

        <p>Amount paid:</p>
        <p class="small">
            ₱ <strong><?php echo htmlspecialchars($amount_php); ?></strong>
            <?php if (!empty($amount_usd) && $amount_usd !== '0.00'): ?>
                &nbsp;•&nbsp; $ <strong><?php echo htmlspecialchars($amount_usd); ?></strong> (USD)
            <?php endif; ?>
        </p>

        <button class="btn" id="closeBtn">Close</button>
    </div>

    <script>
    document.getElementById('closeBtn').addEventListener('click', async () => {
        try {
            // 1. Clear the cart first
            await fetch('../customer/clear_cart.php', { method: 'POST' });

            // 2. Redirect the opener (checkout.php) to cart.php
            if (window.opener && !window.opener.closed) {
                window.opener.location.href = '../customer/cart.php';
            }

            // 3. Close the success popup
            window.close();
        } catch (error) {
            console.error('Error clearing cart:', error);
            window.close(); // still close even if something failed
        }
    });
    </script>

</body>
</html>
