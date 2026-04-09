<?php
// ============================================================
// SEED ADMIN — Proteksi dengan secret key
// Akses: http://localhost/airbiru/seed_admin.php?key=SECRET123
// Hapus file ini setelah dijalankan!
// ============================================================
if (($_GET['key'] ?? '') !== 'SECRET123') {
    http_response_code(403);
    die('403 Forbidden — Akses ditolak.');
}

include 'koneksi.php';

$users = [
    ['Admin Air Biru',  'admin@airbiru.com',   '081234567890', 'admin123',  'admin'],
    ['Driver Budi',     'driver@airbiru.com',  '082345678901', 'driver123', 'driver'],
    ['Driver Eko',      'driver2@airbiru.com', '083456789012', 'driver123', 'driver'],
    ['Driver Susi',     'driver3@airbiru.com', '084567890123', 'driver123', 'driver'],
];

echo '<style>body{font-family:monospace;padding:30px;background:#f0f7fb;}
.ok{color:green;}.err{color:red;}.info{color:#0a2463;}</style>';
echo '<h2>🌱 Air Biru — Seed Admin & Driver</h2><hr>';

foreach ($users as [$nama, $email, $hp, $pass, $role]) {
    $hash  = password_hash($pass, PASSWORD_DEFAULT);
    $stmt  = mysqli_prepare($koneksi, "SELECT id FROM users WHERE email=?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $exist = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($exist) {
        $stmt = mysqli_prepare($koneksi,
            "UPDATE users SET nama=?,no_hp=?,password=?,role=? WHERE email=?");
        mysqli_stmt_bind_param($stmt,'sssss',$nama,$hp,$hash,$role,$email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "<p class='ok'>✅ Updated: <b>$email</b> (role: $role, pass: $pass)</p>";
    } else {
        $stmt = mysqli_prepare($koneksi,
            "INSERT INTO users (nama,email,no_hp,password,role) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt,'sssss',$nama,$email,$hp,$hash,$role);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "<p class='ok'>✅ Created: <b>$email</b> (role: $role, pass: $pass)</p>";
    }
}

echo '<hr><p class="info"><b>Selesai!</b><br>';
echo '⚠️ <b>Hapus file seed_admin.php segera setelah ini!</b></p>';
echo '<p><a href="index.php">→ Ke index.php</a></p>';

mysqli_close($koneksi);
?>
