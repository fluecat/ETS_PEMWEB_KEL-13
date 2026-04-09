<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Pesanan - Air Biru</title>
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
    .btn-primary{background:linear-gradient(135deg,#0a2463,#168aad);color:white;padding:11px 28px;border-radius:999px;font-weight:700;border:none;cursor:pointer;text-decoration:none;display:inline-block;}
    .btn-secondary{background:#e8f4f8;color:#0a2463;padding:10px 24px;border-radius:999px;font-weight:700;border:none;cursor:pointer;text-decoration:none;display:inline-block;}
    .btn-danger{background:linear-gradient(135deg,#c53030,#e53e3e);color:white;padding:10px 24px;border-radius:999px;font-weight:700;border:none;cursor:pointer;}
    .modal-overlay{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
    .modal-overlay.show{display:flex;}
    @keyframes modal-in{from{opacity:0;transform:scale(0.92)}to{opacity:1;transform:scale(1)}}
    .modal-box{animation:modal-in 0.22s ease both;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .fade-up{animation:fadeUp 0.5s ease both;}
    .delay-1{animation-delay:0.1s;} .delay-2{animation-delay:0.2s;} .delay-3{animation-delay:0.3s;}
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
$id  = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) { header("Location: dashboard.php?tab=order"); exit; }

$r    = mysqli_query($koneksi, "SELECT p.*, u.email as user_email FROM pesanan p JOIN users u ON p.user_id=u.id WHERE p.id=$id AND p.user_id=$uid");
$data = mysqli_fetch_assoc($r);

if (!$data) { header("Location: dashboard.php?tab=order"); exit; }

$sc_map = [
    'Diproses' => ['class'=>'bg-orange-100 text-orange-700','icon'=>'⏳'],
    'Disiapkan'=> ['class'=>'bg-blue-100 text-blue-700',   'icon'=>'📦'],
    'Diantar'  => ['class'=>'bg-cyan-100 text-cyan-700',   'icon'=>'🚚'],
    'Selesai'  => ['class'=>'bg-green-100 text-green-700', 'icon'=>'✅'],
];
$st = $sc_map[$data['status']] ?? ['class'=>'bg-gray-100 text-gray-700','icon'=>'❓'];

$harga_satuan = strpos($data['produk'],'19')!==false ? 20000 : 8000;
$total        = $harga_satuan * $data['jumlah'];
?>

<!-- MODAL KONFIRMASI HAPUS -->
<div id="modal-hapus" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal-box bg-white rounded-3xl shadow-2xl p-8 max-w-sm w-full mx-4">
    <div class="text-center mb-6">
      <div class="text-5xl mb-3">🗑️</div>
      <h3 class="font-bold text-navy-900 text-xl mb-2">Hapus Pesanan Ini?</h3>
      <p class="text-gray-500 text-sm">Pesanan <b class="text-navy-900">#<?= str_pad($data['id'],4,'0',STR_PAD_LEFT) ?></b> atas nama <b class="text-navy-900"><?= htmlspecialchars($data['nama']) ?></b> akan dihapus permanen.</p>
    </div>
    <div class="flex gap-3">
      <button onclick="tutupModal()" class="flex-1 btn-secondary">Batal</button>
      <a href="dashboard.php?hapus=<?= $data['id'] ?>&tab=order" class="flex-1 btn-danger text-center" style="padding:10px 16px;border-radius:999px;font-weight:700;font-size:0.87rem;text-decoration:none;">Ya, Hapus</a>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<nav class="fixed top-0 w-full z-50 bg-navy-900/95 backdrop-blur-sm border-b border-white/10">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-2 text-white font-bold text-lg">
      <span class="text-2xl">💧</span><span>Air<span class="text-cyan-brand">Biru</span></span>
    </div>
    <div class="flex items-center gap-2">
      <a href="index.php"     class="text-white/70 hover:text-white text-sm font-semibold px-3 py-2 rounded-lg">🏠 Beranda</a>
      <a href="dashboard.php?tab=order" class="text-white/70 hover:text-white text-sm font-semibold px-3 py-2 rounded-lg">← Kembali ke Order</a>
      <a href="logout.php"    class="ml-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-4 py-2 rounded-full">🚪 Logout</a>
    </div>
  </div>
</nav>

<div class="pt-24 max-w-4xl mx-auto px-6 pb-16">

  <!-- Header -->
  <div class="mb-8 fade-up">
    <div class="flex items-center gap-3 mb-2">
      <span class="text-xs bg-cyan-pale text-cyan-brand font-bold px-3 py-1 rounded-full border border-cyan-light/50">Detail Pesanan</span>
      <span class="text-xs font-bold px-3 py-1 rounded-full <?= $st['class'] ?>"><?= $st['icon'] ?> <?= $data['status'] ?></span>
    </div>
    <h1 class="font-serif text-4xl text-navy-900">Pesanan <span class="italic text-cyan-brand">#<?= str_pad($data['id'],4,'0',STR_PAD_LEFT) ?></span></h1>
    <p class="text-gray-500 text-sm mt-1">Dibuat pada <?= date('d F Y, H:i', strtotime($data['tgl_pesan'])) ?> WIB</p>
  </div>

  <div class="grid grid-cols-3 gap-6">

    <!-- Kolom Utama -->
    <div class="col-span-2 space-y-6">

      <!-- Info Pemesan -->
      <div class="bg-white rounded-2xl p-7 border border-gray-100 fade-up delay-1">
        <div class="font-bold text-navy-900 text-base mb-5 flex items-center gap-2">
          <span class="text-xl">👤</span> Informasi Pemesan
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/30">
            <div class="text-xs font-bold text-navy-900/50 uppercase tracking-wide mb-1">Nama Lengkap</div>
            <div class="font-semibold text-navy-900"><?= htmlspecialchars($data['nama']) ?></div>
          </div>
          <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/30">
            <div class="text-xs font-bold text-navy-900/50 uppercase tracking-wide mb-1">Nomor Telepon</div>
            <div class="font-semibold text-navy-900"><?= htmlspecialchars($data['telepon']) ?></div>
          </div>
        </div>
      </div>

      <!-- Alamat Pengiriman -->
      <div class="bg-white rounded-2xl p-7 border border-gray-100 fade-up delay-2">
        <div class="font-bold text-navy-900 text-base mb-5 flex items-center gap-2">
          <span class="text-xl">📍</span> Alamat Pengiriman Lengkap
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <?php
          $fields = [
            'Negara'          => $data['negara'],
            'Provinsi'        => $data['provinsi'],
            'Kota / Kabupaten'=> $data['kota'],
            'Kecamatan'       => $data['kecamatan'],
            'RT / RW'         => $data['rt_rw'],
            'Kode Pos'        => $data['kode_pos'],
          ];
          foreach ($fields as $label => $val): ?>
          <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/30">
            <div class="text-xs font-bold text-navy-900/50 uppercase tracking-wide mb-1"><?= $label ?></div>
            <div class="font-semibold text-navy-900"><?= htmlspecialchars($val) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="bg-cyan-pale rounded-xl p-4 border border-cyan-light/30 mb-3">
          <div class="text-xs font-bold text-navy-900/50 uppercase tracking-wide mb-1">Alamat Lengkap</div>
          <div class="font-semibold text-navy-900"><?= htmlspecialchars($data['alamat']) ?></div>
        </div>
        <?php if ($data['deskripsi']): ?>
        <div class="bg-yellow-50 rounded-xl p-4 border border-yellow-200">
          <div class="text-xs font-bold text-yellow-700 uppercase tracking-wide mb-1">📝 Deskripsi Tambahan</div>
          <div class="font-medium text-yellow-800"><?= htmlspecialchars($data['deskripsi']) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Catatan -->
      <?php if ($data['catatan']): ?>
      <div class="bg-white rounded-2xl p-7 border border-gray-100 fade-up delay-3">
        <div class="font-bold text-navy-900 text-base mb-3 flex items-center gap-2">
          <span class="text-xl">💬</span> Catatan Tambahan
        </div>
        <p class="text-gray-600 leading-relaxed"><?= htmlspecialchars($data['catatan']) ?></p>
      </div>
      <?php endif; ?>

    </div>

    <!-- Sidebar Ringkasan -->
    <div class="space-y-5">

      <!-- Ringkasan Pesanan -->
      <div class="bg-navy-900 rounded-2xl p-6 text-white fade-up delay-1">
        <div class="text-cyan-brand font-bold text-xs uppercase tracking-widest mb-5">Ringkasan Pesanan</div>
        <div class="flex items-center gap-3 mb-5 p-4 bg-white/10 rounded-xl">
          <div class="text-3xl"><?= strpos($data['produk'],'19')!==false?'🫙':'🧴' ?></div>
          <div>
            <div class="font-bold text-sm"><?= htmlspecialchars($data['produk']) ?></div>
            <div class="text-cyan-light/60 text-xs">Rp <?= number_format($harga_satuan,0,',','.') ?> / unit</div>
          </div>
        </div>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-white/60">Jumlah</span>
            <span class="font-bold"><?= $data['jumlah'] ?> unit</span>
          </div>
          <div class="flex justify-between">
            <span class="text-white/60">Harga Satuan</span>
            <span class="font-bold">Rp <?= number_format($harga_satuan,0,',','.') ?></span>
          </div>
          <div class="border-t border-white/20 pt-3 flex justify-between">
            <span class="text-white/60">Total</span>
            <span class="font-bold text-cyan-brand text-lg">Rp <?= number_format($total,0,',','.') ?></span>
          </div>
        </div>
      </div>

      <!-- Info Jadwal & Status -->
      <div class="bg-white rounded-2xl p-6 border border-gray-100 fade-up delay-2">
        <div class="font-bold text-navy-900 text-sm mb-4">📅 Jadwal & Status</div>
        <div class="space-y-3">
          <div class="bg-cyan-pale rounded-xl p-3 border border-cyan-light/30">
            <div class="text-xs font-bold text-navy-900/50 uppercase tracking-wide mb-1">Jadwal Pengiriman</div>
            <div class="font-semibold text-navy-900 text-sm"><?= htmlspecialchars($data['jadwal']) ?></div>
          </div>
          <div class="bg-cyan-pale rounded-xl p-3 border border-cyan-light/30">
            <div class="text-xs font-bold text-navy-900/50 uppercase tracking-wide mb-1">Status</div>
            <span class="text-xs font-bold px-3 py-1 rounded-full <?= $st['class'] ?>"><?= $st['icon'] ?> <?= $data['status'] ?></span>
          </div>
          <div class="bg-cyan-pale rounded-xl p-3 border border-cyan-light/30">
            <div class="text-xs font-bold text-navy-900/50 uppercase tracking-wide mb-1">Tanggal Pesan</div>
            <div class="font-semibold text-navy-900 text-sm"><?= date('d M Y, H:i', strtotime($data['tgl_pesan'])) ?></div>
          </div>
        </div>
      </div>

      <!-- SDGs Info -->
      <div class="bg-green-50 rounded-2xl p-5 border border-green-200 fade-up delay-3">
        <div class="text-green-700 font-bold text-xs uppercase tracking-widest mb-2">🌍 Kontribusi SDGs</div>
        <div class="font-semibold text-green-800 text-sm mb-1">SDG 6: Air Bersih & Sanitasi</div>
        <p class="text-green-700 text-xs leading-relaxed">Setiap pesanan Air Biru mendukung akses air minum berkualitas yang terjangkau untuk masyarakat.</p>
      </div>

      <!-- Aksi -->
      <div class="space-y-3 fade-up delay-3">
        <a href="dashboard.php?edit=<?= $data['id'] ?>" class="btn-primary w-full text-center text-sm" style="padding:11px 20px;">✏️ Edit Pesanan Ini</a>
        <button onclick="bukaModal()" class="btn-danger w-full text-sm" style="padding:11px 20px;">🗑️ Hapus Pesanan</button>
        <a href="dashboard.php?tab=order" class="btn-secondary w-full text-center text-sm" style="padding:11px 20px;">← Kembali ke Daftar</a>
      </div>

    </div>
  </div>
</div>

<script>
function bukaModal() { document.getElementById('modal-hapus').classList.add('show'); }
function tutupModal(){ document.getElementById('modal-hapus').classList.remove('show'); }
document.getElementById('modal-hapus').addEventListener('click',function(e){if(e.target===this)tutupModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')tutupModal();});
</script>

<?php mysqli_close($koneksi); ?>
</body>
</html>
