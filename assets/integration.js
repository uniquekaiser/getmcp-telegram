(() => {
  const config = window.getmcpTelegramIntegration;
  if (!config) return;
  let queued = false;
  const render = () => {
    queued = false;
    const root = document.getElementById('getmcp-admin-root');
    if (!root) return;
    const heading = [...root.querySelectorAll('h1')].find(node => ['Templates', 'GetMCP Extensions'].includes(node.textContent));
    const old = document.getElementById('getmcp-telegram-extension-card');
    if (!heading) { if (old) old.remove(); return; }
    const templates = heading.textContent === 'Templates';
    const content = heading.closest('main');
    if (templates && content) { const summary = [...content.querySelectorAll('p')].find(node => /pre-built tool configurations for popular APIs$/.test(node.textContent)); if (summary) summary.textContent += ' · 1 extension template'; }
    if (!content) return;
    const search = content.querySelector('input[placeholder="Search templates..."]');
    const filter = content.querySelector('button[aria-label="Filter by category"]');
    const query = (search?.value || '').trim().toLowerCase();
    const category = filter?.textContent.trim() || 'All';
    const show = !templates || ((!query || 'telegram personal account messaging synergetic'.includes(query)) && ['All', 'Productivity'].includes(category));
    const empty = [...content.querySelectorAll('h3')].find(node => node.textContent === 'No templates found')?.parentElement;
    if (empty) { if (templates && show) empty.style.setProperty('display', 'none', 'important'); else if (!content.querySelector('[data-marketing-template]')) empty.style.removeProperty('display'); }
    if (!show) { if (old) old.remove(); return; }
    let grid = content.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3');
    if (templates && !grid) {
      grid = content.querySelector('[data-telegram-catalogue-grid]');
      if (!grid) { grid = document.createElement('div'); grid.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6'; grid.dataset.telegramCatalogueGrid = 'true'; (empty?.parentElement || content).append(grid); }
    }
    const target = templates ? grid : content;
    if (old && target.contains(old)) return;
    if (old) old.remove();
    const card = document.createElement('section'); card.id = 'getmcp-telegram-extension-card';
    card.className = 'bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col hover:shadow-md transition-all';
    const badge = document.createElement('span'); badge.className = 'text-xs text-gray-500 mb-4'; badge.textContent = templates ? 'Productivity · Extension' : `Version ${config.version}`;
    const title = document.createElement(templates ? 'h3' : 'h2'); title.className = 'text-base font-semibold text-gray-900 mb-1'; title.textContent = templates ? 'Telegram · Personal account' : 'GetMCP Telegram Extension';
    const detail = document.createElement('p'); detail.className = 'text-sm text-gray-500 flex-1 mb-4'; detail.textContent = 'Connect your personal Telegram account. Verify it before publishing; write tools start disabled. Add further accounts when needed.';
    const count = document.createElement('p'); count.className = 'text-xs text-gray-400 mb-4'; count.textContent = `${config.count} tools · Personal Telegram sign-in · Synergetic Dev`;
    const link = document.createElement('a'); link.href = config.url; link.className = 'button button-primary'; link.textContent = templates ? 'Set up Telegram' : 'Manage Telegram accounts';
    card.append(badge, title, detail, count, link); target.prepend(card);
  };
  const schedule = () => { if (!queued) { queued = true; requestAnimationFrame(render); } };
  new MutationObserver(schedule).observe(document.getElementById('wpbody-content') || document.body, {childList:true, subtree:true});
  document.addEventListener('input', schedule); window.addEventListener('hashchange', schedule); render();
})();
