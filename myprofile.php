<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - Air Biru</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans:['Plus Jakarta Sans','sans-serif'], serif:['Instrument Serif','serif'] },
          colors: { navy:{950:'#03112e',900:'#0a2463',800:'#0d3180'}, cyan:{brand:'#34b4c8',light:'#a8dadc',pale:'#e8f4f8'} }
        }
      }
    }
  </script>
  <style>
    .input-style{width:100%;padding:11px 16px;background:#f3f8fb;border:1.5px solid #c5dde6;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:0.92rem;color:#0a2463;outline:none;transition:border-color 0.2s;}
    .input-style:focus{border-color:#34b4c8;background:#fff;}
    .btn-primary{background:linear-gradient(135deg,#0a2463,#168aad);color:white;padding:11px 28px;border-radius:999px;font-weight:700;font-size:0.92rem;border:none;cursor:pointer;transition:opacity 0.2s;width:100%;text-align:center;}
    .btn-secondary{background:#e8f4f8;color:#0a2463;padding:11px 28px;border-radius:999px;font-weight:700;font-size:0.92rem;border:none;cursor:pointer;}
    .section-tag{display:inline-block;background:rgba(52,180,200,0.12);color:#34b4c8;font-size:0.75rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:5px 14px;border-radius:999px;border:1px solid rgba(52,180,200,0.3);}
    .alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:0.9rem;}
    .alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:0.9rem;}
  </style>
</head>
<body class="bg-[#f0f7fb] font-sans text-navy-900">

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php#login");
    exit;
}
include 'koneksi.php';

$uid = (int)$_SESSION['user_id'];
$msg = "";

// =============================================
// UPDATE PROFIL
// =============================================
if (isset($_POST['action']) && $_POST['action'] === 'update_profil') {
    $nama  = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $email = mysqli_real_escape_string($koneksi, trim($_POST['email']));
    $hp    = mysqli_real_escape_string($koneksi, trim($_POST['hp']));

    if (!$nama || !$email || !$hp) {
        $msg = ['type'=>'error','text'=>'Semua field harus diisi!'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = ['type'=>'error','text'=>'Format email tidak valid!'];
    } else {
        // Cek email duplikat (kecuali milik sendiri)
        $cek = mysqli_query($koneksi, "SELECT id FROM users WHERE email='$email' AND id!=$uid");
        if (mysqli_num_rows($cek) > 0) {
            $msg = ['type'=>'error','text'=>'Email sudah dipakai akun lain!'];
        } else {
            $q = "UPDATE users SET nama='$nama', email='$email', no_hp='$hp' WHERE id=$uid";
            if (mysqli_query($koneksi, $q)) {
                $_SESSION['user_nama']  = $nama;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_hp']    = $hp;
                $msg = ['type'=>'success','text'=>'Profil berhasil diperbarui!'];
            } else {
                $msg = ['type'=>'error','text'=>'Gagal: '.mysqli_error($koneksi)];
            }
        }
    }
}

// Ambil data user terbaru
$r = mysqli_query($koneksi, "SELECT * FROM users WHERE id=$uid");
$user = mysqli_fetch_assoc($r);
?>

<!-- NAVBAR -->
<nav class="fixed top-0 w-full z-50 bg-navy-900/95 backdrop-blur-sm border-b border-white/10">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2 text-white font-bold text-lg">
      <span class="text-2xl">💧</span>
      <span>Air<span class="text-cyan-brand">Biru</span></span>
    </div>
    <div class="flex items-center gap-3">
      <a href="index.php"     class="text-white/70 hover:text-white text-sm font-semibold px-3 py-2 rounded-lg transition-colors">🏠 Beranda</a>
      <a href="dashboard.php" class="text-white/70 hover:text-white text-sm font-semibold px-3 py-2 rounded-lg transition-colors">← Dashboard</a>
      <a href="logout.php"    class="ml-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-4 py-2 rounded-full transition-colors">🚪 Logout</a>
    </div>
  </div>
</nav>

<div class="pt-24 max-w-2xl mx-auto px-6 pb-16">
  <div class="text-center mb-8">
    <div class="section-tag">Akun Saya</div>
    <h2 class="font-serif text-3xl text-navy-900 mt-2">My <span class="italic text-cyan-brand">Profile</span></h2>
  </div>

  <?php if ($msg): ?>
  <div class="alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
  <?php endif; ?>

  <div class="bg-white rounded-3xl shadow-xl p-10 border border-gray-100">

    <!-- Avatar + Nama -->
    <div class="flex flex-col items-center mb-8">
      <div class="w-20 h-20 rounded-full bg-navy-900 flex items-center justify-center text-3xl mb-3 border-4 border-cyan-brand">👤</div>
      <div class="font-bold text-navy-900 text-xl"><?= htmlspecialchars($user['nama']) ?></div>
      <div class="text-cyan-brand text-xs font-semibold mt-1 capitalize"><?= $user['role'] ?></div>
    </div>

    <!-- Mode View / Edit -->
    <div id="view-mode">
      <div class="space-y-4 mb-6">
        <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/40">
          <div class="text-xs font-bold text-navy-900/60 uppercase tracking-wide mb-1">Nama Lengkap</div>
          <div class="text-navy-900 font-semibold"><?= htmlspecialchars($user['nama']) ?></div>
        </div>
        <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/40">
          <div class="text-xs font-bold text-navy-900/60 uppercase tracking-wide mb-1">Email</div>
          <div class="text-navy-900 font-semibold"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/40">
          <div class="text-xs font-bold text-navy-900/60 uppercase tracking-wide mb-1">Nomor HP</div>
          <div class="text-navy-900 font-semibold"><?= htmlspecialchars($user['no_hp']) ?></div>
        </div>
        <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/40">
          <div class="text-xs font-bold text-navy-900/60 uppercase tracking-wide mb-1">Bergabung Sejak</div>
          <div class="text-navy-900 font-semibold"><?= date('d F Y', strtotime($user['tgl_daftar'])) ?></div>
        </div>
      </div>
      <button onclick="toggleEdit(true)" class="btn-primary">✏️ Edit Profil</button>
    </div>

    <!-- Edit Mode -->
    <div id="edit-mode" class="hidden">
      <form method="POST">
        <input type="hidden" name="action" value="update_profil">
        <div class="space-y-4 mb-6">
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Nama Lengkap</label>
            <input type="text" name="nama" class="input-style" value="<?= htmlspecialchars($user['nama']) ?>" required>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Email</label>
            <input type="email" name="email" class="input-style" value="<?= htmlspecialchars($user['email']) ?>" required>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Nomor HP</label>
            <input type="tel" name="hp" class="input-style" value="<?= htmlspecialchars($user['no_hp']) ?>" required>
          </div>
        </div>
        <div class="flex gap-3">
          <button type="submit" class="btn-primary">💾 Simpan Perubahan</button>
          <button type="button" onclick="toggleEdit(false)" class="btn-secondary">Batal</button>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
function toggleEdit(show) {
  document.getElementById('view-mode').classList.toggle('hidden', show);
  document.getElementById('edit-mode').classList.toggle('hidden', !show);
}
<?php if ($msg && $msg['type']==='error'): ?>
toggleEdit(true); // Tetap di edit mode kalau ada error
<?php endif; ?>
</script>

<?php mysqli_close($koneksi); ?>
</body>
</html>
