<?php
/**
 * =====================================================================
 *  SERVER (PC 2) - RPC ARAH KIBLAT
 *  File   : qibla_server.php
 *  Fungsi : Menerima request XML dari client, memanggil fungsi utama
 *           getQiblaDirection(), lalu membalas dengan XML.
 *  API    : https://api.aladhan.com/v1/qibla/:latitude/:longitude
 *
 *  Struktur file ini:
 *    1. FUNGSI UTAMA  -> logika bisnis (memanggil API Aladhan)
 *    2. SERVER STUB   -> unmarshalling request, panggil fungsi, marshalling response
 *    3. ENTRY POINT   -> menerima HTTP POST dari client
 * =====================================================================
 */
 
header('Content-Type: application/xml; charset=utf-8');
 
/* ---------------------------------------------------------------------
 * 1. FUNGSI UTAMA (di server)
 *    Meminta arah kiblat (derajat dari utara sejati) ke API Aladhan.
 * ------------------------------------------------------------------- */
function getQiblaDirection(float $lat, float $lon): array
{
    // Validasi rentang koordinat
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        throw new InvalidArgumentException('Koordinat di luar rentang (lat -90..90, lon -180..180).');
    }
 
    // sprintf %F -> titik desimal selalu "." (aman dari pengaruh locale)
    $url = sprintf('https://api.aladhan.com/v1/qibla/%.6F/%.6F', $lat, $lon);
 
    // Panggil API eksternal dengan cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
 
    if ($body === false) {
        throw new RuntimeException('Gagal menghubungi API Aladhan: ' . $err);
    }
    if ($http !== 200) {
        throw new RuntimeException("API Aladhan mengembalikan HTTP $http");
    }
 
    // Respons API: {"code":200,"status":"OK","data":{"latitude":..,"longitude":..,"direction":..}}
    $json = json_decode($body, true);
    if (!isset($json['data']['direction'])) {
        throw new RuntimeException('Format respons API tidak dikenali.');
    }
 
    $direction = (float) $json['data']['direction'];
 
    return [
        'latitude'  => $lat,
        'longitude' => $lon,
        'direction' => round($direction, 2),
        'compass'   => degreesToCompass($direction),
        'source'    => 'api.aladhan.com',
    ];
}
 
/* ---------------------------------------------------------------------
 * FUNGSI UTAMA 2: cari koordinat dari nama kota (geocoding),
 * lalu hitung arah kiblat dengan fungsi di atas.
 * Geocoding memakai OpenStreetMap Nominatim (gratis, tanpa API key).
 * ------------------------------------------------------------------- */
function getQiblaByCity(string $city): array
{
    $city = trim($city);
    if ($city === '' || mb_strlen($city) > 100) {
        throw new InvalidArgumentException('Nama kota kosong atau terlalu panjang.');
    }
 
    $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' . urlencode($city);
 
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        // Nominatim mewajibkan User-Agent yang jelas
        CURLOPT_USERAGENT      => 'RPC-Qibla-Praktikum/1.0',
        CURLOPT_HTTPHEADER     => ['Accept-Language: id,en'],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
 
    if ($body === false) {
        throw new RuntimeException('Gagal menghubungi layanan pencari kota: ' . $err);
    }
    if ($http !== 200) {
        throw new RuntimeException("Layanan pencari kota mengembalikan HTTP $http");
    }
 
    $places = json_decode($body, true);
    if (!is_array($places) || count($places) === 0) {
        throw new RuntimeException("Kota '$city' tidak ditemukan. Coba tulis lebih lengkap, mis. 'Banjar, Jawa Barat'.");
    }
 
    $lat = (float) $places[0]['lat'];
    $lon = (float) $places[0]['lon'];
 
    // Pakai fungsi utama yang sudah ada, lalu tambahkan nama tempat yang ditemukan
    $result = getQiblaDirection($lat, $lon);
    $result['place'] = $places[0]['display_name'] ?? $city;
    return $result;
}
 
/* ---------------------------------------------------------------------
 * Reverse geocoding: koordinat -> nama kota/kabupaten + provinsi.
 * Memakai OpenStreetMap Nominatim. Jika gagal, mengembalikan string kosong
 * (hasil arah kiblat tetap ditampilkan, hanya tanpa nama tempat).
 * ------------------------------------------------------------------- */
function reverseGeocode(float $lat, float $lon): string
{
    $url = sprintf(
        'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=10&accept-language=id&lat=%.6F&lon=%.6F',
        $lat,
        $lon
    );
 
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'RPC-Qibla-Praktikum/1.0', // wajib untuk Nominatim
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
 
    if ($body === false || $http !== 200) {
        return '';
    }
 
    $json = json_decode($body, true);
    if (!is_array($json)) {
        return '';
    }
 
    $addr  = $json['address'] ?? [];
    $parts = [];
 
    // Ambil nama kota/kabupaten (urutan prioritas), lalu provinsi
    foreach (['city', 'town', 'municipality', 'county', 'state_district', 'village'] as $key) {
        if (!empty($addr[$key])) {
            $parts[] = $addr[$key];
            break;
        }
    }
    if (!empty($addr['state']) && !in_array($addr['state'], $parts, true)) {
        $parts[] = $addr['state'];
    }
 
    return $parts ? implode(', ', $parts) : ($json['display_name'] ?? '');
}
 
/* Mengubah derajat menjadi mata angin 8 arah (bahasa Indonesia) */
function degreesToCompass(float $deg): string
{
    $names = ['Utara', 'Timur Laut', 'Timur', 'Tenggara',
              'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
    $index = (int) round(fmod($deg + 360, 360) / 45) % 8;
    return $names[$index];
}
 
/* ---------------------------------------------------------------------
 * 2. SERVER STUB
 *    - Unmarshalling : XML request  -> nama method + parameter PHP
 *    - Dispatch      : panggil fungsi utama
 *    - Marshalling   : hasil PHP    -> XML response
 * ------------------------------------------------------------------- */
function serverStubHandle(string $xmlRequest): string
{
    try {
        // --- Unmarshalling ---
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlRequest);
        if ($xml === false) {
            throw new InvalidArgumentException('Request bukan XML yang valid.');
        }
 
        $method = (string) $xml->method;
 
        // --- Dispatch ke fungsi utama ---
        switch ($method) {
            case 'getQiblaDirection':
                $lat = (string) $xml->params->latitude;
                $lon = (string) $xml->params->longitude;
                if (!is_numeric($lat) || !is_numeric($lon)) {
                    throw new InvalidArgumentException('Latitude dan longitude harus berupa angka.');
                }
                $result = getQiblaDirection((float) $lat, (float) $lon);
                // Tambahkan nama tempat dari koordinat (opsional, kosong jika gagal)
                $result['place'] = reverseGeocode((float) $lat, (float) $lon);
                break;
 
            case 'getQiblaByCity':
                $result = getQiblaByCity((string) $xml->params->city);
                break;
 
            default:
                throw new BadFunctionCallException("Method '$method' tidak dikenal di server.");
        }
 
        // --- Marshalling (sukses) ---
        return buildResponseXml('success', $result);
 
    } catch (Throwable $e) {
        // --- Marshalling (error) ---
        return buildResponseXml('error', ['message' => $e->getMessage()]);
    }
}
 
/* Membangun XML response dari array */
function buildResponseXml(string $status, array $data): string
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
 
    $root = $dom->createElement('rpcResponse');
    $dom->appendChild($root);
    $root->appendChild($dom->createElement('status', $status));
 
    $result = $dom->createElement('result');
    foreach ($data as $key => $value) {
        $result->appendChild($dom->createElement($key, htmlspecialchars((string) $value)));
    }
    $root->appendChild($result);
 
    return $dom->saveXML();
}
 
/* ---------------------------------------------------------------------
 * 3. ENTRY POINT
 *    Client mengirim XML lewat HTTP POST (body mentah).
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo buildResponseXml('error', ['message' => 'Gunakan HTTP POST dengan body XML.']);
    exit;
}
 
echo serverStubHandle(file_get_contents('php://input'));