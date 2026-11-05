<?php

declare(strict_types=1);

use Bitrix\Main\HttpRequest;
use Claramente\Options\Admin\AdminForm;
use Claramente\Options\Entity\ClaramenteOptionsTable;

test('misc: cm_option_filled() считает "0" заполненным значением', function (): void {
    require_once __DIR__ . '/../../include/functions.php';
    foreach (['0' => true, 'текст' => true, '' => false] as $value => $filled) {
        ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', (string)$value)];
        assertSame($filled, cm_option_filled('sample'), var_export((string)$value, true));
    }
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', null)];
    assertSame(false, cm_option_filled('sample'), 'null');
    assertSame(false, cm_option_filled('missing'), 'опции нет');
});

test('misc: у новой опции нет кнопки «Создать миграцию»', function (): void {
    CModule::$modules = ['sprint.migration'];
    try {
        ClaramenteOptionsTable::$options = [];
        runAdminScript(__DIR__ . '/../../admin/pages/option.php', fakeUser(true), ['request' => new HttpRequest()]);
        assertTrue(! str_contains(AdminForm::$form->buttons, 'migrate=Y'), 'новая опция: кнопки нет, без обращения к $option->id у null');

        ClaramenteOptionsTable::$options = [1 => fixture()];
        runAdminScript(__DIR__ . '/../../admin/pages/option.php', fakeUser(true), ['request' => new HttpRequest([], ['ID' => '1'])]);
        assertTrue(str_contains(AdminForm::$form->buttons, 'ID=1&migrate=Y'), 'сохраненная опция: кнопка есть');
    } finally {
        CModule::$modules = [];
    }
});
