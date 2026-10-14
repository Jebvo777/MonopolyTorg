#!/usr/bin/env python3
"""Package the existing database-backed site directly into a hosting web root."""
from pathlib import Path
from urllib.parse import urlsplit
import argparse, hashlib, json, re, shutil, zipfile

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--origin', default='https://ps1.system404-sites.ru')
parser.add_argument('--base-path', default='/site7')
parser.add_argument('--output', type=Path, default=ROOT.parent/'deliverables/monopoly-site7-public_html.zip')
args = parser.parse_args()
origin = args.origin.rstrip('/')
base = args.base_path.rstrip('/')
parsed = urlsplit(origin)
if parsed.scheme not in ('https','http') or not parsed.netloc or parsed.path or parsed.query or parsed.fragment:
    parser.error('--origin must contain only the scheme and hostname')
if base and not re.fullmatch(r'(?:/[A-Za-z0-9_-]+)+', base):
    parser.error('--base-path must be a safe absolute URL path')

local = ROOT/'app/config.local.php'
if not local.is_file():
    raise SystemExit('The private app/config.local.php must be present before packaging.')

stage = ROOT.parent/'deployment-site7'
if stage.exists():
    shutil.rmtree(stage)
shutil.copytree(ROOT/'public', stage)
for directory in ('app','data','docs'):
    ignore = shutil.ignore_patterns('release.json','deployment-site7.json') if directory=='docs' else None
    shutil.copytree(ROOT/directory, stage/directory, ignore=ignore)
(stage/'bin').mkdir()
for name in ('send-mail.php','maintenance.php','router.php'):
    shutil.copy2(ROOT/'bin'/name, stage/'bin'/name)
for directory in ('storage','storage/cache','storage/logs','storage/specifications','assets/img/uploads'):
    (stage/directory).mkdir(parents=True, exist_ok=True)
for directory in ('app','data','docs','bin','storage'):
    (stage/directory/'.htaccess').write_text('Require all denied\n', encoding='utf-8')

# The public entry files now live directly next to app/ and assets/.
p = stage/'index.php'
p.write_text(p.read_text().replace("require dirname(__DIR__).'/app/bootstrap.php';", "require __DIR__.'/app/bootstrap.php';"))
p = stage/'admin/index.php'
p.write_text(p.read_text().replace("dirname(__DIR__,2).'/app/bootstrap.php'", "dirname(__DIR__).'/app/bootstrap.php'"))
p = stage/'app/config.php'
p.write_text(p.read_text().replace("'public_root' => dirname(__DIR__).'/public'", "'public_root' => dirname(__DIR__)"))
p = stage/'app/config.local.php'
s = p.read_text()
for key, value in (('base_url', origin), ('base_path', base)):
    pattern = r"('"+key+r"'\s*=>\s*)'[^']*'"
    s, count = re.subn(pattern, lambda m: m.group(1)+repr(value), s)
    if count != 1:
        raise SystemExit('Cannot update '+key+' in the private configuration.')
if re.search(r"'public_root'\s*=>", s):
    s = re.sub(r"('public_root'\s*=>\s*)[^,\n]+", lambda m:m.group(1)+'dirname(__DIR__)', s)
else:
    s = s.replace('return [', "return [\n    'public_root' => dirname(__DIR__),", 1)
p.write_text(s)

htaccess = '''Options -Indexes
DirectoryIndex index.php
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase {base}/
RewriteRule (^|/)\\. - [F,L]
RewriteRule ^(?:app|data|database|docs|tools|bin|storage|asset-packs|releases|ci|public)(?:/|$) - [F,L,NC]
RewriteRule ^(?:ACCESS\\.txt|INSTALL\\.txt|README\\.md|.*\\.(?:sql|zip|log|json|md|py|sh))$ - [F,L,NC]
RewriteCond %{{REQUEST_FILENAME}} !-f
RewriteCond %{{REQUEST_FILENAME}} !-d
RewriteRule ^ index.php [QSA,L]
</IfModule>
<FilesMatch "^(?:ACCESS\\.txt|INSTALL\\.txt|README\\.md|.*\\.(?:sql|zip|log|json|md|py|sh))$">
Require all denied
</FilesMatch>
<IfModule mod_headers.c>
Header always set X-Content-Type-Options "nosniff"
</IfModule>
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType image/webp "access plus 1 year"
ExpiresByType image/jpeg "access plus 1 year"
ExpiresByType font/woff2 "access plus 1 year"
ExpiresByType text/css "access plus 1 month"
ExpiresByType application/javascript "access plus 1 month"
</IfModule>
'''.format(base=base)
(stage/'.htaccess').write_text(htaccess)
instructions = f'''Сайт торговой компании «Монополия»

Адрес: {origin}{base}/

Распакуйте ВЕСЬ архив непосредственно в public_html, включая .htaccess.
В корне public_html сразу должны находиться index.php, assets/, app/ и admin/.
Создавать папку public или дополнительную папку site7 не требуется.

База данных уже развернута. Повторный импорт или SQL-обновление не нужны.
Параметры подключения сохранены в app/config.local.php.

После распаковки откройте {origin}{base}/
Административная панель: {origin}{base}/admin/
Ваши существующие тексты, товары, заявки и доступы хранятся в установленной БД.

Стандартный Apache-хостинг должен обрабатывать включенный .htaccess и PHP 8.2+.
Папкам storage/, assets/img/uploads/ и assets/documents/ нужен доступ PHP на запись.
Заявки направляются на monopoly@list.ru согласно существующим настройкам почты.

При использовании cron путь к обработчику очереди теперь public_html/bin/send-mail.php.
Руководство администратора и исходный реестр товаров находятся в docs/.
'''
(stage/'INSTALL.txt').write_text(instructions, encoding='utf-8')
args.output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(args.output, 'w', zipfile.ZIP_DEFLATED, compresslevel=7) as z:
    for p in sorted(stage.rglob('*')):
        if p.is_file():
            z.write(p, str(p.relative_to(stage)))
        elif not any(p.iterdir()):
            z.writestr(str(p.relative_to(stage))+'/', '')
info = {'url':origin+base+'/', 'archive':args.output.name,
        'bytes':args.output.stat().st_size,
        'sha256':hashlib.sha256(args.output.read_bytes()).hexdigest(),
        'layout':'direct-public_html', 'database_migration_required':False}
(ROOT/'docs/deployment-site7.json').write_text(json.dumps(info, ensure_ascii=False, indent=2)+'\n')
print(json.dumps(info, ensure_ascii=False))
