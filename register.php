<?php
include("config/database.php");

// Variable to store message
$message = '';
$messageType = '';
$showAlert = false;
$redirectAfter = false;

// Google reCAPTCHA Secret Key
$recaptchaSecretKey = '6LenNDorAAAAAA--kdVGI8u139QdNfl_KohuWCJS';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $repassword = $_POST['repassword'];
    $email = trim($_POST['email']);
    $safetycode = trim($_POST['safetycode']);
    $resafetycode = trim($_POST['resafetycode']);
    $ip = $_SERVER['REMOTE_ADDR'];
    $regtime = date('Y-m-d H:i:s');
    
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
    // Validasi username (6-10 karakter, hanya huruf kecil)
    elseif (!preg_match('/^[a-z0-9]{6,10}$/', $username)) {
        $message = 'Username must be 6-10 characters, lowercase letters or numbers only.';
        $messageType = 'error';
        $showAlert = true;
    }
    // Validasi password (7-10 karakter, hanya huruf kecil & angka, kombinasi huruf kecil & angka)
    elseif (
        !preg_match('/^[a-z0-9]{7,10}$/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password)
    ) {
        $message = 'Password must be 7-10 characters, contain both lowercase letters and numbers, and only lowercase letters or numbers.';
        $messageType = 'error';
        $showAlert = true;
    }
    // Validasi re-password
    elseif ($password !== $repassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
        $showAlert = true;
    }
    // Validasi safetycode (hanya angka, maksimal 6 digit)
    elseif (!preg_match('/^\d{1,6}$/', $safetycode)) {
        $message = 'Pin Code must be a numeric value with a maximum of 6 digits.';
        $messageType = 'error';
        $showAlert = true;
    }
    // Validasi re-safetycode
    elseif ($safetycode !== $resafetycode) {
        $message = 'Pin Codes do not match.';
        $messageType = 'error';
        $showAlert = true;
    }
    // Validasi email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
        $messageType = 'error';
        $showAlert = true;
    }
    else {
        // Cek apakah username atau email sudah ada di database
        $stmt = $conn->prepare("SELECT id FROM account WHERE account = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = 'Username or email already exists.';
            $messageType = 'error';
            $showAlert = true;
            $stmt->close();
        }
        else {
            $stmt->close();
            // Masukkan data ke database
            $stmt = $conn->prepare("INSERT INTO account (account, password, email, regtime, ip, safetycode) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $password, $email, $regtime, $ip, $safetycode);
            if ($stmt->execute()) {
                $message = 'Registration successful!';
                $messageType = 'success';
                $showAlert = true;
                $redirectAfter = true;
            } else {
                $message = 'Registration failed: ' . $stmt->error;
                $messageType = 'error';
                $showAlert = true;
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
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
        .register-container {
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
        .register-container .title {
            text-align: center;
            margin-bottom: 20px;
        }
        .register-container .title .name {
            font-size: 24pt;
            font-weight: 900;
            color: #6a11cb;
        }
        .register-container .content {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        #register-form {
            width: 100%;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        #register-form input[type="text"],
        #register-form input[type="password"],
        #register-form input[type="email"] {
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
        #register-form input:focus {
            outline: none;
            border-color: #ff8418;
            box-shadow: 0 0 5px rgba(255, 132, 24, 0.5);
        }
        #register-form button {
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
        #register-form button:hover {
            background: linear-gradient(180deg, rgba(247, 178, 51, 1) 0%, rgba(238, 122, 21, 1) 100%);
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Styling untuk reCAPTCHA */
        .recaptcha-container {
            margin: 15px auto;
            display: flex;
            justify-content: center;
        }
        
        @media only screen and (max-width: 768px) {
            #register-form input[type="text"],
            #register-form input[type="password"],
            #register-form input[type="email"],
            #register-form button {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="title">
            <span class="name">Register</span>
        </div>
        <div class="content">
            <form id="register-form" method="POST" action="">
                <input type="text" name="username" placeholder="Username (6-10 chars, a-z, 0-9)" required maxlength="10" pattern="[a-z0-9]{6,10}" title="6-10 lowercase letters or numbers only">
                <input type="password" name="password" placeholder="Password (7-10 chars, a-z, 0-9)" required maxlength="10" pattern="[a-z0-9]{7,10}" title="7-10 lowercase letters or numbers only">
                <input type="password" name="repassword" placeholder="Re-enter Password" required maxlength="10" pattern="[a-z0-9]{7,10}" title="7-10 lowercase letters or numbers only">
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="safetycode" placeholder="Pin Code (max. 6 digits)" required pattern="\d{1,6}" maxlength="6" title="Pin Code must be numeric and up to 6 digits">
                <input type="text" name="resafetycode" placeholder="Re-enter Pin Code" required pattern="\d{1,6}" maxlength="6" title="Pin Code must be numeric and up to 6 digits">
                
                <!-- Google reCAPTCHA -->
                <div class="recaptcha-container">
                    <div class="g-recaptcha" data-sitekey="6LenNDorAAAAALb70blTIglYsGDBw8LxpvfZoboX"></div>
                </div>
                
                <button type="submit">Register</button>
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
                <?php if ($redirectAfter): ?>
                if (result.isConfirmed) {
                    window.location.href = '/login.php';
                }
                <?php endif; ?>
            });
        });
    </script>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('register-form');
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