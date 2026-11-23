<?php
// Shared widget code editor; textareas remain the authoritative submitted values.
?>
<div class="wm-editor-shell" data-widget-editor>
  <div class="wm-editor-controls">
    <button type="button" title="Find in code" aria-label="Find in code" data-editor-action="search"><svg viewBox="0 0 24 24"><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/></svg></button>
    <div class="wm-editor-actions">
      <button type="button" title="Undo" aria-label="Undo" data-editor-action="undo"><svg viewBox="0 0 24 24"><path d="M9 7 4 12l5 5M4 12h10a6 6 0 0 1 6 6"/></svg></button>
      <button type="button" title="Redo" aria-label="Redo" data-editor-action="redo"><svg viewBox="0 0 24 24"><path d="m15 7 5 5-5 5M20 12H10a6 6 0 0 0-6 6"/></svg></button>
      <button type="button" title="Switch dark/light theme" aria-label="Toggle dark mode" data-editor-action="theme"><svg viewBox="0 0 24 24"><path d="M20 15.5A8 8 0 0 1 8.5 4 8 8 0 1 0 20 15.5Z"/></svg></button>
      <button type="button" title="Copy editor content" aria-label="Copy code" data-editor-action="copy"><svg viewBox="0 0 24 24"><rect x="7" y="7" width="13" height="14" rx="2"/><path d="M4 17H3V3h13v1"/></svg></button>
      <button type="button" title="Toggle fullscreen editor" aria-label="Fullscreen editor" data-editor-action="fullscreen"><svg viewBox="0 0 24 24"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/></svg></button>
      <button type="button" title="Preview widget" aria-label="Preview widget" data-editor-action="preview"><svg viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
    </div>
  </div>
  <div class="wm-editor-find" hidden><input type="search" placeholder="Find text in current tab" aria-label="Search code"><button type="button" data-editor-action="find-next">Next</button><button type="button" data-editor-action="close-search">×</button></div>
  <div class="wm-code-area">
    <?php foreach (['widget_data'=>'html','widget_style'=>'css','widget_javascript'=>'javascript'] as $field => $language): ?>
    <div class="wm-code-layer" data-code-layer="<?= e($field) ?>" <?= $field==='widget_data'?'':'hidden' ?>><div class="wm-line-numbers" aria-hidden="true">1</div><div class="wm-syntax"><pre aria-hidden="true"><code></code></pre><textarea name="<?= e($field) ?>" data-language="<?= e($language) ?>" wrap="off" spellcheck="false" autocomplete="off" autocapitalize="off" aria-label="<?= e(strtoupper($language)) ?> code"><?= e((string)($widget[$field] ?? '')) ?></textarea></div></div>
    <?php endforeach; ?>
  </div>
  <div class="wm-editor-status"><span data-editor-position>Line 1, Column 1</span><span data-editor-language>HTML</span></div>
</div>
<dialog class="wm-preview-dialog"><div class="wm-preview-head"><strong>Widget Preview</strong><button type="button" data-close-preview>Close</button></div><iframe title="Widget preview" sandbox="allow-scripts" referrerpolicy="no-referrer" style="border:0;width:100%;height:65vh;background:#fff"></iframe></dialog>
