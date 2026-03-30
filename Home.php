<?php
include 'Supabase.php';
session_start();

// เช็คความปลอดภัย: ถ้าไม่ได้ Login ให้เด้งกลับไปหน้า login.php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. ดึงข้อมูล User
$logged_user  = $_SESSION['user_id'];
$data = supabase_query("/rest/v1/Users?User_ID=eq." . urlencode($logged_user) . "&select=*");
$user = (!empty($data)) ? $data[0] : null;

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="icon" type="image/x-icon" href="../- Image/BEW-Logo.ico">
    
    <link rel="stylesheet" href="Home Folder/Home.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <main>
        <?php include_once 'Navbar.php'; ?>

        <section id="mainSection">
            <div class="mainContainer">
                <div class="itemContainer">
                    <p id="welcomeText">
                        <span style="font-weight: bold;">ยินดีต้อนรับ <br id="welcomeTextBR"> คุณ</span>
                        <?php echo htmlspecialchars($user['Firstname'] ?? ''); ?>
                        <?php echo htmlspecialchars($user['Lastname'] ?? ''); ?>
                    </p>
                    <div class="describeBoxContainer">
                        <div class="describeBox">
                            <div class="describeBoxHeader"><i class="fa-solid fa-circle-user"></i><p style="margin: 0;">รหัสพนักงาน</p></div>
                            <div class="describeBoxInner"><?php echo htmlspecialchars($user['User_ID']); ?></div>
                        </div>
                        <div class="describeBox">
                            <div class="describeBoxHeader"><i class="fa-solid fa-building-user"></i><p style="margin: 0;">แผนก</p></div>
                            <div class="describeBoxInner"><?php echo htmlspecialchars($user['Section'] ?? ''); ?></div>
                        </div>
                        <div class="describeBox">
                            <div class="describeBoxHeader"><i class="fa-solid fa-computer"></i><p style="margin: 0;">คอมพิวเตอร์</p></div>
                            <div class="describeBoxInner"><?php echo htmlspecialchars($user['Equipment_ID'] ?? 'ไม่มีคอมพิวเตอร์'); ?></div>
                        </div>
                    </div>
                    <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                    <div class="buttonContainer">
                        <a href="IT Request Form" style="text-decoration: none;"><button class="button" id="report"><i class="fa-regular fa-file-lines"></i>ใบแจ้งซ่อม</button></a>        
                        <a href="RequestHistory" style="text-decoration: none;"><button class="button" id="history"><i class="fa-solid fa-clock"></i>ประวัติการแจ้งซ่อม</button></a>
                        <a href="Account" style="text-decoration: none;"><button class="button" id="account"><i class="fa-solid fa-user"></i>แก้ไขข้อมูล<br>ผู้ใช้</button></a>
                        <?php if ($user['Role'] == 'IT' || $user['Role'] == 'IT_Director'): ?>
                            <a href="Computer" style="text-decoration: none;"><button class="button" id="pc"><i class="fa-solid fa-computer"></i>แก้ไขข้อมูล<br>คอมพิวเตอร์</button></a>
                        <?php endif; ?>
                        <a href="" style="text-decoration: none;"><button class="button" id="problem"><i class="fa-solid fa-triangle-exclamation"></i>แจ้งปัญหาเว็บไซต์</button></a>
                        <form action="Logout.php" method="POST" style="margin: 0;">
                            <button class="button" id="exit" type="submit" name="logout"><i class="fa-solid fa-arrow-right-from-bracket"></i>ออกจากระบบ</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>