<?php
session_start(); // Start the session
session_unset(); // Clear all session variables
session_destroy(); // Destroy the session
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>

    <!-- Font Awesome for gem icon -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    <style>
        :root {
            --color-gold: #ffcc00;
        }

        /* Full-screen overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(8px);
            z-index: 2000;
            display: flex; /* Changed from none to flex so it shows immediately */
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .loading-overlay.active {
            opacity: 1;
        }

        .loading-content {
            text-align: center;
            color: #fff;
            padding: 30px;
            border-radius: 10px;
        }

        .loading-icon {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
        }

        .gem-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 35px;
            color: var(--color-gold);
            animation: pulse 1.5s infinite alternate;
            z-index: 10;
        }

        .loading-ring {
            width: 100%;
            height: 100%;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top: 5px solid var(--color-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-title {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .loading-subtitle {
            font-size: 1rem;
            color: #ccc;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes pulse {
            from { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            to { transform: translate(-50%, -50%) scale(1.2); opacity: 0.7; }
        }
    </style>

    <script>
        // Wait for DOM to load
        window.onload = function() {
            // Show the loading screen
            const loader = document.getElementById('loadingScreen');
            loader.classList.add('active');

            // Clear chatbot conversation
            localStorage.removeItem('chatHistory');

            // Redirect after animation finishes
            setTimeout(() => {
                window.location.href = "index.php";
            }, 2500);
        };
    </script>
</head>

<body>
    <div class="loading-overlay" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-icon">
                <i class="fas fa-gem gem-icon"></i>
                <div class="loading-ring"></div>
            </div>
            
            <div class="loading-text">
                <h2 class="loading-title">Logging Out</h2>
                <p class="loading-subtitle">Logging out your account securely...</p>
            </div>
        </div>
    </div>
</body>
</html>
