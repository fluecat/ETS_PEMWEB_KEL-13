<?php
session_start();
if (!isset($_SESSION['user_id']))               { header("Location: index.php#login"); exit; }
if ($_SESSION['user_role'] === 'admin')         { header("Location: admin_dashboard.php"); exit; }
if ($_SESSION['user_role'] === 'driver')        { header("Location: driver_dashboard.php"); exit; }

include 'koneksi.php';

$uid       = (int)$_SESSION['user_id'];
$nama_user = htmlspecialchars($_SESSION['user_nama'] ?? '');
$msg       = null;
$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'order') ? 'order' : 'dashboard';

// ── helpers ──────────────────────────────────────────────────────────────────
function validasiPesanan(array $p): array {
    $required = ['nama'=>'Nama','telepon'=>'Nomor telepon','provinsi'=>'Provinsi',
                 'kota'=>'Kota','kecamatan'=>'Kecamatan','rt_rw'=>'RT/RW',
                 'kode_pos'=>'Kode pos','alamat'=>'Alamat lengkap'];
    $e = [];
    foreach ($required as $k => $l)
        if (empty(trim($p[$k] ?? ''))) $e[] = "$l harus diisi.";
    if (empty($p['produk'])) $e[] = "Produk harus dipilih.";
    return $e;
}

function produkKey(string $p): string {
    if (strpos($p, '19') !== false)        return '19L';
    if (strpos($p, '5 Liter') !== false)   return '5L';
    return 'other';
}

// ── POST handlers ─────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($action === 'tambah_pesanan') {
    $errs = validasiPesanan($_POST);
    if ($errs) {
        $msg = ['type'=>'error', 'text'=>implode(' ', $errs)];
    } else {
        $stmt = mysqli_prepare($koneksi,
            "INSERT INTO pesanan (user_id,nama,telepon,negara,provinsi,kota,kecamatan,
             rt_rw,kode_pos,alamat,deskripsi,produk,jumlah,jadwal,catatan)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $v = [
            $uid,
            trim($_POST['nama']),   trim($_POST['telepon']),  trim($_POST['negara']  ?? 'Indonesia'),
            trim($_POST['provinsi']),trim($_POST['kota']),    trim($_POST['kecamatan']),
            trim($_POST['rt_rw']),  trim($_POST['kode_pos']), trim($_POST['alamat']),
            trim($_POST['deskripsi'] ?? ''), trim($_POST['produk']),
            max(1, min(20, (int)($_POST['jumlah'] ?? 1))),
            trim($_POST['jadwal'] ?? 'Sekarang (1-2 jam)'),
            trim($_POST['catatan'] ?? ''),
        ];
        mysqli_stmt_bind_param($stmt, 'isssssssssssiss',
            $v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$v[6],$v[7],$v[8],$v[9],$v[10],$v[11],$v[12],$v[13],$v[14]);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = $ok
            ? ['type'=>'success', 'text'=>'Pesanan berhasil ditambahkan!']
            : ['type'=>'error',   'text'=>'Gagal menyimpan: ' . mysqli_error($koneksi)];
    }
    $active_tab = 'order';
}

if ($action === 'edit_pesanan') {
    $pid  = (int)($_POST['pesanan_id'] ?? 0);
    $errs = validasiPesanan($_POST);
    if (!$pid) {
        $msg = ['type'=>'error', 'text'=>'ID pesanan tidak valid.'];
    } elseif ($errs) {
        $msg = ['type'=>'error', 'text'=>implode(' ', $errs)];
    } else {
        $stmt = mysqli_prepare($koneksi,
            "UPDATE pesanan SET nama=?,telepon=?,negara=?,provinsi=?,kota=?,kecamatan=?,
             rt_rw=?,kode_pos=?,alamat=?,deskripsi=?,produk=?,jumlah=?,jadwal=?,catatan=?
             WHERE id=? AND user_id=?");
        $jml = max(1, min(20, (int)($_POST['jumlah'] ?? 1)));
        mysqli_stmt_bind_param($stmt, 'sssssssssssissii',
            trim($_POST['nama']),      trim($_POST['telepon']),  trim($_POST['negara']   ?? ''),
            trim($_POST['provinsi']),  trim($_POST['kota']),     trim($_POST['kecamatan']),
            trim($_POST['rt_rw']),     trim($_POST['kode_pos']), trim($_POST['alamat']),
            trim($_POST['deskripsi'] ?? ''), trim($_POST['produk']),
            $jml,
            trim($_POST['jadwal'] ?? ''), trim($_POST['catatan'] ?? ''),
            $pid, $uid);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = $ok
            ? ['type'=>'success', 'text'=>'Pesanan berhasil diperbarui!']
            : ['type'=>'error',   'text'=>'Gagal: ' . mysqli_error($koneksi)];
    }
    $active_tab = 'order';
}

if (isset($_GET['hapus']) && ctype_digit((string)$_GET['hapus'])) {
    $pid  = (int)$_GET['hapus'];
    $stmt = mysqli_prepare($koneksi, "DELETE FROM pesanan WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $pid, $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: dashboard.php?tab=order&msg=hapus"); exit;
}

if ($action === 'kirim_laporan') {
    $allowed_kat = ['Keterlambatan Pengiriman','Kualitas Air Bermasalah','Galon Bocor/Rusak',
                    'Driver Tidak Profesional','Pesanan Salah','Lainnya'];
    $v_kat  = trim($_POST['kategori']   ?? '');
    $v_nop  = trim($_POST['no_pesanan'] ?? '');
    $v_desk = trim($_POST['deskripsi']  ?? '');
    if (!in_array($v_kat, $allowed_kat)) {
        $msg = ['type'=>'error', 'text'=>'Kategori tidak valid!'];
    } elseif (!$v_desk) {
        $msg = ['type'=>'error', 'text'=>'Deskripsi wajib diisi!'];
    } else {
        $stmt = mysqli_prepare($koneksi,
            "INSERT INTO laporan (user_id,no_pesanan,kategori,deskripsi) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'isss', $uid, $v_nop, $v_kat, $v_desk);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = $ok
            ? ['type'=>'success', 'text'=>'Laporan berhasil dikirim!']
            : ['type'=>'error',   'text'=>'Gagal: ' . mysqli_error($koneksi)];
    }
    $active_tab = 'dashboard';
}

if (isset($_GET['msg']) && $_GET['msg'] === 'hapus')
    $msg = ['type'=>'success', 'text'=>'Pesanan berhasil dihapus!'];

// ── fetch data ────────────────────────────────────────────────────────────────
$r_pesanan    = mysqli_query($koneksi, "SELECT * FROM pesanan WHERE user_id=$uid ORDER BY tgl_pesan DESC");
$r_laporan    = mysqli_query($koneksi, "SELECT * FROM laporan WHERE user_id=$uid ORDER BY tgl_laporan DESC");
$total_p      = mysqli_num_rows($r_pesanan);
$total_l      = mysqli_num_rows($r_laporan);
$cnt_selesai  = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM pesanan WHERE user_id=$uid AND status='Selesai'"))['c'];
$cnt_proses   = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM pesanan WHERE user_id=$uid AND status!='Selesai'"))['c'];

$edit_pesanan = null;
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM pesanan WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $eid, $uid);
    mysqli_stmt_execute($stmt);
    $edit_pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($edit_pesanan) $active_tab = 'order';
}

$aktif_pesanan = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM pesanan WHERE user_id=$uid AND status!='Selesai' ORDER BY tgl_pesan DESC LIMIT 1"));

$sc_map = ['Diproses'=>'bg-orange-100 text-orange-700','Disiapkan'=>'bg-blue-100 text-blue-700',
           'Diantar'=>'bg-cyan-100 text-cyan-700','Selesai'=>'bg-green-100 text-green-700'];
$lc_map = ['Masuk'=>'bg-orange-100 text-orange-700','Diproses'=>'bg-blue-100 text-blue-700','Selesai'=>'bg-green-100 text-green-700'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Air Biru</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Plus Jakarta Sans','sans-serif'],serif:['Instrument Serif','serif']},colors:{navy:{950:'#03112e',900:'#0a2463',800:'#0d3180'},cyan:{brand:'#34b4c8',light:'#a8dadc',pale:'#e8f4f8'}}}}}</script>
  <style>
    .input-style{width:100%;padding:11px 16px;background:#f3f8fb;border:1.5px solid #c5dde6;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.92rem;color:#0a2463;outline:none;transition:border-color .2s;}
    .input-style:focus{border-color:#34b4c8;background:#fff;}
    .btn-primary{background:linear-gradient(135deg,#0a2463,#168aad);color:white;padding:11px 28px;border-radius:999px;font-weight:700;font-size:.92rem;border:none;cursor:pointer;text-decoration:none;display:inline-block;text-align:center;}
    .btn-primary:hover{opacity:.9;}
    .btn-danger{background:linear-gradient(135deg,#c53030,#e53e3e);color:white;padding:8px 16px;border-radius:999px;font-weight:700;font-size:.8rem;border:none;cursor:pointer;}
    .btn-secondary{background:#e8f4f8;color:#0a2463;padding:8px 16px;border-radius:999px;font-weight:700;font-size:.8rem;border:none;cursor:pointer;text-decoration:none;display:inline-block;}
    .section-tag{display:inline-block;background:rgba(52,180,200,.12);color:#34b4c8;font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:5px 14px;border-radius:999px;border:1px solid rgba(52,180,200,.3);}
    .tab-btn{padding:10px 28px;border-radius:999px;font-weight:700;font-size:.87rem;cursor:pointer;border:none;transition:all .2s;}
    .tab-btn.active{background:#0a2463;color:white;}
    .tab-btn:not(.active){background:#e8f4f8;color:#0a2463;}
    .tab-content{display:none;}.tab-content.active{display:block;}
    .alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.9rem;}
    .alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.9rem;}
    select.input-style{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%230a2463'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;background-size:16px;padding-right:36px;}
    .filter-chip{padding:6px 16px;border-radius:999px;font-weight:700;font-size:.78rem;cursor:pointer;border:1.5px solid #c5dde6;background:#f3f8fb;color:#0a2463;transition:all .2s;}
    .filter-chip.active{background:#0a2463;color:white;border-color:#0a2463;}
    .err-msg{display:none;color:#e53e3e;font-size:.75rem;margin-top:4px;font-weight:600;}
    .modal-overlay{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
    .modal-overlay.show{display:flex;}
    @keyframes modal-in{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
    .modal-box{animation:modal-in .22s ease both;}
    .card-hover{transition:transform .25s,box-shadow .25s;}
    .card-hover:hover{transform:translateY(-4px);box-shadow:0 16px 32px rgba(10,36,99,.1);}
    @keyframes pulse-ring{0%,100%{opacity:.6;transform:scale(1)}50%{opacity:1;transform:scale(1.04)}}
    .reminder-ring{animation:pulse-ring 2s infinite;}
  </style>
</head>
<body class="bg-[#f0f7fb] font-sans text-navy-900">

<!-- MODAL HAPUS -->
<div id="modal-hapus" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal-box bg-white rounded-3xl shadow-2xl p-8 max-w-sm w-full mx-4">
    <div class="text-center mb-6">
      <div class="text-5xl mb-3">🗑️</div>
      <h3 class="font-bold text-navy-900 text-xl mb-2">Hapus Pesanan Ini?</h3>
      <p class="text-gray-500 text-sm">Pesanan atas nama <b class="text-navy-900" id="modal-nama">-</b> akan dihapus permanen.</p>
    </div>
    <div class="flex gap-3">
      <button onclick="tutupModal()" class="flex-1 btn-secondary">Batal</button>
      <a id="modal-confirm-link" href="#" class="flex-1 btn-danger text-center" style="padding:10px 16px;border-radius:999px;text-decoration:none;font-weight:700;">Ya, Hapus</a>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<nav class="fixed top-0 w-full z-50 bg-navy-900/95 backdrop-blur-sm border-b border-white/10">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2 text-white font-bold text-lg">
      <span class="text-2xl">💧</span><span>Air<span class="text-cyan-brand">Biru</span></span>
      <span class="ml-2 text-xs bg-cyan-brand/20 text-cyan-brand font-bold px-3 py-1 rounded-full border border-cyan-brand/30">👤 Pelanggan</span>
    </div>
    <div class="flex items-center gap-2">
      <a href="myprofile.php" class="text-white/70 hover:text-white text-sm font-semibold px-3 py-2 rounded-lg transition-colors">👤 <?= $nama_user ?></a>
      <a href="logout.php" class="ml-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-4 py-2 rounded-full transition-colors">🚪 Logout</a>
    </div>
  </div>
</nav>

<div class="pt-20 max-w-6xl mx-auto px-6 pb-16">

  <div class="flex gap-2 mt-6 mb-8">
    <button id="tab-btn-dashboard" class="tab-btn" onclick="switchTab('dashboard')">📊 Dashboard</button>
    <button id="tab-btn-order"     class="tab-btn" onclick="switchTab('order')">🛒 Order Galon</button>
  </div>

  <?php if ($msg): ?>
  <div class="alert-<?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
  <?php endif; ?>

  <!-- ═══ TAB: DASHBOARD ═══ -->
  <div id="tab-dashboard" class="tab-content">
    <div class="mb-6">
      <div class="section-tag">Dashboard Pelanggan</div>
      <h2 class="font-serif text-3xl text-navy-900 mt-1">Halo, <span class="italic text-cyan-brand"><?= $nama_user ?></span>! 👋</h2>
      <p class="text-gray-500 text-sm mt-1">Pantau pesanan dan laporan Anda di sini.</p>
    </div>

    <div class="grid grid-cols-4 gap-5 mb-8">
      <div class="bg-white rounded-2xl p-6 border border-gray-100 card-hover text-center"><div class="text-3xl mb-2">📦</div><div class="text-2xl font-bold text-navy-900"><?= $total_p ?></div><div class="text-gray-500 text-xs mt-1">Total Pesanan</div></div>
      <div class="bg-white rounded-2xl p-6 border border-gray-100 card-hover text-center"><div class="text-3xl mb-2">✅</div><div class="text-2xl font-bold text-green-600"><?= $cnt_selesai ?></div><div class="text-gray-500 text-xs mt-1">Pesanan Selesai</div></div>
      <div class="bg-white rounded-2xl p-6 border border-gray-100 card-hover text-center"><div class="text-3xl mb-2">⏳</div><div class="text-2xl font-bold text-orange-500"><?= $cnt_proses ?></div><div class="text-gray-500 text-xs mt-1">Sedang Diproses</div></div>
      <div class="bg-white rounded-2xl p-6 border border-gray-100 card-hover text-center"><div class="text-3xl mb-2">📋</div><div class="text-2xl font-bold text-cyan-600"><?= $total_l ?></div><div class="text-gray-500 text-xs mt-1">Total Laporan</div></div>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-6">
      <!-- Water Reminder -->
      <div class="bg-navy-900 rounded-2xl p-6 text-white">
        <div class="text-cyan-brand font-bold text-xs uppercase tracking-widest mb-4 reminder-ring">💧 Reminder Minum Air</div>
        <div class="text-4xl font-bold mb-1" id="glass-count">0</div>
        <div class="text-cyan-light/60 text-xs mb-4">dari 8 gelas target harian</div>
        <div id="glass-track" class="flex flex-wrap gap-1 mb-4"></div>
        <div class="flex gap-2">
          <button onclick="addGlass()" class="flex-1 bg-cyan-brand/20 hover:bg-cyan-brand/30 text-cyan-brand text-xs font-bold py-2 rounded-xl transition-colors">+ Tambah Gelas</button>
          <button onclick="resetGlass()" class="bg-white/10 hover:bg-white/20 text-white/60 text-xs font-bold py-2 px-3 rounded-xl transition-colors">↺</button>
        </div>
        <div class="mt-4 pt-4 border-t border-white/10 text-xs text-cyan-light/50 leading-relaxed">💡 8 gelas/hari menjaga energi dan kesehatan ginjal.</div>
      </div>
      <!-- Quick Order -->
      <div class="col-span-2 bg-white rounded-2xl p-6 border border-gray-100">
        <div class="font-bold text-navy-900 text-base mb-4">⚡ Quick Order Galon</div>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <button onclick="setProduk('Galon 19 Liter - Rp 20.000')" class="bg-cyan-pale rounded-xl p-4 text-center border border-cyan-light/50 hover:border-cyan-brand transition-all card-hover">
            <div class="text-3xl mb-2">🫙</div><div class="font-bold text-navy-900 text-sm">Galon 19 Liter</div>
            <div class="text-cyan-brand font-bold text-sm mt-1">Rp 20.000</div><div class="text-gray-400 text-xs mt-1">Klik untuk pesan →</div>
          </button>
          <button onclick="setProduk('Galon 5 Liter - Rp 8.000')" class="bg-cyan-pale rounded-xl p-4 text-center border border-cyan-light/50 hover:border-cyan-brand transition-all card-hover">
            <div class="text-3xl mb-2">🧴</div><div class="font-bold text-navy-900 text-sm">Galon 5 Liter</div>
            <div class="text-cyan-brand font-bold text-sm mt-1">Rp 8.000</div><div class="text-gray-400 text-xs mt-1">Klik untuk pesan →</div>
          </button>
        </div>
        <button onclick="switchTab('order')" class="btn-primary w-full text-sm" style="padding:10px 20px;">🛒 Buka Form Order Lengkap</button>
      </div>
    </div>

    <!-- Kirim Laporan -->
    <div class="bg-white rounded-2xl p-7 border border-gray-100 mb-6">
      <div class="font-bold text-navy-900 text-base mb-5 flex items-center gap-2"><span class="text-xl">📋</span> Kirim Laporan Masalah</div>
      <form method="POST">
        <input type="hidden" name="action" value="kirim_laporan">
        <div class="grid grid-cols-2 gap-5 mb-5">
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Kategori Masalah</label>
            <select name="kategori" id="lap-kat" class="input-style">
              <option value="">-- Pilih Kategori --</option>
              <option>Keterlambatan Pengiriman</option>
              <option>Kualitas Air Bermasalah</option>
              <option>Galon Bocor/Rusak</option>
              <option>Driver Tidak Profesional</option>
              <option>Pesanan Salah</option>
              <option>Lainnya</option>
            </select>
            <div class="err-msg" id="err-lap-kat">Kategori harus dipilih!</div>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">No. Pesanan (opsional)</label>
            <input type="text" name="no_pesanan" class="input-style" placeholder="#0012" maxlength="20">
          </div>
        </div>
        <div class="mb-5">
          <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Deskripsi Masalah</label>
          <textarea name="deskripsi" id="lap-desk" rows="3" class="input-style resize-none" placeholder="Jelaskan masalah secara detail..." maxlength="1000"></textarea>
          <div class="err-msg" id="err-lap-desk">Deskripsi harus diisi!</div>
        </div>
        <button type="submit" onclick="return validasiLaporan()" class="btn-primary" style="padding:10px 24px;">📤 Kirim Laporan</button>
      </form>

      <div class="mt-8 border-t border-gray-100 pt-6">
        <div class="font-bold text-navy-900 text-sm mb-3">📄 Riwayat Laporan Saya</div>
        <div class="overflow-x-auto rounded-xl border border-gray-100">
          <table class="w-full text-sm">
            <thead><tr class="bg-cyan-pale">
              <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">ID</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Kategori</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Deskripsi</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Status</th>
            </tr></thead>
            <tbody>
              <?php mysqli_data_seek($r_laporan, 0);
              if (!mysqli_num_rows($r_laporan)): ?>
              <tr><td colspan="5" class="text-center py-8 text-gray-400 text-sm">Belum ada laporan.</td></tr>
              <?php else: while ($l = mysqli_fetch_assoc($r_laporan)):
                $lc = $lc_map[$l['status']] ?? 'bg-gray-100 text-gray-700'; ?>
              <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-3 font-bold text-xs">#<?= str_pad($l['id'],4,'0',STR_PAD_LEFT) ?></td>
                <td class="px-4 py-3 text-xs"><?= htmlspecialchars($l['kategori']) ?></td>
                <td class="px-4 py-3 text-xs text-gray-500" style="max-width:200px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= htmlspecialchars($l['deskripsi']) ?></td>
                <td class="px-4 py-3 text-xs text-gray-500"><?= date('d M Y', strtotime($l['tgl_laporan'])) ?></td>
                <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-full <?= $lc ?>"><?= htmlspecialchars($l['status']) ?></span></td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div><!-- /tab-dashboard -->

  <!-- ═══ TAB: ORDER ═══ -->
  <div id="tab-order" class="tab-content">
    <div class="mt-2 mb-6">
      <div class="section-tag">Pesan Sekarang</div>
      <h2 class="font-serif text-3xl text-navy-900 mt-1">Order <span class="italic text-cyan-brand">Galon</span></h2>
    </div>

    <!-- Tracking -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6">
      <div class="bg-navy-900 px-6 py-4 flex items-center justify-between">
        <span class="text-white font-bold">🚚 Status Pesanan Aktif Terakhir</span>
        <span class="text-cyan-light/60 text-xs">Pesanan terbaru yang belum selesai</span>
      </div>
      <div class="p-6">
        <?php if ($aktif_pesanan):
          $step_map = ['Diproses'=>1,'Disiapkan'=>2,'Diantar'=>3,'Selesai'=>4];
          $cur = $step_map[$aktif_pesanan['status']] ?? 1;
          $track_items = ['Dikonfirmasi'=>'✅','Disiapkan'=>'📦','Diantar'=>'🚚','Tiba'=>'🏠'];
          $idx = 0; ?>
        <div class="mb-4 flex items-center justify-between">
          <span class="font-semibold text-navy-900 text-sm">Pesanan #<?= str_pad($aktif_pesanan['id'],4,'0',STR_PAD_LEFT) ?> — <?= htmlspecialchars($aktif_pesanan['nama']) ?></span>
          <span class="text-xs font-bold px-3 py-1 rounded-full <?= $sc_map[$aktif_pesanan['status']]??'bg-gray-100 text-gray-700' ?>"><?= htmlspecialchars($aktif_pesanan['status']) ?></span>
        </div>
        <div class="flex items-center gap-3">
          <?php foreach ($track_items as $lbl => $ico): $idx++;
            $done = $cur >= $idx; ?>
          <div class="flex flex-col items-center gap-1">
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg <?= $done?'bg-green-500 text-white':'bg-gray-200 text-gray-400' ?> <?= $cur===$idx?'ring-2 ring-cyan-brand ring-offset-2':'' ?>"><?= $ico ?></div>
            <div class="text-xs font-bold <?= $done?'text-green-600':'text-gray-400' ?>"><?= $lbl ?></div>
          </div>
          <?php if ($idx < 4): ?>
          <div class="flex-1 h-2 rounded-full bg-gray-200 overflow-hidden">
            <div class="h-full rounded-full bg-green-400 transition-all" style="width:<?= $cur>$idx?'100%':'0%' ?>"></div>
          </div>
          <?php endif; endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-6 text-gray-400 text-sm">Tidak ada pesanan aktif. Buat pesanan baru di bawah!</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Pilih produk -->
    <div class="grid grid-cols-2 gap-6 mb-8">
      <div class="bg-white rounded-2xl p-6 border border-gray-100 text-center card-hover">
        <div class="text-5xl mb-3">🫙</div><div class="font-bold text-navy-900 text-lg">Galon 19 Liter</div>
        <div class="text-gray-500 text-sm mb-3">Cocok untuk rumah tangga & kantor</div>
        <div class="text-2xl font-bold text-cyan-brand mb-4">Rp 20.000</div>
        <button onclick="setProduk('Galon 19 Liter - Rp 20.000')" class="btn-primary text-sm" style="padding:9px 24px;">Pilih Ini ↓</button>
      </div>
      <div class="bg-white rounded-2xl p-6 border border-gray-100 text-center card-hover">
        <div class="text-5xl mb-3">🧴</div><div class="font-bold text-navy-900 text-lg">Galon 5 Liter</div>
        <div class="text-gray-500 text-sm mb-3">Praktis untuk personal & perjalanan</div>
        <div class="text-2xl font-bold text-cyan-brand mb-4">Rp 8.000</div>
        <button onclick="setProduk('Galon 5 Liter - Rp 8.000')" class="btn-primary text-sm" style="padding:9px 24px;">Pilih Ini ↓</button>
      </div>
    </div>

    <!-- Form Order -->
    <div class="bg-white rounded-2xl p-7 border border-gray-100 mb-8" id="form-order-wrap">
      <div class="font-bold text-navy-900 text-lg mb-5">
        <?= $edit_pesanan ? '✏️ Edit Pesanan #'.str_pad($edit_pesanan['id'],4,'0',STR_PAD_LEFT) : '➕ Tambah Pesanan Baru' ?>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $edit_pesanan ? 'edit_pesanan' : 'tambah_pesanan' ?>">
        <?php if ($edit_pesanan): ?><input type="hidden" name="pesanan_id" value="<?= (int)$edit_pesanan['id'] ?>"><?php endif; ?>

        <div class="grid grid-cols-2 gap-5 mb-5">
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Nama Lengkap</label>
            <input type="text" id="o-nama" name="nama" class="input-style" placeholder="Budi Santoso" maxlength="100"
              value="<?= htmlspecialchars($edit_pesanan['nama'] ?? '') ?>">
            <div class="err-msg" id="err-o-nama">Nama harus diisi!</div>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Nomor Telepon</label>
            <input type="tel" id="o-telp" name="telepon" class="input-style" placeholder="08123456789" maxlength="20"
              value="<?= htmlspecialchars($edit_pesanan['telepon'] ?? '') ?>">
            <div class="err-msg" id="err-o-telp">Nomor telepon harus diisi!</div>
          </div>
        </div>

        <div class="bg-cyan-pale rounded-xl p-5 mb-5 border border-cyan-light/40">
          <div class="font-bold text-navy-900 text-sm mb-4">📍 Alamat Pengiriman</div>
          <div class="grid grid-cols-2 gap-4 mb-4">
            <?php $addr_fields = [
              'negara'    => ['Negara','Indonesia'],
              'provinsi'  => ['Provinsi','Jawa Timur'],
              'kota'      => ['Kota / Kabupaten','Surabaya'],
              'kecamatan' => ['Kecamatan','Gubeng'],
              'rt_rw'     => ['RT / RW','RT 03 / RW 07'],
              'kode_pos'  => ['Kode Pos','60281'],
            ];
            foreach ($addr_fields as $fname => [$flabel, $fph]):
              $fval = htmlspecialchars($edit_pesanan[$fname] ?? ($fname==='negara'?'Indonesia':''));
            ?>
            <div>
              <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide"><?= $flabel ?></label>
              <input type="text" id="o-<?= $fname ?>" name="<?= $fname ?>" class="input-style" placeholder="<?= $fph ?>" maxlength="100" value="<?= $fval ?>">
              <div class="err-msg" id="err-o-<?= $fname ?>"><?= $flabel ?> harus diisi!</div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mb-4">
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Alamat Lengkap</label>
            <textarea id="o-alamat" name="alamat" rows="2" class="input-style resize-none" placeholder="Jl. Kertajaya No. 45..." maxlength="500"><?= htmlspecialchars($edit_pesanan['alamat'] ?? '') ?></textarea>
            <div class="err-msg" id="err-o-alamat">Alamat lengkap harus diisi!</div>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Deskripsi Tambahan</label>
            <input type="text" name="deskripsi" class="input-style" placeholder="Pagar hitam / lantai 2..." maxlength="255"
              value="<?= htmlspecialchars($edit_pesanan['deskripsi'] ?? '') ?>">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-5 mb-5">
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Pilih Produk</label>
            <select id="select-produk" name="produk" class="input-style">
              <option value="">-- Pilih Produk --</option>
              <option value="Galon 19 Liter - Rp 20.000" <?= isset($edit_pesanan['produk']) && strpos($edit_pesanan['produk'],'19')!==false ? 'selected' : '' ?>>Galon 19 Liter — Rp 20.000</option>
              <option value="Galon 5 Liter - Rp 8.000"  <?= isset($edit_pesanan['produk']) && strpos($edit_pesanan['produk'],'5 Liter')!==false ? 'selected' : '' ?>>Galon 5 Liter — Rp 8.000</option>
            </select>
            <div class="err-msg" id="err-o-produk">Produk harus dipilih!</div>
          </div>
          <div>
            <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Jumlah (unit)</label>
            <input type="number" name="jumlah" class="input-style" min="1" max="20" value="<?= (int)($edit_pesanan['jumlah'] ?? 1) ?>">
          </div>
        </div>

        <div class="mb-5">
          <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Jadwal Pengiriman</label>
          <select name="jadwal" class="input-style">
            <?php foreach ([
              'Sekarang (1-2 jam)'=>'⚡ Sekarang (estimasi 1–2 jam)',
              'Besok'=>'📅 Besok',
              'Langganan Mingguan'=>'🔄 Langganan Mingguan',
              'Langganan 2 Minggu Sekali'=>'🔄 Langganan 2 Minggu Sekali',
            ] as $jv => $jl): ?>
            <option value="<?= $jv ?>" <?= isset($edit_pesanan['jadwal'])&&$edit_pesanan['jadwal']===$jv?'selected':'' ?>><?= $jl ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-6">
          <label class="block text-xs font-bold text-navy-900 mb-2 uppercase tracking-wide">Catatan Tambahan</label>
          <textarea name="catatan" rows="2" class="input-style resize-none" placeholder="Taruh di depan pagar..." maxlength="500"><?= htmlspecialchars($edit_pesanan['catatan'] ?? '') ?></textarea>
        </div>

        <div class="flex gap-3">
          <button type="submit" onclick="return validasiOrder()" class="btn-primary" style="padding:11px 28px;">
            💾 <?= $edit_pesanan ? 'Simpan Perubahan' : 'Buat Pesanan' ?>
          </button>
          <?php if ($edit_pesanan): ?>
          <a href="dashboard.php?tab=order" class="btn-secondary" style="padding:11px 20px;">✕ Batal Edit</a>
          <?php else: ?>
          <button type="reset" class="btn-secondary">🔄 Reset</button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Riwayat Pesanan -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="bg-navy-900 px-6 py-4 flex items-center justify-between">
        <span class="text-white font-bold">📦 Riwayat Pesanan</span>
        <span class="text-cyan-light/60 text-xs"><?= $total_p ?> total</span>
      </div>
      <div class="p-4 border-b border-gray-100">
        <div class="flex flex-wrap gap-2 mb-2">
          <span class="text-xs font-bold text-navy-900/50 uppercase tracking-wide self-center mr-1">Status:</span>
          <?php foreach (['semua'=>'Semua','Diproses'=>'⏳ Diproses','Disiapkan'=>'📦 Disiapkan','Diantar'=>'🚚 Diantar','Selesai'=>'✅ Selesai'] as $v=>$l): ?>
          <button class="filter-chip <?= $v==='semua'?'active':'' ?>" data-filter="status" data-value="<?= $v ?>" onclick="setFilter(this,'status')"><?= $l ?></button>
          <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap gap-2 mb-3">
          <span class="text-xs font-bold text-navy-900/50 uppercase tracking-wide self-center mr-1">Produk:</span>
          <?php foreach (['semua'=>'Semua','19L'=>'🫙 19 Liter','5L'=>'🧴 5 Liter'] as $v=>$l): ?>
          <button class="filter-chip <?= $v==='semua'?'active':'' ?>" data-filter="produk" data-value="<?= $v ?>" onclick="setFilter(this,'produk')"><?= $l ?></button>
          <?php endforeach; ?>
        </div>
        <input type="text" id="search-pesanan" class="input-style" placeholder="🔍 Cari nama, produk, kota..." onkeyup="filterTabel()" style="max-width:320px;">
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-cyan-pale">
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">No</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Nama</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Produk</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Jml</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Kota</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Jadwal</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Aksi</th>
          </tr></thead>
          <tbody>
            <?php mysqli_data_seek($r_pesanan, 0);
            if (!mysqli_num_rows($r_pesanan)): ?>
            <tr><td colspan="8" class="text-center py-10 text-gray-400">Belum ada pesanan. Buat pesanan baru di atas!</td></tr>
            <?php else: $no = 1; while ($p = mysqli_fetch_assoc($r_pesanan)):
              $sc = $sc_map[$p['status']] ?? 'bg-gray-100 text-gray-700';
              $pk = produkKey($p['produk']);
              $bg = $no % 2 === 0 ? 'bg-gray-50' : '';
            ?>
            <tr class="<?= $bg ?> pesanan-row border-t border-gray-50"
                data-status="<?= htmlspecialchars($p['status']) ?>"
                data-produk="<?= $pk ?>"
                data-search="<?= htmlspecialchars(strtolower($p['nama'].' '.$p['produk'].' '.$p['kota'])) ?>">
              <td class="px-4 py-3 font-bold text-xs">#<?= str_pad($p['id'],4,'0',STR_PAD_LEFT) ?></td>
              <td class="px-4 py-3 font-medium"><?= htmlspecialchars($p['nama']) ?></td>
              <td class="px-4 py-3 text-xs"><?= htmlspecialchars($p['produk']) ?></td>
              <td class="px-4 py-3 text-center"><?= (int)$p['jumlah'] ?></td>
              <td class="px-4 py-3 text-xs"><?= htmlspecialchars($p['kota']) ?></td>
              <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($p['jadwal']) ?></td>
              <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-full <?= $sc ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td class="px-4 py-3 whitespace-nowrap">
                <a href="detail_pesanan.php?id=<?= (int)$p['id'] ?>" class="btn-secondary text-xs" style="padding:5px 10px;">👁️</a>
                <a href="dashboard.php?tab=order&edit=<?= (int)$p['id'] ?>" class="btn-secondary text-xs ml-1" style="padding:5px 10px;">✏️</a>
                <button onclick="bukaModal(<?= (int)$p['id'] ?>,'<?= addslashes(htmlspecialchars($p['nama'])) ?>')"
                        class="btn-danger text-xs ml-1" style="padding:5px 10px;">🗑️</button>
              </td>
            </tr>
            <?php $no++; endwhile; endif; ?>
          </tbody>
        </table>
        <div id="no-result" class="hidden text-center py-8 text-gray-400 text-sm">Tidak ada data yang sesuai.</div>
      </div>
    </div>
  </div><!-- /tab-order -->

</div><!-- /container -->

<script>
function switchTab(tab){
  document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(el=>el.classList.remove('active'));
  var tc=document.getElementById('tab-'+tab),tb=document.getElementById('tab-btn-'+tab);
  if(tc)tc.classList.add('active');if(tb)tb.classList.add('active');
}
switchTab('<?= $active_tab ?>');

var fStatus='semua',fProduk='semua';
function setFilter(btn,type){
  document.querySelectorAll('.filter-chip[data-filter="'+type+'"]').forEach(c=>c.classList.remove('active'));
  btn.classList.add('active');
  if(type==='status')fStatus=btn.dataset.value;else fProduk=btn.dataset.value;
  filterTabel();
}
function filterTabel(){
  var q=document.getElementById('search-pesanan').value.toLowerCase();
  var rows=document.querySelectorAll('.pesanan-row'),shown=0;
  rows.forEach(function(r){
    var ok=(fStatus==='semua'||r.dataset.status===fStatus)&&(fProduk==='semua'||r.dataset.produk===fProduk)&&(!q||r.dataset.search.includes(q));
    r.style.display=ok?'':'none';if(ok)shown++;
  });
  document.getElementById('no-result').classList.toggle('hidden',shown>0);
}
function bukaModal(id,nama){
  document.getElementById('modal-nama').textContent=nama;
  document.getElementById('modal-confirm-link').href='dashboard.php?hapus='+id+'&tab=order';
  document.getElementById('modal-hapus').classList.add('show');
}
function tutupModal(){document.getElementById('modal-hapus').classList.remove('show');}
document.getElementById('modal-hapus').addEventListener('click',function(e){if(e.target===this)tutupModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')tutupModal();});
function validasiOrder(){
  var ids=['o-nama','o-telp','o-negara','o-provinsi','o-kota','o-kecamatan','o-rt_rw','o-kode_pos','o-alamat'];
  var valid=true;
  document.querySelectorAll('[id^="err-o-"]').forEach(el=>el.style.display='none');
  ids.forEach(function(id){
    var el=document.getElementById(id);
    if(el&&!el.value.trim()){var er=document.getElementById('err-'+id);if(er)er.style.display='block';valid=false;}
  });
  if(!document.getElementById('select-produk').value){document.getElementById('err-o-produk').style.display='block';valid=false;}
  if(!valid)document.getElementById('form-order-wrap').scrollIntoView({behavior:'smooth'});
  return valid;
}
function validasiLaporan(){
  var v=true;
  document.getElementById('err-lap-kat').style.display='none';
  document.getElementById('err-lap-desk').style.display='none';
  if(!document.getElementById('lap-kat').value){document.getElementById('err-lap-kat').style.display='block';v=false;}
  if(!document.getElementById('lap-desk').value.trim()){document.getElementById('err-lap-desk').style.display='block';v=false;}
  return v;
}
function setProduk(val){
  document.getElementById('select-produk').value=val;
  switchTab('order');
  setTimeout(function(){document.getElementById('form-order-wrap').scrollIntoView({behavior:'smooth'});},100);
}
var gl=parseInt(localStorage.getItem('airbiru_gl')||'0');
function renderGl(){
  var t=document.getElementById('glass-track');if(!t)return;
  t.innerHTML='';for(var i=0;i<8;i++)t.innerHTML+='<span style="font-size:1.3rem">'+(i<gl?'🥛':'⬜')+'</span>';
  document.getElementById('glass-count').textContent=gl;
}
function addGlass(){if(gl<8){gl++;localStorage.setItem('airbiru_gl',gl);renderGl();}}
function resetGlass(){gl=0;localStorage.setItem('airbiru_gl',0);renderGl();}
renderGl();
</script>
<?php mysqli_close($koneksi); ?>
</body>
</html>