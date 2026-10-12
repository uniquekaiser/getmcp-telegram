(() => {
  'use strict';
  const app = document.getElementById('gmt-app'), status = document.getElementById('gmt-status');
  if (!app) return;
  const cfg = window.GetMCPTelegram;
  const el = (tag, text, attrs = {}) => {
    const node = document.createElement(tag);
    if (text !== null) node.textContent = text;
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
    return node;
  };
  const request = async (path, data) => {
    const response = await fetch(cfg.root + path, { method: data === undefined ? 'GET' : 'POST', credentials: 'same-origin',
      headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' }, body: data === undefined ? undefined : JSON.stringify(data), cache: 'no-store' });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'The operation failed.');
    return result;
  };
  const execute = async (button, fn) => {
    button.disabled = true; status.textContent = 'Working…';
    try { const result = await fn(); status.textContent = result.notice || 'Saved.'; await load(); }
    catch (error) { status.textContent = error.message; }
    finally { button.disabled = false; }
  };
  const field = (form, name, label, type = 'text', attrs = {}) => {
    const wrap = el('label', label), input = el('input', null, {name, type, required: 'required', ...attrs});
    wrap.append(input); form.append(wrap); return input;
  };
  const button = (parent, label, action, cls = '') => {
    const b = el('button', label, {type: 'button', class: 'button ' + cls});
    b.addEventListener('click', () => execute(b, action)); parent.append(b); return b;
  };
  const confirmedButton = (parent, label, explanation, action) => {
    const box = el('div'), open = el('button', label, {type:'button', class:'button'}), confirmation = el('div',null,{class:'gmt-confirm'});
    confirmation.hidden = true; confirmation.append(el('p',explanation));
    button(confirmation, 'Confirm ' + label, action);
    const cancel = el('button','Cancel',{type:'button',class:'button'}); cancel.addEventListener('click',() => {confirmation.hidden=true;}); confirmation.append(cancel);
    open.addEventListener('click',() => {confirmation.hidden=!confirmation.hidden;}); box.append(open,confirmation); parent.append(box);
  };
  async function load() {
    const data = await request('status'); app.replaceChildren();
    const report = el('section', null, {class: 'gmt-card'});
    report.append(el('h2', data.ready ? 'Hosting checks passed' : 'Hosting setup needed'));
    if (data.synthetic_driver) report.append(el('p','Synthetic Telegram test fixture: results and login are simulated. Do not enter a real API hash or phone number.'));
    [...data.errors, ...data.warnings].forEach(text => report.append(el('p', text)));
    report.append(el('p', data.runtime + ' · ' + data.worker)); app.append(report);
    if (data.can_create) {
      const form = el('form', null, {class: 'gmt-card'}); form.append(el('h2', 'Add a personal Telegram account'));
      form.append(el('p', 'Get an API ID and API hash from my.telegram.org → API development tools. These belong to your Telegram application. Each connection signs into a separate personal account.'));
      field(form, 'name', 'Connection name', 'text', {maxlength: '120'});
      field(form, 'api_id', 'Telegram API ID', 'number', {min: '1', step: '1'});
      field(form, 'api_hash', 'Telegram API hash', 'password', {autocomplete: 'new-password', pattern: '[a-f0-9]{32}', maxlength: '32'});
      const submit = el('button', 'Save connection', {type: 'submit', class: 'button button-primary'}); submit.disabled = !data.ready; form.append(submit);
      form.addEventListener('submit', event => { event.preventDefault(); const values = new FormData(form); execute(submit, async () => {
        const result = await request('connections', {name: values.get('name'), api_id: Number(values.get('api_id')), api_hash: values.get('api_hash')}); form.reset(); return result;
      }); }); app.append(form);
    }
    data.connections.forEach(connection => {
      const card = el('section', null, {class: 'gmt-card'}); card.append(el('h2', connection.name), el('p', 'Status: ' + connection.state.replaceAll('_', ' ')));
      const action = (name, payload = {}) => request('connections/' + connection.id + '/' + name, payload);
      if (connection.state !== 'connected') {
        const form = el('form'); let step = 'phone';
        if (connection.state === 'waiting_code') step = 'code';
        if (connection.state === 'waiting_password') step = 'password';
        const labels = {phone: 'Phone number (e.g. +573001234567)', code: 'Login code from Telegram', password: 'Telegram two-step verification password'};
        const input = field(form, 'value', labels[step], step === 'password' ? 'password' : 'text', {autocomplete: step === 'code' ? 'one-time-code' : 'off'});
        const submit = el('button', step === 'phone' ? 'Send login code' : 'Continue', {type: 'submit', class: 'button button-primary'}); submit.disabled = !data.ready; form.append(submit);
        form.addEventListener('submit', event => { event.preventDefault(); const value = input.value; input.value = ''; execute(submit, () => action('login', {step, value})); }); card.append(form);
      } else {
        card.append(el('p', connection.verified_at ? 'Personal-account identity verified with a harmless read.' : 'Verify your personal account before publishing.'));
        button(card, 'Verify account (read only)', () => action('verify'));
        if (data.can_create) {
          confirmedButton(card, connection.published ? 'Synchronize GetMCP tools' : 'Publish to GetMCP', 'I understand that gateway membership grants access to my personal Telegram account.', () => action('publish', {acknowledge_delegation:true}));
          if (connection.writes_enabled) button(card, 'Disable write tools', () => action('writes',{enabled:false}));
          else confirmedButton(card, 'Enable write tools', 'I authorize message sending, editing, deletion and account changes. Every write also requires confirm=true.', () => action('writes',{enabled:true,confirm:true}));
        }
      }
      if (connection.endpoint) { card.append(el('p', 'GetMCP endpoint: ' + connection.endpoint)); }
      confirmedButton(card, 'Disconnect account', 'Disable this connection and revoke its Telegram session.', () => action('disconnect')); app.append(card);
    });
    const details = el('details'); details.append(el('summary', 'Included tools (' + data.tools.length + ')'));
    data.tools.forEach(tool => details.append(el('p', tool.name + ' — ' + (tool.write ? 'Write: ' : 'Read: ') + tool.description))); app.append(details);
  }
  load().catch(error => { status.textContent = error.message; app.textContent = 'Could not load Telegram connections.'; });
})();
