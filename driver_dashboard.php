<?php
session_start();
if (!isset($_SESSION['user_id']))       { header("Location: index.php#login"); exit; }
if ($_SESSION['user_role'] !== 'driver'){ header("Location: dashboard.php"); exit; }

include 'koneksi.php';

$uid       = (int)$_SESSION['user_id'];
$nama_user = htmlspecialchars($_SESSION['user_nama'] ?? '');
$msg       = null;

// Status yang boleh dipilih driver (urutan maju saja)
$allowed_status = ['Disiapkan', 'Diantar', 'Selesai'];

// ── POST handler ──────────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_status_driver') {
    $pid    = (int)($_POST['pesanan_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!$pid) {
        $msg = ['type'=>'error', 'text'=>'ID pesanan tidak valid.'];
    } elseif (!in_array($status, $allowed_status)) {
        $msg = ['type'=>'error', 'text'=>'Status tidak diizinkan.'];
    } else {
        // Pastikan pesanan ini memang milik driver ini
        $chk = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT id FROM pesanan WHERE id=$pid AND driver_id=$uid"));
        if (!$chk) {
            $msg = ['type'=>'error', 'text'=>'Pesanan tidak ditemukan atau bukan milik Anda.'];
        } else {
            $st = mysqli_real_escape_string($koneksi, $status);
            mysqli_query($koneksi, "UPDATE pesanan SET status='$st' WHERE id=$pid AND driver_id=$uid");
            $msg = ['type'=>'success', 'text'=>'✅ Status diperbarui ke "'.$status.'"!'];
        }
    }
}

// ── Fetch pesanan yang di-assign ke driver ini ────────────────────────────────
$r_aktif = mysqli_query($koneksi,
    "SELECT p.*, u.email AS u_email
     FROM pesanan p
     JOIN users u ON p.user_id = u.id
     WHERE p.driver_id = $uid AND p.status != 'Selesai'
     ORDER BY p.tgl_pesan ASC");

$r_selesai_count = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) c FROM pesanan WHERE driver_id=$uid AND status='Selesai'"))['c'];

$cnt_aktif = mysqli_num_rows($r_aktif);

$sc_map = ['Diproses'=>'bg-orange-100 text-orange-700','Disiapkan'=>'bg-blue-100 text-blue-700',
           'Diantar'=>'bg-cyan-100 text-cyan-700','Selesai'=>'bg-green-100 text-green-700'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Dashboard — Air Biru</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Plus Jakarta Sans','sans-serif'],serif:['Instrument Serif','serif']},colors:{navy:{950:'#03112e',900:'#0a2463',800:'#0d3180'},cyan:{brand:'#34b4c8',light:'#a8dadc',pale:'#e8f4f8'}}}}}</script>
  <style>
    .btn-primary{background:linear-gradient(135deg,#0a2463,#168aad);color:white;padding:8px 18px;border-radius:999px;font-weight:700;font-size:.85rem;border:none;cursor:pointer;text-decoration:none;display:inline-block;}
    .btn-primary:hover{opacity:.9;}
    .btn-danger{background:linear-gradient(135deg,#c53030,#e53e3e);color:white;padding:8px 16px;border-radius:999px;font-weight:700;font-size:.8rem;border:none;cursor:pointer;text-decoration:none;}
    .section-tag{display:inline-block;background:rgba(52,180,200,.12);color:#34b4c8;font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:5px 14px;border-radius:999px;border:1px solid rgba(52,180,200,.3);}
    .card-hover{transition:transform .2s,box-shadow .2s;}
    .card-hover:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(10,36,99,.1);}
    .alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.9rem;}
    .alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.9rem;}
    .drv-select{padding:6px 30px 6px 10px;border:1.5px solid #c5dde6;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;color:#0a2463;background:#f3f8fb;outline:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%230a2463'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 7px center;background-size:14px;}
    .pill-role{display:inline-block;border-radius:999px;font-size:.7rem;font-weight:700;padding:3px 12px;}
    .btn-update{background:linear-gradient(135deg,#276749,#38a169);color:white;padding:6px 14px;border-radius:999px;font-weight:700;font-size:.75rem;border:none;cursor:pointer;}
  </style>
</head>
<body class="bg-[#f0f7fb] font-sans text-navy-900">

<!-- NAVBAR -->
<nav class="fixed top-0 w-full z-50 bg-navy-900/95 backdrop-blur-sm border-b border-white/10">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2 text-white font-bold text-lg">
      <span class="text-2xl">💧</span><span>Air<span class="text-cyan-brand">Biru</span></span>
      <span class="pill-role bg-blue-400 text-white ml-2">🚚 Driver</span>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-white/70 text-sm font-semibold hidden md:block">Halo, <?= $nama_user ?></span>
      <a href="logout.php" class="btn-danger text-xs" style="padding:8px 18px;">🚪 Logout</a>
    </div>
  </div>
</nav>

<div class="pt-20 max-w-6xl mx-auto px-6 pb-16">

  <div class="mb-6 mt-6">
    <div class="section-tag">Driver Panel</div>
    <h2 class="font-serif text-3xl text-navy-900 mt-1">Halo, <span class="italic text-cyan-brand"><?= $nama_user ?></span>! 🚚</h2>
    <p class="text-gray-500 text-sm mt-1">Pesanan di bawah adalah pesanan yang ditugaskan kepada Anda.</p>
  </div>

  <?php if ($msg): ?>
  <div class="alert-<?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
  <?php endif; ?>

  <!-- Info box -->
  <div class="mb-6 bg-blue-50 border border-blue-200 rounded-2xl p-5 flex gap-4 items-start">
    <div class="text-2xl flex-shrink-0">📌</div>
    <div>
      <div class="font-bold text-blue-800 text-sm mb-1">Panduan Driver</div>
      <p class="text-blue-700 text-xs leading-relaxed">
        Hanya pesanan yang ditugaskan admin kepada Anda yang muncul di tabel ini.
        Perbarui status ke <b>Disiapkan → Diantar → Selesai</b> sesuai progres.
        Hubungi pelanggan 10 menit sebelum tiba dan konfirmasi alamat terlebih dahulu.
      </p>
    </div>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-3 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-6 border border-gray-100 card-hover text-center">
      <div class="text-3xl mb-2">🚚</div>
      <div class="text-2xl font-bold text-blue-600"><?= $cnt_aktif ?></div>
      <div class="text-gray-500 text-xs mt-1">Pesanan Aktif (Ditugaskan)</div>
    </div>
    <div class="bg-white rounded-2xl p-6 border border-gray-100 card-hover text-center">
      <div class="text-3xl mb-2">✅</div>
      <div class="text-2xl font-bold text-green-600"><?= $r_selesai_count ?></div>
      <div class="text-gray-500 text-xs mt-1">Total Selesai Diantar</div>
    </div>
    <div class="bg-navy-900 rounded-2xl p-6 text-center flex flex-col justify-center">
      <div class="text-cyan-brand text-xs font-bold uppercase tracking-widest mb-2">Status Saya</div>
      <div class="text-xl font-bold <?= $cnt_aktif>0?'text-cyan-brand':'text-gray-400' ?>">
        <?= $cnt_aktif>0 ? '🟢 Aktif Bertugas' : '⚪ Menunggu Penugasan' ?>
      </div>
    </div>
  </div>

  <!-- Tabel pesanan aktif -->
  <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6">
    <div class="bg-navy-900 px-6 py-4 flex items-center justify-between">
      <span class="text-white font-bold">📦 Pesanan yang Harus Diantarkan</span>
      <span class="text-cyan-light/60 text-xs">Update status setelah galon diterima pelanggan</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="bg-cyan-pale">
          <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">No</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Pelanggan</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Produk</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Alamat Tujuan</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Jadwal</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Catatan</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Status</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-navy-900 uppercase">Update</th>
        </tr></thead>
        <tbody>
          <?php if ($cnt_aktif === 0): ?>
          <tr><td colspan="8" class="text-center py-12 text-gray-400">
            <div class="text-4xl mb-3">📭</div>
            <div class="font-semibold">Tidak ada pesanan yang ditugaskan saat ini.</div>
            <div class="text-xs mt-1">Admin akan menugaskan pesanan kepada Anda segera.</div>
          </td></tr>
          <?php else: $no=1; while ($p = mysqli_fetch_assoc($r_aktif)):
            $sc = $sc_map[$p['status']] ?? 'bg-gray-100 text-gray-700';
            $alamat_full = implode(', ', array_filter([
                $p['alamat'], $p['deskripsi'], $p['kecamatan'], $p['kota'], $p['provinsi']
            ]));
            $bg = $no%2===0?'bg-gray-50':'';
          ?>
          <tr class="<?= $bg ?> border-t border-gray-50 hover:bg-cyan-pale/20">
            <td class="px-4 py-3 font-bold text-xs">#<?= str_pad($p['id'],4,'0',STR_PAD_LEFT) ?></td>
            <td class="px-4 py-3">
              <div class="font-medium"><?= htmlspecialchars($p['nama']) ?></div>
              <div class="text-xs text-gray-400">📞 <?= htmlspecialchars($p['telepon']) ?></div>
            </td>
            <td class="px-4 py-3">
              <div class="font-semibold text-xs"><?= htmlspecialchars($p['produk']) ?></div>
              <div class="text-gray-400 text-xs">× <?= (int)$p['jumlah'] ?> unit</div>
            </td>
            <td class="px-4 py-3 text-xs" style="max-width:200px;">
              <div class="text-gray-700 leading-relaxed"><?= htmlspecialchars(mb_substr($alamat_full,0,100)).'...' ?></div>
              <?php if ($p['deskripsi']): ?>
              <div class="text-cyan-brand font-semibold mt-1">📍 <?= htmlspecialchars($p['deskripsi']) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500"><?= htmlspecialchars($p['jadwal']) ?></td>
            <td class="px-4 py-3 text-xs text-gray-400 italic"><?= htmlspecialchars($p['catatan'] ?: '—') ?></td>
            <td class="px-4 py-3">
              <span class="text-xs font-bold px-2 py-1 rounded-full <?= $sc ?>"><?= htmlspecialchars($p['status']) ?></span>
            </td>
            <td class="px-4 py-3">
              <form method="POST" class="flex flex-col gap-1">
                <input type="hidden" name="action" value="update_status_driver">
                <input type="hidden" name="pesanan_id" value="<?= (int)$p['id'] ?>">
                <select name="status" class="drv-select mb-1">
                  <?php foreach ($allowed_status as $s): ?>
                  <option value="<?= $s ?>" <?= $p['status']===$s?'selected':'' ?>><?= $s === 'Disiapkan' ? '📦' : ($s==='Diantar'?'🚚':'✅') ?> <?= $s ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-update">Update</button>
              </form>
            </td>
          </tr>
          <?php $no++; endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Riwayat selesai -->
  <?php
  $r_done = mysqli_query($koneksi,
      "SELECT p.*, u.email AS u_email
       FROM pesanan p JOIN users u ON p.user_id=u.id
       WHERE p.driver_id=$uid AND p.status='Selesai'
       ORDER BY p.tgl_pesan DESC LIMIT 10");
  if (mysqli_num_rows($r_done) > 0):
  ?>
  <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6">
    <div class="bg-green-700 px-6 py-4">
      <span class="text-white font-bold">✅ Riwayat Pengiriman Selesai (10 terbaru)</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="bg-green-50">
          <th class="px-4 py-3 text-left text-xs font-bold text-green-800 uppercase">No</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-green-800 uppercase">Pelanggan</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-green-800 uppercase">Produk</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-green-800 uppercase">Kota</th>
          <th class="px-4 py-3 text-left text-xs font-bold text-green-800 uppercase">Tanggal Pesan</th>
        </tr></thead>
        <tbody>
          <?php $no=1; while ($p = mysqli_fetch_assoc($r_done)): ?>
          <tr class="border-t border-gray-50 hover:bg-green-50/50">
            <td class="px-4 py-3 text-xs font-bold">#<?= str_pad($p['id'],4,'0',STR_PAD_LEFT) ?></td>
            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($p['nama']) ?></td>
            <td class="px-4 py-3 text-xs"><?= htmlspecialchars($p['produk']) ?></td>
            <td class="px-4 py-3 text-xs"><?= htmlspecialchars($p['kota']) ?></td>
            <td class="px-4 py-3 text-xs text-gray-500"><?= date('d M Y H:i', strtotime($p['tgl_pesan'])) ?></td>
          </tr>
          <?php $no++; endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tips -->
  <div class="bg-cyan-pale rounded-2xl p-5 border border-cyan-light/40 flex gap-4 items-start">
    <div class="text-3xl">💡</div>
    <div>
      <div class="font-bold text-navy-900 text-sm mb-1">Tips Pengiriman</div>
      <p class="text-gray-600 text-sm leading-relaxed">Hubungi pelanggan 10 menit sebelum tiba. Konfirmasi alamat dan deskripsi tambahan sebelum berangkat. Jika tidak ada respons setelah 5 menit, segera hubungi admin.</p>
    </div>
  </div>

</div>
<?php mysqli_close($koneksi); ?>
</body>
</html>
