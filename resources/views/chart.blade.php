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
        input[type="number"] { min-width: 110px; }
        input:disabled { opacity: .55; cursor: not-allowed; }
        details.disclosure { border: 1px solid #243043; border-radius: 10px; background: #0b1222; padding: 8px 10px; width: 100%; }
        details.disclosure summary { cursor: pointer; user-select: none; list-style: none; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        details.disclosure summary::-webkit-details-marker { display: none; }
        details.disclosure summary .summary-title { font-size: 12px; color: #e5e7eb; font-weight: 600; }
        details.disclosure summary .summary-sub { font-size: 12px; color: #9ca3af; }
        details.disclosure summary .chev { color: #9ca3af; font-size: 12px; }
        details.disclosure[open] summary .chev { transform: rotate(180deg); }
        details.disclosure .disclosure-body { margin-top: 10px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; align-items: end; }
        details.disclosure .disclosure-body .full { grid-column: 1 / -1; }
        .toggle-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 10px 12px; border: 1px solid #243043; border-radius: 8px; background: #111827; }
        .toggle { display: flex; gap: 8px; align-items: center; font-size: 12px; color: #e5e7eb; }
        .toggle input { min-width: auto; width: 16px; height: 16px; padding: 0; }
        button { background: #2563eb; border: 1px solid #1d4ed8; border-radius: 8px; padding: 10px 14px; color: white; font-weight: 600; cursor: pointer; }
        button.secondary { background: transparent; border-color: #243043; color: #e5e7eb; }
        button:disabled { opacity: .6; cursor: not-allowed; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 14px; }
        #chart { height: 560px; border: 1px solid #243043; border-radius: 12px; overflow: hidden; background: #0b1222; }
        #volWrap { border: 1px solid #243043; border-radius: 12px; overflow: hidden; background: #0b1222; }
        #volChart { height: 140px; }
        #stochWrap { border: 1px solid #243043; border-radius: 12px; overflow: hidden; background: #0b1222; }
        #stochChart { height: 180px; }
        .hidden { display: none; }
        .status { display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 10px 12px; border: 1px solid #243043; border-radius: 12px; background: #0b1222; }
        .status-left { display: flex; gap: 10px; align-items: center; }
        .badge { font-size: 12px; padding: 4px 8px; border-radius: 999px; border: 1px solid #243043; color: #9ca3af; }
        .error { color: #fca5a5; }
        .muted { color: #9ca3af; font-size: 12px; }
        .spinner { width: 12px; height: 12px; border: 2px solid #243043; border-top-color: #93c5fd; border-radius: 999px; animation: spin 0.9s linear infinite; display: inline-block; }
        .spinner.hidden { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
        a { color: #93c5fd; text-decoration: none; }
        .footer { margin-top: 16px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="title">Forex Chart (D1 / W1 / MN1)</div>
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
                    <option value="MN1">MN1</option>
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
                Closed candles only (EOD) · <span id="lastClosed">Last closed: —</span> · Data source: local DB via <code>/api/candles</code>
            </div>
        </div>

        @if(app()->environment(['local', 'staging', 'testing']))
            <div class="status" id="syncStatusBar">
                <div class="status-left">
                    <span class="badge" id="syncBadge">sync</span>
                    <span class="muted" id="syncText">Sync all timeframes (D1/W1/MN1) from Alpha Vantage is available in local/staging.</span>
                    <span id="syncSpinner" class="spinner hidden"></span>
                </div>
                <div class="status-left">
                    <button id="syncAllBtn" class="secondary" type="button">Sync all timeframes</button>
                </div>
            </div>
        @endif

        <div id="chart"></div>
        <div id="volWrap" class="hidden">
            <div id="volChart"></div>
        </div>
        <div id="stochWrap" class="hidden">
            <div id="stochChart"></div>
        </div>
    </div>

    <div style="margin-top: 12px;">
        <details class="disclosure">
            <summary>
                <div>
                    <div class="summary-title">Indicators</div>
                    <div class="summary-sub">Optional (default off)</div>
                </div>
                <div class="chev">▼</div>
            </summary>
            <div class="disclosure-body">
                <div class="field full">
                    <label>Enable</label>
                    <div class="toggle-row">
                        <label class="toggle"><input id="showVol" type="checkbox" />Volume</label>
                        <label class="toggle"><input id="showSr" type="checkbox" />SR</label>
                        <label class="toggle"><input id="showStoch" type="checkbox" />Stoch</label>
                    </div>
                </div>
                <div class="field full">
                    <label>Notes</label>
                    <div class="muted">FX volume is often unavailable. When provider volume is missing, the chart shows a simple activity proxy (range).</div>
                </div>
                <div class="field">
                    <label for="stochK">Stoch K</label>
                    <input id="stochK" type="number" min="2" max="100" step="1" />
                </div>
                <div class="field">
                    <label for="stochD">Stoch D</label>
                    <input id="stochD" type="number" min="1" max="50" step="1" />
                </div>
                <div class="field">
                    <label for="stochSmooth">Stoch Smooth</label>
                    <input id="stochSmooth" type="number" min="1" max="50" step="1" />
                </div>
                <div class="field">
                    <label for="srLookback">SR Lookback</label>
                    <input id="srLookback" type="number" min="50" max="2000" step="1" />
                </div>
                <div class="field">
                    <label for="srLevels">SR Levels</label>
                    <input id="srLevels" type="number" min="1" max="50" step="1" />
                </div>
            </div>
        </details>
    </div>

    <div class="footer muted">
        <div>Defaults: D1 = last 2 years, W1 = last 5 years, MN1 = last 15 years</div>
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
    const elLastClosed = document.getElementById('lastClosed');

    const elSyncAllBtn = document.getElementById('syncAllBtn');
    const elSyncBadge = document.getElementById('syncBadge');
    const elSyncText = document.getElementById('syncText');
    const elSyncSpinner = document.getElementById('syncSpinner');
    const elShowVol = document.getElementById('showVol');
    const elShowSr = document.getElementById('showSr');
    const elShowStoch = document.getElementById('showStoch');
    const elStochK = document.getElementById('stochK');
    const elStochD = document.getElementById('stochD');
    const elStochSmooth = document.getElementById('stochSmooth');
    const elSrLookback = document.getElementById('srLookback');
    const elSrLevels = document.getElementById('srLevels');
    const elVolWrap = document.getElementById('volWrap');
    const elStochWrap = document.getElementById('stochWrap');

    let lastOkStatusText = 'Ready.';

    let syncPollTimer = 0;
    let lastSyncKey = '';

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
            lastOkStatusText = text;
        }
    }

    function setSyncUi(kind, text, spinning) {
        if (!elSyncText || !elSyncBadge || !elSyncSpinner) return;
        elSyncBadge.textContent = kind;
        elSyncBadge.style.borderColor = '#243043';
        elSyncBadge.style.color = '#9ca3af';
        elSyncText.classList.remove('error');
        elSyncText.textContent = text;

        if (kind === 'queued' || kind === 'running') {
            elSyncBadge.style.color = '#93c5fd';
        }
        if (kind === 'failed') {
            elSyncBadge.style.color = '#fca5a5';
            elSyncText.classList.add('error');
        }
        if (kind === 'succeeded') {
            elSyncBadge.style.color = '#86efac';
        }

        if (spinning) {
            elSyncSpinner.classList.remove('hidden');
        } else {
            elSyncSpinner.classList.add('hidden');
        }
    }

    function stopSyncPolling() {
        if (syncPollTimer) {
            window.clearInterval(syncPollTimer);
            syncPollTimer = 0;
        }
    }

    function buildSyncParams() {
        const symbol = elSymbol.value;
        const timeframe = elTimeframe.value;
        const from = elFrom.value || null;
        const to = elTo.value || null;
        return { symbol, timeframe, from, to };
    }

    function buildSyncStatusAllUrl(symbol) {
        const params = new URLSearchParams();
        params.set('symbol', symbol);
        return `/api/sync-candles/status-all?${params.toString()}`;
    }

    async function fetchSyncStatusAll(symbol) {
        const url = buildSyncStatusAllUrl(symbol);
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const payload = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg = payload?.message || `Sync status failed (${res.status})`;
            throw new Error(msg);
        }
        return payload;
    }

    function humanizeIso(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return iso;
        return d.toLocaleString();
    }

    function renderSyncAllPayload(payload) {
        const data = payload?.data || {};
        const tfs = payload?.meta?.timeframes || ['D1', 'W1', 'MN1'];

        const statuses = tfs.map(tf => {
            const s = String(data?.[tf]?.status || 'idle');
            return `${tf}:${s}`;
        });

        const anyRunning = tfs.some(tf => {
            const s = String(data?.[tf]?.status || 'idle');
            return s === 'queued' || s === 'running';
        });

        const anyFailed = tfs.some(tf => String(data?.[tf]?.status || 'idle') === 'failed');
        const allSucceeded = tfs.every(tf => String(data?.[tf]?.status || 'idle') === 'succeeded');

        if (anyRunning) {
            setSyncUi('running', `All timeframes: ${statuses.join(' · ')}`, true);
            return;
        }

        if (anyFailed) {
            const firstFailed = tfs.find(tf => String(data?.[tf]?.status || 'idle') === 'failed');
            const err = firstFailed ? (data?.[firstFailed]?.last_error || 'Unknown error') : 'Unknown error';
            setSyncUi('failed', `All timeframes: ${statuses.join(' · ')} · Error: ${String(err)}`, false);
            return;
        }

        if (allSucceeded) {
            const lastSynced = tfs.map(tf => ({ tf, at: data?.[tf]?.last_synced_at || null }));
            const mostRecent = lastSynced
                .map(x => ({ tf: x.tf, ms: x.at ? Date.parse(x.at) : NaN, at: x.at }))
                .filter(x => !Number.isNaN(x.ms))
                .sort((a, b) => b.ms - a.ms)[0];
            const text = mostRecent?.at ? `All timeframes synced: ${humanizeIso(mostRecent.at)}` : `All timeframes: ${statuses.join(' · ')}`;
            setSyncUi('succeeded', text, false);
            return;
        }

        setSyncUi('idle', `All timeframes: ${statuses.join(' · ')}`, false);
    }

    async function refreshSyncStatus() {
        if (!elSyncText) return;
        const { symbol, timeframe } = buildSyncParams();
        if (!symbol || !timeframe) return;

        const key = `${symbol}:ALL`;
        lastSyncKey = key;

        try {
            const payload = await fetchSyncStatusAll(symbol);
            renderSyncAllPayload(payload);
        } catch (e) {
            setSyncUi('failed', e?.message ? String(e.message) : 'Sync status error', false);
        }
    }

    function startSyncPolling(symbol, timeframe) {
        stopSyncPolling();
        const key = `${symbol}:ALL`;
        lastSyncKey = key;

        syncPollTimer = window.setInterval(async () => {
            if (lastSyncKey !== key) {
                stopSyncPolling();
                return;
            }

            try {
                const payload = await fetchSyncStatusAll(symbol);
                renderSyncAllPayload(payload);

                const tfs = payload?.meta?.timeframes || ['D1', 'W1', 'MN1'];
                const anyRunning = tfs.some(tf => {
                    const s = String(payload?.data?.[tf]?.status || 'idle');
                    return s === 'queued' || s === 'running';
                });
                if (!anyRunning) {
                    stopSyncPolling();
                }
            } catch (e) {
                setSyncUi('failed', e?.message ? String(e.message) : 'Sync status error', false);
                stopSyncPolling();
            }
        }, 1500);
    }

    async function queueSyncAll() {
        if (!elSyncAllBtn) return;
        const { symbol, from, to } = buildSyncParams();
        if (!symbol) return;

        elSyncAllBtn.disabled = true;
        setSyncUi('queued', 'Queueing sync for all timeframes…', true);

        try {
            const res = await fetch('/api/sync-candles/all', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ symbol, from, to }),
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = payload?.message || `Sync request failed (${res.status})`;
                throw new Error(msg);
            }

            renderSyncAllPayload(payload);
            startSyncPolling(symbol, '');
        } catch (e) {
            setSyncUi('failed', e?.message ? String(e.message) : 'Sync request failed', false);
        } finally {
            elSyncAllBtn.disabled = false;
        }
    }

    function fmtDate(d) {
        const yyyy = d.getUTCFullYear();
        const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
        const dd = String(d.getUTCDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function businessDayFromUnixSeconds(t) {
        const d = new Date(Number(t) * 1000);
        return {
            year: d.getUTCFullYear(),
            month: d.getUTCMonth() + 1,
            day: d.getUTCDate(),
        };
    }

    function defaultRange(timeframe) {
        const to = new Date();
        const from = new Date(to);
        if (timeframe === 'W1') {
            from.setUTCDate(from.getUTCDate() - 365 * 5);
        } else if (timeframe === 'MN1') {
            from.setUTCDate(from.getUTCDate() - 365 * 15);
        } else {
            from.setUTCDate(from.getUTCDate() - 365 * 2);
        }
        return { from: fmtDate(from), to: fmtDate(to) };
    }

    function indicatorDefaults(timeframe) {
        if (timeframe === 'W1') {
            return {
                stochK: 14,
                stochD: 3,
                stochSmooth: 3,
                srLookback: 260,
                srLevels: 6,
            };
        }

        if (timeframe === 'MN1') {
            return {
                stochK: 14,
                stochD: 3,
                stochSmooth: 3,
                srLookback: 180,
                srLevels: 6,
            };
        }

        return {
            stochK: 14,
            stochD: 3,
            stochSmooth: 3,
            srLookback: 300,
            srLevels: 6,
        };
    }

    function applyDefaultRange() {
        const tf = elTimeframe.value;
        const r = defaultRange(tf);
        elFrom.value = r.from;
        elTo.value = r.to;
    }

    function applyIndicatorDefaults() {
        const tf = elTimeframe.value;
        const d = indicatorDefaults(tf);
        elStochK.value = String(d.stochK);
        elStochD.value = String(d.stochD);
        elStochSmooth.value = String(d.stochSmooth);
        elSrLookback.value = String(d.srLookback);
        elSrLevels.value = String(d.srLevels);
    }

    const chart = LightweightCharts.createChart(document.getElementById('chart'), {
        layout: { background: { color: '#0b1222' }, textColor: '#e5e7eb' },
        grid: { vertLines: { color: '#111827' }, horzLines: { color: '#111827' } },
        rightPriceScale: { borderColor: '#243043' },
        timeScale: { borderColor: '#243043', timeVisible: false, secondsVisible: false },
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

    const volChart = LightweightCharts.createChart(document.getElementById('volChart'), {
        layout: { background: { color: '#0b1222' }, textColor: '#e5e7eb' },
        grid: { vertLines: { color: '#111827' }, horzLines: { color: '#111827' } },
        rightPriceScale: { borderColor: '#243043' },
        timeScale: { borderColor: '#243043', timeVisible: false, secondsVisible: false },
        crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
    });

    const volSeries = volChart.addHistogramSeries({
        priceFormat: { type: 'volume' },
        priceScaleId: '',
        scaleMargins: { top: 0.15, bottom: 0 },
    });

    const stochChart = LightweightCharts.createChart(document.getElementById('stochChart'), {
        layout: { background: { color: '#0b1222' }, textColor: '#e5e7eb' },
        grid: { vertLines: { color: '#111827' }, horzLines: { color: '#111827' } },
        rightPriceScale: { borderColor: '#243043' },
        timeScale: { borderColor: '#243043', timeVisible: false, secondsVisible: false },
        crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
    });

    const stochKSeries = stochChart.addLineSeries({ color: '#60a5fa', lineWidth: 2 });
    const stochDSeries = stochChart.addLineSeries({ color: '#f59e0b', lineWidth: 2 });
    const stochMinSeries = stochChart.addLineSeries({ color: 'rgba(0,0,0,0)', lineWidth: 1 });
    const stochMaxSeries = stochChart.addLineSeries({ color: 'rgba(0,0,0,0)', lineWidth: 1 });

    stochKSeries.createPriceLine({ price: 20, color: '#243043', lineWidth: 1, lineStyle: LightweightCharts.LineStyle.Dashed });
    stochKSeries.createPriceLine({ price: 80, color: '#243043', lineWidth: 1, lineStyle: LightweightCharts.LineStyle.Dashed });

    function resizeChart() {
        const container = document.getElementById('chart');
        chart.applyOptions({ width: container.clientWidth, height: container.clientHeight });

        const vc = document.getElementById('volChart');
        volChart.applyOptions({ width: vc.clientWidth, height: vc.clientHeight });

        const st = document.getElementById('stochChart');
        stochChart.applyOptions({ width: st.clientWidth, height: st.clientHeight });
    }

    window.addEventListener('resize', resizeChart);

    let isSyncingTimeRange = false;
    let pendingLogicalRange = null;
    let pendingLogicalSource = null;
    let syncRafId = 0;

    function applyLogicalRangeSync(source, range) {
        if (!range) return;
        if (isSyncingTimeRange) return;
        isSyncingTimeRange = true;
        try {
            if (source !== chart) chart.timeScale().setVisibleLogicalRange(range);
            if (source !== volChart) volChart.timeScale().setVisibleLogicalRange(range);
            if (source !== stochChart) stochChart.timeScale().setVisibleLogicalRange(range);
        } finally {
            isSyncingTimeRange = false;
        }
    }

    function scheduleLogicalRangeSync(source, range) {
        if (!range) return;
        pendingLogicalRange = range;
        pendingLogicalSource = source;
        if (syncRafId) return;
        syncRafId = requestAnimationFrame(() => {
            syncRafId = 0;
            const r = pendingLogicalRange;
            const s = pendingLogicalSource;
            pendingLogicalRange = null;
            pendingLogicalSource = null;
            applyLogicalRangeSync(s, r);
        });
    }

    chart.timeScale().subscribeVisibleLogicalRangeChange((range) => scheduleLogicalRangeSync(chart, range));
    volChart.timeScale().subscribeVisibleLogicalRangeChange((range) => scheduleLogicalRangeSync(volChart, range));
    stochChart.timeScale().subscribeVisibleLogicalRangeChange((range) => scheduleLogicalRangeSync(stochChart, range));

    let lastLoadedCandles = [];

    function applyIndicatorEnabledStates() {
        const stochEnabled = elShowStoch.checked;
        const srEnabled = elShowSr.checked;
        elStochK.disabled = !stochEnabled;
        elStochD.disabled = !stochEnabled;
        elStochSmooth.disabled = !stochEnabled;
        elSrLookback.disabled = !srEnabled;
        elSrLevels.disabled = !srEnabled;
    }

    function clearVol() {
        volSeries.setData([]);
        elVolWrap.classList.add('hidden');
    }

    function renderVolFromLastCandles() {
        if (!elShowVol.checked) {
            clearVol();
            return;
        }

        if (!Array.isArray(lastLoadedCandles) || lastLoadedCandles.length === 0) {
            clearVol();
            return;
        }

        const points = lastLoadedCandles.map(c => {
            const t = Number(c.t);
            const open = Number(c.o);
            const high = Number(c.h);
            const low = Number(c.l);
            const close = Number(c.c);
            const vRaw = c.v;
            const v = (vRaw === null || vRaw === undefined || vRaw === '') ? NaN : Number(vRaw);
            const value = Number.isFinite(v) ? v : Math.max(0, high - low);
            const color = close >= open ? 'rgba(34,197,94,0.65)' : 'rgba(239,68,68,0.65)';
            return {
                time: businessDayFromUnixSeconds(t),
                value: Number(value),
                color,
            };
        }).filter(p => Number.isFinite(p.value));

        if (points.length === 0) {
            clearVol();
            return;
        }

        elVolWrap.classList.remove('hidden');
        volSeries.setData(points);
        resizeChart();
        volChart.timeScale().fitContent();
    }

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

    function buildOverlaysUrl() {
        const params = new URLSearchParams();
        params.set('symbol', elSymbol.value);
        params.set('timeframe', elTimeframe.value);

        if (elFrom.value) params.set('from', elFrom.value);
        if (elTo.value) params.set('to', elTo.value);

        params.set('stoch_k', elStochK.value || '14');
        params.set('stoch_d', elStochD.value || '3');
        params.set('stoch_smooth', elStochSmooth.value || '3');

        params.set('sr_lookback', elSrLookback.value || (elTimeframe.value === 'W1' ? '260' : (elTimeframe.value === 'MN1' ? '180' : '300')));
        params.set('sr_max_levels', elSrLevels.value || '6');

        return `/api/overlays?${params.toString()}`;
    }

    let srLines = [];

    function clearSrLines() {
        for (const l of srLines) {
            try { series.removePriceLine(l); } catch (_) {}
        }
        srLines = [];
    }

    function clearStoch() {
        stochKSeries.setData([]);
        stochDSeries.setData([]);
        stochMinSeries.setData([]);
        stochMaxSeries.setData([]);
        elStochWrap.classList.add('hidden');
    }

    function applyStochBounds(times) {
        if (!times || times.length === 0) {
            return;
        }
        const first = times[0];
        const last = times[times.length - 1];
        stochMinSeries.setData([{ time: first, value: 0 }, { time: last, value: 0 }]);
        stochMaxSeries.setData([{ time: first, value: 100 }, { time: last, value: 100 }]);
    }

    async function loadOverlays() {
        const wantSr = elShowSr.checked;
        const wantStoch = elShowStoch.checked;

        if (!wantSr && !wantStoch) {
            clearSrLines();
            clearStoch();
            return;
        }

        setStatus('loading', 'Loading overlays...');

        try {
            const url = buildOverlaysUrl();
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const payload = await res.json();

            if (!res.ok) {
                const msg = payload?.message || `Request failed (${res.status})`;
                throw new Error(msg);
            }

            const data = payload.data || {};

            clearSrLines();
            if (wantSr) {
                const levels = Array.isArray(data.sr_levels) ? data.sr_levels : [];
                for (const lvl of levels) {
                    const price = Number(lvl.price);
                    if (!Number.isFinite(price)) continue;
                    const strength = Number(lvl.strength);
                    const title = Number.isFinite(strength) ? `SR (${strength})` : 'SR';
                    const pl = series.createPriceLine({
                        price,
                        color: '#fbbf24',
                        lineWidth: 1,
                        lineStyle: LightweightCharts.LineStyle.Dashed,
                        axisLabelVisible: true,
                        title,
                    });
                    srLines.push(pl);
                }
            }

            clearStoch();
            if (wantStoch) {
                const k = Array.isArray(data?.stochastic?.k) ? data.stochastic.k : [];
                const d = Array.isArray(data?.stochastic?.d) ? data.stochastic.d : [];

                const kData = k.map(p => ({
                    time: businessDayFromUnixSeconds(p.t),
                    value: Number(p.value),
                })).filter(p => Number.isFinite(p.value));

                const dData = d.map(p => ({
                    time: businessDayFromUnixSeconds(p.t),
                    value: Number(p.value),
                })).filter(p => Number.isFinite(p.value));

                stochKSeries.setData(kData);
                stochDSeries.setData(dData);

                const times = (kData.length > 0 ? kData : dData).map(p => p.time);
                applyStochBounds(times);

                elStochWrap.classList.remove('hidden');
                resizeChart();
                stochChart.timeScale().fitContent();
            }

            setStatus('ok', lastOkStatusText);
        } catch (e) {
            setStatus('error', e?.message ? String(e.message) : 'Overlay error');
        }
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
            lastLoadedCandles = candles;
            const sorted = [...candles].sort((a, b) => Number(a.t) - Number(b.t));
            const seenT = new Set();
            let skipped = 0;

            const chartData = [];
            for (const c of sorted) {
                const t = Number(c.t);
                const open = Number(c.o);
                const high = Number(c.h);
                const low = Number(c.l);
                const close = Number(c.c);

                if (!Number.isFinite(t) || !Number.isFinite(open) || !Number.isFinite(high) || !Number.isFinite(low) || !Number.isFinite(close)) {
                    skipped++;
                    continue;
                }

                if (seenT.has(t)) {
                    skipped++;
                    continue;
                }
                seenT.add(t);

                chartData.push({
                    time: businessDayFromUnixSeconds(t),
                    open,
                    high,
                    low,
                    close,
                });
            }

            series.setData(chartData);
            renderVolFromLastCandles();

            const meta = payload.meta || {};
            const count = chartData.length;
            if (candles.length > 0) {
                const lastT = candles[candles.length - 1].t;
                elLastClosed.textContent = `Last closed: ${fmtDate(new Date(Number(lastT) * 1000))}`;
            } else {
                elLastClosed.textContent = 'Last closed: —';
            }
            const baseMsg = `Loaded ${count} candles (${meta.symbol || elSymbol.value} ${meta.timeframe || elTimeframe.value}).`;
            if (skipped > 0) {
                setStatus('ok', `${baseMsg} Skipped ${skipped} invalid/duplicate rows.`);
            } else {
                setStatus('ok', baseMsg);
            }

            if (count > 0) {
                chart.timeScale().fitContent();
            }

            await loadOverlays();
        } catch (e) {
            const msg = e?.message ? String(e.message) : 'Unknown error';
            setStatus('error', `${msg}`);
        } finally {
            elLoad.disabled = false;
        }
    }

    elReset.addEventListener('click', () => {
        applyDefaultRange();
        applyIndicatorDefaults();
        loadCandles();
    });

    elLoad.addEventListener('click', () => loadCandles());

    elTimeframe.addEventListener('change', () => {
        applyDefaultRange();
        applyIndicatorDefaults();
        loadCandles();
        refreshSyncStatus();
    });

    elSymbol.addEventListener('change', () => {
        loadCandles();
        refreshSyncStatus();
    });

    elShowVol.addEventListener('change', () => renderVolFromLastCandles());

    elShowSr.addEventListener('change', () => {
        applyIndicatorEnabledStates();
        loadOverlays();
    });
    elShowStoch.addEventListener('change', () => {
        applyIndicatorEnabledStates();
        loadOverlays();
    });

    elStochK.addEventListener('change', () => { if (elShowStoch.checked) loadOverlays(); });
    elStochD.addEventListener('change', () => { if (elShowStoch.checked) loadOverlays(); });
    elStochSmooth.addEventListener('change', () => { if (elShowStoch.checked) loadOverlays(); });
    elSrLookback.addEventListener('change', () => { if (elShowSr.checked) loadOverlays(); });
    elSrLevels.addEventListener('change', () => { if (elShowSr.checked) loadOverlays(); });

    (async function init() {
        resizeChart();
        applyDefaultRange();
        applyIndicatorDefaults();
        applyIndicatorEnabledStates();
        clearVol();
        try {
            await loadSymbols();
            loadCandles();
            if (elSyncAllBtn) {
                elSyncAllBtn.addEventListener('click', () => queueSyncAll());
                refreshSyncStatus();
            }
        } catch (e) {
            setStatus('error', e?.message ? String(e.message) : 'Init failed');
        }
    })();
</script>
</body>
</html>
