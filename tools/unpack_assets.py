#!/usr/bin/env python3
"""Restore binary website assets from the GitHub asset packs."""
from pathlib import Path
import zipfile
root=Path(__file__).resolve().parents[1]
for pack in sorted((root/'asset-packs').glob('*.zip')):
 with zipfile.ZipFile(pack) as archive:
  for info in archive.infolist():
   target=(root/info.filename).resolve()
   if not target.is_relative_to((root/'public').resolve()):raise RuntimeError('Unsafe asset path')
  archive.extractall(root)
print('Website assets restored')
