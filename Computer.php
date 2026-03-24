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
$computers = supabase_query("/rest/v1/Computer?select=*") ?? [];
usort($computers, function($a, $b) {return strnatcmp($a['Equipment_ID'], $b['Equipment_ID']);});
$computerMap = [];
foreach ($computers as $com) {
    // ใช้ Equipment_ID เป็น Key แทนเลข 0, 1
    $computerMap[$com['Equipment_ID']] = [
        'Equipment_ID' => $com['Equipment_ID'] ?? '',
        'ComName' => $com['ComName'] ?? '',
        'IP' => $com['IP'] ?? '',
        'Section' => $com['Section'] ?? ''
    ];
}
$compJson = htmlspecialchars(json_encode($computerMap), ENT_QUOTES, 'UTF-8');

// ค้นหาคอมพิวเตอร์ในรายการที่มี Equipment_ID ตรงกับของ User
$mycomputer = array_filter($computers, fn($c) => $c['Equipment_ID'] === $user['Equipment_ID']);
$mycomputer = reset($mycomputer) ?: null; // ดึงข้อมูลเครื่องนั้นออกมา (ตัวแรกที่เจอ)

$all_sections = ['IT', 'AC', 'HR', 'PUR', 'SALES', 'PC', 'PJ&System', 'ENG-PE', 'QA-QC', 'MT', 'Production'];

$error_computerempty = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") { 
    if (isset($_POST['update_computer'])) {
        $computer = $_POST['computer'] ?? $user['Equipment_ID'];
        $equipment_id = $_POST['equipment_id'];
        $comname = $_POST['comname'] ?? null;
        $ip = $_POST['ip'] ?? null;
        $section = $_POST['section'] ?? null;

        $key = array_search($computer, array_column($computers ?? [], 'Equipment_ID'));
        $target_com = ($key !== false) ? $computers[$key] : $mycomputer;

        if (empty(trim($equipment_id)) || empty(trim($comname)) || empty(trim($ip)) || empty(trim($section))) {$error_computerempty = true;} 
        else{
            $data = [
                "Equipment_ID" => $equipment_id,
                "ComName" => $comname,
                "IP" => $ip,
                "Section" => $section,
            ];
            
            $status = 0; 
            supabase_query("/rest/v1/Computer?Equipment_ID=eq." . urlencode($target_com['Equipment_ID']), "PATCH", $data, $status);

            if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "แก้ไขข้อมูลคอมพิวเตอร์สำเร็จ";} 
            else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";}
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if (isset($_POST['create_computer'])) {
        $equipment_id = $_POST['equipment_id'];
        $comname = $_POST['comname'] ?? null;
        $ip = $_POST['ip'] ?? null;
        $section = $_POST['section'] ?? null;

        $key = array_search($computer, array_column($computers ?? [], 'Equipment_ID'));
        $target_com = ($key !== false) ? $computers[$key] : $mycomputer;

        if (empty(trim($equipment_id)) || empty(trim($comname)) || empty(trim($ip)) || empty(trim($section))) {$error_computerempty = true;} 
        else{
            $data = [
                "Equipment_ID" => $equipment_id,
                "ComName" => $comname,
                "IP" => $ip,
                "Section" => $section,
            ];
            
            $status = 0; 
            supabase_query("/rest/v1/Computer", "POST", $data, $status);

            if ($status >= 200 && $status < 300) {$_SESSION['flash_message'] = "เพิ่มข้อมูลคอมพิวเตอร์สำเร็จ";} 
            else {$_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (Status: $status)";}
            
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
    <title>Home</title>
    <link rel="icon" type="image/x-icon" href="../- Image/BEW-Logo.ico">
    
    <link rel="stylesheet" href="Computer Folder/Computer.css">

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
                    <div class="headerText">ตัวเลือกการแก้ไข</div>
                    <div class="formItemContainer">
                        <form action="" id="" class="formContainer">
                            <div class="formItemRow" id="radioFormRow">
                                <div class="formLabel" id="radioFormLabel">ตัวเลือกการแก้ไข</div>
                                <div class="formInput" id="radioFormInput">
                                    <div class="radioInput">
                                        <input type="radio" id="editAccount" name="account" value="EditAccount" onclick="NewComputer(false)" checked><label for="editAccount">แก้ไขข้อมูลคอมพิวเตอร์</label>
                                    </div>
                                    <div class="radioInput">
                                        <input type="radio" id="newAccount" name="account" value="NewAccount" onclick="NewComputer(true)"><label for="newAccount">สร้างข้อมูลคอมพิวเตอร์ใหม่</label>
                                    </div>   
                                </div>    
                            </div>
                        </form>
                    </div>

                    <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                    <div id="computerSelect">
                        <div class="headerText">คอมพิวเตอร์</div>
                        <div class="formItemContainer">
                            <div class="formItemRow">
                                <label class="formLabel" for="computer_select">คอมพิวเตอร์</label>
                                <select id="computer_select" name="computer_select" class="formInput" onfocus="this.size=10;" onblur="this.size=1;" onchange="this.size=1; this.blur();">
                                    <?php $myComputer = $user['Equipment_ID']; ?>
                                    <option value="<?= $myComputer ?>" selected><?= $myComputer ?></option>
                                    <?php foreach ($computers as $eachdata): ?>
                                        <?php if ($eachdata['Equipment_ID'] == $myComputer) continue; ?>
                                        <option value="<?= $eachdata['Equipment_ID'] ?>"><?= $eachdata['Equipment_ID'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    
                        <hr style="margin:25px 0; border: 1px solid #e2e8f0">
                    </div>

                    <div class="headerText">รายละเอียดคอมพิวเตอร์</div>
                    <form action="" id="computerForm" method="POST" class="formContainer" data-computers='<?= $compJson ?>' onsubmit="getComputerData(this, '<?php echo $user['Equipment_ID'] ?>')">
                        <input type="hidden" name="computer">
                        <div class="formItemContainer">
                            <div class="formItemRow">
                                <label class="formLabel" for="equipment_id">คอมพิวเตอร์</label>
                                <input class="formInput" type="text" name="equipment_id" id="equipment_id" placeholder="คอมพิวเตอร์" value="<?php echo htmlspecialchars($mycomputer['Equipment_ID'] ?? ''); ?>">
                            </div>
                            <div class="formItemRow">
                                <label class="formLabel" for="comname">ชื่อคอมพิวเตอร์</label>
                                <input class="formInput" type="text" name="comname" id="comname" placeholder="ชื่อคอมพิวเตอร์" value="<?php echo htmlspecialchars($mycomputer['ComName'] ?? ''); ?>">
                            </div>
                            <div class="formItemRow">
                                <label class="formLabel" for="ip">IP Address</label>
                                <input class="formInput" type="text" name="ip" id="ip" placeholder="IP Address" value="<?php echo htmlspecialchars($mycomputer['IP'] ?? ''); ?>">
                            </div>
                            <div class="formItemRow">
                                    <label class="formLabel" for="section">แผนก</label>
                                    <select id="section" name="section" class="formInput">
                                        <option value="" hidden>ไม่มี</option>
                                        <?php foreach ($all_sections as $section): ?>
                                            <option value="<?= $section ?>" <?= ($section == $mycomputer['Section']) ? 'selected' : '' ?>>
                                                <?= $section ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                        </div>
                        <p class="checkedText" id="checkCom" <?= $error_computerempty ? '' : 'hidden' ?>><i class="fa-solid fa-circle-info"></i> คอมพิวเตอร์ ชื่อคอมพิวเตอร์ IP Address และแผนก ต้องไม่เป็นค่าว่าง</p>
                        <button type="submit" value="Submit" class="button" id="computerButton" name="update_computer">
                            <i class="fa-solid fa-computer"></i>
                            <p class="buttonLabel">แก้ไขข้อมูลคอมพิวเตอร์</p>
                        </button>
                        <button type="submit" value="Submit" class="button" id="computerCreateButton" name="create_computer" style="display: none;">
                            <i class="fa-solid fa-computer"></i>
                            <p class="buttonLabel">เพิ่มข้อมูลคอมพิวเตอร์</p>
                        </button>
                    </form>
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

    <script src="Computer Folder/Computer.js"></script>
</body>
</html>