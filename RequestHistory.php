<?php
include 'Supabase.php';
session_start();

// 1. เช็คความปลอดภัย: ถ้าไม่ได้ Login ให้เด้งกลับไปหน้า login.php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="icon" type="image/x-icon" href="../- Image/BEW-Logo.ico">
    
    <link rel="stylesheet" href="RequestHistory Folder/RequestHistory.css">

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
                    <div id="header">
                        <h1 id="headerText">ประวัติการแจ้งซ่อม</h1>
                        <input type="text" id="searchBox" placeholder="ค้นหา"> 
                    </div>
                    <div id="tableContainer">
                        <table id="historyTable">
                            <tr>
                                <th>วันที่</th>
                                <th>หัวข้อ</th>
                                <th>การดำเนินการ</th>
                                <th>สถานะ</th>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </main>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    alert("<?php echo $_SESSION['flash_message']; ?>");
                }, 100);
            };
        </script>
    <?php 
        unset($_SESSION['flash_message']);
    endif; 
    ?>
    
    <!-- <script src="Computer Folder/Computer.js"></script> -->
</body>
</html>