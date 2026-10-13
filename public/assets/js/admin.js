'use strict';
document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(form.dataset.confirm))event.preventDefault();}));
document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.copy);button.textContent='Скопировано';setTimeout(()=>button.textContent='Копировать путь',2200);}catch{const input=button.closest('.media-card').querySelector('input');input.focus();input.select();}}));
document.querySelectorAll('.rich-field').forEach(field=>{
 const editor=field.querySelector('.rich-editor'),source=field.querySelector('.rich-source');
 field.querySelectorAll('[data-editor-command]').forEach(button=>button.addEventListener('click',()=>{editor.focus();let value=button.dataset.editorValue||null;if(button.dataset.editorCommand==='createLink'){value=prompt('Адрес ссылки (https://… или /страница)');if(!value||!(/^(https?:\/\/|\/|mailto:|tel:)/i.test(value)))return;}document.execCommand(button.dataset.editorCommand,false,value);source.value=editor.innerHTML;}));
 field.querySelector('[data-editor-source]').addEventListener('click',()=>{if(source.hidden){source.value=editor.innerHTML;source.hidden=false;editor.hidden=true;}else{editor.innerHTML=source.value;source.hidden=true;editor.hidden=false;}});
 field.closest('form').addEventListener('submit',()=>{if(source.hidden)source.value=editor.innerHTML;});
});
