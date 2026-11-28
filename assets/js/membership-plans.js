'use strict';
document.querySelectorAll('[data-open]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.open).showModal()));
document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));
document.querySelectorAll('.mp-editor').forEach(form=>{
 const tabs=[...form.querySelectorAll('[data-tab]')], panels=[...form.querySelectorAll('[data-panel]')];
 function activate(i){form.querySelector('[name=active_tab]').value=String(i);tabs.forEach((b,j)=>b.classList.toggle('selected',i===j));panels.forEach((p,j)=>p.hidden=i!==j);}
 tabs.forEach((b,i)=>b.addEventListener('click',()=>{form.querySelector('.mp-setting-search').value='';form.querySelectorAll('.mp-field').forEach(f=>f.hidden=false);activate(i);}));
 form.querySelector('.mp-setting-search').addEventListener('input',e=>{const q=e.target.value.toLowerCase();if(!q){form.querySelectorAll('.mp-field').forEach(f=>f.hidden=false);activate(Math.max(0,tabs.findIndex(t=>t.classList.contains('selected'))));return;}panels.forEach(p=>{let found=false;p.querySelectorAll('.mp-field').forEach(f=>{f.hidden=!f.textContent.toLowerCase().includes(q);if(!f.hidden)found=true;});p.hidden=!found;});});
 form.querySelectorAll('[data-toggle]').forEach(b=>b.addEventListener('change',()=>form.querySelectorAll(`input[name="${b.dataset.toggle}[]"]`).forEach(c=>c.checked=b.checked)));
 form.querySelectorAll('[data-all],[data-none]').forEach(b=>b.addEventListener('click',()=>b.closest('.mp-choice-group').querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=b.hasAttribute('data-all'))));
 const value=k=>form.elements.namedItem(`settings[${k}]`)?.value;
 function preview(){const p=form.querySelector('.mp-highlight-preview');if(!p)return;p.style.border=`${Math.min(20,Number(value('_highlight_border_width'))||0)}px solid ${value('_highlight_border_color')}`;p.style.borderRadius=`${Math.min(100,Number(value('_highlight_border_radius'))||0)}px`;p.style.color=value('_highlight_text_color');p.style.fontSize=`${Math.min(60,Number(value('_highlight_font_size'))||14)}px`;p.querySelector('.mp-preview-message').textContent=value('_highlight_text_message')||'Featured';p.classList.toggle('mp-preview-muted',value('_member_results_highlight')!=='1');}
 form.addEventListener('input',preview);preview();
});

// Keep the menu outside the table's scrolling and sizing calculations.
document.querySelectorAll('.mp-actions').forEach(group => {
 const trigger=group.querySelector('.mp-action-trigger'), menu=group.querySelector('.mp-action-menu');
 let timer;
 const close=()=>{clearTimeout(timer);menu.classList.remove('is-open');group.classList.remove('is-open');trigger.setAttribute('aria-expanded','false');};
 const open=()=>{clearTimeout(timer);document.querySelectorAll('.mp-actions.is-open').forEach(other=>{if(other!==group){other.classList.remove('is-open');other.querySelector('.mp-action-menu').classList.remove('is-open');other.querySelector('button').setAttribute('aria-expanded','false');}});group.classList.add('is-open');menu.classList.add('is-open');const r=trigger.getBoundingClientRect();menu.style.left=Math.max(8,Math.min(r.left,innerWidth-230))+'px';menu.style.top=(r.bottom+menu.offsetHeight>innerHeight?Math.max(8,r.top-menu.offsetHeight):r.bottom)+'px';group.classList.add('is-open');trigger.setAttribute('aria-expanded','true');};
 trigger.addEventListener('mouseenter',open);menu.addEventListener('mouseenter',()=>clearTimeout(timer));group.addEventListener('mouseleave',()=>{timer=setTimeout(close,160);});
 trigger.addEventListener('click',open);group.addEventListener('focusin',open);group.addEventListener('focusout',e=>{if(!group.contains(e.relatedTarget))close();});
 group.addEventListener('keydown',e=>{if(e.key==='Escape'){trigger.focus();close();}});menu.addEventListener('click',close);
 window.addEventListener('scroll',close,true);window.addEventListener('resize',close);document.addEventListener('pointerdown',e=>{if(!group.contains(e.target))close();});
});

function initializeMembershipEditors(root=document){
 root.querySelectorAll('.mp-richtext').forEach(field=>{
  if(field.dataset.initialized || !field.getClientRects().length)return;
  const status=field.nextElementSibling, lib=window.CKEDITOR;
  if(!lib || !lib.ClassicEditor || !window.mpEditorLicense){status.textContent='Rich text editor requires a valid CKEditor license configuration and CDN access. HTML can still be edited below.';return;}
  field.dataset.initialized='1';
  const names=['Essentials','Paragraph','Heading','Bold','Italic','Underline','Strikethrough','FontFamily','FontSize','FontColor','FontBackgroundColor','Alignment','List','Link','ImageBlock','ImageToolbar','ImageCaption','ImageStyle','ImageResize','ImageInsertViaUrl','MediaEmbed','Table','TableToolbar','SourceEditing','BlockQuote','HorizontalLine','RemoveFormat','WordCount'];
  lib.ClassicEditor.create(field,{licenseKey:window.mpEditorLicense,plugins:names.map(n=>lib[n]).filter(Boolean),placeholder:'Type something',toolbar:{items:['undo','redo','|','fontFamily','bold','italic','underline','fontSize','fontColor','fontBackgroundColor','|','heading','alignment','bulletedList','numberedList','|','link','insertImageViaUrl','mediaEmbed','insertTable','sourceEditing','blockQuote','removeFormat'],shouldNotGroupWhenFull:true},table:{contentToolbar:['tableColumn','tableRow','mergeTableCells']}}).then(editor=>{
   const sync=()=>{field.value=editor.getData();status.textContent='Characters: '+editor.getData().replace(/<[^>]*>/g,'').length;};
   editor.model.document.on('change:data',sync);field.form.addEventListener('submit',sync);sync();
  }).catch(error=>{delete field.dataset.initialized;field.hidden=false;field.style.display='';status.textContent='CKEditor could not initialize. HTML editing remains available.';console.error(error);});
 });
}
document.querySelectorAll('[data-open],[data-tab]').forEach(button=>button.addEventListener('click',()=>requestAnimationFrame(()=>initializeMembershipEditors())));document.querySelectorAll('.mp-setting-search').forEach(input=>input.addEventListener('input',()=>requestAnimationFrame(()=>initializeMembershipEditors())));
document.querySelectorAll('.mp-editor').forEach(form=>{
 const type=form.elements.namedItem('settings[profile_type]');
 const paidKeys=['payment_default','initial_amount','monthly_amount','quarterly_amount','semiyearly_amount','yearly_amount','biennially_amount','triennially_amount'];
 const refresh=()=>{paidKeys.forEach(key=>{[key,'hide_'+key].forEach(k=>{const field=form.querySelector(`[data-key="${k}"]`);if(field)field.classList.toggle('mp-paid-hidden',type.value==='free');});});};
 if(type){type.addEventListener('change',refresh);refresh();}
 const header=form.querySelector('[data-key="upgrade_page_header"]'), description=form.querySelector('[data-key="upgrade_page_description"]'), choices=form.querySelector('[data-panel="0"] .mp-choice-group');
 if(header&&description&&choices){const block=document.createElement('div');block.className='mp-fields mp-upgrade-content';choices.after(block);block.append(header,description);}
});
// Keep highlight controls and preview together without changing saved values.
document.querySelectorAll('.mp-editor').forEach(form=>{
 const fields=form.querySelector('[data-section="Highlight Member Profiles"]'), preview=form.querySelector('.mp-highlight-preview');
 if(!fields||!preview)return;
 const layout=document.createElement('div');layout.className='mp-highlight-layout';fields.before(layout);layout.append(fields,preview);
 const refresh=()=>{const enabled=form.elements.namedItem('settings[_member_results_highlight]')?.value==='1';layout.classList.toggle('is-muted',!enabled);fields.querySelectorAll('.mp-field:not([data-key="_member_results_highlight"])').forEach(field=>{field.inert=!enabled;});};
 form.addEventListener('change',refresh);refresh();
});

document.querySelectorAll('.mp-editor').forEach(form=>{
 const panel=form.querySelector('[data-panel="4"]');if(!panel)return;
 const postAll=panel.querySelector('[data-publish-all]'),viewAll=panel.querySelector('[data-view-all]');
 const posts=[...panel.querySelectorAll('[data-activate]')],views=[...panel.querySelectorAll('[data-view-rule]')];
 function refresh(){postAll.checked=posts.length>0&&posts.every(c=>c.checked);postAll.indeterminate=posts.some(c=>c.checked)&&!postAll.checked;viewAll.checked=views.length>0&&views.every(c=>c.value==='allow');viewAll.indeterminate=views.some(c=>c.value==='allow')&&!viewAll.checked;views.forEach(c=>{const status=c.closest('.mp-publish-row').querySelector('.mp-view-status');status.textContent=c.value==='deny'?'Cannot View':c.value==='allow'?'Can View':'Public for All to View';status.classList.toggle('is-denied',c.value==='deny');});}
 postAll.addEventListener('change',()=>{posts.forEach(c=>c.checked=postAll.checked);refresh();});viewAll.addEventListener('change',()=>{views.forEach(c=>c.value=viewAll.checked?'allow':'deny');refresh();});panel.addEventListener('change',refresh);refresh();
});

initializeMembershipEditors();
