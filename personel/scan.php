<?php
require_once __DIR__ . '/../config/config.php';
require_role('personel');

$dosirList = get_dosir_master($pdo);
$selectedKode = $_GET['kode'] ?? '';

$pageTitle = 'Scan Dokumen (Kamera)';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:720px;margin:0 auto;">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
    <div style="width:42px;height:42px;border-radius:50%;background:rgba(201,168,76,0.15);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:22px;border:1px solid var(--gold);flex-shrink:0;">
      📷
    </div>
    <div>
      <h3 style="margin:0;">Scan Dokumen Melalui Kamera</h3>
      <div style="color:var(--text-dim);font-size:13px;margin-top:2px;">
        Ambil foto dokumen via kamera HP/PC atau pilih berkas foto. Semua halaman otomatis digabung menjadi satu berkas PDF.
      </div>
    </div>
  </div>

  <div class="grid grid-2" style="margin-bottom:16px;">
    <div>
      <label>Jenis Dosir *</label>
      <select id="kodeDosir" required>
        <option value="">-- Pilih Jenis Dosir --</option>
        <?php foreach ($dosirList as $d): ?>
          <option value="<?= $d['kode'] ?>" <?= $selectedKode === $d['kode'] ? 'selected' : '' ?>>
            DOSIR <?= $d['kode'] ?> - <?= htmlspecialchars($d['nama_dosir']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label>Keterangan Dokumen (Opsional)</label>
      <input id="keterangan" placeholder="cth: Ijazah SMA depan &amp; belakang">
    </div>
  </div>

  <!-- Area Viewfinder Kamera & Preview -->
  <div style="position:relative;margin-bottom:14px;">
    <div id="videoContainer" style="position:relative;width:100%;height:320px;background:#050705;border-radius:10px;border:2px solid var(--border);overflow:hidden;display:flex;align-items:center;justify-content:center;">
      
      <!-- Video Element -->
      <video id="camPreview" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;display:none;"></video>
      
      <!-- Placeholder Box (Ketika kamera belum aktif) -->
      <div id="camPlaceholder" style="display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-dim);text-align:center;padding:20px;z-index:2;">
        <div style="font-size:44px;margin-bottom:8px;">🎥</div>
        <strong style="color:var(--text);font-size:16px;">Kamera Belum Aktif</strong>
        <div style="font-size:12.5px;max-width:400px;margin-top:6px;line-height:1.5;">
          Klik tombol <strong>"🎥 Aktifkan Kamera"</strong> di bawah untuk membuka kamera, atau klik <strong>"📁 Ambil/Pilih Foto"</strong> untuk memilih file gambar dari HP/laptop.
        </div>
      </div>

      <!-- Efek Flash Kamera saat mengambil gambar -->
      <div id="cameraFlash" style="position:absolute;top:0;left:0;right:0;bottom:0;background:#ffffff;opacity:0;pointer-events:none;transition:opacity 0.15s ease;z-index:5;"></div>

      <canvas id="camCanvas" style="display:none;"></canvas>
    </div>
  </div>

  <!-- Tombol Kontrol Kamera & Upload Foto -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <button type="button" id="btnStart" class="btn btn-outline" style="flex:1;min-width:160px;font-size:13.5px;">
      🎥 Aktifkan Kamera
    </button>
    <button type="button" id="btnCapture" class="btn" style="flex:1;min-width:160px;background:var(--ok);font-size:13.5px;" disabled>
      📸 Ambil Gambar
    </button>
    <button type="button" id="btnPickFile" class="btn btn-outline" style="flex:1;min-width:160px;font-size:13.5px;">
      📁 Ambil/Pilih Foto
    </button>
    <!-- Hidden file input for native camera or file picking -->
    <input type="file" id="fileDocInput" accept="image/*" capture="environment" multiple style="display:none;">
  </div>

  <!-- Kotak Status Interaktif -->
  <div id="scanStatus" style="padding:10px 14px;border-radius:8px;background:var(--panel-2);border:1px solid var(--border);margin-bottom:16px;font-size:13px;min-height:40px;display:flex;align-items:center;">
    <span style="color:var(--text-dim);">Siap mengambil foto dokumen. Klik <strong>"Aktifkan Kamera"</strong> atau <strong>"Ambil/Pilih Foto"</strong>.</span>
  </div>

  <!-- Galeri Thumbnail Halaman yang Telah Ditangkap -->
  <div style="margin-bottom:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <strong style="font-size:13px;">Halaman Dokumen Ditangkap (<span id="pageCount">0</span> halaman):</strong>
      <button type="button" id="btnClearAll" class="btn btn-outline" style="padding:3px 8px;font-size:11px;display:none;">Hapus Semua</button>
    </div>
    <div id="thumbs" style="display:flex;gap:10px;flex-wrap:wrap;min-height:40px;align-items:center;">
      <div id="noThumbsNotice" style="font-size:12.5px;color:var(--text-dim);font-style:italic;">Belum ada halaman yang ditangkap.</div>
    </div>
  </div>

  <!-- Tombol Selesai & Generate PDF -->
  <button type="button" id="btnBuildPdf" class="btn" style="width:100%;padding:12px;font-size:15px;" disabled>
    📑 Buat Berkas PDF &amp; Unggah Dosir
  </button>
</div>

<!-- Library jsPDF Offline (Lokal) + Fallback CDN -->
<script src="<?= BASE_URL ?>/assets/js/jspdf.umd.min.js"></script>
<script>
if (typeof window.jspdf === 'undefined' && typeof window.jsPDF === 'undefined') {
  const cdnScript = document.createElement('script');
  cdnScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
  document.head.appendChild(cdnScript);
}
</script>

<!-- Skrip Utama Scan Kamera & PDF (Inline untuk menghindari isu caching browser) -->
<script>
(function () {
  const BASE_URL_JS = <?= json_encode(BASE_URL) ?>;
  const video = document.getElementById('camPreview');
  const placeholder = document.getElementById('camPlaceholder');
  const flash = document.getElementById('cameraFlash');
  const canvas = document.getElementById('camCanvas');
  const thumbs = document.getElementById('thumbs');
  const status = document.getElementById('scanStatus');
  const btnStart = document.getElementById('btnStart');
  const btnCapture = document.getElementById('btnCapture');
  const btnPickFile = document.getElementById('btnPickFile');
  const fileDocInput = document.getElementById('fileDocInput');
  const btnBuildPdf = document.getElementById('btnBuildPdf');
  const btnClearAll = document.getElementById('btnClearAll');
  const pageCountSpan = document.getElementById('pageCount');
  const noThumbsNotice = document.getElementById('noThumbsNotice');

  let stream = null;
  const captures = []; // Array Data URLs

  function setStatus(msg, type) {
    let color = 'var(--text-dim)';
    if (type === 'success') color = 'var(--ok)';
    else if (type === 'error') color = 'var(--danger)';
    else if (type === 'warn') color = 'var(--warn)';
    status.innerHTML = `<span style="color:${color};font-weight:500;">${msg}</span>`;
  }

  function updateGallery() {
    thumbs.innerHTML = '';
    pageCountSpan.textContent = captures.length;

    if (captures.length === 0) {
      thumbs.appendChild(noThumbsNotice);
      btnClearAll.style.display = 'none';
      btnBuildPdf.disabled = true;
      return;
    }

    noThumbsNotice.remove();
    btnClearAll.style.display = 'inline-block';
    btnBuildPdf.disabled = false;

    captures.forEach((dataUrl, index) => {
      const card = document.createElement('div');
      card.style.cssText = 'position:relative;display:flex;flex-direction:column;align-items:center;background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:6px;width:95px;';

      const img = document.createElement('img');
      img.src = dataUrl;
      img.style.cssText = 'width:83px;height:110px;object-fit:cover;border-radius:4px;border:1px solid var(--border);';

      const label = document.createElement('span');
      label.textContent = 'Halaman ' + (index + 1);
      label.style.cssText = 'font-size:11px;color:var(--text);margin-top:4px;font-weight:600;';

      const delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.innerHTML = '&times;';
      delBtn.title = 'Hapus halaman ini';
      delBtn.style.cssText = 'position:absolute;top:-4px;right:-4px;background:var(--danger);color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;line-height:1;box-shadow:0 2px 4px rgba(0,0,0,0.3);';
      delBtn.onclick = function () {
        captures.splice(index, 1);
        updateGallery();
        setStatus(`Halaman ${index + 1} dihapus. Tersisa ${captures.length} halaman.`, 'info');
      };

      card.appendChild(img);
      card.appendChild(label);
      card.appendChild(delBtn);
      thumbs.appendChild(card);
    });
  }

  // --- 1. KONTROL AKTIFKAN / MATIKAN KAMERA ---
  btnStart.addEventListener('click', async function () {
    if (stream) {
      // Matikan kamera jika sedang hidup
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
      video.style.display = 'none';
      placeholder.style.display = 'flex';
      btnStart.textContent = '🎥 Aktifkan Kamera';
      btnCapture.disabled = true;
      setStatus('Kamera dimatikan.', 'info');
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Browser tidak mendukung akses kamera langsung pada konteks ini. Silakan gunakan tombol <strong>"📁 Ambil/Pilih Foto"</strong>.', 'warn');
      return;
    }

    setStatus('Mengakses perangkat kamera...', 'info');

    try {
      // Coba kamera belakang (smartphone) dengan ideal constraint (tidak memaksa exact)
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1920 },
            height: { ideal: 1080 }
          }
        });
      } catch (errConstraint) {
        // Fallback untuk webcam laptop/PC standar
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
      }

      video.srcObject = stream;
      video.style.display = 'block';
      placeholder.style.display = 'none';
      await video.play();

      btnStart.textContent = '⏹️ Matikan Kamera';
      btnCapture.disabled = false;
      setStatus('✓ Kamera aktif! Arahkan dokumen ke lensa kamera lalu tekan tombol <strong>"📸 Ambil Gambar"</strong>.', 'success');
    } catch (err) {
      console.error('Kamera error:', err);
      let errMsg = err.message;
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        errMsg = 'Izin kamera ditolak. Berikan izin akses kamera di ikon gembok URL browser Anda.';
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        errMsg = 'Perangkat kamera tidak terdeteksi pada komputer ini.';
      }
      setStatus('Gagal mengakses kamera: ' + errMsg + '. Anda dapat menggunakan tombol <strong>"📁 Ambil/Pilih Foto"</strong> di samping.', 'error');
    }
  });

  // --- 2. AMBIL GAMBAR DARI KAMERA ---
  btnCapture.addEventListener('click', function () {
    if (!video.videoWidth || !video.videoHeight) {
      setStatus('Kamera belum siap merekam gambar. Pastikan video kamera terlihat.', 'warn');
      return;
    }

    // Efek flash kamera
    flash.style.opacity = '0.85';
    setTimeout(() => { flash.style.opacity = '0'; }, 120);

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    captures.push(dataUrl);
    updateGallery();
    setStatus(`✓ Halaman ke-${captures.length} berhasil ditangkap! Tambah halaman lagi atau klik <strong>"Buat Berkas PDF & Unggah"</strong>.`, 'success');
  });

  // --- 3. AMBIL / PILIH FOTO DARI FILE (HP / PC) ---
  btnPickFile.addEventListener('click', function () {
    fileDocInput.click();
  });

  fileDocInput.addEventListener('change', function () {
    const files = Array.from(this.files || []);
    if (files.length === 0) return;

    setStatus(`Memproses ${files.length} gambar terpilih...`, 'info');
    let loadedCount = 0;

    files.forEach((file) => {
      const reader = new FileReader();
      reader.onload = function (e) {
        const tempImg = new Image();
        tempImg.onload = function () {
          // Normalisasi resolusi jika terlalu besar (maksimal 2000px agar PDF ringan dan cepat)
          const maxDim = 2000;
          let w = tempImg.width;
          let h = tempImg.height;

          if (w > maxDim || h > maxDim) {
            if (w > h) {
              h = Math.round((h * maxDim) / w);
              w = maxDim;
            } else {
              w = Math.round((w * maxDim) / h);
              h = maxDim;
            }
          }

          canvas.width = w;
          canvas.height = h;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(tempImg, 0, 0, w, h);

          const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
          captures.push(dataUrl);
          loadedCount++;

          if (loadedCount === files.length) {
            updateGallery();
            setStatus(`✓ Berhasil memuat ${files.length} foto dokumen. Total ${captures.length} halaman siap digabung ke PDF!`, 'success');
          }
        };
        tempImg.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });

    this.value = '';
  });

  // --- 4. HAPUS SEMUA HALAMAN ---
  btnClearAll.addEventListener('click', function () {
    if (confirm('Bersihkan seluruh halaman dokumen yang sudah diambil?')) {
      captures.length = 0;
      updateGallery();
      setStatus('Seluruh halaman telah dibersihkan.', 'info');
    }
  });

  // --- 5. BUAT BERKAS PDF & UNGGAH KE SERVER ---
  btnBuildPdf.addEventListener('click', async function () {
    const kode = document.getElementById('kodeDosir').value;
    const keterangan = document.getElementById('keterangan').value.trim();

    if (!kode) {
      setStatus('Harap pilih Jenis Dosir terlebih dahulu pada pilihan di atas.', 'warn');
      document.getElementById('kodeDosir').focus();
      return;
    }
    if (captures.length === 0) {
      setStatus('Belum ada halaman dokumen yang ditangkap. Ambil gambar kamera atau pilih foto.', 'warn');
      return;
    }

    btnBuildPdf.disabled = true;
    btnBuildPdf.textContent = '⏳ Menggabungkan Halaman ke PDF...';
    setStatus('Sedang mengonversi ' + captures.length + ' halaman ke format PDF A4 standar...');

    try {
      const JsPDFConstructor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : window.jsPDF;
      if (!JsPDFConstructor) {
        throw new Error('Library jsPDF belum selesai dimuat. Silakan tunggu 2 detik atau muat ulang halaman.');
      }

      const pdf = new JsPDFConstructor({ orientation: 'portrait', unit: 'mm', format: 'a4' });
      const pageW = pdf.internal.pageSize.getWidth();  // 210 mm
      const pageH = pdf.internal.pageSize.getHeight(); // 297 mm
      const margin = 10;
      const printableW = pageW - (margin * 2);
      const printableH = pageH - (margin * 2);

      for (let i = 0; i < captures.length; i++) {
        if (i > 0) pdf.addPage();

        const img = new Image();
        await new Promise((resolve, reject) => {
          img.onload = resolve;
          img.onerror = reject;
          img.src = captures[i];
        });

        // Hitung rasio pas proporsional ke halaman A4
        const ratio = Math.min(printableW / img.width, printableH / img.height);
        const drawW = img.width * ratio;
        const drawH = img.height * ratio;
        const drawX = margin + (printableW - drawW) / 2;
        const drawY = margin + (printableH - drawH) / 2;

        pdf.addImage(img, 'JPEG', drawX, drawY, drawW, drawH, undefined, 'FAST');
      }

      setStatus('PDF berhasil dibuat! Mengunggah ke server...', 'info');
      btnBuildPdf.textContent = '⏳ Mengunggah Berkas ke Server...';
      const blob = pdf.output('blob');

      const formData = new FormData();
      formData.append('csrf_token', <?= json_encode(csrf_token()) ?>);
      formData.append('kode', kode);
      formData.append('keterangan', keterangan);
      formData.append('berkas', blob, 'scan_' + kode + '.pdf');

      const res = await fetch(BASE_URL_JS + '/personel/upload_process.php', {
        method: 'POST',
        body: formData,
      });

      if (res.ok || res.redirected) {
        setStatus('✓ Berkas PDF berhasil diunggah! Mengalihkan ke Dashboard...', 'success');
        if (stream) stream.getTracks().forEach((t) => t.stop());
        setTimeout(() => {
          window.location.href = BASE_URL_JS + '/personel/dashboard.php';
        }, 600);
      } else {
        throw new Error('Gagal mengunggah ke server (Status HTTP ' + res.status + ')');
      }
    } catch (err) {
      console.error('Scan Error:', err);
      setStatus('Terjadi kesalahan: ' + err.message, 'error');
      btnBuildPdf.disabled = false;
      btnBuildPdf.textContent = '📑 Buat Berkas PDF & Unggah Dosir';
    }
  });

  window.addEventListener('beforeunload', () => {
    if (stream) stream.getTracks().forEach((t) => t.stop());
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
