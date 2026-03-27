<?php
include 'Supabase.php';
session_start();

// 1. เช็คความปลอดภัย: ถ้าไม่ได้ Login ให้เด้งกลับไปหน้า login.php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
$logged_user  = $_SESSION['user_id'];
$history = supabase_query("/rest/v1/RequestForm?User_ID=eq." . urlencode($logged_user) . "&select=*");
usort($history, function($a, $b) {return strnatcmp($a['Form_ID'], $b['Form_ID']);});

$historyMap = [];
foreach($history as $his){
    $historyMap[$his['Form_ID']] = [
        'Form_ID'    => $his['Form_ID'] ?? '',
        'Date'       => $his['Date'] ?? '',
        'FixCom'     => $his['FixCom'] ?? false,
        'FixETC'     => $his['FixETC'] ?? false,
        'ReInstall'  => $his['ReInstall'] ?? false,
        'Broken'     => $his['Broken'] ?? false,
        'ETC'        => $his['ETC'] ?? false,
        'ETCText'    => $his['ETCText'] ?? '',
        'FormStatus' => $his['FormStatus'] ?? '',
    ];
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report History</title>
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
                        <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="ค้นหา"> 
                    </div>
                    <div class="filter-buttons" style="margin-bottom: 25px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="button searchBtn" id="allBtn" onclick="filterStatus('all')"><p class="buttonLabel">ทั้งหมด</p></button>
                        <button class="button searchBtn" id="waitBtn" onclick="filterStatus('รอ')" ><p class="buttonLabel">รออนุมัติ/ซ่อม</p></button>
                        <button class="button searchBtn" id="denyBtn" onclick="filterStatus('ไม่อนุมัติ')"><p class="buttonLabel">ไม่อนุมัติ</p></button>
                        <button class="button searchBtn" id="completeBtn" onclick="filterStatus('เสร็จสิ้น')"><p class="buttonLabel">เสร็จสิ้น</p></button>
                    </div>
                    <div id="tableContainer">
                        <table id="historyTable">
                            <tr>
                                <th class="dateCol">วันที่</th>
                                <th class="fixCol">หัวข้อที่ต้องการ</th>
                                <th class="topicCol">การดำเนินการ</th>
                                <th class="statusCol">สถานะ</th>
                                <th class="buttonCol">รายละเอียด</th>
                            </tr>
                            <?php foreach($historyMap as $h): ?>
                                <tr>
                                    <td class="dateCol"><?= $h['Date'] ?></td>
                                    <td class="fixCol">
                                        <ul class="ulTable">
                                            <?php if($h['FixCom']): ?><li>ปรับปรุงแก้ไข คอมพิวเตอร์ โปรแกรมและอุปกรณ์ต่อพ่วง</li><?php endif ?>
                                            <?php if($h['FixETC']): ?><li>ปรับปรุงแก้ไข อุปกรณ์ทางไอทีแบบอื่นๆ</li><?php endif ?>
                                        </ul>
                                    </td>
                                    <td class="topicCol">
                                        <ul class="ulTable">
                                            <?php if($h['ReInstall']): ?><li>ถอนหรือติดตั้งโปรแกรมใหม่</li><?php endif ?>
                                            <?php if($h['Broken']): ?><li>อุปกรณ์ใช้งานไม่ได้ ชำรุด เสียหาย</li><?php endif ?>
                                            <?php if($h['ETC']): ?><li><?= $h['ETCText'] ?><?php endif ?>
                                        </ul>
                                    </td>
                                    <td class="statusCol">
                                        <?php if($h['FormStatus'] == 'WaitForApproval'): ?><div class="status statusYellow">รออนุมัติจากหัวหน้าแผนก</div>
                                        <?php elseif($h['FormStatus'] == 'WaitForConfirm'): ?><div class="status statusYellow">รออนุมัติจากหัวหน้าไอที</div>
                                        <?php elseif($h['FormStatus'] == 'WaitForFixing'): ?><div class="status statusYellow">รอการซ่อมแซม</div>
                                        <?php elseif($h['FormStatus'] == 'HoD_Denied'): ?><div class="status statusRed">หัวหน้าแผนกไม่อนุมัติ</div>
                                        <?php elseif($h['FormStatus'] == 'IT_Director_Denied'): ?><div class="status statusRed">หัวหน้าไอทีไม่อนุมัติ</div>
                                        <?php elseif($h['FormStatus'] == 'Complete'): ?><div class="status statusGreen">เสร็จสิ้น</div>
                                        <?php else: ?><div class="status statusYellow">สถานะ</div>
                                        <?php endif ?>
                                    </td>
                                    <td class="buttonCol">
                                        <div class="buttonColContainer">
                                            <?php if($h['FormStatus'] == 'WaitForApproval'): ?>
                                                <button class="smallbutton editButton" onclick="window.location.href='IT Request Form.php?form_id=<?php echo $h['Form_ID'] ?>'">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                    <p class="smallbuttonLabel">แก้ไขใบแจ้งซ่อม</p>
                                                </button>
                                            <?php endif ?>
                                            <button class="smallbutton infoButton"><i class="fa-solid fa-file-lines"></i><p class="smallbuttonLabel">รายละเอียด</p></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </main>
    
    <!-- <?php if (isset($_SESSION['flash_message'])): ?>
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
    ?> -->
    
    <script src="RequestHistory Folder/RequestHistory.js"></script>
</body>
</html>