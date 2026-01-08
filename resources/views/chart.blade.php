@extends('layouts.app')

@section('title', 'Forex Chart')

@section('content')
<div class="mx-auto max-w-6xl p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-lg font-semibold text-slate-100">Forex Chart (D1 / W1 / MN1)</div>

        <div class="flex flex-wrap items-end justify-end gap-2">
            <div class="flex flex-col gap-1.5">
                <label for="symbol" class="text-xs text-slate-400">Symbol</label>
                <select id="symbol" class="min-w-[140px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"></select>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="timeframe" class="text-xs text-slate-400">Timeframe</label>
                <select id="timeframe" class="min-w-[140px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                    <option value="D1">D1</option>
                    <option value="W1">W1</option>
                    <option value="MN1">MN1</option>
                </select>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="from" class="text-xs text-slate-400">From (optional)</label>
                <input id="from" type="date" class="min-w-[160px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="to" class="text-xs text-slate-400">To (optional)</label>
                <input id="to" type="date" class="min-w-[160px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
            </div>

            <button id="load" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">Load</button>
            <button id="reset" class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500/30">Reset range</button>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900/60 px-3 py-2">
            <div class="flex flex-wrap items-center gap-2">
                <span id="statusBadge" class="inline-flex items-center rounded-full border border-slate-700 px-2 py-0.5 text-xs text-slate-400">idle</span>
                <span id="statusText" class="text-xs text-slate-400">Ready.</span>
            </div>
            <div class="text-xs text-slate-400">
                Closed candles only (EOD) · <span id="lastClosed">Last closed: —</span> · Data source: local DB via <code class="rounded bg-slate-950 px-1 py-0.5 text-[11px] text-slate-200">/api/candles</code>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900/60 px-3 py-2" id="syncStatusBar">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full border border-slate-700 px-2 py-0.5 text-xs text-slate-400" id="syncBadge">sync</span>
                <span class="text-xs text-slate-400" id="syncText">Sync all timeframes (D1/W1/MN1) from Alpha Vantage.</span>
                <span id="syncSpinner" class="hidden h-3 w-3 animate-spin rounded-full border-2 border-slate-700 border-t-blue-300"></span>
            </div>
            <div class="flex items-center gap-2">
                <button id="syncAllBtn" class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500/30" type="button">Sync all timeframes</button>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3" id="aiPanel">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span id="aiBadge" class="inline-flex items-center rounded-full border border-slate-700 px-2 py-0.5 text-xs text-slate-400">AI</span>
                    <span id="aiSummary" class="text-xs text-slate-400">No signal loaded.</span>
                    <span id="aiSpinner" class="hidden h-3 w-3 animate-spin rounded-full border-2 border-slate-700 border-t-blue-300"></span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div id="aiMeta" class="text-xs text-slate-400"></div>
                    <button id="aiReviewBtn" class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500/30" type="button">AI Review</button>
                </div>
            </div>
            <div class="mt-2 text-xs text-slate-400">
                AI Review runs per selected timeframe (D1/W1/MN1) using candles + support/resistance + stochastic. Different timeframes can look similar.
            </div>
            <div id="aiReason" class="mt-2 whitespace-pre-wrap text-sm leading-snug text-slate-300"></div>
            <div id="aiDetails" class="mt-2 whitespace-pre-wrap text-sm leading-snug text-slate-300"></div>
        </div>

        <div id="chart" class="h-[560px] overflow-hidden rounded-xl border border-slate-800 bg-slate-900/60"></div>
        <div id="volWrap" class="hidden overflow-hidden rounded-xl border border-slate-800 bg-slate-900/60">
            <div id="volChart" class="h-[140px]"></div>
        </div>
        <div id="stochWrap" class="hidden overflow-hidden rounded-xl border border-slate-800 bg-slate-900/60">
            <div id="stochChart" class="h-[180px]"></div>
        </div>
    </div>

    <div class="mt-3">
        <details class="group rounded-xl border border-slate-800 bg-slate-900/60 px-3 py-2">
            <summary class="cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold text-slate-100">Indicators</div>
                        <div class="text-xs text-slate-400">Optional (default off)</div>
                    </div>
                    <div class="text-xs text-slate-400 transition group-open:rotate-180">▼</div>
                </div>
            </summary>
            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 md:items-end">
                <div class="md:col-span-2">
                    <label class="block text-xs text-slate-400">Enable</label>
                    <div class="mt-2 flex flex-wrap items-center gap-4 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-200"><input id="showVol" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-blue-500/30" />Volume</label>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-200"><input id="showSr" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-blue-500/30" />SR</label>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-200"><input id="showStoch" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-blue-500/30" />Stoch</label>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs text-slate-400">Notes</label>
                    <div class="mt-2 text-xs text-slate-400">FX volume is often unavailable. When provider volume is missing, the chart shows a simple activity proxy (range).</div>
                </div>

                <div>
                    <label for="stochK" class="block text-xs text-slate-400">Stoch K</label>
                    <input id="stochK" type="number" min="2" max="100" step="1" class="mt-2 block w-full min-w-[110px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>
                <div>
                    <label for="stochD" class="block text-xs text-slate-400">Stoch D</label>
                    <input id="stochD" type="number" min="1" max="50" step="1" class="mt-2 block w-full min-w-[110px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>
                <div>
                    <label for="stochSmooth" class="block text-xs text-slate-400">Stoch Smooth</label>
                    <input id="stochSmooth" type="number" min="1" max="50" step="1" class="mt-2 block w-full min-w-[110px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>
                <div>
                    <label for="srLookback" class="block text-xs text-slate-400">SR Lookback</label>
                    <input id="srLookback" type="number" min="50" max="2000" step="1" class="mt-2 block w-full min-w-[110px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>
                <div>
                    <label for="srLevels" class="block text-xs text-slate-400">SR Levels</label>
                    <input id="srLevels" type="number" min="1" max="50" step="1" class="mt-2 block w-full min-w-[110px] rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>
            </div>
        </details>
    </div>

    @if(auth()->user()?->is_admin)
        <div class="mt-3 rounded-xl border border-slate-800 bg-slate-900/60 p-3" id="tradePanel">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span id="tradeBadge" class="inline-flex items-center rounded-full border border-slate-700 px-2 py-0.5 text-xs text-slate-400">Trade</span>
                    <span id="tradeSummary" class="text-xs text-slate-400">Submit an open trade to get an AI management review.</span>
                    <span id="tradeSpinner" class="hidden h-3 w-3 animate-spin rounded-full border-2 border-slate-700 border-t-blue-300"></span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div id="tradeMeta" class="text-xs text-slate-400"></div>
                    <button id="tradeReviewBtn" class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500/30" type="button">Review current trade</button>
                </div>
            </div>

            <div class="mt-2 text-xs text-slate-400">
                Prices are absolute chart prices (not pips). Tip: click the chart to fill the focused price field.
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3 md:items-end">
                <div>
                    <label for="tradeSide" class="block text-xs text-slate-400">Side</label>
                    <select id="tradeSide" class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                        <option value="BUY">BUY</option>
                        <option value="SELL">SELL</option>
                    </select>
                </div>
                <div>
                    <label for="tradeEntry" class="block text-xs text-slate-400">Entry (price)</label>
                    <input id="tradeEntry" type="number" step="0.00001" inputmode="decimal" placeholder="e.g. 1.08420" class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>
                <div>
                    <label for="tradeStop" class="block text-xs text-slate-400">Stop loss (price)</label>
                    <input id="tradeStop" type="number" step="0.00001" inputmode="decimal" placeholder="e.g. 1.08100" class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>

                <div>
                    <label for="tradeTp" class="block text-xs text-slate-400">Take profit (price, optional)</label>
                    <input id="tradeTp" type="number" step="0.00001" inputmode="decimal" placeholder="e.g. 1.09200" class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>
                <div class="md:col-span-2">
                    <label for="tradeOpenedAt" class="block text-xs text-slate-400">Opened at (optional)</label>
                    <input id="tradeOpenedAt" type="datetime-local" class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                </div>

                <div class="md:col-span-3">
                    <label for="tradeNotes" class="block text-xs text-slate-400">Notes (optional)</label>
                    <textarea id="tradeNotes" rows="2" class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"></textarea>
                </div>
            </div>

            <div id="tradeReason" class="mt-3 whitespace-pre-wrap text-sm leading-snug text-slate-300"></div>
            <div id="tradeDetails" class="mt-2 whitespace-pre-wrap text-sm leading-snug text-slate-300"></div>
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
        <div>Defaults: D1 = last 2 years, W1 = last 5 years, MN1 = last 15 years</div>
        <div>
            <a href="/" class="text-slate-300 hover:text-white">Home</a>
            @if(auth()->user()?->is_admin)
                <span class="px-1">·</span>
                <a href="{{ route('admin.settings') }}" class="text-slate-300 hover:text-white">Admin Settings</a>
            @endif
        </div>
    </div>
</div>

<script src="https://unpkg.com/lightweight-charts@4.2.1/dist/lightweight-charts.standalone.production.js"></script>
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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
    const elAiBadge = document.getElementById('aiBadge');
    const elAiSummary = document.getElementById('aiSummary');
    const elAiSpinner = document.getElementById('aiSpinner');
    const elAiMeta = document.getElementById('aiMeta');
    const elAiReason = document.getElementById('aiReason');
    const elAiDetails = document.getElementById('aiDetails');
    const elAiReviewBtn = document.getElementById('aiReviewBtn');

    const elTradeBadge = document.getElementById('tradeBadge');
    const elTradeSummary = document.getElementById('tradeSummary');
    const elTradeSpinner = document.getElementById('tradeSpinner');
    const elTradeMeta = document.getElementById('tradeMeta');
    const elTradeReviewBtn = document.getElementById('tradeReviewBtn');
    const elTradeReason = document.getElementById('tradeReason');
    const elTradeDetails = document.getElementById('tradeDetails');
    const elTradeSide = document.getElementById('tradeSide');
    const elTradeEntry = document.getElementById('tradeEntry');
    const elTradeStop = document.getElementById('tradeStop');
    const elTradeTp = document.getElementById('tradeTp');
    const elTradeOpenedAt = document.getElementById('tradeOpenedAt');
    const elTradeNotes = document.getElementById('tradeNotes');
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

    let aiReqToken = 0;

    let tradeActivePriceEl = null;
    let lastPriceDecimals = 5;

    function setStatus(kind, text) {
        elStatusBadge.textContent = kind;
        elStatusBadge.style.borderColor = '#243043';
        elStatusBadge.style.color = '#9ca3af';
        elStatusText.classList.remove('text-red-300');
        elStatusText.textContent = text;

        if (kind === 'loading') {
            elStatusBadge.style.color = '#93c5fd';
        }

        if (kind === 'error') {
            elStatusBadge.style.color = '#fca5a5';
            elStatusText.classList.add('text-red-300');
        }

        if (kind === 'ok') {
            elStatusBadge.style.color = '#86efac';
            lastOkStatusText = text;
        }
    }

    function guessPriceDecimals(v) {
        if (v === null || v === undefined) return 5;
        const s = String(v);
        if (!s.includes('.')) return 5;
        const part = s.split('.')[1] || '';
        const d = part.length;
        return (d >= 2 && d <= 8) ? d : 5;
    }

    function fmtPrice(p) {
        if (!Number.isFinite(p)) return '';
        return p.toFixed(lastPriceDecimals);
    }

    function setTradeUi(kind, summary, meta, reason, details, spinning) {
        if (!elTradeBadge || !elTradeSummary) return;

        elTradeBadge.textContent = kind;
        elTradeBadge.style.borderColor = '#243043';
        elTradeBadge.style.color = '#9ca3af';

        elTradeSummary.classList.remove('text-red-300');
        elTradeSummary.textContent = summary || '';

        if (elTradeMeta) elTradeMeta.textContent = meta || '';
        if (elTradeReason) elTradeReason.textContent = reason || '';
        if (elTradeDetails) elTradeDetails.textContent = details || '';

        if (kind === 'HOLD') {
            elTradeBadge.style.color = '#86efac';
        }
        if (kind === 'EXIT') {
            elTradeBadge.style.color = '#fca5a5';
        }
        if (kind === 'ADJUST_STOP') {
            elTradeBadge.style.color = '#93c5fd';
        }
        if (kind === 'WAIT') {
            elTradeBadge.style.color = '#fbbf24';
        }
        if (kind === 'error') {
            elTradeBadge.style.color = '#fca5a5';
            elTradeSummary.classList.add('text-red-300');
        }

        if (elTradeSpinner) {
            if (spinning) elTradeSpinner.classList.remove('hidden');
            else elTradeSpinner.classList.add('hidden');
        }
    }

    function setSyncUi(kind, text, spinning) {
        if (!elSyncText || !elSyncBadge || !elSyncSpinner) return;
        elSyncBadge.textContent = kind;
        elSyncBadge.style.borderColor = '#243043';
        elSyncBadge.style.color = '#9ca3af';
        elSyncText.classList.remove('text-red-300');
        elSyncText.textContent = text;

        if (kind === 'queued' || kind === 'running') {
            elSyncBadge.style.color = '#93c5fd';
        }
        if (kind === 'failed') {
            elSyncBadge.style.color = '#fca5a5';
            elSyncText.classList.add('text-red-300');
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

    function setAiUi(kind, summary, meta, reason, details, spinning) {
        if (!elAiBadge || !elAiSummary) return;

        elAiBadge.textContent = kind;
        elAiBadge.style.borderColor = '#243043';
        elAiBadge.style.color = '#9ca3af';

        elAiSummary.classList.remove('text-red-300');
        elAiSummary.textContent = summary || '';

        if (elAiMeta) elAiMeta.textContent = meta || '';
        if (elAiReason) elAiReason.textContent = reason || '';
        if (elAiDetails) elAiDetails.textContent = details || '';

        if (kind === 'BUY') {
            elAiBadge.style.color = '#86efac';
        }
        if (kind === 'SELL') {
            elAiBadge.style.color = '#fca5a5';
        }
        if (kind === 'WAIT') {
            elAiBadge.style.color = '#fbbf24';
        }
        if (kind === 'error') {
            elAiBadge.style.color = '#fca5a5';
            elAiSummary.classList.add('text-red-300');
        }

        if (elAiSpinner) {
            if (spinning) elAiSpinner.classList.remove('hidden');
            else elAiSpinner.classList.add('hidden');
        }
    }

    async function runAiReview() {
        if (!elAiReviewBtn) return;

        const symbol = elSymbol.value;
        const timeframe = elTimeframe.value;
        if (!symbol || !timeframe) return;

        elAiReviewBtn.disabled = true;
        setAiUi('loading', `Running AI Review (${symbol} ${timeframe})…`, '', '', '', true);

        try {
            const res = await fetch('/api/signals/review', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ symbol, timeframe }),
            });

            const payload = await res.json().catch(() => ({}));

            if (!res.ok) {
                const msg = payload?.message || `AI Review failed (${res.status})`;
                setAiUi('error', msg, '', '', '', false);
                return;
            }

            const data = payload?.data || {};
            const sig = String(data?.signal || 'WAIT');
            const asOf = data?.as_of_date || null;
            const conf = (data?.confidence === null || data?.confidence === undefined) ? null : Number(data?.confidence);
            const reason = String(data?.reason || '');
            const model = data?.model ? String(data.model) : '—';

            const summaryParts = [sig];
            if (Number.isFinite(conf)) summaryParts.push(`(${Math.round(conf)}%)`);
            if (asOf) summaryParts.push(`as of ${asOf}`);

            const levels = Array.isArray(data?.levels_json) ? data.levels_json : [];
            const lvlText = levels
                .map(l => {
                    const type = l?.type ? String(l.type) : '';
                    const price = Number(l?.price);
                    if (!Number.isFinite(price)) return null;
                    return `${type}:${price}`;
                })
                .filter(Boolean);

            const stochInterp = data?.stoch_json?.interpretation ? String(data.stoch_json.interpretation) : '';
            const details = [
                lvlText.length ? `Key levels: ${lvlText.join(', ')}` : '',
                stochInterp ? `Stochastic: ${stochInterp}` : '',
            ].filter(Boolean).join('\n');

            setAiUi(sig, summaryParts.join(' · '), `Model: ${model}`, reason, details, false);
        } catch (e) {
            setAiUi('error', e?.message ? String(e.message) : 'AI Review failed', '', '', '', false);
        } finally {
            elAiReviewBtn.disabled = false;
        }
    }

    async function runTradeReview() {
        if (!elTradeReviewBtn || !elTradeSide || !elTradeEntry || !elTradeStop) return;

        const symbol = elSymbol.value;
        const timeframe = elTimeframe.value;
        if (!symbol || !timeframe) return;

        const side = String(elTradeSide.value || 'BUY');
        const entry = Number(elTradeEntry.value);
        const stop = Number(elTradeStop.value);
        const tpRaw = elTradeTp ? String(elTradeTp.value || '') : '';
        const tp = tpRaw ? Number(tpRaw) : null;
        const openedAtRaw = elTradeOpenedAt ? String(elTradeOpenedAt.value || '') : '';
        const openedAt = openedAtRaw ? new Date(openedAtRaw).toISOString() : null;
        const notes = elTradeNotes ? String(elTradeNotes.value || '') : '';

        if (!Number.isFinite(entry) || !Number.isFinite(stop)) {
            setTradeUi('error', 'Entry price and stop loss are required.', '', '', '', false);
            return;
        }

        elTradeReviewBtn.disabled = true;
        setTradeUi('loading', `Reviewing trade (${symbol} ${timeframe})…`, '', '', '', true);

        try {
            const res = await fetch('/api/trades/review', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    symbol,
                    timeframe,
                    side,
                    entry_price: entry,
                    stop_loss: stop,
                    take_profit: tp,
                    opened_at: openedAt,
                    notes: notes || null,
                }),
            });

            const payload = await res.json().catch(() => ({}));

            if (!res.ok) {
                const msg = payload?.message || `Trade review failed (${res.status})`;
                setTradeUi('error', msg, '', '', '', false);
                return;
            }

            const data = payload?.data || {};
            const review = data?.review_json || {};

            const decision = String(review?.decision || 'WAIT');
            const conf = (review?.confidence === null || review?.confidence === undefined) ? null : Number(review?.confidence);
            const candleAsOf = String(data?.candle_as_of_date || '');
            const generatedAt = String(data?.generated_at || '');
            const model = data?.model ? String(data.model) : '—';

            const summaryParts = [decision];
            if (Number.isFinite(conf)) summaryParts.push(`(${Math.round(conf)}%)`);
            if (candleAsOf) summaryParts.push(`as of ${candleAsOf}`);

            const summary = String(review?.summary || '');
            const plan = String(review?.management_plan || '');
            const invalidation = String(review?.invalidation || '');

            const levels = Array.isArray(review?.key_levels) ? review.key_levels : [];
            const lvlText = levels
                .map(l => {
                    const type = l?.type ? String(l.type) : '';
                    const price = Number(l?.price);
                    if (!Number.isFinite(price)) return null;
                    return `${type}:${price}`;
                })
                .filter(Boolean);

            const details = [
                lvlText.length ? `Key levels: ${lvlText.join(', ')}` : '',
                invalidation ? `Invalidation: ${invalidation}` : '',
                plan ? `Plan: ${plan}` : '',
            ].filter(Boolean).join('\n');

            const meta = [
                model ? `Model: ${model}` : '',
                generatedAt ? `Generated: ${humanizeIso(generatedAt)}` : '',
            ].filter(Boolean).join(' · ');

            setTradeUi(decision, summaryParts.join(' · '), meta, summary, details, false);
        } catch (e) {
            setTradeUi('error', e?.message ? String(e.message) : 'Trade review failed', '', '', '', false);
        } finally {
            elTradeReviewBtn.disabled = false;
        }
    }

    function buildLatestSignalUrl() {
        const params = new URLSearchParams();
        params.set('symbol', elSymbol.value);
        params.set('timeframe', elTimeframe.value);
        return `/api/signals/latest?${params.toString()}`;
    }

    async function refreshAiSignal() {
        if (!elAiSummary) return;
        const token = ++aiReqToken;
        const symbol = elSymbol.value;
        const timeframe = elTimeframe.value;
        if (!symbol || !timeframe) return;

        setAiUi('loading', `Loading signal (${symbol} ${timeframe})…`, '', '', '', true);

        try {
            const url = buildLatestSignalUrl();
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const payload = await res.json().catch(() => ({}));
            if (token !== aiReqToken) return;

            if (!res.ok) {
                if (res.status === 404) {
                    setAiUi('none', `No signal yet for ${symbol} ${timeframe}. Click AI Review.`, '', '', '', false);
                    return;
                }
                const msg = payload?.message || `Signal request failed (${res.status})`;
                setAiUi('error', msg, '', '', '', false);
                return;
            }

            const data = payload?.data || {};
            const sig = String(data?.signal || 'WAIT');
            const asOf = data?.as_of_date || null;
            const conf = (data?.confidence === null || data?.confidence === undefined) ? null : Number(data?.confidence);
            const reason = String(data?.reason || '');
            const model = data?.model ? String(data.model) : '—';

            const summaryParts = [sig];
            if (Number.isFinite(conf)) summaryParts.push(`(${Math.round(conf)}%)`);
            if (asOf) summaryParts.push(`as of ${asOf}`);

            const levels = Array.isArray(data?.levels_json) ? data.levels_json : [];
            const lvlText = levels
                .map(l => {
                    const type = l?.type ? String(l.type) : '';
                    const price = Number(l?.price);
                    if (!Number.isFinite(price)) return null;
                    return `${type}:${price}`;
                })
                .filter(Boolean);

            const stochInterp = data?.stoch_json?.interpretation ? String(data.stoch_json.interpretation) : '';
            const details = [
                lvlText.length ? `Key levels: ${lvlText.join(', ')}` : '',
                stochInterp ? `Stochastic: ${stochInterp}` : '',
            ].filter(Boolean).join('\n');

            setAiUi(sig, summaryParts.join(' · '), `Model: ${model}`, reason, details, false);
        } catch (e) {
            if (token !== aiReqToken) return;
            setAiUi('error', e?.message ? String(e.message) : 'Signal load failed', '', '', '', false);
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
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ symbol }),
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

    chart.subscribeClick((param) => {
        if (!tradeActivePriceEl) return;
        if (!param || !param.point) return;
        const price = series.coordinateToPrice(param.point.y);
        if (!Number.isFinite(price)) return;
        tradeActivePriceEl.value = fmtPrice(price);
        tradeActivePriceEl.dispatchEvent(new Event('change'));
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

        refreshAiSignal();

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

            if (candles.length > 0) {
                lastPriceDecimals = guessPriceDecimals(candles[candles.length - 1]?.c);
            }
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

                if (elTradeEntry && String(elTradeEntry.value || '') === '') {
                    const lastClose = Number(candles[candles.length - 1].c);
                    if (Number.isFinite(lastClose)) {
                        elTradeEntry.value = fmtPrice(lastClose);
                    }
                }
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
        requestAnimationFrame(() => resizeChart());
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

            if (elAiReviewBtn) {
                elAiReviewBtn.addEventListener('click', () => runAiReview());
            }

            if (elTradeReviewBtn) {
                elTradeReviewBtn.addEventListener('click', () => runTradeReview());
            }

            const tradePriceEls = [elTradeEntry, elTradeStop, elTradeTp].filter(Boolean);
            for (const el of tradePriceEls) {
                el.addEventListener('focus', () => { tradeActivePriceEl = el; });
            }
        } catch (e) {
            setStatus('error', e?.message ? String(e.message) : 'Init failed');
        }
    })();
</script>

@endsection
