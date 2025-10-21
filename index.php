<?php
session_start();
$flash_success = '';
if (isset($_SESSION['success_message'])) {
    $flash_success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Guard - Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');
        
        :root {
            --accent-color: skyblue;
            --base-color: white;
            --text-color: black;
            --input-color: #F3F0FF;
            --label-color: white;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-family: Poppins, Segoe UI, sans-serif;
            font-size: 12pt;
            color: var(--text-color);
            height: 100%;
            text-align: center;
        }

        body {
            min-height: 100vh;
            background-image: url("./assets/icons/images/nova schoola-Photoroom.png");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }

        /* Hover text field styles */
        .hover-text-field {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1000;
            opacity: 0;
            transition: all 0.3s ease-in-out;
            pointer-events: none;
        }
        
        .hover-text-field .content-container {
            padding: 25px;
            border: 2px solid #007bff;
            border-radius: 15px;
            width: 420px;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            text-align: left;
            max-height: 800px;
            overflow-y: auto;
        }

        .system-info {
            margin-bottom: 25px;
            text-align: justify;
        }

        .system-info h2 {
            color: #333;
            font-size: 16px;
            margin-bottom: 20px;
            font-weight: 800;
            text-align: center;
        }

        .system-info h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        .system-info p {
            font-size: 13px;
            margin-bottom: 10px;
        }

        .system-info ul {
            font-size: 13px;
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .version-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }

        /* Our Team Section */
        .our-team {
            text-align: center;
        }

        .our-team h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .team-members {
            display: flex;
            justify-content: space-around;
            gap: 15px;
        }

        .team-member {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .team-member img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
            margin-bottom: 8px;
            transition: transform 0.3s ease;
        }

        .team-member img:hover {
            transform: scale(1.1);
        }

        .team-member span {
            font-size: 12px;
            font-weight: 500;
            color: #333;
        }
        
        /* Right side text field */
        .hover-text-field.right {
            right: -440px;
        }
        
        .hover-text-field.right.show {
            right: 20px;
            opacity: 1;
        }
        
        /* Logo positioning */
        .brand-logo-right {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1000;
        }
        
        .brand-logo-right img {
            height: 100px;
            width: auto;
            object-fit: contain;
            display: block;
            border-radius: 10px;
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .brand-logo-right:hover img {
            transform: scale(1.05);
            border: 2px solid #007bff;
        }

        /* Index page styles */
        .index-page {
            margin-top: 50px;
        }

        .index-page .role-selection {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 30px 0;
        }

        .index-page .role-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            width: 200px;
            height: 200px;
            justify-content: center;
        }

        .index-page .role-option:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .index-page .role-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .index-page .role-option img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 15px;
        }

        .index-page .role-option h3 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        .index-page .role-option p {
            margin: 5px 0 0 0;
            font-size: 14px;
            text-align: center;
        }

        .index-page .header-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .index-page .header-section h1 {
            color: #231f1f;
            margin-bottom: 10px;
            font-size: 3rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .index-page .header-section h3 {
            color: #231f1f;
            margin-bottom: 20px;
            font-size: 2rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .wrapper {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(50px);
            border-radius: 10px;
            padding: 10px 20px;
            display: inline-block;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php if (!empty($flash_success)): ?>
        <div style="background-color: #2ed573; color: #ffffff; padding: 15px; margin: 10px; border-radius: 5px; text-align: center;">
            <?php echo htmlspecialchars($flash_success); ?>
        </div>
    <?php endif; ?>
    <!-- Hover text field -->
    <div class="hover-text-field right" id="rightTextField">
        <div class="content-container">
            <div class="system-info">
                <h2> About us </h2>
                <h3>Our Story</h3>
                
                <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;At Nova Schola Tanauan, our team set out to solve the challenge of keeping school discipline organized, safe, and transparent.</p> 
                <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;We designed Student Violation Monitoring System, a monitoring and reporting system for student violations, aiming to make disciplinary processes more accurate and efficient.</p>
                <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;By replacing outdated manual logbooks with a digital platform, we help staff and guards quickly record, manage, and review violations and ensure that data is always reliable and secure.</p>
                
                <div class="version-info">
                    App Version: 1.0.0 <br>
                    Having trouble? email us at <a href="mailto:novaguard3@gmail.com">novaguard3@gmail.com</a>
                </div>
            </div>

            <div class="our-team">
                <h3>Our Team</h3>
                <div class="team-members">
                    <div class="team-member">
                        <img src="./assets/icons/images/diether.jpg" alt="Diether Flores" onerror="this.src='https://via.placeholder.com/60x60/007bff/white?text=CJ'">
                        <span>Diether Flores</span>
                    </div>
                    <div class="team-member">
                        <img src="./assets/icons/images/eunices.jpg" alt="Eunecis Raymundo" onerror="this.src='https://via.placeholder.com/60x60/007bff/white?text=J'">
                        <span>Eunecis Raymundo</span>
                    </div>
                    <div class="team-member">
                        <img src="./assets/icons/images/IMG_20210410_215217.png" alt="Butch Salar" onerror="this.src='https://via.placeholder.com/60x60/007bff/white?text=AJ'">
                        <span>Butch Salar</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="brand-logo-right" id="rightLogo">
        <img src="./assets/icons/images/NovaScholaLogo.png" alt="Nova Schola Logo" onerror="this.src='https://via.placeholder.com/100x100/007bff/white?text=NOVA'">
    </div>

    <div class="index-page">
        <div class="header-section">
            <h1>Nova Schola</h1>
            <h3>Student Violation Monitoring System</h3>
        </div>
        <div class="login-container">
            <div class="role-selection">
                <div class="role-option" onclick="selectRole('guard')">
                    <img src="./assets/icons/images/guard_icon.jpg" alt="Guard Icon" onerror="this.src='https://via.placeholder.com/80x80/28a745/white?text=G'">
                    <h3>Guard</h3>
                    <p>Security Personnel</p>
                </div>
                <div class="role-option" onclick="selectRole('admin')">
                    <img src="./assets/icons/images/admin_icon.jpg" alt="Admin Icon" onerror="this.src='https://via.placeholder.com/80x80/dc3545/white?text=A'">
                    <h3>Admin</h3>
                    <p>Administrator</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const rightLogo = document.getElementById('rightLogo');
        const rightTextField = document.getElementById('rightTextField');

        rightLogo.addEventListener('mouseenter', () => rightTextField.classList.add('show'));
        rightLogo.addEventListener('mouseleave', () => rightTextField.classList.remove('show'));

        rightTextField.addEventListener('mouseenter', function() {
            this.classList.add('show');
            this.style.pointerEvents = 'auto';
        });
        rightTextField.addEventListener('mouseleave', function() {
            this.classList.remove('show');
            this.style.pointerEvents = 'none';
        });

        function selectRole(role) {
            const roleOptions = document.querySelectorAll('.role-option');
            roleOptions.forEach(option => option.classList.remove('selected'));
            event.target.closest('.role-option').classList.add('selected');
            if (role === 'guard') {
                window.location.href = './pages/login_guard.php';
            } else if (role === 'admin') {
                window.location.href = './pages/login_admin.php';
            }
        }
    </script>
</body>
</html>