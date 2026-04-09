<?php
session_start();
if (!isset($_SESSION['user_id']))          { header("Location: index.php#login"); exit; }
if ($_SESSION['user_role'] !== 'admin')    { header("Location: dashboard.php"); exit; }

include 'koneksi.php';

$uid       = (int)$_SESSION['user_id'];
$nama_user = htmlspecialchars($_SESSION['user_nama'] ?? '');
$msg       = null;
$adm_tab   = (isset($_GET['tab']) && in_array($_GET['tab'],['depot','pesanan','laporan','driver']))
             ? $_GET['tab'] : 'depot';

// ── POST handlers ─────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

// Update status pesanan (admin bebas)
if ($action === 'update_status') {
    $pid     = (int)($_POST['pesanan_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $allowed = ['Diproses','Disiapkan','Diantar','Selesai'];
    if ($pid && in_array($status, $allowed)) {
        $st   = mysqli_real_escape_string($koneksi, $status);
        mysqli_query($koneksi, "UPDATE pesanan SET status='$st' WHERE id=$pid");
        $msg = ['type'=>'success', 'text'=>'✅ Status pesanan diperbarui ke "'.$status.'"!'];
    }
    $adm_tab = 'pesanan';
}

// Assign driver ke pesanan
if ($action === 'assign_driver') {
    $pid       = (int)($_POST['pesanan_id']  ?? 0);
    $driver_id = (int)($_POST['driver_id']   ?? 0);
    if ($pid) {
        if ($driver_id > 0) {
            // Pastikan driver_id valid dan rolenya driver
            $chk = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT id FROM users WHERE id=$driver_id AND role='driver'"));
            if ($chk) {
                mysqli_query($koneksi, "UPDATE pesanan SET driver_id=$driver_id WHERE id=$pid");
                $msg = ['type'=>'success', 'text'=>'✅ Driver berhasil ditugaskan!'];
            } else {
                $msg = ['type'=>'error', 'text'=>'Driver tidak ditemukan!'];
            }
        } else {
            // driver_id = 0 berarti lepas assignment
            mysqli_query($koneksi, "UPDATE pesanan SET driver_id=NULL WHERE id=$pid");
            $msg = ['type'=>'success', 'text'=>'Driver dilepas dari pesanan ini.'];
        }
    }
    $adm_tab = 'pesanan';
}

// Update status laporan
if ($action === 'update_laporan') {
    $lid     = (int)($_POST['laporan_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $allowed = ['Masuk','Diproses','Selesai'];
    if ($lid && in_array($status, $allowed)) {
        $st = mysqli_real_escape_string($koneksi, $status);
        mysqli_query($koneksi, "UPDATE laporan SET status='$st' WHERE id=$lid");
        $msg = ['type'=>'success', 'text'=>'✅ Status laporan diperbarui!'];
    }
    $adm_tab = 'laporan';
}

// ── Fetch stats ───────────────────────────────────────────────────────────────
$stat_pesanan   = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM pesanan"))['c'];
$stat_selesai   = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM pesanan WHERE status='Selesai'"))['c'];
$stat_driver    = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM users WHERE role='driver'"))['c'];
$stat_laporan   = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM laporan WHERE status='Masuk'"))['c'];
$stat_pelanggan = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM users WHERE role='pelanggan'"))['c'];

// Semua pesanan + info driver
$r_pesanan = mysqli_query($koneksi,
    "SELECT p.*, u.nama AS u_nama, d.nama AS d_nama
     FROM pesanan p
     JOIN users u ON p.user_id = u.id
     LEFT JOIN users d ON p.driver_id = d.id AND d.role = 'driver'
     ORDER BY p.tgl_pesan DESC");

$r_laporan = mysqli_query($koneksi,
    "SELECT l.*, u.nama AS u_nama
     FROM laporan l
     JOIN users u ON l.user_id = u.id
     ORDER BY l.tgl_laporan DESC");

// Daftar semua driver (untuk dropdown assign)
$r_drivers = mysqli_query($koneksi,
    "SELECT id, nama, no_hp FROM users WHERE role='driver' ORDER BY nama ASC");
$drivers_list = [];
while ($d = mysqli_fetch_assoc($r_drivers)) $drivers_list[] = $d;

$sc_map = ['Diproses'=>'bg-orange-100 text-orange-700','Disiapkan'=>'bg-blue-100 text-blue-700',
           'Diantar'=>'bg-cyan-100 text-cyan-700','Selesai'=>'bg-green-100 text-green-700'];
$lc_map = ['Masuk'=>'bg-orange-100 text-orange-700','Diproses'=>'bg-blue-100 text-blue-700','Selesai'=>'bg-green-100 text-green-700'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — Air Biru</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Plus Jakarta Sans','sans-serif'],serif:['Instrument Serif','serif']},colors:{navy:{950:'#03112e',900:'#0a2463',800:'#0d3180'},cyan:{brand:'#34b4c8',light:'#a8dadc',pale:'#e8f4f8'}}}}}</script>
  <style>
    .input-style{width:100%;padding:9px 14px;background:#f3f8fb;border:1.5px solid #c5dde6;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;color:#0a2463;outline:none;transition:border-color .2s;}
    .input-style:focus{border-color:#34b4c8;background:#fff;}
    select.input-style{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%230a2463'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;background-size:14px;padding-right:30px;}
    .btn-primary{background:linear-gradient(135deg,#0a2463,#168aad);color:white;padding:8px 18px;border-radius:999px;font-weight:700;font-size:.82rem;border:none;cursor:pointer;text-decoration:none;display:inline-block;}
    .btn-primary:hover{opacity:.9;}
    .btn-sm{padding:5px 12px;border-radius:999px;font-weight:700;font-size:.75rem;border:none;cursor:pointer;}
    .btn-danger{background:linear-gradient(135deg,#c53030,#e53e3e);color:white;}
    .btn-success{background:linear-gradient(135deg,#276749,#38a169);color:white;}
    .btn-secondary{background:#e8f4f8;color:#0a2463;}
    .adm-tab-btn{padding:8px 18px;border-radius:999px;font-weight:700;font-size:.82rem;cursor:pointer;border:none;transition:all .2s;}
    .adm-tab-btn.active{background:#0a2463;color:white;}
    .adm-tab-btn:not(.active){background:#e8f4f8;color:#0a2463;}
    .adm-tab-content{display:none;}.adm-tab-content.active{display:block;}
    .alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.9rem;}
    .alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.9rem;}
    .section-tag{display:inline-block;background:rgba(52,180,200,.12);color:#34b4c8;font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:5px 14px;border-radius:999px;border:1px solid rgba(52,180,200,.3);}
    .pill-role{display:inline-block;border-radius:999px;font-size:.7rem;font-weight:700;padding:3px 12px;}
    .card-hover{transition:transform .2s,box-shadow .2s;}
    .card-hover:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(10,36,99,.1);}
    td,th{vertical-align:middle;}
  </style>
</head>
<body class="bg-[#f0f7fb] font-sans text-navy-900">

<!-- NAVBAR -->
<nav class="fixed top-0 w-full z-50 bg-navy-900/95 backdrop-blur-sm border-b border-white/10">
  <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2 text-white font-bold text-lg">
      <span class="text-2xl">💧</span><span>Air<span class="text-cyan-brand">Biru</span></span>
      <span class="pill-role bg-yellow-400 text-navy-900 ml-2">🛡️ Admin</span>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <span class="text-white/60 text-sm hidden md:block">Halo, <?= $nama_user ?></span>
      <button onclick="showAdmTab('depot')"   id="btn-depot"   class="adm-tab-btn active">🏪 Depot</button>
      <button onclick="showAdmTab('pesanan')" id="btn-pesanan" class="adm-tab-btn">🛒 Pesanan</button>
      <button onclick="showAdmTab('laporan')" id="btn-laporan" class="adm-tab-btn">📋 Laporan</button>
      <button onclick="showAdmTab('driver')"  id="btn-driver"  class="adm-tab-btn">🚚 Driver</button>
      <a href="logout.php" class="ml-2 btn-sm btn-danger" style="text-decoration:none;border-radius:999px;padding:8px 16px;">🚪 Logout</a>
    </div>
  </div>
</nav>

<div class="pt-20 max-w-7xl mx-auto px-6 pb-16">

  <?php if ($msg): ?>
  <div class="alert-<?= $msg['type'] ?> mt-4"><?= htmlspecialchars($msg['text']) ?></div>
  <?php endif; ?>

  <!-- ═══ TAB: DEPOT ═══ -->
  <div id="adm-depot" class="adm-tab-content active">
    <div class="mb-8 mt-6">
      <div class="section-tag">Admin Panel</div>
      <h2 class="font-serif text-3xl text-navy-900 mt-1">Dashboard <span class="italic text-cyan-brand">Admin</span></h2>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-5 gap-4 mb-8">
      <?php $stats=[
        ['📦',$stat_pesanan,'Total Pesanan','text-navy-900'],
        ['✅',$stat_selesai,'Selesai','text-green-600'],
        ['🚚',$stat_driver,'Driver','text-blue-600'],
        ['📋',$stat_laporan,'Laporan Baru','text-orange-500'],
        ['👥',$stat_pelanggan,'Pelanggan','text-cyan-600'],
      ];
      foreach($stats as [$ico,$val,$lbl,$clr]): ?>
      <div class="bg-white rounded-2xl p-5 border border-gray-100 card-hover text-center">
        <div class="text-3xl mb-2"><?= $ico ?></div>
        <div class="text-2xl font-bold <?= $clr ?>"><?= $val ?></div>
        <div class="text-gray-500 text-xs mt-1"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-2 gap-6">
      <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
        <div class="flex items-center gap-4 mb-6">
          <div class="w-16 h-16 rounded-full bg-navy-900 flex items-center justify-center text-2xl border-4 border-cyan-brand">🏪</div>
          <div>
            <div class="font-bold text-navy-900 text-xl">Depot Air Biru Pusat</div>
            <div class="text-cyan-brand text-xs font-semibold">Depot Resmi</div>
          </div>
        </div>
        <div class="space-y-3">
          <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/40"><div class="text-xs font-bold text-navy-900/60 uppercase mb-1">📍 Alamat</div><div class="text-navy-900 font-semibold text-sm">Jl. Raya Darmo No. 12, Surabaya</div></div>
          <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/40"><div class="text-xs font-bold text-navy-900/60 uppercase mb-1">📞 No. HP</div><div class="text-navy-900 font-semibold text-sm">031-5678901</div></div>
          <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/40"><div class="text-xs font-bold text-navy-900/60 uppercase mb-1">✉️ Email</div><div class="text-navy-900 font-semibold text-sm">depot@airbiru.com</div></div>
        </div>
        <div class="mt-5 p-4 bg-green-50 rounded-xl border border-green-200">
          <div class="text-green-700 font-bold text-sm">🟢 Depot Sedang BUKA — Menerima Pesanan</div>
        </div>
      </div>
      <div class="bg-navy-900 rounded-3xl p-8 text-white">
        <div class="text-cyan-brand font-bold text-sm uppercase tracking-widest mb-6">Statistik Sistem</div>
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-white/10 rounded-2xl p-4 text-center"><div class="text-3xl font-bold text-white"><?= $stat_pesanan ?></div><div class="text-cyan-light/60 text-xs mt-1">Total Pesanan</div></div>
          <div class="bg-white/10 rounded-2xl p-4 text-center"><div class="text-3xl font-bold text-green-400"><?= $stat_driver ?></div><div class="text-cyan-light/60 text-xs mt-1">Driver Aktif</div></div>
          <div class="bg-white/10 rounded-2xl p-4 text-center"><div class="text-3xl font-bold text-cyan-brand"><?= $stat_pelanggan ?></div><div class="text-cyan-light/60 text-xs mt-1">Pelanggan</div></div>
          <div class="bg-white/10 rounded-2xl p-4 text-center"><div class="text-3xl font-bold text-yellow-400"><?= $stat_laporan ?></div><div class="text-cyan-light/60 text-xs mt-1">Laporan Baru</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ TAB: PESANAN ═══ -->
  <div id="adm-pesanan" class="adm-tab-content">
    <div class="mb-6 mt-6">
      <div class="section-tag">Manajemen Pesanan</div>
      <h2 class="font-serif text-3xl text-navy-900 mt-1">Pesanan <span class="italic text-cyan-brand">Masuk</span></h2>
      <p class="text-gray-500 text-sm mt-1">Perbarui status dan tugaskan driver ke setiap pesanan.</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="bg-navy-900 px-6 py-4 flex items-center justify-between">
        <span class="text-white font-bold">🛒 Daftar Semua Pesanan</span>
        <span class="text-cyan-light/60 text-xs"><?= $stat_pesanan ?> pesanan total</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-cyan-pale">
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">No</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">Pelanggan</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">Produk</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">Jml</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">Kota</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">Status</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">Driver</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">Ubah Status</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-navy-900 uppercase">Assign Driver</th>
          </tr></thead>
          <tbody>
            <?php
            if (!mysqli_num_rows($r_pesanan)): ?>
            <tr><td colspan="9" class="text-center py-10 text-gray-400">Belum ada pesanan.</td></tr>
            <?php else: $no=1; while ($p = mysqli_fetch_assoc($r_pesanan)):
              $sc = $sc_map[$p['status']] ?? 'bg-gray-100 text-gray-700';
              $bg = $no%2===0?'bg-gray-50':'';
            ?>
            <tr class="<?= $bg ?> border-t border-gray-50 hover:bg-cyan-pale/30">
              <td class="px-3 py-3 font-bold text-xs">#<?= str_pad($p['id'],4,'0',STR_PAD_LEFT) ?></td>
              <td class="px-3 py-3">
                <div class="font-medium text-sm"><?= htmlspecialchars($p['nama']) ?></div>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($p['u_nama']) ?></div>
              </td>
              <td class="px-3 py-3 text-xs"><?= htmlspecialchars($p['produk']) ?></td>
              <td class="px-3 py-3 text-center font-bold"><?= (int)$p['jumlah'] ?></td>
              <td class="px-3 py-3 text-xs"><?= htmlspecialchars($p['kota']) ?></td>
              <td class="px-3 py-3"><span class="text-xs font-bold px-2 py-1 rounded-full <?= $sc ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td class="px-3 py-3">
                <?php if ($p['d_nama']): ?>
                <span class="text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-1 rounded-full border border-blue-200">🚚 <?= htmlspecialchars($p['d_nama']) ?></span>
                <?php else: ?>
                <span class="text-xs text-gray-400 italic">Belum ditugaskan</span>
                <?php endif; ?>
              </td>
              <!-- Ubah Status -->
              <td class="px-3 py-3">
                <form method="POST" class="flex gap-1 items-center">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="pesanan_id" value="<?= (int)$p['id'] ?>">
                  <select name="status" class="input-style" style="width:120px;padding:5px 28px 5px 8px;">
                    <option value="Diproses"  <?= $p['status']==='Diproses'?'selected':'' ?>>⏳ Diproses</option>
                    <option value="Disiapkan" <?= $p['status']==='Disiapkan'?'selected':'' ?>>📦 Disiapkan</option>
                    <option value="Diantar"   <?= $p['status']==='Diantar'?'selected':'' ?>>🚚 Diantar</option>
                    <option value="Selesai"   <?= $p['status']==='Selesai'?'selected':'' ?>>✅ Selesai</option>
                  </select>
                  <button type="submit" class="btn-sm btn-success">OK</button>
                </form>
              </td>
              <!-- Assign Driver -->
              <td class="px-3 py-3">
                <?php if (count($drivers_list) > 0): ?>
                <form method="POST" class="flex gap-1 items-center">
                  <input type="hidden" name="action" value="assign_driver">
                  <input type="hidden" name="pesanan_id" value="<?= (int)$p['id'] ?>">
                  <select name="driver_id" class="input-style" style="width:130px;padding:5px 28px 5px 8px;">
                    <option value="0">— Lepas Driver —</option>
                    <?php foreach ($drivers_list as $drv): ?>
                    <option value="<?= (int)$drv['id'] ?>" <?= (int)$p['driver_id']===(int)$drv['id']?'selected':'' ?>><?= htmlspecialchars($drv['nama']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn-sm btn-primary">Tugaskan</button>
                </form>
                <?php else: ?>
                <span class="text-xs text-gray-400">Tidak ada driver</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php $no++; endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ═══ TAB: LAPORAN ═══ -->
  <div id="adm-laporan" class="adm-tab-content">
    <div class="mb-6 mt-6">
      <div class="section-tag">Manajemen Laporan</div>
      <h2 class="font-serif text-3xl text-navy-900 mt-1">Laporan <span class="italic text-cyan-brand">Pelanggan</span></h2>
      <p class="text-gray-500 text-sm mt-1">Tindak lanjuti laporan masalah dari pelanggan.</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="bg-navy-900 px-6 py-4 flex items-center justify-between">
        <span class="text-white font-bold">📋 Semua Laporan</span>
        <span class="text-cyan-light/60 text-xs"><?= $stat_laporan ?> laporan baru</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-cyan-pale">
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">ID</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Pelanggan</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Kategori</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Deskripsi</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Tanggal</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Ubah Status</th>
          </tr></thead>
          <tbody>
            <?php
            if (!mysqli_num_rows($r_laporan)): ?>
            <tr><td colspan="7" class="text-center py-10 text-gray-400">Belum ada laporan.</td></tr>
            <?php else: while ($l = mysqli_fetch_assoc($r_laporan)):
              $lc = $lc_map[$l['status']] ?? 'bg-gray-100 text-gray-700'; ?>
            <tr class="border-t border-gray-50 hover:bg-gray-50">
              <td class="px-4 py-3 font-bold text-xs">#<?= str_pad($l['id'],4,'0',STR_PAD_LEFT) ?></td>
              <td class="px-4 py-3 font-medium text-sm"><?= htmlspecialchars($l['u_nama']) ?></td>
              <td class="px-4 py-3 text-xs"><?= htmlspecialchars($l['kategori']) ?></td>
              <td class="px-4 py-3 text-xs text-gray-500" style="max-width:180px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="<?= htmlspecialchars($l['deskripsi']) ?>"><?= htmlspecialchars($l['deskripsi']) ?></td>
              <td class="px-4 py-3 text-xs text-gray-500"><?= date('d M Y', strtotime($l['tgl_laporan'])) ?></td>
              <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-full <?= $lc ?>"><?= htmlspecialchars($l['status']) ?></span></td>
              <td class="px-4 py-3">
                <form method="POST" class="flex gap-1 items-center">
                  <input type="hidden" name="action" value="update_laporan">
                  <input type="hidden" name="laporan_id" value="<?= (int)$l['id'] ?>">
                  <select name="status" class="input-style" style="width:120px;padding:5px 28px 5px 8px;">
                    <option value="Masuk"    <?= $l['status']==='Masuk'?'selected':'' ?>>📥 Masuk</option>
                    <option value="Diproses" <?= $l['status']==='Diproses'?'selected':'' ?>>🔄 Diproses</option>
                    <option value="Selesai"  <?= $l['status']==='Selesai'?'selected':'' ?>>✅ Selesai</option>
                  </select>
                  <button type="submit" class="btn-sm btn-success">OK</button>
                </form>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ═══ TAB: DRIVER ═══ -->
  <div id="adm-driver" class="adm-tab-content">
    <div class="mb-6 mt-6">
      <div class="section-tag">Manajemen Driver</div>
      <h2 class="font-serif text-3xl text-navy-900 mt-1">Daftar <span class="italic text-cyan-brand">Driver</span></h2>
      <p class="text-gray-500 text-sm mt-1">Semua driver yang terdaftar di sistem Air Biru.</p>
    </div>

    <?php if (empty($drivers_list)): ?>
    <div class="bg-white rounded-2xl p-10 text-center border border-gray-100">
      <div class="text-5xl mb-3">🚚</div>
      <div class="font-bold text-navy-900 mb-2">Belum Ada Driver</div>
      <p class="text-gray-500 text-sm">Tambahkan driver lewat <code class="bg-gray-100 px-2 py-1 rounded">seed_admin.php</code> atau langsung di database dengan role = 'driver'.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-3 gap-5 mb-6">
      <?php foreach ($drivers_list as $drv):
        $d_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT COUNT(*) c FROM pesanan WHERE driver_id={$drv['id']} AND status!='Selesai'"))['c'];
        $d_selesai = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT COUNT(*) c FROM pesanan WHERE driver_id={$drv['id']} AND status='Selesai'"))['c'];
      ?>
      <div class="bg-white rounded-2xl p-6 border border-gray-100 card-hover">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-full bg-navy-900 flex items-center justify-center text-xl border-2 border-cyan-brand">🚚</div>
          <div>
            <div class="font-bold text-navy-900"><?= htmlspecialchars($drv['nama']) ?></div>
            <div class="text-xs <?= $d_aktif>0?'text-cyan-brand font-bold':'text-gray-400' ?>">
              <?= $d_aktif>0 ? '🟢 Aktif Bertugas' : '⚪ Tersedia' ?>
            </div>
          </div>
        </div>
        <div class="space-y-2 text-xs">
          <div class="bg-cyan-pale rounded-lg p-3 border border-cyan-light/30">
            <div class="font-bold text-navy-900/50 mb-1 uppercase tracking-wide">No. HP</div>
            <div class="font-medium"><?= htmlspecialchars($drv['no_hp'] ?? '-') ?></div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-100">
              <div class="text-xl font-bold text-blue-600"><?= $d_aktif ?></div>
              <div class="text-blue-500 font-bold">Pesanan Aktif</div>
            </div>
            <div class="bg-green-50 rounded-lg p-3 text-center border border-green-100">
              <div class="text-xl font-bold text-green-600"><?= $d_selesai ?></div>
              <div class="text-green-500 font-bold">Selesai</div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pesanan aktif semua -->
    <div class="bg-white rounded-2xl p-5 border border-gray-100">
      <div class="font-bold text-navy-900 text-sm mb-3">📋 Semua Pesanan Aktif (Belum Selesai)</div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-cyan-pale">
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Pelanggan</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Produk</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Kota</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Driver</th>
          </tr></thead>
          <tbody>
            <?php
            $r_aktif = mysqli_query($koneksi,
                "SELECT p.*, d.nama AS d_nama
                 FROM pesanan p
                 LEFT JOIN users d ON p.driver_id=d.id AND d.role='driver'
                 WHERE p.status!='Selesai'
                 ORDER BY p.tgl_pesan ASC");
            if (!mysqli_num_rows($r_aktif)): ?>
            <tr><td colspan="5" class="text-center py-8 text-gray-400 text-sm">Semua pesanan sudah selesai. 🎉</td></tr>
            <?php else: while ($p = mysqli_fetch_assoc($r_aktif)):
              $sc = $sc_map[$p['status']] ?? 'bg-gray-100 text-gray-700'; ?>
            <tr class="border-t border-gray-50 hover:bg-gray-50">
              <td class="px-4 py-3 font-medium"><?= htmlspecialchars($p['nama']) ?></td>
              <td class="px-4 py-3 text-xs"><?= htmlspecialchars($p['produk']) ?></td>
              <td class="px-4 py-3 text-xs"><?= htmlspecialchars($p['kota']) ?></td>
              <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-full <?= $sc ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td class="px-4 py-3 text-xs">
                <?php if ($p['d_nama']): ?>
                <span class="text-blue-700 font-semibold">🚚 <?= htmlspecialchars($p['d_nama']) ?></span>
                <?php else: ?>
                <span class="text-red-400 font-bold">⚠️ Belum ada driver</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /container -->

<script>
function showAdmTab(tab) {
  document.querySelectorAll('.adm-tab-content').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll('.adm-tab-btn').forEach(el=>el.classList.remove('active'));
  var tc=document.getElementById('adm-'+tab),tb=document.getElementById('btn-'+tab);
  if(tc)tc.classList.add('active');if(tb)tb.classList.add('active');
}
showAdmTab('<?= $adm_tab ?>');
</script>
<?php mysqli_close($koneksi); ?>
</body>
</html>
