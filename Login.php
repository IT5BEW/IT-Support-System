<!-- Login System with Supabase-->
<?php
include 'Supabase.php';
session_start();

if (isset($_SESSION['logged_in'])) {
    header("Location: Home.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = $_POST['userID'] ?? ''; 
    $pass_input = $_POST['password'] ?? ''; 
    $data = supabase_query("/rest/v1/Users?User_ID=eq." . urlencode($user_input) . "&select=*");

    if($user_input != "" || $pass_input != ""){
        if (is_array($data) && !empty($data)) {
            $user = $data[0]; // Get the first record

            // Verify using your column name: "Password"
            if (password_verify($pass_input, $user['Password'])) {
            //if ($pass_input == $user['Password']) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['User_ID'];
                header("Location: Home.php");
                exit;
            } 
            else {$error = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";}
        } 
        else { $error = "ไม่พบชื่อผู้ใช้งาน กรุณาติดต่อเจ้าหน้าที่ที่เกี่ยวข้อง";}
    }
    else {$error = "กรุณากรอกข้อมูลให้ครบทุกช่อง";}
}

?>

<!DOCTYPE php>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="icon" type="image/x-icon" href="../- Image/BEW-Logo.ico">

    <link rel="stylesheet" href="Login Folder/Login.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <main>
        <section id="loginSection">
            <div class="mainContainer">
                <div class="itemContainer">
                    <img src="../- Image/BEW Logo.png" alt="logo" id="logo">
                    <h1 id="headerText">ระบบแจ้งปัญหา IT</h1>
                    <form method="POST" style="margin: auto;">
                        <div class="formContainer">
                            <div>
                                <i class="fa-solid fa-circle-user symbol"></i>
                                <input type="text" name="userID" id="userID" placeholder="รหัสพนักงาน">
                            </div>
                            <div>
                                <i class="fa-solid fa-lock symbol"></i>
                                <input type="password" name="password" id="password" placeholder="รหัสผ่าน">
                            </div>
                        </div>

                        <button type="submit" value="Submit" id="submitButton">เข้าสู่ระบบ</button>
                        <?php if(isset($error)) echo "<p style='color:red; margin:10px;'>* $error</p>"; ?>
                    </form>

                </div>
            </div>
        </section>
    </main>
</body>
</html>