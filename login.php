<?php
// Pastikan tidak ada whitespace sebelum tag PHP ini
session_start(); // Harus diletakkan di awal sebelum output apapun
include("config/database.php");

// Variable to store message
$message = '';
$messageType = '';
$showAlert = false;
$redirect = false;
$redirectUrl = '';

// Google reCAPTCHA Secret Key
$recaptchaSecretKey = '6LenNDorAAAAAA--kdVGI8u139QdNfl_KohuWCJS';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Verify Google reCAPTCHA
    $captchaVerified = false;
    if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
        $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$recaptchaSecretKey.'&response='.$_POST['g-recaptcha-response'].'&remoteip='.$ip);
        $responseData = json_decode($verifyResponse);
        $captchaVerified = $responseData->success;
    }
    
    // Check if captcha is verified
    if (!$captchaVerified) {
        $message = 'Please verify that you are not a robot.';
        $messageType = 'error';
        $showAlert = true;
    }
    // Validasi input dasar
    elseif (empty($username) || empty($password)) {
        $message = 'Please enter both username and password.';
        $messageType = 'error';
        $showAlert = true;
    } else {
        // Cek username dan password di database
        $stmt = $conn->prepare("SELECT id, account, password FROM account WHERE account = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $dbUsername, $dbPassword);
            $stmt->fetch();
            
            // Verifikasi password
            if ($password === $dbPassword) {
                // Login berhasil, set session
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $dbUsername;
                
                // Set variabel redirect, bukan langsung menggunakan header()
                $redirect = true;
                $redirectUrl = "dashboard.php";
                $message = 'Login successful! Redirecting...';
                $messageType = 'success';
                $showAlert = true;
            } else {
                $message = 'Invalid password. Please try again.';
                $messageType = 'error';
                $showAlert = true;
            }
        } else {
            $message = 'Username not found. Please check your username or register a new account.';
            $messageType = 'error';
            $showAlert = true;
        }
        
        $stmt->close();
    }
    
    $conn->close();
}
// Tidak ada output sebelum sini
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #6a11cb, #2575fc);
            color: #fff;
            margin: 0;
            padding: 0;
        }
        .login-container {
            width: 90%;
            max-width: 1024px;
            margin: 50px auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: #333;
            position: relative;
        }
        .login-container .title {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-container .title .name {
            font-size: 24pt;
            font-weight: 900;
            color: #6a11cb;
        }
        .login-container .content {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        #login-form {
            width: 100%;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        #login-form input[type="text"],
        #login-form input[type="password"] {
            width: 100%;
            max-width: 400px;
            height: 45px;
            padding: 10px;
            border: 1px solid #919eec;
            border-radius: 5px;
            color: #3138ad;
            font-size: 16px;
            background-color: #f9f9f9;
            box-sizing: border-box;
            display: block;
            margin: 0 auto;
        }
        #login-form input:focus {
            outline: none;
            border-color: #ff8418;
            box-shadow: 0 0 5px rgba(255, 132, 24, 0.5);
        }
        #login-form button {
            background: linear-gradient(180deg, rgb(247, 187, 77) 0%, rgb(251, 146, 54) 100%);
            color: white;
            border: none;
            padding: 15px 20px;
            font-size: 18px;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
            max-width: 400px;
            font-weight: 900;
            transition: all 0.3s ease;
            margin-top: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        #login-form button:hover {
            background: linear-gradient(180deg, rgba(247, 178, 51, 1) 0%, rgba(238, 122, 21, 1) 100%);
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .register-link a {
            color: #6a11cb;
            text-decoration: none;
            font-weight: bold;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        /* Styling untuk reCAPTCHA */
        .recaptcha-container {
            margin: 15px auto;
            display: flex;
            justify-content: center;
        }
        
        @media only screen and (max-width: 768px) {
            #login-form input[type="text"],
            #login-form input[type="password"],
            #login-form button {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="title">
            <span class="name">Login</span>
        </div>
        <div class="content">
            <form id="login-form" method="POST" action="">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                
                <!-- Google reCAPTCHA -->
                <div class="recaptcha-container">
                    <div class="g-recaptcha" data-sitekey="6LenNDorAAAAALb70blTIglYsGDBw8LxpvfZoboX"></div>
                </div>
                
                <button type="submit">Login</button>
                <div class="register-link">
                    Don't have an account? <a href="index.php?page=register">Register here</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($showAlert): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: '<?php echo $messageType == 'success' ? 'Success' : 'Error'; ?>',
                text: '<?php echo $message; ?>',
                icon: '<?php echo $messageType; ?>',
                confirmButtonText: 'OK'
            }).then((result) => {
                <?php if ($redirect): ?>
                if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                    window.location.href = '<?php echo $redirectUrl; ?>';
                }
                <?php endif; ?>
            });
        });
    </script>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('login-form');
            form.addEventListener('submit', function(e) {
                const recaptchaResponse = grecaptcha.getResponse();
                if (recaptchaResponse.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error',
                        text: 'Please verify that you are not a robot.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
    </script>
</body>
</html>
