# Модуль опций для Bitrix

[![Claramente](https://claramente.ru/upload/claramente/a2c/ho3rj4p3j2t7scsartohgjajkb1xkyh0/logo.svg)](https://claramente.ru)

Установка через composer
-------------------------
Пример composer.json с установкой модуля в local/modules/
```
{
  "extra": {
    "installer-paths": {
      "local/modules/{$name}/": ["type:bitrix-module"]
    }
  },
  "require": {
    "claramente-ru/bitrix-options": "dev-master"
  }
}
```

1. Запустить `composer require claramente/claramente.options dev-master`

2. В административном разделе установить модуль **claramente.options** _(/bitrix/admin/partner_modules.php?lang=ru)_

3. Подключить модуль в `/local/php_interface/init.php` или в `/bitrix/php_interface/init.php` добавив строчку `CModule::IncludeModule('claramente.options')`

4. После установки модуля он будет доступен в разделе Контент => Параметры сайта (_/bitrix/admin/claramente_options.php?lang=ru_)

![](https://claramente.ru/upload/claramente/admin-left-menu.png)

Использование модуля
-------------------------
- Получение значения опции: `cm_option(code, site_id)`
- Установка нового значения опции: `cm_option_set(code, site_id, value)`
- Проверка существования опции: `cm_option_exists(code, site_id)`
- Проверка заполнения опции: `cm_option_filled(code, site_id)`

О модуле
-------------------------
Этот модуль упрощает работу с опциями сайта, позволяя редактировать контент и хранить важные данные, часто используемые в коде.

Аналог модуля COption из BX, но с расширенными возможностями: поддерживает удобное форматирование строк в административной панели, позволяет задавать тип данных, устанавливать ограничения и создавать новые типы.

В системе доступны несколько предустановленных типов данных:

- Строка
- Строки (множественный ввод, поддержка тысяч строк)
- Флаг (true/false)
- Дата
- Список
- Файл

В модуле существует возможность расставлять опции и сортировать их. Для переноса опций в группу необходимо создать новую вкладку. По умолчанию все опции хранятся во вкладке "Опции". После созданий новой вкладки можно создавать опции и помещать их туда, устанавливая сортировку. 

## Странице настроек параметров сайта
![](https://claramente.ru/upload/claramente/admin-main.jpg)


## Страница настроек параметра 
![](https://claramente.ru/upload/claramente/admin-option-edit.png)
