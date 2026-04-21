<?php
// Supabase.php

// --- Private Helpers ---

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

function get_supabase_config(): array {
    init_env(__DIR__ . '/.env');
    return [
        'url' => $_ENV['SUPABASE_URL'] ?? '',
        'key' => $_ENV['SUPABASE_KEY'] ?? '',
    ];
}

function make_auth_headers(string $key, array $extra = []): array {
    return array_merge([
        "apikey: $key",
        "Authorization: Bearer $key",
    ], $extra);
}

function curl_exec_with_code(string $url, array $options = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    foreach ($options as $opt => $val) {
        curl_setopt($ch, $opt, $val);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$response, $httpCode];
}

// --- Public Functions ---

function supabase_query(string $path, string $method = "GET", ?array $postData = null, ?int &$httpCode = null): array {
    ['url' => $url, 'key' => $key] = get_supabase_config();

    $options = [
        CURLOPT_HTTPHEADER => make_auth_headers($key, ["Content-Type: application/json"]),
    ];

    if ($method === "POST") {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($postData);
    } elseif ($method === "PATCH") {
        $options[CURLOPT_CUSTOMREQUEST] = "PATCH";
        $options[CURLOPT_POSTFIELDS] = json_encode($postData);
    }

    [$response, $httpCode] = curl_exec_with_code($url . $path, $options);
    return json_decode($response, true) ?? [];
}

function uploadSignature(string $user_id, array $file): array {
    ['url' => $url, 'key' => $key] = get_supabase_config();
    $bucket = 'Signature';

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = "{$user_id}_signature.{$ext}";

    [, $httpCode] = curl_exec_with_code("$url/storage/v1/object/$bucket/$fileName", [
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS    => file_get_contents($file['tmp_name']),
        CURLOPT_HTTPHEADER    => make_auth_headers($key, [
            "Content-Type: " . mime_content_type($file['tmp_name']),
            "x-upsert: true",
        ]),
    ]);

    return $httpCode >= 200 && $httpCode < 300
        ? ["success" => true,  "url" => "$url/storage/v1/object/public/$bucket/$fileName"]
        : ["success" => false, "status" => $httpCode];
}

function updateSignatureWhenChangeUserID(string $old_user_id, string $new_user_id): array {
    ['url' => $url, 'key' => $key] = get_supabase_config();
    $bucket = 'Signature';

    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        $oldFile = "{$old_user_id}_signature.{$ext}";
        $newFile = "{$new_user_id}_signature.{$ext}";

        [, $copyCode] = curl_exec_with_code("$url/storage/v1/object/copy", [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode(["bucketId" => $bucket, "sourceKey" => $oldFile, "destinationKey" => $newFile]),
            CURLOPT_HTTPHEADER => make_auth_headers($key, ["Content-Type: application/json"]),
        ]);

        if ($copyCode >= 200 && $copyCode < 300) {
            [, $deleteCode] = curl_exec_with_code("$url/storage/v1/object/$bucket/$oldFile", [
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_HTTPHEADER    => make_auth_headers($key),
            ]);

            return [
                "success"       => true,
                "new"           => $newFile,
                "url"           => "$url/storage/v1/object/public/$bucket/$newFile",
                "copy_status"   => $copyCode,
                "delete_status" => $deleteCode,
            ];
        }
    }

    return ["success" => false, "error_msg" => "ไม่พบไฟล์ต้นทางในระบบ"];
}

function deleteSignatureByUserId(string $user_id): array {
    ['url' => $url, 'key' => $key] = get_supabase_config();
    $bucket = 'Signature';

    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        [, $httpCode] = curl_exec_with_code("$url/storage/v1/object/$bucket/{$user_id}_signature.{$ext}", [
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_HTTPHEADER    => make_auth_headers($key),
        ]);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ["success" => true, "status" => $httpCode];
        }
    }

    return ["success" => false, "status" => $httpCode ?? 0];
}

function uploadImageToForm(string $form_id, array $file, string $custom_filename): array {
    ['url' => $url, 'key' => $key] = get_supabase_config();
    $bucket   = 'Form';
    

     // 1. ค้นหาไฟล์เดิมที่อาจมีนามสกุลต่างกัน (เช่น .jpg, .png) แล้วลบทิ้งก่อน
    $list_url = "$url/storage/v1/object/list/$bucket";
    [$listResponse, ] = curl_exec_with_code($list_url, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["prefix" => "$form_id/"]),
        CURLOPT_HTTPHEADER => make_auth_headers($key, ["Content-Type: application/json"])
    ]);

    $files = json_decode($listResponse, true) ?: [];
    foreach ($files as $f) {
        if (str_starts_with($f['name'], $custom_filename)) {
            curl_exec_with_code("$url/storage/v1/object/$bucket/$form_id/{$f['name']}", [
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_HTTPHEADER    => make_auth_headers($key)
            ]);
        }
    }

     // 2. เตรียมไฟล์ใหม่ที่จะอัปโหลด
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fullPath = "$form_id/$custom_filename.$ext";

    [, $httpCode] = curl_exec_with_code("$url/storage/v1/object/$bucket/$fullPath", [
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS    => file_get_contents($file['tmp_name']),
        CURLOPT_HTTPHEADER    => make_auth_headers($key, [
            "Content-Type: " . mime_content_type($file['tmp_name']),
            "x-upsert: true",
            "cache-control: no-cache"
        ]),
    ]);

    return $httpCode >= 200 && $httpCode < 300
        ? ["success" => true,  "url" => "$url/storage/v1/object/public/$bucket/$fullPath"]
        : ["success" => false, "status" => $httpCode];
}

function getImageFromUrlAndUploadToForm(string $form_id, string $image_url, string $custom_filename): array {
    ['url' => $url, 'key' => $key] = get_supabase_config();
    $bucket = 'Form';

    $list_url = "$url/storage/v1/object/list/$bucket";
    [$listResponse, ] = curl_exec_with_code($list_url, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["prefix" => "$form_id/"]),
        CURLOPT_HTTPHEADER => make_auth_headers($key, ["Content-Type: application/json"])
    ]);

    $files = json_decode($listResponse, true) ?: [];
    foreach ($files as $f) {
        if (str_starts_with($f['name'], $custom_filename)) {
            curl_exec_with_code("$url/storage/v1/object/$bucket/$form_id/{$f['name']}", [
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_HTTPHEADER    => make_auth_headers($key)
            ]);
        }
    }

    // ดึงไฟล์จาก URL
    [$fileData, $httpCode] = curl_exec_with_code($image_url);
    if ($httpCode < 200 || $httpCode >= 300 || empty($fileData)) {
        return ["success" => false, "error_msg" => "ดึงไฟล์จาก URL ไม่สำเร็จ", "status" => $httpCode];
    }

    // เดา extension จาก Content-Type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($fileData);
    $ext = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'bin'
    };

    $fullPath = "$form_id/$custom_filename.$ext";

    [, $uploadCode] = curl_exec_with_code("$url/storage/v1/object/$bucket/$fullPath", [
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS    => $fileData,
        CURLOPT_HTTPHEADER    => make_auth_headers($key, [
            "Content-Type: $mimeType",
            "x-upsert: true",
            "cache-control: no-cache"
        ]),
    ]);

    return $uploadCode >= 200 && $uploadCode < 300
        ? ["success" => true,  "url" => "$url/storage/v1/object/public/$bucket/$fullPath"]
        : ["success" => false, "status" => $uploadCode, "error_msg" => "อัปโหลดไฟล์ไปยัง Supabase ไม่สำเร็จ"];
}

function deleteFilesByPrefix(string $form_id, string $custom_filename): void {
    ['url' => $url, 'key' => $key] = get_supabase_config();
    $bucket = 'Form';

    // 1. ดึงรายชื่อไฟล์ในโฟลเดอร์ form_id
    $list_url = "$url/storage/v1/object/list/$bucket";
    [$listRes, ] = curl_exec_with_code($list_url, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["prefix" => "$form_id/"]),
        CURLOPT_HTTPHEADER => make_auth_headers($key, ["Content-Type: application/json"])
    ]);
    
    $files = json_decode($listRes, true) ?: [];
    foreach ($files as $f) {
        if (str_starts_with($f['name'], $custom_filename)) {
            curl_exec_with_code("$url/storage/v1/object/$bucket/$form_id/{$f['name']}", [
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_HTTPHEADER    => make_auth_headers($key)
            ]);
        }
    }
}