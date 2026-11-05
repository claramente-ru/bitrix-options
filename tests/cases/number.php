<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Services\OptionTypes;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use Claramente\Options\Types\NumberOption;

/**
 * Опция типа «Число» с заданной точностью
 */
function numberOption(?string $value = null, int $scale = 2, bool $single = true): OptionEntityStructure
{
    $option = fixture(serialize(['scale' => $scale, 'size' => 20]), 'number', $value);
    $option->code = 'price';
    $option->name = 'Цена';
    $option->singleValueForAllLang = $single;

    return $option;
}

test('number: тип зарегистрирован', function (): void {
    assertSame(NumberOption::class, OptionTypes::getTypes()['number'] ?? null, 'number');
    assertSame('Число', NumberOption::getName(), 'название');
});

test('number: допустимые значения нормализуются', function (): void {
    $cases = [
        '12' => '12', '12.5' => '12.5', '12,5' => '12.5', '12,50' => '12.5', '007' => '7', '0.0' => '0',
        '0,00' => '0', '3.25' => '3.25', '.5' => '0.5', '5.' => '5', ',5' => '0.5',
        ' 12.5 ' => '12.5', '000.100' => '0.1',
    ];
    foreach ($cases as $input => $expected) {
        $input = (string)$input;
        assertSame(null, (new NumberOption())->validateValue(numberOption(), $input), "проверка: '$input'");
        assertSame($expected, NumberOption::normalize($input), "нормализация: '$input'");
    }
});

test('number: любое значение, которое не является числом, дает одну ошибку', function (): void {
    $cases = [
        '12.5.3', '12,5.3', '1 000', "1\u{00A0}000", '1e3', '1E3', 'abc', '12a', '-', '-5', '-0', '+3',
        '.', ',', '--1', '5-', '1/2', '0x1A', '12 5', '1_000', "\u{00A0}12",
    ];
    foreach ($cases as $input) {
        assertSame('Значение должно быть числом', (new NumberOption())->validateValue(numberOption(), $input), "'$input'");
        assertSame(null, NumberOption::normalize($input), "не нормализуется: '$input'");
    }
});

test('number: точность проверяется без округления', function (): void {
    $type = new NumberOption();
    assertSame(null, $type->validateValue(numberOption(null, 2), '12.55'), '2 знака при точности 2');
    assertSame(null, $type->validateValue(numberOption(null, 1), '12.50'), 'незначащий ноль не считается');
    assertSame(
        'Допускается не более 2 зн. после разделителя, округление не выполняется',
        $type->validateValue(numberOption(null, 2), '12,555'),
        '3 знака при точности 2'
    );
    assertSame('Допускаются только целые числа', $type->validateValue(numberOption(null, 0), '12.5'), 'точность 0');
    assertSame(null, $type->validateValue(numberOption(null, 0), '12'), 'целое при точности 0');
    assertSame(null, $type->validateValue(numberOption(null, 10), '0.0000000001'), 'максимальная точность');
});

test('number: не более 15 значащих цифр', function (): void {
    $type = new NumberOption();
    assertSame(null, $type->validateValue(numberOption(), str_repeat('9', NumberOption::MAX_DIGITS)), '15 цифр целой части');
    assertSame(null, $type->validateValue(numberOption(null, 10), '12345.6789012345'), '15 цифр вместе с дробной частью');
    assertSame('Допускается не более 15 значащих цифр', $type->validateValue(numberOption(), str_repeat('9', 16)), '16 цифр');
    assertSame('Допускается не более 15 значащих цифр', $type->validateValue(numberOption(null, 10), '123456.6789012345'), '16 цифр вместе');
    assertSame(null, $type->validateValue(numberOption(), '000000000000000000001'), 'ведущие нули не считаются');
    assertSame(null, $type->validateValue(numberOption(null, 10), '0.0000000001'), 'нули после разделителя до первой цифры не считаются');
});

test('number: значения из кода — int, float, bool и массивы', function (): void {
    $type = new NumberOption();
    assertSame(null, $type->validateValue(numberOption(), 12), 'int');
    assertSame(null, $type->validateValue(numberOption(), 12.5), 'float');
    assertTrue(null !== $type->validateValue(numberOption(), 0.1 + 0.2), 'float 0.30000000000000004 не округляется молча');
    assertSame('Значение должно быть числом', $type->validateValue(numberOption(), true), 'bool');
    assertTrue($type->validateValue(numberOption(), -5) === 'Значение должно быть числом', 'отрицательный int');
    assertTrue($type->validateValue(numberOption(), -0.5) === 'Значение должно быть числом', 'отрицательный float');
    assertSame(null, $type->validateValue(numberOption(), ['value' => '1,5']), 'значение из формы');
    assertSame(null, $type->validateValue(numberOption(), ''), 'пустое значение очищает параметр');
    assertSame(null, $type->validateValue(numberOption(), null), 'null очищает параметр');
});

test('number: сохранение и чтение через cm_option_set() и cm_option()', function (): void {
    require_once __DIR__ . '/../../include/functions.php';
    ClaramenteOptionsTable::$options = [1 => numberOption()];

    assertSame(true, cm_option_set('price', null, '0012,50', $error), 'сохранено: ' . $error);
    assertSame('12.5', ClaramenteOptionsTable::$options[1]->value, 'в базе точка и без лишних нулей');
    assertSame(12.5, cm_option('price'), 'дробное — float');

    cm_option_set('price', null, '42');
    assertSame(42, cm_option('price'), 'целое — int');

    assertSame(false, cm_option_set('price', null, '1,234', $error), 'лишний знак');
    assertSame('Допускается не более 2 зн. после разделителя, округление не выполняется', $error, 'текст ошибки');
    assertSame('42', ClaramenteOptionsTable::$options[1]->value, 'значение не изменилось');

    assertSame(true, cm_option_set('price', null, ''), 'очистка');
    assertSame(null, ClaramenteOptionsTable::$options[1]->value, 'пустое значение хранится как NULL');
    assertSame(null, cm_option('price'), 'пустое значение — null');
    assertSame(false, cm_option_filled('price'), 'не заполнено');

    cm_option_set('price', null, '0');
    assertSame(0, cm_option('price'), 'ноль');
    assertSame(true, cm_option_filled('price'), '"0" заполнено');
});

test('number: float не искажает значения из 15 значащих цифр', function (): void {
    ClaramenteOptionsTable::$options = [1 => numberOption(null, 10)];
    foreach (['12345.6789012345', '0.1', '0.0000000001', '999999999999999', '0.3', '12.55'] as $value) {
        assertSame(true, cm_option_set('price', null, $value, $error), "$value: $error");
        // При выводе с точностью опции float дает ровно сохраненное значение
        assertSame($value, NumberOption::normalize(sprintf('%.10F', cm_option('price'))), "без искажений: $value");
    }
});

test('number: значения по языкам', function (): void {
    $option = numberOption(null, 1, false);
    ClaramenteOptionsTable::$options = [1 => $option];
    $value = [['value' => '1,50', 'lang' => 'ru'], ['value' => '', 'lang' => 'de'], ['value' => '00', 'lang' => 'en']];
    assertSame(true, cm_option_set('price', null, $value, $error), 'сохранено: ' . $error);
    assertSame(
        serialize([['value' => '1.5', 'lang' => 'ru'], ['value' => '0', 'lang' => 'en']]),
        ClaramenteOptionsTable::$options[1]->value,
        'нормализованы, пустые убраны'
    );
    assertSame(1.5, cm_option('price'), 'значение текущего языка');
    assertSame(
        [['value' => 1.5, 'lang' => 'ru'], ['value' => 0, 'lang' => 'en']],
        ClaramenteOptionsTable::$options[1]->getValuesForLangs(),
        'значения всех языков числами'
    );

    $error = (new NumberOption())->validateValue($option, [['value' => '1', 'lang' => 'ru'], ['value' => '1.25', 'lang' => 'en']], true);
    assertSame('Допускается не более 1 зн. после разделителя, округление не выполняется (язык en)', $error, 'ошибка с языком');
});

test('number: страница параметров не сохраняет недопустимое число', function (): void {
    ClaramenteOptionsTable::$options = [1 => numberOption('5')];
    CAdminMessage::$messages = [];
    Application::$request = new HttpRequest(['options' => [1 => ['value' => '12,555']]]);
    $redirect = runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(false));
    assertSame(null, $redirect, 'без редиректа');
    assertSame('5', ClaramenteOptionsTable::$options[1]->value, 'значение не изменилось');
    assertSame(["Значения параметров не сохранены\n«Цена»: Допускается не более 2 зн. после разделителя, округление не выполняется"], CAdminMessage::$messages, 'ошибка');

    Application::$request = new HttpRequest(['options' => [1 => ['value' => '12,5']]]);
    assertTrue(null !== runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(false)), 'допустимое значение сохраняется');
    assertSame('12.5', ClaramenteOptionsTable::$options[1]->value, 'нормализовано');
});

test('number: поле ввода и подпись с правилами', function (): void {
    $option = numberOption('"><script>');
    $form = new CAdminForm();
    ob_start();
    (new NumberOption())->getFormFieldValue('options[1]', $form, $option);
    $html = ob_get_clean();
    assertTrue(str_contains($html, 'name="options[1][value]"'), 'имя поля');
    assertTrue(str_contains($html, 'value="&quot;&gt;&lt;script&gt;"'), 'значение экранировано');
    assertTrue(str_contains($html, 'inputmode="decimal"'), 'цифровая клавиатура на телефоне');
    assertTrue(str_contains($html, 'Число, не более 2 зн. после запятой или точки'), 'подпись');
    assertSame('Целое число', (new NumberOption())->getRulesDescription(numberOption(null, 0)), 'подпись для точности 0');

    $row = (new NumberOption())->getOptionLangRow('options[1][0][value]', 'options[1][0][lang]', ['value' => '', 'lang' => '']);
    assertTrue(str_contains($row, 'name="options[1][0][value]"'), 'строка для языка (ajax-добавление)');
});

/**
 * Сохранить настройки параметра типа «Число»
 */
function saveNumberSettings(?OptionEntityStructure $current, array $settings, string $single = 'Y'): array
{
    ClaramenteOptionsTable::$options = $current ? [1 => $current] : [];
    CAdminMessage::$messages = [];
    Application::$request = new HttpRequest(['option' => [
        'code' => 'price', 'name' => 'Цена', 'type' => 'number', 'single_value_for_all_lang' => $single, 'settings' => $settings,
    ]], $current ? ['ID' => 1] : []);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));

    return CAdminMessage::$messages;
}

test('number: точность проверяется и сохраняется в настройках', function (): void {
    foreach (['abc', '-1', '11', '1.5', '100'] as $scale) {
        assertSame(['Ошибка. Точность должна быть целым числом от 0 до 10'], saveNumberSettings(null, ['scale' => $scale]), "точность '$scale'");
    }
    assertSame([], saveNumberSettings(null, ['scale' => '3', 'size' => '10']), 'новый параметр');
    assertSame(['scale' => 3, 'size' => 10], unserialize(ClaramenteOptionsTable::$options[1]->settings), 'настройки');
    assertSame([], saveNumberSettings(null, ['scale' => '', 'size' => '']), 'пустые настройки');
    assertSame(['scale' => 2, 'size' => 20], unserialize(ClaramenteOptionsTable::$options[1]->settings), 'значения по умолчанию');
});

test('number: точность нельзя уменьшить, если текущее значение не подходит', function (): void {
    $errors = saveNumberSettings(numberOption('12.55', 2), ['scale' => '1']);
    assertTrue(str_contains($errors[0] ?? '', 'Текущее значение параметра не подходит под новые настройки: Допускается не более 1 зн.'), 'ошибка');
    assertSame(2, unserialize(ClaramenteOptionsTable::$options[1]->settings)['scale'], 'точность не изменилась');

    assertSame([], saveNumberSettings(numberOption('12.5', 2), ['scale' => '1']), 'значение подходит');
    assertSame([], saveNumberSettings(numberOption('12.55', 2), ['scale' => '3']), 'точность увеличена');

    $multi = numberOption(serialize([['value' => '1', 'lang' => 'ru'], ['value' => '1.5', 'lang' => 'en']]), 1, false);
    assertTrue(str_contains(saveNumberSettings($multi, ['scale' => '0'], 'N')[0] ?? '', 'Допускаются только целые числа (язык en)'), 'значения по языкам');
});

test('number: строку нельзя сменить на число, если значение не число', function (): void {
    $errors = saveNumberSettings(fixture(null, 'string', 'много'), ['scale' => '2']);
    assertTrue(str_contains($errors[0] ?? '', 'Значение должно быть числом'), 'ошибка');
    assertSame('string', ClaramenteOptionsTable::$options[1]->type, 'тип не изменился');

    assertSame([], saveNumberSettings(fixture(null, 'string', '12,5'), ['scale' => '2']), 'строка с числом');
    assertSame('number', ClaramenteOptionsTable::$options[1]->type, 'тип изменен');
    assertSame('12,5', ClaramenteOptionsTable::$options[1]->value, 'значение не переписывается при смене типа');
    assertSame(12.5, cm_option('price'), 'но читается числом');
});

test('number: миграция переносит значение и настройки без изменений', function (): void {
    $settings = serialize(['scale' => 3, 'size' => 20]);
    $migration = renderMigration([
        'TAB_ID' => null, 'NAME' => 'Цена', 'CODE' => 'price', 'TYPE' => 'number', 'SITE_ID' => null,
        'VALUE' => '12345678901.125', 'SETTINGS' => $settings, 'SORT' => 100, 'SINGLE_VALUE_FOR_ALL_LANG' => 1,
    ])['migration'];
    assertSame('12345678901.125', prop($migration, 'optionValue'), 'значение строкой, без float');
    assertSame($settings, prop($migration, 'optionSettings'), 'настройки');
    assertSame('number', prop($migration, 'optionType'), 'тип');
});
