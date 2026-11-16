<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SIBI Vision — Deteksi Bahasa Isyarat</title>
    <!-- Typography sesuai kontrak DESIGN.md -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Raleway:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- TensorFlow.js -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.20.0/dist/tf.min.js"></script>
    <style>
        :root {
            /* Color Palette */
            --bg-canvas:      #111821;
            --bg-surface:     #1B252F;
            --bg-elevated:    #26323E;
            --border-subtle:  #2C3844;
            --text-primary:   #FFFFFF;
            --text-muted:     #9BA8B5;
            --accent-primary: #6B47FF;
            --accent-hover:   #7C5CFF;
            --accent-soft:    rgba(107, 71, 255, 0.16);
            --status-success: #34D399;
            --status-warning: #FBBF24;
            --status-danger:  #F87171;

            /* Typography */
            --font-ui:   'Raleway', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, monospace;

            --fs-app-title:  28px;
            --fs-tab:        18px;
            --fs-card-title: 20px;
            --fs-body:       16px;
            --fs-small:      12px;
            --fs-data-xl:    40px;
            --fs-data-md:    20px;

            /* Geometry & Elevation */
            --radius-card:   12px;
            --radius-tile:   14px;
            --radius-pill:   999px;
            --radius-circle: 50%;

            --border-width: 1px;
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 16px;
            --space-4: 24px;
            --space-5: 32px;

            --shadow-card: 0 6px 20px rgba(0, 0, 0, 0.35);
            --shadow-tile: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-primary);
            font-family: var(--font-ui);
            font-size: var(--fs-body);
            line-height: 1.4;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        :focus-visible {
            outline: 2px solid var(--accent-hover);
            outline-offset: 2px;
        }

        /* 1. Top Bar */
        .topbar {
            height: 68px;
            background-color: var(--bg-canvas);
            padding: 0 var(--space-5);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .brand-logo {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-circle);
            background-color: var(--accent-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
        }

        .app-title {
            font-size: var(--fs-app-title);
            font-weight: 500;
            letter-spacing: 0.01em;
            color: var(--text-primary);
        }

        .topbar-meta {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--fs-small);
            color: var(--text-muted);
        }

        /* 2. Tab Bar */
        .tabbar {
            background-color: var(--bg-elevated);
            padding: 0 var(--space-5);
            display: flex;
            align-items: center;
            gap: var(--space-5);
            height: 52px;
        }

        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-family: var(--font-ui);
            font-size: var(--fs-tab);
            font-weight: 500;
            letter-spacing: 0.01em;
            height: 100%;
            min-width: 44px;
            cursor: pointer;
            position: relative;
            display: inline-flex;
            align-items: center;
            transition: color 150ms ease-out;
        }

        .tab-btn:hover {
            color: var(--text-primary);
        }

        .tab-btn.active {
            color: var(--text-primary);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 25%;
            width: 50%;
            height: 4px;
            background-color: var(--accent-primary);
            border-radius: var(--radius-pill);
        }

        /* 3. Content Grid (12 Kolom) */
        .main-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: var(--space-4);
            margin: var(--space-4) var(--space-5);
            flex: 1;
        }

        .card {
            background-color: var(--bg-surface);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: var(--space-4);
            display: flex;
            flex-direction: column;
        }

        .card-title {
            font-size: var(--fs-card-title);
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--text-primary);
            text-align: center;
            margin-bottom: var(--space-3);
        }

        /* Area Kamera: Kolom 8 dari 12 */
        .camera-col {
            grid-column: span 8;
            justify-content: space-between;
            max-height: calc(100vh - 68px - 52px - 48px);
        }

        .video-wrapper {
            position: relative;
            width: 100%;
            flex: 1;
            min-height: 320px;
            background-color: #000000;
            border-radius: var(--radius-card);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        video, #preview-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        video.mirrored {
            transform: scaleX(-1);
        }

        #preview-img {
            display: none;
        }

        /* Overlay Bounding Box di atas video via Canvas */
        #overlay-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        /* Chip status di pojok kartu kamera */
        .status-chip {
            position: absolute;
            top: var(--space-3);
            left: var(--space-3);
            background-color: var(--bg-surface);
            border-radius: var(--radius-pill);
            padding: var(--space-1) 12px;
            font-size: var(--fs-small);
            font-weight: 500;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--text-muted);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: var(--radius-circle);
            background-color: var(--text-muted);
        }

        .status-chip.live {
            color: var(--status-success);
        }
        .status-chip.live .status-dot {
            background-color: var(--status-success);
        }
        .status-chip.danger {
            color: var(--status-danger);
        }
        .status-chip.danger .status-dot {
            background-color: var(--status-danger);
        }

        /* Bar Kontrol Bawah Video */
        .controls-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-3);
            margin-top: var(--space-3);
            flex-wrap: wrap;
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        /* Tombol Power Bulat Solid Aksen */
        .btn-power {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-circle);
            background-color: var(--accent-primary);
            border: none;
            color: var(--text-primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 150ms ease-out;
            flex-shrink: 0;
        }

        .btn-power:hover {
            background-color: var(--accent-hover);
        }

        .btn-power.paused {
            background-color: var(--bg-elevated);
            color: var(--text-muted);
        }

        /* Sub-tile Kontrol & Toggle */
        .control-tile {
            background-color: var(--bg-elevated);
            border-radius: var(--radius-tile);
            box-shadow: var(--shadow-tile);
            padding: var(--space-2) var(--space-3);
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            gap: var(--space-3);
            font-size: var(--fs-small);
            color: var(--text-primary);
            cursor: pointer;
            border: none;
            font-family: var(--font-ui);
        }

        .control-tile svg {
            color: var(--accent-primary);
            flex-shrink: 0;
        }

        /* Toggle Pill (84px x 36px, knob putih 28px) */
        .toggle-wrap {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--fs-small);
            color: var(--text-muted);
        }

        .toggle-pill {
            width: 84px;
            height: 36px;
            border-radius: var(--radius-pill);
            background-color: var(--bg-elevated);
            border: none;
            padding: 4px;
            cursor: pointer;
            position: relative;
            transition: background-color 150ms ease-out;
        }

        .toggle-pill.active {
            background-color: var(--accent-primary);
        }

        .toggle-knob {
            width: 28px;
            height: 28px;
            border-radius: var(--radius-circle);
            background-color: var(--text-primary);
            display: block;
            transform: translateX(0);
            transition: transform 150ms ease-out;
        }

        .toggle-pill.active .toggle-knob {
            transform: translateX(48px);
        }

        /* Slider Threshold */
        .threshold-control {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--fs-small);
            color: var(--text-muted);
        }

        .threshold-control input[type="range"] {
            accent-color: var(--accent-primary);
            width: 90px;
            cursor: pointer;
            min-height: 44px;
        }

        .mono-val {
            font-family: var(--font-mono);
            font-variant-numeric: tabular-nums;
            color: var(--text-primary);
        }

        /* Panel Hasil: Kolom 4 dari 12 */
        .result-col {
            grid-column: span 4;
            min-width: 320px;
            max-height: calc(100vh - 68px - 52px - 48px);
            overflow-y: auto;
            justify-content: space-between;
            gap: var(--space-4);
        }

        /* Sub-tile Hasil Utama + Gauge Busur Setengah Lingkaran */
        .primary-tile {
            background-color: var(--bg-elevated);
            border-radius: var(--radius-tile);
            box-shadow: var(--shadow-tile);
            padding: var(--space-4);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--space-2);
        }

        .gauge-wrap {
            position: relative;
            width: 180px;
            height: 100px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .gauge-svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 180px;
            height: 100px;
        }

        .gauge-track {
            fill: none;
            stroke: var(--border-subtle);
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 4 6;
        }

        .gauge-fill {
            fill: none;
            stroke: var(--accent-primary);
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 226;
            stroke-dashoffset: 226;
            transition: stroke-dashoffset 180ms ease-out;
        }

        .primary-letter {
            font-family: var(--font-mono);
            font-size: var(--fs-data-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1;
            color: var(--text-primary);
            z-index: 1;
        }

        .primary-conf-row {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            margin-top: var(--space-1);
        }

        .conf-dot {
            width: 8px;
            height: 8px;
            border-radius: var(--radius-circle);
            background-color: var(--text-muted);
        }

        .primary-conf {
            font-family: var(--font-mono);
            font-size: var(--fs-data-md);
            font-weight: 500;
            font-variant-numeric: tabular-nums;
            min-width: 6ch;
            color: var(--text-primary);
        }

        .tile-subtext {
            font-size: var(--fs-small);
            letter-spacing: 0.02em;
            color: var(--accent-primary);
        }

        .empty-state {
            font-size: var(--fs-small);
            color: var(--text-muted);
            text-align: center;
            padding: var(--space-2) 0;
        }

        /* Daftar Kelas Kandidat (Top-4) */
        .candidate-list {
            display: flex;
            flex-direction: column;
        }

        .candidate-row {
            padding: 12px 0;
            border-bottom: var(--border-width) solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .candidate-row:last-child {
            border-bottom: none;
        }

        .candidate-top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .candidate-name {
            font-size: var(--fs-body);
            font-weight: 400;
            color: var(--text-primary);
        }

        .candidate-pct {
            font-family: var(--font-mono);
            font-size: var(--fs-data-md);
            font-weight: 500;
            font-variant-numeric: tabular-nums;
            min-width: 6ch;
            text-align: right;
            color: var(--text-primary);
        }

        .bar-track {
            width: 100%;
            height: 6px;
            background-color: var(--bg-elevated);
            border-radius: var(--radius-pill);
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            width: 0%;
            background-color: var(--accent-primary);
            border-radius: var(--radius-pill);
            transition: width 160ms ease-out;
        }

        /* Sub-tile Preview Input Tensor 128x128 & Telemetry Footer */
        .telemetry-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: var(--space-3);
            border-top: var(--border-width) solid var(--border-subtle);
        }

        .input-preview-tile {
            background-color: var(--bg-elevated);
            border-radius: var(--radius-tile);
            box-shadow: var(--shadow-tile);
            padding: var(--space-2);
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        #proc-canvas {
            width: 44px;
            height: 44px;
            border-radius: 6px;
            background-color: #000000;
            image-rendering: pixelated;
        }

        .preview-meta {
            display: flex;
            flex-direction: column;
            font-size: var(--fs-small);
            color: var(--text-muted);
        }

        .preview-meta span:last-child {
            color: var(--accent-primary);
        }

        .perf-telemetry {
            font-family: var(--font-mono);
            font-size: var(--fs-small);
            font-variant-numeric: tabular-nums;
            color: var(--text-muted);
            text-align: right;
        }

        /* Responsif di bawah 1024px: Stack Vertikal */
        @media (max-width: 1024px) {
            .topbar, .tabbar {
                padding: 0 var(--space-3);
            }
            .main-grid {
                margin: var(--space-3);
            }
            .camera-col, .result-col {
                grid-column: span 12;
                max-height: none;
                min-width: 0;
            }
            .video-wrapper {
                aspect-ratio: 4 / 3;
            }
        }
    </style>
</head>
<body>
    <!-- 1. Top Bar -->
    <header class="topbar">
        <div class="brand">
            <div class="brand-logo" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M18 11V6a2 2 0 0 0-2-2 2 2 0 0 0-2 2v0"/>
                    <path d="M14 10V4a2 2 0 0 0-2-2 2 2 0 0 0-2 2v2"/>
                    <path d="M10 10.5V6a2 2 0 0 0-2-2 2 2 0 0 0-2 2v8"/>
                    <path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/>
                </svg>
            </div>
            <h1 class="app-title">SIBI Vision</h1>
        </div>
        <div class="topbar-meta">
            <span>CNN 128×128 Grayscale</span>
            <span>·</span>
            <span>24 Kelas Abjad</span>
        </div>
    </header>

    <!-- 2. Tab Bar -->
    <nav class="tabbar" aria-label="Mode Sumber Input">
        <button class="tab-btn active" id="tab-live" type="button">Kamera Langsung</button>
        <button class="tab-btn" id="tab-static" type="button">Uji Citra Statis</button>
        <input type="file" id="img-upload" accept="image/*" style="display:none;">
    </nav>

    <!-- 3. Content Grid (12 Kolom: 8 Kamera + 4 Panel Hasil) -->
    <main class="main-grid">
        <!-- Kartu Kamera (Kolom 8) -->
        <section class="card camera-col">
            <h2 class="card-title">Sensor Visual</h2>

            <div class="video-wrapper" id="video-wrapper">
                <video id="webcam" autoplay playsinline muted class="mirrored"></video>
                <img id="preview-img" alt="Citra uji bahasa isyarat SIBI">
                <canvas id="overlay-canvas"></canvas>

                <div class="status-chip" id="status-chip">
                    <span class="status-dot"></span>
                    <span id="status-text">Memuat model</span>
                </div>
            </div>

            <!-- Bar Kontrol Bawah Video -->
            <div class="controls-bar">
                <div class="control-group">
                    <button class="btn-power" id="btn-power" type="button" title="Mulai / Hentikan Inferensi" aria-label="Mulai atau hentikan inferensi">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v10"/>
                            <path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>
                        </svg>
                    </button>

                    <button class="control-tile" id="btn-switch-cam" type="button">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M20 7h-3l-2-2H9L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/>
                            <path d="m15 13-3-3-3 3"/>
                        </svg>
                        <span>Ganti Kamera</span>
                    </button>

                    <label class="control-tile" for="img-upload">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="9" cy="9" r="2"/>
                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                        </svg>
                        <span>Pilih Berkas</span>
                    </label>
                </div>

                <div class="control-group">
                    <div class="threshold-control">
                        <label for="threshold-slider">Ambang</label>
                        <input type="range" id="threshold-slider" min="30" max="90" value="50">
                        <span class="mono-val" id="threshold-val">50%</span>
                    </div>

                    <div class="toggle-wrap">
                        <span>Stabilizer</span>
                        <button class="toggle-pill active" id="toggle-smooth" type="button" role="switch" aria-checked="true" aria-label="Aktifkan penstabil prediksi">
                            <span class="toggle-knob"></span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Panel Hasil (Kolom 4) -->
        <aside class="card result-col">
            <div>
                <h2 class="card-title">Hasil Klasifikasi</h2>

                <!-- Sub-tile Utama dengan Gauge Busur Setengah Lingkaran -->
                <div class="primary-tile">
                    <div class="gauge-wrap">
                        <svg class="gauge-svg" viewBox="0 0 180 100" aria-hidden="true">
                            <path class="gauge-track" d="M 18 90 A 72 72 0 0 1 162 90"/>
                            <path class="gauge-fill" id="gauge-fill" d="M 18 90 A 72 72 0 0 1 162 90"/>
                        </svg>
                        <div class="primary-letter" id="primary-letter">--</div>
                    </div>

                    <div class="primary-conf-row">
                        <span class="conf-dot" id="conf-dot"></span>
                        <span class="primary-conf" id="primary-conf">0.0%</span>
                    </div>

                    <span class="tile-subtext" id="tile-subtext">Menunggu inisialisasi model</span>
                </div>

                <div class="empty-state" id="empty-state">Belum ada objek terdeteksi</div>

                <!-- Daftar Kandidat Top-4 -->
                <div class="candidate-list" id="candidate-list">
                    <div class="candidate-row">
                        <div class="candidate-top">
                            <span class="candidate-name">Kandidat 1</span>
                            <span class="candidate-pct">0.0%</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill"></div></div>
                    </div>
                    <div class="candidate-row">
                        <div class="candidate-top">
                            <span class="candidate-name">Kandidat 2</span>
                            <span class="candidate-pct">0.0%</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill"></div></div>
                    </div>
                    <div class="candidate-row">
                        <div class="candidate-top">
                            <span class="candidate-name">Kandidat 3</span>
                            <span class="candidate-pct">0.0%</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill"></div></div>
                    </div>
                    <div class="candidate-row">
                        <div class="candidate-top">
                            <span class="candidate-name">Kandidat 4</span>
                            <span class="candidate-pct">0.0%</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill"></div></div>
                    </div>
                </div>
            </div>

            <!-- Footer Telemetri & Monitor Input Tensor -->
            <div class="telemetry-footer">
                <div class="input-preview-tile">
                    <canvas id="proc-canvas" width="128" height="128"></canvas>
                    <div class="preview-meta">
                        <span>Input Tensor</span>
                        <span>128×128 px</span>
                    </div>
                </div>

                <div class="perf-telemetry" id="perf-telemetry">FPS 0 · -- ms</div>
            </div>
        </aside>
    </main>

    <script>
        const CLASS_NAMES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'];

        const video = document.getElementById('webcam');
        const previewImg = document.getElementById('preview-img');
        const videoWrapper = document.getElementById('video-wrapper');
        const overlayCanvas = document.getElementById('overlay-canvas');
        const overlayCtx = overlayCanvas.getContext('2d');
        const procCanvas = document.getElementById('proc-canvas');
        const procCtx = procCanvas.getContext('2d', { willReadFrequently: true });

        const statusChip = document.getElementById('status-chip');
        const statusText = document.getElementById('status-text');
        const tabLive = document.getElementById('tab-live');
        const tabStatic = document.getElementById('tab-static');
        const btnPower = document.getElementById('btn-power');
        const btnSwitchCam = document.getElementById('btn-switch-cam');
        const imgUpload = document.getElementById('img-upload');
        const thresholdSlider = document.getElementById('threshold-slider');
        const thresholdVal = document.getElementById('threshold-val');
        const toggleSmooth = document.getElementById('toggle-smooth');

        const gaugeFill = document.getElementById('gauge-fill');
        const primaryLetter = document.getElementById('primary-letter');
        const primaryConf = document.getElementById('primary-conf');
        const confDot = document.getElementById('conf-dot');
        const tileSubtext = document.getElementById('tile-subtext');
        const emptyState = document.getElementById('empty-state');
        const candidateList = document.getElementById('candidate-list');
        const perfTelemetry = document.getElementById('perf-telemetry');

        let model = null;
        let currentStream = null;
        let facingMode = 'user';
        let isRunning = true;
        let isStaticMode = false;
        let useSmoothing = true;
        let threshold = 0.50;

        // EMA & Throttling state (4-6x per detik sesuai kontrak 6.3)
        let emaProbs = new Float32Array(CLASS_NAMES.length);
        let lastUiUpdate = 0;
        const UI_INTERVAL_MS = 180; // ~5.5 Hz
        let frameCount = 0;
        let fpsLastTime = performance.now();
        let currentFps = 0;

        function setStatus(state, label) {
            statusChip.className = 'status-chip' + (state ? ' ' + state : '');
            statusText.textContent = label;
        }

        // Menggambar bounding box ROI di <canvas> sejajar video sesuai kontrak 6.2
        function drawDetectionOverlay(label, conf, active) {
            const rect = videoWrapper.getBoundingClientRect();
            if (overlayCanvas.width !== rect.width || overlayCanvas.height !== rect.height) {
                overlayCanvas.width = rect.width;
                overlayCanvas.height = rect.height;
            }
            overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

            if (isStaticMode) return;

            const boxSize = Math.min(overlayCanvas.width, overlayCanvas.height) * 0.58;
            const x = (overlayCanvas.width - boxSize) / 2;
            const y = (overlayCanvas.height - boxSize) / 2;

            // Warna box sesuai aturan 6.2
            let boxColor = '#9BA8B5';
            if (active && conf >= 0.80) {
                boxColor = '#34D399'; // --status-success
            } else if (active && conf >= 0.50) {
                boxColor = '#FBBF24'; // --status-warning
            } else if (active) {
                boxColor = '#F87171'; // --status-danger
            }

            // Stroke 2px, tanpa fill, radius 4px
            overlayCtx.strokeStyle = boxColor;
            overlayCtx.lineWidth = 2;
            overlayCtx.beginPath();
            overlayCtx.roundRect(x, y, boxSize, boxSize, 4);
            overlayCtx.stroke();

            // Label chip di sudut kiri-atas box jika melewati threshold
            if (active && conf >= threshold) {
                const chipText = `${label} ${conf.toFixed(2)}`;
                overlayCtx.font = '700 12px "JetBrains Mono", monospace';
                const textMetrics = overlayCtx.measureText(chipText);
                const chipW = textMetrics.width + 12;
                const chipH = 20;

                overlayCtx.fillStyle = boxColor;
                overlayCtx.fillRect(x, y - chipH, chipW, chipH);

                overlayCtx.fillStyle = '#0B1016';
                overlayCtx.textBaseline = 'middle';
                overlayCtx.fillText(chipText, x + 6, y - chipH / 2);
            }
        }

        async function startCamera() {
            isStaticMode = false;
            tabLive.classList.add('active');
            tabStatic.classList.remove('active');
            previewImg.style.display = 'none';
            video.style.display = 'block';
            emaProbs.fill(0);

            if (currentStream) {
                currentStream.getTracks().forEach(t => t.stop());
            }

            try {
                currentStream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: { facingMode: facingMode, width: { ideal: 640 }, height: { ideal: 480 } }
                });
                video.srcObject = currentStream;
                video.classList.toggle('mirrored', facingMode === 'user');
                await new Promise(resolve => video.onloadedmetadata = resolve);
                if (model) setStatus('live', 'LIVE');
                tileSubtext.textContent = 'Arahkan tangan ke dalam bingkai';
            } catch (err) {
                setStatus('danger', 'Kamera ditolak');
                tileSubtext.textContent = 'Gunakan tab Uji Citra Statis';
            }
        }

        // Custom Loader untuk model Keras 3 -> TensorFlow.js
        async function loadKeras3Model(modelUrl) {
            const response = await fetch(modelUrl);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const modelJson = await response.json();

            const layers = modelJson.modelTopology?.config?.layers || [];
            layers.forEach(layer => {
                if (layer.class_name === 'InputLayer' && layer.config) {
                    if (layer.config.batch_shape && !layer.config.batchInputShape) {
                        layer.config.batchInputShape = layer.config.batch_shape;
                    }
                }
                if (layer.config && typeof layer.config.dtype === 'object' && layer.config.dtype !== null) {
                    layer.config.dtype = layer.config.dtype.config?.name || 'float32';
                }
            });

            const baseUrl = modelUrl.substring(0, modelUrl.lastIndexOf('/') + 1);
            const manifest = modelJson.weightsManifest || [];
            const weightSpecs = [];
            const buffers = [];

            for (const group of manifest) {
                for (const w of group.weights) {
                    const parts = w.name.split('/');
                    const cleanName = parts.length >= 2 ? parts.slice(-2).join('/') : w.name;
                    weightSpecs.push({ ...w, name: cleanName });
                }
                for (const path of group.paths) {
                    const binRes = await fetch(baseUrl + path);
                    if (!binRes.ok) throw new Error('Gagal memuat bobot model');
                    buffers.push(await binRes.arrayBuffer());
                }
            }

            const totalBytes = buffers.reduce((sum, b) => sum + b.byteLength, 0);
            const merged = new Uint8Array(totalBytes);
            let offset = 0;
            for (const b of buffers) {
                merged.set(new Uint8Array(b), offset);
                offset += b.byteLength;
            }

            return await tf.loadLayersModel({
                load: async () => ({
                    modelTopology: modelJson.modelTopology,
                    weightSpecs: weightSpecs,
                    weightData: merged.buffer
                })
            });
        }

        async function loadModel() {
            setStatus('', 'Memuat model');
            try {
                const url = "/models/sibi/model.json";
                model = await loadKeras3Model(url);
                tf.tidy(() => model.predict(tf.zeros([1, 128, 128, 1])));
                if (!isStaticMode && currentStream) {
                    setStatus('live', 'LIVE');
                } else {
                    setStatus('live', 'Model siap');
                }
                tileSubtext.textContent = 'Klasifikasi aktif';
            } catch (err) {
                setStatus('danger', 'Model gagal dimuat');
                tileSubtext.textContent = err.message;
            }
        }

        function renderGrayscalePreview() {
            const imgData = procCtx.getImageData(0, 0, 128, 128);
            const d = imgData.data;
            for (let i = 0; i < d.length; i += 4) {
                const g = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
                d[i] = d[i + 1] = d[i + 2] = g;
            }
            procCtx.putImageData(imgData, 0, 0);
        }

        function updateResultUI(probs, latencyMs) {
            const ranked = Array.from(probs)
                .map((prob, idx) => ({ label: CLASS_NAMES[idx], prob }))
                .sort((a, b) => b.prob - a.prob);

            const top1 = ranked[0];
            const isDetected = top1.prob >= threshold;

            // Update Gauge (total panjang busur = 226)
            const dashOffset = 226 - (226 * (isDetected ? top1.prob : 0));
            gaugeFill.style.strokeDashoffset = dashOffset;

            if (isDetected) {
                emptyState.style.display = 'none';
                primaryLetter.textContent = top1.label;
                primaryConf.textContent = `${(top1.prob * 100).toFixed(1)}%`;

                if (top1.prob >= 0.80) {
                    confDot.style.backgroundColor = 'var(--status-success)';
                    tileSubtext.textContent = 'Confidence tinggi';
                } else if (top1.prob >= 0.50) {
                    confDot.style.backgroundColor = 'var(--status-warning)';
                    tileSubtext.textContent = 'Confidence menengah';
                } else {
                    confDot.style.backgroundColor = 'var(--status-danger)';
                    tileSubtext.textContent = 'Confidence rendah';
                }
            } else {
                emptyState.style.display = 'block';
                primaryLetter.textContent = '--';
                primaryConf.textContent = `${(top1.prob * 100).toFixed(1)}%`;
                confDot.style.backgroundColor = 'var(--text-muted)';
                tileSubtext.textContent = 'Di bawah ambang deteksi';
            }

            // Update Top-4 Kandidat
            candidateList.innerHTML = ranked.slice(0, 4).map(item => {
                const pct = (item.prob * 100).toFixed(1);
                return `
                    <div class="candidate-row">
                        <div class="candidate-top">
                            <span class="candidate-name">Huruf ${item.label}</span>
                            <span class="candidate-pct">${pct}%</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: ${pct}%"></div>
                        </div>
                    </div>
                `;
            }).join('');

            perfTelemetry.textContent = `FPS ${currentFps} · ${latencyMs.toFixed(0)} ms`;
            drawDetectionOverlay(top1.label, top1.prob, isDetected);
        }

        function inferCanvas(isSingleFrame = false) {
            if (!model) return;
            const t0 = performance.now();

            const rawProbs = tf.tidy(() => {
                const rgb = tf.browser.fromPixels(procCanvas);
                const gray = rgb.mean(2).expandDims(0).expandDims(-1);
                return model.predict(gray).dataSync();
            });

            const latency = performance.now() - t0;

            if (isSingleFrame || !useSmoothing) {
                emaProbs.set(rawProbs);
            } else {
                // Exponential Moving Average (EMA) pada nilai tampilan sesuai kontrak 6.3
                const alpha = 0.35;
                for (let i = 0; i < rawProbs.length; i++) {
                    emaProbs[i] = alpha * rawProbs[i] + (1 - alpha) * emaProbs[i];
                }
            }

            const now = performance.now();
            frameCount++;
            if (now - fpsLastTime >= 1000) {
                currentFps = frameCount;
                frameCount = 0;
                fpsLastTime = now;
            }

            // Throttle pembaruan UI angka ke ~5.5x/detik (kontrak 6.3)
            if (isSingleFrame || now - lastUiUpdate >= UI_INTERVAL_MS) {
                lastUiUpdate = now;
                updateResultUI(emaProbs, latency);
            }
        }

        function loop() {
            if (model && isRunning && !isStaticMode && video.readyState === 4) {
                const vw = video.videoWidth;
                const vh = video.videoHeight;
                const size = Math.min(vw, vh) * 0.58;
                const sx = (vw - size) / 2;
                const sy = (vh - size) / 2;

                procCtx.save();
                if (facingMode === 'user') {
                    procCtx.translate(128, 0);
                    procCtx.scale(-1, 1);
                }
                procCtx.drawImage(video, sx, sy, size, size, 0, 0, 128, 128);
                procCtx.restore();

                renderGrayscalePreview();
                inferCanvas(false);
            } else if (!isRunning && !isStaticMode) {
                drawDetectionOverlay('', 0, false);
            }
            requestAnimationFrame(loop);
        }

        // Event Listeners
        btnPower.addEventListener('click', () => {
            isRunning = !isRunning;
            btnPower.classList.toggle('paused', !isRunning);
            if (!isStaticMode && model) {
                setStatus(isRunning ? 'live' : '', isRunning ? 'LIVE' : 'Dijeda');
            }
        });

        btnSwitchCam.addEventListener('click', () => {
            if (!isStaticMode) {
                facingMode = facingMode === 'user' ? 'environment' : 'user';
            }
            startCamera();
        });

        tabLive.addEventListener('click', () => {
            if (isStaticMode) startCamera();
        });

        tabStatic.addEventListener('click', () => {
            imgUpload.click();
        });

        imgUpload.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => {
                isStaticMode = true;
                tabStatic.classList.add('active');
                tabLive.classList.remove('active');
                video.style.display = 'none';
                previewImg.src = ev.target.result;
                previewImg.style.display = 'block';
                overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

                previewImg.onload = () => {
                    procCtx.drawImage(previewImg, 0, 0, 128, 128);
                    renderGrayscalePreview();
                    setStatus('live', 'Citra statis');
                    inferCanvas(true);
                };
            };
            reader.readAsDataURL(file);
        });

        thresholdSlider.addEventListener('input', (e) => {
            threshold = Number(e.target.value) / 100;
            thresholdVal.textContent = `${e.target.value}%`;
            if (isStaticMode) inferCanvas(true);
        });

        toggleSmooth.addEventListener('click', () => {
            useSmoothing = !useSmoothing;
            toggleSmooth.classList.toggle('active', useSmoothing);
            toggleSmooth.setAttribute('aria-checked', String(useSmoothing));
        });

        window.addEventListener('resize', () => {
            if (!isStaticMode) drawDetectionOverlay('', 0, false);
        });

        (async function init() {
            await startCamera();
            await loadModel();
            requestAnimationFrame(loop);
        })();
    </script>
</body>
</html>