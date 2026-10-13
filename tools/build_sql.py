#!/usr/bin/env python3
"""Generate one MySQL script with schema and all publication content."""
import json,mimetypes
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def load(name):return json.loads((ROOT/'data'/f'{name}.json').read_text())
def quote(value):
 if value is None:return 'NULL'
 if isinstance(value,(int,float)):return str(value)
 s=str(value).replace('\\','\\\\').replace("'","''").replace('\x00','').replace('\r','\\r').replace('\n','\\n')
 return "'"+s+"'"
def dump(table,record):
 cols=list(record);values=[quote(record[c]) for c in cols]
 updates=','.join('`'+c+'`=VALUES(`'+c+'`)' for c in cols if c not in ['id'])
 return f"INSERT INTO `{table}` ({','.join('`'+c+'`' for c in cols)}) VALUES ({','.join(values)}) ON DUPLICATE KEY UPDATE {updates};\n"
header="""-- Trading company Monopoly: database creation and full initial content.
-- Import into an empty database. Initial administrator password is supplied separately.
-- Database user and password are configured outside this SQL file.
SET NAMES utf8mb4;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET time_zone='+03:00';
CREATE DATABASE IF NOT EXISTS `system404_monop` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `system404_monop`;
"""
out=[header,(ROOT/'database/schema.sql').read_text(),"\nSTART TRANSACTION;\n"]
for k,v in load('settings').items():out.append(dump('settings',{'name':k,'value':v}))
admin=load('admin-user');admin['id']=1;out.append(dump('admin_users',admin))
catalog=load('catalog')
for c in catalog['categories']:out.append(dump('categories',c))
for p in catalog['products']:
 p=dict(p)
 for source,target in [('specs','specs_json'),('gallery','gallery_json'),('old_urls','old_urls_json')]:p[target]=json.dumps(p.pop(source),ensure_ascii=False)
 out.append(dump('products',p))
for p in load('pages'):out.append(dump('pages',p))
for f in load('faqs'):out.append(dump('faqs',f))
for d in load('documents'):out.append(dump('documents',d))
paths=[]
for pattern in ['assets/img/**/*','assets/documents/*','favicon.ico','apple-touch-icon.png']:
 paths.extend(p for p in (ROOT/'public').glob(pattern) if p.is_file())
for i,p in enumerate(sorted(set(paths)),1):
 if p.suffix.lower() not in ['.jpg','.jpeg','.png','.gif','.webp','.svg','.ico','.pdf']:continue
 rel=str(p.relative_to(ROOT/'public'))
 alt=next((x['name'] for x in catalog['products'] if rel==x['image']),'')
 record={'id':i,'file_path':rel,'original_name':p.name,'alt':alt,'mime':mimetypes.guess_type(p.name)[0] or 'application/octet-stream','bytes':p.stat().st_size}
 out.append(dump('media',record))
out.append('COMMIT;\n')
path=ROOT/'database/monopoly_install.sql';path.write_text(''.join(out),encoding='utf-8')
print('Database installation script created:',path.name)
