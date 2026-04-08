// assets/js/main.js

let chartInstance = null;

const euro = new Intl.NumberFormat('es-ES', {
  style: 'currency',
  currency: 'EUR'
});

function el(id) { return document.getElementById(id); }

function getFilters() {
  return {
    chart_month: el('chart_month')?.value || '',
    category: el('category')?.value || '',
    q: el('q')?.value || ''
  };
}

function currentMode() {
  const btnCat = el('toggle-category');
  return (btnCat && btnCat.getAttribute('aria-pressed') === 'true') ? 'category' : 'day';
}

function setMode(mode) {
  const btnCat = el('toggle-category');
  const btnDay = el('toggle-day');
  if (!btnCat || !btnDay) return;

  const isCat = mode === 'category';
  btnCat.setAttribute('aria-pressed', isCat ? 'true' : 'false');
  btnDay.setAttribute('aria-pressed', !isCat ? 'true' : 'false');
}

async function fetchData(mode) {
  const { chart_month, category, q } = getFilters();
  const params = new URLSearchParams();

  params.set('mode', mode);

  // Mes del gráfico: si está vacío, usar mes actual y ponerlo en el input
  let m = chart_month;
  if (!m) {
    m = new Date().toISOString().slice(0, 7);
    const cm = el('chart_month');
    if (cm) cm.value = m;
  }
  params.set('month', m);

  // Categoría solo en modo día
  if (mode === 'day' && category) params.set('category', category);
  if (q) params.set('q', q);

  const url = new URL('../back/controllers/chart_data.php', window.location.href);
  url.search = params.toString();

  const res = await fetch(url.toString(), {
    headers: { Accept: 'application/json' }
  });

  if (!res.ok) {
    const txt = await res.text().catch(() => '');
    console.error('Chart error:', res.status, txt);
    throw new Error('No se pudo cargar el gráfico');
  }

  const json = await res.json();
  return json;
}


function colors(n) {
  // Paleta simple (se repite si hay muchas categorías)
  const base = [
    '#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed',
    '#0ea5e9', '#14b8a6', '#f97316', '#e11d48', '#64748b'
  ];
  const arr = [];
  for (let i = 0; i < n; i++) arr.push(base[i % base.length]);
  return arr;
}

function render(payload) {
  const canvas = el('chart');
  const wrap = el('chart-wrap');
  const errBox = el('chart-error');
  if (!canvas) return;

  if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

  const ctx = canvas.getContext('2d');

  // Limpia gráfico anterior
  if (chartInstance) {
    chartInstance.destroy();
    chartInstance = null;
  }

  const mode = payload.mode;
  const labels = payload.labels || [];
  const values = payload.values || [];

  if (wrap) wrap.style.height = mode === 'category' ? '280px' : '260px';

  const title = mode === 'category'
    ? `Gastos por categoría · ${payload.month}`
    : `Gastos por día · ${payload.month}`;

  const type = (mode === 'category') ? 'doughnut' : 'bar';

  chartInstance = new Chart(ctx, {
    type,
    data: {
      labels,
      datasets: [{
        label: 'Gastos',
        data: values,
        backgroundColor: (mode === 'category') ? colors(values.length) : '#2563eb',
        borderColor: (mode === 'category') ? '#ffffff' : '#1d4ed8',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: { display: true, text: title },
        tooltip: {
        callbacks: {
            label: (ctx) => {
            const v =
                (typeof ctx.parsed === 'number') ? ctx.parsed :
                (ctx.parsed && typeof ctx.parsed.y === 'number') ? ctx.parsed.y :
                (typeof ctx.raw === 'number') ? ctx.raw :
                Number(ctx.raw);

            return `${ctx.label}: ${euro.format(v)}`;
            }
        }
    },
        legend: {
          display: mode === 'category'
        }
      },
      scales: (mode === 'day') ? {
        y: {
          beginAtZero: true,
          ticks: {
            callback: (v) => euro.format(v)
          }
        }
      } : {}
    }
  });
}

async function refresh(mode) {
  try {
    setMode(mode);
    const data = await fetchData(mode);
    render(data);
  } catch (e) {
    const errBox = el('chart-error');
    if (errBox) {
      errBox.textContent = e.message;
      errBox.style.display = 'block';
    }
    console.error(e);
  }
}

function bindUI() {
  const btnCat = el('toggle-category');
  const btnDay = el('toggle-day');
  const chartMonthEl = el('chart_month');
  const catEl = el('category');
  const qEl = el('q');

  const handler = () => refresh(currentMode());

  btnCat?.addEventListener('click', () => refresh('category'));
  btnDay?.addEventListener('click', () => refresh('day'));

  chartMonthEl?.addEventListener('change', handler);
  catEl?.addEventListener('change', handler);

  let t = null;
  qEl?.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(handler, 250);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  bindUI();
  refresh('category'); // por defecto
});

// Modal "Eliminar TODO" 
document.addEventListener('DOMContentLoaded', () => {
  const openBtn = document.getElementById('open-delete-all');
  const modal = document.getElementById('delete-all-modal');
  const input = document.getElementById('confirm_text');
  const confirmBtn = document.getElementById('confirm-delete-all');

  if (!openBtn || !modal || !input || !confirmBtn) return;

  const openModal = () => {
    modal.setAttribute('aria-hidden', 'false');
    // focus inicial
    setTimeout(() => input.focus(), 50);
    // reset
    input.value = '';
    confirmBtn.disabled = true;
    confirmBtn.setAttribute('aria-disabled', 'true');
  };

  const closeModal = () => {
    modal.setAttribute('aria-hidden', 'true');
    openBtn.focus();
  };

  openBtn.addEventListener('click', openModal);

  modal.addEventListener('click', (e) => {
    if (e.target && e.target.hasAttribute('data-close-modal')) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (modal.getAttribute('aria-hidden') === 'false' && e.key === 'Escape') {
      closeModal();
    }
  });

  input.addEventListener('input', () => {
    const ok = input.value.trim() === 'BORRAR TODO';
    confirmBtn.disabled = !ok;
    confirmBtn.setAttribute('aria-disabled', ok ? 'false' : 'true');
  });
});