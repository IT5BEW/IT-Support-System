<?php
include 'Supabase.php';
include 'Bucket.php';
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

$datas = []; 
$users = [];
$computers = [];
$userSigMap = [];
$userMap = [];

if ($user['Role'] == 'IT' || $user['Role'] == 'IT_Director'){
    $datas = supabase_query("/rest/v1/Users?select=*") ?? [];
    $users = array_column($datas, 'User_ID');
    $computers = supabase_query("/rest/v1/Computer?select=*") ?? [];
    foreach ($datas as $row) {
        $userSigMap[$row['User_ID']] = $row['Signature'] ?? null;
    }
    foreach ($datas as $u) {
        $userMap[$u['User_ID']] = [
            'Firstname' => $u['Firstname'] ?? '',
            'Lastname' => $u['Lastname'] ?? '',
            'Section' => $u['Section'] ?? '',
            'Role' => $u['Role'] ?? '',
            'Equipment_ID' => $u['Equipment_ID'] ?? '',
            'ComUsername' => $u['ComUsername'] ?? ''
        ];
    }
}
$sigJson = htmlspecialchars(json_encode($userSigMap), ENT_QUOTES, 'UTF-8');
$userJson = htmlspecialchars(json_encode($userMap), ENT_QUOTES, 'UTF-8');
$comJson = htmlspecialchars(json_encode($computers), ENT_QUOTES, 'UTF-8');

$all_sections = ['IT', 'AC', 'HR', 'PUR', 'SALES', 'PC', 'PJ&System', 'ENG-PE', 'QA-QC', 'MT', 'Production'];
$all_roles = ['User', 'HoD', 'IT', 'IT_Director'];

// ตั้งค่าเริ่มต้นให้ทุกอย่างเป็นเท็จ
$error_empty = false;
$error_wrong_pass = false;
$error_mismatch = false;
$error_match_pass = false;
$error_noimage = false;
$error_bigimage = false;
$error_nameempty = false;
$error_useridempty = false;
$error_edit_userid_empty = false;
$error_match_userid = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") { 
    if (isset($_POST['update_profile'])) {
        $user_id = $_POST['user_id'] ?? $user['User_ID'];
        // หาข้อมูลของคนที่จะถูกอัปเดตจาก $datas
        $key = array_search($user_id, array_column($datas ?? [], 'User_ID'));
        // ถ้าหาเจอใน $datas ให้ใช้ค่านั้น ถ้าไม่เจอ (เช่น User แก้ตัวเอง) ให้ใช้ $user
        $target_user = ($key !== false) ? $datas[$key] : $user;

        $dataRaw = [
            "Firstname"    => $_POST['firstname'] ?? null,
            "Lastname"     => $_POST['lastname'] ?? null,
            "Section"      => $_POST['section'] ?? null,
            "Role"         => $_POST['role'] ?? null,
            "Equipment_ID" => ($_POST['equipment_id'] !== "") ? $_POST['equipment_id'] : null,
            "ComUsername"  => ($_POST['comusername'] !== "") ? $_POST['comusername'] : null,
        ];
        // รายการที่ยอมให้ส่ง null ไปเพื่อ "ล้างค่า" ใน DB
        $allowNull = ["Equipment_ID", "ComUsername"];
        // เอาค่าที่ไม่ได้ส่งออก
        $dataInput = array_filter($dataRaw, function($value, $key) use ($user, $allowNull) {
            // กฎข้อที่ 1: ถ้ามีค่าจริง (ไม่ใช่ null และไม่ใช่ "") -> ให้ส่งไปอัปเดตเสมอ
            if ($value !== null && $value !== '') {return true;}
            // กฎข้อที่ 2: ถ้าค่าเป็น 
            if ($value === null && in_array($key, $allowNull)) {
                // เช็คว่าคนแก้คือ "IT" หรือไม่ (ควรเช็คตัวเล็กตัวใหญ่ด้วย strtolower)
                if (isset($user['Role']) && strtoupper($user['Role']) === 'IT') {return true;} // IT สามารถส่ง null ไป "ล้างค่า" ใน Database ได้ 
                else {return false;} // คนอื่นที่ไม่ใช่ IT ให้ "ดีดออก" เพื่อคงค่าเดิมใน DB ไว้
            }
            // นอกเหนือจากนั้น (เช่น ฟิลด์อื่นๆ ที่เป็น null) -> ให้ดีดทิ้ง (ไม่ส่งไป Patch)
            return false;
        }, ARRAY_FILTER_USE_BOTH);
        
        // ใช้ PATCH ไปยังตารางของคุณ
        
        $status = 0; 
        supabase_query("/rest/v1/Users?User_ID=eq." . urlencode($target_user['User_ID']), "PATCH", $dataInput, $status);

        if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "แก้ไขข้อมูลผู้ใช้สำเร็จ";} 
        else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";}

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_POST['change_password'])) {
        $user_id = $_POST['user_id'] ?? $user['User_ID'];
        $oldPass = $_POST['oldPass'] ?? '';
        $newPass = $_POST['newPass'] ?? '';
        $confirmPass = $_POST['confirmPass'] ?? '';

        // หาข้อมูลของคนที่จะถูกอัปเดตจาก $datas
        $key = array_search($user_id, array_column($datas ?? [], 'User_ID'));
        // ถ้าหาเจอใน $datas ให้ใช้ค่านั้น ถ้าไม่เจอ (เช่น User แก้ตัวเอง) ให้ใช้ $user
        $target_user = ($key !== false) ? $datas[$key] : $user;

        if (empty(trim($oldPass)) || empty(trim($newPass)) || empty(trim($confirmPass))) {$error_empty = true;} 
        elseif (!password_verify($oldPass, $target_user['Password'])) {$error_wrong_pass = true;}
        elseif ($newPass !== $confirmPass) {$error_mismatch = true;}
        elseif (password_verify($newPass, $target_user['Password'])) {$error_match_pass = true;}
        else {
            $data = [
                "Password" => password_hash($_POST['newPass'], PASSWORD_DEFAULT),
            ];
            
            $status = 0; 
            supabase_query("/rest/v1/Users?User_ID=eq." . urlencode($target_user['User_ID']), "PATCH", $data, $status);

            if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "แก้ไขรหัสผ่านสำเร็จ";} 
            else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";}
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if (isset($_POST['upload_signature'])) {
        $user_id = $_POST['user_id'] ?? $user['User_ID'];
        // หาข้อมูลของคนที่จะถูกอัปเดตจาก $datas
        $key = array_search($user_id, array_column($datas ?? [], 'User_ID'));
        // ถ้าหาเจอใน $datas ให้ใช้ค่านั้น ถ้าไม่เจอ (เช่น User แก้ตัวเอง) ให้ใช้ $user
        $target_user = ($key !== false) ? $datas[$key] : $user;
        // 50 MB ในหน่วย Bytes
        $maxSize = 50 * 1024 * 1024;
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];

        if (!isset($_FILES['signature']) || $_FILES['signature']['error'] === UPLOAD_ERR_NO_FILE) {$error_noimage = true;}
        elseif ($_FILES['signature']['size'] > $maxSize) {$error_bigimage = true;}
        elseif (!in_array(strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION)), $allowedExtensions)) {
            $_SESSION['flash_message'] = "ผิดพลาด: อนุญาตเฉพาะไฟล์ " . implode(', ', $allowedExtensions) . " เท่านั้น";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        else{
            $signature = $_FILES['signature'];
            $link = uploadSignature($user_id, $signature);
            if($link['success']){
                $data = ["Signature" => $link['url']];
                
                $status = 0; 
                supabase_query("/rest/v1/Users?User_ID=eq." . urlencode($target_user['User_ID']), "PATCH", $data, $status);
                
                if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "แก้ไขลายเซ็นสำเร็จ";} 
                else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";}
            }
            else{
                $status = $link['status'];
                $_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการอัพโหลดรูปภาพ (Status: $status)";
            }

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if (isset($_POST['delete_signature'])) {
        $user_id = $_POST['user_id'] ?? $user['User_ID'];
        // หาข้อมูลของคนที่จะถูกอัปเดตจาก $datas
        $key = array_search($user_id, array_column($datas ?? [], 'User_ID'));
        // ถ้าหาเจอใน $datas ให้ใช้ค่านั้น ถ้าไม่เจอ (เช่น User แก้ตัวเอง) ให้ใช้ $user
        $target_user = ($key !== false) ? $datas[$key] : $user;
        // ดึง URL ปัจจุบันจาก $target_user มาเช็ค
        $current_url = $target_user['Signature'] ?? null;

        if (!empty($current_url)) {
            // 1. ลบไฟล์ใน Storage
            $deleteStatus = deleteSignatureByUserId($user_id);

            // 2. อัปเดต Database ให้ Signature เป็น NULL
            if($deleteStatus["success"] || $deleteStatus["status"] == 404){
                $data = ["Signature" => null];
                $status = 0;
                supabase_query("/rest/v1/Users?User_ID=eq." . urlencode($user_id), "PATCH", $data, $status);

                if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "ลบลายเซ็นสำเร็จ";} 
                else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการอัปเดตฐานข้อมูล (Status: $status)";}
            }
            else{
                $status = $deleteStatus['status'];
                $_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการลบรูปภาพ (Status: $status)";
            }
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_POST['create_new_user'])) {
        $user_id      = $_POST['user_id'] ?? '';
        $firstname    = $_POST['firstname'] ?? '';
        $lastname     = $_POST['lastname'] ?? '';
        $section      = $_POST['section'] ?? '';
        $role         = $_POST['role'] ?? '';
        $newPass      = $_POST['newPass'] ?? '';
        $confirmPass  = $_POST['confirmPass'] ?? ''; // รับมาเพื่อเช็ค mismatch
        $equipment_id = !empty($_POST['equipment_id']) ? $_POST['equipment_id'] : null;
        $comusername  = !empty($_POST['comusername']) ? $_POST['comusername'] : null;

        $maxSize = 50 * 1024 * 1024; // 50MB
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];
        $hasFile = isset($_FILES['signature']) && $_FILES['signature']['error'] !== UPLOAD_ERR_NO_FILE;


        // 2. ลำดับการตรวจสอบ (Validation)
        if (empty(trim($user_id))) {
            $error_useridempty = true; // รหัสผู้ใช้ห้ามว่าง
        } 
        elseif (in_array($user_id, $users)) {
            $_SESSION['flash_message'] = "ผิดพลาด: รหัสผู้ใช้นี้มีอยู่ในระบบแล้ว";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit; // รหัสผู้ใช้ซ้ำกับที่มีในระบบ
        }
        elseif (empty(trim($firstname)) || empty(trim($lastname)) || empty(trim($section)) || empty(trim($role))) {
            $error_nameempty = true; // ชื่อ-แผนก-บทบาท ห้ามว่าง
        } 
        elseif (empty(trim($newPass))) {
            $error_empty = true; // รหัสผ่านใหม่ห้ามว่าง
        }
        elseif ($newPass !== $confirmPass) {
            $error_mismatch = true; // รหัสผ่านไม่ตรงกัน
        }
        // เช็คกรณีมีไฟล์: ขนาด และ นามสกุล
        elseif ($hasFile && $_FILES['signature']['size'] > $maxSize) {
            $error_bigimage = true;
        } 
        elseif ($hasFile && !in_array(strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION)), $allowedExtensions)) {
            $_SESSION['flash_message'] = "ผิดพลาด: อนุญาตเฉพาะไฟล์ " . implode(', ', $allowedExtensions) . " เท่านั้น";
        }
        else {
            // 3. ผ่านทุกเงื่อนไข: เริ่มขั้นตอนอัปโหลดและบันทึก
            $signature_url = null; // เริ่มต้นเป็น null

            if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
                $link = uploadSignature($user_id, $_FILES['signature']);
                if ($link['success']) {
                    $signature_url = $link['url'];
                } 
                else {
                    $status = $link['status'];
                    $_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ (Status: $status)";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }

            $data = [
                "User_ID"      => $user_id,
                "Firstname"    => $firstname,
                "Lastname"     => $lastname,
                "Section"      => $section,
                "Role"         => $role,
                "Password"     => password_hash($newPass, PASSWORD_DEFAULT),
                "Equipment_ID" => $equipment_id,
                "ComUsername"  => $comusername,
                "Signature"    => $signature_url // จะเป็น URL หรือ null ก็ได้ตามที่เราตั้งไว้
            ];

            $status = 0;
            supabase_query("/rest/v1/Users", "POST", $data, $status);

            if ($status >= 200 && $status < 300) {
                $_SESSION['flash_message'] = "สร้างผู้ใช้งานสำเร็จ";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } 
            else {
                $_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }

    if (isset($_POST['change_userid'])) {
        // 1. ดึง ID เดิม (จาก hidden) และ ID ใหม่ (จาก input)
        $current_id = $_POST['user_id'] ?? ''; 
        $new_id = trim($_POST['edit_user_id'] ?? '');

        // 2. หาข้อมูลของคนที่จะถูกอัปเดตจาก $datas (สมมติ PK ของคุณชื่อ 'id')
        $key = array_search($current_id, array_column($datas ?? [], 'User_ID'));
        $target_user = ($key !== false) ? $datas[$key] : null;

        // 3. ตรวจสอบรหัสซ้ำ (เช็คว่ารหัสใหม่ไปตรงกับ User_ID ของคนอื่นในระบบหรือไม่)
        $is_duplicate = false;
        foreach ($datas as $d) {
            // เงื่อนไข: ถ้าเจอ User_ID ในระบบที่ตรงกับรหัสใหม่ (new_id) 
            // และคนนั้น "ไม่ใช่" คนที่เรากำลังแก้ไขอยู่ (current_id)
            if ($d['User_ID'] === $new_id && $d['User_ID'] !== $current_id) {
                $is_duplicate = true;
                break;
            }
        }

        if (empty($new_id)) {$error_edit_userid_empty = true;} 
        elseif ($is_duplicate) {$error_match_userid = true;} 
        elseif ($new_id === $current_id) {header("Location: " . $_SERVER['PHP_SELF']); exit;} 
        else {
            $data = ["User_ID" => $new_id];
            $status = 0;
            
            supabase_query("/rest/v1/Users?User_ID=eq." . urlencode($current_id), "PATCH", $data, $status);

            if ($status >= 200 && $status < 300) {
                $_SESSION['flash_message'] = "แก้ไขรหัสผู้ใช้สำเร็จ";
                if ($current_id === $_SESSION['user_id']) {$_SESSION['user_id'] = $new_id;}
            } 
            else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาด (Status: $status)";}
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

?>

<!DOCTYPE php>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account</title>
    <link rel="icon" type="image/x-icon" href="../- Image/BEW-Logo.ico">
    
    <link rel="stylesheet" href="Account Folder/Account.css">

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
                    <?php if ($user['Role'] == 'IT' || $user['Role'] == 'IT_Director'): ?>
                        <div class="headerText">ตัวเลือกการแก้ไข</div>
                        <div class="formItemContainer">
                            <form action="" id="" class="formContainer">
                                <div class="formItemRow" id="radioFormRow">
                                    <div class="formLabel" id="radioFormLabel">ตัวเลือกการแก้ไข</div>
                                    <div class="formInput" id="radioFormInput">
                                        <div class="radioInput">
                                            <input type="radio" id="editAccount" name="account" value="EditAccount" onclick="NewUser(false)" checked><label for="editAccount">แก้ไขข้อมูลผู้ใช้</label>
                                        </div>
                                        <div class="radioInput">
                                            <input type="radio" id="newAccount" name="account" value="NewAccount" onclick="NewUser(true)"><label for="newAccount">สร้างผู้ใช้งานใหม่</label>
                                        </div>   
                                    </div>    
                                </div>
                            </form>
                        </div>

                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                    <?php endif; ?>

                    <?php if ($user['Role'] == 'IT' || $user['Role'] == 'IT_Director'): ?>
                        <div class="headerText">รหัสผู้ใช้</div>
                        <div class="formItemContainer">
                            <div class="formItemRow">
                                <label class="formLabel" for="user_id_select">รหัสผู้ใช้</label>
                                <select id="user_id_select" name="user_id_select" class="formInput" onfocus="this.size=10;" onblur="this.size=1;" onchange="this.size=1; this.blur();">
                                    <?php natsort($users); $myId = $user['User_ID']; ?>
                                    <option value="<?= $myId ?>" selected><?= $myId ?></option>
                                    <?php foreach ($users as $eachdata): ?>
                                        <?php if ($eachdata == $myId) continue; ?>
                                        <option value="<?= $eachdata ?>"><?= $eachdata ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="formInput" type="text" name="user_id" id="user_id" placeholder="รหัสผู้ใช้" style="display:none;">
                            </div>
                        </div>
                        <p class="checkedText" id="checkUserID" <?= $error_useridempty ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> รหัสผู้ใช้ต้องไม่เป็นค่าว่าง</p>
                    
                        <hr style="margin:25px 0; border: 1px solid #e2e8f0"> 
                    <?php endif; ?>

                    <?php if ($user['Role'] == 'IT' || $user['Role'] == 'IT_Director'): ?>
                        <div id="editUserIdItem">
                            <form action="" method="POST" id="editUserIDForm" onsubmit="getUserData(this, '<?php echo $user['User_ID'] ?>')">
                                <input type="hidden" name="user_id">
                                <div class="headerText">แก้ไขรหัสผู้ใช้</div>
                                <div class="formItemContainer">
                                    <div class="formItemRow">
                                        <label class="formLabel" for="edit_user_id">แก้ไขรหัสผู้ใช้</label>
                                        <input class="formInput" type="text" name="edit_user_id" id="edit_user_id" placeholder="รหัสผู้ใช้" value="<?= htmlspecialchars($user['User_ID']) ?>">
                                    </div>
                                </div>
                                <p class="checkedText" id="checkBlankUserID" <?= $error_edit_userid_empty ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> รหัสผู้ใช้ห้ามเป็นค่าว่าง</p>
                                <p class="checkedText" id="checkMatchUserID" <?= $error_match_userid ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> มีรหัสผู้ใช้นี้ในระบบแล้ว กรุณาใช้รหัสอื่น</p>
                                <button type="submit" value="Submit" class="button" id="editUserIDButton" name="change_userid">
                                    <i class="fa-solid fa-user-tag"></i>
                                    <p class="buttonLabel">แก้ไขรหัสผู้ใช้</p>
                                </button>
                            </form>
                            <hr style="margin:25px 0; border: 1px solid #e2e8f0"> 
                        </div>
                    <?php endif; ?>

                    <div class="headerText">แก้ไขข้อมูลผู้ใช้</div>
                    <form action="" id="nameForm" method="POST" class="formContainer" data-users='<?= $userJson ?>' onsubmit="getUserData(this, '<?php echo $user['User_ID'] ?>'); return confirm('ยืนยันการเปลี่ยนแปลงข้อมูลหรือไม่?');">
                        <input type="hidden" name="user_id">
                        <div class="formItemContainer">
                            <div class="formItemRow">
                                <label class="formLabel" for="firstname">ชื่อ</label>
                                <input class="formInput" type="text" name="firstname" id="firstname" placeholder="ชื่อ" value="<?php echo htmlspecialchars($user['Firstname'] ?? ''); ?>">
                            </div>
                            <div class="formItemRow">
                                <label class="formLabel" for="lastname">นามสกุล</label>
                                <input class="formInput" type="text" name="lastname" id="lastname" placeholder="นามสกุล" value="<?php echo htmlspecialchars($user['Lastname'] ?? ''); ?>">
                            </div>
                            <?php if ($user['Role'] == 'IT' || $user['Role'] == 'IT_Director'): ?>
                                <div class="formItemRow">
                                    <label class="formLabel" for="section">แผนก</label>
                                    <select id="section" name="section" class="formInput">
                                        <option value="" hidden>ไม่มี</option>
                                        <?php foreach ($all_sections as $section): ?>
                                            <option value="<?= $section ?>" <?= ($section == $user['Section']) ? 'selected' : '' ?>>
                                                <?= $section ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="formItemRow">
                                    <label class="formLabel" for="role">บทบาท</label>
                                    <select id="role" name="role" class="formInput">
                                        <?php foreach ($all_roles as $role): ?>
                                            <option value="" hidden>ไม่มี</option>
                                            <option value="<?= $role ?>" <?= ($role == $user['Role']) ? 'selected' : '' ?>>
                                                <?= $role ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="formItemRow">
                                    <label class="formLabel" for="equipment_id">คอมพิวเตอร์</label>
                                    <select id="equipment_id" name="equipment_id" class="formInput" data-all-comps='<?= $comJson ?>'>
                                        <option value="">ไม่มี</option>
                                        <?php foreach ($computers as $computer): ?>
                                            <?php if ($computer['Section'] == $user['Section'] || $computer['Equipment_ID'] == $user['Equipment_ID']): ?>
                                                <option value="<?= $computer['Equipment_ID'] ?>" <?= ($computer['Equipment_ID'] == $user['Equipment_ID']) ? 'selected' : '' ?>>
                                                    <?= $computer['Equipment_ID'] ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="formItemRow">
                                <label class="formLabel" for="comusername">ชื่อโปรไฟล์</label>
                                <input class="formInput" type="text" name="comusername" id="comusername" placeholder="ชื่อโปรไฟล์" value="<?php echo htmlspecialchars($user['ComUsername'] ?? ''); ?>">
                            </div>
                        </div>
                        <p class="checkedText" id="checkName" <?= $error_nameempty ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> ชื่อ นามสกุล แผนก และบทบาท ต้องไม่เป็นค่าว่าง</p>
                        <button type="submit" value="Submit" class="button" id="nameButton" name="update_profile">
                            <i class="fa-solid fa-pen"></i>
                            <p class="buttonLabel">แก้ไขข้อมูลผู้ใช้</p>
                        </button>
                    </form>
                    
                    <hr style="margin:25px 0; border: 1px solid #e2e8f0">       
                    
                    <?php if ($user['Role'] == 'IT' || $user['Role'] == 'IT_Director'): ?>
                        <div class="headerText">แก้ไขรหัสผ่าน</div>
                        <form action="" method="POST" id="passForm" class="formContainer" onsubmit="getUserData(this, '<?php echo $user['User_ID'] ?>')">
                            <input type="hidden" name="user_id">
                            <div class="formItemContainer">
                                <div class="formItemRow" id="oldPassRow">
                                    <label class="formLabel" for="oldPass">รหัสผ่านปัจจุบัน</label>
                                    <div class="formInput">
                                        <input class="formPassInput" type="password" name="oldPass" id="oldPass" placeholder="รหัสผ่านปัจจุบัน">
                                        <button class="passwordbutton" type="button" 
                                            onmousedown="showPass('oldPass','oldPassEye')" 
                                            onmouseup="hidePass('oldPass','oldPassEye')" 
                                            onmouseleave="hidePass('oldPass','oldPassEye')"
                                            ontouchstart="showPass('oldPass','oldPassEye')" 
                                            ontouchend="hidePass('oldPass','oldPassEye')">
                                            <i id="oldPassEye" class="fa-solid fa-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="formItemRow">
                                    <label class="formLabel" for="newPass">รหัสผ่านใหม่</label>
                                    <div class="formInput">
                                        <input class="formPassInput" type="password" name="newPass" id="newPass" placeholder="รหัสผ่านใหม่">
                                        <button class="passwordbutton" type="button" 
                                            onmousedown="showPass('newPass','newPassEye')" 
                                            onmouseup="hidePass('newPass','newPassEye')" 
                                            onmouseleave="hidePass('newPass','newPassEye')"
                                            ontouchstart="showPass('newPass','newPassEye')" 
                                            ontouchend="hidePass('newPass','newPassEye')">
                                            <i id="newPassEye" class="fa-solid fa-eye-slash"></i></button>
                                    </div>
                                </div>
                                <div class="formItemRow">
                                    <label class="formLabel" for="confirmPass">ยืนยันรหัสผ่าน</label>
                                    <div class="formInput">
                                        <input class="formPassInput" type="password" name="confirmPass" id="confirmPass" placeholder="ยืนยันรหัสผ่าน">
                                        <button class="passwordbutton" type="button" 
                                            onmousedown="showPass('confirmPass','confirmPassEye')" 
                                            onmouseup="hidePass('confirmPass','confirmPassEye')" 
                                            onmouseleave="hidePass('confirmPass','confirmPassEye')"
                                            ontouchstart="showPass('confirmPass','confirmPassEye')" 
                                            ontouchend="hidePass('confirmPass','confirmPassEye')">
                                            <i id="confirmPassEye" class="fa-solid fa-eye-slash"></i></button>
                                    </div>
                                </div>
                            </div>
                            <p class="checkedText" id="check1" <?= $error_empty ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> กรุณากรอกฟอร์มที่กำหนดให้ครบทุกช่อง</p>
                            <p class="checkedText" id="check2" <?= $error_wrong_pass ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> รหัสผ่านไม่ถูกต้อง</p>
                            <p class="checkedText" id="check3" <?= $error_mismatch ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> กรุณายืนยันรหัสผ่านให้ตรงกัน</p>
                            <p class="checkedText" id="check4" <?= $error_match_pass ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> รหัสผ่านใหม่ต้องไม่ตรงกับรหัสผ่านปัจจุบัน</p>
                            <button type="submit" value="Submit" class="button" id="passButton" name="change_password">
                                <i class="fa-solid fa-key"></i>
                                <p class="buttonLabel">แก้ไขรหัสผ่าน</p>
                            </button>
                        </form>

                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">       
                    <?php endif; ?>

                    <div class="headerText">แก้ไขลายเซ็น</div>
                    <form action="" method="POST" class="formContainer" id="signatureForm" enctype="multipart/form-data" data-signatures='<?= $sigJson ?>' onsubmit="getUserData(this, '<?php echo $user['User_ID'] ?>')">
                        <input type="hidden" name="user_id">
                        <div class="formItemContainer">
                            <div class="formItemRow" style="height: auto;">
                                <label class="formLabel" <?= !empty($user['Signature']) ? '' : 'for="signature"' ?> id="signatureLabel">ลายเซ็น</label>
                                <div id="signatureContainer" class="formInput">
                                    <?php if (!empty($user['Signature'])): ?>
                                        <img src="<?= $user['Signature'] ?>" style="max-height: 80px; display: block; max-width: 225px;">
                                    <?php else: ?>
                                        <input type="file" id="signature" name="signature" style="width: 100%; height: auto;" accept="image/*" />
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <p class="checkedText" id="checkSignature" <?= $error_noimage ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> กรุณาอัพโหลดลายเซ็นของคุณ</p>
                        <p class="checkedText" id="checkSignatureSize" <?= $error_bigimage ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> ลายเซ็นของคุณมีขนาดใหญ่เกินไป (ขนาดต้องไม่เกิน 50MB)</p>
                        <button type="submit" value="Submit" class="button" id="deleteSignatureButton" name="delete_signature" style="<?= !empty($user['Signature']) ? 'display: flex;' : 'display: none;' ?>">
                            <i class="fa-solid fa-trash"></i>
                            <p class="buttonLabel">ลบลายเซ็น</p>
                        </button>
                        <button type="submit" value="Submit" class="button" id="signatureButton" name="upload_signature" style="<?= !empty($user['Signature']) ? 'display: none;' : 'display: flex;' ?>">
                            <i class="fa-solid fa-file-signature"></i>
                            <p class="buttonLabel">อัพโหลดลายเซ็น</p>
                        </button>
                    </form>
                    <form action="" method="POST" class="formContainer" id="newUserForm" enctype="multipart/form-data">
                        <div id="fileContainer" style="display: none;"></div>
                        <button type="submit" value="Submit" class="button" id="newUserButton" name="create_new_user" style="display: none;">
                            <i class="fa-solid fa-user-plus"></i>
                            <p class="buttonLabel">สร้างผู้ใช้งานใหม่</p>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    
    </main>
    <script src="Account Folder/Account.js"></script>

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