#!/usr/bin/env python3
"""Import the supplied offline copy, preserving source URLs and asset provenance."""
import json,re,hashlib,shutil,csv,html
from pathlib import Path
from collections import Counter
from urllib.parse import urlparse,unquote
from bs4 import BeautifulSoup,Comment
ROOT=Path(__file__).resolve().parents[1]
SOURCE=ROOT.parent/'source-old'
manifest=json.loads((SOURCE/'manifest.json').read_text(encoding='utf-8-sig'))
all_files=manifest['Files']
url_files={f['Url']:f for f in all_files}
local_files={Path(f['LocalFile']).name:f for f in all_files if f.get('LocalFile')}

def clean(v):
 v=re.sub(r'\s+',' ',v.replace('\xa0',' ')).strip()
 v=re.sub(r'\bТК\b','торговая компания',v,flags=re.I)
 return v

def title(s):
 t=clean(s.title.get_text(' ',strip=True) if s.title else '')
 t=re.split(r'\s+-\s+ТОРГОВАЯ',t,flags=re.I)[0]
 return t

roots=[('cable','Кабельная продукция','Кабели и провода для строительства, промышленности и энергетики.','kabelnaya-produkciya'),('metal','Металлопрокат','Арматура, стальные трубы, сортовой и листовой прокат для строительных и производственных задач.','metalloprocat'),('profile','Профнастил','Профилированные листы для кровли, фасадов, ограждений и несущих конструкций.','profnactil'),('transformers','Трансформаторы и КТП','Силовые трансформаторы и комплектные подстанции по параметрам проекта.','transformatornye-podstantsii')]
categories=[];products=[];articles=[];redirects={};source_map=[]
for i,(slug,name,lead,path) in enumerate(roots,1):categories.append(dict(id=i,parent_id=None,slug=slug,name=name,lead=lead,description_html='',image=f'assets/img/{slug}.webp',sort_order=i,active=1,seo_title=f'{name} в Санкт-Петербурге — торговая компания «Монополия»',seo_description=lead+' Подбор и расчет по спецификации.'))

def get_category(root,group):
 parent=next(c for c in categories if c['slug']==root)
 if not group:return parent['id']
 slug=root+'-'+group
 c=next((c for c in categories if c['slug']==slug),None)
 if c:return c['id']
 f=next((f for f in all_files if f['Url'].endswith('/'+group) and f['State']=='Downloaded' and 'html' in f['ContentType']),None)
 name=title(BeautifulSoup((SOURCE/f['LocalFile']).read_text(),'lxml')) if f else group.replace('-',' ')
 fixes={'kabeli-dlya-pit-pogr-nasosov':'Кабели для питания погружных насосов','kabeli-dlya-sist-signalizatsii':'Кабели для сигнализации и передачи данных','kabeli-sil-s-rez-izolyatsiej':'Силовые кабели с резиновой изоляцией','kabeli-ognestojkie':'Огнестойкие кабели','samoreg-nagrevatelnyj-kabel':'Саморегулирующийся нагревательный кабель','transformatornye-podstantsii':'Комплектные трансформаторные подстанции'}
 name=fixes.get(group,name)
 name=name.replace('Кабели сил.','Кабели силовые').replace('с пвх','с ПВХ')
 lead=f'{name} для комплектации строительных, промышленных и энергетических объектов. Подберем исполнение и рассчитаем поставку по вашей спецификации.'
 c=dict(id=len(categories)+1,parent_id=parent['id'],slug=slug,name=name,lead=lead,description_html=f'<p>{html.escape(lead)}</p><p>Укажите марку, необходимые характеристики, объем и адрес поставки. Актуальные условия и комплект документов согласуем в коммерческом предложении.</p>',image=parent['image'],sort_order=len(categories),active=1,seo_title=f'{name} — поставка и подбор | Монополия',seo_description=lead)
 categories.append(c);return c['id']

allowed={'p','h2','h3','h4','ul','ol','li','table','thead','tbody','tr','th','td','strong','em','b','br','a','img','sub','sup','caption','blockquote','div'}
copied={}
def copy_asset(src,base):
 src=unquote(src.split('?')[0]);p=(base/src).resolve()
 if not p.is_relative_to(SOURCE.resolve()) or not p.is_file():return None
 if p.suffix.lower() not in ['.jpg','.jpeg','.png','.gif','.webp','.pdf']:return None
 if p.name in copied:return copied[p.name]
 dest=(ROOT/'public/assets/documents' if p.suffix.lower()=='.pdf' else ROOT/'public/assets/img/products')/p.name
 shutil.copy2(p,dest);rel=str(dest.relative_to(ROOT/'public'));copied[p.name]=rel;return rel

def sanitize(block,base):
 soup=BeautifulSoup(str(block),'lxml')
 for x in soup.find_all(string=lambda t:isinstance(t,Comment)):x.extract()
 for x in soup.select('script,style,iframe,form,input,button,select,option,.pagination,.article-info,.actions,#system-message-container,#cont'):x.decompose()
 for table in soup.select('table'):
  rows=table.select('tr')
  if not rows:continue
  # Historical amounts are never published as current prices.
  cells=rows[0].find_all(['td','th'],recursive=False)
  price_indices=[i for i,c in enumerate(cells) if re.search('цена|стоимость',c.get_text(),re.I)]
  for row in rows:
   for i,c in reversed(list(enumerate(row.find_all(['td','th'],recursive=False)))):
    if i in price_indices:c.decompose()
 for x in list(soup.find_all(True)):
  if x.name in ['html','body']:continue
  if x.name not in allowed:x.unwrap();continue
  attrs={}
  if x.name=='img':
   asset=copy_asset(x.get('src',''),base)
   if not asset:x.decompose();continue
   attrs={'src':asset,'alt':clean(x.get('alt','') or 'Схема и конструкция продукции'),'loading':'lazy'}
  if x.name in ['td','th']:
   for a in ['colspan','rowspan']:
    if str(x.get(a,'')).isdigit() and int(x[a])<30:attrs[a]=x[a]
  if x.name=='a':
   href=x.get('href','');asset=copy_asset(href,base)
   if asset:attrs={'href':asset,'target':'_blank','rel':'noopener'}
   else:x.unwrap();continue
  x.attrs=attrs
 for t in soup.find_all(string=True):
  if isinstance(t,Comment):continue
  value=clean(str(t))
  value=re.sub(r'(?i)прайс[- ]лист','Типоразмеры и исполнения',value)
  t.replace_with(value+' ' if value else '')
 for x in list(soup.select('p,div,h2,h3,h4')):
  if not x.get_text(strip=True) and not x.select('img'):x.decompose()
 body=soup.body or soup
 return str(body.decode_contents()).strip()

html_files=[f for f in all_files if f['State']=='Downloaded' and 'html' in f['ContentType']]
for f in html_files:
 u=urlparse(f['Url']);path=u.path.removeprefix('/index.php/').strip('/')
 if u.query:source_map.append({'old_url':f['Url'],'kind':'duplicate_or_pagination','local_file':f['LocalFile']});continue
 s=BeautifulSoup((SOURCE/f['LocalFile']).read_text(),'lxml');main=s.select_one('#content');block=s.select_one('.item-page')
 name=title(s);kind='information';target=None
 parts=path.split('/')
 root=None
 if parts[0]=='kabelnaya-produkciya':root='cable'
 elif parts[0]=='metalloprocat':root='metal'
 elif parts[0]=='profnactil':root='profile'
 elif parts[0]=='transformatornye-podstantsii':root='transformers'
 is_product=bool(root and block and len(parts)>1)
 if is_product and re.search(r'особенности|применение про|правка|резка|производство|раскрой',name,re.I):is_product=False
 if is_product:
  if root=='cable' and not re.match(r'\d+-',parts[-1]):is_product=False
  if root=='transformers' and not re.match(r'\d+-',parts[-1]):is_product=False
 if is_product:
  if root in ['cable','transformers']:group=parts[1]
  elif root=='metal' and len(parts)>2:group=parts[1]
  else:group=''
  category_id=get_category(root,group)
  if root=='profile':
   h=block.find(['h1','h2']);name=clean(h.get_text(' ',strip=True)) if h and h.get_text(strip=True) else name
   if len(name)>110:name=re.split(r',\s*(?:купить|цена|стоимость)',name)[0]
  if root=='metal':
   h=block.find('h1');name=clean(h.get_text(' ',strip=True)) if h else re.split(r',\s*(?:купить|цена|стоимость)',name)[0]
  pid=len(products)+1;rawslug=parts[-1];slug=rawslug if re.match(r'\d+-',rawslug) else root+'-'+rawslug
  base=(SOURCE/f['LocalFile']).parent
  gallery=[]
  for im in block.select('img'):
   a=copy_asset(im.get('src',''),base)
   if a and a not in gallery:gallery.append(a)
  text=clean(block.get_text(' ',strip=True))
  intro=''
  for p in block.select('p'):
   t=clean(p.get_text(' ',strip=True))
   if 55<len(t)<700 and not re.search(r'прайс|гост.*цена|скачать',t,re.I):intro=t;break
  if not intro or intro==name:intro=f'{name} для промышленных, строительных и энергетических объектов.'
  if len(intro)>250:intro=intro[:247].rsplit(' ',1)[0]+'.'
  specs=[]
  for row in block.select('table tr'):
   cs=row.find_all(['td','th'],recursive=False)
   if len(cs)==2:
    a,b=[clean(c.get_text(' ',strip=True)) for c in cs]
    if a and b and len(a)<160 and len(b)<180:specs.append([a,b])
  unit='м' if root=='cable' else 'т' if root=='metal' else 'лист' if root=='profile' else 'шт.'
  req={'cable':'Укажите марку, число и сечение жил, рабочее напряжение, длину и условия прокладки.','metal':'Укажите размеры, марку стали, требуемый стандарт, количество или массу и условия поставки.','profile':'Укажите профиль, толщину, длину и количество листов, покрытие и цвет RAL.','transformers':'Укажите мощность, напряжение, исполнение и комплектацию. Приложите опросный лист или проект.'}[root]
  seo=f'{name}: подбор и поставка в Санкт-Петербурге. {req} Расчет стоимости — по заявке.'
  products.append(dict(id=pid,category_id=category_id,root_category=root,name=name,slug=slug,sku=f'MNP-{pid:04d}',short_description=intro,description_html=sanitize(block,base),specs=specs[:30],gallery=gallery,image=gallery[0] if gallery else 'assets/img/product-placeholder.svg',unit=unit,price=None,request_hint=req,active=1,featured=1 if parts[-1] in ['111-vvgng','102-vvg','kvadrat','armatura','mp20kh1100','s21kh1000','939-transformatory-tmg','960-ktp'] else 0,sort_order=pid,seo_title=f'{name} — купить по заявке | Монополия',seo_description=seo[:300],old_urls=[f['Url']],source_file=f['LocalFile']))
  target='/product/'+slug;kind='product'
 elif root and len(parts)<=2:
  cid=get_category(root,parts[1] if len(parts)>1 and root in ['cable','transformers'] else '')
  cat=next(c for c in categories if c['id']==cid);target='/catalog/'+cat['slug'];kind='category'
 elif main and re.search(r'/\d+-',u.path):
  text=clean(main.get_text(' ',strip=True))
  if len(text)>50 and not re.search('В разработке',text,re.I):
   slug=parts[-1];articles.append(dict(slug=slug,title=name,body_html=sanitize(main,(SOURCE/f['LocalFile']).parent),old_url=f['Url']))
   target='/information/'+slug;kind='article'
 elif path in ['','index.php'] :target='/';kind='home'
 elif path=='o-kompanii':target='/about';kind='company'
 elif path=='ofis-prodazh':target='/contacts';kind='contacts'
 elif 'sertifik' in path or 'tekh-informats' in path or 'prajs' in path:target='/documents';kind='documents'
 elif path.startswith('kalkulyator'):target='/calculators';kind='calculator'
 if target:redirects[u.path]=target
 source_map.append(dict(old_url=f['Url'],kind=kind,title=name,new_path=target,local_file=f['LocalFile']))
# Map short menu aliases to their canonical nested counterparts.
for row in source_map:
 if row['kind']!='information':continue
 suffix=urlparse(row['old_url']).path.split('/')[-1]
 matches=[r for r in source_map if r.get('new_path') and urlparse(r['old_url']).path.split('/')[-1]==suffix and r['kind'] in ['product','category']]
 if matches:redirects[urlparse(row['old_url']).path]=matches[0]['new_path'];row['new_path']=matches[0]['new_path'];row['kind']='alias'
# Archive PDFs as explicitly historical technical references, with their actual link labels.
documents=[]
labels={}
for f in html_files:
 if any(x in f['Url'] for x in ['tekh-informats','sertifik','prajs']):
  s=BeautifulSoup((SOURCE/f['LocalFile']).read_text(),'lxml')
  for a in s.select('#content a[href]'):
   if '.pdf' in a.get('href','').lower():labels[Path(a['href']).name]=clean(a.get_text(' ',strip=True))
for f in all_files:
 if f['State']=='Downloaded' and f.get('LocalFile','').endswith('.pdf'):
  path=copy_asset(Path(f['LocalFile']).name,(SOURCE/f['LocalFile']).parent)
  documents.append(dict(id=len(documents)+1,title=labels.get(Path(f['LocalFile']).name,unquote(Path(f['Url']).name).replace('_',' ')),file_path=path,kind='archive',description='Архивный технический документ. Для поставки применимость стандарта и действующие документы подтверждаются в коммерческом предложении.',active=1))
summary={'source':'Старая копия сайта.zip','source_domain':'www.monopoly-torg.ru','snapshot_finished':manifest['FinishedUtc'],'downloaded_files':manifest['Downloaded'],'html_files':len(html_files),'categories':len(categories),'products':len(products),'products_with_image':sum(p['image']!='assets/img/product-placeholder.svg' for p in products),'product_image_files':sum(1 for v in copied.values() if '/products/' in v),'documents':len(documents),'articles':len(articles),'redirects':len(redirects),'root_counts':dict(Counter(p['root_category'] for p in products)),'source_errors':manifest['Errors'],'brand':'торговая компания «Монополия»','contact':{'phone':'+7 (812) 932-86-48','email':'monopoly@list.ru','address':'195299, Санкт-Петербург, Киришская ул., д. 2А, помещение 8Н','hours':'Пн–Пт, 09:30–18:00'},'design_source':'monopoly-site-v2(2).zip','design_product_groups':52}
for name,data in [('catalog',{'categories':categories,'products':products}),('articles',articles),('documents',documents),('redirects',redirects)]:
 (ROOT/'data'/f'{name}.json').write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf-8')
for name,data in [('manifest',summary),('source-map',source_map)]:
 (ROOT/'docs'/f'{name}.json').write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf-8')
with (ROOT/'docs/products.csv').open('w',encoding='utf-8-sig',newline='') as fp:
 w=csv.writer(fp,delimiter=';');w.writerow(['ID','Артикул','Товар','Раздел','URL','Изображение','Источник'])
 for p in products:w.writerow([p['id'],p['sku'],p['name'],p['root_category'],'/product/'+p['slug'],p['image'],p['old_urls'][0]])
lines=['# Карта сайта','', '| Страница | Адрес |', '|---|---|']
for n,p in [('Главная','/'),('Каталог','/catalog'),('О компании','/about'),('Условия поставки','/delivery'),('Документы','/documents'),('Вопросы и ответы','/faq'),('Контакты','/contacts'),('Поиск','/search'),('Корзина и заявка','/request'),('Техническая информация','/information'),('Калькуляторы','/calculators'),('Политика конфиденциальности','/privacy'),('Карта сайта','/sitemap'),('Администрирование','/admin')]:lines.append(f'| {n} | `{p}` |')
lines+=['','## Разделы каталога','']+[f'- {c["name"]}: `/catalog/{c["slug"]}`' for c in categories]+['','## Товарные карточки','',f'Полный список {len(products)} карточек находится в `products.csv` и `../data/catalog.json`. Каждая карточка имеет отдельный адрес `/product/<slug>`. Старые адреса перенаправляются через `../data/redirects.json`.']
(ROOT/'docs/sitemap.md').write_text('\n'.join(lines),encoding='utf-8')
(ROOT/'docs/analysis.md').write_text(f'''# Манифест переноса сайта торговой компании «Монополия»

Источник — предоставленная офлайн-копия, завершенная {manifest['FinishedUtc']}. Макет — предоставленный архив редизайна. В макете 52 укрупненные группы товаров; это не полный ассортимент исходного сайта.

Импортировано {len(products)} отдельных товарных карточек, {len(categories)} разделов, {len(documents)} PDF и {len(articles)} информационных материалов. Изображения имеются у {summary['products_with_image']} товаров. Недостающие изображения заменяются нейтральной товарной заглушкой, без синтетических фотографий товара.

Исходный сайт — Joomla с техническими дублями меню, постраничными списками и устаревшей версткой. Дубли объединены, старая структура адресов сохранена в таблице перенаправлений. Товары с разными исходными карточками и маркировками сохранены отдельно. Характеристики и таблицы типоразмеров берутся из архивных карточек, исторические суммы из прайс-листов не используются как действующие цены.

Публичные тексты — окончательная редакция для публикации. Название компании всегда записывается полностью. SEO: самостоятельные title и description, один главный заголовок, хлебные крошки, canonical, XML-карта, структурированные данные и перенаправления старых адресов. Необоснованные обещания наличия, цен и сроков не добавляются.

Заявки оформляются из корзины, по свободной спецификации либо через форму. Получатель — monopoly@list.ru. Каждая заявка сохраняется в БД независимо от результата доставки письма, почтовые ошибки видны администратору.

Админка: товары, разделы, страницы, блоки главной, меню, контакты, SEO, изображения, документы, вопросы и ответы, заявки, настройки почты, пароль, посещаемость и CSV-экспорт. Статистика собирается на собственном сервере.

Конфигурация подключения и первоначальные учетные данные включаются только в приватный установочный архив. Секреты не публикуются открытым текстом в GitHub.

Логотип исходного сайта отсутствует как отдельный PNG в копии: сохранилась фирменная иконка с земным шаром и лентой. Редизайн сохраняет эти узнаваемые элементы в палитре макета.

Проверка функциональности, всех типов страниц, адаптивности и отступов выполняется в заключительном этапе. Сборка архива и единого SQL — в предпоследнем.
''',encoding='utf-8')
print(json.dumps(summary,ensure_ascii=False,indent=2))
