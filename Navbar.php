<?php 
    $current_page = basename($_SERVER['PHP_SELF']); 
    function isActive($pageName) {
    $current = basename($_SERVER['PHP_SELF']);
    return ($current == $pageName) ? 'active' : '';
}
?>

<link rel="stylesheet" href="- Navbar/NavBar.css">

<nav class="nav">
    <div class="nav-inner">
        <a href="Home" class="brand" style="text-decoration: none; color: black;">
            <img src="../- Image/BEW Logo.png" alt="logo" id="imgLogo">
            ระบบแจ้งปัญหา IT
        </a>
        <div class="hamburger" id="hamburger">
            <p id="menuText" style="margin:0px;">เมนูทั้งหมด</p>
            <i class="fa-solid fa-bars" style="font-size: 18px;"></i>
        </div>
        <div class="nav-links" id="navLinks">
            <a href="Home" class="nav-link <?php echo isActive('Home.php'); ?>">หน้าหลัก</a>
            <a href="IT Request Form" class="nav-link <?php echo isActive('IT Request Form.php'); ?>">ใบขอแจ้งซ่อม</a>
            <a href="RequestHistory" class="nav-link <?php echo isActive('RequestHistory.php'); ?>">ประวัติการแจ้งซ่อม</a>
            <a href="Account" class="nav-link <?php echo isActive('Account.php'); ?>">แก้ไขข้อมูลผู้ใช้</a>
            <?php if (isset($user['Role']) && ($user['Role'] == 'IT' || $user['Role'] == 'IT_Director')): ?>
                 <a href="Computer" class="nav-link <?php echo isActive('Computer.php'); ?>">แก้ไขข้อมูลคอมพิวเตอร์</a>
            <?php endif; ?>
            <form action="Logout.php" method="POST" style="margin: 0;">
                <button class="nav-link" id="logoutMenu" type="submit" name="logout">ออกจากระบบ</button>
            </form>     
        </div>
    </div>
</nav>

<script src="- Navbar/NavBar.js"></script>