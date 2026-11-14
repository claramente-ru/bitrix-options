<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Structures\Entity\TabEntityStructure;

/**
 * Сохранить вкладки со страницы параметров администратором. Возвращает сообщения и адрес редиректа
 */
function saveTabs(array $tabs): array
{
    ClaramenteOptionsTable::$options = [];
    ClaramenteOptionTabsTable::$tabs = [
        1 => new TabEntityStructure(1, 'Контакты', 'contacts', 100),
        2 => new TabEntityStructure(2, 'Шапка', 'header', 200),
    ];
    CAdminMessage::$messages = [];
    Application::$request = new HttpRequest(['tabs' => $tabs]);
    $redirect = runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(true));

    return ['messages' => CAdminMessage::$messages, 'redirect' => $redirect];
}

test('tabs: смена кода вкладки на свободный сохраняется без ошибки', function (): void {
    $result = saveTabs([1 => ['name' => 'Контакты', 'code' => 'phones', 'sort' => 100]]);
    assertSame([], $result['messages'], 'нет сообщения «уже существует»');
    assertTrue(null !== $result['redirect'], 'редирект после сохранения');
    assertSame('phones', ClaramenteOptionTabsTable::$tabs[1]->code, 'код изменен');
});

test('tabs: код, занятый другой вкладкой, не сохраняется', function (): void {
    $result = saveTabs([1 => ['name' => 'Контакты', 'code' => 'header', 'sort' => 100]]);
    assertSame(['Ошибка. Вкладка с кодом header уже существует'], $result['messages'], 'ошибка');
    assertSame(null, $result['redirect'], 'без редиректа');
    assertSame('contacts', ClaramenteOptionTabsTable::$tabs[1]->code, 'код не изменен');
});

test('tabs: вкладки без изменений и с прежним кодом сохраняются', function (): void {
    $result = saveTabs([
        1 => ['name' => 'Контакты 2', 'code' => 'contacts', 'sort' => 100],
        2 => ['name' => 'Шапка', 'code' => 'header', 'sort' => 200],
    ]);
    assertSame([], $result['messages'], 'без ошибок');
    assertSame('Контакты 2', ClaramenteOptionTabsTable::$tabs[1]->name, 'название изменено');
});

test('tabs: ошибка сохранения вкладки показывается', function (): void {
    $result = saveTabs([1 => ['name' => 'Контакты', 'code' => '', 'sort' => 100]]);
    assertSame(['Ошибка. Не заполнено обязательное поле «CODE»'], $result['messages'], 'ошибка ORM');
    assertSame(null, $result['redirect'], 'без редиректа');
    assertSame('contacts', ClaramenteOptionTabsTable::$tabs[1]->code, 'код не изменен');
});
