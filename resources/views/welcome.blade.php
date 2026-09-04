<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RasaPKKMB | Analisis Sentimen</title>
    <style>
        .chart-panel { margin-top: 18px; padding: 24px; background: rgba(255,255,255,.72); border: 1px solid var(--line); }
        .chart-title { margin: 0 0 22px; font-size: 14px; }
        .chart-row { display: grid; grid-template-columns: 90px 1fr 52px; gap: 14px; align-items: center; margin-top: 13px; }
        .chart-label { color: var(--muted); font: 11px 'DM Mono', monospace; }
        .chart-track { height: 12px; overflow: hidden; background: #e6e9e1; }
        .chart-bar { display: block; height: 100%; transform-origin: left; animation: grow-bar .7s ease-out both; }
        .chart-bar.positive { background: var(--green); }.chart-bar.neutral { background: #b4a85c; }.chart-bar.negative { background: var(--orange); }
        .chart-value { color: var(--ink); font: 11px 'DM Mono', monospace; text-align: right; }
        @keyframes grow-bar { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        @media (max-width: 700px) { .chart-row { grid-template-columns: 72px 1fr 45px; gap: 9px; } .chart-panel { padding: 18px; } }
        .donut-panel { display: flex; align-items: center; gap: 24px; margin-top: 18px; padding: 24px; background: rgba(255,255,255,.72); border: 1px solid var(--line); }
        .donut { display: grid; flex: 0 0 138px; place-items: center; width: 138px; height: 138px; border-radius: 50%; background: conic-gradient(var(--green) 0 {{ $summary['total'] ?? 0 ? (($summary['positive'] ?? 0) / $summary['total']) * 100 : 0 }}%, #b4a85c 0 {{ $summary['total'] ?? 0 ? ((($summary['positive'] ?? 0) + ($summary['neutral'] ?? 0)) / $summary['total']) * 100 : 0 }}%, var(--orange) 0 100%); }
        .donut:after { content: ''; grid-area: 1 / 1; width: 88px; height: 88px; background: var(--paper); border-radius: 50%; }.donut-total { z-index: 1; grid-area: 1 / 1; text-align: center; }.donut-total strong { display: block; font-size: 23px; }.donut-total span { color: var(--muted); font: 9px 'DM Mono', monospace; text-transform: uppercase; }.legend { display: grid; gap: 12px; }.legend-item { display: grid; grid-template-columns: 8px 1fr auto; gap: 8px; align-items: center; font-size: 12px; }.legend-dot { width: 8px; height: 8px; border-radius: 50%; }.legend-dot.positive { background: var(--green); }.legend-dot.neutral { background: #b4a85c; }.legend-dot.negative { background: var(--orange); }
        @media (max-width: 700px) { .donut-panel { justify-content: center; } }
        .visual-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 18px; }.visual-row .chart-panel, .visual-row .donut-panel { margin-top: 0; }.search-panel { margin-top: 18px; padding: 20px 24px; background: rgba(255,255,255,.72); border: 1px solid var(--line); }.search-label { display: block; margin-bottom: 10px; color: var(--muted); font: 11px 'DM Mono', monospace; letter-spacing: 1px; text-transform: uppercase; }.search-input { width: 100%; padding: 13px 15px; color: var(--ink); background: #fbfcf8; border: 1px solid var(--line); outline: none; font: 13px 'Manrope', sans-serif; }.search-input:focus { border-color: var(--green); }.search-result-count { display: block; margin-top: 9px; color: var(--muted); font: 10px 'DM Mono', monospace; }
        @media (max-width: 700px) { .visual-row { grid-template-columns: 1fr; } }
    </style>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark">R</span><span>Rasa<span class="brand-accent">PKKMB</span></span></a>
        <span class="status"><span class="status-dot"></span> Gemini AI aktif</span>
    </header>
    <main class="main-content">
        <section class="intro">
            <p class="eyebrow">PUSAT INSIGHT PKKMB</p>
            <h1>Dengar suara<br><em>mahasiswa baru.</em></h1>
            <p class="intro-copy">Ubah komentar PKKMB menjadi insight yang ringkas. Masukkan satu komentar, atau analisis ratusan baris sekaligus.</p>
        </section>
        @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
        <form class="analysis-form" action="{{ route('classify') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="input-panel">
                <div class="panel-heading"><span class="step-number">01</span><div><h2>Masukkan komentar</h2><p>Pilih cara yang paling nyaman untuk memulai.</p></div></div>
                <div class="input-grid">
                    <label class="comment-box"><span class="field-label">Tulis manual</span><textarea name="comment" placeholder="Contoh: Acara PKKMB sangat seru dan panitianya ramah...">{{ old('comment') }}</textarea><span class="field-hint">Satu komentar per analisis</span></label>
                    <div class="or-divider"><span>atau</span></div>
                    <label class="upload-box" for="file-input"><span class="upload-icon">↑</span><strong>Unggah file komentar</strong><span>CSV atau TXT, maksimal 20 MB</span><input id="file-input" type="file" name="file" accept=".csv,.txt"><span id="file-name" class="file-name">Pilih file dari perangkat</span></label>
                </div>
                <button class="primary-button" type="submit">Analisis sentimen <span>→</span></button>
            </div>
        </form>
        @if (isset($results))
            <section class="results-section">
                <div class="results-heading"><div><p class="eyebrow">HASIL ANALISIS</p><h2>Gambaran suasana PKKMB</h2></div><span class="result-count">{{ $summary['total'] }} komentar dianalisis</span></div>
                <div class="summary-grid">
                    <div class="summary-card total"><span class="summary-label">Total komentar</span><strong>{{ $summary['total'] }}</strong><span class="summary-note">dari input kamu</span></div>
                    <div class="summary-card positive"><span class="summary-label">Positif</span><strong>{{ $summary['positive'] }}</strong><span class="summary-note">pengalaman baik</span></div>
                    <div class="summary-card neutral"><span class="summary-label">Netral</span><strong>{{ $summary['neutral'] }}</strong><span class="summary-note">tanpa kecenderungan</span></div>
                    <div class="summary-card negative"><span class="summary-label">Negatif</span><strong>{{ $summary['negative'] }}</strong><span class="summary-note">perlu perhatian</span></div>
                </div>
                <div class="visual-row"><div class="chart-panel" aria-label="Grafik distribusi sentimen">
                    <h3 class="chart-title">Distribusi sentimen</h3>
                    <div class="chart-row"><span class="chart-label">Positif</span><span class="chart-track"><i class="chart-bar positive" style="width: {{ $summary['total'] ? ($summary['positive'] / $summary['total']) * 100 : 0 }}%"></i></span><span class="chart-value">{{ $summary['total'] ? number_format(($summary['positive'] / $summary['total']) * 100, 1) : 0 }}%</span></div>
                    <div class="chart-row"><span class="chart-label">Netral</span><span class="chart-track"><i class="chart-bar neutral" style="width: {{ $summary['total'] ? ($summary['neutral'] / $summary['total']) * 100 : 0 }}%"></i></span><span class="chart-value">{{ $summary['total'] ? number_format(($summary['neutral'] / $summary['total']) * 100, 1) : 0 }}%</span></div>
                    <div class="chart-row"><span class="chart-label">Negatif</span><span class="chart-track"><i class="chart-bar negative" style="width: {{ $summary['total'] ? ($summary['negative'] / $summary['total']) * 100 : 0 }}%"></i></span><span class="chart-value">{{ $summary['total'] ? number_format(($summary['negative'] / $summary['total']) * 100, 1) : 0 }}%</span></div>
                </div>
                </div><div class="donut-panel" aria-label="Diagram donut distribusi sentimen"><div class="donut"><div class="donut-total"><strong>{{ $summary['total'] }}</strong><span>komentar</span></div></div><div class="legend"><div class="legend-item"><i class="legend-dot positive"></i><span>Positif</span><small>{{ $summary['positive'] }}</small></div><div class="legend-item"><i class="legend-dot neutral"></i><span>Netral</span><small>{{ $summary['neutral'] }}</small></div><div class="legend-item"><i class="legend-dot negative"></i><span>Negatif</span><small>{{ $summary['negative'] }}</small></div></div></div></div>
                <div class="search-panel"><label class="search-label" for="comment-search">Cari komentar</label><input class="search-input" id="comment-search" type="search" placeholder="Ketik kata atau sentimen, misalnya negatif..."><span class="search-result-count" id="search-result-count">{{ $summary['total'] }} hasil ditampilkan</span></div>
                <div class="table-wrap"><table><thead><tr><th>Komentar</th><th>Hasil klasifikasi</th><th>Keterangan</th><th>Keyakinan</th></tr></thead><tbody>@foreach ($results as $result)<tr><td>{{ $result['comment'] }}</td><td><span class="pill {{ strtolower($result['label']) }}"><span></span>{{ $result['label'] }}</span></td><td class="explanation">{{ $result['explanation'] }}</td><td><div class="confidence"><span class="confidence-bar"><i style="width: {{ $result['confidence'] }}%"></i></span><small>{{ $result['confidence'] }}%</small></div></td></tr>@endforeach</tbody></table></div>
            </section>
        @else
            <section class="empty-state"><span class="empty-icon">✦</span><div><strong>Insight kamu akan muncul di sini</strong><p>Hasil klasifikasi dan ringkasan distribusi sentimen siap dibaca dalam sekali klik.</p></div></section>
        @endif
    </main>
    <footer><span>RasaPKKMB</span><span>Eksplorasi feedback. Bangun PKKMB yang lebih baik.</span></footer>
</div>
<script>
    document.getElementById('file-input').addEventListener('change', function () {
        document.getElementById('file-name').textContent = this.files[0]?.name || 'Pilih file dari perangkat';
    });

    const searchInput = document.getElementById('comment-search');
    if (searchInput) {
        const rows = Array.from(document.querySelectorAll('.table-wrap tbody tr'));
        const resultCount = document.getElementById('search-result-count');
        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let visibleRows = 0;
            rows.forEach((row) => {
                const matches = row.textContent.toLowerCase().includes(query);
                row.hidden = !matches;
                visibleRows += matches ? 1 : 0;
            });
            resultCount.textContent = `${visibleRows} hasil ditampilkan`;
        });
    }
</script>
</body>
</html>