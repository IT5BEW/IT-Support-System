<?php
// Supabase.php
function init_env($path) {
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

function supabase_query($path, $method = "GET", $postData = null, &$httpCode = null) {
    init_env(__DIR__ . '/.env');

    $supabase_url =  $_ENV['SUPABASE_URL'] ?? '';
    $supabase_key = $_ENV['SUPABASE_KEY'] ?? '';

    $ch = curl_init($supabase_url . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: " . $supabase_key,
        "Authorization: Bearer " . $supabase_key,
        "Content-Type: application/json"
    ]);

    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }
    elseif ($method === "PATCH") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    // PHP 8.5 handles curl_close automatically
    return json_decode($response, true) ?? [];
    
}