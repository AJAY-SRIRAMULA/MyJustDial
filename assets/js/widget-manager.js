(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const dialog = $('#newWidgetDialog');
  const toast = $('#widgetToast');
  const editForm = $('#widgetEditForm');
  let dark = false;
  try { dark = localStorage.getItem('mjd-widget-theme') === 'dark'; } catch (_) {}
  function setTheme() {
    $$('.wm-editor-shell').forEach(editor => editor.classList.toggle('wm-dark', dark));
  }
  setTheme();
  $('[data-theme-toggle]')?.addEventListener('click', () => {
    dark = !dark; setTheme();
    try { localStorage.setItem('mjd-widget-theme', dark ? 'dark' : 'light'); } catch (_) {}
  });
  function notify(message, error = false) {
    if (!toast) return;
    toast.textContent = message; toast.classList.toggle('wm-error', error);
    toast.style.display = 'block'; clearTimeout(notify.timer);
    notify.timer = setTimeout(() => toast.style.display = 'none', 3000);
  }
  const serverNotice = $('.notice-success');
  if (serverNotice && toast) {
    notify(serverNotice.textContent.replace('×','').trim());
    serverNotice.hidden = true;
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  $$('[data-open-widget]').forEach(b => b.addEventListener('click', () => dialog?.showModal()));
  $$('[data-close-widget]').forEach(b => b.addEventListener('click', () => dialog?.close()));
  if (dialog && new URLSearchParams(location.search).has('new')) dialog.showModal();
  async function copyToClipboard(value) {
    if (!value) return false;
    if (navigator.clipboard && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(value);
        return true;
      } catch (_) {
        // Older browsers and restricted clipboard permissions use the fallback.
      }
    }
    const field = document.createElement('textarea');
    field.value = value;
    field.setAttribute('readonly', '');
    field.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0';
    document.body.appendChild(field);
    field.focus();
    field.select();
    let copied = false;
    try { copied = document.execCommand('copy'); } catch (_) {}
    field.remove();
    return copied;
  }
  $$('[data-copy]').forEach(button => button.addEventListener('click', async () => {
    const shortcode = button.dataset.copy || '';
    if (!/^\[widget=[^\]\r\n]+\]$/.test(shortcode)) {
      notify('This widget has no valid shortcode to copy.', true);
      return;
    }
    if (await copyToClipboard(shortcode)) notify('Shortcode copied');
    else notify('Copy failed. Please select the shortcode manually.', true);
  }));
  $('[data-page-size]')?.addEventListener('change', e => {
    const u = new URL(location.href); u.searchParams.set('limit', e.target.value);
    u.searchParams.set('page', '1'); location.assign(u.toString());
  });
  $('[data-table-density]')?.addEventListener('click', () => $('.wm-table')?.classList.toggle('wm-compact'));
  const columnButton = $('[data-columns-toggle]');
  const columnMenu = $('#widgetColumnMenu');
  columnButton?.addEventListener('click', () => {
    if (columnMenu) columnMenu.hidden = !columnMenu.hidden;
  });
  $$('[data-visible-column]').forEach(checkbox => checkbox.addEventListener('change', () => {
    $('.wm-table')?.classList.toggle('wm-hide-' + checkbox.dataset.visibleColumn, !checkbox.checked);
  }));
  document.addEventListener('pointerdown', event => {
    if (columnMenu && !columnMenu.hidden && !columnMenu.contains(event.target) && !columnButton.contains(event.target)) columnMenu.hidden = true;
  });
  const actionMenus = $$('[data-row-actions]');
  function positionActionMenu(details) {
    const trigger = $('summary', details), popup = $('.wm-dropdown', details);
    if (!trigger || !popup || !details.open) return;
    const rect = trigger.getBoundingClientRect();
    const width = popup.offsetWidth || 255;
    const height = Math.min(popup.scrollHeight, window.innerHeight * .75);
    const below = window.innerHeight - rect.bottom;
    const top = below >= Math.min(height, 240) ? rect.bottom + 6 : Math.max(8, rect.top - height - 6);
    popup.style.top = Math.round(top) + 'px';
    popup.style.left = Math.round(Math.min(rect.left, window.innerWidth - width - 10)) + 'px';
  }
  actionMenus.forEach(details => details.addEventListener('toggle', () => {
    if (!details.open) return;
    actionMenus.forEach(other => { if (other !== details) other.open = false; });
    requestAnimationFrame(() => positionActionMenu(details));
  }));
  document.addEventListener('pointerdown', event => {
    actionMenus.forEach(details => { if (details.open && !details.contains(event.target)) details.open = false; });
  });
  // Close every custom details-based dropdown when the user clicks outside it.
  const customDropdowns = $$('.wm-page details');
  customDropdowns.forEach(details => details.addEventListener('toggle', () => {
    if (!details.open) return;
    customDropdowns.forEach(other => {
      if (other !== details && other.open) other.open = false;
    });
  }));
  document.addEventListener('pointerdown', event => {
    customDropdowns.forEach(details => {
      if (details.open && !details.contains(event.target)) details.open = false;
    });
    if (columnMenu && !columnMenu.hidden && !columnMenu.contains(event.target) && !columnButton?.contains(event.target)) columnMenu.hidden = true;
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    customDropdowns.forEach(details => { details.open = false; });
    if (columnMenu) columnMenu.hidden = true;
  });
  // Restore a selected saved revision through the existing CSRF-protected history endpoint.
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-restore-revision]');
    if (!button) return;
    const revisionId = button.dataset.restoreRevision;
    const widgetId = button.dataset.widgetId;
    const csrf = button.dataset.csrf;
    if (!revisionId || !widgetId || !csrf) {
      notify('Revision information is incomplete.', true);
      return;
    }
    if (!confirm('Restore this saved revision? The current widget version will first be saved to history.')) return;
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'history.php';
    [['csrf_token', csrf], ['id', widgetId], ['revision', revisionId]].forEach(([name, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  });
  window.addEventListener('resize', () => actionMenus.forEach(positionActionMenu));
  window.addEventListener('scroll', () => actionMenus.forEach(details => { if (details.open) positionActionMenu(details); }), true);
  $('[data-widget-select-all]')?.addEventListener('change', e => {
    $$('[data-widget-select]').forEach(box => box.checked = e.target.checked);
  });
  const bulkForm = $('#bulkWidgetForm');
  const selectAll = $('[data-widget-select-all]');
  const rowChecks = $$('[data-widget-select]');
  function refreshBulkState() {
    const selected = rowChecks.filter(box => box.checked).length;
    if (bulkForm) bulkForm.hidden = selected === 0;
    const count = $('[data-selected-count]');
    if (count) count.textContent = selected + (selected === 1 ? ' selected' : ' selected');
    if (selectAll) {
      selectAll.checked = rowChecks.length > 0 && selected === rowChecks.length;
      selectAll.indeterminate = selected > 0 && selected < rowChecks.length;
    }
  }
  rowChecks.forEach(box => box.addEventListener('change', refreshBulkState));
  selectAll?.addEventListener('change', e => {
    rowChecks.forEach(box => { box.checked = e.target.checked; });
    refreshBulkState();
  });
  refreshBulkState();
  bulkForm?.addEventListener('submit', e => {
    if (!rowChecks.some(box => box.checked)) { e.preventDefault(); notify('Select at least one widget.', true); return; }
    if (!$('[name=bulk_action]', e.currentTarget).value) { e.preventDefault(); notify('Choose a bulk action.', true); return; }
    if ($('[name=bulk_action]', e.currentTarget).value === 'delete' && !confirm('Delete the selected widgets?')) e.preventDefault();
  });
  $('[data-export-widget]') && $$('[data-export-widget]').forEach(button => button.addEventListener('click', () => { location.href = 'export.php?id=' + encodeURIComponent(button.dataset.exportWidget); }));
  $$('[data-history-widget]').forEach(button => button.addEventListener('click', () => { location.href = 'history.php?id=' + encodeURIComponent(button.dataset.historyWidget); }));
  const filterForm = $('#widgetFilters');
  filterForm?.querySelector('[name="status"]')?.addEventListener('change', () => filterForm.requestSubmit());
  filterForm?.querySelector('[name="sort"]')?.addEventListener('change', () => filterForm.requestSubmit());
  filterForm?.querySelector('[name="q"]')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); filterForm.requestSubmit(); } });
  const dateRange = $('[data-date-range]');
  if (dateRange) {
    const from = dateRange.querySelector('[name="date"]');
    const to = dateRange.querySelector('[name="date_to"]');
    const label = $('[data-date-label]', dateRange);
    function updateDateLabel() {
      if (!label) return;
      label.textContent = from?.value || to?.value ? [from?.value, to?.value].filter(Boolean).join(' – ') : 'Date range';
    }
    from?.addEventListener('change', updateDateLabel);
    to?.addEventListener('change', updateDateLabel);
    $('[data-clear-dates]', dateRange)?.addEventListener('click', () => {
      if (from) from.value = '';
      if (to) to.value = '';
      updateDateLabel();
    });
    document.addEventListener('pointerdown', event => {
      if (dateRange.open && !dateRange.contains(event.target)) dateRange.open = false;
    });
  }
  $('[data-rename-widget]')?.addEventListener('click', () => $('[name=widget_name]', editForm)?.focus());
  $('[data-bd-butler]')?.addEventListener('click', () => notify('BD Butler is displayed for layout matching, but its external service integration is not configured in this project.', true));

  $$('.wm-editor-shell').forEach(shell => {
    const form = shell.closest('form');
    const layers = $$('.wm-code-layer', shell);
    const areas = $$('textarea', shell);
    const findBar = $('.wm-editor-find', shell);
    const findInput = $('input', findBar);
    const preview = $('.wm-preview-dialog', form);
    const undo = new Map(), redo = new Map();
    let active = areas[0], lastMatch = -1;
    function highlight(value, language) {
      let result = esc(value);
      if (language === 'html') result = result.replace(/(&lt;\/?)([\w-]+)([^&]*?)(&gt;)/g,
        '<span class="tok-punct">$1</span><span class="tok-tag">$2</span><span class="tok-attr">$3</span><span class="tok-punct">$4</span>');
      if (language === 'css') result = result.replace(/(#[0-9a-fA-F]{3,8})\b/g, '<span class="tok-value">$1</span>')
        .replace(/([a-zA-Z-]+)(\s*:)/g, '<span class="tok-prop">$1</span>$2');
      if (language === 'javascript') result = result.replace(/\b(const|let|var|function|return|if|else|new|class|async|await|true|false|null)\b/g,
        '<span class="tok-keyword">$1</span>');
      return result + '\n';
    }
    function draw(area) {
      const layer = area.closest('.wm-code-layer');
      $('pre code', layer).innerHTML = highlight(area.value, area.dataset.language);
      $('.wm-line-numbers', layer).textContent = Array.from({length: area.value.split('\n').length}, (_,i) => i+1).join('\n');
      const lines = area.value.slice(0, area.selectionStart).split('\n');
      $('[data-editor-position]', shell).textContent = 'Line ' + lines.length + ', Column ' + (lines[lines.length-1].length+1);
      $('[data-editor-language]', shell).textContent = area.dataset.language.toUpperCase();
      $('.wm-syntax pre', layer).scrollTop = area.scrollTop;
      $('.wm-syntax pre', layer).scrollLeft = area.scrollLeft;
      $('.wm-line-numbers', layer).scrollTop = area.scrollTop;
    }
    const originalCode = new Map(areas.map(area => [area.name, area.value]));
    const dirtyCode = new Set();
    function updateCodeDirty(area, announce = false) {
      const wasDirty = dirtyCode.has(area.name);
      const isDirty = area.value !== originalCode.get(area.name);
      if (isDirty) dirtyCode.add(area.name); else dirtyCode.delete(area.name);
      const tab = $('.wm-tabs [data-code-tab="' + area.name + '"]', form);
      if (tab) {
        tab.classList.toggle('wm-tab-dirty', isDirty);
        tab.setAttribute('data-dirty', isDirty ? 'true' : 'false');
        tab.setAttribute('aria-label', (tab.textContent.trim()) + (isDirty ? ' (unsaved changes)' : ''));
      }
      if (announce && isDirty && !wasDirty) notify(area.dataset.language.toUpperCase() + ' has unsaved changes');
      refreshUnsavedBar();
    }
    function show(field) {
      layers.forEach(layer => layer.hidden = layer.dataset.codeLayer !== field);
      active = $('textarea', layers.find(layer => layer.dataset.codeLayer === field));
      $$('.wm-tabs [data-code-tab]', form).forEach(tab => tab.classList.toggle('active', tab.dataset.codeTab === field));
      const hints = {widget_data:'html', widget_style:'css', widget_javascript:'js'};
      $$('[data-hint-html],[data-hint-css],[data-hint-js]', form).forEach(h => h.hidden = !h.hasAttribute('data-hint-' + hints[field]));
      draw(active);
    }
    function record(area) {
      const stack = undo.get(area);
      if (stack[stack.length-1] !== area.value) { stack.push(area.value); if (stack.length > 150) stack.shift(); }
      redo.set(area, []);
    }
    function history(action) {
      const past = undo.get(active), future = redo.get(active);
      if (action === 'undo' && past.length > 1) { future.push(past.pop()); active.value = past[past.length-1]; }
      else if (action === 'redo' && future.length) { const value = future.pop(); past.push(value); active.value = value; }
      else return;
      draw(active); active.dispatchEvent(new Event('change', {bubbles:true}));
    }
    areas.forEach(area => {
      undo.set(area, [area.value]); redo.set(area, []);
      area.addEventListener('input', () => { record(area); draw(area); updateCodeDirty(area, true); });
      area.addEventListener('change', () => updateCodeDirty(area, true));
      area.addEventListener('scroll', () => draw(area));
      area.addEventListener('click', () => draw(area));
      area.addEventListener('keyup', () => draw(area));
      area.addEventListener('keydown', e => {
        if (e.key === 'Tab') {
          e.preventDefault(); area.setRangeText('  ', area.selectionStart, area.selectionEnd, 'end');
          area.dispatchEvent(new Event('input', {bubbles:true}));
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
          e.preventDefault(); findBar.hidden = false; findInput.focus();
        }
        if ((e.ctrlKey || e.metaKey) && ['z','y'].includes(e.key.toLowerCase())) {
          e.preventDefault(); history(e.key.toLowerCase() === 'z' && !e.shiftKey ? 'undo' : 'redo');
        }
      });
      draw(area);
    });
    $$('.wm-tabs [data-code-tab]', form).forEach(tab => tab.addEventListener('click', () => show(tab.dataset.codeTab)));
    function findNext() {
      if (!findInput.value) return;
      const search = findInput.value.toLowerCase(), text = active.value.toLowerCase();
      let at = text.indexOf(search, lastMatch < 0 ? active.selectionEnd : lastMatch + 1);
      if (at < 0) at = text.indexOf(search);
      if (at < 0) { notify('Text not found', true); return; }
      lastMatch = at; active.focus(); active.setSelectionRange(at, at + search.length); draw(active);
    }
    findInput.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); findNext(); }
      if (e.key === 'Escape') findBar.hidden = true;
    });
    $$('[data-editor-action]', shell).forEach(button => button.addEventListener('click', async () => {
      const action = button.dataset.editorAction;
      if (action === 'theme') { dark = !dark; setTheme(); try { localStorage.setItem('mjd-widget-theme', dark ? 'dark' : 'light'); } catch (_) {} }
      if (action === 'search') { findBar.hidden = false; findInput.focus(); }
      if (action === 'find-next') findNext();
      if (action === 'close-search') { findBar.hidden = true; lastMatch = -1; active.focus(); }
      if (action === 'undo' || action === 'redo') history(action);
      if (action === 'fullscreen') shell.classList.toggle('wm-fullscreen');
      if (action === 'copy') {
        try { if (!await copyToClipboard(active.value)) throw new Error('Clipboard unavailable'); notify('Code copied'); }
        catch (_) { active.focus(); active.select(); notify('Select and copy the highlighted code.'); }
      }
      if (action === 'preview' && preview) {
        const previewButton = button;
        const payload = new FormData(form);
        payload.set('csrf_token', $('[name=csrf_token]', form)?.value || '');
        try {
          previewButton.disabled = true;
          const response = await fetch('preview.php', { method: 'POST', body: payload, credentials: 'same-origin' });
          const result = await response.json();
          if (!response.ok || !result.ok) throw new Error(result.message || 'Preview could not be rendered.');
          if (preview.open) preview.close();
          const oldFrame = $('iframe', preview);
          const frame = oldFrame.cloneNode(false);
          frame.removeAttribute('src');
          frame.srcdoc = result.document;
          oldFrame.replaceWith(frame);
          preview.showModal();
        } catch (error) {
          notify(error.message || 'Preview could not be rendered.', true);
        } finally {
          previewButton.disabled = false;
        }
      }
    }));
    $('[data-close-preview]', preview)?.addEventListener('click', () => { if (preview.open) preview.close(); });
    show('widget_data');
  });
  if (editForm) {
    const initialValues = new Map(Array.from(new FormData(editForm).entries()).map(([key, value]) => [key, String(value)]));
    let submitting = false;
    let unsavedBar = $('#wmUnsavedBar');
    if (!unsavedBar) {
      unsavedBar = document.createElement('div');
      unsavedBar.id = 'wmUnsavedBar';
      unsavedBar.className = 'wm-unsaved-bar';
      unsavedBar.setAttribute('role', 'status');
      unsavedBar.innerHTML = '<span class="wm-unsaved-indicator"></span><strong>Unsaved changes</strong><div class="wm-unsaved-actions"><button type="button" data-discard-widget>Discard</button><button type="button" data-save-widget>Save</button></div>';
      document.body.appendChild(unsavedBar);
    }
    let discardDialog = $('#wmDiscardDialog');
    if (!discardDialog) {
      discardDialog = document.createElement('div');
      discardDialog.id = 'wmDiscardDialog';
      discardDialog.className = 'wm-discard-overlay';
      discardDialog.hidden = true;
      discardDialog.innerHTML = '<section class="wm-discard-dialog" role="dialog" aria-modal="true" aria-labelledby="wmDiscardTitle" aria-describedby="wmDiscardDescription"><div class="wm-discard-icon" aria-hidden="true">!</div><h2 id="wmDiscardTitle">Discard Unsaved Changes</h2><p id="wmDiscardDescription">Every change since the last save will be lost.</p><div class="wm-discard-actions"><button type="button" data-keep-editing>Keep Editing</button><button type="button" data-confirm-discard>Discard Changes</button></div></section>';
      document.body.appendChild(discardDialog);
    }
    function changed() {
      return Array.from(new FormData(editForm).entries()).some(([key, value]) => String(value) !== initialValues.get(key));
    }
    function refreshUnsavedBar() {
      unsavedBar.classList.toggle('is-visible', changed());
    }
    function openDiscardDialog() {
      if (!changed()) return;
      discardDialog.hidden = false;
      document.body.classList.add('wm-discard-open');
      $('[data-keep-editing]', discardDialog)?.focus();
    }
    function closeDiscardDialog() {
      discardDialog.hidden = true;
      document.body.classList.remove('wm-discard-open');
      $('[data-discard-widget]', unsavedBar)?.focus();
    }
    function discardChanges() {
      editForm.querySelectorAll('textarea,input,select').forEach(field => {
        if (!initialValues.has(field.name)) return;
        field.value = initialValues.get(field.name);
        field.dispatchEvent(new Event('input', {bubbles:true}));
        field.dispatchEvent(new Event('change', {bubbles:true}));
      });
      editForm.querySelectorAll('.wm-tabs [data-code-tab]').forEach(tab => {
        tab.classList.remove('wm-tab-dirty');
        tab.dataset.dirty = 'false';
        tab.setAttribute('aria-label', tab.textContent.trim());
      });
      refreshUnsavedBar();
      closeDiscardDialog();
      notify('Unsaved changes discarded');
    }
    function submitChanges() {
      if (!changed() || !editForm.reportValidity()) return;
      submitting = true;
      const barSave = $('[data-save-widget]', unsavedBar);
      if (barSave) barSave.disabled = true;
      editForm.submit();
    }
    $('[data-discard-widget]', unsavedBar)?.addEventListener('click', openDiscardDialog);
    $('[data-save-widget]', unsavedBar)?.addEventListener('click', submitChanges);
    $('[data-keep-editing]', discardDialog)?.addEventListener('click', closeDiscardDialog);
    $('[data-confirm-discard]', discardDialog)?.addEventListener('click', discardChanges);
    discardDialog.addEventListener('click', event => {
      if (event.target === discardDialog) closeDiscardDialog();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !discardDialog.hidden) closeDiscardDialog();
    });
    editForm.addEventListener('input', refreshUnsavedBar);
    editForm.addEventListener('change', refreshUnsavedBar);
    refreshUnsavedBar();
    window.addEventListener('beforeunload', e => {
      if (changed() && !submitting) { e.preventDefault(); e.returnValue = ''; }
    });
  }})();
