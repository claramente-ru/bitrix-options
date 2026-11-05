<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;

/**
 * Выполнить действие с недействительным токеном сессии
 */
function withoutSessid(callable $fn): void
{
    $GLOBALS['TEST_SESSID_VALID'] = false;
    try {
        $fn();
    } finally {
        $GLOBALS['TEST_SESSID_VALID'] = true;
    }
}

test('csrf: сохранение параметра без токена сессии ничего не меняет', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture()];
    Application::$request = new HttpRequest(['option' => ['code' => 'changed', 'name' => 'Changed', 'type' => 'string', 'hint' => 'x']], ['ID' => 1]);
    CAdminMessage::$messages = [];

    withoutSessid(static fn() => runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true)));

    assertTrue(str_contains(CAdminMessage::$messages[0] ?? '', 'Сессия истекла'), 'сообщение об ошибке');
    assertSame('sample', ClaramenteOptionsTable::$options[1]->code, 'опция не изменилась');
});

test('csrf: сохранение значений без токена сессии ничего не меняет', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', 'old')];
    Application::$request = new HttpRequest(['options' => [1 => ['value' => 'new']]]);
    CAdminMessage::$messages = [];

    withoutSessid(static fn() => runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(true)));

    assertTrue(str_contains(CAdminMessage::$messages[0] ?? '', 'Сессия истекла'), 'сообщение об ошибке');
    assertSame('old', ClaramenteOptionsTable::$options[1]->value, 'значение не изменилось');
});

test('csrf: сохранение значений с токеном сессии работает', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', 'old')];
    Application::$request = new HttpRequest(['options' => [1 => ['value' => 'new']]]);

    $redirect = runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(true));

    assertTrue(null !== $redirect, 'редирект после сохранения');
    assertSame('new', ClaramenteOptionsTable::$options[1]->value, 'значение сохранено');
});

test('csrf: удаление опции только по ссылке с токеном сессии', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture()];
    CAdminMessage::$messages = [];
    $request = new HttpRequest([], ['ID' => '1', 'delete' => 'Y']);

    withoutSessid(static fn() => runAdminScript(__DIR__ . '/../../admin/pages/option.php', fakeUser(true), ['request' => $request]));
    assertTrue(isset(ClaramenteOptionsTable::$options[1]), 'без токена опция не удалена');
    assertTrue(str_contains(CAdminMessage::$messages[0] ?? '', 'Сессия истекла'), 'сообщение об ошибке');

    $redirect = runAdminScript(__DIR__ . '/../../admin/pages/option.php', fakeUser(true), ['request' => $request]);
    assertTrue(null !== $redirect, 'редирект после удаления');
    assertTrue(! isset(ClaramenteOptionsTable::$options[1]), 'с токеном опция удалена');
});

test('csrf: формы и ссылки действий содержат токен сессии', function (): void {
    $source = file_get_contents(__DIR__ . '/../../admin/pages/option.php');
    assertTrue(str_contains($source, "&delete=Y&' . bitrix_sessid_get()"), 'ссылка удаления');
    assertTrue(str_contains($source, "&migrate=Y&' . bitrix_sessid_get()"), 'ссылка миграции');
    foreach (['main.php', 'option.php'] as $page) {
        assertTrue(str_contains(file_get_contents(__DIR__ . '/../../admin/pages/' . $page), 'echo bitrix_sessid_post();'), "токен в форме: $page");
    }
});
