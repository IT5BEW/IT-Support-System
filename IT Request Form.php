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
                    <form action="javascript:;" onsubmit="fillForm()" id="mainForm">
                        <div id="DescriptionBox">
                            <p style="margin: 0;"><b style="font-weight: bold;">รายละเอียดของผู้ใช้งาน</b></p>
                            <div class="DescriptionRow">
                                <div class="DescriptionCollumn">      
                                    <div class="DescriptionLabel"><i class="fa-solid fa-computer DescriptionIcon"></i>Equipment ID:</div>
                                    <div class="DescriptionInput" id="EquipmentID"><?php echo htmlspecialchars($user['Equipment_ID'] ?? 'ไม่มีคอมพิวเตอร์'); ?></div>
                                </div>
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-circle-user DescriptionIcon"></i>User:</div>
                                    <div class="DescriptionInput" id="User"><?php echo htmlspecialchars($user['ComUsername'] ?? 'ไม่มีชื่อผู้ใช้'); ?></div>
                                </div>
                            </div>
                            <div class="DescriptionRow">
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-laptop-file DescriptionIcon"></i>Com. Name:</div>
                                    <div class="DescriptionInput" id="ComName">TEST</div>
                                </div>
                                <div class="DescriptionCollumn">
                                    <div class="DescriptionLabel"><i class="fa-solid fa-building-user DescriptionIcon"></i>Section:</div>
                                    <div class="DescriptionInput" id="Section"><?php echo htmlspecialchars($user['Section'] ?? 'ไม่มีแผนก'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                        
                        <p style="margin: 0;"><span style="color: red;">* </span><b style="font-weight: bold;">โปรดระบุหัวข้อที่ต้องการปรับปรุงแก้ไข</b></p>
                        <input type="checkbox" id="FixCom" name="FixCom">
                        <label for="FixCom">ต้องการปรับปรุงแก้ไข คอมพิวเตอร์ โปรแกรมและอุปกรณ์ต่อพ่วง</label><br>

                        <input type="checkbox" id="FixETC" name="FixETC">
                        <label for="FixETC">ต้องการปรับปรุงแก้ไข อุปกรณ์ทางไอทีแบบอื่นๆ (เช่น Printer, Handheld, Wireless, Switch ฯลฯ)</label>
                    
                        <p class="requiredText" id="check1" hidden><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>
                        
                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">

                        <p style="margin: 0;"><span style="color: red;">* </span><b style="font-weight: bold;">ขอดำเนินการเพื่อ:</b></p>
                        <input type="checkbox" id="ReInstall" name="ReInstall">
                        <label for="ReInstall">ถอนหรือติดตั้งโปรแกรมใหม่</label><br>
                        <input type="checkbox" id="Broken" name="Broken">
                        <label for="Broken">อุปกรณ์ใช้งานไม่ได้ ชำรุด เสียหาย</label><br>
                        <div id="ETCBox">
                            <div id="ETCBoxLeft">
                                <input type="checkbox" id="ETC" name="ETC" onchange="UnlockFormETC()">
                                <label for="ETC"> อื่นๆ (ระบุ) </label>
                            </div>
                            <div id="ETCBoxRight">
                                <input type="text" id="ETCText" name="ETC" class="limit-width" disabled>
                            </div>
                        </div>
                        <p class="requiredText" id="check2" hidden><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>

                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">

                        <div class="causeTextBox">
                            <div id="causeTextFirstLineBox">
                                <div id="causeTextFirstLineBoxLeft">
                                    <label for="cause"><b style="font-weight: bold;">เหตุผล/รายละเอียดในการขอดำเนินการ :</b></label>
                                </div>
                                <div id="causeTextFirstLineBoxRight">
                                    <input type="text" id="cause1" name="cause" class="causeText limit-width">
                                </div>
                            </div>
                            <input type="text" id="cause2" name="cause" class="causeText limit-width"><br>
                            <input type="text" id="cause3" name="cause" class="causeText limit-width">
                        </div>

                        <div class="FormFooterContainer">
                            <div class="FormConfirmLeftItem">
                                <label for="nameText"><span style="color: red;">* </span><b style="font-weight: bold;">ลงชื่อ: </b></label><br>
                                <input type="text" id="nameText" name="NameText" style="width: 100%;" class="limit-width">
                                <p class="requiredText" id="check3" hidden><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>
                            </div>
                            <div class="FormConfirmRightItem">
                                <button type="submit" value="Submit" class="FormConfirmButton" id="submitButton">
                                    <i class="fa-solid fa-circle-check FormConfirmIcon"></i>
                                    <p class="FormConfirmLabel">ยืนยัน</p>
                                </button>
                                <button type="button" value="Cancel" class="FormConfirmButton" id="cancelButton" onclick="ClearForm()">
                                    <i class="fa-solid fa-circle-xmark FormConfirmIcon"></i>
                                    <p class="FormConfirmLabel">ยกเลิก</p>
                                </button>
                                <button type="button" value="Cancel" class="FormConfirmButton" id="downloadButton" onclick="DownloadForm()">
                                    <i class="fa-solid fa-circle-down FormConfirmIcon"></i>
                                    <p class="FormConfirmLabel">ดาวน์โหลด</p>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</body>

<script src="IT Form Folder/IT Request Form.js"></script>

</html>