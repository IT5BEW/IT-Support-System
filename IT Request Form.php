<?php
include 'Supabase.php';
session_start();

// 1. เช็คความปลอดภัย: ถ้าไม่ได้ Login ให้เด้งกลับไปหน้า login.php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. ดึงข้อมูล User
$logged_user  = $_SESSION['user_id'];
$data = supabase_query("/rest/v1/Users?User_ID=eq." . urlencode($logged_user) . "&select=*");
$user = (!empty($data)) ? $data[0] : null;

$computers = supabase_query("/rest/v1/Computer?select=*") ?? [];
usort($computers, function($a, $b) {return strnatcmp($a['Equipment_ID'], $b['Equipment_ID']);});
$mycomputer = array_filter($computers, fn($c) => $c['Equipment_ID'] === $user['Equipment_ID']);
$mycomputer = reset($mycomputer) ?: null;

$error_check1 = false;
$error_check2 = false;
$error_check3 = false;

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
        $siganture = $_POST['signature'] ?? '';

        if ($fixcom || $fixetc) {
            $error_check1 = true;
        }
        else if ($reinstall || $broken || $etc) {
            $error_check2 = true;
        } 
        else if($etc && empty(trim($etctext))){
            $error_check2 = true;
        }
        if (empty(trim($siganture))) {
            $error_check3 = true;
        } 
        else{
            date_default_timezone_set('Asia/Bangkok');
            $date = new DateTime('now');
            $date_formatted = $date->format('YmdHis');
            $date_ymd = $date->format('Y-m-d');

            $form_id = $user['User_ID'] . "_" . $date_formatted;

            if(!$etc){$etctext = '';}

            if($siganture == 'useSignature'){
                $usesignature = true;
                $signatureInput = $user['Signature'];
            }
            else{
                $usesignature = false;
                $signatureInput = null;
            }

            $data = [
                "Form_ID"      => $form_id,
                "User_ID"      => $user['User_ID'],
                "Equipment_ID" => $user['Equipment_ID'],
                "Section"      => $user['Section'],
                "FormStatus"   => 'WaitForApproval',
                "Date"         => $date_ymd,
                "FixCom"       => $fixcom,
                "FixETC"       => $fixetc,
                "ReInstall"    => $reinstall,
                "Broken"       => $broken,
                "ETC"          => $etc,
                "ETCText"      => $etctext,
                "CauseText1"   => $cause1,
                "CauseText2"   => $cause2,
                "CauseText3"   => $cause3,
                "UseSignature" => $usesignature,
                "Signature"    => $signatureInput 
            ];

            $status = 0;
            supabase_query("/rest/v1/RequestForm", "POST", $data, $status);

            if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "ส่งใบขอแจ้งซ่อมสำเร็จ";} 
            else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";}
        }

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
                    <form action="" id="mainForm" method="POST">
                        <div id="DescriptionBox">
                            <p style="margin: 0;"><b style="font-weight: bold;">รายละเอียดของผู้ใช้งาน</b></p>
                            <div class="DescriptionRow">
                                <div class="DescriptionCollumn">      
                                    <div class="DescriptionLabel"><i class="fa-solid fa-computer DescriptionIcon"></i>Equipment ID:</div>
                                    <div class="DescriptionInput" id="EquipmentID"><?php echo htmlspecialchars($user['Equipment_ID'] ?? 'ไม่มีคอมพิวเตอร์'); ?></div>
                                </div>
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-circle-user DescriptionIcon"></i>User:</div>
                                    <div class="DescriptionInput" id="User"><?php echo htmlspecialchars($user['Firstname'] ?? 'ไม่มีชื่อผู้ใช้'); ?></div>
                                </div>
                            </div>
                            <div class="DescriptionRow">
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-laptop-file DescriptionIcon"></i>Com. Name:</div>
                                    <div class="DescriptionInput" id="ComName"><?php echo htmlspecialchars($mycomputer['ComName'] ?? 'ไม่มีชื่อคอม'); ?></div>
                                </div>
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-building-user DescriptionIcon"></i>Section:</div>
                                    <div class="DescriptionInput" id="Section"><?php echo htmlspecialchars($user['Section'] ?? 'ไม่มีแผนก'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                        
                        <p style="margin: 0;"><span style="color: red;">* </span><b style="font-weight: bold;">โปรดระบุหัวข้อที่ต้องการปรับปรุงแก้ไข</b></p>
                        <input type="checkbox" id="FixCom" name="FixCom" value="1">
                        <label for="FixCom">ต้องการปรับปรุงแก้ไข คอมพิวเตอร์ โปรแกรมและอุปกรณ์ต่อพ่วง</label><br>

                        <input type="checkbox" id="FixETC" name="FixETC" value="1">
                        <label for="FixETC">ต้องการปรับปรุงแก้ไข อุปกรณ์ทางไอทีแบบอื่นๆ (เช่น Printer, Handheld, Wireless, Switch ฯลฯ)</label>
                    
                        <p class="requiredText" id="check1" <?= $error_check1 ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>
                        
                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">

                        <p style="margin: 0;"><span style="color: red;">* </span><b style="font-weight: bold;">ขอดำเนินการเพื่อ:</b></p>
                        <input type="checkbox" id="ReInstall" name="ReInstall" value="1">
                        <label for="ReInstall">ถอนหรือติดตั้งโปรแกรมใหม่</label><br>
                        <input type="checkbox" id="Broken" name="Broken" value="1">
                        <label for="Broken">อุปกรณ์ใช้งานไม่ได้ ชำรุด เสียหาย</label><br>
                        <div id="ETCBox">
                            <div id="ETCBoxLeft">
                                <input type="checkbox" id="ETC" name="ETC" onchange="UnlockFormETC()" value="1">
                                <label for="ETC"> อื่นๆ (ระบุ) </label>
                            </div>
                            <div id="ETCBoxRight">
                                <input type="text" id="ETCText" name="ETCText" class="limit-width" disabled>
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
                                    <input type="text" id="cause1" name="cause1" class="causeText limit-width">
                                </div>
                            </div>
                            <input type="text" id="cause2" name="cause2" class="causeText limit-width"><br>
                            <input type="text" id="cause3" name="cause3" class="causeText limit-width">
                        </div>

                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">

                        <div class="FormFooterContainer">
                            <div class="FormConfirmLeftItem">
                                <p style="margin: 0;"><span style="color: red;">* </span><b style="font-weight: bold;">การลงชื่อ</b></p>
                                <input type="radio" id="useName" name="signature" value="useName" checked><label for="useName">ใช้ชื่อจริงในการเซ็นเอกสาร: <?php echo htmlspecialchars($user['Firstname'] ?? 'ไม่มีชื่อผู้ใช้'); ?></label><br>
                                <input type="radio" id="useSignature" name="signature" value="useSignature" <?php if(empty($user['Signature'])) echo 'disabled'; ?>><label for="useSignature" id="useSignatureLabel">ใช้ลายเซ็นในการเซ็นเอกสาร:
                                <?php if (!empty($user['Signature'])): ?>
                                    <img src="<?= $user['Signature'] ?>" style="max-height: 80px; display: block; max-width: 225px;">
                                <?php endif ?>
                                 </label>
                                <p class="requiredText" id="check3" <?= $error_check3 ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>
                            </div>
                            <div class="FormConfirmRightItem">
                                <button type="submit" value="Submit" class="FormConfirmButton" id="submitButton" name="create_form">
                                    <i class="fa-solid fa-circle-check FormConfirmIcon"></i>
                                    <p class="FormConfirmLabel">ยืนยัน</p>
                                </button>
                                <button type="button" value="Cancel" class="FormConfirmButton" id="cancelButton" onclick="ClearForm()">
                                    <i class="fa-solid fa-circle-xmark FormConfirmIcon"></i>
                                    <p class="FormConfirmLabel">ยกเลิก</p>
                                </button>
                                <!-- <button type="button" value="Cancel" class="FormConfirmButton" id="downloadButton" onclick="DownloadForm()">
                                    <i class="fa-solid fa-circle-down FormConfirmIcon"></i>
                                    <p class="FormConfirmLabel">ดาวน์โหลด</p>
                                </button> -->
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