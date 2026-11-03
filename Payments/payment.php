<?php
// Get total from checkout (in PHP)
$total_php = isset($_GET['total_php']) ? (float)$_GET['total_php'] : 0;

// Conversion rate
$conversion_rate = 0.01670;

// Convert PHP to USD
$amount_usd = $total_php * $conversion_rate;

// Format to 2 decimal places for PayPal
$amount_usd_formatted = number_format($amount_usd, 2, '.', '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Payment</title>
    <script src="https://www.paypal.com/sdk/js?client-id=AQP0RcUaAtQokBySL3qoeZMM-3O-zBYUoBLevMmDogAh7EPenzoCPPXHOUfkyTi1U4-lOPudvEJgM4rK&currency=USD"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #f8f8f8;
        }
        #paypal-button-container {
            background: white;
            padding: 25px 35px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .amount-info {
            margin-bottom: 15px;
            font-size: 16px;
        }
    </style>
</head>
<body>

    <div id="paypal-button-container">
        <div class="amount-info">
            <p>Amount in PHP: <strong>₱<?= number_format($total_php, 2) ?></strong></p>
            <p>Converted to USD: <strong>$<?= $amount_usd_formatted ?></strong></p>
        </div>
    </div>

    <script>
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '<?= $amount_usd_formatted ?>'
                        },
                        description: 'Sweetheart Gold Shop Purchase'
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    try {
                        // Tell the parent window that payment succeeded
                        if (window.opener) {
                            window.opener.paypal_payment_status = 'success';
                            window.opener.paypal_payment_id = details.id;
                        }

                        // Close the popup
                        window.close();
                    } catch (err) {
                        console.error('Error in onApprove:', err);
                        alert('An error occurred during the payment. Please try again.');
                    }
                });
            },
            onError: function(err) {
                console.error(err);
                alert("An error occurred during the payment. Please try again.");
            }
        }).render('#paypal-button-container');
    </script>
</body>
</html>
