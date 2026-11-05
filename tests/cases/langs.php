<?php

declare(strict_types=1);

use Bitrix\Main\Entity\DataManager;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Types\StringOption;

function langsOption(?string $value): void
{
    $option = fixture(null, 'string', $value);
    $option->singleValueForAllLang = false;
    ClaramenteOptionsTable::$options = [1 => $option];
}

test('langs: строка вместо массива для опции по языкам не сохраняется', function (): void {
    require_once __DIR__ . '/../../include/functions.php';
    $stored = serialize([['value' => 'Привет', 'lang' => 'ru'], ['value' => 'Hello', 'lang' => 'en']]);
    langsOption($stored);

    assertSame(false, cm_option_set('sample', null, '123', $error), 'cm_option_set()');
    assertTrue(str_contains((string)$error, 'хранит значения по языкам') && str_contains((string)$error, 'string'), 'текст ошибки: ' . $error);
    assertSame($stored, ClaramenteOptionsTable::$options[1]->value, 'значение не изменилось');

    assertSame(true, cm_option_set('sample', null, [['value' => 'Пока', 'lang' => 'ru']], $error), 'массив по языкам');
    assertSame(null, $error, 'без ошибки');
    assertSame(serialize([['value' => 'Пока', 'lang' => 'ru']]), ClaramenteOptionsTable::$options[1]->value, 'сохранено');

    assertSame(true, cm_option_set('sample', null, null, $error), 'null очищает значение');
    assertSame(null, ClaramenteOptionsTable::$options[1]->value, 'очищено без сериализации null');
});

test('langs: cm_option_set() сообщает, что опция не найдена или тип неизвестен', function (): void {
    ClaramenteOptionsTable::$options = [];
    assertSame(false, cm_option_set('missing', null, 'x', $error), 'нет опции');
    assertSame('Опция missing не найдена', $error, 'текст ошибки');

    ClaramenteOptionsTable::$options = [1 => fixture(null, 'unknown', 'old')];
    assertSame(false, cm_option_set('sample', null, 'x', $error), 'неизвестный тип');
    assertTrue(str_contains((string)$error, 'тип данных unknown'), 'текст ошибки: ' . $error);
    assertSame('old', ClaramenteOptionsTable::$options[1]->value, 'значение не изменилось');
    assertSame([], ClaramenteOptionsTable::$options[1]->getValuesForLangs(), 'getValuesForLangs() для неизвестного типа');
});

test('langs: пустая строка языка не отбрасывает заполненное значение этого языка', function (): void {
    $option = fixture();
    $saved = (new StringOption())->getValueBeforeSave($option, [
        ['value' => '', 'lang' => 'ru'],
        ['value' => 'Привет', 'lang' => 'ru'],
        ['value' => 'Hello', 'lang' => 'en'],
        ['value' => 'Hi', 'lang' => 'en'],
    ], true);
    assertSame(
        serialize([['value' => 'Привет', 'lang' => 'ru'], ['value' => 'Hello', 'lang' => 'en']]),
        $saved,
        'пустые убраны, для повторного языка остается первое заполненное'
    );
});

test('langs: поврежденное значение по языкам читается как пустое', function (): void {
    $table = realOptionsTable();
    foreach (['123', 'N;', serialize('строка'), serialize([['value' => ['массив'], 'lang' => 'ru']])] as $broken) {
        $row = optionRow(1, 'phone', null, $broken);
        $row['SINGLE_VALUE_FOR_ALL_LANG'] = false;
        DataManager::$rows = [1 => $row];
        $table::clearPreloader();
        assertSame('', $table::getByCode('phone')?->value, var_export($broken, true));
    }

    $row['VALUE'] = serialize([['value' => 'Hello', 'lang' => 'en'], ['value' => 'Привет', 'lang' => 'ru']]);
    DataManager::$rows = [1 => $row];
    $table::clearPreloader();
    assertSame('Привет', $table::getByCode('phone')?->value, 'значение текущего языка');
    assertSame($row['VALUE'], $table::getByCode('phone', null, null, true)?->value, 'для всех языков без изменений');
});
