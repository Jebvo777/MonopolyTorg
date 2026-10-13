#!/usr/bin/env python3
"""Store source assets in bounded GitHub binary packs. Not the installation release."""
from pathlib import Path
import zipfile,json
root=Path(__file__).resolve().parents[1]
folder=root/'asset-packs';folder.mkdir(exist_ok=True)
files=[]
for pattern in ['public/assets/img/products/*','public/assets/documents/*','public/assets/img/*.webp','public/assets/img/logo-mark.png','public/favicon.ico','public/apple-touch-icon.png','public/assets/fonts/*.ttf','public/assets/fonts/*.woff2']:
 files.extend(p for p in root.glob(pattern) if p.is_file())
files=sorted(set(files));groups=[];current=[];size=0
for p in files:
 if current and size+p.stat().st_size>1500000:groups.append(current);current=[];size=0
 current.append(p);size+=p.stat().st_size
groups.append(current)
output=[]
for i,group in enumerate(groups,1):
 p=folder/f'assets-{i:02d}.zip'
 with zipfile.ZipFile(p,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
  for f in group:z.write(f,str(f.relative_to(root)))
 output.append({'path':str(p.relative_to(root)),'size':p.stat().st_size,'files':len(group)})
(folder/'manifest.json').write_text(json.dumps(output,ensure_ascii=False,indent=2))
print('Asset packs prepared:',len(groups))
