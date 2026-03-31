<?php

function init_envi($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // ข้ามคอมเมนต์
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

function uploadSignature($user_id, $file) {
    init_envi(__DIR__ . '/.env');
    $supabase_url =  $_ENV['SUPABASE_URL'] ?? '';
    $supabase_key = $_ENV['SUPABASE_KEY'] ?? '';
    $bucketName  = 'Signature';

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName  = $user_id . "_signature." . $extension;
    $fileData  = file_get_contents($file['tmp_name']);

    $ch = curl_init("$supabase_url/storage/v1/object/$bucketName/$fileName");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $supabase_key",
        "apikey: $supabase_key",
        "Content-Type: " . mime_content_type($file['tmp_name']),
        "x-upsert: true"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            "success" => true, 
            "url" => "$supabase_url/storage/v1/object/public/$bucketName/$fileName"
        ];
    } else {
        return [
            "success" => false, 
            "status" => $httpCode
        ];
    }
}
function updateSignatureWhenChangeUserID($old_user_id, $new_user_id) { 
    init_envi(__DIR__ . '/.env');
    $supabase_url = $_ENV['SUPABASE_URL'] ?? '';
    $supabase_key = $_ENV['SUPABASE_KEY'] ?? '';
    $bucketName  = 'Signature';

    $extensions = ['png', 'jpg', 'jpeg', 'webp'];
    $success = false;
    $copy_code = 0;
    $delete_code = 0;
    $newFileName = '';

    foreach ($extensions as $ext) {
        $oldFileName = $old_user_id . "_signature." . $ext;
        $newFileName = $new_user_id . "_signature." . $ext;

        // 1. Copy
        $copy_url = "{$supabase_url}/storage/v1/object/copy";
        $copy_data = [
            "bucketId" => $bucketName,
            "sourceKey" => $oldFileName,
            "destinationKey" => $newFileName
        ];

        $ch = curl_init($copy_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($copy_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $supabase_key",
            "apikey: $supabase_key",
            "Content-Type: application/json"
        ]);
        $copy_response = curl_exec($ch);
        $copy_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($copy_code >= 200 && $copy_code < 300) {
            // 2. Delete ไฟล์เก่า
            $delete_url = "{$supabase_url}/storage/v1/object/{$bucketName}/{$oldFileName}";
            $ch = curl_init($delete_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $supabase_key",
                "apikey: $supabase_key"
            ]);
            $delete_response = curl_exec($ch);
            $delete_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $success = true;
            break; // เจอแล้วก็หยุด ไม่ต้องวนต่อ
        }
    }

    if ($success) {
        return [
            "success" => true,
            "new" => $newFileName,
            "url" => "{$supabase_url}/storage/v1/object/public/{$bucketName}/{$newFileName}",
            "copy_status" => $copy_code,
            "delete_status" => $delete_code
        ];
    }

    return [
        "success" => false,
        "status" => $copy_code,
        "error_msg" => "ไม่พบไฟล์ต้นทางในระบบ"
    ];
}

function deleteSignatureByUserId($user_id) {
    init_envi(__DIR__ . '/.env');
    $supabase_url = $_ENV['SUPABASE_URL'] ?? '';
    $supabase_key = $_ENV['SUPABASE_KEY'] ?? '';
    $bucketName  = 'Signature';

    $extensions = ['png', 'jpg', 'jpeg', 'webp'];

    $success = false;
    $lastHttpCode = 0;
    
    foreach ($extensions as $ext) {
        $fileName = $user_id . "_signature." . $ext;
        $ch = curl_init("$supabase_url/storage/v1/object/$bucketName/$fileName");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $supabase_key",
            "apikey: $supabase_key"
        ]);
        $response =curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $lastHttpCode = $httpCode;

        if ($httpCode >= 200 && $httpCode < 300) {
            $success = true;
            break; // *** เจอไฟล์และลบสำเร็จแล้ว ให้หยุดวนลูปทันที ***
        }
    }
    return [
        "success" => $success, 
        "status"  => $lastHttpCode
    ];
}