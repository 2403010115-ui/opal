<?php
/**
 * =====================================================================
 *  CLIENT STUB (PC 1) - RPC ARAH KIBLAT
 *  File   : client_stub.php
 *  Fungsi : Membuat pemanggilan fungsi remote terlihat seperti fungsi
 *           lokal. Stub ini:
 *             1. Marshalling   : parameter PHP -> XML request
 *             2. Mengirim XML ke server via HTTP POST
 *             3. Unmarshalling : XML response  -> array PHP
 * =====================================================================
 */
 
// Alamat server (PC 2). Ganti IP sesuai jaringan, mis. http://192.168.1.20/qibla/qibla_server.php
// const SERVER_URL = 'http://192.168.1.20/qibla/qibla_server.php';
const SERVER_URL = 'http://localhost/rpc_arah_kiblat/rpc_arah_kiblat/qibla_server.php';
 
/* Fungsi yang dipanggil oleh aplikasi client (terlihat seperti fungsi lokal) */
function getQiblaDirection(float $lat, float $lon): array
{
    // 1. Marshalling: bangun XML request
    $xmlRequest = buildRequestXml('getQiblaDirection', [
        'latitude'  => $lat,
        'longitude' => $lon,
    ]);
 
    // 2. Kirim ke server dan terima XML response
    $xmlResponse = sendToServer($xmlRequest);
 
    // 3. Unmarshalling: ubah XML response menjadi array
    return parseResponseXml($xmlResponse);
}
 
/* Pemanggilan remote kedua: kirim nama kota, server yang mencari koordinatnya */
function getQiblaByCity(string $city): array
{
    $xmlRequest  = buildRequestXml('getQiblaByCity', ['city' => $city]);
    $xmlResponse = sendToServer($xmlRequest);
    return parseResponseXml($xmlResponse);
}
 
/* Membangun XML request */
function buildRequestXml(string $method, array $params): string
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
 
    $root = $dom->createElement('rpcRequest');
    $dom->appendChild($root);
    $root->appendChild($dom->createElement('method', $method));
 
    $p = $dom->createElement('params');
    foreach ($params as $name => $value) {
        // %F -> titik desimal tetap "." apa pun locale-nya
        $p->appendChild($dom->createElement($name, is_float($value) ? sprintf('%.6F', $value) : (string) $value));
    }
    $root->appendChild($p);
 
    return $dom->saveXML();
}
 
/* Mengirim XML ke server lewat HTTP POST */
function sendToServer(string $xml): string
{
    $ch = curl_init(SERVER_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/xml'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);
 
    if ($response === false) {
        throw new RuntimeException('Tidak dapat terhubung ke server RPC: ' . $err);
    }
    return $response;
}
 
/* Membaca XML response dan mengubahnya menjadi array */
function parseResponseXml(string $xmlText): array
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlText);
    if ($xml === false) {
        throw new RuntimeException('Response dari server bukan XML yang valid.');
    }
 
    if ((string) $xml->status !== 'success') {
        throw new RuntimeException((string) $xml->result->message);
    }
 
    return [
        'latitude'  => (float)  $xml->result->latitude,
        'longitude' => (float)  $xml->result->longitude,
        'direction' => (float)  $xml->result->direction,
        'compass'   => (string) $xml->result->compass,
        'source'    => (string) $xml->result->source,
        'place'     => (string) $xml->result->place, // kosong jika request lewat koordinat
    ];
}
 