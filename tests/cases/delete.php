<?php

declare(strict_types=1);

use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;

/**
 * Опция типа «Файл»: один файл или файлы по языкам
 */
function fileOption(bool $single): void
{
    $option = fixture(null, 'file', $single ? '15' : serialize([
        ['value' => '21', 'lang' => 'ru'],
        ['value' => '22', 'lang' => 'en'],
    ]));
    $option->singleValueForAllLang = $single;
    ClaramenteOptionsTable::$options = [1 => $option];
    CFile::$deleted = [];
}

test('delete: cm_option_delete() удаляет файл опции', function (): void {
    require_once __DIR__ . '/../../include/functions.php';
    fileOption(true);
    assertTrue(cm_option_delete('sample'), 'опция удалена');
    assertSame([15], CFile::$deleted, 'файл удален');
});

test('delete: cm_option_delete() удаляет файлы всех языков', function (): void {
    fileOption(false);
    assertTrue(cm_option_delete('sample'), 'опция удалена');
    assertSame([21, 22], CFile::$deleted, 'файлы ru и en, а не только текущего языка');
});

test('delete: удаление на странице параметра удаляет файлы', function (): void {
    fileOption(false);
    runAdminScript(__DIR__ . '/../../admin/pages/option.php', fakeUser(true), [
        'request' => new HttpRequest([], ['ID' => '1', 'delete' => 'Y']),
    ]);
    assertSame([], ClaramenteOptionsTable::$options, 'опция удалена');
    assertSame([21, 22], CFile::$deleted, 'файлы удалены');
});

test('delete: опции других типов удаляются без удаления файлов', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', '15')];
    CFile::$deleted = [];
    assertTrue(cm_option_delete('sample'), 'опция удалена');
    assertSame([], CFile::$deleted, 'файлы не трогаются');
});

test('delete: если опция не удалилась, файлы остаются', function (): void {
    fileOption(false);
    ClaramenteOptionsTable::$failNext = 'Ошибка БД';
    assertSame(false, cm_option_delete('sample'), 'cm_option_delete() сообщает о неудаче');
    assertSame([], CFile::$deleted, 'файлы на месте: опция по-прежнему на них ссылается');

    ClaramenteOptionsTable::$failNext = 'Ошибка БД';
    CAdminMessage::$messages = [];
    $redirect = runAdminScript(__DIR__ . '/../../admin/pages/option.php', fakeUser(true), [
        'request' => new HttpRequest([], ['ID' => '1', 'delete' => 'Y']),
    ]);
    assertSame(null, $redirect, 'без редиректа');
    assertSame('Ошибка удаления. Ошибка БД', CAdminMessage::$messages[0] ?? null, 'сообщение об ошибке');
    assertTrue(isset(ClaramenteOptionsTable::$options[1]), 'опция осталась');
    assertSame([], CFile::$deleted, 'файлы на месте');
});
