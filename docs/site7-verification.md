# Проверка размещения в /site7/

14 октября 2026 года.

Исправленный архив распакован напрямую в тестовый корень сайта. Проверены 36 условия; все выполнены.

Использована копия уже установленной тестовой БД: повторное заполнение не выполнялось. Структура таблиц и исходный объединенный SQL совпадают с переданными установочными файлами. Миграция БД не нужна.

- Archive opens directly at index.php
- No database reinstaller in deployment
- Production origin, prefix and public root
- Database schema unchanged
- Unified SQL unchanged
- Existing catalogue is preserved
- Route /
- Route /catalog
- Route /about
- Route /documents
- Route /product/898-kvorne-3
- Route /request
- Route /robots.txt
- Route /sitemap.xml
- Route /theme.css
- Route /assets/css/site.css
- Route /assets/js/site.js
- Route /favicon.ico
- Canonical includes site7
- All internal page URLs keep prefix
- Session cookie scoped to site7
- Robots points to prefixed sitemap
- Cart POST at site7 works
- Cart survives navigation
- Existing administrator login works
- Direct administrator entry works
- Existing product CSV export works
- Private directory blocked /app/config.local.php
- Private directory blocked /data/catalog.json
- Private directory blocked /docs/products.csv
- Private directory blocked /storage/specifications/private.pdf
- Private directory blocked /INSTALL.txt
- Media upload succeeds in flat layout
- Uploaded file is in root assets
- Uploaded file URL works
- Edited HTML keeps prefixed links and images

Проверка выполнена локально, без доступа к пользовательской рабочей БД и без отправки реальных писем. CSS и визуальные ресурсы не изменялись; повторный полный визуальный прогон не выполнялся. Размещение файлов на хостинге выполняет пользователь.
