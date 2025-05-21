<?php
session_start(); // mulai session
include("config/database.php"); // koneksi database

// Tentukan halaman berdasarkan parameter URL
$page = isset($_GET['page']) ? $_GET['page'] : 'home'; // default ke 'home'

// Validasi halaman yang diminta
$allowed_pages = ['home', 'register', 'download', 'ranking', 'login'];
if (!in_array($page, $allowed_pages)) {
    $page = 'home'; // jika halaman tidak valid, kembali ke 'home'
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>V-Ragnarok Mobile</title>
    <link rel="shortcut icon" href="/images/favicon.ico" type="image/x-icon">
    <!-- Tambahan: Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
      /* Base CSS */
      body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background: linear-gradient(to bottom, #6a11cb, #2575fc);
        color: #333;
      }

      /* Navigasi */
      .menu {
        background-color: rgba(0, 0, 0, 0.2);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      }

      .menu ul {
        list-style-type: none;
        margin: 0;
        padding: 0;
        display: flex;
      }

      .menu ul li {
        flex: 1;
        text-align: center;
      }

      .menu ul li a {
        display: block;
        color: white;
        text-decoration: none;
        padding: 15px 0;
        font-weight: bold;
        transition: 0.3s;
      }

      .menu ul li a:hover {
        background-color: rgba(255, 255, 255, 0.1);
      }

      .menu ul li a.active {
        background-color: rgba(255, 255, 255, 0.2);
        color: #fff;
      }

      /* Container untuk konten halaman */
      #container {
        width: 90%;
        max-width: 1200px;
        margin: 20px auto;
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        min-height: 400px;
      }

      /* Footer */
      .footer-container {
        background: url('/images/footer-bg.jpg') no-repeat center center;
        background-size: cover;
        color: white;
        padding: 20px 0;
        text-align: center;
        margin-top: 30px;
      }

      .footer-container .inside {
        padding: 20px;
      }

      .footer-container .text-1 {
        font-size: 18px;
        font-weight: bold;
      }

      .footer-container .text-2 {
        font-size: 14px;
        opacity: 0.8;
      }

      /* Banner */
      .game-banner {
        background: url('/images/game-banner.jpg') no-repeat center center;
        background-size: cover;
        height: 300px;
        position: relative;
        margin: 0 auto 20px;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        max-width: 1200px;
        width: 90%;
      }

      .banner-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        color: white;
        text-shadow: 2px 2px 5px rgba(0,0,0,0.7);
      }

      .animated-text {
        font-size: 3rem;
        margin-bottom: 10px;
        opacity: 0;
        transition: opacity 1s ease;
      }

      .animated-text.fadeIn {
        opacity: 1;
      }

      .animated-subtext {
        font-size: 1.5rem;
        opacity: 0;
        animation: fadeIn 1s ease forwards;
        animation-delay: 0.5s;
      }

      /* Server Stats Section */
      .server-stats {
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        margin: 30px auto;
        max-width: 900px;
      }

      .stat-box {
        background: linear-gradient(145deg, #6a11cb, #8844e0);
        border-radius: 10px;
        padding: 20px;
        margin: 10px;
        text-align: center;
        color: white;
        flex: 1;
        min-width: 200px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        transform: scale(0.95);
        transition: transform 0.3s ease;
        opacity: 0;
        animation: fadeIn 0.5s ease forwards;
      }

      .stat-box:hover {
        transform: scale(1.05);
      }

      .stat-box.pop {
        animation: popIn 0.4s ease forwards;
      }

      .stat-box i {
        font-size: 2.5rem;
        margin-bottom: 10px;
        display: block;
      }

      .stat-count {
        font-size: 2rem;
        font-weight: bold;
        display: block;
        margin-bottom: 5px;
      }

      .stat-label {
        font-size: 1rem;
      }

      .online {
        color: #4cffb3;
        font-weight: bold;
      }

      /* Footer enhancements */
      .social-icons {
        margin-top: 15px;
      }

      .social-icons a {
        display: inline-block;
        margin: 0 10px;
        color: white;
        font-size: 1.5rem;
        transition: transform 0.3s ease, color 0.3s ease;
      }

      .social-icons a:hover {
        transform: translateY(-5px);
        color: #4cffb3;
      }

      /* Menu dengan ikon */
      .menu ul li a i {
        margin-right: 5px;
      }

      /* Animasi */
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }

      @keyframes popIn {
        0% { 
          opacity: 0;
          transform: scale(0.8);
        }
        70% { 
          transform: scale(1.05);
          opacity: 1;
        }
        100% { 
          transform: scale(1);
          opacity: 1;
        }
      }

      /* Media Queries */
      @media only screen and (max-width: 768px) {
        .game-banner {
          height: 200px;
        }
        
        .animated-text {
          font-size: 2rem;
        }
        
        .animated-subtext {
          font-size: 1rem;
        }
        
        .menu ul {
          flex-direction: column;
        }
        
        .menu ul li {
          margin-bottom: 1px;
        }
      }
    </style>
  </head>
  <body>
    <!-- Menu Navigasi -->
    <div class="menu">
      <ul>
        <li><a href="index.php?page=home" <?php if ($page == "home") { echo 'class="active"'; } ?>><i class="fas fa-home"></i> HOME</a></li>
        <li><a href="index.php?page=download" <?php if ($page == "download") { echo 'class="active"'; } ?>><i class="fas fa-download"></i> DOWNLOAD</a></li>
        <li><a href="index.php?page=register" <?php if ($page == "register") { echo 'class="active"'; } ?>><i class="fas fa-user-plus"></i> REGISTER</a></li>
        <li><a href="index.php?page=login" <?php if ($page == "login") { echo 'class="active"'; } ?>><i class="fas fa-sign-in-alt"></i> LOGIN</a></li>
        <li><a href="index.php?page=ranking" <?php if ($page == "ranking") { echo 'class="active"'; } ?>><i class="fas fa-trophy"></i> RANKING</a></li>
      </ul>
    </div>

    <!-- Banner animasi dengan karakter game -->
    <?php if ($page == 'home'): ?>
    <div class="game-banner">
      <div class="banner-content">
        <h1 class="animated-text">Welcome to V-Ragnarok Mobile!</h1>
        <p class="animated-subtext">Embark on your adventure today</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Konten Halaman -->
    <div id="container">
      <?php 
      // Muat file berdasarkan halaman
      $file_to_include = $page . ".php";
      if (file_exists($file_to_include)) {
          include($file_to_include);
      } else {
          echo "<h1>404 - Page Not Found</h1>";
      }
      ?>
    </div>

    <!-- Stats Box - Informasi Server (hanya di halaman home) -->
    <?php if ($page == 'home'): ?>
    <div class="server-stats">
      <div class="stat-box">
        <i class="fas fa-server"></i>
        <span class="stat-label">Server Status: <span class="online">Online</span></span>
      </div>
    </div>
    <?php endif; ?>
    <!-- Footer -->
    <div class="footer-container">
      <div class="inside">
        <span class="text-1">© 2025</span><br/>
        <span class="text-2">V-Ragnarok Mobile</span>
        <div class="social-icons">
          <a href="#" title="Discord"><i class="fab fa-discord"></i></a>
          <a href="#" title="Facebook"><i class="fab fa-facebook"></i></a>
          <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
        </div>
      </div>
    </div>

    <!-- Script untuk animasi dan efek -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Animasi banner text
        const bannerText = document.querySelector('.animated-text');
        if(bannerText) {
          bannerText.classList.add('fadeIn');
        }
        
        // Animasi untuk stat boxes
        const statBoxes = document.querySelectorAll('.stat-box');
        if(statBoxes.length) {
          statBoxes.forEach((box, index) => {
            setTimeout(() => {
              box.classList.add('pop');
            }, 200 * index);
          });
        }
      });
    </script>
  </body>
</html>