<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

ini_set('zend.exception_ignore_args', 0);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global $APPLICATION CMain */
global $APPLICATION;

$APPLICATION->SetTitle('Параметры сайта');
$request = Application::getInstance()->getContext()->getRequest();

if (! Loader::includeModule('claramente.options')) {
    throw new Exception('Необходимо установить модуль claramente.options');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

// Страница вкладки
$pagePath = match ($request->get('page')) {
    // Страница редактирования опции
    'option' => 'pages/option.php',
    'about' => 'pages/about.php',
    // Главная страница
    default => 'pages/main.php',
};

require_once $pagePath;


require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
