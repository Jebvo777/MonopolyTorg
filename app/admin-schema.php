<?php
return [
 'products'=>['title'=>'Товары','table'=>'products','search'=>['name','sku','slug'],'columns'=>['name'=>'Товар','sku'=>'Артикул','active'=>'Публикация'],'fields'=>[
  'name'=>['Название','text',true,500],'category_id'=>['Раздел','category',true],'sku'=>['Артикул','text',false,80],'slug'=>['Адрес карточки','slug',false,190],'short_description'=>['Краткое описание','textarea',true,2000],'description_html'=>['Описание и технические сведения','rich',false],
  'image'=>['Основное изображение','image',false,500],'gallery_json'=>['Дополнительные изображения','gallery',false],'specs_json'=>['Характеристики','specs',false],'unit'=>['Единица измерения','text',true,20],'request_hint'=>['Подсказка для заявки','textarea',false,2000],
  'featured'=>['Показывать на главной','checkbox'],'active'=>['Опубликован','checkbox'],'sort_order'=>['Порядок','number'],'seo_title'=>['Заголовок в поиске (title)','text',false,600],'seo_description'=>['Описание в поиске','textarea',false,2000]
 ]],
 'categories'=>['title'=>'Разделы каталога','table'=>'categories','search'=>['name','slug'],'columns'=>['name'=>'Раздел','slug'=>'Адрес','active'=>'Публикация'],'fields'=>[
  'name'=>['Название','text',true,255],'slug'=>['Адрес раздела','slug',false,190],'parent_id'=>['Родительский раздел','parent',false],'lead'=>['Вступление','textarea',true,2000],'description_html'=>['Описание раздела','rich',false],'image'=>['Изображение','image',false,500],
  'active'=>['Опубликован','checkbox'],'sort_order'=>['Порядок','number'],'seo_title'=>['Заголовок в поиске (title)','text',false,500],'seo_description'=>['Описание в поиске','textarea',false,2000]
 ]],
 'pages'=>['title'=>'Страницы','table'=>'pages','search'=>['title','slug','h1'],'columns'=>['title'=>'Страница','slug'=>'Адрес','active'=>'Публикация'],'fields'=>[
  'title'=>['Название страницы','text',true,500],'slug'=>['Адрес страницы','page_slug',false,190],'h1'=>['Главный заголовок','text',true,500],'lead'=>['Вступление','textarea',false,5000],'body_html'=>['Текст страницы','rich',false],'image'=>['Изображение','image',false,500],
  'active'=>['Опубликована','checkbox'],'sort_order'=>['Порядок','number'],'seo_title'=>['Заголовок в поиске (title)','text',false,600],'seo_description'=>['Описание в поиске','textarea',false,2000]
 ]],
 'faqs'=>['title'=>'Вопросы и ответы','table'=>'faqs','search'=>['question','answer'],'columns'=>['question'=>'Вопрос','active'=>'Публикация'],'fields'=>[
  'question'=>['Вопрос','text',true,500],'answer'=>['Ответ','textarea',true,8000],'active'=>['Опубликован','checkbox'],'sort_order'=>['Порядок','number']
 ]],
 'documents'=>['title'=>'Документы','table'=>'documents','search'=>['title','description'],'columns'=>['title'=>'Документ','kind'=>'Тип','active'=>'Публикация'],'fields'=>[
  'title'=>['Название','text',true,500],'file_path'=>['PDF-документ','document',true,500],'kind'=>['Тип документа','doc_kind',true],'description'=>['Описание','textarea',false,5000],'active'=>['Опубликован','checkbox'],'sort_order'=>['Порядок','number']
 ]],
 'media'=>['title'=>'Изображения и файлы','table'=>'media','search'=>['original_name','alt','file_path'],'columns'=>['file_path'=>'Файл','alt'=>'Подпись','bytes'=>'Размер, байт'],'fields'=>[
  'file_path'=>['Путь к файлу','readonly',true,500],'original_name'=>['Название файла','text',true,255],'alt'=>['Описание изображения','textarea',false,500]
 ]],
 'users'=>['title'=>'Администраторы','table'=>'admin_users','search'=>['username','name'],'columns'=>['username'=>'Логин','name'=>'Имя','active'=>'Доступ'],'fields'=>[
  'username'=>['Логин','text',true,80],'name'=>['Имя','text',true,120],'password'=>['Новый пароль','password',false,200],'active'=>['Доступ разрешен','checkbox']
 ]]
];
