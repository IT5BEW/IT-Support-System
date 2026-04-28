<?php
include 'Database.php';
session_start();

// 1. เช็คความปลอดภัย: ถ้าไม่ได้ Login ให้เด้งกลับไปหน้า login.php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
$logged_user  = $_SESSION['user_id'];
// lookup UID จาก User_ID ก่อน แล้วค่อย query RequestForm
$userData = db_query('SELECT * FROM [Users] WHERE [User_ID] = :id', [':id' => $logged_user]);
$user = (!empty($userData)) ? $userData[0] : null;

$approving = db_query('SELECT * FROM [RequestForm] WHERE [Section] = :section ORDER BY [Date] DESC', [':section' => $user['Section']]);
usort($approving, function($a, $b) {
    // 1. กำหนดลำดับ (Priority) ให้แต่ละ Status (เลขน้อย = อยู่บนสุด)
    $priority = [
        'WaitForApproval'    => 1,
        'WaitForConfirm'     => 2,
        'HoD_Denied'         => 3,
        'WaitForFixing'      => 4,
        'WaitForFinalize'    => 5,
        'Fixed_Denied'       => 6,
        'FormCreated'        => 7,
        'Complete'           => 8,
        'IT_Director_Denied' => 9,
    ];

    // ดึงค่าลำดับ ถ้าไม่มีในลิสต์ให้เป็น 99 (อยู่ล่างสุด)
    $pA = $priority[$a['FormStatus']] ?? 99;
    $pB = $priority[$b['FormStatus']] ?? 99;

    // 2. ถ้าสถานะต่างกัน ให้เรียงตามลำดับความสำคัญ (Status Priority)
    if ($pA !== $pB) {return $pA - $pB;}

    // 3. ถ้าสถานะเหมือนกัน ให้เรียงตามวันที่ (เอา "ใหม่ล่าสุด" ขึ้นก่อน)
    return strtotime($b['Date']) - strtotime($a['Date']);
});

$approvingMap = [];
foreach($approving as $apv){
    $approvingMap[$apv['Form_ID']] = [
        'Form_ID'    => $apv['Form_ID'] ?? '',
        'Date'       => $apv['Date'] ?? '',
        'UID'        => $apv['UID'] ?? '',
        'FixCom'     => $apv['FixCom'] ?? false,
        'FixETC'     => $apv['FixETC'] ?? false,
        'ReInstall'  => $apv['ReInstall'] ?? false,
        'Broken'     => $apv['Broken'] ?? false,
        'ETC'        => $apv['ETC'] ?? false,
        'ETCText'    => $apv['ETCText'] ?? '',
        'FormStatus' => $apv['FormStatus'] ?? '',
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") { 
    if (isset($_POST['ApproveForm'])) {
        $Form_ID    = $_POST['form_id'] ?? '';
        $data = [
            'Form_ID' => $Form_ID,
            'UID'     => $user['UID'] ?? '',
            'ApproveDate'    => date('Y-m-d'),
            'Signature' => $user['Signature'] ?? '',
            'SignatureMime' => $user['SignatureMime'] ?? '',
            'IsApproved' => 1,
        ];

        $status = 0;
        $status = db_insert('DepartmentHeadApproveForm', $data) ? 201 : 500;
            if ($status >= 200 && $status < 300) {
                $updateStatus = 0;
                $updateStatus = db_update('RequestForm', ['FormStatus' => 'WaitForConfirm'], ['Form_ID' => $Form_ID]) ? 200 : 500;

                if($updateStatus >= 200 && $updateStatus < 300){$_SESSION['flash_message'] = 'อนุมัติใบขอแจ้งซ่อมเรียบร้อยแล้ว';} 
                else {$_SESSION['flash_message'] = 'เกิดข้อผิดพลาดในการอนุมัติใบขอแจ้งซ่อม ddd';}
            } 
            else {$_SESSION['flash_message'] = 'เกิดข้อผิดพลาดในการอนุมัติใบขอแจ้งซ่อม';}

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_POST['DenyForm'])) {
        $Form_ID    = $_POST['form_id'] ?? '';
        $data = [
            'Form_ID' => $Form_ID,
            'UID'     => $user['UID'] ?? '',
            'ApproveDate'    => date('Y-m-d'),
            'Signature' => $user['Signature'] ?? '',
            'SignatureMime' => $user['SignatureMime'] ?? '',
            'IsApproved' => 0,
        ];

        $status = 0;
        $status = db_insert('DepartmentHeadApproveForm', $data) ? 201 : 500;
            if ($status >= 200 && $status < 300) {
                $updateStatus = 0;
                $updateStatus = db_update('RequestForm', ['FormStatus' => 'HoD_Denied'], ['Form_ID' => $Form_ID]) ? 200 : 500;

                if($updateStatus >= 200 && $updateStatus < 300){$_SESSION['flash_message'] = 'ปฏิเสธใบขอแจ้งซ่อมเรียบร้อยแล้ว';} 
                else {$_SESSION['flash_message'] = 'เกิดข้อผิดพลาดในการปฏิเสธใบขอแจ้งซ่อม ddd';}
            } 
            else {$_SESSION['flash_message'] = 'เกิดข้อผิดพลาดในการปฏิเสธใบขอแจ้งซ่อม';}

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_POST['ResetForm'])) {
        $Form_ID    = $_POST['form_id'] ?? '';

        $status = 0;
        $status = db_remove('DepartmentHeadApproveForm', ['Form_ID' => $Form_ID]) ? 201 : 500;
            if ($status >= 200 && $status < 300) {
                $updateStatus = 0;
                $updateStatus = db_update('RequestForm', ['FormStatus' => 'WaitForApproval'], ['Form_ID' => $Form_ID]) ? 200 : 500;

                if($updateStatus >= 200 && $updateStatus < 300){$_SESSION['flash_message'] = 'รีเซ็ตสถานะใบขอแจ้งซ่อมเรียบร้อยแล้ว';} 
                else {$_SESSION['flash_message'] = 'เกิดข้อผิดพลาดในการรีเซ็ตสถานะใบขอแจ้งซ่อม';}
            } 
            else {$_SESSION['flash_message'] = 'เกิดข้อผิดพลาดในการรีเซ็ตสถานะใบขอแจ้งซ่อม';}

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report History</title>
    <link rel="icon" type="image/x-icon" href="../- Image/BEW-Logo.ico">
    
    <link rel="stylesheet" href="RequestApproving Folder/RequestApproving.css">

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
                        <h1 id="headerText">การร้องขอการแจ้งซ่อม</h1>
                        <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="ค้นหา"> 
                    </div>
                    <div class="filter-buttons" style="margin-bottom: 25px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="button searchBtn" id="allBtn" onclick="filterStatus('all')"><p class="buttonLabel">ทั้งหมด</p></button>
                        <button class="button searchBtn" id="waitBtn" onclick="filterStatus('รอ')" ><p class="buttonLabel">รออนุมัติ</p></button>
                        <button class="button searchBtn" id="denyBtn" onclick="filterStatus('ไม่อนุมัติ')"><p class="buttonLabel">ไม่อนุมัติ</p></button>
                        <button class="button searchBtn" id="completeBtn" onclick="filterStatus('อนุมัติแล้ว')"><p class="buttonLabel">อนุมัติแล้ว</p></button>
                    </div>
                    <div id="tableContainer">
                        <table id="historyTable">
                            <tr>
                                <th class="dateCol">วันที่</th>
                                <th class="userCol">ผู้ร้องขอ</th>
                                <th class="fixCol">หัวข้อที่ต้องการ</th>
                                <th class="topicCol">การดำเนินการ</th>
                                <th class="statusCol">สถานะ</th>
                                <th class="approveCol">การจัดการ</th>
                                <th class="buttonCol">รายละเอียด</th>
                            </tr>
                            <?php foreach($approvingMap as $a): ?>
                                <tr>
                                    <td class="dateCol"><?= $a['Date'] ?></td>
                                    <td class="userCol">
                                        <?php 
                                            $requesterData = db_query('SELECT * FROM [Users] WHERE [UID] = :id', [':id' => $a['UID']]);
                                            $requester = (!empty($requesterData)) ? $requesterData[0] : null;
                                            echo $requester['Firstname'] . ' ' . $requester['Lastname'] ?? 'ไม่พบข้อมูล';
                                        ?>
                                    </td>
                                    <td class="fixCol">
                                        <ul class="ulTable">
                                            <?php if($a['FixCom']): ?><li>ปรับปรุงแก้ไข คอมพิวเตอร์ โปรแกรมและอุปกรณ์ต่อพ่วง</li><?php endif ?>
                                            <?php if($a['FixETC']): ?><li>ปรับปรุงแก้ไข อุปกรณ์ทางไอทีแบบอื่นๆ</li><?php endif ?>
                                        </ul>
                                    </td>
                                    <td class="topicCol">
                                        <ul class="ulTable">
                                            <?php if($a['ReInstall']): ?><li>ถอนหรือติดตั้งโปรแกรมใหม่</li><?php endif ?>
                                            <?php if($a['Broken']): ?><li>อุปกรณ์ใช้งานไม่ได้ ชำรุด เสียหาย</li><?php endif ?>
                                            <?php if($a['ETC']): ?><li><?= $a['ETCText'] ?><?php endif ?>
                                        </ul>
                                    </td>
                                    <td class="statusCol">
                                        <?php if($a['FormStatus'] == 'WaitForApproval'): ?><div class="status statusYellow">รออนุมัติ</div>
                                        <?php elseif($a['FormStatus'] == 'WaitForConfirm'): ?><div class="status statusGreen">อนุมัติแล้ว</div>
                                        <?php elseif($a['FormStatus'] == 'HoD_Denied'): ?><div class="status statusRed">ไม่อนุมัติ</div>
                                        <?php elseif($a['FormStatus'] == 'WaitForFixing'): ?><div class="status statusGreen">อนุมัติแล้ว</div>
                                        <?php elseif($a['FormStatus'] == 'WaitForFinalize'): ?><div class="status statusGreen">อนุมัติแล้ว</div>
                                        <?php elseif($a['FormStatus'] == 'Fixed_Denied'): ?><div class="status statusGreen">อนุมัติแล้ว</div>
                                        <?php elseif($a['FormStatus'] == 'IT_Director_Denied'): ?><div class="status statusGreen">อนุมัติแล้ว</div>
                                        <?php elseif($a['FormStatus'] == 'Complete'): ?><div class="status statusGreen">อนุมัติแล้ว</div>
                                        <?php else: ?><div class="status statusYellow">สถานะ</div>
                                        <?php endif ?>
                                    </td>
                                    <td class="approveCol">
                                        <?php if($a['FormStatus'] == 'WaitForApproval'): ?>
                                            <?php if(!empty($user['Signature'])): ?>
                                                <form action="" method="POST" class="formContainer">
                                                    <input type="hidden" name="form_id" value="<?php echo $a['Form_ID'] ?>">
                                                    <div style="display: flex; justify-content: space-evenly;">
                                                        <button class="mediumbutton confirmButton" type="submit" value="Submit" name="ApproveForm" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะอนุมัติใบขอแจ้งซ่อมนี้?')">
                                                            <i class="fa-solid fa-check"></i>
                                                            <p class="mediumbuttonLabel">อนุมัติ</p>
                                                        </button>
                                                        <button class="mediumbutton cancleButton" type="submit" value="Submit" name="DenyForm" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะไม่อนุมัติใบขอแจ้งซ่อมนี้?')">
                                                            <i class="fa-solid fa-times"></i>
                                                            <p class="mediumbuttonLabel">ไม่อนุมัติ</p>
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: var(--edit);"><i class="fa-solid fa-circle-info"></i> กรุณาอัพโหลดลายเซ็น</span>
                                            <?php endif ?>
                                        <?php elseif($a['FormStatus'] == 'WaitForConfirm' || $a['FormStatus'] == 'HoD_Denied'): ?>
                                            <form action="" method="POST" class="formContainer">
                                                <input type="hidden" name="form_id" value="<?php echo $a['Form_ID'] ?>">
                                                <div style="display: flex; justify-content: space-evenly;">
                                                    <button class="mediumbutton resetButton" type="submit" value="Submit" name="ResetForm" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะแก้ไขการอนุมัติใบขอแจ้งซ่อมนี้?')">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                        <p class="mediumbuttonLabel">แก้ไขการอนุมัติ</p>
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: var(--statusGreenTX);"><i class="fa-solid fa-circle-info"></i> ไม่ต้องดำเนินการ</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="buttonCol">
                                        <div class="buttonColContainer">
                                            <button class="smallbutton infoButton" onclick="window.location.href='RequestDetails.php?form_id=<?php echo $a['Form_ID'] ?>'">
                                                <i class="fa-solid fa-file-lines"></i>
                                                <p class="smallbuttonLabel">รายละเอียด</p>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <button type="button" value="Home" class="button" id="homeButton" onclick="window.location.href='Home.php'" style="margin-top: 25px;">
                        <i class="fa-solid fa-house FormConfirmIcon"></i>
                        <p class="FormConfirmLabel">กลับหน้าหลัก</p>
                    </button>
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
    
    <script src="RequestApproving Folder/RequestApproving.js"></script>
</body>
</html>