@php
    $status = $status ?? 500;
    $meta = \App\Support\ErrorPageData::forStatus((int) $status, $retryAfter ?? null);
    $title = $title ?? $meta['title'];
    $message = $message ?? $meta['message'];
    $homeUrl = $homeUrl ?? '/';
    $homeLabel = $homeLabel ?? 'Ke Beranda';
    $reference = $reference ?? null;
    $retryAfter = $retryAfter ?? null;
    $isPublicContext = $isPublicContext ?? false;
    $isKioskContext = $isKioskContext ?? false;
    $digits = str_split((string) $status);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} — {{ $title }} · Sahabat SDAM</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #475569;
            --primary: #059669;
            --primary-hover: #047857;
            --sticker-bg: #fef3c7;
            --sticker-border: #fcd34d;
            --sticker-text: #78350f;
            --btn-sec-bg: #ffffff;
            --btn-sec-border: #cbd5e1;
            --btn-sec-text: #334155;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #020617;
                --card: #0f172a;
                --border: #1e293b;
                --text: #f1f5f9;
                --muted: #94a3b8;
                --primary: #10b981;
                --primary-hover: #059669;
                --sticker-bg: #451a03;
                --sticker-border: #92400e;
                --sticker-text: #fde68a;
                --btn-sec-bg: #0f172a;
                --btn-sec-border: #334155;
                --btn-sec-text: #e2e8f0;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: calc(1.25rem + env(safe-area-inset-top)) 1.25rem calc(1.25rem + env(safe-area-inset-bottom));
        }
        .wrap { width: 100%; max-width: 960px; margin: 0 auto; }
        header, footer { display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: var(--muted); }
        .brand { display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 800; color: var(--text); text-decoration: none; font-size: 0.875rem; }
        .brand img { width: 26px; height: 26px; border-radius: 7px; }
        main {
            margin: auto 0;
            padding: 2rem 0;
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            align-items: center;
        }
        @media (min-width: 900px) {
            main { grid-template-columns: 1fr 1fr; gap: 3rem; }
        }
        .sticker {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--sticker-border);
            background: var(--sticker-bg);
            color: var(--sticker-text);
            font-size: 0.75rem;
            font-weight: 800;
            transform: rotate(-2deg);
            margin-bottom: 0.75rem;
        }
        .digits { display: flex; gap: 0.75rem; }
        .digit {
            width: 84px;
            height: 104px;
            border-radius: 18px;
            border: 2px solid var(--border);
            background: var(--card);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.25rem;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .digit.mid { color: var(--primary); transform: translateY(-6px); }
        .report { font-size: 0.75rem; font-weight: 700; color: var(--muted); }
        h1 { margin-top: 0.5rem; font-size: 1.875rem; font-weight: 900; letter-spacing: -0.025em; line-height: 1.15; }
        p { margin-top: 0.75rem; font-size: 0.95rem; line-height: 1.6; color: var(--muted); }
        .actions { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.625rem; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 44px; padding: 0.625rem 1.25rem; border-radius: 12px;
            font-size: 0.875rem; font-weight: 700; text-decoration: none; cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-primary { background: var(--primary); color: #ffffff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-sec { background: var(--btn-sec-bg); border-color: var(--btn-sec-border); color: var(--btn-sec-text); }
        .ref { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.75rem; color: var(--muted); }
        .ref code { font-family: ui-monospace, monospace; font-weight: 700; color: var(--text); }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
    <header class="wrap">
        @if(!$isPublicContext)
            <a href="{{ $homeUrl }}" class="brand">
                <img src="/img/icon-192.png" alt="Logo SAHABAT SDAM">
                <span>SAHABAT SDAM</span>
            </a>
        @else
            <span>Verifikasi Dokumen Publik</span>
        @endif
        <span>Kode {{ $status }}</span>
    </header>

    <main class="wrap" @if($status >= 500 || $status === 429) role="alert" @endif>
        <div>
            <span class="sticker">{{ strtoupper($title) }}</span>
            <div class="digits" aria-label="Kode kesalahan {{ $status }}">
                @foreach($digits as $i => $d)
                    <span class="digit {{ $i === 1 ? 'mid' : '' }}">{{ $d }}</span>
                @endforeach
            </div>
        </div>

        <section>
            <div class="report">Laporan Status {{ $status }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>

            @if($status === 429 && $retryAfter)
                <p style="font-weight:700;font-size:0.8rem;">Tunggu <span id="retry-sec">{{ $retryAfter }}</span> detik</p>
            @endif

            <div class="actions">
                @if(in_array($status, [408, 419, 429, 500, 503], true))
                    <button type="button" class="btn btn-primary" onclick="(window.top || window).location.reload()">Muat Ulang</button>
                @else
                    <a href="{{ $homeUrl }}" target="_top" class="btn btn-primary">{{ $homeLabel }}</a>
                @endif

                @if(!$isKioskContext)
                    <button type="button" class="btn btn-sec" onclick="var w = (window.top || window); w.history.length > 1 ? w.history.back() : (w.location.href='{{ $homeUrl }}')">← Kembali</button>
                @endif
            </div>

            @if($reference)
                <div class="ref">
                    ID Referensi: <code>{{ $reference }}</code>
                </div>
            @endif
        </section>
    </main>

    <footer class="wrap">
        <span>SAHABAT SDAM</span>
        <span>SD Al Mujahidin Wonosari</span>
    </footer>

    @if($status === 429 && $retryAfter)
    <script>
        (function () {
            var s = {{ (int) $retryAfter }};
            var el = document.getElementById('retry-sec');
            var t = setInterval(function () {
                s = Math.max(0, s - 1);
                if (el) el.textContent = s;
                if (s <= 0) clearInterval(t);
            }, 1000);
        })();
    </script>
    @endif
</body>
</html>
