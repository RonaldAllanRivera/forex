<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forex Chart</title>
    <style>
        :root { color-scheme: dark; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; background: #0b1020; color: #e5e7eb; }
        .container { max-width: 1200px; margin: 0 auto; padding: 16px; }
        .header { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .title { font-size: 18px; font-weight: 600; }
        .controls { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 12px; color: #9ca3af; }
        select, input { background: #111827; border: 1px solid #243043; border-radius: 8px; padding: 10px 12px; color: #e5e7eb; min-width: 140px; }
        input[type="date"] { min-width: 160px; }
        button { background: #2563eb; border: 1px solid #1d4ed8; border-radius: 8px; padding: 10px 14px; color: white; font-weight: 600; cursor: pointer; }
        button.secondary { background: transparent; border-color: #243043; color: #e5e7eb; }
        button:disabled { opacity: .6; cursor: not-allowed; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 14px; }
        #chart { height: 560px; border: 1px solid #243043; border-radius: 12px; overflow: hidden; background: #0b1222; }
        .status { display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 10px 12px; border: 1px solid #243043; border-radius: 12px; background: #0b1222; }
        .status-left { display: flex; gap: 10px; align-items: center; }
        .badge { font-size: 12px; padding: 4px 8px; border-radius: 999px; border: 1px solid #243043; color: #9ca3af; }
        .error { color: #fca5a5; }
        .muted { color: #9ca3af; font-size: 12px; }
        a { color: #93c5fd; text-decoration: none; }
        .footer { margin-top: 16px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="title">Forex Chart (D1 / W1)</div>
        <div class="controls">
            <div class="field">
                <label for="symbol">Symbol</label>
                <select id="symbol"></select>
            </div>
            <div class="field">
                <label for="timeframe">Timeframe</label>
                <select id="timeframe">
                    <option value="D1">D1</option>
                    <option value="W1">W1</option>
                </select>
            </div>
            <div class="field">
                <label for="from">From (optional)</label>
                <input id="from" type="date" />
            </div>
            <div class="field">
                <label for="to">To (optional)</label>
                <input id="to" type="date" />
            </div>
            <button id="load">Load</button>
            <button id="reset" class="secondary">Reset range</button>
        </div>
    </div>

    <div class="grid">
        <div class="status">
            <div class="status-left">
                <span id="statusBadge" class="badge">idle</span>
                <span id="statusText" class="muted">Ready.</span>
            </div>
            <div class="muted">
                Data source: local DB via <code>/api/candles</code>
            </div>
        </div>
        <div id="chart"></div>
    </div>

    <div class="footer muted">
        <div>Defaults: D1 = last 2 years, W1 = last 5 years</div>
        <div><a href="/">Home</a></div>
    </div>
</div>

<script src="https://unpkg.com/lightweight-charts@4.2.1/dist/lightweight-charts.standalone.production.js"></script>
<script>
    const elSymbol = document.getElementById('symbol');
    const elTimeframe = document.getElementById('timeframe');
    const elFrom = document.getElementById('from');
    const elTo = document.getElementById('to');
    const elLoad = document.getElementById('load');
    const elReset = document.getElementById('reset');
    const elStatusBadge = document.getElementById('statusBadge');
    const elStatusText = document.getElementById('statusText');

    function setStatus(kind, text) {
        elStatusBadge.textContent = kind;
        elStatusBadge.style.borderColor = '#243043';
        elStatusBadge.style.color = '#9ca3af';
        elStatusText.classList.remove('error');
        elStatusText.textContent = text;

        if (kind === 'loading') {
            elStatusBadge.style.color = '#93c5fd';
        }
        if (kind === 'error') {
            elStatusBadge.style.color = '#fca5a5';
            elStatusText.classList.add('error');
        }
        if (kind === 'ok') {
            elStatusBadge.style.color = '#86efac';
        }
    }

    function fmtDate(d) {
        const yyyy = d.getUTCFullYear();
        const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
        const dd = String(d.getUTCDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function defaultRange(timeframe) {
        const to = new Date();
        const from = new Date(to);
        if (timeframe === 'W1') {
            from.setUTCDate(from.getUTCDate() - 365 * 5);
        } else {
            from.setUTCDate(from.getUTCDate() - 365 * 2);
        }
        return { from: fmtDate(from), to: fmtDate(to) };
    }

    function applyDefaultRange() {
        const tf = elTimeframe.value;
        const r = defaultRange(tf);
        elFrom.value = r.from;
        elTo.value = r.to;
    }

    const chart = LightweightCharts.createChart(document.getElementById('chart'), {
        layout: { background: { color: '#0b1222' }, textColor: '#e5e7eb' },
        grid: { vertLines: { color: '#111827' }, horzLines: { color: '#111827' } },
        rightPriceScale: { borderColor: '#243043' },
        timeScale: { borderColor: '#243043', timeVisible: true, secondsVisible: false },
        crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
    });

    const series = chart.addCandlestickSeries({
        upColor: '#22c55e',
        downColor: '#ef4444',
        borderUpColor: '#22c55e',
        borderDownColor: '#ef4444',
        wickUpColor: '#22c55e',
        wickDownColor: '#ef4444',
    });

    function resizeChart() {
        const container = document.getElementById('chart');
        chart.applyOptions({ width: container.clientWidth, height: container.clientHeight });
    }

    window.addEventListener('resize', resizeChart);

    async function loadSymbols() {
        const res = await fetch('/api/symbols', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) {
            throw new Error(`Failed to load symbols (${res.status})`);
        }
        const payload = await res.json();
        const data = Array.isArray(payload.data) ? payload.data : [];

        elSymbol.innerHTML = '';
        for (const s of data) {
            const opt = document.createElement('option');
            opt.value = s.code;
            opt.textContent = s.code;
            elSymbol.appendChild(opt);
        }

        if (data.length === 0) {
            throw new Error('No active symbols available. Seed symbols first.');
        }
    }

    function buildCandlesUrl() {
        const params = new URLSearchParams();
        params.set('symbol', elSymbol.value);
        params.set('timeframe', elTimeframe.value);

        if (elFrom.value) params.set('from', elFrom.value);
        if (elTo.value) params.set('to', elTo.value);

        return `/api/candles?${params.toString()}`;
    }

    async function loadCandles() {
        elLoad.disabled = true;
        setStatus('loading', 'Loading candles...');

        try {
            const url = buildCandlesUrl();
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const payload = await res.json();

            if (!res.ok) {
                const msg = payload?.message || `Request failed (${res.status})`;
                throw new Error(msg);
            }

            const candles = Array.isArray(payload.data) ? payload.data : [];
            const chartData = candles.map(c => ({
                time: Number(c.t),
                open: Number(c.o),
                high: Number(c.h),
                low: Number(c.l),
                close: Number(c.c),
            })).filter(p => Number.isFinite(p.time));

            series.setData(chartData);

            const meta = payload.meta || {};
            const count = chartData.length;
            setStatus('ok', `Loaded ${count} candles (${meta.symbol || elSymbol.value} ${meta.timeframe || elTimeframe.value}).`);

            if (count > 0) {
                chart.timeScale().fitContent();
            }
        } catch (e) {
            setStatus('error', e?.message ? String(e.message) : 'Unknown error');
        } finally {
            elLoad.disabled = false;
        }
    }

    elReset.addEventListener('click', () => {
        applyDefaultRange();
        loadCandles();
    });

    elLoad.addEventListener('click', () => loadCandles());

    elTimeframe.addEventListener('change', () => {
        applyDefaultRange();
        loadCandles();
    });

    elSymbol.addEventListener('change', () => loadCandles());

    (async function init() {
        resizeChart();
        applyDefaultRange();
        try {
            await loadSymbols();
            loadCandles();
        } catch (e) {
            setStatus('error', e?.message ? String(e.message) : 'Init failed');
        }
    })();
</script>
</body>
</html>
