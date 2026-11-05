<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Services\OptionTypes;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use Claramente\Options\Types\StringOption;

/**
 * Тип с проверкой настроек и сохраненного значения, как у будущего типа «Число»
 */
final class ValidationTestOption extends StringOption
{
    public static ?OptionEntityStructure $settingsOption = null;

    public static function getCode(): string
    {
        return 'validation_test';
    }

    public function validateSettings(HttpRequest $request): ?string
    {
        return ($request->getPost('option')['settings']['size'] ?? '') === 'bad' ? 'Размер должен быть числом' : null;
    }

    public function validateStoredValue(OptionEntityStructure $option): ?string
    {
        return $option->value === 'old' && unserialize((string)$option->settings)['size'] === '5' ? 'значение old не помещается' : null;
    }

    public function beforeSaveSettings(HttpRequest $request, OptionEntityStructure $option): ?string
    {
        self::$settingsOption = $option;

        return parent::beforeSaveSettings($request, $option);
    }
}

/**
 * Строковая опция с готовым набором символов «Только цифры»
 */
function digitsOption(int $id, ?string $value, bool $single = true): OptionEntityStructure
{
    $option = fixture(serialize(['ready_regex' => 'number']), 'string', $value);
    $option->id = $id;
    $option->code = 'digits' . $id;
    $option->name = 'Цифры ' . $id;
    $option->singleValueForAllLang = $single;

    return $option;
}

test('validation: по умолчанию типы принимают любое значение', function (): void {
    foreach (OptionTypes::getTypes() as $code => $class) {
        if (in_array($code, ['string', 'custom', 'validation_test'], true)) {
            continue;
        }
        $type = new $class();
        assertSame(null, $type->validateValue(fixture(), 'любое <значение>'), (string)$code);
        assertSame(null, $type->validateStoredValue(fixture(null, (string)$code, 'x')), (string)$code);
        assertSame(null, $type->validateSettings(new HttpRequest()), (string)$code);
    }
});

test('validation: «Только цифры» проверяется на сервере и в cm_option_set()', function (): void {
    require_once __DIR__ . '/../../include/functions.php';
    ClaramenteOptionsTable::$options = [1 => digitsOption(1, '1')];
    assertSame(false, cm_option_set('digits1', null, '12a', $error), 'буква');
    assertSame('Недопустимые символы. Разрешено: только цифры', $error, 'текст ошибки');
    assertSame('1', ClaramenteOptionsTable::$options[1]->value, 'значение не изменилось');
    assertSame(true, cm_option_set('digits1', null, '123', $error), 'цифры');
    assertSame('123', ClaramenteOptionsTable::$options[1]->value, 'сохранено');
});

test('validation: прежнее значение, не подходящее под набор, не мешает сохранению', function (): void {
    ClaramenteOptionsTable::$options = [1 => digitsOption(1, 'abc')];
    assertSame(true, cm_option_set('digits1', null, 'abc', $error), 'значение не менялось: ' . $error);

    $multi = digitsOption(1, serialize([['value' => 'abc', 'lang' => 'ru']]), false);
    assertSame(null, (new StringOption())->validateValue($multi, [['value' => 'abc', 'lang' => 'ru'], ['value' => '5', 'lang' => 'en']], true), 'по языкам');
    assertSame(
        'Недопустимые символы. Разрешено: только цифры',
        (new StringOption())->validateValue($multi, [['value' => 'abc', 'lang' => 'ru'], ['value' => '5x', 'lang' => 'en']], true),
        'новое значение для языка проверяется'
    );
});

test('validation: пользовательское регулярное выражение на сервере не применяется', function (): void {
    $option = fixture(serialize(['ready_regex' => 'number', 'regex' => '/[^a-z]/']), 'string', '');
    assertSame(null, (new StringOption())->validateValue($option, 'АБВ 123'), 'синтаксис JS и PCRE различается');
});

test('validation: при ошибке на странице параметров не сохраняется ни одно значение', function (): void {
    ClaramenteOptionsTable::$options = [1 => digitsOption(1, '1'), 2 => digitsOption(2, '2')];
    CAdminMessage::$messages = [];
    Application::$request = new HttpRequest(['options' => [1 => ['value' => '10'], 2 => ['value' => '2x']]]);

    $redirect = runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(true));

    assertSame(null, $redirect, 'без редиректа');
    assertSame('1', ClaramenteOptionsTable::$options[1]->value, 'правильное значение тоже не сохранено');
    assertSame('2', ClaramenteOptionsTable::$options[2]->value, 'ошибочное значение не сохранено');
    assertSame(["Значения параметров не сохранены\n«Цифры 2»: Недопустимые символы. Разрешено: только цифры"], CAdminMessage::$messages, 'ошибки с названием параметра');
});

test('validation: настройки типа проверяются до сохранения параметра', function (): void {
    $option = fixture(serialize(['size' => '30']), 'validation_test', 'old');
    ClaramenteOptionsTable::$options = [1 => $option];
    CAdminMessage::$messages = [];
    Application::$request = new HttpRequest(['option' => [
        'code' => 'sample', 'name' => 'Новое имя', 'type' => 'validation_test', 'single_value_for_all_lang' => 'Y', 'settings' => ['size' => 'bad'],
    ]], ['ID' => 1]);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));
    assertSame(['Ошибка. Размер должен быть числом'], CAdminMessage::$messages, 'ошибка настроек');
    assertSame('Sample', ClaramenteOptionsTable::$options[1]->name, 'параметр не изменен');
});

test('validation: текущее значение проверяется по новым настройкам до сохранения', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture(serialize(['size' => '30']), 'validation_test', 'old')];
    CAdminMessage::$messages = [];
    Application::$request = new HttpRequest(['option' => [
        'code' => 'sample', 'name' => 'Новое имя', 'type' => 'validation_test', 'single_value_for_all_lang' => 'Y', 'settings' => ['size' => '5'],
    ]], ['ID' => 1]);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));
    assertTrue(str_contains(CAdminMessage::$messages[0] ?? '', 'Текущее значение параметра не подходит под новые настройки: значение old не помещается'), 'ошибка');
    assertSame('Sample', ClaramenteOptionsTable::$options[1]->name, 'параметр не изменен');
    assertSame(serialize(['size' => '30']), ClaramenteOptionsTable::$options[1]->settings, 'настройки не изменены');
});

test('validation: параметр сохраняется одним запросом, настройки готовятся до записи', function (): void {
    ClaramenteOptionsTable::$options = [];
    Application::$request = new HttpRequest(['option' => [
        'code' => 'fresh', 'name' => 'Новый', 'type' => 'validation_test', 'single_value_for_all_lang' => 'Y', 'settings' => ['size' => '7'], 'hint' => 'Подсказка',
    ]]);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));
    $saved = unserialize(ClaramenteOptionsTable::$options[1]->settings);
    assertSame(['7', 'Подсказка'], [$saved['size'], $saved['hint']], 'настройки и подсказка новой опции');
    assertSame(0, ValidationTestOption::$settingsOption?->id, 'новая опция: beforeSaveSettings() получает пустую структуру');

    ClaramenteOptionsTable::$options[1]->value = 'Привет';
    Application::$request = new HttpRequest(['option' => [
        'code' => 'fresh', 'name' => 'Новый', 'type' => 'validation_test', 'settings' => ['size' => '7'],
    ]], ['ID' => 1]);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));
    assertSame(serialize([['value' => 'Привет', 'lang' => LANG]]), ClaramenteOptionsTable::$options[1]->value, 'смена на значения по языкам переводит значение');
    assertSame(1, ValidationTestOption::$settingsOption?->id, 'редактирование: beforeSaveSettings() получает текущую опцию');
});

/**
 * Выбрать у строкового параметра набор «Только цифры» на странице параметра
 */
function saveDigitsSettings(OptionEntityStructure $option, array $settings = ['ready_regex' => 'number']): array
{
    ClaramenteOptionsTable::$options = [1 => $option];
    CAdminMessage::$messages = [];
    Application::$request = new HttpRequest(['option' => [
        'code' => 'sample', 'name' => 'Sample', 'type' => 'string',
        'single_value_for_all_lang' => $option->singleValueForAllLang ? 'Y' : 'N', 'settings' => $settings,
    ]], ['ID' => 1]);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));

    return CAdminMessage::$messages;
}

test('validation: набор «Только цифры» нельзя выбрать, если текущее значение ему не подходит', function (): void {
    $errors = saveDigitsSettings(fixture(null, 'string', 'abc'));
    assertTrue(str_contains($errors[0] ?? '', 'Текущее значение параметра не подходит под новые настройки: Недопустимые символы. Разрешено: только цифры'), 'ошибка');
    assertSame(null, ClaramenteOptionsTable::$options[1]->settings, 'набор не сохранен');

    assertSame([], saveDigitsSettings(fixture(null, 'string', '123')), 'цифры подходят');
    assertSame('number', unserialize(ClaramenteOptionsTable::$options[1]->settings)['ready_regex'], 'набор сохранен');

    assertSame([], saveDigitsSettings(fixture(null, 'string', null)), 'пустое значение подходит');
});

test('validation: при выборе набора проверяются значения всех языков', function (): void {
    $option = fixture(null, 'string', serialize([['value' => '1', 'lang' => 'ru'], ['value' => 'one', 'lang' => 'en']]));
    $option->singleValueForAllLang = false;
    $errors = saveDigitsSettings($option);
    assertTrue(str_contains($errors[0] ?? '', 'Разрешено: только цифры'), 'значение en не подходит');
});

test('validation: с пользовательским регулярным выражением сохраненное значение не проверяется', function (): void {
    assertSame([], saveDigitsSettings(fixture(null, 'string', 'abc'), ['ready_regex' => 'number', 'regex' => '/[^0-9]/']), 'проверка только в браузере');
});
