(function () {
  const video = document.getElementById('camPreview');
  const placeholder = document.getElementById('camPlaceholder');
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
  const captures = []; // Array of Data URLs

  function setStatus(msg, type = 'info') {
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
      card.style.cssText = 'position:relative;display:flex;flex-direction:column;align-items:center;background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:6px;width:100px;';

      const img = document.createElement('img');
      img.src = dataUrl;
      img.style.cssText = 'width:88px;height:115px;object-fit:cover;border-radius:4px;border:1px solid var(--border);';

      const label = document.createElement('span');
      label.textContent = 'Hal ' + (index + 1);
      label.style.cssText = 'font-size:11px;color:var(--text-dim);margin-top:4px;font-weight:600;';

      const delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.innerHTML = '&times;';
      delBtn.title = 'Hapus halaman ini';
      delBtn.style.cssText = 'position:absolute;top:2px;right:2px;background:var(--danger);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;line-height:1;';
      delBtn.onclick = function () {
        captures.splice(index, 1);
        updateGallery();
        setStatus(`Halaman ${index + 1} dihapus. Tersisa ${captures.length} halaman.`);
      };

      card.appendChild(img);
      card.appendChild(label);
      card.appendChild(delBtn);
      thumbs.appendChild(card);
    });
  }

  // --- 1. KONTROL KAMERA ---
  btnStart.addEventListener('click', async function () {
    if (stream) {
      // Matikan kamera jika sedang aktif
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
      video.style.display = 'none';
      placeholder.style.display = 'flex';
      btnStart.textContent = '🎥 Aktifkan Kamera';
      btnCapture.disabled = true;
      setStatus('Kamera dinonaktifkan.');
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Browser atau perangkat Anda tidak mendukung akses kamera langsung (biasanya membutuhkan HTTPS atau localhost). Silakan gunakan tombol <strong>"📁 Ambil/Pilih Foto"</strong>.', 'warn');
      return;
    }

    setStatus('Mengakses perangkat kamera...', 'info');

    try {
      // Prioritaskan kamera belakang (khusus smartphone/tablet) dengan ideal constraint (bukan exact)
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1920 },
            height: { ideal: 1080 }
          }
        });
      } catch (errEnvironment) {
        // Fallback ke kamera apa pun yang tersedia (Webcam laptop/PC)
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
      }

      video.srcObject = stream;
      await video.play();

      placeholder.style.display = 'none';
      video.style.display = 'block';
      btnStart.textContent = '⏹️ Matikan Kamera';
      btnCapture.disabled = false;
      setStatus('Kamera aktif! Arahkan dokumen ke lensa kamera lalu tekan "Ambil Gambar".', 'success');
    } catch (err) {
      console.error('Kamera gagal diakses:', err);
      let errMsg = err.message;
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        errMsg = 'Izin akses kamera ditolak oleh browser. Berikan izin kamera di pengaturan browser.';
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        errMsg = 'Perangkat kamera tidak ditemukan pada komputer/perangkat ini.';
      }
      setStatus('Gagal mengakses kamera: ' + errMsg + '. Anda dapat menggunakan tombol <strong>"📁 Ambil/Pilih Foto"</strong> sebagai alternatif.', 'error');
    }
  });

  // --- 2. AMBIL GAMBAR DARI KAMERA ---
  btnCapture.addEventListener('click', function () {
    if (!video.videoWidth || !video.videoHeight) {
      setStatus('Kamera belum siap mengambil gambar. Pastikan kamera sudah aktif.', 'warn');
      return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    captures.push(dataUrl);
    updateGallery();
    setStatus(`✓ Halaman ke-${captures.length} berhasil diambil! Tambah halaman lagi atau klik "Buat Berkas PDF".`, 'success');
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
        // Gambar ke canvas untuk normalisasi ukuran jika terlalu besar
        const tempImg = new Image();
        tempImg.onload = function () {
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
            setStatus(`✓ Berhasil memuat ${files.length} foto dokumen. Total ${captures.length} halaman siap dijadikan PDF!`, 'success');
          }
        };
        tempImg.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });

    this.value = ''; // Reset file input
  });

  // --- 4. HAPUS SEMUA HALAMAN ---
  btnClearAll.addEventListener('click', function () {
    if (confirm('Hapus seluruh halaman yang sudah diambil?')) {
      captures.length = 0;
      updateGallery();
      setStatus('Seluruh halaman telah dibersihkan.');
    }
  });

  // --- 5. BUAT PDF DAN UNGGAH ---
  btnBuildPdf.addEventListener('click', async function () {
    const kode = document.getElementById('kodeDosir').value;
    const keterangan = document.getElementById('keterangan').value.trim();

    if (!kode) {
      setStatus('Harap pilih Jenis Dosir terlebih dahulu pada dropdown di atas.', 'warn');
      document.getElementById('kodeDosir').focus();
      return;
    }
    if (captures.length === 0) {
      setStatus('Belum ada foto halaman yang diambil. Silakan ambil gambar kamera atau pilih foto.', 'warn');
      return;
    }

    btnBuildPdf.disabled = true;
    btnBuildPdf.textContent = '⏳ Memproses & Membuat Berkas PDF...';
    setStatus('Sedang mengonversi ' + captures.length + ' halaman ke format PDF A4 standar...');

    try {
      // Ambil constructor jsPDF
      const JsPDFConstructor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : window.jsPDF;
      if (!JsPDFConstructor) {
        throw new Error('Library jsPDF gagal dimuat. Pastikan berkas assets/js/jspdf.umd.min.js tersedia.');
      }

      const pdf = new JsPDFConstructor({ orientation: 'portrait', unit: 'mm', format: 'a4' });
      const pageW = pdf.internal.pageSize.getWidth();  // 210 mm
      const pageH = pdf.internal.pageSize.getHeight(); // 297 mm
      const margin = 10; // 10mm margin
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

      setStatus('PDF berhasil dibuat! Sedang mengunggah ke server...', 'info');
      const blob = pdf.output('blob');

      const formData = new FormData();
      formData.append('kode', kode);
      formData.append('keterangan', keterangan);
      formData.append('berkas', blob, 'scan_' + kode + '.pdf');

      const res = await fetch(window.BASE_URL_JS + '/personel/upload_process.php', {
        method: 'POST',
        body: formData,
      });

      if (res.ok || res.redirected) {
        setStatus('✓ Berkas PDF berhasil diunggah! Mengalihkan ke dashboard...', 'success');
        if (stream) stream.getTracks().forEach((t) => t.stop());
        setTimeout(() => {
          window.location.href = window.BASE_URL_JS + '/personel/dashboard.php';
        }, 600);
      } else {
        throw new Error('Server mengembalikan status HTTP ' + res.status);
      }
    } catch (err) {
      console.error('Build PDF / Upload Error:', err);
      setStatus('Terjadi kesalahan: ' + err.message, 'error');
      btnBuildPdf.disabled = false;
      btnBuildPdf.textContent = '📑 Buat Berkas PDF & Unggah Dosir';
    }
  });

  // Hentikan kamera jika user meninggalkan halaman
  window.addEventListener('beforeunload', () => {
    if (stream) stream.getTracks().forEach((t) => t.stop());
  });
})();
