<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\SiteTable;
use Claramente\Options\Types\SelectOption;
use Claramente\Options\Types\StringOption;
use Claramente\Options\Types\StringsOption;
use Claramente\Options\Types\TextOption;
use Claramente\Options\Validator\RequestAddRowValidator;

test('security: название опции и сайта выводятся в собственных строках формы текстом', function (): void {
    SiteTable::$sites = [['LID' => 's1', 'NAME' => '<img src=x onerror=alert1>Сайт']];
    $option = fixture(null, 'text');
    $option->name = '<script>alert(1)</script>Название';
    $option->siteId = 's1';
    $type = new TextOption();

    // В поля CAdminForm::Add*Field подпись передается как есть: ее экранирует Bitrix (GetCustomLabelHTML)
    $label = (new ReflectionMethod(TextOption::class, 'getOptionLabel'))->invoke($type, $option);
    assertTrue(str_contains($label, '<script>alert(1)</script>Название'), 'подпись для Add*Field не экранируется повторно');

    // В собственных строках модуль выводит подпись сам и экранирует ее
    $header = (new ReflectionMethod(TextOption::class, 'getFieldHeader'))->invoke($type, $option);
    assertTrue(! str_contains($header, '<script>') && ! str_contains($header, '<img'), 'нет исполняемого HTML: ' . $header);
    assertTrue(str_contains($header, '&lt;script&gt;alert(1)&lt;/script&gt;Название'), 'название текстом');
    assertTrue(str_contains($header, '&lt;img src=x onerror=alert1&gt;Сайт'), 'название сайта текстом');
});

test('security: регулярное выражение строки передается в браузер как данные', function (): void {
    $type = new StringOption();
    $attribute = (new ReflectionMethod(StringOption::class, 'getClientFilterAttribute'))
        ->invoke($type, fixture(serialize(['regex' => '/a/,alert(document.cookie),/"onmouseover="x/i'])));

    assertTrue(str_starts_with($attribute, ' onkeyup="'), 'атрибут onkeyup');
    assertSame(2, substr_count($attribute, '"'), 'кавычки из настройки не закрывают атрибут');
    assertTrue(str_contains($attribute, 'new RegExp('), 'регулярка строится из строки');
    assertTrue(! str_contains($attribute, 'replace(/a/,alert'), 'текст настройки не вставлен как код');

    $ready = (new ReflectionMethod(StringOption::class, 'getClientFilterAttribute'))
        ->invoke($type, fixture(serialize(['ready_regex' => 'number'])));
    assertTrue(str_contains(html_entity_decode($ready), 'new RegExp("[^0-9]","g")'), 'готовый набор символов: ' . $ready);
    assertSame('', (new ReflectionMethod(StringOption::class, 'getClientFilterAttribute'))->invoke($type, fixture()), 'без настройки атрибута нет');
});

test('security: настройки и значения не восстанавливают объекты', function (): void {
    $payload = serialize(['values' => [new ArrayObject(['x'])], 'size' => 10]);
    $settings = (new StringOption())->getSettings(fixture($payload));
    assertTrue(! ($settings['values'][0] ?? null) instanceof ArrayObject, 'класс из настроек не восстановлен');
    assertSame([], (new StringOption())->getSettings(fixture(serialize('строка'))), 'не массив — пустые настройки');
    assertSame([], (new StringOption())->getSettings(fixture(null)), 'без настроек');

    $select = fixture(serialize(['values' => ['A', new ArrayObject(['x'])]]), 'select');
    $form = new class extends CAdminForm {
        public array $dropDowns = [];

        public function AddDropDownField(...$arguments): void
        {
            $this->dropDowns[] = $arguments;
        }
    };
    (new SelectOption())->getFormFieldValue('options[1]', $form, $select);
    foreach ($form->dropDowns[0]['arSelect'] ?? [] as $item) {
        assertTrue(! $item instanceof ArrayObject, 'класс из настроек списка не восстановлен');
    }

    $strings = fixture(null, 'strings', serialize([new ArrayObject(['x'])]));
    $value = (new StringsOption())->getValue($strings);
    assertTrue(! ($value[0] ?? null) instanceof ArrayObject, 'класс из значения «Несколько строк» не восстановлен');
});

test('security: id строки в ajax-добавлении принимается только вида options[N][M]', function (): void {
    foreach (['options[12][3]' => true, 'options[1][0]' => true,
        'options[1][0]"><script>alert(1)</script>' => false, 'options[1][a]' => false, 'other[1][0]' => false] as $id => $valid) {
        Application::$request = new HttpRequest(['option_type' => 'string', 'id' => $id]);
        $error = null;
        try {
            (new RequestAddRowValidator())->validate();
        } catch (Exception $exception) {
            $error = $exception->getMessage();
        }
        assertSame($valid, null === $error, "id $id: " . var_export($error, true));
    }
});
