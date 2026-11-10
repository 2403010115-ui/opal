
<?php
/**
 * ============================================================
 * CLIENT - RPC ARAH KIBLAT
 * File: index.php
 * Fitur:
 * 1. Pencarian berdasarkan nama kota
 * 2. Pencarian berdasarkan koordinat
 * 3. Menggunakan GPS perangkat
 * 4. Kompas dinamis mengikuti rotasi HP
 * ============================================================
 */

require_once 'client_stub.php';

// Daftar kota preset
$cities = [
    'Jakarta'    => [-6.2088, 106.8456],
    'Bandung'    => [-6.9175, 107.6191],
    'Surabaya'   => [-7.2575, 112.7521],
    'Medan'      => [3.5952, 98.6722],
    'Makassar'   => [-5.1477, 119.4327],
    'Jayapura'   => [-2.5337, 140.7181],
];

$result = null;
$error = null;

$city = trim($_POST['city'] ?? '');
$lat = $_POST['latitude'] ?? '';
$lon = $_POST['longitude'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($city !== '') {
            $result = getQiblaByCity($city);

        } elseif (is_numeric($lat) && is_numeric($lon)) {
            $result = getQiblaDirection(
                (float) $lat,
                (float) $lon
            );

        } else {
            $error = 'Isi nama kota atau koordinat terlebih dahulu.';
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    if ($result) {
        $lat = $result['latitude'];
        $lon = $result['longitude'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>RPC Arah Kiblat</title>

<link rel="stylesheet" href="css/style.css">

</head>

<body>

<div class="card">

    <h1>🕋 RPC Arah Kiblat</h1>

    <p class="sub">
        Temukan arah kiblat dengan mudah dan akurat
        menggunakan lokasi perangkat.
    </p>

    <!-- FORM PENCARIAN -->
    <form method="post" id="qibla-form">

        <button
            type="button"
            id="btn-loc"
            class="btn-loc">

            📍 Gunakan lokasi saya

        </button>

        <div id="loc-status"></div>

        <label for="city">Nama Kota</label>

        <input
            type="text"
            name="city"
            id="city"
            list="city-list"
            placeholder="Masukkan nama kota"
            value="<?= htmlspecialchars($city) ?>"
        >

        <datalist id="city-list">

            <?php foreach ($cities as $name => $c): ?>

                <option value="<?= htmlspecialchars($name) ?>">

            <?php endforeach; ?>

        </datalist>

        <small>
            Kosongkan nama kota jika ingin memakai koordinat manual di bawah.
        </small>

        <div class="row">

            <div>
                <label for="latitude">Latitude</label>

                <input
                    type="text"
                    name="latitude"
                    id="latitude"
                    placeholder="Masukkan latitude"
                    value="<?= htmlspecialchars((string)$lat) ?>"
                >
            </div>

            <div>
                <label for="longitude">Longitude</label>

                <input
                    type="text"
                    name="longitude"
                    id="longitude"
                    placeholder="Masukkan longitude"
                    value="<?= htmlspecialchars((string)$lon) ?>"
                >
            </div>

        </div>

        <button type="submit">
            Cari Arah Kiblat
        </button>

    </form>

    <!-- PESAN ERROR -->
    <?php if ($error): ?>

        <div class="error">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- HASIL ARAH KIBLAT -->
    <?php if ($result): ?>

        <div class="result">

            <?php if (!empty($result['place'])): ?>

                <div>
                    <strong>
                        <?= htmlspecialchars($result['place']) ?>
                    </strong>
                </div>

            <?php endif; ?>

            <div class="deg">
                <?= htmlspecialchars((string)$result['direction']) ?>°
            </div>

            <div>
                dari Utara sejati, arah
                <strong>
                    <?= htmlspecialchars($result['compass']) ?>
                </strong>
            </div>

            <!-- KOMPAS DINAMIS -->
            <div
                class="compass"
                id="compass"
                data-qibla="<?= htmlspecialchars((string)$result['direction']) ?>"
            >

                <span class="n">U</span>
                <span class="s">S</span>
                <span class="e">T</span>
                <span class="w">B</span>

                <div
                    class="needle"
                    id="needle">
                </div>

            </div>

            <!-- TOMBOL AKTIVASI KOMPAS -->
            <button
                type="button"
                id="btn-compass"
                class="btn-compass">

                🧭 Aktifkan Kompas

            </button>

            <div id="compass-status">
                Tekan tombol untuk mengaktifkan sensor kompas HP.
            </div>

            <br>

            <small>
                Koordinat:
                <?= htmlspecialchars((string)$result['latitude']) ?>,
                <?= htmlspecialchars((string)$result['longitude']) ?>

                <br>

                Sumber:
                <?= htmlspecialchars($result['source']) ?>
            </small>

        </div>

    <?php endif; ?>

</div>


<!-- JAVASCRIPT -->
<script>

// ==========================================
// 1. FITUR GPS / LOKASI PERANGKAT
// ==========================================

(function () {

    const btn = document.getElementById('btn-loc');
    const status = document.getElementById('loc-status');
    const form = document.getElementById('qibla-form');

    ['latitude', 'longitude'].forEach(function (id) {

        document.getElementById(id).addEventListener('input', function () {
            document.getElementById('city').value = '';
        });

    });

    if (!btn) return;

    btn.addEventListener('click', function () {

        if (!navigator.geolocation || !window.isSecureContext) {

            status.textContent =
                'Lokasi membutuhkan HTTPS atau localhost.';

            return;
        }

        status.textContent = 'Mengambil lokasi perangkat...';

        btn.disabled = true;

        navigator.geolocation.getCurrentPosition(

            function (pos) {

                document.getElementById('latitude').value =
                    pos.coords.latitude.toFixed(6);

                document.getElementById('longitude').value =
                    pos.coords.longitude.toFixed(6);

                document.getElementById('city').value = '';

                status.textContent =
                    'Lokasi berhasil didapat. Menghitung arah kiblat...';

                form.submit();

            },

            function (err) {

                btn.disabled = false;

                const pesan = {
                    1: 'Izin lokasi ditolak. Silakan izinkan akses lokasi.',
                    2: 'Lokasi perangkat tidak tersedia.',
                    3: 'Waktu pengambilan lokasi habis. Coba kembali.'
                };

                status.textContent =
                    pesan[err.code] || 'Gagal mengambil lokasi.';

            },

            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }

        );

    });

})();


// ==========================================
// 2. FITUR KOMPAS DINAMIS
// ==========================================

(function () {

    const compass = document.getElementById('compass');
    const needle = document.getElementById('needle');
    const button = document.getElementById('btn-compass');
    const status = document.getElementById('compass-status');

    // Kompas hanya aktif jika hasil arah kiblat tersedia
    if (!compass || !needle || !button) return;

    // Mengambil derajat arah kiblat dari server
    const qiblaDirection = parseFloat(
        compass.dataset.qibla
    );

    let active = false;
    let receivedSensor = false;

    // Membaca arah perangkat
    function updateCompass(event) {

        let heading = null;

        // Sensor kompas iPhone / iPad
        if (
            typeof event.webkitCompassHeading === 'number' &&
            Number.isFinite(event.webkitCompassHeading)
        ) {

            heading = event.webkitCompassHeading;

        }

        // Sensor orientasi absolut Android
        else if (
            event.absolute === true &&
            event.alpha !== null &&
            Number.isFinite(event.alpha)
        ) {

            heading = (360 - event.alpha) % 360;

        }

        // Jika sensor belum memberikan arah
        if (heading === null) {

            status.textContent =
                'Menunggu data sensor kompas absolut. Pastikan sensor perangkat mendukung fitur ini.';

            return;
        }

        receivedSensor = true;

        // Menghitung posisi jarum terhadap arah HP
        const needleAngle =
            (qiblaDirection - heading + 360) % 360;

        // Menggerakkan jarum kompas
        needle.style.transform =
            `rotate(${needleAngle}deg)`;

        status.textContent =
            'Kompas aktif | Arah HP: ' +
            heading.toFixed(1) + '°';

    }

    // Mengaktifkan sensor kompas
    async function activateCompass() {

        if (!window.isSecureContext) {

            status.textContent =
                'Kompas membutuhkan HTTPS atau localhost.';

            return;
        }

        if (active) return;

        button.disabled = true;

        status.textContent =
            'Meminta izin sensor kompas...';

        try {

            if (!('DeviceOrientationEvent' in window)) {

                throw new Error(
                    'Browser tidak mendukung sensor orientasi.'
                );

            }

            // Permintaan izin khusus iPhone / iPad
            if (
                typeof DeviceOrientationEvent.requestPermission === 'function'
            ) {

                const permission =
                    await DeviceOrientationEvent.requestPermission();

                if (permission !== 'granted') {

                    throw new Error(
                        'Izin sensor kompas ditolak.'
                    );

                }

            }

            // Memulai pembacaan sensor
            window.addEventListener(
                'deviceorientation',
                updateCompass,
                true
            );

            active = true;

            button.textContent = '🧭 Kompas Aktif';

            status.textContent =
                'Sensor aktif. Silakan putar HP perlahan.';

            // Cek apakah sensor memberikan data
            setTimeout(function () {

                if (!receivedSensor) {

                    status.textContent =
                        'Belum menerima data arah absolut. Periksa dukungan sensor dan kalibrasi HP.';

                    button.disabled = false;
                    button.textContent = '🧭 Coba Aktifkan Lagi';

                    window.removeEventListener(
                        'deviceorientation',
                        updateCompass,
                        true
                    );

                    active = false;

                }

            }, 5000);

        } catch (error) {

            button.disabled = false;

            status.textContent = error.message;

        }

    }

    // Tombol aktivasi
    button.addEventListener(
        'click',
        activateCompass
    );

})();

</script>

</body>
</html>
