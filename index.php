<?php
session_start();
include 'koneksi.php';

// Kalau sudah login, redirect berdasarkan role
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'pelanggan';
    if ($role === 'admin') {
        header("Location: admin_dashboard.php");
    } elseif ($role === 'driver') {
        header("Location: driver_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$msg_daftar = "";
$msg_login  = "";
$show_login = false;

// PROSES DAFTAR
if (isset($_POST['action']) && $_POST['action'] === 'daftar') {
    $nama  = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $email = mysqli_real_escape_string($koneksi, trim($_POST['email']));
    $hp    = mysqli_real_escape_string($koneksi, trim($_POST['hp']));
    $pass  = $_POST['password'];

    if (!$nama || !$email || !$hp || !$pass) {
        $msg_daftar = ['type' => 'error', 'text' => 'Semua field wajib diisi!'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg_daftar = ['type' => 'error', 'text' => 'Format email tidak valid!'];
    } elseif (strlen($pass) < 6) {
        $msg_daftar = ['type' => 'error', 'text' => 'Password minimal 6 karakter!'];
    } else {
        $cek = mysqli_query($koneksi, "SELECT id FROM users WHERE email='$email'");
        if (mysqli_num_rows($cek) > 0) {
            $msg_daftar = ['type' => 'error', 'text' => 'Email sudah terdaftar!'];
        } else {
            $hash  = password_hash($pass, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (nama, email, no_hp, password, role) VALUES ('$nama','$email','$hp','$hash','pelanggan')";
            if (mysqli_query($koneksi, $query)) {
                $msg_daftar = ['type' => 'success', 'text' => 'Pendaftaran berhasil! Silakan login.'];
                $show_login = true;
            } else {
                $msg_daftar = ['type' => 'error', 'text' => 'Gagal mendaftar: ' . mysqli_error($koneksi)];
            }
        }
    }
}

// PROSES LOGIN
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $show_login  = true;
    $email       = mysqli_real_escape_string($koneksi, trim($_POST['email']));
    $pass        = $_POST['password'];
    $login_role  = isset($_POST['login_role']) ? trim($_POST['login_role']) : 'pelanggan';

    // Validasi nilai role agar aman
    $allowed_roles = ['pelanggan', 'admin', 'driver'];
    if (!in_array($login_role, $allowed_roles)) {
        $login_role = 'pelanggan';
    }

    if (!$email || !$pass) {
        $msg_login = ['type' => 'error', 'text' => 'Email dan password wajib diisi!'];
    } else {
        $result = mysqli_query($koneksi, "SELECT * FROM users WHERE email='$email'");
        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($pass, $row['password'])) {
                // === VALIDASI ROLE ===
                if ($row['role'] !== $login_role) {
                    $role_labels = [
                        'pelanggan' => 'Pelanggan',
                        'admin'     => 'Admin',
                        'driver'    => 'Driver',
                    ];
                    $selected_label = $role_labels[$login_role] ?? ucfirst($login_role);
                    $actual_label   = $role_labels[$row['role']] ?? ucfirst($row['role']);
                    $msg_login = [
                        'type' => 'error',
                        'text' => "Akun ini terdaftar sebagai {$actual_label}, bukan {$selected_label}. Pilih tab role yang sesuai.",
                    ];
                } else {
                    // Role cocok — simpan session & redirect
                    $_SESSION['user_id']    = $row['id'];
                    $_SESSION['user_nama']  = $row['nama'];
                    $_SESSION['user_role']  = $row['role'];
                    $_SESSION['user_email'] = $row['email'];
                    $_SESSION['user_hp']    = $row['no_hp'];

                    if ($row['role'] === 'admin') {
                        header("Location: admin_dashboard.php");
                    } elseif ($row['role'] === 'driver') {
                        header("Location: driver_dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit;
                }
            } else {
                $msg_login = ['type' => 'error', 'text' => 'Password salah!'];
            }
        } else {
            $msg_login = ['type' => 'error', 'text' => 'Email tidak ditemukan!'];
        }
    }
}

mysqli_close($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Air Biru — Pemesanan Air Galon</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'], serif: ['Instrument Serif', 'serif'] },
          colors: {
            navy: { 950: '#03112e', 900: '#0a2463', 800: '#0d3180', 700: '#1a4aa0' },
            cyan: { brand: '#34b4c8', light: '#a8dadc', pale: '#e8f4f8' }
          }
        }
      }
    }
  </script>
  <style>
    html { scroll-behavior: smooth; }
    .hero-bg { background: linear-gradient(135deg, #03112e 0%, #0a2463 45%, #0d4f7a 100%); }
    .card-hover { transition: transform 0.25s ease, box-shadow 0.25s ease; }
    .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(10,36,99,0.15); }
    .section-tag { display: inline-block; background: rgba(52,180,200,0.12); color: #34b4c8; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; padding: 5px 14px; border-radius: 999px; border: 1px solid rgba(52,180,200,0.3); margin-bottom: 12px; }
    .input-style { width: 100%; padding: 11px 16px; background: #f3f8fb; border: 1.5px solid #c5dde6; border-radius: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.92rem; color: #0a2463; outline: none; transition: border-color 0.2s; }
    .input-style:focus { border-color: #34b4c8; background: #fff; }
    .btn-primary { background: linear-gradient(135deg, #0a2463, #168aad); color: white; padding: 13px 32px; border-radius: 999px; font-weight: 700; font-size: 0.95rem; border: none; cursor: pointer; transition: opacity 0.2s, transform 0.2s; display: inline-block; text-align: center; text-decoration: none; }
    .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
    .fade-up { animation: fadeUp 0.7s ease both; }
    .delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; } .delay-3 { animation-delay: 0.35s; } .delay-4 { animation-delay: 0.5s; }
    .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 0.9rem; }
    .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 0.9rem; }
    .reg-tab-btn { padding: 9px 22px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; cursor: pointer; border: none; transition: all 0.2s; }
    .reg-tab-btn.active { background: #0a2463; color: white; }
    .reg-tab-btn:not(.active) { background: #e8f4f8; color: #0a2463; }
    @keyframes badge-float { 0% { transform: translateY(0px); } 100% { transform: translateY(-6px); } }
    .hero-badge:nth-child(4) { animation: badge-float 3s ease-in-out 0s infinite alternate; }
    .hero-badge:nth-child(5) { animation: badge-float 3s ease-in-out 1s infinite alternate; }
    .hero-badge:nth-child(6) { animation: badge-float 3s ease-in-out 2s infinite alternate; }
    @keyframes modal-in { from { opacity: 0; transform: scale(0.92) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    #feature-modal-box { animation: modal-in 0.25s ease both; }
    .feature-card { cursor: pointer; transition: all 0.2s; }
    .feature-card:active { transform: scale(0.98); }

    /* ===== LOGIN ROLE TABS ===== */
    .role-tabs-wrapper { display: flex; gap: 6px; background: #f0f7fb; padding: 5px; border-radius: 999px; margin-bottom: 22px; }
    .role-tab { display: flex; align-items: center; gap: 7px; padding: 9px 20px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; cursor: pointer; border: 2px solid transparent; transition: all 0.22s; background: transparent; color: #64748b; }
    .role-tab:hover { background: #f0f7fb; color: #0a2463; }
    .role-tab.active { background: #0a2463; color: white; border-color: #0a2463; box-shadow: 0 4px 14px rgba(10,36,99,0.25); }

    /* ===== PASSWORD TOGGLE ===== */
    .password-wrapper { position: relative; }
    .password-wrapper .input-style { padding-right: 44px; }
    .pwd-toggle { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #94a3b8; padding: 4px; display: flex; align-items: center; justify-content: center; transition: color 0.2s; }
    .pwd-toggle:hover { color: #0a2463; }
    .pwd-toggle svg { width: 19px; height: 19px; }

    /* ===== TIM DEVELOPER SECTION ===== */
    .tim-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(10,36,99,0.07); border: 1px solid #e2edf5; transition: transform 0.25s ease, box-shadow 0.25s ease; }
    .tim-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(10,36,99,0.14); }
    .tim-card-header { background: linear-gradient(135deg, #0a2463 0%, #0d4f7a 100%); height: 120px; display: flex; align-items: center; justify-content: center; position: relative; }
    .tim-avatar { width: 86px; height: 86px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 4px 14px rgba(0,0,0,0.25); background: #e8f4f8; }
    .tim-avatar-placeholder { width: 86px; height: 86px; border-radius: 50%; border: 3px solid white; box-shadow: 0 4px 14px rgba(0,0,0,0.25); background: linear-gradient(135deg, #34b4c8, #0a2463); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: 700; }
    .skill-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: #e8f4f8; color: #0a2463; border: 1px solid #c5dde6; }
    .tim-npm { color: #34b4c8; font-size: 0.8rem; font-weight: 700; margin: 2px 0 8px; }
    .tim-meta { color: #64748b; font-size: 0.78rem; display: flex; align-items: center; gap: 4px; margin-bottom: 2px; }
  </style>
</head>
<body class="bg-[#f0f7fb] font-sans text-navy-900">

<!-- NAVBAR -->
<nav class="fixed top-0 w-full z-50 bg-navy-900/95 backdrop-blur-sm border-b border-white/10">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
    <a href="index.php" class="flex items-center gap-2 text-white font-bold text-lg" style="text-decoration:none;">
      <span class="text-2xl">💧</span><span>Air<span class="text-cyan-brand">Biru</span></span>
    </a>
    <div class="flex items-center gap-1 text-sm">
      <a href="#tentang" class="text-white/70 hover:text-white px-4 py-2 rounded-lg transition-colors">Tentang</a>
      <a href="#masalah" class="text-white/70 hover:text-white px-4 py-2 rounded-lg transition-colors">Masalah</a>
      <a href="#solusi"  class="text-white/70 hover:text-white px-4 py-2 rounded-lg transition-colors">Solusi</a>
      <a href="#tim"     class="text-white/70 hover:text-white px-4 py-2 rounded-lg transition-colors">Tim</a>
      <a href="#daftar"  class="ml-2 bg-cyan-brand font-bold px-4 py-2 rounded-full hover:opacity-90 transition-opacity" style="color:#0a2463;">Daftar</a>
      <a href="#login"   class="ml-1 border border-white/40 text-white px-4 py-2 rounded-full text-xs font-bold hover:bg-white/10 transition-colors">Login</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero-bg min-h-screen flex items-center pt-16">
  <div class="max-w-6xl mx-auto px-6 py-24 grid grid-cols-2 gap-16 items-center">
    <div>
      <div class="section-tag fade-up">Aplikasi Pemesanan Air Galon</div>
      <h1 class="font-serif text-5xl text-white leading-tight mt-3 mb-6 fade-up delay-1">
        Solusi Cerdas<br><span class="italic text-cyan-brand">Pesan Air Galon</span><br>dari Genggaman
      </h1>
      <p class="text-cyan-light/80 text-base leading-relaxed mb-8 fade-up delay-2">Air Biru adalah aplikasi pemesanan air galon berbasis lokasi yang menghubungkan pelanggan dengan depot terdekat secara otomatis — cepat, mudah, dan transparan.</p>
      <div class="flex gap-4 fade-up delay-3">
        <a href="#daftar" class="btn-primary">Daftar Sekarang</a>
        <a href="#tentang" class="text-white border border-white/30 px-7 py-3 rounded-full font-semibold text-sm hover:bg-white/10 transition-colors">Pelajari Lebih</a>
      </div>
      <div class="flex gap-8 mt-12 fade-up delay-4">
        <div><div class="text-white text-2xl font-bold">12+</div><div class="text-cyan-light/60 text-xs">Depot Aktif</div></div>
        <div class="w-px bg-white/20"></div>
        <div><div class="text-white text-2xl font-bold">5.000+</div><div class="text-cyan-light/60 text-xs">Pelanggan</div></div>
        <div class="w-px bg-white/20"></div>
        <div><div class="text-white text-2xl font-bold">4.8/5</div><div class="text-cyan-light/60 text-xs">Rating</div></div>
      </div>
    </div>
    <div class="fade-up delay-2 flex justify-center">
      <div class="relative w-72 h-72">
        <div class="absolute inset-0 rounded-full bg-cyan-brand/10 animate-ping" style="animation-duration:3s;"></div>
        <div class="absolute inset-6 rounded-full bg-cyan-brand/15 border border-cyan-brand/30"></div>
        <div class="absolute inset-14 rounded-full bg-navy-800 border-2 border-cyan-brand/50 flex items-center justify-center"><span class="text-7xl">💧</span></div>
        <a href="#solusi" class="hero-badge absolute -top-2 -right-4 bg-white text-navy-900 text-xs font-bold px-3 py-2 rounded-full shadow-lg flex items-center gap-1 hover:bg-cyan-brand hover:text-white transition-all duration-300">
          <span>🚚</span><span>Lacak Realtime</span>
        </a>
        <a href="#solusi" class="hero-badge absolute bottom-6 -right-8 bg-white text-navy-900 text-xs font-bold px-3 py-2 rounded-full shadow-lg flex items-center gap-1 hover:bg-cyan-brand hover:text-white transition-all duration-300">
          <span>📍</span><span>Lokasi Otomatis</span>
        </a>
        <a href="#solusi" class="hero-badge absolute -bottom-4 -left-6 bg-white text-navy-900 text-xs font-bold px-3 py-2 rounded-full shadow-lg flex items-center gap-1 hover:bg-cyan-brand hover:text-white transition-all duration-300">
          <span>🔔</span><span>Reminder</span>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- TENTANG -->
<section id="tentang" class="py-24 bg-white">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center mb-14">
      <div class="section-tag">Tentang Kami</div>
      <h2 class="font-serif text-4xl text-navy-900 mt-2">Apa itu <span class="italic text-cyan-brand">Air Biru?</span></h2>
    </div>
    <div class="grid grid-cols-3 gap-8">
      <div class="col-span-2 space-y-5">
        <p class="text-gray-600 leading-relaxed">Air Biru adalah platform pemesanan air galon digital yang dirancang untuk memudahkan pelanggan mendapatkan air minum berkualitas kapan saja dan di mana saja.</p>
        <p class="text-gray-600 leading-relaxed">Dengan sistem berbasis lokasi, Air Biru secara otomatis mendeteksi depot terdekat dari alamat pelanggan dan meneruskan pesanan langsung ke sana — memangkas waktu tunggu sekaligus mengurangi beban komunikasi manual.</p>
        <div class="grid grid-cols-2 gap-4 pt-4">
          <div class="bg-cyan-pale rounded-xl p-5 border border-cyan-light/50"><div class="text-navy-900 font-bold text-sm mb-1">📍 Berbasis Lokasi</div><div class="text-gray-600 text-sm">Deteksi depot terdekat otomatis dari alamat Anda</div></div>
          <div class="bg-cyan-pale rounded-xl p-5 border border-cyan-light/50"><div class="text-navy-900 font-bold text-sm mb-1">🚚 Pengiriman Cepat</div><div class="text-gray-600 text-sm">Estimasi pengiriman 1–2 jam untuk area sekitar</div></div>
          <div class="bg-cyan-pale rounded-xl p-5 border border-cyan-light/50"><div class="text-navy-900 font-bold text-sm mb-1">🔄 Langganan Mingguan</div><div class="text-gray-600 text-sm">Atur jadwal rutin agar galon datang otomatis</div></div>
          <div class="bg-cyan-pale rounded-xl p-5 border border-cyan-light/50"><div class="text-navy-900 font-bold text-sm mb-1">📋 Transparan &amp; Aman</div><div class="text-gray-600 text-sm">Lacak status pesanan dan riwayat transaksi</div></div>
        </div>
      </div>
      <div class="bg-navy-900 rounded-2xl p-7 text-white">
        <div class="text-cyan-brand font-bold text-sm mb-6 uppercase tracking-widest">Mengapa Air Biru?</div>
        <div class="space-y-5">
          <div class="flex gap-3 items-start"><div class="text-cyan-brand text-xl flex-shrink-0">✅</div><div><div class="font-semibold text-sm">Hemat Waktu</div><div class="text-white/50 text-xs mt-1">Tidak perlu telepon atau datang langsung ke depot</div></div></div>
          <div class="flex gap-3 items-start"><div class="text-cyan-brand text-xl flex-shrink-0">✅</div><div><div class="font-semibold text-sm">Harga Transparan</div><div class="text-white/50 text-xs mt-1">Tidak ada biaya tersembunyi, harga tertera jelas</div></div></div>
          <div class="flex gap-3 items-start"><div class="text-cyan-brand text-xl flex-shrink-0">✅</div><div><div class="font-semibold text-sm">Lacak Real-Time</div><div class="text-white/50 text-xs mt-1">Pantau status pengiriman hingga galon tiba</div></div></div>
          <div class="flex gap-3 items-start"><div class="text-cyan-brand text-xl flex-shrink-0">✅</div><div><div class="font-semibold text-sm">Mudah Dilaporkan</div><div class="text-white/50 text-xs mt-1">Keluhan langsung masuk ke sistem admin</div></div></div>
          <div class="flex gap-3 items-start"><div class="text-cyan-brand text-xl flex-shrink-0">✅</div><div><div class="font-semibold text-sm">Driver Terverifikasi</div><div class="text-white/50 text-xs mt-1">Semua pengantar telah melalui seleksi ketat</div></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MASALAH -->
<section id="masalah" class="py-24 bg-[#f0f7fb]">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center mb-14">
      <div class="section-tag">Latar Belakang</div>
      <h2 class="font-serif text-4xl text-navy-900 mt-2">Masalah yang Ingin <span class="italic text-cyan-brand">Diselesaikan</span></h2>
    </div>
    <div class="grid grid-cols-3 gap-6">
      <div class="bg-white rounded-2xl p-7 card-hover border border-gray-100"><div class="text-3xl mb-4">📞</div><h3 class="font-bold text-navy-900 mb-2">Pemesanan Manual</h3><p class="text-gray-500 text-sm leading-relaxed">Pelanggan harus menelepon atau datang langsung ke depot — tidak praktis.</p></div>
      <div class="bg-white rounded-2xl p-7 card-hover border border-gray-100"><div class="text-3xl mb-4">🗺️</div><h3 class="font-bold text-navy-900 mb-2">Tidak Tahu Depot Terdekat</h3><p class="text-gray-500 text-sm leading-relaxed">Pelanggan kesulitan menemukan depot yang bisa melayani area mereka.</p></div>
      <div class="bg-white rounded-2xl p-7 card-hover border border-gray-100"><div class="text-3xl mb-4">❓</div><h3 class="font-bold text-navy-900 mb-2">Status Pengiriman Tidak Jelas</h3><p class="text-gray-500 text-sm leading-relaxed">Tidak ada informasi real-time tentang kapan pesanan tiba.</p></div>
      <div class="bg-white rounded-2xl p-7 card-hover border border-gray-100"><div class="text-3xl mb-4">🔄</div><h3 class="font-bold text-navy-900 mb-2">Tidak Ada Langganan Otomatis</h3><p class="text-gray-500 text-sm leading-relaxed">Pelanggan rutin harus memesan ulang setiap kali kehabisan.</p></div>
      <div class="bg-white rounded-2xl p-7 card-hover border border-gray-100"><div class="text-3xl mb-4">💧</div><h3 class="font-bold text-navy-900 mb-2">Lupa Minum Air</h3><p class="text-gray-500 text-sm leading-relaxed">Banyak orang tidak memenuhi kebutuhan hidrasi harian mereka.</p></div>
      <div class="bg-white rounded-2xl p-7 card-hover border border-gray-100"><div class="text-3xl mb-4">📋</div><h3 class="font-bold text-navy-900 mb-2">Tidak Ada Saluran Pengaduan</h3><p class="text-gray-500 text-sm leading-relaxed">Keluhan tidak bisa dilaporkan secara terstruktur kepada depot.</p></div>
    </div>
  </div>
</section>

<!-- SOLUSI -->
<section id="solusi" class="py-24 bg-navy-900">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center mb-14">
      <div class="section-tag">Fitur Aplikasi</div>
      <h2 class="font-serif text-4xl text-white mt-2">Solusi yang <span class="italic text-cyan-brand">Kami Tawarkan</span></h2>
    </div>
    <div class="grid grid-cols-2 gap-5">
      <div onclick="openFeatureModal('lokasi')" class="feature-card bg-white/5 border border-white/10 rounded-2xl p-6 flex gap-5 items-start hover:bg-white/10 hover:border-cyan-brand/50 transition-all cursor-pointer group">
        <div class="text-4xl flex-shrink-0 group-hover:scale-110 transition-transform">📍</div>
        <div><h3 class="text-white font-bold mb-1">Pemesanan Berbasis Lokasi</h3><p class="text-cyan-light/60 text-sm leading-relaxed">Sistem otomatis mendeteksi depot terdekat dari alamat pelanggan sehingga pesanan langsung diterima oleh depot yang paling dekat.</p></div>
      </div>
      <div onclick="openFeatureModal('lacak')" class="feature-card bg-white/5 border border-white/10 rounded-2xl p-6 flex gap-5 items-start hover:bg-white/10 hover:border-cyan-brand/50 transition-all cursor-pointer group">
        <div class="text-4xl flex-shrink-0 group-hover:scale-110 transition-transform">🚚</div>
        <div><h3 class="text-white font-bold mb-1">Lacak Pengiriman Real-Time</h3><p class="text-cyan-light/60 text-sm leading-relaxed">Pantau posisi driver dan status pesanan secara langsung. Notifikasi otomatis dikirim saat pesanan berubah status.</p></div>
      </div>
      <div onclick="openFeatureModal('reminder')" class="feature-card bg-white/5 border border-white/10 rounded-2xl p-6 flex gap-5 items-start hover:bg-white/10 hover:border-cyan-brand/50 transition-all cursor-pointer group">
        <div class="text-4xl flex-shrink-0 group-hover:scale-110 transition-transform">🔔</div>
        <div><h3 class="text-white font-bold mb-1">Reminder Minum Air Harian</h3><p class="text-cyan-light/60 text-sm leading-relaxed">Pengingat dan edukasi hidrasi minimal 8 gelas per hari. Tracker gelas harian tersedia di dashboard pelanggan.</p></div>
      </div>
      <div onclick="openFeatureModal('langganan')" class="feature-card bg-white/5 border border-white/10 rounded-2xl p-6 flex gap-5 items-start hover:bg-white/10 hover:border-cyan-brand/50 transition-all cursor-pointer group">
        <div class="text-4xl flex-shrink-0 group-hover:scale-110 transition-transform">🔄</div>
        <div><h3 class="text-white font-bold mb-1">Langganan Otomatis</h3><p class="text-cyan-light/60 text-sm leading-relaxed">Atur jadwal pengiriman rutin dan galon akan datang sendiri tanpa perlu pesan ulang setiap kali habis.</p></div>
      </div>
      <div onclick="openFeatureModal('laporan')" class="feature-card bg-white/5 border border-white/10 rounded-2xl p-6 flex gap-5 items-start hover:bg-white/10 hover:border-cyan-brand/50 transition-all cursor-pointer group">
        <div class="text-4xl flex-shrink-0 group-hover:scale-110 transition-transform">📋</div>
        <div><h3 class="text-white font-bold mb-1">Form Laporan Masalah</h3><p class="text-cyan-light/60 text-sm leading-relaxed">Laporkan kendala pengiriman atau kualitas produk secara terstruktur langsung ke sistem admin.</p></div>
      </div>
      <div onclick="openFeatureModal('riwayat')" class="feature-card bg-white/5 border border-white/10 rounded-2xl p-6 flex gap-5 items-start hover:bg-white/10 hover:border-cyan-brand/50 transition-all cursor-pointer group">
        <div class="text-4xl flex-shrink-0 group-hover:scale-110 transition-transform">📜</div>
        <div><h3 class="text-white font-bold mb-1">Riwayat Pesanan (CRUD)</h3><p class="text-cyan-light/60 text-sm leading-relaxed">Kelola semua data pesanan Anda dengan mudah — lihat, ubah, atau hapus riwayat transaksi kapan saja.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================ -->
<!--  SECTION TIM DEVELOPER                                       -->
<!-- ============================================================ -->
<section id="tim" class="py-24 bg-white">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center mb-14">
      <div class="section-tag">Tim Developer</div>
      <h2 class="font-serif text-4xl text-navy-900 mt-2">Kelompok <span class="italic text-cyan-brand">13</span></h2>
      <p class="text-gray-500 mt-3 text-sm">Sistem Informasi &middot; Angkatan 2024 &middot; UPN Veteran Jawa Timur</p>
    </div>

    <div class="grid grid-cols-4 gap-6">

      <!-- Anggota 1: Michelle Evelyn Dyvani -->
      <div class="tim-card">
        <div class="tim-card-header">
          <!-- Ganti placeholder dengan foto asli:
               <img src="assets/tim/michelle.jpg" class="tim-avatar" alt="Michelle Evelyn Dyvani">
               Lalu hapus baris <div class="tim-avatar-placeholder"> di bawah ini. -->
          <div class="tim-avatar-placeholder">M</div>
        </div>
        <div class="p-5">
          <div class="font-bold text-navy-900 text-sm leading-snug">Michelle Evelyn Dyvani</div>
          <div class="tim-npm">24082010160</div>
          <div class="tim-meta"><span>📍</span> Surabaya</div>
          <div class="tim-meta"><span>🎬</span> Menonton Film</div>
          <div class="flex flex-wrap gap-1 mt-3">
            <span class="skill-badge">HTML</span>
            <span class="skill-badge">UI/UX</span>
            <span class="skill-badge">MySQL</span>
          </div>
        </div>
      </div>

      <!-- Anggota 2: Lutfia Nur Sabrina -->
      <div class="tim-card">
        <div class="tim-card-header">
          <!-- Ganti placeholder dengan foto asli:
               <img src="assets/tim/lutfia.jpg" class="tim-avatar" alt="Lutfia Nur Sabrina">
               Lalu hapus baris <div class="tim-avatar-placeholder"> di bawah ini. -->
          <div class="tim-avatar-placeholder">L</div>
        </div>
        <div class="p-5">
          <div class="font-bold text-navy-900 text-sm leading-snug">Lutfia Nur Sabrina</div>
          <div class="tim-npm">24082010175</div>
          <div class="tim-meta"><span>📍</span> Sidoarjo</div>
          <div class="tim-meta"><span>🎬</span> Menonton Film</div>
          <div class="flex flex-wrap gap-1 mt-3">
            <span class="skill-badge">Java</span>
            <span class="skill-badge">MySQL</span>
            <span class="skill-badge">Coding</span>
          </div>
        </div>
      </div>

      <!-- Anggota 3: Novia Farah Harwati -->
      <div class="tim-card">
        <div class="tim-card-header">
          <!-- Ganti placeholder dengan foto asli:
               <img src="assets/tim/novia.jpg" class="tim-avatar" alt="Novia Farah Harwati">
               Lalu hapus baris <div class="tim-avatar-placeholder"> di bawah ini. -->
          <div class="tim-avatar-placeholder">N</div>
        </div>
        <div class="p-5">
          <div class="font-bold text-navy-900 text-sm leading-snug">Novia Farah Harwati</div>
          <div class="tim-npm">24082010176</div>
          <div class="tim-meta"><span>📍</span> Surabaya</div>
          <div class="tim-meta"><span>🎮</span> Gaming</div>
          <div class="flex flex-wrap gap-1 mt-3">
            <span class="skill-badge">UI/UX</span>
            <span class="skill-badge">Editing</span>
            <span class="skill-badge">Java</span>
          </div>
        </div>
      </div>

      <!-- Anggota 4: Gratia Novelin Tamba -->
      <div class="tim-card">
        <div class="tim-card-header">
          <!-- Ganti placeholder dengan foto asli:
               <img src="assets/tim/gratia.jpg" class="tim-avatar" alt="Gratia Novelin Tamba">
               Lalu hapus baris <div class="tim-avatar-placeholder"> di bawah ini. -->
          <div class="tim-avatar-placeholder">G</div>
        </div>
        <div class="p-5">
          <div class="font-bold text-navy-900 text-sm leading-snug">Gratia Novelin Tamba</div>
          <div class="tim-npm">24082010178</div>
          <div class="tim-meta"><span>📍</span> Batam</div>
          <div class="tim-meta"><span>🎨</span> Design</div>
          <div class="flex flex-wrap gap-1 mt-3">
            <span class="skill-badge">UI/UX</span>
            <span class="skill-badge">MySQL</span>
            <span class="skill-badge">Java</span>
          </div>
        </div>
      </div>

    </div><!-- /grid tim -->

    <p class="text-center text-xs text-gray-400 mt-8">
      💡 Untuk menampilkan foto asli, taruh file di folder <code class="bg-gray-100 px-1 rounded">assets/tim/</code>
      lalu ganti <code class="bg-gray-100 px-1 rounded">div.tim-avatar-placeholder</code>
      dengan <code class="bg-gray-100 px-1 rounded">&lt;img class="tim-avatar" src="assets/tim/nama.jpg"&gt;</code>
    </p>
  </div>
</section>
<!-- ============================================================ -->
<!--  END SECTION TIM DEVELOPER                                   -->
<!-- ============================================================ -->

<!-- DAFTAR -->
<section id="daftar" class="py-24 bg-[#f0f7fb]">
  <div class="max-w-6xl mx-auto px-6 grid grid-cols-2 gap-16 items-start">
    <div>
      <div class="section-tag">Bergabung</div>
      <h2 class="font-serif text-4xl text-navy-900 mt-2">Daftar <span class="italic text-cyan-brand">Sekarang</span></h2>
      <p class="text-gray-600 mt-4 mb-8 leading-relaxed">Bergabung dengan ribuan pelanggan yang sudah menikmati kemudahan pesan air galon lewat Air Biru. Gratis, cepat, dan mudah.</p>
      <div class="space-y-4">
        <div class="flex items-center gap-4"><div class="w-10 h-10 rounded-full bg-cyan-brand/20 flex items-center justify-content-center text-cyan-brand font-bold flex items-center justify-center">1</div><div class="text-navy-900 font-semibold">Isi form pendaftaran</div></div>
        <div class="flex items-center gap-4"><div class="w-10 h-10 rounded-full bg-cyan-brand/20 flex items-center justify-center text-cyan-brand font-bold">2</div><div class="text-navy-900 font-semibold">Login ke dashboard</div></div>
        <div class="flex items-center gap-4"><div class="w-10 h-10 rounded-full bg-cyan-brand/20 flex items-center justify-center text-cyan-brand font-bold">3</div><div class="text-navy-900 font-semibold">Pesan galon pertama Anda!</div></div>
      </div>
    </div>

    <div class="bg-white rounded-3xl shadow-xl p-8 border border-gray-100">
      <?php if ($msg_daftar): ?>
        <div class="alert-<?= $msg_daftar['type'] ?>"><?= htmlspecialchars($msg_daftar['text']) ?></div>
      <?php endif; ?>

      <?php if (!$show_login || ($msg_daftar && $msg_daftar['type'] === 'error')): ?>
      <div class="flex gap-2 mb-6">
        <button id="btn-reg" class="reg-tab-btn active">📝 Daftar</button>
      </div>
      <form method="POST" action="#daftar">
        <input type="hidden" name="action" value="daftar">
        <div class="space-y-4 mb-6">
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Nama Lengkap</label>
            <input type="text" name="nama" class="input-style" placeholder="Nama lengkap Anda"
              value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>" required>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Email</label>
            <input type="email" name="email" class="input-style" placeholder="email@contoh.com"
              value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Nomor HP</label>
            <input type="tel" name="hp" class="input-style" placeholder="08xxxxxxxxxx"
              value="<?= isset($_POST['hp']) ? htmlspecialchars($_POST['hp']) : '' ?>" required>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Password</label>
            <div class="password-wrapper">
              <input type="password" id="pwd-daftar" name="password" class="input-style" placeholder="Minimal 6 karakter" required>
              <button type="button" class="pwd-toggle" onclick="togglePassword('pwd-daftar', this)" aria-label="Tampilkan password">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="display:none;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
              </button>
            </div>
          </div>
        </div>
        <button type="submit" class="btn-primary w-full">🚀 Daftar Sekarang</button>
      </form>
      <p class="text-center text-sm text-gray-500 mt-5">Sudah punya akun? <a href="#login" class="text-cyan-brand font-bold hover:underline">Login di sini</a></p>

      <?php else: ?>
      <div class="text-center py-4">
        <div class="text-5xl mb-4">🎉</div>
        <h3 class="font-bold text-navy-900 text-xl mb-2">Pendaftaran Berhasil!</h3>
        <p class="text-gray-500 text-sm mb-6">Akun Anda sudah dibuat. Silakan login untuk mulai memesan.</p>
        <a href="#login" class="btn-primary">Login Sekarang →</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ============================================================ -->
<!--  SECTION LOGIN                                               -->
<!-- ============================================================ -->
<section id="login" class="py-24 bg-navy-900">
  <div class="max-w-6xl mx-auto px-6 flex justify-center">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="text-5xl mb-3">💧</div>
        <h2 class="font-serif text-3xl text-white">Masuk ke <span class="italic text-cyan-brand">Air Biru</span></h2>
        <p class="text-cyan-light/60 text-sm mt-2">Selamat datang kembali</p>
      </div>

      <div class="bg-white rounded-3xl shadow-2xl p-8">

        <!-- TAB PILIH ROLE -->
        <div class="role-tabs-wrapper">
          <button type="button" class="role-tab active" id="tab-pelanggan" onclick="setLoginRole('pelanggan')">
            <span>👤</span> Pelanggan
          </button>
          <button type="button" class="role-tab" id="tab-admin" onclick="setLoginRole('admin')">
            <span>🛡️</span> Admin
          </button>
          <button type="button" class="role-tab" id="tab-driver" onclick="setLoginRole('driver')">
            <span>🚚</span> Driver
          </button>
        </div>
        <p class="text-xs text-gray-400 text-center -mt-2 mb-5" id="role-hint">
          Login sebagai <strong id="role-hint-text" class="text-navy-900">Pelanggan</strong>
        </p>
        <!-- END TAB ROLE -->

        <?php if ($msg_login): ?>
          <div class="alert-<?= $msg_login['type'] ?>"><?= htmlspecialchars($msg_login['text']) ?></div>
        <?php endif; ?>

        <form method="POST" action="#login" id="login-form">
          <input type="hidden" name="action" value="login">
          <!-- Hidden input role — divalidasi di backend -->
          <input type="hidden" name="login_role" id="login_role_input"
            value="<?= isset($_POST['login_role']) ? htmlspecialchars($_POST['login_role']) : 'pelanggan' ?>">

          <div class="mb-5">
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Email</label>
            <input type="email" name="email" class="input-style" placeholder="email@contoh.com"
              value="<?= ($show_login && isset($_POST['email'])) ? htmlspecialchars($_POST['email']) : '' ?>" required>
          </div>

          <div class="mb-7">
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Password</label>
            <div class="password-wrapper">
              <input type="password" id="pwd-login" name="password" class="input-style" placeholder="Masukkan password" required>
              <button type="button" class="pwd-toggle" onclick="togglePassword('pwd-login', this)" aria-label="Tampilkan password">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="display:none;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-primary w-full">Masuk →</button>
        </form>

        <p class="text-center mt-5 text-sm text-gray-500">Belum punya akun? <a href="#daftar" class="text-cyan-brand font-bold hover:underline">Daftar di sini</a></p>

        <!-- Demo hint -->
        <div class="mt-6 bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-800">
          <div class="font-bold mb-1">💡 Akun Demo:</div>
          <div>Admin: admin@airbiru.com / admin123 → pilih tab <strong>Admin</strong></div>
          <div>Driver: driver@airbiru.com / driver123 → pilih tab <strong>Driver</strong></div>
          <div>Pelanggan: daftar sendiri → pilih tab <strong>Pelanggan</strong></div>
        </div>

      </div><!-- /bg-white card -->
    </div>
  </div>
</section>
<!-- ============================================================ -->
<!--  END SECTION LOGIN                                           -->
<!-- ============================================================ -->

<!-- FOOTER -->
<footer class="bg-navy-950 py-10">
  <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-2 text-white font-bold"><span class="text-xl">💧</span><span>Air<span class="text-cyan-brand">Biru</span></span></div>
    <p class="text-white/40 text-xs">© 2025 Air Biru &middot; Kelompok 13 &middot; Sistem Informasi &middot; UPN Veteran Jawa Timur</p>
    <div class="flex gap-4">
      <a href="#tentang" class="text-white/40 hover:text-white text-xs">Tentang</a>
      <a href="#solusi"  class="text-white/40 hover:text-white text-xs">Fitur</a>
      <a href="#tim"     class="text-white/40 hover:text-white text-xs">Tim</a>
      <a href="#daftar"  class="text-white/40 hover:text-white text-xs">Daftar</a>
    </div>
  </div>
</footer>

<!-- Feature Modal -->
<div id="feature-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);align-items:center;justify-content:center;" onclick="closeFeatureModal(event,false)" class="flex">
  <div id="feature-modal-box" class="bg-navy-900 border border-white/10 rounded-3xl shadow-2xl p-8 max-w-lg w-full mx-4 relative">
    <button onclick="closeFeatureModal(null,true)" class="absolute top-4 right-4 text-white/40 hover:text-white text-xl font-bold">✕</button>
    <div id="feature-modal-content"></div>
    <a href="#daftar" onclick="closeFeatureModal(null,true)" class="btn-primary w-full text-center mt-6 block" style="text-decoration:none;">Daftar Sekarang →</a>
  </div>
</div>

<script>
/* ===================================================
   FEATURE MODAL
=================================================== */
var featureData = {
  lokasi:    { icon:'📍', title:'Pemesanan Berbasis Lokasi',  color:'#34b4c8', desc:'Sistem cerdas Air Biru secara otomatis mendeteksi depot terdekat berdasarkan alamat yang Anda daftarkan.', points:['🗺️ Deteksi otomatis depot terdekat dari alamat Anda','⚡ Pesanan langsung diteruskan ke depot terpilih','📏 Algoritma jarak memastikan waktu pengiriman minimum','🔄 Update depot otomatis jika Anda pindah alamat'] },
  lacak:     { icon:'🚚', title:'Lacak Pengiriman Real-Time', color:'#3b82f6', desc:'Pantau status pesanan Anda secara langsung mulai dari diproses hingga galon tiba di depan pintu.',          points:['📡 Update status: Diproses → Diantar → Selesai','🔔 Notifikasi otomatis di setiap perubahan status','👤 Lihat informasi driver yang mengantarkan','📋 Riwayat lengkap semua pesanan tersimpan'] },
  reminder:  { icon:'🔔', title:'Reminder Minum Air Harian',  color:'#f59e0b', desc:'Jaga kesehatan dengan pengingat hidrasi harian dan tracker konsumsi air yang terintegrasi di dashboard.',   points:['💧 Target 8 gelas per hari dengan pengingat otomatis','📊 Tracker visual konsumsi air harian Anda','🎯 Edukasi manfaat hidrasi yang cukup','⏰ Jadwal pengingat yang bisa disesuaikan'] },
  langganan: { icon:'🔄', title:'Langganan Otomatis',         color:'#10b981', desc:'Atur jadwal pengiriman rutin dan galon akan datang sendiri tanpa perlu pesan ulang setiap kali habis.',       points:['📅 Pilih jadwal mingguan atau dua mingguan','🤖 Pesanan dibuat otomatis sesuai jadwal','💡 Cukup aktifkan sekali, galon datang terus','✏️ Ubah atau batalkan langganan kapan saja'] },
  laporan:   { icon:'📋', title:'Form Laporan Masalah',       color:'#ef4444', desc:'Laporkan kendala pengiriman atau kualitas produk secara terstruktur langsung ke sistem admin.',               points:['📝 Kategori laporan yang jelas dan terorganisir','📸 Lampirkan deskripsi masalah secara detail','⏱️ Tim admin merespons dalam 1×24 jam','📂 Riwayat laporan tampil di dashboard Anda'] },
  riwayat:   { icon:'📜', title:'Riwayat Pesanan (CRUD)',     color:'#8b5cf6', desc:'Kelola semua data pesanan Anda dengan mudah — lihat, ubah, atau hapus riwayat transaksi kapan saja.',         points:['👁️ Lihat semua riwayat pesanan dalam satu tampilan','✏️ Edit detail pesanan yang masih diproses','🗑️ Hapus pesanan yang tidak diperlukan','🔍 Filter pesanan berdasarkan status atau tanggal'] }
};

function openFeatureModal(key) {
  var d = featureData[key]; if (!d) return;
  var html = '<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;"><div style="font-size:48px;">' + d.icon + '</div><div><div style="color:white;font-weight:700;font-size:1.2rem;">' + d.title + '</div><div style="color:' + d.color + ';font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;margin-top:4px;">Fitur Air Biru</div></div></div>';
  html += '<p style="color:rgba(168,218,220,0.7);font-size:0.875rem;line-height:1.6;margin-bottom:20px;">' + d.desc + '</p>';
  html += '<div style="display:flex;flex-direction:column;gap:8px;">';
  d.points.forEach(function(p) { html += '<div style="display:flex;align-items:flex-start;gap:8px;font-size:0.875rem;color:rgba(255,255,255,0.8);background:rgba(255,255,255,0.05);border-radius:12px;padding:10px 16px;">' + p + '</div>'; });
  html += '</div>';
  document.getElementById('feature-modal-content').innerHTML = html;
  document.getElementById('feature-modal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeFeatureModal(e, force) {
  if (!force && e && e.target !== document.getElementById('feature-modal')) return;
  document.getElementById('feature-modal').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeFeatureModal(null, true); });

/* ===================================================
   ROLE TAB SWITCHER
=================================================== */
var roleLabels = { pelanggan: 'Pelanggan', admin: 'Admin', driver: 'Driver' };

function setLoginRole(role) {
  document.querySelectorAll('.role-tab').forEach(function(btn) { btn.classList.remove('active'); });
  document.getElementById('tab-' + role).classList.add('active');
  document.getElementById('login_role_input').value = role;
  document.getElementById('role-hint-text').textContent = roleLabels[role];
}

// Saat halaman load, pastikan tab role sesuai dengan POST yang dikirim (jika ada error)
(function() {
  var savedRole = document.getElementById('login_role_input').value;
  if (savedRole && roleLabels[savedRole]) {
    setLoginRole(savedRole);
  }
})();

/* ===================================================
   PASSWORD TOGGLE (show / hide)
=================================================== */
function togglePassword(inputId, btn) {
  var input = document.getElementById(inputId);
  var isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';

  var icons = btn.querySelectorAll('svg');
  if (icons.length >= 2) {
    icons[0].style.display = isPassword ? 'none'  : 'block';
    icons[1].style.display = isPassword ? 'block' : 'none';
  }
  btn.setAttribute('aria-label', isPassword ? 'Sembunyikan password' : 'Tampilkan password');
}

/* ===================================================
   AUTO SCROLL ke login jika baru daftar berhasil
=================================================== */
<?php if ($show_login): ?>
window.addEventListener('load', function() { document.getElementById('login').scrollIntoView({ behavior: 'smooth' }); });
<?php endif; ?>
</script>
</body>
</html>
