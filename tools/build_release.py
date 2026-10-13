#!/usr/bin/env python3
"""Build the private deployment archive and separate unified SQL deliverable."""
from pathlib import Path
import zipfile,shutil,hashlib,json
ROOT=Path(__file__).resolve().parents[1]
DEST=ROOT.parent/'deliverables';DEST.mkdir(exist_ok=True)
archive=DEST/'monopoly-php-site.zip'
# Credentials are only part of the user-requested private deployment archive.
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=7) as z:
 for file in sorted(ROOT.rglob('*')):
  if not file.is_file():continue
  rel=file.relative_to(ROOT)
  if rel.parts[0] in ['.git','asset-packs','releases']:continue
  if rel.parts[0]=='storage' and file.name not in ['.gitkeep','.htaccess']:continue
  if file.name=='.gitignore' or str(rel)=='docs/release.json':continue
  z.write(file,'monopoly-site/'+str(rel))
sql=DEST/'monopoly_install.sql';shutil.copy2(ROOT/'database/monopoly_install.sql',sql)
info={'archive':archive.name,'archive_bytes':archive.stat().st_size,'archive_sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'sql':sql.name,'sql_bytes':sql.stat().st_size,'sql_sha256':hashlib.sha256(sql.read_bytes()).hexdigest()}
(ROOT/'docs/release.json').write_text(json.dumps(info,ensure_ascii=False,indent=2))
print(json.dumps(info,ensure_ascii=False))
