<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Deteksi Bahasa Isyarat SIBI (CNN)</title>
    <!-- TensorFlow.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.20.0/dist/tf.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: #0f172a; color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 16px; }
        .container { width: 100%; max-width: 540px; display: flex; flex-direction: column; gap: 14px; }
        header { text-align: center; }
        header h1 { font-size: 1.25rem; font-weight: 700; color: #38bdf8; }
        header p { font-size: 0.85rem; color: #94a3b8; margin-top: 2px; }

        /* Area Kamera & Kotak Panduan ROI */
        .camera-box { position: relative; width: 100%; aspect-ratio: 4/3; background: #1e293b; border-radius: 16px; overflow: hidden; border: 2px solid #334155; }
        video, #preview-img { width: 100%; height: 100%; object-fit: cover; display: block; }
        video.mirrored { transform: scaleX(-1); }
        #preview-img { display: none; object-fit: contain; background: #000; }

        .roi-overlay {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 62%; aspect-ratio: 1/1;
            border: 3px dashed #38bdf8; border-radius: 16px;
            box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.55);
            pointer-events: none;
            display: flex; align-items: flex-end; justify-content: center; padding-bottom: 8px;
        }
        .roi-label { background: rgba(15, 23, 42, 0.8); color: #38bdf8; font-size: 0.72rem; padding: 3px 10px; border-radius: 999px; }

        /* Kartu Hasil Prediksi */
        .result-card { background: #1e293b; border-radius: 16px; padding: 16px; border: 1px solid #334155; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .main-pred { display: flex; align-items: center; gap: 14px; }
        .letter-badge { width: 68px; height: 68px; border-radius: 14px; background: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 2.4rem; font-weight: 800; color: #fff; }
        .pred-info h2 { font-size: 1.05rem; color: #e2e8f0; }
        .pred-info span { font-size: 0.85rem; color: #38bdf8; font-weight: 600; }

        .top3-list { display: flex; flex-direction: column; gap: 4px; min-width: 130px; font-size: 0.8rem; }
        .top3-item { display: flex; justify-content: space-between; background: #0f172a; padding: 4px 10px; border-radius: 6px; color: #cbd5e1; }

        /* Kontrol & Status */
        .controls { display: flex; flex-wrap: wrap; gap: 8px; }
        button, .file-btn { flex: 1; min-width: 140px; padding: 11px; border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; background: #334155; color: #f8fafc; transition: 0.2s; text-align: center; }
        button:hover, .file-btn:hover { background: #475569; }
        button.primary { background: #0284c7; }
        button.primary:hover { background: #0369a1; }
        .status { text-align: center; font-size: 0.8rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Penerjemah Huruf SIBI (CNN)</h1>
            <p>Arahkan tangan di dalam kotak biru berlatar polos</p>
        </header>

        <div class="camera-box">
            <video id="webcam" autoplay playsinline muted class="mirrored"></video>
            <img id="preview-img" alt="Preview Uji Gambar">
            <div class="roi-overlay" id="roi-box">
                <span class="roi-label">Area Deteksi Tangan</span>
            </div>
        </div>

        <div class="result-card">
            <div class="main-pred">
                <div class="letter-badge" id="pred-letter">-</div>
                <div class="pred-info">
                    <h2>Huruf Terdeteksi</h2>
                    <span id="pred-conf">Menunggu model...</span>
                </div>
            </div>
            <div class="top3-list" id="top3-container">
                <div class="top3-item"><span>1. -</span><span>0%</span></div>
                <div class="top3-item"><span>2. -</span><span>0%</span></div>
                <div class="top3-item"><span>3. -</span><span>0%</span></div>
            </div>
        </div>

        <div class="controls">
            <button id="btn-switch">Ganti Kamera</button>
            <label class="file-btn">
                Uji Foto Tangan
                <input type="file" id="img-upload" accept="image/*" style="display:none;">
            </label>
            <button id="btn-toggle" class="primary">Jeda Deteksi</button>
        </div>

        <div class="status" id="status-text">Memuat model CNN TensorFlow.js...</div>
    </div>

    <canvas id="proc-canvas" width="128" height="128" style="display:none;"></canvas>

    <script>
        const CLASS_NAMES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'];

        const video = document.getElementById('webcam');
        const previewImg = document.getElementById('preview-img');
        const roiBox = document.getElementById('roi-box');
        const canvas = document.getElementById('proc-canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        const predLetterEl = document.getElementById('pred-letter');
        const predConfEl = document.getElementById('pred-conf');
        const top3Container = document.getElementById('top3-container');
        const statusText = document.getElementById('status-text');
        const btnSwitch = document.getElementById('btn-switch');
        const btnToggle = document.getElementById('btn-toggle');
        const imgUpload = document.getElementById('img-upload');

        let model = null;
        let currentStream = null;
        let facingMode = 'user';
        let isRunning = true;
        let isImageMode = false;

        // 1. Inisialisasi Kamera
        async function startCamera() {
            isImageMode = false;
            previewImg.style.display = 'none';
            video.style.display = 'block';
            roiBox.style.display = 'flex';

            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
            }
            try {
                const constraints = {
                    audio: false,
                    video: {
                        facingMode: facingMode,
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    }
                };
                currentStream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = currentStream;

                if (facingMode === 'user') {
                    video.classList.add('mirrored');
                } else {
                    video.classList.remove('mirrored');
                }

                await new Promise(resolve => video.onloadedmetadata = resolve);
                statusText.textContent = 'Kamera aktif. Siap mendeteksi secara real-time.';
            } catch (err) {
                statusText.textContent = 'Kamera tidak tersedia/sedang dipakai aplikasi lain. Gunakan tombol "Uji Foto Tangan" atau tutup aplikasi kamera lain.';
                console.warn('Info Kamera:', err.message);
            }
        }

        // 2. Custom Loader untuk menormalisasi struktur Keras 3 -> TensorFlow.js
        async function loadKeras3Model(modelUrl) {
            const response = await fetch(modelUrl);
            if (!response.ok) throw new Error(`HTTP ${response.status} saat mengambil model.json`);
            const modelJson = await response.json();

            // Perbaiki format InputLayer Keras 3 (batch_shape -> batchInputShape)
            const layers = modelJson.modelTopology?.config?.layers || [];
            layers.forEach(layer => {
                if (layer.class_name === 'InputLayer' && layer.config) {
                    if (layer.config.batch_shape && !layer.config.batchInputShape) {
                        layer.config.batchInputShape = layer.config.batch_shape;
                    }
                }
                // Normalisasi dtype policy Keras 3 (object DTypePolicy -> string 'float32')
                if (layer.config && typeof layer.config.dtype === 'object' && layer.config.dtype !== null) {
                    layer.config.dtype = layer.config.dtype.config?.name || 'float32';
                }
            });

            // Perbaiki nama weight manifest jika memakai prefix path panjang
            const baseUrl = modelUrl.substring(0, modelUrl.lastIndexOf('/') + 1);
            const manifest = modelJson.weightsManifest || [];
            const weightSpecs = [];
            const buffers = [];

            for (const group of manifest) {
                for (const w of group.weights) {
                    // Ambil 2 segmen terakhir nama weight agar cocok dengan nama layer TF.js (misal: "conv2d/kernel")
                    const parts = w.name.split('/');
                    const cleanName = parts.length >= 2 ? parts.slice(-2).join('/') : w.name;
                    weightSpecs.push({ ...w, name: cleanName });
                }
                for (const path of group.paths) {
                    const binRes = await fetch(baseUrl + path);
                    if (!binRes.ok) throw new Error(`Gagal mengunduh bobot ${path}`);
                    buffers.push(await binRes.arrayBuffer());
                }
            }

            // Gabungkan buffer bobot
            const totalBytes = buffers.reduce((sum, b) => sum + b.byteLength, 0);
            const merged = new Uint8Array(totalBytes);
            let offset = 0;
            for (const b of buffers) {
                merged.set(new Uint8Array(b), offset);
                offset += b.byteLength;
            }

            // Muat model langsung dari memori menggunakan IOHandler kustom
            return await tf.loadLayersModel({
                load: async () => ({
                    modelTopology: modelJson.modelTopology,
                    weightSpecs: weightSpecs,
                    weightData: merged.buffer
                })
            });
        }

        async function loadModel() {
            try {
                const url = "{{ asset('models/sibi/model.json') }}";
                model = await loadKeras3Model(url);

                // Warm-up prediksi pertama
                tf.tidy(() => {
                    const dummy = tf.zeros([1, 128, 128, 1]);
                    model.predict(dummy);
                });
                predConfEl.textContent = 'Model siap!';
                statusText.textContent = 'Model CNN berhasil dimuat!';
            } catch (err) {
                statusText.textContent = 'Error memuat model: ' + err.message;
                console.error(err);
            }
        }

        // Fungsi Inferensi dari Canvas 128x128
        function runInference() {
            if (!model) return;
            const probs = tf.tidy(() => {
                const rgbTensor = tf.browser.fromPixels(canvas);
                const grayTensor = rgbTensor.mean(2).expandDims(0).expandDims(-1);
                const prediction = model.predict(grayTensor);
                return prediction.dataSync();
            });

            const ranked = Array.from(probs)
                .map((prob, idx) => ({ label: CLASS_NAMES[idx], prob }))
                .sort((a, b) => b.prob - a.prob);

            const top1 = ranked[0];
            predLetterEl.textContent = top1.label;
            predConfEl.textContent = `Keyakinan: ${(top1.prob * 100).toFixed(1)}%`;

            top3Container.innerHTML = ranked.slice(0, 3).map((item, i) => `
                <div class="top3-item">
                    <span>${i + 1}. Huruf ${item.label}</span>
                    <span>${(item.prob * 100).toFixed(1)}%</span>
                </div>
            `).join('');
        }

        // 3. Loop Prediksi Real-Time Webcam
        async function predictLoop() {
            if (model && isRunning && !isImageMode && video.readyState === 4) {
                const vw = video.videoWidth;
                const vh = video.videoHeight;
                const size = Math.min(vw, vh) * 0.62;
                const sx = (vw - size) / 2;
                const sy = (vh - size) / 2;

                ctx.save();
                if (facingMode === 'user') {
                    ctx.translate(128, 0);
                    ctx.scale(-1, 1);
                }
                ctx.drawImage(video, sx, sy, size, size, 0, 0, 128, 128);
                ctx.restore();

                runInference();
            }
            setTimeout(() => requestAnimationFrame(predictLoop), 120);
        }

        // 4. Handler Uji Foto Tangan
        imgUpload.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => {
                isImageMode = true;
                video.style.display = 'none';
                roiBox.style.display = 'none';
                previewImg.src = ev.target.result;
                previewImg.style.display = 'block';
                previewImg.onload = () => {
                    ctx.drawImage(previewImg, 0, 0, 128, 128);
                    runInference();
                    statusText.textContent = 'Mode uji foto aktif. Klik "Ganti Kamera" untuk kembali ke webcam.';
                };
            };
            reader.readAsDataURL(file);
        });

        btnSwitch.addEventListener('click', () => {
            if (!isImageMode) {
                facingMode = (facingMode === 'user') ? 'environment' : 'user';
            }
            startCamera();
        });

        btnToggle.addEventListener('click', () => {
            isRunning = !isRunning;
            btnToggle.textContent = isRunning ? 'Jeda Deteksi' : 'Lanjutkan Deteksi';
            if (!isRunning) predConfEl.textContent = 'Deteksi dijeda';
        });

        (async function init() {
            await startCamera();
            await loadModel();
            predictLoop();
        })();
    </script>
</body>
</html>