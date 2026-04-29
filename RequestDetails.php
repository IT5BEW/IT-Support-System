<?php
include 'Database.php';
session_start();

// เช็คความปลอดภัย: ถ้าไม่ได้ Login ให้เด้งกลับไปหน้า login.php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. ดึงข้อมูล User
$logged_user  = $_SESSION['user_id'];
$data = db_query('SELECT * FROM [Users] WHERE [User_ID] = :id', [':id' => $logged_user]);
$user = (!empty($data)) ? $data[0] : null;

if ($_SERVER["REQUEST_METHOD"] == "GET") { 
    $get_form_id = $_GET['form_id'] ?? null;
    if ($get_form_id) {
        $reports = db_query('SELECT * FROM [RequestForm] WHERE [Form_ID] = :id', [':id' => $get_form_id]) ?? [];
        $detailReport = (!empty($reports)) ? $reports[0] : null;
        if (!$detailReport) {
            header("Location: RequestHistory.php"); 
            exit();
        }
        $userData = db_query('SELECT [Firstname], [Lastname], [Section] FROM [Users] WHERE [UID] = :id', [':id' => $detailReport['UID']]) ?? [];
        $detailUser = (!empty($userData)) ? $userData[0] : null;

        $compData = db_query('SELECT [Equipment_ID], [ComName] FROM [Computer] WHERE [CID] = :id', [':id' => $detailReport['CID']]) ?? [];
        $detailComp = (!empty($compData)) ? $compData[0] : null;

        $approveData = db_query('SELECT * FROM [DepartmentHeadApproveForm] WHERE [Form_ID] = :id', [':id' => $get_form_id]) ?? [];
        $detailApprove = (!empty($approveData)) ? $approveData[0] : null;
        if ($detailApprove) {
            $approveUser = db_query('SELECT [Firstname], [Lastname] FROM [Users] WHERE [UID] = :id', [':id' => $detailApprove['UID']]) ?? [];
            $detailApproveUser = (!empty($approveUser)) ? $approveUser[0] : null;
        }
    }
}

$currentStatus = $detailReport['FormStatus'] ?? '';
function getStepClass($stepNumber, $currentStatus) {
    // กำหนดเงื่อนไขตาม Logic ธุรกิจของคุณ
    switch ($stepNumber) {
        case 1: // ขั้นตอนสร้างฟอร์ม
            return "done"; // สร้างแล้วเสมอ
        case 2: // ขั้นตอนอนุมัติ
            if ($currentStatus == 'HoD_Denied') return "cancel";
            if (in_array($currentStatus, ['WaitForConfirm','IT_Director_Denied', 'WaitForFixing', 'WaitForFinalize' ,'Fixed_Denied', 'Complete'])) return "done";
            if ($currentStatus == 'WaitForApproval') return "active";
            break;
        case 3: // ขั้นตอนยืนยัน
            if ($currentStatus == 'IT_Director_Denied') return "cancel";
            if (in_array($currentStatus, ['WaitForFixing', 'WaitForFinalize', 'Complete', 'Fixed_Denied'])) return "done";
            if ($currentStatus == 'WaitForConfirm') return "active";
            break;
        case 4: // ขั้นตอนซ่อมแซม
            if (in_array($currentStatus, ['WaitForFinalize', 'Complete', 'Fixed_Denied'])) return "done";
            if ($currentStatus == 'WaitForFixing') return "active";
            break;
        case 5: // ขั้นตอนสุดท้าย
            if ($currentStatus == 'Complete') return "done";
            if ($currentStatus == 'Fixed_Denied') return "cancel";
            if ($currentStatus == 'WaitForFinalize') return "active";
            break;
    }
    return ""; // ยังไม่ถึงขั้นตอนนั้น
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RequestDetails</title>
    <link rel="icon" type="image/x-icon" href="../- Image/BEW-Logo.ico">
    
    <link rel="stylesheet" href="RequestDetails Folder/RequestDetails.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <script src="https://unpkg.com/pdf-lib@1.17.1"></script>
    <script src="https://unpkg.com/@pdf-lib/fontkit@1.1.1"></script>
    <script src="https://unpkg.com/downloadjs@1.4.7"></script>
</head>
<body>
    <main>
        <?php include_once 'Navbar.php'; ?>

        <section id="mainSection">
            <div class="mainContainer">
                <div class="itemContainer">
                    <h1 style="font-weight: bold; margin-top: 0; text-align: center;">รายละเอียด<span style="white-space: nowrap; font-weight: bold;">ใบขอแจ้งซ่อม</span></h1> 
                    <ul class="progressbar" id="progress-bar">
                        <li class="<?php echo getStepClass(1, $currentStatus); ?>">สร้างคำขอ</li>
                        <li class="<?php echo getStepClass(2, $currentStatus); ?>">อนุมัติ</li>
                        <li class="<?php echo getStepClass(3, $currentStatus); ?>">ตรวจสอบ</li>
                        <li class="<?php echo getStepClass(4, $currentStatus); ?>">ซ่อมแซม</li>
                        <li class="<?php echo getStepClass(5, $currentStatus); ?>">เสร็จสิ้น</li>
                    </ul>

                    <div class="item">
                        <h2 style="font-weight: bold; margin:0 0 10px;">1. รายละเอียด<span style="white-space: nowrap; font-weight: bold;">การแจ้งซ่อม</span></h2>
                        <div class="FormInfoContainer">
                            <div class="FormInfoLeft">
                                <div class="FormInfoItem">
                                    <p style="margin: 0;"><b style="font-weight: bold;">รายละเอียดใบแจ้งซ่อม:</b></p>
                                    <ul style="margin: 0 0 10px;">
                                        <li>รหัสใบแจ้งซ่อม: <?php echo $detailReport['Form_ID']; ?></li>
                                        <li>วันที่แจ้งซ่อม: <?php echo $detailReport['Date']; ?></li>
                                    </ul>
                                    <p style="margin: 0;"><b style="font-weight: bold;">รายละเอียดผู้ใช้งาน:</b></p>
                                    <ul style="margin: 0 0 10px;">
                                        <li>Equipment ID: <?php echo $detailComp['Equipment_ID'] ?? 'ไม่มีคอมพิวเตอร์'; ?></li>
                                        <li>Com. Name: <?php echo $detailComp['ComName'] ?? 'ไม่มีชื่อคอมพิวเตอร์'; ?></li>
                                        <li>User: <?php echo ($detailUser['Firstname'] ?? 'ไม่มีชื่อผู้ใช้') . ' ' . ($detailUser['Lastname'] ?? 'ไม่มีนามสกุล'); ?></li>
                                        <li>Section: <?php echo $detailReport['Section'] ?? 'ไม่มีแผนก'; ?></li>
                                    </ul>
                                    <p style="margin: 0;"><b style="font-weight: bold;">หัวข้อที่ต้องการปรับปรุงแก้ไข:</b></p>
                                    <ul style="margin: 0 0 10px;">
                                        <?php if($detailReport['FixCom']): ?><li>ปรับปรุงแก้ไข คอมพิวเตอร์ โปรแกรมและอุปกรณ์ต่อพ่วง</li><?php endif ?>
                                        <?php if($detailReport['FixETC']): ?><li>ปรับปรุงแก้ไข อุปกรณ์ทางไอที<span style="white-space: nowrap;">แบบอื่นๆ</span></li><?php endif ?>
                                    </ul>
                                    <p style="margin: 0;"><b style="font-weight: bold;">การดำเนินการ:</b></p>
                                    <ul style="margin: 0 0 10px;">
                                        <?php if($detailReport['ReInstall']): ?><li>ถอนหรือติดตั้งโปรแกรมใหม่</li><?php endif ?>
                                        <?php if($detailReport['Broken']): ?><li>อุปกรณ์ใช้งานไม่ได้ ชำรุด เสียหาย</li><?php endif ?>
                                        <?php if($detailReport['ETC']): ?><li><?php echo $detailReport['ETCText'] ?><?php endif ?>
                                    </ul>
                                    <p style="margin: 0;"><b style="font-weight: bold;">เหตุผล:</b></p>
                                    <p style="margin: 0 0 10px;">
                                        <ul style="margin: 0 0 10px;">
                                            <li>
                                                <?php if(empty($detailReport['CauseText1']) && empty($detailReport['CauseText2']) && empty($detailReport['CauseText3'])): ?>
                                                    ไม่มีเหตุผลเพิ่มเติม
                                                <?php else: ?>
                                                    <?php echo $detailReport['CauseText1'] ?? ''; ?>
                                                    <?php echo $detailReport['CauseText2'] ?? ''; ?>
                                                    <?php echo $detailReport['CauseText3'] ?? ''; ?>
                                                <?php endif ?>
                                            </li>
                                        </ul>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="FormInfoRight">
                                <div class="FormInfoItem">
                                    <p style="margin: 0;"><b style="font-weight: bold;">ภาพประกอบ:</b></p>
                                    <div style="width: 100%;">
                                        <?php if (!empty($detailReport['DetailedImage'])): ?>
                                            <img src="<?= blob_to_data_uri($detailReport['DetailedImage'] ?? null, $detailReport['DetailedImageMime'] ?? null) ?>" alt="Detailed Image" style="object-fit: contain; width: 100%; height: 100%;">
                                        <?php else: ?>
                                            <p style="margin: 0 0 10px;">ไม่มีภาพประกอบ</p>
                                        <?php endif ?>
                                    </div>.
                                    <p style="margin: 0;"><b style="font-weight: bold;">ลายเซ็น:</b></p>
                                    <div style="width: 100%;">
                                        <?php if ($detailReport['UseSignature'] && !empty($detailReport['Signature'])): ?>
                                            <img src="<?= blob_to_data_uri($detailReport['Signature'] ?? null, $detailReport['SignatureMime'] ?? null) ?>" alt="Signature Image" style="object-fit: contain; max-width: 200px; max-height: 80px;">
                                        <?php else: ?>
                                            <ul style="margin: 0 0 10px;">
                                                <li>ใช้ชื่อจริง: <?php echo ($detailUser['Firstname'] ?? 'ไม่มีชื่อผู้ใช้') . ' ' . ($detailUser['Lastname'] ?? 'ไม่มีนามสกุล'); ?></li>
                                            </ul>
                                        <?php endif ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                    

                    <div class="FormInfoContainer">
                            <div class="FormInfoLeft item">
                                <h2 style="font-weight: bold; margin:0 0 10px;">2. การอนุมัติจาก<span style="white-space: nowrap; font-weight: bold;">หัวหน้าแผนก</span></h2>
                                <div class="FormInfoItem">
                                    <?php if ($currentStatus !== 'WaitForApproval'): ?>
                                        <p style="margin: 0;"><b style="font-weight: bold;">รายละเอียดการอนุมัติ:</b></p>
                                        <ul style="margin: 0;">
                                            <li>ผู้อนุมัติ: <?=$detailApproveUser ? $detailApproveUser['Firstname'] . ' ' . $detailApproveUser['Lastname'] : 'ไม่ทราบ'?></li>
                                            <li>สถานะ: <?=$detailApprove['IsApproved'] ? 'อนุมัติ' : 'ไม่อนุมัติ'?></li>
                                            <li>วันที่: <?= $detailApprove['ApproveDate'] ?? 'ไม่ทราบ' ?></li>
                                            <li>ลายเซ็น: <br>
                                                <?php if (!empty($detailApprove['Signature'])): ?>
                                                    <img src="<?= blob_to_data_uri($detailApprove['Signature'] ?? null, $detailApprove['SignatureMime'] ?? null) ?>" alt="Approver Signature" style="object-fit: contain; max-width: 200px; max-height: 80px;">
                                                <?php else: ?>
                                                    ไม่มีลายเซ็น
                                                <?php endif ?>
                                        </ul>
                                    <?php else: ?>
                                        <i class="fa-solid fa-circle-info"></i> รอการอนุมัติจากหัวหน้าแผนก
                                    <?php endif ?>
                                </div>
                            </div>
                        
                            <div class="FormInfoRight item">    
                                <h2 style="font-weight: bold; margin:0 0 10px;">3. การอนุมัติจาก<span style="white-space: nowrap; font-weight: bold;">หัวหน้าฝ่ายไอที</span></h2>
                                <div class="FormInfoItem">
                                    <p style="margin: 0;"><b style="font-weight: bold;">รายละเอียดการอนุมัติ:</b></p>
                                    <ul style="margin: 0;">
                                        <li>กำลังทำเว็บอยู่ครับ</li>
                                    </ul>
                                </div>
                            </div>
                    </div>
                    
                    <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                    
                    <div class="item">
                        <h2 style="font-weight: bold; margin:0 0 10px;">4. รายละเอียดการแก้ไข</h2>
                    </div>

                    <div id="buttonContainer">
                        <button type="button" value="Back" class="button" id="backButton" onclick="history.back()">
                            <i class="fa-solid fa-arrow-left FormConfirmIcon"></i>
                            <p class="FormConfirmLabel">กลับไป</p>
                        </button>
                        <button type="button" value="Home" class="button" id="homeButton" onclick="window.location.href='Home.php'" >
                            <i class="fa-solid fa-house FormConfirmIcon"></i>
                            <p class="FormConfirmLabel">กลับหน้าหลัก</p>
                        </button>
                        <button type="button" value="Download" class="button" id="downloadButton" onclick="DownloadForm(reportData)" style="margin-left: auto;">
                            <i class="fa-solid fa-download FormConfirmIcon"></i>
                            <p class="FormConfirmLabel">ดาวน์โหลด</p>
                        </button>
                    </div>

                </div>
            </div>
        </section>
    </main>

    <script>
        // ดึงค่าจาก PHP Variables ที่คุณมีอยู่แล้ว มาใส่ใน Object
        const reportData = {
            Date: "<?= $detailReport['Date'] ?? '' ?>",
        // ข้อมูลผู้ใช้
            // ข้อมูลคอมพิวเตอร์
            EquipmentID: "<?= $detailComp['Equipment_ID'] ?? 'ไม่มีคอมพิวเตอร์' ?>",
            ComName: "<?= $detailComp['ComName'] ?? 'ไม่มีชื่อคอมพิวเตอร์' ?>",
            User: "<?= $detailUser['Firstname'] ?? 'ไม่มีชื่อผู้ใช้' ?>",
            Section: "<?= $detailReport['Section'] ?? 'ไม่มีแผนก' ?>",
            // Checkboxes (แปลงเป็น boolean)
            FixCom: <?= $detailReport['FixCom'] == "1" ? 'true' : 'false' ?>,
            FixETC: <?= $detailReport['FixETC'] == "1" ? 'true' : 'false' ?>,
            ReInstall: <?= $detailReport['ReInstall'] == "1" ? 'true' : 'false' ?>,
            Broken: <?= $detailReport['Broken'] == "1" ? 'true' : 'false' ?>,
            ETC: <?= $detailReport['ETC'] == "1" ? 'true' : 'false' ?>,
            ETCText: "<?= $detailReport['ETCText'] ?? '' ?>",
            // เหตุผลการแจ้งซ่อม
            Cause1: "<?= $detailReport['CauseText1'] ?? '' ?>",
            Cause2: "<?= $detailReport['CauseText2'] ?? '' ?>",
            Cause3: "<?= $detailReport['CauseText3'] ?? '' ?>",
            UseSignature: <?= $detailReport['UseSignature'] == "1" ? 'true' : 'false' ?>,
            Signature: "<?= !empty($detailReport['Signature']) ? base64_encode(hex2bin(str_replace('0x', '', $detailReport['Signature']))) : '' ?>",
            SignatureMime: "<?= $detailReport['SignatureMime'] ?? 'image/png' ?>",
        // ข้อมูลการอนุมัติ
            ApproveStatus: <?= $detailApprove ? (($detailApprove['IsApproved']) ? 'true' : 'false') : 'false' ?>,        
            ApproveSignature: "<?= !empty($detailApprove['Signature']) ? base64_encode(hex2bin(str_replace('0x', '', $detailApprove['Signature']))) : '' ?>",
            ApproveSignatureMime: "<?= $detailApprove['SignatureMime'] ?? 'image/png' ?>",
            ApproveDate: "<?= $detailApprove['ApproveDate'] ?? '' ?>"
        };
    </script>

    <script src="RequestDetails Folder/RequestDetails.js"></script>
</body>
</html>