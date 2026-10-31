/* scripts.js
   Shared marketplace logic for Dashboard, Market, Portfolio, Transactions.
   - Persists to localStorage keys: marketData, portfolioData, transactionsData, usersData
   - Provides matching engine, buy/sell/listing flows, admin price updates
   - Renders dashboard charts and snapshots
   - Exposes functions used by other pages (market.html, portfolio.html, transactions.html)
*/

/* ---------- Utilities ---------- */
const currency = v => 'R' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const nowISO = () => new Date().toISOString();

/* ---------- Storage helpers ---------- */
function loadAll() {
  const market = JSON.parse(localStorage.getItem('marketData') || 'null');
  const portfolio = JSON.parse(localStorage.getItem('portfolioData') || 'null');
  const transactions = JSON.parse(localStorage.getItem('transactionsData') || 'null');
  const users = JSON.parse(localStorage.getItem('usersData') || 'null');
  return {
    market: market || getDefaultMarket(),
    portfolio: portfolio || [],
    transactions: transactions || [],
    users: users || ['me']
  };
}

function saveAll({ market, portfolio, transactions, users }) {
  localStorage.setItem('marketData', JSON.stringify(market));
  localStorage.setItem('portfolioData', JSON.stringify(portfolio));
  localStorage.setItem('transactionsData', JSON.stringify(transactions));
  localStorage.setItem('usersData', JSON.stringify(users));
}

/* ---------- Seed data ---------- */
function getDefaultMarket() {
  return [
    { id: 'land-001', title: 'Land: Mpumalanga', target: 500000, sharesTotal: 500000, sharesSold: 100000, currentPrice: 1.00, offers: [], bids: [] },
    { id: 'building-001', title: 'Building: Sandton House', target: 1000000, sharesTotal: 1000000, sharesSold: 250000, currentPrice: 1.00, offers: [], bids: [] },
    { id: 'equipment-001', title: 'Equipment: Manufacturing Line', target: 200000, sharesTotal: 200000, sharesSold: 50000, currentPrice: 1.05, offers: [], bids: [] },
    { id: 'land-002', title: 'Land: Eastern Plot', target: 250000, sharesTotal: 250000, sharesSold: 40000, currentPrice: 1.00, offers: [], bids: [] }
  ];
}

/* ---------- Matching engine ---------- */
function matchOrders(market, portfolio, transactions) {
  // For each asset, match highest bid with lowest offer
  market.forEach(asset => {
    asset.bids = asset.bids || [];
    asset.offers = asset.offers || [];
    // sort bids desc, offers asc
    asset.bids.sort((a, b) => b.price - a.price || b.createdAt - a.createdAt);
    asset.offers.sort((a, b) => a.price - b.price || a.createdAt - b.createdAt);

    while (asset.bids.length && asset.offers.length && asset.bids[0].price >= asset.offers[0].price) {
      const bid = asset.bids[0];
      const offer = asset.offers[0];
      const tradeShares = Math.min(bid.shares, offer.shares);
      const tradePrice = (bid.price + offer.price) / 2;

      // Buyer (bid.user) receives shares
      const buyer = bid.user || 'me';
      let buyerHolding = portfolio.find(p => p.assetId === asset.id && p.user === buyer);
      if (!buyerHolding) {
        buyerHolding = { user: buyer, assetId: asset.id, assetTitle: asset.title, shares: 0, totalCost: 0, avgBuyPrice: 0, currentPrice: asset.currentPrice };
        portfolio.push(buyerHolding);
      }
      buyerHolding.shares += tradeShares;
      buyerHolding.totalCost += tradeShares * tradePrice;
      buyerHolding.avgBuyPrice = buyerHolding.totalCost / buyerHolding.shares;
      buyerHolding.currentPrice = asset.currentPrice;

      // Seller (offer.user) loses shares and realizes P/L
      const seller = offer.user || 'me';
      let sellerHolding = portfolio.find(p => p.assetId === asset.id && p.user === seller);
      let realizedPL = 0;
      if (sellerHolding) {
        const costBasis = (sellerHolding.avgBuyPrice || 0) * tradeShares;
        realizedPL = tradeShares * tradePrice - costBasis;
        sellerHolding.shares = Math.max(0, sellerHolding.shares - tradeShares);
        sellerHolding.totalCost = Math.max(0, (sellerHolding.avgBuyPrice || 0) * sellerHolding.shares);
        sellerHolding.avgBuyPrice = sellerHolding.shares ? (sellerHolding.totalCost / sellerHolding.shares) : 0;
      }

      // Update market sold count
      asset.sharesSold = (asset.sharesSold || 0) + tradeShares;

      // Record transaction for both sides
      transactions.push({
        date: nowISO(),
        assetId: asset.id,
        assetTitle: asset.title,
        action: 'Trade',
        shares: tradeShares,
        price: tradePrice,
        total: tradeShares * tradePrice,
        buyer,
        seller,
        realizedPL
      });

      // decrement orders
      bid.shares -= tradeShares;
      offer.shares -= tradeShares;
      if (bid.shares <= 0) asset.bids.shift();
      if (offer.shares <= 0) asset.offers.shift();
    }
  });
}

/* ---------- Core actions ---------- */
function buyAtMarket(user, assetId, amountR) {
  const { market, portfolio, transactions, users } = loadAll();
  const asset = market.find(m => m.id === assetId);
  if (!asset) throw new Error('Asset not found');
  const sharesToBuy = Math.floor(amountR / asset.currentPrice);
  if (sharesToBuy < 1) throw new Error('Amount too small for one share at current price');
  const available = (asset.sharesTotal || 0) - (asset.sharesSold || 0);
  const actualShares = Math.min(sharesToBuy, available);
  if (actualShares <= 0) throw new Error('No shares available');

  // update market
  asset.sharesSold = (asset.sharesSold || 0) + actualShares;

  // update portfolio
  let holding = portfolio.find(p => p.assetId === assetId && p.user === user);
  if (!holding) {
    holding = { user, assetId, assetTitle: asset.title, shares: 0, totalCost: 0, avgBuyPrice: 0, currentPrice: asset.currentPrice };
    portfolio.push(holding);
  }
  const totalCost = actualShares * asset.currentPrice;
  holding.shares += actualShares;
  holding.totalCost += totalCost;
  holding.avgBuyPrice = holding.totalCost / holding.shares;
  holding.currentPrice = asset.currentPrice;

  // record transaction
  transactions.push({ date: nowISO(), assetId, assetTitle: asset.title, action: 'Buy', shares: actualShares, price: asset.currentPrice, total: totalCost, user, realizedPL: 0 });

  saveAll({ market, portfolio, transactions, users });
  return { actualShares, totalCost };
}

function placeOrder(user, assetId, type, shares, price) {
  const { market, portfolio, transactions, users } = loadAll();
  const asset = market.find(m => m.id === assetId);
  if (!asset) throw new Error('Asset not found');
  const order = { id: 'ord-' + Date.now(), user, type, shares, price, createdAt: Date.now() };
  if (type === 'Bid') {
    asset.bids = asset.bids || [];
    asset.bids.push(order);
  } else {
    asset.offers = asset.offers || [];
    asset.offers.push(order);
  }
  transactions.push({ date: nowISO(), assetId, assetTitle: asset.title, action: type, shares, price, total: shares * price, user, note: 'Placed order' });
  matchOrders(market, portfolio, transactions);
  saveAll({ market, portfolio, transactions, users });
  return order;
}

function sellNow(user, assetId, sellShares, sellPrice) {
  const { market, portfolio, transactions, users } = loadAll();
  const holding = portfolio.find(p => p.assetId === assetId && p.user === user);
  if (!holding) throw new Error('Holding not found');
  if (sellShares < 1 || sellShares > holding.shares) throw new Error('Invalid sell shares');

  const marketItem = market.find(m => m.id === assetId);
  const executionPrice = sellPrice > 0 ? sellPrice : (marketItem ? marketItem.currentPrice : holding.currentPrice || 1.00);
  const total = sellShares * executionPrice;
  const costBasis = sellShares * (holding.avgBuyPrice || 0);
  const realizedPL = total - costBasis;

  // update holding
  holding.shares -= sellShares;
  holding.totalCost = Math.max(0, (holding.avgBuyPrice || 0) * holding.shares);
  holding.avgBuyPrice = holding.shares ? (holding.totalCost / holding.shares) : 0;

  // update market sold count (reduce outstanding)
  if (marketItem) marketItem.sharesSold = Math.max(0, (marketItem.sharesSold || 0) - sellShares);

  // record transaction
  transactions.push({ date: nowISO(), assetId, assetTitle: holding.assetTitle, action: 'Sell', shares: sellShares, price: executionPrice, total, user, realizedPL });

  // remove holding if zero
  if (holding.shares === 0) {
    const idx = portfolio.findIndex(p => p.assetId === assetId && p.user === user);
    if (idx >= 0) portfolio.splice(idx, 1);
  }

  saveAll({ market, portfolio, transactions, users });
  return { total, realizedPL };
}

function listOffer(user, assetId, shares, price) {
  return placeOrder(user, assetId, 'Offer', shares, price);
}

/* ---------- Admin ---------- */
function updatePrice(assetId, newPrice) {
  const { market, portfolio, transactions, users } = loadAll();
  const asset = market.find(m => m.id === assetId);
  if (!asset) throw new Error('Asset not found');
  asset.currentPrice = Number(newPrice);
  // update portfolio currentPrice for holdings of this asset
  portfolio.forEach(h => { if (h.assetId === assetId) h.currentPrice = asset.currentPrice; });
  saveAll({ market, portfolio, transactions, users });
}

/* ---------- Export CSV ---------- */
function exportTransactionsCSV() {
  const { transactions } = loadAll();
  if (!transactions.length) return '';
  const headers = ['date', 'assetTitle', 'action', 'shares', 'price', 'total', 'realizedPL', 'note', 'buyer', 'seller', 'user'];
  const rows = transactions.map(t => headers.map(h => JSON.stringify(t[h] ?? '')).join(','));
  return headers.join(',') + '\n' + rows.join('\n');
}

/* ---------- Rendering helpers (Dashboard) ---------- */
function renderDashboard() {
  const { market, portfolio, transactions } = loadAll();

  // totals
  const totalInvested = portfolio.reduce((s, p) => s + (p.totalCost || 0), 0);
  const portfolioValue = portfolio.reduce((s, p) => {
    const price = (market.find(m => m.id === p.assetId)?.currentPrice) || p.currentPrice || 1;
    return s + (p.shares * price);
  }, 0);
  const realized = transactions.reduce((s, t) => s + (t.realizedPL || 0), 0);
  const openOrders = market.reduce((s, m) => s + ((m.offers?.length || 0) + (m.bids?.length || 0)), 0);

  document.getElementById('db-total-invested').textContent = currency(totalInvested);
  document.getElementById('db-portfolio-value').textContent = currency(portfolioValue);
  document.getElementById('db-realized-pl').textContent = currency(realized);
  document.getElementById('db-open-orders').textContent = String(openOrders);

  // snapshot table
  const tbody = document.querySelector('#snapshotTable tbody');
  if (tbody) {
    tbody.innerHTML = '';
    portfolio.forEach(p => {
      const current = (market.find(m => m.id === p.assetId)?.currentPrice) || p.currentPrice || 1;
      const unrealized = (current - (p.avgBuyPrice || 0)) * p.shares;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td data-label="Asset">${p.assetTitle}</td>
        <td data-label="Shares">${p.shares.toLocaleString()}</td>
        <td data-label="Avg Buy">${currency(p.avgBuyPrice)}</td>
        <td data-label="Current">${currency(current)}</td>
        <td data-label="Unrealized P/L" class="${unrealized >= 0 ? 'pl-positive' : 'pl-negative'}">${currency(unrealized)}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  // charts
  renderAllocationChart();
  renderGrowthChart();
}

/* ---------- Charts ---------- */
let allocChart = null;
let growthChart = null;

function renderAllocationChart() {
  const { market, portfolio } = loadAll();
  const labels = [];
  const data = [];
  portfolio.forEach(p => {
    const price = (market.find(m => m.id === p.assetId)?.currentPrice) || p.currentPrice || 1;
    labels.push(p.assetTitle);
    data.push(Number((p.shares * price).toFixed(2)));
  });

  const ctx = document.getElementById('allocChart');
  if (!ctx) return;
  if (allocChart) allocChart.destroy();
  allocChart = new Chart(ctx, {
    type: 'pie',
    data: { labels, datasets: [{ data, backgroundColor: generateColors(data.length) }] },
    options: { plugins: { legend: { position: 'bottom' } } }
  });
}

function renderGrowthChart() {
  const { transactions } = loadAll();
  // cumulative invested (simple): sum of buys minus sells over time
  const points = [];
  let cumulative = 0;
  const sorted = transactions.slice().sort((a, b) => new Date(a.date) - new Date(b.date));
  sorted.forEach(t => {
    if (t.action === 'Buy') cumulative += (t.total || 0);
    else if (t.action === 'Sell') cumulative -= (t.total || 0);
    points.push({ date: new Date(t.date), value: cumulative });
  });

  const labels = points.map(p => p.date.toLocaleDateString());
  const data = points.map(p => Number(p.value.toFixed(2)));

  const ctx = document.getElementById('growthChart');
  if (!ctx) return;
  if (growthChart) growthChart.destroy();
  growthChart = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: [{ label: 'Net Cash Flow', data, borderColor: '#1e3a8a', backgroundColor: 'rgba(30,58,138,0.08)', fill: true }] },
    options: { scales: { x: { display: true }, y: { display: true } }, plugins: { legend: { display: false } } }
  });
}

/* ---------- Helpers ---------- */
function generateColors(n) {
  const base = ['#0f172a', '#1e3a8a', '#2563eb', '#0ade5b', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
  const out = [];
  for (let i = 0; i < n; i++) out.push(base[i % base.length]);
  return out;
}

/* ---------- Page wiring for admin controls on dashboard ---------- */
function populateAdminAssetSelect() {
  const sel = document.getElementById('adminAsset');
  if (!sel) return;
  const { market } = loadAll();
  sel.innerHTML = market.map(m => `<option value="${m.id}">${m.title}</option>`).join('');
}

function initDashboard() {
  populateAdminAssetSelect();
  renderDashboard();

  const btn = document.getElementById('adminUpdate');
  if (btn) {
    btn.addEventListener('click', () => {
      const assetId = document.getElementById('adminAsset').value;
      const price = Number(document.getElementById('adminPrice').value || 0);
      if (!assetId || price <= 0) return alert('Select asset and enter a valid price');
      updatePrice(assetId, price);
      renderDashboard();
      alert('Price updated');
    });
  }
}

/* ---------- Generic renderers for other pages (market, portfolio, transactions) ---------- */
function renderMarketPage(containerId = 'listings') {
  const { market } = loadAll();
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = '';
  market.forEach(m => {
    const div = document.createElement('article');
    div.className = 'asset-card';
    div.innerHTML = `
      <div class="asset-head">
        <h3 class="asset-title">${m.title}</h3>
        <div class="badge">Target ${currency(m.target)}</div>
      </div>
      <div class="asset-meta"><div class="asset-sub">Shares: ${m.sharesTotal.toLocaleString()} • Sold: ${m.sharesSold.toLocaleString()} • Current: ${currency(m.currentPrice)}</div></div>
      <div class="asset-body">
        <table class="orders-table">
          <thead><tr><th>Type</th><th>Shares</th><th>Price</th></tr></thead>
          <tbody>
            ${((m.offers || []).concat(m.bids || [])).map(o => `<tr><td data-label="Type">${o.type || (o.user ? 'Offer' : 'Order')}</td><td data-label="Shares">${o.shares.toLocaleString()}</td><td data-label="Price">${currency(o.price)}</td></tr>`).join('') || '<tr><td colspan="3">No open orders</td></tr>'}
          </tbody>
        </table>
      </div>
      <div class="asset-actions">
        <button class="form-btn" data-asset="${m.id}" data-action="buy">Buy at Market</button>
        <button class="form-btn" data-asset="${m.id}" data-action="make-offer">Make Offer</button>
        <button class="form-btn" data-asset="${m.id}" data-action="place-bid">Place Bid</button>
      </div>
    `;
    container.appendChild(div);
  });

  // attach handlers
  container.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', e => {
      const action = e.currentTarget.dataset.action;
      const assetId = e.currentTarget.dataset.asset;
      if (action === 'buy') {
        const amount = prompt('Enter amount in R to spend (R1 per share nominal):', '100');
        if (!amount) return;
        try {
          const res = buyAtMarket('me', assetId, Number(amount));
          alert(`Bought ${res.actualShares.toLocaleString()} shares for ${currency(res.totalCost)}`);
          renderMarketPage(containerId);
        } catch (err) { alert(err.message); }
      } else if (action === 'make-offer' || action === 'place-bid') {
        const shares = Number(prompt('Enter number of shares:', '100'));
        const price = Number(prompt('Enter price per share (R):', '1.00'));
        if (!shares || !price) return alert('Invalid input');
        const type = action === 'place-bid' ? 'Bid' : 'Offer';
        placeOrder('me', assetId, type, shares, price);
        alert(`${type} placed for ${shares.toLocaleString()} shares at ${currency(price)}`);
        renderMarketPage(containerId);
      }
    });
  });
}

function renderPortfolioPage(tableId = 'port') {
  const { market, portfolio } = loadAll();
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!tbody) return;
  tbody.innerHTML = '';
  portfolio.forEach(p => {
    const current = (market.find(m => m.id === p.assetId)?.currentPrice) || p.currentPrice || 1;
    const unrealized = (current - (p.avgBuyPrice || 0)) * p.shares;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td data-label="Asset">${p.assetTitle}</td>
      <td data-label="Shares">${p.shares.toLocaleString()}</td>
      <td data-label="Avg Buy">${currency(p.avgBuyPrice)}</td>
      <td data-label="Current">${currency(current)}</td>
      <td data-label="P/L" class="${unrealized >= 0 ? 'pl-positive' : 'pl-negative'}">${currency(unrealized)}</td>
      <td data-label="Sell">
        <div class="asset-actions">
          <button class="action-btn" data-action="sell" data-asset="${p.assetId}" data-user="${p.user}">Sell Now</button>
          <button class="action-btn action-secondary" data-action="list" data-asset="${p.assetId}" data-user="${p.user}">List on Market</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });

  // attach handlers
  tbody.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', e => {
      const action = e.currentTarget.dataset.action;
      const assetId = e.currentTarget.dataset.asset;
      const user = e.currentTarget.dataset.user || 'me';
      if (action === 'sell') {
        const shares = Number(prompt('Enter shares to sell:', '100'));
        const price = Number(prompt('Enter price per share (leave blank for market price):', ''));
        if (!shares) return alert('Invalid shares');
        try {
          const res = sellNow(user, assetId, shares, Number(price || 0));
          alert(`Sold ${shares.toLocaleString()} shares. Realized ${currency(res.realizedPL)}`);
          renderPortfolioPage(tableId);
        } catch (err) { alert(err.message); }
      } else if (action === 'list') {
        const shares = Number(prompt('Enter shares to list:', '100'));
        const price = Number(prompt('Enter price per share:', '1.00'));
        if (!shares || !price) return alert('Invalid input');
        listOffer(user, assetId, shares, price);
        alert('Offer listed on market');
        renderPortfolioPage(tableId);
      }
    });
  });
}

function renderTransactionsPage(tableId = 'ledgerTable') {
  const { transactions } = loadAll();
  const tbody = document.querySelector(`#${tableId} tbody`);
  if (!tbody) return;
  tbody.innerHTML = '';
  transactions.slice().reverse().forEach(t => {
    const tr = document.createElement('tr');
    const plClass = t.realizedPL > 0 ? 'tx-pl-positive' : (t.realizedPL < 0 ? 'tx-pl-negative' : 'tx-pl-neutral');
    tr.innerHTML = `
      <td class="col-date" data-label="Date">${new Date(t.date).toLocaleString()}</td>
      <td class="col-asset" data-label="Asset">${t.assetTitle || t.assetId}</td>
      <td class="col-action" data-label="Action">${t.action}</td>
      <td class="col-shares" data-label="Shares">${(t.shares || 0).toLocaleString()}</td>
      <td class="col-price" data-label="Price">${currency(t.price || 0)}</td>
      <td class="col-total" data-label="Total">${currency(t.total || 0)}</td>
      <td class="col-pl ${plClass}" data-label="Realized P/L">${t.realizedPL ? currency(t.realizedPL) : '—'}</td>
      <td class="col-note" data-label="Note">${t.note || ''}</td>
    `;
    tbody.appendChild(tr);
  });
}

/* ---------- Accordion helper ---------- */
function initAccordions(root = document) {
  root.querySelectorAll('.accordion-header').forEach(h => {
    const acc = h.parentElement;
    const body = acc.querySelector('.accordion-body');
    h.addEventListener('click', () => {
      const open = acc.classList.toggle('open');
      h.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (body) body.setAttribute('aria-hidden', open ? 'false' : 'true');
    });
    h.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); h.click(); }
    });
  });
}

/* ---------- Init on DOM ready ---------- */
document.addEventListener('DOMContentLoaded', () => {
  // ensure seed data exists
  const data = loadAll();
  saveAll(data);

  // page-specific initializations
  if (document.getElementById('dashboardRoot')) {
    initDashboard();
    initAccordions(document);
  }
  if (document.getElementById('marketRoot') || document.getElementById('listings')) {
    // render market if page contains listings container
    renderMarketPage('listings');
    initAccordions(document);
  }
  if (document.getElementById('portfolioRoot') || document.getElementById('port') || document.getElementById('holdingsTable')) {
    renderPortfolioPage('port');
    initAccordions(document);
  }
  if (document.getElementById('transactionsRoot') || document.getElementById('ledgerTable')) {
    renderTransactionsPage('ledgerTable');
  }

  // wire export CSV if present
  const exportBtn = document.getElementById('exportCsv');
  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      const csv = exportTransactionsCSV();
      if (!csv) return alert('No transactions to export');
      // create a blob and download (client-side)
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'transactions.csv';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    });
  }

  // keep pages in sync when localStorage changes
  window.addEventListener('storage', () => {
    if (document.getElementById('dashboardRoot')) renderDashboard();
    if (document.getElementById('listings')) renderMarketPage('listings');
    if (document.getElementById('port')) renderPortfolioPage('port');
    if (document.getElementById('ledgerTable')) renderTransactionsPage('ledgerTable');
  });
});