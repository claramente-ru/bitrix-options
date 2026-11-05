<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;

/**
 * Время кэширования запросов, выполненных в $fn
 */
function cacheTtls(callable $fn): array
{
    DataManager::$queries = [];
    $fn(realOptionsTable());

    return array_column(DataManager::$queries, 'ttl');
}

// Константы глобальные и не отменяются, поэтому порядок проверок важен:
// сначала без CLARAMENTE_OPTIONS_CACHE_TIME, затем с ней, затем предзагрузчик
test('cache: без глобальной константы кэшируются только запросы с явно заданным временем', function (): void {
    DataManager::$rows = [1 => optionRow(1, 'phone', null, '1')];
    assertSame([0], cacheTtls(static fn(string $table) => $table::getByCode('phone')), 'время не передано');
    assertSame([0], cacheTtls(static fn(string $table) => $table::getByCode('phone', null, 0)), 'время 0, как в cm_option() по умолчанию');
    assertSame([60], cacheTtls(static fn(string $table) => $table::getByCode('phone', null, 60)), 'getByCode()');
    assertSame([60], cacheTtls(static fn(string $table) => $table::getOptionById(1, 60)), 'getOptionById()');
    assertSame([60], cacheTtls(static fn(string $table) => $table::getByTabId(1, null, 60)), 'getByTabId()');
});

test('cache: глобальная константа применяется по умолчанию, явное время приоритетнее', function (): void {
    define('CLARAMENTE_OPTIONS_CACHE_TIME', 3600);
    DataManager::$rows = [1 => optionRow(1, 'phone', null, '1')];
    assertSame([3600], cacheTtls(static fn(string $table) => $table::getByCode('phone')), 'время не передано');
    assertSame([3600], cacheTtls(static fn(string $table) => $table::getByCode('phone', null, 0)), 'время 0, как в cm_option() по умолчанию');
    assertSame([3600], cacheTtls(static fn(string $table) => $table::getOptionById(1)), 'getOptionById() без времени');
    assertSame([3600], cacheTtls(static fn(string $table) => $table::getByTabId(1)), 'getByTabId() без времени');
    assertSame([60], cacheTtls(static fn(string $table) => $table::getByCode('phone', null, 60)), 'getByCode()');
    assertSame([60], cacheTtls(static fn(string $table) => $table::getOptionById(1, 60)), 'getOptionById()');
    assertSame([60], cacheTtls(static fn(string $table) => $table::getByTabId(1, null, 60)), 'getByTabId()');
});

test('cache: предзагрузчик не путает код и сайт (foo + s1 и foos1 без сайта)', function (): void {
    define('CLARAMENTE_OPTIONS_PRELOADER', true);
    $table = realOptionsTable();
    DataManager::$rows = [
        1 => optionRow(1, 'foo', 's1', 'для сайта s1'),
        2 => optionRow(2, 'foos1', null, 'для всех сайтов'),
    ];
    $table::clearPreloader();
    DataManager::$queries = [];
    assertSame('для сайта s1', $table::getByCode('foo', 's1')?->value, 'foo + s1');
    assertSame('для всех сайтов', $table::getByCode('foos1')?->value, 'foos1 без сайта');
    assertSame(null, $table::getByCode('foo')?->value, 'foo без сайта отсутствует');
    assertSame(1, count(array_filter(DataManager::$queries, static fn(array $query): bool => $query['filter'] === [])), 'все опции загружены одним запросом');
});

test('cache: после сброса предзагрузчик читает актуальные данные', function (): void {
    $table = realOptionsTable();
    DataManager::$rows = [1 => optionRow(1, 'phone', null, 'old')];
    $table::clearPreloader();
    assertSame('old', $table::getByCode('phone')?->value, 'первое чтение');
    DataManager::$rows[1]['VALUE'] = 'new';
    assertSame('old', $table::getOptionById(1)?->value, 'без сброса в рамках хита данные из предзагрузчика');
    $table::clearPreloader();
    assertSame('new', $table::getByCode('phone')?->value, 'getByCode() после сброса');
    assertSame('new', $table::getOptionById(1)?->value, 'getOptionById() после сброса');
});

test('cache: предзагрузчик сбрасывается после изменения и удаления опций', function (): void {
    require_once __DIR__ . '/../../include/functions.php';

    ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', 'old')];
    ClaramenteOptionsTable::$preloaderResets = 0;
    assertTrue(ClaramenteOptionsTable::getOptionById(1)->setValue('new'), 'setValue()');
    assertSame(1, ClaramenteOptionsTable::$preloaderResets, 'setValue(), а значит cm_option_set() и сохранение значений на странице параметров');

    ClaramenteOptionsTable::$preloaderResets = 0;
    Application::$request = new HttpRequest(['option' => ['code' => 'sample', 'name' => 'Sample', 'type' => 'string']], ['ID' => 1]);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));
    assertTrue(ClaramenteOptionsTable::$preloaderResets > 0, 'сохранение параметра');

    ClaramenteOptionsTable::$preloaderResets = 0;
    assertTrue(cm_option_delete('sample'), 'cm_option_delete()');
    assertSame([], ClaramenteOptionsTable::$options, 'опция удалена');
    assertSame(1, ClaramenteOptionsTable::$preloaderResets, 'cm_option_delete()');
});
