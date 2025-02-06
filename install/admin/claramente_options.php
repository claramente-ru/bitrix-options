<?php

if (is_file($_SERVER['DOCUMENT_ROOT'] . '/local/modules/claramente.options/admin/options.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/claramente.options/admin/options.php';
} else {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/claramente.options/admin/options.php';
}
