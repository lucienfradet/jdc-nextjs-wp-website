<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if action was completed (via URL parameter)
$action_completed = isset($_GET['action']) && $_GET['action'] === 'completed';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jardin des Chefs - Site Headless</title>
    <style>
        :root {
            --green: #24311B;
            --green-transparent: rgba(36, 49, 27, 0.85);
            --red: #A22D22;
            --beige: #F5F0E1;
            --beige-transparent: rgba(245,240,225, 0.75);
            --orange: #D17829;
            --pink: #C17C74;
            --blue: #153C66;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 600px;
            background: var(--beige);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--green);
        }

        .success-banner {
            background: var(--green);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px var(--green-transparent);
            animation: slideDown 0.5s ease-out;
        }

        .success-banner h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .checkmark {
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--green);
            font-weight: bold;
        }

        .logo-container {
            margin-bottom: 2rem;
        }

        .logo-img {
            max-width: 200px;
            height: auto;
        }

        .headless-info {
            background: rgba(255, 255, 255, 0.7);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--green);
        }

        .headless-info p {
            color: #666;
            font-size: 1.1rem;
        }

        .main-button {
            display: inline-block;
            background: var(--green);
            color: white;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px var(--green-transparent);
            margin-top: 1rem;
        }

        .main-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--green-transparent);
            background: var(--green-transparent);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .logo {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="https://jardindeschefs.ca/images/jdc_logo.png" alt="Jardin des Chefs" class="logo-img">
        </div>

        <div class="success-banner">
            <h2><span class="checkmark">✓</span></h2>
            <p>Votre demande a été traitée correctement.</p>
        </div>

        <div class="headless-info">
            <p>Ce site WordPress fonctionne en mode headless et sert d'API pour d'autres applications.</p>
        </div>

        <a href="https://jardindeschefs.ca" class="main-button">
            Visiter le Site Principal
        </a>
    </div>
</body>
</html>
