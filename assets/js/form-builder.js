(function () {
  'use strict';

  var list = document.getElementById('fbList');
  if (!list && !document.getElementById('fbPreview')) {
    return;
  }

  var preview = document.getElementById('fbPreview');
  var saveBtn = document.getElementById('fbSave');
  var searchInput = document.getElementById('fbSearch');
  var nicknameInput = document.getElementById('formNickname');
  var variableInput = document.getElementById('formVariable');
  var titleInput = document.getElementById('formTitle');
  var descInput = document.getElementById('formDescription');
  var formName = document.getElementById('fbFormName');
  var shortcodeEl = document.getElementById('shortcodePreview');
  var settingsCard = document.getElementById('formSettingsCard');
  var toast = document.getElementById('fbToast');
  var groups = window.MJD_FIELD_GROUPS || {};
  var lists = window.MJD_FIELD_DATA || {};

  var typeLabels = {};
  Object.keys(groups).forEach(function (groupName) {
    Object.keys(groups[groupName]).forEach(function (label) {
      typeLabels[groups[groupName][label]] = label;
    });
  });

  var CHOICE_TYPES = ['select', 'radio', 'checkbox_group'];
  var LIST_TYPES = ['country', 'state', 'top_category', 'years'];
  var TEXT_BLOCK_TYPES = ['html', 'help_alert'];
  var NON_INPUT = ['section', 'html', 'help_alert', 'honeypot', 'captcha', 'submit', 'hidden'];

  var nextId = 1;
  var fields = [];
  var dirty = false;
  var saving = false;
  var toastTimer = null;

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
    });
  }

  function sanitizeCustomHtml(value) {
    var allowedTags = [
      'A', 'B', 'BLOCKQUOTE', 'BR', 'CODE', 'DIV', 'EM',
      'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HR', 'I',
      'LI', 'OL', 'P', 'PRE', 'S', 'SMALL', 'SPAN',
      'STRONG', 'U', 'UL'
    ];
    var allowedAttributes = ['class', 'href', 'rel', 'target', 'title', 'style'];
    function sanitizeStyle(styleText) {
      var allowedProperties = [
        'color', 'background-color', 'font-size', 'font-weight',
        'font-style', 'text-align', 'text-decoration', 'border',
        'border-color', 'border-width', 'border-style', 'border-radius',
        'padding', 'padding-top', 'padding-right', 'padding-bottom',
        'padding-left', 'margin', 'margin-top', 'margin-right',
        'margin-bottom', 'margin-left', 'display'
      ];
      var safeStyles = [];

      String(styleText || '').split(';').forEach(function (declaration) {
        var parts = declaration.split(':');
        var property = (parts.shift() || '').trim().toLowerCase();
        var value = parts.join(':').trim();

        if (
          allowedProperties.indexOf(property) !== -1
          && value
          && !/[<>\\\\{}]/.test(value)
          && !/(url|expression|javascript|@import|behavior)/i.test(value)
        ) {
          safeStyles.push(property + ': ' + value);
        }
      });

      return safeStyles.join('; ');
    }

    var parser = new DOMParser();
    var documentHtml = parser.parseFromString(
      String(value || ''),
      'text/html'
    );

    Array.from(documentHtml.body.querySelectorAll('*')).forEach(function (element) {
      if (allowedTags.indexOf(element.tagName) === -1) {
        element.replaceWith(documentHtml.createTextNode(element.textContent));
        return;
      }

      Array.from(element.attributes).forEach(function (attribute) {
        if (allowedAttributes.indexOf(attribute.name.toLowerCase()) === -1) {
          element.removeAttribute(attribute.name);
        }
      });

      if (element.hasAttribute('style')) {
        var safeStyle = sanitizeStyle(element.getAttribute('style'));

        if (safeStyle) {
          element.setAttribute('style', safeStyle);
        } else {
          element.removeAttribute('style');
        }
      }

      if (element.tagName === 'A') {
        var href = element.getAttribute('href') || '';
        var safeHref = /^(https?:|mailto:|tel:|\/|#)/i.test(href)
          && !/^\/\//.test(href);

        if (!safeHref) {
          element.removeAttribute('href');
        }

        if (element.getAttribute('target') === '_blank') {
          element.setAttribute('rel', 'noopener noreferrer');
        }
      }
    });

    return documentHtml.body.innerHTML;
  }

  function makeId() {
    return 'new_' + (nextId++);
  }

  function toVariable(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .replace(/_+/g, '_');
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function byId(id) {
    for (var i = 0; i < fields.length; i++) {
      if (fields[i].id === id) {
        return fields[i];
      }
    }

    return null;
  }

  function indexOfId(id) {
    for (var i = 0; i < fields.length; i++) {
      if (fields[i].id === id) {
        return i;
      }
    }

    return -1;
  }
  function setDirty(isDirty) {
    dirty = isDirty;
    saveBtn.classList.toggle('is-dirty', isDirty);
  }
  function showToast(message, isError) {
    toast.textContent = message;
    toast.classList.toggle('is-error', !!isError);
    toast.classList.add('is-visible');

    clearTimeout(toastTimer);

    var displayTime = isError ? 5000 : 2600;

    toastTimer = setTimeout(function () {
      toast.classList.remove('is-visible');
    }, displayTime);
  }

  function defaultSettings() {
    return {
      inputView: true,
      displayView: true,
      leadPreviews: false,
      includeEmails: true,
      tableView: true,
      adminOnly: false,
      altInput: '',
      altDisplay: '',
      altEmail: '',
      altTable: ''
    };
  }
  function newField(type, label, name) {
    return {
      id: makeId(), type: type || 'text', origType: '', label: label == null ? 'New Field' : label,
      name: name == null ? toVariable(label || 'new_field') : name, nameTouched: name != null,
      required: false, placeholder: '', help: '', description: '', options: '', defaultValue: '',
      inputClass: NON_INPUT.indexOf(type) >= 0 && type !== 'hidden' ? '' : 'form-control', displayClass: '', outerId: '',
      settings: defaultSettings(), open: false
    };
  }
  function normalize(field) {
    field.settings = Object.assign(
      defaultSettings(),
      field.settings || {}
    );
    field.nameTouched = true;
    field.open = !!field.open;

    return field;
  }
  function optionsOf(f) {
    var options = String(f.options || '').split(/\r\n|\r|\n/).map(function (option) {
      return option.trim();
    }).filter(Boolean);

    if (options.length) {
      return options;
    }
    if (f.type === 'country') {
      return lists.countries || [];
    }

    if (f.type === 'state') {
      return lists.states || [];
    }

    if (f.type === 'top_category') {
      return lists.categories || [];
    }
    if (f.type === 'years') {
      var year = new Date().getFullYear();
      return Array.from({ length: 30 }, function (_, i) {
        return String(year - i);
      });
    }

    return [];
  }

  function typeSelect(f) {
    var known = false;
    var html = Object.keys(groups).map(function (g) {
      return '<optgroup label="' + esc(g) + '">' + Object.keys(groups[g]).map(function (label) {
        var v = groups[g][label];
        if (v === f.type) {
          known = true;
        }
        return '<option value="' + esc(v) + '"' + (v === f.type ? ' selected' : '') + '>' + esc(label) + '</option>';
      }).join('') + '</optgroup>';
    }).join('');
    if (!known) {
      html = '<option value="' + esc(f.type) + '" selected>'
        + esc(f.type)
        + '</option>'
        + html;
    }
    return '<select class="fb-ctl" data-p="type" aria-label="Type of Field">' + html + '</select>';
  }
  function toggle(prop, checked, isSetting, label) {
    return '<label class="fb-toggle"><input type="checkbox" ' + (isSetting ? 'data-s="' + prop + '"' : 'data-p="' + prop + '"') +
      (checked ? ' checked' : '') + ' aria-label="' + esc(label) + '"><span class="fb-track"><span class="fb-knob"></span></span></label>';
  }
  function dsItem(label, prop, altProp, f) {
    var s = f.settings;
    return '<div class="fb-ds-item"><label class="fb-lbl">' + label + '</label>' + toggle(prop, !!s[prop], true, label) +
      (altProp ? '<textarea class="fb-ctl" rows="1" data-s="' + altProp + '" placeholder="Alt. Field" aria-label="' + esc(label) + ' alternate field">' + esc(s[altProp]) + '</textarea>' : '') + '</div>';
  }
  function cardHtml(f) {
    var s = f.settings;
    var extra = '';
    if (CHOICE_TYPES.indexOf(f.type) >= 0 || LIST_TYPES.indexOf(f.type) >= 0) {
      extra += '<div class="fb-adv-grid" style="margin-top:14px"><div class="fb-adv-full"><label class="fb-lbl">Field Options (one per line)</label>' +
        '<textarea class="fb-ctl" rows="3" style="overflow:auto" data-p="options" placeholder="Enter one option per line">' + esc(f.options) + '</textarea>' +
        '<button type="button" class="fb-addfield" data-act="add-option" style="margin-top:8px">Add Option +</button>' +
        (LIST_TYPES.indexOf(f.type) >= 0 ? '<div class="fb-muted">Leave blank to use the list stored in the database.</div>' : '') + '</div></div>';
    }
    if (TEXT_BLOCK_TYPES.indexOf(f.type) >= 0) {
      extra += '<div class="fb-adv-grid" style="margin-top:14px"><div class="fb-adv-full"><label class="fb-lbl">' + (f.type === 'html' ? 'Custom HTML' : 'Alert Text') + '</label>' +
        '<textarea class="fb-ctl" rows="3" style="overflow:auto" data-p="description">' + esc(f.description) + '</textarea></div></div>';
    }
    return '<div class="fb-strip" tabindex="0" role="button" title="Drag to reorder (or use the Up/Down arrow keys)" aria-label="Reorder field"><i class="fa fa-bars"></i></div>' +
      '<div class="fb-body">' +
      '<div class="fb-row fb-row-primary">' +
        '<div class="fb-col"><label>Type of Field</label>' + typeSelect(f) + '</div>' +
        '<div class="fb-col"><label>Required Field?</label>' + toggle('required', f.required, false, 'Required Field?') + '</div>' +
        '<div class="fb-tools">' +
          '<button type="button" class="fb-gear' + (f.open ? ' is-open' : '') + '" data-act="gear" title="Field settings" aria-label="Field settings" aria-expanded="' + (f.open ? 'true' : 'false') + '"><i class="fa fa-cog"></i></button>' +
          '<div class="fb-menu-wrap"><button type="button" class="fb-menu-btn" data-act="menu" aria-haspopup="true">Actions</button>' +
            '<div class="fb-menu"><button type="button" data-act="dup"><i class="fa fa-clone"></i> Duplicate</button><button type="button" data-act="del" class="is-danger"><i class="fa fa-trash-o"></i> Delete</button></div></div>' +
        '</div>' +
      '</div>' +
      '<div class="fb-row fb-row-secondary">' +
        '<div class="fb-col"><label>Field Label</label><textarea class="fb-ctl" rows="1" data-p="label" placeholder="Field label">' + esc(f.label) + '</textarea></div>' +
        '<div class="fb-col"><label>Database Variable</label><input class="fb-ctl" data-p="name" value="' + esc(f.name) + '" placeholder="database_variable"></div>' +
        '<div class="fb-tools-bottom"><button type="button" class="fb-addfield" data-act="add">Add Field +</button></div>' +
      '</div>' +
      '<div class="fb-adv' + (f.open ? ' is-open' : '') + '">' +
        '<div class="fb-adv-grid">' +
          '<div><label class="fb-lbl">Placeholder Text</label><textarea class="fb-ctl" rows="1" data-p="placeholder" placeholder="Placeholder Text">' + esc(f.placeholder) + '</textarea></div>' +
          '<div><label class="fb-lbl">Field Help Text</label><textarea class="fb-ctl" rows="1" data-p="help" placeholder="Field Instructions">' + esc(f.help) + '</textarea></div>' +
          '<div><label class="fb-lbl">Default Field Value</label><textarea class="fb-ctl" rows="1" data-p="defaultValue" placeholder="Default Value">' + esc(f.defaultValue) + '</textarea></div>' +
        '</div>' +
        '<div class="fb-adv-grid">' +
          '<div><label class="fb-lbl">Addtl. Field CSS Class</label><input class="fb-ctl" data-p="inputClass" value="' + esc(f.inputClass) + '" placeholder="form-control"></div>' +
          '<div><label class="fb-lbl">Outer Element ID</label><input class="fb-ctl" data-p="outerId" maxlength="50" value="' + esc(f.outerId) + '" placeholder="Enter Outer Element ID"></div>' +
        '</div>' + extra +
        '<hr>' +
        '<div class="fb-ds-head"><h4>Display Settings</h4><a href="https://support.brilliantdirectories.com/" target="_blank" rel="noopener">Learn More <i class="fa fa-external-link"></i></a></div>' +
        '<div class="fb-ds">' +
          dsItem('Input View', 'inputView', 'altInput', f) + dsItem('Display View', 'displayView', 'altDisplay', f) +
          dsItem('Lead Previews', 'leadPreviews', null, f) + dsItem('Include In Emails', 'includeEmails', 'altEmail', f) +
          dsItem('Table View', 'tableView', 'altTable', f) +
        '</div>' +
        '<div class="fb-admin"><input type="checkbox" id="adm_' + esc(f.id) + '" data-s="adminOnly"' + (s.adminOnly ? ' checked' : '') + '><label for="adm_' + esc(f.id) + '">Admin Only</label></div>' +
        '<i class="fa fa-question-circle fb-help-icon" title="Only administrators will see this field."></i>' +
      '</div>' +
      '</div>';
  }
  function makeCard(f) {
    var el = document.createElement('div');
    el.className = 'fb-card';
    el.setAttribute('data-id', f.id);
    el.innerHTML = cardHtml(f);
    return el;
  }
  function renderList(keepScroll) {
    var top = list.scrollTop;
    list.innerHTML = '';
    if (!fields.length) {
      list.innerHTML = '<div class="fb-empty"><strong>No fields yet</strong>Add your first field to start building this form.<br><br><button type="button" class="fb-addfield" data-act="add-first">Add Field +</button></div>';
      return;
    }
    fields.forEach(function (field) {
      list.appendChild(makeCard(field));
    });
    applyFilter();
    if (keepScroll) {
      list.scrollTop = top;
    }
  }
  function replaceCard(f) {
    var old = list.querySelector('.fb-card[data-id="' + cssEsc(f.id) + '"]');
    if (!old) {
      return;
    }
    var fresh = makeCard(f);
    old.parentNode.replaceChild(fresh, old);
    applyFilter();
    return fresh;
  }
  function cssEsc(value) {
    return String(value).replace(/(["\\])/g, '\\$1');
  }

  function cardEl(id) {
    return list.querySelector('.fb-card[data-id="' + cssEsc(id) + '"]');
  }

  function applyFilter() {
    var q = (searchInput.value || '').trim().toLowerCase();
    Array.prototype.forEach.call(list.querySelectorAll('.fb-card'), function (card) {
      var f = byId(card.getAttribute('data-id'));
      if (!f) {
        return;
      }
      var hay = (f.label + ' ' + f.name + ' ' + (typeLabels[f.type] || f.type) + ' ' + f.placeholder + ' ' + f.help).toLowerCase();
      card.classList.toggle('is-hidden', q !== '' && hay.indexOf(q) < 0);
    });
  }

  function pvControl(f) {
    var t = f.type, ph = esc(f.placeholder), val = esc(f.defaultValue), opts;
    if (t === 'select' || LIST_TYPES.indexOf(t) >= 0) {
      opts = optionsOf(f);
      var defaultIsOption = opts.indexOf(f.defaultValue) >= 0;
      var selectPlaceholder = ph || (!defaultIsOption && val ? val : 'Select an option');
      return '<select class="pv-control" name="' + esc(f.name) + '"><option value="">' +
        esc(selectPlaceholder) + '</option>' + opts.map(function (option) {
          return '<option value="' + esc(option) + '"' +
            (option === f.defaultValue ? ' selected' : '') + '>' + esc(option) + '</option>';
        }).join('') + '</select>';
    }
    if (t === 'radio' || t === 'checkbox_group' || t === 'yes_no') {
      opts = t === 'yes_no' ? ['Yes', 'No'] : optionsOf(f);
      var choiceType = t === 'checkbox_group' ? 'checkbox' : 'radio';
      var choiceName = t === 'checkbox_group' ? esc(f.name) + '[]' : esc(f.name);
      return '<div class="pv-choices">' + opts.map(function (option) {
        return '<label><input type="' + choiceType + '" name="' + choiceName +
          '" value="' + esc(option) + '"' + (option === f.defaultValue ? ' checked' : '') +
          '> ' + esc(option) + '</label>';
      }).join('') + '</div>';
    }
    if (t === 'phone_country') {
      return '<div class="pv-phone"><select class="pv-control" disabled><option>+91</option></select><input class="pv-control" type="tel" disabled placeholder="' + ph + '" value="' + val + '"></div>';
    }
    if (t === 'textarea' || t.indexOf('richtext') === 0) {
      return '<textarea class="pv-control" disabled placeholder="' + ph + '">' + val + '</textarea>';
    }
    var map = { email: 'email', tel: 'tel', number: 'number', password: 'password', url: 'url', date: 'date', 'datetime-local': 'datetime-local', file: 'file', price: 'text' };
    var it = map[t] || 'text';
    return '<input class="pv-control" type="' + it + '" disabled placeholder="' + ph + '"' + (it === 'file' ? '' : ' value="' + val + '"') + '>';
  }
  function pvField(f) {
    var s = f.settings || {};
    var t = f.type;
    if (s.inputView === false && t !== 'html') {
      return '';
    }
    if (t === 'section') {
      return '<div class="pv-section"><h2>' + esc(f.label) + '</h2></div>';
    }
    if (t === 'honeypot' || t === 'hidden') {
      return '';
    }
    if (t === 'submit') {
      return '<div class="pv-field"><button type="button" class="pv-submit" disabled>'
        + esc(f.label || 'Submit')
        + '</button></div>';
    }
    if (t === 'captcha') {
      return '<div class="pv-field"><div class="pv-note">'
        + '<input type="checkbox" aria-label="CAPTCHA preview"> I\u2019m not a robot (preview only)'
        + '</div></div>';
    }
    if (t === 'html') {
      var customHtml = sanitizeCustomHtml(f.description);

      if (!customHtml.trim()) {
        return '';
      }

      return '<div class="pv-field pv-html">' + customHtml + '</div>';
    }
    if (t === 'help_alert') {
      return '<div class="pv-field"><div class="pv-alert">'
        + esc(f.description || f.help || f.label)
        + '</div></div>';
    }
    var label = '<label class="pv-label">' + (f.required ? '<span class="pv-star">*</span>' : '') + esc(f.label) + (s.adminOnly ? ' <small style="font-weight:400;color:#6c7884">(Admin only)</small>' : '') + '</label>';
    var help = f.help ? '<small class="pv-help">' + esc(f.help) + '</small>' : '';
    return '<div class="pv-field">' + label + pvControl(f) + help + '</div>';
  }
  function mountPreviewMarkup(target, markup) {
    var frame = document.createElement('iframe');
    frame.title = 'Interactive widget preview';
    frame.setAttribute('sandbox', 'allow-scripts');
    frame.style.cssText = 'width:100%;height:420px;border:0;background:transparent';
    target.replaceChildren(frame);
    frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;font:inherit;font-family:Arial,sans-serif}*{box-sizing:border-box}input,select,textarea{max-width:100%}</style></head><body>' + markup + '</body></html>';
  }

  var previewGeneration = 0;
  function renderPreview() {
    var generation = ++previewGeneration;
    var html = fields.map(function (field) {
      if (field.type === 'html' && /\[widget=[^\]\r\n]+\]/.test(String(field.label || '') + ' ' + String(field.description || ''))) {
        return '<div class="pv-field pv-html" data-widget-field="' + esc(field.id) + '">Loading widget preview...</div>';
      }
      if (field.type === 'html' && /\.php$/i.test(String(field.label || '').trim())) {
        return '<div class="pv-field pv-html" data-component-field="' + esc(field.id) + '">Loading PHP component preview...</div>';
      }
      return pvField(field);
    }).join('');
    preview.innerHTML = html || '<div class="pv-empty"><strong>Your form preview will appear here</strong>Add fields on the left and press Save Changes.</div>';
    fields.forEach(function(field){
      var content=String(field.label||'')+' '+String(field.description||'');
      if(field.type!=='html'||!/\[widget=[^\]\r\n]+\]/.test(content))return;
      var data=new FormData();data.append('widget_content',content);data.append('csrf_token',String(window.MJD_CSRF_TOKEN||''));
      fetch(window.MJD_COMPONENT_PREVIEW_URL,{method:'POST',body:data,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(result){
        var target=preview.querySelector('[data-widget-field="'+cssEsc(field.id)+'"]');
        if(generation !== previewGeneration) return;
        if(target&&result.ok)mountPreviewMarkup(target,result.html);
        else if(target)target.textContent='Widget preview is unavailable.';
      }).catch(function(){if(generation !== previewGeneration) return;var target=preview.querySelector('[data-widget-field="'+cssEsc(field.id)+'"]');if(target)target.textContent='Widget preview could not load.'});
    });
    fields.forEach(function (field) {
      var filename = String(field.label || '').trim();
      if (field.type !== 'html' || !/\.php$/i.test(filename)) return;
      var target = preview.querySelector('[data-component-field="' + cssEsc(field.id) + '"]');
      if (!target || !window.MJD_COMPONENT_PREVIEW_URL) return;
      var data = new FormData();
      data.append('filename', filename);
      data.append('form_id', String(window.MJD_FORM_ID || 0));
      data.append('csrf_token', String(window.MJD_CSRF_TOKEN || ''));
      fetch(window.MJD_COMPONENT_PREVIEW_URL, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          var current = preview.querySelector('[data-component-field="' + cssEsc(field.id) + '"]');
          var latest = byId(field.id);
          if (!current || !latest || String(latest.label || '').trim() !== filename) return;
          if (generation !== previewGeneration) return;
          mountPreviewMarkup(current, result.html || '<div class="custom-component-error">Component preview is unavailable.</div>');
        })
        .catch(function () {
          var current = preview.querySelector('[data-component-field="' + cssEsc(field.id) + '"]');
          if (generation === previewGeneration && current) current.textContent = 'The PHP component preview could not be loaded.';
        });
    });
  }

  if (!list) {
    fields = (window.MJD_INITIAL_FIELDS || []).map(normalize);
    renderPreview();
    return;
  }

  list.addEventListener('input', function (event) {
    onEdit(event, false);
  });

  list.addEventListener('change', function (event) {
    onEdit(event, true);
  });

  function onEdit(e, isChange) {
    var el = e.target;
    var card = el.closest('.fb-card');
    if (!card) {
      return;
    }
    var f = byId(card.getAttribute('data-id'));
    if (!f) {
      return;
    }
    var p = el.getAttribute('data-p'), s = el.getAttribute('data-s');
    if (p === 'type') {
      if (!isChange) {
        return;
      }
      f.type = el.value;
      if (
        NON_INPUT.indexOf(f.type) >= 0
        && f.type !== 'hidden'
        && f.inputClass === 'form-control'
      ) {
        f.inputClass = '';
      }
      if (NON_INPUT.indexOf(f.type) < 0 && !f.inputClass) {
        f.inputClass = 'form-control';
      }
      var fresh = replaceCard(f);
      var sel = fresh && fresh.querySelector('[data-p="type"]');
      if (sel) {
        sel.focus();
      }
      setDirty(true);
      renderPreview();
      return;
    }
    if (p) {
      if (el.type === 'checkbox') {
        if (!isChange) {
          return;
        }

        f[p] = el.checked;
      } else {
        f[p] = el.value;
      }
      if (p === 'label' && !f.nameTouched) {
        f.name = toVariable(f.label);
        var nameInput = card.querySelector('[data-p="name"]');
        if (nameInput) {
          nameInput.value = f.name;
        }
      }
      if (p === 'name') {
        f.nameTouched = true;
      }
      setDirty(true);
      renderPreview();
      return;
    }
    if (s) {
      if (el.type === 'checkbox') {
        if (!isChange) {
          return;
        }

        f.settings[s] = el.checked;
      } else {
        f.settings[s] = el.value;
      }
      setDirty(true);
      renderPreview();
    }
  }

  function addAfter(id) {
    var idx = id ? indexOfId(id) : fields.length - 1;
    var f = newField('text', 'New Field');
    fields.splice(idx + 1, 0, f);
    renderList(true);
    setDirty(true);
    renderPreview();
    var el = cardEl(f.id);
    if (el) {
      el.classList.add('is-flash');
      el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      var lbl = el.querySelector('[data-p="label"]');
      if (lbl) {
        lbl.focus();
        lbl.select();
      }
    }
  }
  function closeMenus() {
    Array.prototype.forEach.call(
      list.querySelectorAll('.fb-menu.is-open'),
      function (menu) {
        menu.classList.remove('is-open');
      }
    );
  }
  list.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn) {
      return;
    }
    var act = btn.getAttribute('data-act');
    var card = btn.closest('.fb-card');
    var f = card ? byId(card.getAttribute('data-id')) : null;
    if (act === 'add-first') {
      addAfter(null);
      return;
    }
    if (!f) {
      return;
    }
    if (act === 'gear') {
      f.open = !f.open;
      card.querySelector('.fb-adv').classList.toggle('is-open', f.open);
      btn.classList.toggle('is-open', f.open);
      btn.setAttribute('aria-expanded', f.open ? 'true' : 'false');
    } else if (act === 'menu') {
      var menu = card.querySelector('.fb-menu');
      var wasOpen = menu.classList.contains('is-open');
      closeMenus();
      menu.classList.toggle('is-open', !wasOpen);
      e.stopPropagation();
    } else if (act === 'add') {
      addAfter(f.id);
    } else if (act === 'add-option') {
      var optionsInput = card.querySelector('[data-p="options"]');
      if (optionsInput) {
        var currentOptions = optionsInput.value;
        if (currentOptions && !/\n$/.test(currentOptions)) {
          optionsInput.value = currentOptions + '\n';
        }
        f.options = optionsInput.value;
        setDirty(true);
        renderPreview();
        optionsInput.focus();
        optionsInput.setSelectionRange(optionsInput.value.length, optionsInput.value.length);
      }
    } else if (act === 'dup') {
      var copy = clone(f);
      copy.id = makeId();
      copy.origType = '';
      copy.label = f.label + ' Copy';
      copy.name = toVariable(copy.label);
      copy.nameTouched = false;
      fields.splice(indexOfId(f.id) + 1, 0, copy);
      renderList(true);
      setDirty(true);
      renderPreview();
      var copyCard = cardEl(copy.id);

      if (copyCard) {
        copyCard.classList.add('is-flash');
        copyCard.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    } else if (act === 'del') {
      if (!window.confirm('Delete the field \u201C' + (f.label || 'Untitled') + '\u201D? This takes effect when you save.')) {
        closeMenus();
        return;
      }
      fields.splice(indexOfId(f.id), 1);
      renderList(true);
      setDirty(true);
      renderPreview();
    }
  });
  document.addEventListener('click', function (event) {
    if (!event.target.closest('.fb-menu-wrap')) {
      closeMenus();
    }
  });
  searchInput.addEventListener('input', applyFilter);

  var drag = null;
  var dropLine = document.createElement('div');
  dropLine.className = 'fb-drop-line';

  list.addEventListener('pointerdown', function (e) {
    var strip = e.target.closest('.fb-strip');
    if (!strip || (e.pointerType === 'mouse' && e.button !== 0)) return;
    var card = strip.closest('.fb-card');
    e.preventDefault();
    var cards = Array.prototype.filter.call(
      list.querySelectorAll('.fb-card'),
      function (card) {
        return !card.classList.contains('is-hidden');
      }
    );
    drag = {
      card: card, id: card.getAttribute('data-id'), strip: strip, pointerId: e.pointerId,
      startY: e.clientY, startScroll: list.scrollTop, lastY: e.clientY, target: null, raf: null,
      rects: cards.map(function (card) {
        return {
          id: card.getAttribute('data-id'),
          top: card.offsetTop,
          h: card.offsetHeight
        };
      })
    };
    var me = drag.rects.filter(function (r) { return r.id === drag.id; })[0];
    drag.center = me.top + me.h / 2;
    drag.minDy = -me.top;
    drag.maxDy = list.scrollHeight - (me.top + me.h);

    try {
      strip.setPointerCapture(e.pointerId);
    } catch (err) {}
    card.classList.add('is-dragging');
    list.appendChild(dropLine);
    dropLine.style.display = 'none';
    closeMenus();
    loop();
  });
  list.addEventListener('pointermove', function (event) {
    if (drag && event.pointerId === drag.pointerId) {
      drag.lastY = event.clientY;
      update();
    }
  });
  list.addEventListener('pointerup', endDrag);
  list.addEventListener('pointercancel', function (event) {
    endDrag(event, true);
  });

  function loop() {
    if (!drag) {
      return;
    }
    var r = list.getBoundingClientRect(), edge = 44, speed = 0;
    if (drag.lastY < r.top + edge) {
      speed = -Math.ceil((r.top + edge - drag.lastY) / 4);
    } else if (drag.lastY > r.bottom - edge) {
      speed = Math.ceil((drag.lastY - (r.bottom - edge)) / 4);
    }
    if (speed) {
      list.scrollTop += Math.max(-24, Math.min(24, speed));
      update();
    }
    drag.raf = requestAnimationFrame(loop);
  }
  function update() {
    var dy = (drag.lastY - drag.startY) + (list.scrollTop - drag.startScroll);
    dy = Math.max(drag.minDy, Math.min(drag.maxDy, dy));
    drag.card.style.transform = 'translateY(' + dy + 'px)';
    var y = drag.center + dy;
    var others = drag.rects.filter(function (rect) {
      return rect.id !== drag.id;
    });
    var target = { before: null }, lineY;
    for (var i = 0; i < others.length; i++) {
      if (y < others[i].top + others[i].h / 2) {
        target.before = others[i].id;
        lineY = others[i].top - 10;
        break;
      }
    }
    if (target.before === null) {
      var last = others[others.length - 1];
      lineY = last ? last.top + last.h + 6 : 0;
    }
    drag.target = target;
    dropLine.style.display = 'block';
    dropLine.style.top = Math.max(0, lineY) + 'px';
  }
  function endDrag(e, cancelled) {
    if (!drag || (e && e.pointerId !== drag.pointerId)) return;
    cancelAnimationFrame(drag.raf);
    try {
      drag.strip.releasePointerCapture(drag.pointerId);
    } catch (err) {}
    var moved = false;
    if (!cancelled && drag.target) {
      var from = indexOfId(drag.id), f = fields.splice(from, 1)[0];
      var to = drag.target.before === null ? fields.length : indexOfId(drag.target.before);
      fields.splice(to, 0, f);
      moved = to !== from;
    }
    var id = drag.id;
    drag = null;
    if (dropLine.parentNode) {
      dropLine.parentNode.removeChild(dropLine);
    }
    renderList(true);
    if (moved) {
      setDirty(true);
      renderPreview();

      var movedCard = cardEl(id);
      if (movedCard) {
        movedCard.classList.add('is-flash');
      }
    }
  }
  list.addEventListener('keydown', function (e) {
    var strip = e.target.closest && e.target.closest('.fb-strip');
    if (!strip || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
    e.preventDefault();
    var id = strip.closest('.fb-card').getAttribute('data-id');
    var i = indexOfId(id), j = e.key === 'ArrowUp' ? i - 1 : i + 1;
    if (j < 0 || j >= fields.length) {
      return;
    }
    var f = fields.splice(i, 1)[0];
    fields.splice(j, 0, f);
    renderList(true);
    setDirty(true);
    renderPreview();
    var movedCard = cardEl(id);

    if (movedCard) {
      var dragStrip = movedCard.querySelector('.fb-strip');

      if (dragStrip) {
        dragStrip.focus();
      }

      movedCard.scrollIntoView({ block: 'nearest' });
    }
  });

  function refreshNickname() {
    var v = toVariable(nicknameInput.value);
    variableInput.value = v;
    shortcodeEl.textContent = '[form=' + (v || 'your_form_variable') + ']';
    formName.textContent = nicknameInput.value.trim() || 'Untitled form';
    setDirty(true);
  }
  nicknameInput.addEventListener('input', refreshNickname);
  titleInput.addEventListener('input', function () {
    setDirty(true);
  });

  descInput.addEventListener('input', function () {
    setDirty(true);
  });
  document.getElementById('toggleFormSettings').addEventListener('click', function () {
    window.location.href = 'settings.php?id=' + encodeURIComponent(window.MJD_FORM_ID);
  });
  document.getElementById('copyShortcode').addEventListener('click', function () {
    var text = shortcodeEl.textContent, btn = this, original = btn.innerHTML;
    function done() {
      btn.innerHTML = '<i class="fa fa-check"></i> Copied';

      setTimeout(function () {
        btn.innerHTML = original;
      }, 1500);
    }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () {
        window.prompt('Copy this shortcode:', text);
      });
    }
    else {
      var temporaryInput = document.createElement('textarea');
      temporaryInput.value = text;
      document.body.appendChild(temporaryInput);
      temporaryInput.select();

      try {
        document.execCommand('copy');
        done();
      } catch (error) {
        window.prompt('Copy this shortcode:', text);
      }

      temporaryInput.remove();
    }
  });

  function save() {
    if (saving) {
      return;
    }
    if (!toVariable(nicknameInput.value)) {
      settingsCard.classList.remove('is-collapsed');
      nicknameInput.focus();
      showToast('Enter a form nickname containing at least one letter or number.', true);
      return;
    }
    saving = true;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving\u2026';
    var form = document.getElementById('formBuilder');
    var body = new FormData();
    body.append('csrf_token', form.elements.csrf_token.value);
    body.append('form_id', form.elements.form_id.value);
    body.append('form_nickname', nicknameInput.value);
    body.append('form_title', titleInput.value);
    body.append('form_desc', descInput.value);
    body.append('fields_json', JSON.stringify(fields.map(function (f) {
      var fieldCopy = clone(f);
      delete fieldCopy.open;
      delete fieldCopy.nameTouched;
      return fieldCopy;
    })));

    
    fetch(window.MJD_SAVE_URL, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (response) {
        return response.text().then(function (responseText) {
          try {
            return JSON.parse(responseText);
          } catch (error) {
            throw new Error('The server returned an unexpected response.');
          }
        });
      })
      .then(function (res) {
        if (!res.ok) {
          throw new Error(res.message || 'Save failed.');
        }
        var openByIndex = fields.map(function (field) {
          return field.open;
        });

        fields = res.fields.map(function (field, index) {
          field = normalize(field);
          field.open = !!openByIndex[index];
          return field;
        });
        if (res.form) {
          variableInput.value = res.form.variable;
          shortcodeEl.textContent = res.form.shortcode;
          if (!titleInput.value.trim()) titleInput.value = res.form.title;
        }
        renderList(true);
        renderPreview();
        setDirty(false);
        showToast('Changes saved \u2014 preview updated.');
      })
      .catch(function (error) {
        showToast(error.message || 'Save failed.', true);
      })
      .then(function () {
        saving = false;
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
      });
  }
  saveBtn.addEventListener('click', save);
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
      e.preventDefault();
      save();
    }
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  var initial = Array.isArray(window.MJD_INITIAL_FIELDS) ? window.MJD_INITIAL_FIELDS : [];
  var starting = false;
  if (window.MJD_IS_NEW_FORM && !initial.length) {
    initial = [
      newField('section', 'Form header title', 'title'), newField('text', 'Enter Name', 'yourname'),
      newField('email', 'Enter Email', 'inquiry_email'), newField('tel', 'Enter Phone', 'phone'),
      newField('textarea', 'Enter Message', 'comments'), newField('submit', 'Submit Message', 'savebutton')
    ];
    initial[5].inputClass = 'btn btn-primary btn-lg btn-block';
    starting = true;
  }
  fields = initial.map(normalize);
  renderList(false);
  renderPreview();
  setDirty(starting);
}());
