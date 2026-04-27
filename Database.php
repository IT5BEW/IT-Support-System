<?php
// Database.php
// แทนที่ Supabase.php ทั้งหมด
// รูปภาพทั้งหมดเก็บเป็น VARBINARY(MAX) ใน SQL Server
// เชื่อมต่อผ่าน ODBC (ไม่ต้องติดตั้ง extension เพิ่ม)

// ===================================================
// Private Helpers
// ===================================================

function init_env(string $path): void {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    init_env(__DIR__ . '/.env');

    $host   = $_ENV['DB_HOST']         ?? 'localhost';
    $port   = $_ENV['DB_PORT']         ?? '1433';
    $dbname = $_ENV['DB_NAME']         ?? '';
    $user   = $_ENV['DB_USER']         ?? '';
    $pass   = $_ENV['DB_PASSWORD']     ?? '';
    $driver = $_ENV['DB_ODBC_DRIVER']  ?? 'ODBC Driver 17 for SQL Server';

    $pdo = new PDO(
        "odbc:Driver={$driver};Server=$host,$port;Database=$dbname;TrustServerCertificate=yes",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
}

function get_mime_from_data(string $data): string {
    return (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
}

function mime_to_ext(string $mime): string {
    return match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'bin',
    };
}

// แปลง BLOB จาก SQL Server -> data URI สำหรับใช้ใน <img src="...">
// เช่น blob_to_data_uri($row['Signature'], $row['SignatureMime'])
function blob_to_data_uri(?string $blob, ?string $mime): ?string {
    if (empty($blob) || empty($mime)) return null;
    // SQL Server ODBC อาจส่งกลับมาเป็น hex string (0x...) หรือ hex ล้วนๆ
    // ต้องแปลงเป็น binary ก่อน base64
    if (str_starts_with($blob, '0x') || str_starts_with($blob, '0X')) {
        $blob = hex2bin(substr($blob, 2));
    } elseif (ctype_xdigit($blob)) {
        $blob = hex2bin($blob);
    }
    if (empty($blob)) return null;
    return "data:$mime;base64," . base64_encode($blob);
}

// ===================================================
// Public Functions (แทน supabase_query)
// ===================================================

// SELECT - คืน array of rows
// ตัวอย่าง: db_query('SELECT * FROM [Users] WHERE [User_ID] = :id', [':id' => 'U001'])
function db_query(string $sql, array $params = []): array {
    // ODBC: แปลง :param -> CAST(:param AS NVARCHAR(500)) เพื่อแก้ type mismatch
    $sql = preg_replace('/:(\w+)/', 'CAST(:$1 AS NVARCHAR(500))', $sql);
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// INSERT - คืน true/false
function db_insert(string $table, array $data): bool {
    $cols    = array_keys($data);
    $colList = implode(', ', array_map(fn($c) => "[$c]", $cols));
    $valList = implode(', ', array_fill(0, count($cols), '?'));

    $stmt = get_db()->prepare("INSERT INTO [$table] ($colList) VALUES ($valList)");

    $pos = 1;
    foreach ($data as $col => $val) {
        if (is_null($val)) {
            $stmt->bindValue($pos, null, PDO::PARAM_NULL);
        } elseif (is_blob_col($col)) {
            $temp = fopen('php://memory', 'r+');
            fwrite($temp, $val);
            rewind($temp);
            $stmt->bindParam($pos, $temp, PDO::PARAM_LOB);
        } else {
            $stmt->bindValue($pos, $val, PDO::PARAM_STR);
        }
        $pos++;
    }

    return $stmt->execute();
}

// UPDATE - คืน true/false
// $where คือ array ของเงื่อนไข เช่น ['User_ID' => 'U001']
// column ที่เป็น VARBINARY(MAX) ต้อง bind ด้วย PARAM_LOB
const BLOB_COLUMNS = ['Signature', 'DetailedImage'];

function is_blob_col(string $col): bool {
    return in_array($col, BLOB_COLUMNS);
}

function db_update(string $table, array $data, array $where): bool {
    // ใช้ ? ทั้งหมด เพื่อหลีกเลี่ยง mixed named/positional parameters
    $setParts   = array_map(fn($c) => "[$c] = ?", array_keys($data));
    $whereParts = array_map(fn($c) => "[$c] = ?", array_keys($where));

    $sql = "UPDATE [$table] SET " . implode(', ', $setParts)
         . " WHERE "              . implode(' AND ', $whereParts);

    $stmt = get_db()->prepare($sql);

    // bind SET values
    $pos = 1;
    foreach ($data as $col => $val) {
        if (is_null($val)) {
            $stmt->bindValue($pos, null, PDO::PARAM_NULL);
        } elseif (is_blob_col($col)) {
            $temp = fopen('php://memory', 'r+');
            fwrite($temp, $val);
            rewind($temp);
            $stmt->bindParam($pos, $temp, PDO::PARAM_LOB);
        } else {
            $stmt->bindValue($pos, $val, PDO::PARAM_STR);
        }
        $pos++;
    }

    // bind WHERE values (ไม่มี BLOB ใน WHERE)
    foreach ($where as $val) {
        $stmt->bindValue($pos, $val, PDO::PARAM_STR);
        $pos++;
    }

    return $stmt->execute();
}

// ===================================================
// File Functions (เก็บ BLOB ใน SQL Server)
// ===================================================

// อัปโหลดลายเซ็นจากไฟล์ที่ user upload มา
// คืน ['success' => true, 'mime' => '...']
// หรือ ['success' => false, 'error_msg' => '...']
function uploadSignature(string $user_id, array $file): array {
    $data = file_get_contents($file['tmp_name']);
    if ($data === false) {
        return ['success' => false, 'error_msg' => 'ไม่สามารถอ่านไฟล์ได้'];
    }

    $mime = get_mime_from_data($data);

    $ok = db_update('Users',
        ['Signature' => $data, 'SignatureMime' => $mime],
        ['User_ID'   => $user_id]
    );

    return $ok
        ? ['success' => true, 'mime' => $mime]
        : ['success' => false, 'error_msg' => 'บันทึกลงฐานข้อมูลไม่สำเร็จ'];
}

// เปลี่ยน User ID — signature อยู่ใน DB แล้ว ไม่ต้องย้ายไฟล์
// คืน ['success' => true] เสมอ (ให้ไว้เพื่อ backward compatible กับโค้ดที่เรียกใช้)
function updateSignatureWhenChangeUserID(string $old_user_id, string $new_user_id): array {
    // Signature ผูกกับ Users row ไม่ใช่ชื่อไฟล์
    // เมื่อ User_ID เปลี่ยน row ก็ย้ายตามอัตโนมัติ ไม่ต้องทำอะไรเพิ่ม
    return ['success' => true];
}

// ลบลายเซ็น
function deleteSignatureByUserId(string $user_id): array {
    $ok = db_update('Users',
        ['Signature' => null, 'SignatureMime' => null],
        ['User_ID'   => $user_id]
    );

    return $ok
        ? ['success' => true]
        : ['success' => false, 'error_msg' => 'ลบลายเซ็นออกจากฐานข้อมูลไม่สำเร็จ'];
}

// อัปโหลดรูปภาพประกอบฟอร์มจากไฟล์ที่ user upload มา
// column ที่จะ update ต้องส่งมาเป็น $image_col และ $mime_col
// เช่น uploadImageToForm($form_id, $file, 'DetailedImage', 'DetailedImageMime')
function uploadImageToForm(string $form_id, array $file, string $image_col, string $mime_col = ''): array {
    if (empty($mime_col)) $mime_col = $image_col . 'Mime';

    $data = file_get_contents($file['tmp_name']);
    if ($data === false) {
        return ['success' => false, 'error_msg' => 'ไม่สามารถอ่านไฟล์ได้'];
    }

    $mime = get_mime_from_data($data);

    $ok = db_update('RequestForm',
        [$image_col => $data, $mime_col => $mime],
        ['Form_ID'  => $form_id]
    );

    return $ok
        ? ['success' => true, 'mime' => $mime]
        : ['success' => false, 'error_msg' => 'บันทึกลงฐานข้อมูลไม่สำเร็จ'];
}

// copy ลายเซ็นจาก Users ไปเก็บใน RequestForm ณ เวลาที่ส่งฟอร์ม
// (snapshot ลายเซ็น เผื่อ user เปลี่ยนลายเซ็นทีหลัง ฟอร์มเก่ายังถูกต้อง)
function getBinaryImageAndUploadToForm(string $form_id, string $user_id, string $col_prefix = 'Signature'): array {
    $image_col = $col_prefix;
    $mime_col  = $col_prefix . 'Mime';

    // 1. ดึงข้อมูลแบบไม่ผ่าน db_query (เพื่อเลี่ยง CAST AS NVARCHAR)
    $db = get_db();
    $stmt = $db->prepare("SELECT [Signature], [SignatureMime] FROM [Users] WHERE [User_ID] = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['Signature'])) {
        return ['success' => false, 'error_msg' => 'ไม่พบลายเซ็นของผู้ใช้'];
    }

    $blob = $row['Signature'];
    $mime = $row['SignatureMime'];

    if (is_string($blob) && preg_match('/^[0-9a-fA-F]+$/', str_replace('0x', '', $blob))) {
        $blob = hex2bin(str_replace('0x', '', $blob));
    }

    $ok = db_update('RequestForm',
        [$image_col => $blob, $mime_col => $mime],
        ['Form_ID'  => $form_id]
    );

    return $ok
        ? ['success' => true, 'mime' => $mime]
        : ['success' => false, 'error_msg' => 'บันทึกลงฐานข้อมูลไม่สำเร็จ'];
}

// ลบรูปภาพประกอบฟอร์ม
function deleteFilesByPrefix(string $form_id, string $image_col, string $mime_col = ''): void {
    if (empty($mime_col)) $mime_col = $image_col . 'Mime';

    db_update('RequestForm',
        [$image_col => null, $mime_col => null],
        ['Form_ID'  => $form_id]
    );
}