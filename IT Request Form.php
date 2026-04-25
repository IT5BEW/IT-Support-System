<?php
include 'Database.php';
session_start();

// 1. เช็คความปลอดภัย: ถ้าไม่ได้ Login ให้เด้งกลับไปหน้า login.php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. ดึงข้อมูล User
$logged_user  = $_SESSION['user_id'];
$data = db_query('SELECT * FROM [Users] WHERE [User_ID] = :id', [':id' => $logged_user]);
$user = (!empty($data)) ? $data[0] : null;

$computers = db_query('SELECT * FROM [Computer]') ?? [];
usort($computers, function($a, $b) {return strnatcmp($a['Equipment_ID'], $b['Equipment_ID']);});
$mycomputer = array_filter($computers, fn($c) => $c['Equipment_ID'] === $user['Equipment_ID']);
$mycomputer = reset($mycomputer) ?: null;

$error_check1 = false;
$error_check2 = false;
$error_check3 = false;
$error_filesize = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") { 
    if (isset($_POST['create_form'])) {
        $fixcom    = isset($_POST['FixCom']) ? true : false;
        $fixetc    = isset($_POST['FixETC']) ? true : false;
        $reinstall = isset($_POST['ReInstall']) ? true : false;
        $broken    = isset($_POST['Broken']) ? true : false;
        $etc       = isset($_POST['ETC']) ? true : false;
        $etctext   = $_POST['ETCText'] ?? '';
        $cause1    = $_POST['cause1'] ?? '';
        $cause2    = $_POST['cause2'] ?? '';
        $cause3    = $_POST['cause3'] ?? ''; // รับมาเพื่อเช็ค mismatch
        $signature = $_POST['signature'] ?? '';
        $detailImage = $_FILES['detailImage'] ?? null;


        $error_check1 = (!$fixcom && !$fixetc) ? true : false;
        $error_check2   = (!$reinstall && !$broken && !$etc) ? true : false;
        if ($etc && empty(trim($etctext))) { $error_check2 = true; }
        $error_check3   = (empty(trim($signature))) ? true : false;

        if ($detailImage && $detailImage['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($detailImage['error'] !== UPLOAD_ERR_OK) {
                $error_filesize = true;
                $_SESSION['flash_message'] = "ไฟล์มีปัญหา กรุณาลองอัปโหลดใหม่";
            } 
            elseif ($detailImage['size'] > 50 * 1024 * 1024) {
                $error_filesize = true;
                $_SESSION['flash_message'] = "ขนาดของภาพใหญ่เกินไป ขนาดต้องไม่เกิน 50MB";
            }
        }
        
        if (!$error_check1 && !$error_check2 && !$error_check3 && !$error_filesize) {
            date_default_timezone_set('Asia/Bangkok');
            $date = new DateTime('now');
            $date_formatted = $date->format('YmdHis');
            $date_ymd = $date->format('Y-m-d');

            $form_id = $user['User_ID'] . "_" . $date_formatted;

            if(!$etc){$etctext = '';}

            if ($detailImage && $detailImage['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadedDetailImage = uploadImageToForm($form_id, $detailImage, 'DetailedImage', 'DetailedImageMime');
                if ($uploadedDetailImage['success']) {
                    $detailImageInput = true; // BLOB บันทึกลง DB แล้ว
                } 
                else {
                    $status = $uploadedDetailImage['status'];
                    $_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการอัปโหลดประกอบ (Status: $status)";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
            else {$detailImageInput = null; }

            if($signature == 'useSignature'){
                $usesignature = true;
                $signatureUpload = getImageFromUrlAndUploadToForm($form_id, $user['User_ID']);
                if (!$signatureUpload['success']) {
                    $_SESSION['flash_message'] = $signatureUpload['error_msg'] ?? 'อัปโหลดลายเซ็นไม่สำเร็จ';
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
            else{
                $usesignature = false;
            }

            $data = [
                'Form_ID' => $form_id,
                'UID'     => $user['UID'],
                'CID'     => !empty($user['Equipment_ID'])
                    ? (db_query('SELECT [CID] FROM [Computer] WHERE [Equipment_ID] = :id', [':id' => $user['Equipment_ID']])[0]['CID'] ?? null)
                    : null,
                'Section' => $user['Section'],
                'FormStatus' => 'WaitForApproval',
                'Date' => $date_ymd,
                'FixCom' => $fixcom,
                'FixETC' => $fixetc,
                'ReInstall' => $reinstall,
                'Broken' => $broken,
                'ETC' => $etc,
                'ETCText' => $etctext,
                'CauseText1' => $cause1,
                'CauseText2' => $cause2,
                'CauseText3' => $cause3,
                'UseSignature' => $usesignature,
            ];

            $status = 0;
            $status = db_insert('RequestForm', $data) ? 201 : 500;

            if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "ส่งใบขอแจ้งซ่อมสำเร็จ";} 
            else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";}
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_POST['edit_form'])) {
        $get_form_id = $_POST['form_id_to_save'] ?? '';

        $status_check = 0;
        $existingData = db_query('SELECT * FROM [RequestForm] WHERE [Form_ID] = :id', [':id' => $get_form_id]);
        $editReport = (!empty($existingData)) ? $existingData[0] : null;

        $fixcom    = isset($_POST['FixCom']) ? true : false;
        $fixetc    = isset($_POST['FixETC']) ? true : false;
        $reinstall = isset($_POST['ReInstall']) ? true : false;
        $broken    = isset($_POST['Broken']) ? true : false;
        $etc       = isset($_POST['ETC']) ? true : false;
        $etctext   = $_POST['ETCText'] ?? '';
        $cause1    = $_POST['cause1'] ?? '';
        $cause2    = $_POST['cause2'] ?? '';
        $cause3    = $_POST['cause3'] ?? ''; // รับมาเพื่อเช็ค mismatch
        $signature = $_POST['signature'] ?? 'ทดสอบ';
        $detailImage = $_FILES['detailImage'] ?? null;

        $error_check1 = (!$fixcom && !$fixetc) ? true : false;
        $error_check2   = (!$reinstall && !$broken && !$etc) ? true : false;
        if ($etc && empty(trim($etctext))) { $error_check2 = true; }
        $error_check3   = (empty(trim($signature))) ? true : false;

        if ($detailImage && $detailImage['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($detailImage['error'] !== UPLOAD_ERR_OK) {
                $error_filesize = true;
                $_SESSION['flash_message'] = "ไฟล์มีปัญหา กรุณาลองอัปโหลดใหม่";
            } 
            elseif ($detailImage['size'] > 50 * 1024 * 1024) {
                $error_filesize = true;
                $_SESSION['flash_message'] = "ขนาดของภาพใหญ่เกินไป ขนาดต้องไม่เกิน 50MB";
            }
        }
        
        if (!$error_check1 && !$error_check2 && !$error_check3 && !$error_filesize && !empty(trim($get_form_id))) {
            date_default_timezone_set('Asia/Bangkok');
            $date = new DateTime('now');
            $date_formatted = $date->format('YmdHis');
            $date_ymd = $date->format('Y-m-d');

            $form_id = $get_form_id;

            if(!$etc){$etctext = '';}

            if ($detailImage && $detailImage['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadedDetailImage = uploadImageToForm($form_id, $detailImage, 'DetailedImage', 'DetailedImageMime');
                if ($uploadedDetailImage['success']) {
                    $detailImageInput = true; // BLOB บันทึกลง DB แล้ว
                } 
                else {
                    $status = $uploadedDetailImage['status'];
                    $_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการอัปโหลดประกอบ (Status: $status)";
                    header("Location: " . $_SERVER['PHP_SELF'] . "?form_id=" . urlencode($get_form_id));
                    exit;
                }
            }
            else {$detailImageInput = $editReport['DetailedImage'] ?? null;}

            $usesignature = $editReport['UseSignature'] ?? false;
            $signatureInput = $editReport['Signature'] ?? null;

            if ($signature == 'useSignature') {
                if (!empty($user['Signature'])) {
                    $signatureUpload = getImageFromUrlAndUploadToForm($form_id, $user['User_ID']);
                    if ($signatureUpload['success']) {
                        $usesignature = true;
                    } 
                    else {
                        $_SESSION['flash_message'] = $signatureUpload['error_msg'] ?? 'อัปโหลดลายเซ็นไม่สำเร็จ';
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }
                } 
                elseif (!empty($editReport['Signature'])) {
                    $usesignature = true;
                }
            } 
            else {
                $usesignature = false;
            }

            $data = [
                // UID และ CID ไม่เปลี่ยนตอน edit
                'Section' => $editReport['Section'] ?? $user['Section'],
                'Date' => $date_ymd,
                'FixCom' => $fixcom,
                'FixETC' => $fixetc,
                'ReInstall' => $reinstall,
                'Broken' => $broken,
                'ETC' => $etc,
                'ETCText' => $etctext,
                'CauseText1' => $cause1,
                'CauseText2' => $cause2,
                'CauseText3' => $cause3,
                'UseSignature' => $usesignature,
            ];

            $status = 0;
            $status = db_update('RequestForm', $data, ['Form_ID' => $get_form_id]) ? 200 : 500;

            if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "แก้ไขใบขอแจ้งซ่อมสำเร็จ";} 
            else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";}
        }
        else{
            $_SESSION['flash_message'] = "เกิดข้อผิดพลาด ไม่พบฟอร์มที่ต้องการแก้ไข: " . $get_form_id;
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?form_id=" . urlencode($get_form_id));
        exit;
    }

    if (isset($_POST['delete_image'])) {
        // ลองหาจาก POST ก่อน ถ้าไม่มีให้เอาจาก GET (ที่มากับ URL formaction)
        $form_id = $_POST['form_id_to_save'] ?? $_GET['form_id'] ?? '';

        $_SESSION['flash_message'] = $form_id;
        if (!empty($form_id)) {
            // ลบไฟล์ใน Storage
            deleteFilesByPrefix($form_id, 'DetailedImage', 'DetailedImageMime');
            
            $status = 0;
            $status = db_update('RequestForm', ['DetailedImage' => null, 'DetailedImageMime' => null], ['Form_ID' => $form_id]) ? 200 : 500;

            if ($status >= 200 && $status < 300) {
                $_SESSION['flash_message'] = "ลบรูปภาพประกอบสำเร็จ";
            } 
            else {
                $_SESSION['flash_message'] = "ลบจากฐานข้อมูลไม่สำเร็จ (Status: $status)";
            }
        } 
        else {
            $_SESSION['flash_message'] = "ไม่พบรหัสฟอร์มที่จะลบ";
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?form_id=" . urlencode($form_id));
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "GET") { 
    $get_form_id = $_GET['form_id'] ?? null;
    if ($get_form_id) {
        $reports = db_query('SELECT * FROM [RequestForm] WHERE [Form_ID] = :id', [':id' => $get_form_id]) ?? [];
        $editReport = (!empty($reports)) ? $reports[0] : null;
        if (!$editReport) {
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); 
            exit();
        }
        if ($editReport['UID'] !== $user['UID']) {
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); 
            exit();
        }
        $userData = db_query('SELECT [Firstname], [Section] FROM [Users] WHERE [UID] = :uid', [':uid' => $editReport['UID']]) ?? [];
        $editUser = (!empty($userData)) ? $userData[0] : null;

        $compData = !empty($editReport['CID']) ? db_query('SELECT [ComName] FROM [Computer] WHERE [CID] = :cid', [':cid' => $editReport['CID']]) : [];
        $editComp = (!empty($compData)) ? $compData[0] : null;
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Request Form</title>
    <link rel="icon" type="image/x-icon" href="../- Image/BEW-Logo.ico">

    <link rel="stylesheet" href="IT Form Folder/IT Request Form.css">

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

        <section id="forminputSection">
            <div class="forminputContainer">
                <div class="formItemContainer">
                    <h1 style="font-weight: bold; margin-top: 0; text-align: center;">ใบขอแจ้งซ่อม/ติดตั้งระบบสารสนเทศใหม่</h1> 
                    <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                    <form action="" id="mainForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="form_id_to_save" value="<?php echo $get_form_id; ?>">
                        <div id="DescriptionBox">
                            <p style="margin: 0;"><b style="font-weight: bold;">รายละเอียดของผู้ใช้งาน</b></p>
                            <div class="DescriptionRow">
                                <div class="DescriptionCollumn">      
                                    <div class="DescriptionLabel"><i class="fa-solid fa-computer DescriptionIcon"></i>Equipment ID:</div>
                                    <div class="DescriptionInput" id="EquipmentID"><?php echo htmlspecialchars($user['Equipment_ID'] ?? $editReport['Equipment_ID']  ?? 'ไม่มีคอมพิวเตอร์'); ?></div>
                                </div>
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-circle-user DescriptionIcon"></i>User:</div>
                                    <div class="DescriptionInput" id="User"><?php echo htmlspecialchars($user['Firstname'] ?? $editUser['Firstname'] ?? 'ไม่มีชื่อผู้ใช้'); ?></div>
                                </div>
                            </div>
                            <div class="DescriptionRow">
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-laptop-file DescriptionIcon"></i>Com. Name:</div>
                                    <div class="DescriptionInput" id="ComName"><?php echo htmlspecialchars($mycomputer['ComName'] ?? $editComp['ComName'] ?? 'ไม่มีชื่อคอม'); ?></div>
                                </div>
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-building-user DescriptionIcon"></i>Section:</div>
                                    <div class="DescriptionInput" id="Section"><?php echo htmlspecialchars($user['Section'] ?? $editUser['Section'] ?? 'ไม่มีแผนก'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                        
                        <p style="margin: 0;"><span style="color: red;">* </span><b style="font-weight: bold;">โปรดระบุหัวข้อที่ต้องการปรับปรุงแก้ไข</b></p>
                        <input type="checkbox" id="FixCom" name="FixCom" value="1" <?php echo (!empty($editReport['FixCom']) && $editReport['FixCom'] === true) ? 'checked' : ''; ?>>
                        <label for="FixCom">ต้องการปรับปรุงแก้ไข คอมพิวเตอร์ โปรแกรมและอุปกรณ์ต่อพ่วง</label><br>

                        <input type="checkbox" id="FixETC" name="FixETC" value="1" <?php echo (!empty($editReport['FixETC']) && $editReport['FixETC'] === true) ? 'checked' : ''; ?>>
                        <label for="FixETC">ต้องการปรับปรุงแก้ไข อุปกรณ์ทางไอทีแบบอื่นๆ (เช่น Printer, Handheld, Wireless, Switch ฯลฯ)</label>
                    
                        <p class="requiredText" id="check1" <?= $error_check1 ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>
                        
                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">

                        <p style="margin: 0;"><span style="color: red;">* </span><b style="font-weight: bold;">ขอดำเนินการเพื่อ:</b></p>
                        <input type="checkbox" id="ReInstall" name="ReInstall" value="1" <?php echo (!empty($editReport['ReInstall']) && $editReport['ReInstall'] === true) ? 'checked' : ''; ?>>
                        <label for="ReInstall">ถอนหรือติดตั้งโปรแกรมใหม่</label><br>
                        <input type="checkbox" id="Broken" name="Broken" value="1" <?php echo (!empty($editReport['Broken']) && $editReport['Broken'] === true) ? 'checked' : ''; ?>>
                        <label for="Broken">อุปกรณ์ใช้งานไม่ได้ ชำรุด เสียหาย</label><br>
                        <div id="ETCBox">
                            <div id="ETCBoxLeft">
                                <input type="checkbox" id="ETC" name="ETC" onchange="UnlockFormETC()" value="1" <?php echo (!empty($editReport['ETC']) && $editReport['ETC'] === true) ? 'checked' : ''; ?>>
                                <label for="ETC"> อื่นๆ (ระบุ) </label>
                            </div>
                            <div id="ETCBoxRight">
                                <input type="text" id="ETCText" name="ETCText" class="limit-width" value="<?php echo htmlspecialchars($editReport['ETCText'] ?? ''); ?>"
                                <?php echo (!empty($editReport['ETC']) && $editReport['ETC'] === true) ? '' : 'disabled'; ?> >
                            </div>
                        </div>
                        <p class="requiredText" id="check2" <?= $error_check2 ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>

                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">

                        <div class="causeTextBox">
                            <div id="causeTextFirstLineBox">
                                <div id="causeTextFirstLineBoxLeft">
                                    <b style="font-weight: bold;">เหตุผล/รายละเอียดในการขอดำเนินการ :</b>
                                </div>
                                <div id="causeTextFirstLineBoxRight">
                                    <input type="text" id="cause1" name="cause1" class="causeText limit-width" value="<?php echo htmlspecialchars($editReport['CauseText1'] ?? ''); ?>">
                                </div>
                            </div>
                            <input type="text" id="cause2" name="cause2" class="causeText limit-width" value="<?php echo htmlspecialchars($editReport['CauseText2'] ?? ''); ?>"><br>
                            <input type="text" id="cause3" name="cause3" class="causeText limit-width" value="<?php echo htmlspecialchars($editReport['CauseText3'] ?? ''); ?>">
                        </div>

                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">

                        <p style="margin: 0;"><b style="font-weight: bold;">รูปภาพประกอบ :</b></p>
                        <?php if (!empty($editReport['DetailedImage'])): ?>
                            <img src="<?= blob_to_data_uri($editReport['DetailedImage'] ?? null, $editReport['DetailedImageMime'] ?? null) ?>" class="previewImage" onerror="this.style.display='none';">
                            <button type="button" id="removeImageButton" onclick="execDeleteImage('<?= $get_form_id ?>')">
                                <i class="fa-solid fa-trash-can"></i>
                                <p class="buttonLabel">นำรูปภาพออก</p>
                            </button>
                            <br><p style="margin: 0;"><b style="font-weight: bold;">เปลี่ยนรูปภาพใหม่: </b></p>
                        <?php endif ?>
                        <input type="file" id="detailImage" name="detailImage" style="width: 100%; height: auto; margin-top: 5px;" accept="image/*" onchange="previewImage(event)">
                        <img id="output-image" class="previewImage">

                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">

                        <div class="FormFooterContainer">
                            <div class="FormConfirmLeftItem">
                                <p style="margin: 0;"><span style="color: red;">* </span><b style="font-weight: bold;">การลงชื่อ</b></p>
                                <?php 
                                    $useSig = $editReport['UseSignature'] ?? false;
                                    $hasSig = !empty($editReport['Signature']) || !empty($user['Signature']);
                                ?>
                                <input type="radio" id="useName" name="signature" value="useName" <?php if (!$useSig || !$hasSig) echo 'checked'; ?>>
                                <label for="useName">ใช้ชื่อจริงในการเซ็นเอกสาร: <?php echo htmlspecialchars($editUser['Firstname'] ?? $user['Firstname'] ?? 'ไม่มีชื่อผู้ใช้'); ?></label><br>
                                <input type="radio" id="useSignature" name="signature" value="useSignature" <?php echo (!$hasSig) ? 'disabled' : ''; ?> <?php echo ($hasSig) ? 'checked' : ''; ?> >
                                <label for="useSignature" id="useSignatureLabel">ใช้ลายเซ็นในการเซ็นเอกสาร:
                                <?php if ($hasSig): ?>
                                    <img src="<?= blob_to_data_uri($user['Signature'] ?? $editReport['Signature'] ?? null, $user['SignatureMime'] ?? $editReport['SignatureMime'] ?? null) ?>" style="max-height: 80px;">
                                <?php endif ?>
                                 </label>
                                <p class="requiredText" id="check3" <?= $error_check3 ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>
                            </div>
                            <div class="FormConfirmRightItem">
                                <?php if (!$get_form_id): // ถ้าไม่มี ID ส่งมา (โหมดสร้างฟอร์มใหม่) ให้โชว์ปุ่ม ?>
                                    <button type="submit" value="Submit" class="FormConfirmButton" id="submitButton" name="create_form">
                                        <i class="fa-solid fa-circle-check FormConfirmIcon"></i>
                                        <p class="FormConfirmLabel">ยืนยัน</p>
                                    </button>
                                    <button type="button" value="Cancel" class="FormConfirmButton" id="cancelButton" onclick="ClearForm()">
                                        <i class="fa-solid fa-circle-xmark FormConfirmIcon"></i>
                                        <p class="FormConfirmLabel">ล้างข้อมูล</p>
                                    </button>
                                <?php endif; ?>  
                                <?php if ($get_form_id): // หรือถ้ามี ID ส่งมา (โหมดแก้ไข) คุณอาจจะโชว์ปุ่ม "อัปเดต" แทน ?>
                                    <button type="submit" value="Submit" class="FormConfirmButton" id="editButton" name="edit_form">
                                        <i class="fa-solid fa-pen-to-square FormConfirmIcon"></i>
                                        <p class="FormConfirmLabel">แก้ไข</p>
                                    </button>
                                    <button type="button" value="Cancel" class="FormConfirmButton" id="backButton" onclick="window.location.href='RequestHistory.php'">
                                        <i class="fa-solid fa-circle-xmark FormConfirmIcon"></i>
                                        <p class="FormConfirmLabel">กลับ</p>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <script src="IT Form Folder/IT Request Form.js"></script>

    <?php if (isset($_SESSION['flash_message'])): ?>
            <script>
                // รอให้เบราว์เซอร์โหลด HTML และวาดหน้าจอ (Render) ให้เสร็จก่อน
                window.onload = function() {
                    // หน่วงเวลาอีก 100-200ms เพื่อให้แน่ใจว่าหน้าเว็บแสดงผลครบแล้วค่อยเด้ง
                    setTimeout(function() {
                        alert("<?php echo $_SESSION['flash_message']; ?>");
                    }, 100);
                };
            </script>
        <?php 
            unset($_SESSION['flash_message']); // ลบข้อความทิ้ง ไม่ให้เด้งซ้ำตอนกด refresh เอง
        endif; 
    ?>

</body>


</html>