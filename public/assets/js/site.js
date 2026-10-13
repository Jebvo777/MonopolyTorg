'use strict';
const base = document.body.dataset.base || '';
const csrf = document.body.dataset.csrf || '';
const toastEl = document.querySelector('#toast');
let toastTimer;
function toast(text) { if(!toastEl)return;toastEl.textContent=text;toastEl.classList.add('is-show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toastEl.classList.remove('is-show'),3800); }
async function post(form) {
 const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json','X-CSRF-Token':csrf},credentials:'same-origin'});
 const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Не удалось обработать запрос. Повторите попытку.');return data;
}
function updateCount(n){document.querySelectorAll('[data-cart-count]').forEach(el=>el.textContent=n);}
document.querySelector('.menu-toggle')?.addEventListener('click',event=>{const b=event.currentTarget;const nav=document.querySelector('#mainNav');const open=b.getAttribute('aria-expanded')!=='true';b.setAttribute('aria-expanded',String(open));nav.classList.toggle('is-open',open);});
document.addEventListener('keydown',event=>{if(event.key==='Escape'){document.querySelector('#mainNav')?.classList.remove('is-open');document.querySelector('.menu-toggle')?.setAttribute('aria-expanded','false');}});
document.querySelectorAll('.add-form').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button[type=submit]');button.disabled=true;try{const data=await post(form);updateCount(data.count);toast('Позиция добавлена в заявку');}catch(error){toast(error.message);}finally{button.disabled=false;}}));
document.querySelectorAll('.request-form').forEach(form=>form.addEventListener('submit',async event=>{
 event.preventDefault();const button=form.querySelector('button[type=submit]');const errorEl=form.querySelector('.form-error');errorEl.hidden=true;
 const file=form.querySelector('input[type=file]')?.files[0];if(file&&file.size>10*1024*1024){errorEl.textContent='Размер файла превышает 10 МБ. Выберите меньший файл или отправьте его на monopoly@list.ru.';errorEl.hidden=false;return;}
 let snapshot=form.querySelector('[name=cart_updates]');if(!snapshot){snapshot=document.createElement('input');snapshot.type='hidden';snapshot.name='cart_updates';form.append(snapshot);}
 const updates={};document.querySelectorAll('.cart-update').forEach(f=>{const values=new FormData(f);updates[values.get('key')]={quantity:values.get('quantity'),unit:values.get('unit'),parameters:values.get('parameters')};});snapshot.value=JSON.stringify(updates);
 button.disabled=true;const original=button.innerHTML;button.textContent='Отправляем заявку…';
 try{const data=await post(form);updateCount(data.count||0);location.assign(base+'/success');}catch(error){errorEl.textContent=error.message;errorEl.hidden=false;errorEl.scrollIntoView({block:'center',behavior:'smooth'});button.disabled=false;button.innerHTML=original;}
}));
document.querySelectorAll('[data-autosubmit]').forEach(input=>input.addEventListener('change',()=>input.form.requestSubmit()));
function analyticsOff(){return document.cookie.split(';').some(x=>x.trim()==='monopoly_analytics=off');}
function updatePrivacy(){document.querySelectorAll('[data-analytics-toggle]').forEach(b=>b.textContent=analyticsOff()?'Включить статистику':'Отключить статистику');}
updatePrivacy();document.querySelectorAll('[data-analytics-toggle]').forEach(button=>button.addEventListener('click',()=>{const wasOff=analyticsOff();document.cookie='monopoly_analytics='+(wasOff?'on':'off')+'; Path='+(base||'/')+'; Max-Age=31536000; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');updatePrivacy();toast(wasOff?'Статистика включена':'Статистика отключена');}));
document.querySelectorAll('[data-calculator]').forEach(form=>form.addEventListener('submit',event=>{
 event.preventDefault();const f=new FormData(form);const num=n=>Number(f.get(n));let result;
 if(form.dataset.calculator==='profile'){const area=num('width')/1000*num('length')*num('count');result=Number.isFinite(area)&&area>0?area.toLocaleString('ru-RU',{maximumFractionDigits:3})+' м²':'Проверьте размеры и количество.';}
 else{const a=num('a'),b=num('b'),t=num('t'),l=num('length'),n=num('count');let area=0;switch(f.get('shape')){case'round':area=Math.PI*a*a/4;break;case'rect':area=a*b;break;case'pipe':if(2*t>=a){result='Стенка должна быть меньше половины диаметра.';break;}area=Math.PI*(a*a-(a-2*t)**2)/4;break;case'box':if(2*t>=Math.min(a,b)){result='Стенка должна быть меньше половины каждой стороны.';break;}area=a*b-(a-2*t)*(b-2*t);break;}
 if(!result){const kg=area/1e6*l*7850*n;result=Number.isFinite(kg)&&kg>0?kg.toLocaleString('ru-RU',{maximumFractionDigits:3})+' кг · '+(kg/1000).toLocaleString('ru-RU',{maximumFractionDigits:4})+' т':'Проверьте параметры.';}}
 form.querySelector('output').textContent=result;
}));
