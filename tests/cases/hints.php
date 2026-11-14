<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Admin\AdminForm;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use Claramente\Options\Types\AbstractOption;
use Claramente\Options\Types\BoolOption;
use Claramente\Options\Types\DateOption;
use Claramente\Options\Types\FileOption;
use Claramente\Options\Types\SelectOption;
use Claramente\Options\Types\StringOption;
use Claramente\Options\Types\StringsOption;
use Claramente\Options\Types\TextOption;

/**
 * Пользовательский наследник: собственный вывод значения и собственные настройки
 */
final class HintsCustomOption extends StringOption
{
    public static function getCode(): string
    {
        return 'custom';
    }

    public function getFormFieldValue(string $postName, CAdminForm &$form, OptionEntityStructure $option): void
    {
        $form->AddViewField($postName, '', 'single value');
    }

    public function getFormFieldMultipleValue(string $postName, CAdminForm &$form, OptionEntityStructure $option): void
    {
        $form->AddViewField($postName, '', 'multiple values');
    }

    public function beforeSaveSettings(HttpRequest $request, OptionEntityStructure $option): ?string
    {
        return serialize(['custom_setting' => 'retained']);
    }

    protected function getFieldFooter(): string
    {
        return '<span>Custom footer</span>' . parent::getFieldFooter();
    }
}

const HINTS_SAMPLE = "Use <script>alert('x')</script> & \"quotes\"\nNext&nbsp;line &#160; &amp; <a href=\"javascript:alert(1)\">link</a>";

function hintsAllTypes(): array
{
    return [
        new StringOption(), new HintsCustomOption(), new SelectOption(), new BoolOption(),
        new DateOption(), new FileOption(), new TextOption(), new StringsOption(),
    ];
}

test('hints: опция читает подсказку из SETTINGS', function (): void {
    assertSame('Текст', fixture(serialize(['hint' => 'Текст', 'size' => 30]))->getHint(), 'подсказка');
    foreach ([null, '', 'not serialized', serialize([]), serialize(['hint' => '  ']), serialize(['hint' => ['x']])] as $settings) {
        assertSame('', fixture($settings)->getHint(), var_export($settings, true));
    }
    $object = serialize(['hint' => new ArrayObject(['x'])]);
    assertSame('', fixture($object)->getHint(), 'объект в настройках не восстанавливается');
});

test('hints: пустая или отсутствующая подсказка не меняет форму', function (): void {
    foreach (hintsAllTypes() as $type) {
        foreach ([null, serialize([]), serialize(['hint' => '']), serialize(['hint' => '  ']), serialize(['hint' => []])] as $settings) {
            $form = new CAdminForm();
            $type->getFormFieldHint('options[1]', $form, fixture($settings));
            assertSame([], $form->fields, $type::getCode() . ' ' . var_export($settings, true));
        }
    }
});

test('hints: подсказка выводится один раз и безопасно для всех типов', function (): void {
    foreach (hintsAllTypes() as $type) {
        foreach ([true, false] as $single) {
            $option = fixture(serialize(['hint' => HINTS_SAMPLE]));
            $option->singleValueForAllLang = $single;
            $form = new CAdminForm();
            $type->getFormFieldHint('options[1]', $form, $option);
            $context = $type::getCode() . ($single ? '' : ' (по языкам)');
            assertSame(['hint_options[1]'], array_keys($form->fields), "одна строка подсказки, id не начинается с options: $context");
            $html = $form->fields['hint_options[1]'];
            assertTrue(! str_contains($html, '<script>') && ! str_contains($html, '<a '), "нет исполняемого HTML: $context");
            assertTrue(str_contains($html, '&lt;script&gt;'), "теги выводятся текстом: $context");
            assertTrue(str_contains($html, '&#039;') && str_contains($html, '&quot;'), "кавычки экранированы: $context");
            assertTrue(str_contains($html, '<br />'), "переводы строк сохранены: $context");
            assertTrue(str_contains($html, '&nbsp;') && str_contains($html, '&#160;'), "сущности сохранены: $context");
            assertTrue(! str_contains($html, '&amp;amp;'), "сущности не кодируются повторно: $context");
        }
    }
});

test('hints: общая форма выводит подсказку после поля и скрывает опции только для админа', function (): void {
    foreach ([true, false] as $single) {
        $visible = fixture(serialize(['hint' => 'Visible hint']));
        $visible->type = 'custom';
        $visible->singleValueForAllLang = $single;
        $hidden = clone $visible;
        $hidden->id = 2;
        $hidden->isAdminOnly = true;
        AdminForm::$options = [$visible, $hidden];

        runAdminScript(__DIR__ . '/../../admin/pages/main.php', fakeUser(false), ['request' => new HttpRequest()]);

        $fields = AdminForm::$form->fields;
        assertSame(['options[1]', 'hint_options[1]'], array_keys($fields), 'порядок поля и подсказки');
        assertTrue(str_contains($fields['hint_options[1]'], 'Visible hint'), 'подсказка выведена');
        assertSame($single ? 'single value' : 'multiple values', $fields['options[1]'], 'вывод значения типа не изменился');
    }
});

test('hints: подсказка создается, редактируется и очищается вместе с настройками типа', function (): void {
    foreach (['string', 'select', 'custom'] as $type) {
        foreach (['new', 'edit', 'clear', 'legacy', 'legacy_keep'] as $scenario) {
            ClaramenteOptionsTable::$options = $scenario === 'new' ? [] : [1 => fixture(
                $scenario === 'legacy_keep' ? serialize(['hint' => 'Old hint']) : null
            )];
            $post = [
                'code' => 'sample',
                'name' => 'Sample',
                'type' => $type,
                'site_id' => null,
                'single_value_for_all_lang' => 'Y',
                'hint' => $scenario === 'clear' ? '' : HINTS_SAMPLE,
                'settings' => [
                    'size' => '30',
                    'values' => ['First', '', 'Second'],
                ],
            ];
            if (in_array($scenario, ['legacy', 'legacy_keep'], true)) {
                unset($post['hint']);
            }
            Application::$request = new HttpRequest(['option' => $post], $scenario === 'new' ? [] : ['ID' => 1]);

            runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));

            $saved = unserialize(ClaramenteOptionsTable::$options[1]->settings);
            $expected = match ($scenario) {
                'clear', 'legacy' => '',
                'legacy_keep' => 'Old hint',
                default => HINTS_SAMPLE,
            };
            assertSame($expected, $saved['hint'] ?? '', "подсказка: $type/$scenario");
            if ($type === 'select') {
                assertSame([0 => 'First', 2 => 'Second'], $saved['values'], "элементы списка: $scenario");
            } elseif ($type === 'custom') {
                assertSame('retained', $saved['custom_setting'], "настройки наследника: $scenario");
            } else {
                assertSame('30', $saved['size'], "настройки строки: $scenario");
            }
        }
    }
});

test('hints: подсказка в редакторе переживает htmlspecialcharsbx(htmlspecialcharsback()) в CAdminForm', function (): void {
    foreach (["It's \"ok\"", '&amp; &lt;b&gt; &#039; &apos;', "a\\b\nc"] as $text) {
        $prepared = htmlspecialchars($text, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
        $textareaHtml = htmlspecialcharsbx(htmlspecialcharsback($prepared));
        assertSame($text, html_entity_decode($textareaHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'текст в редакторе');
    }
});

test('hints: подсказка длиннее лимита отклоняется до сохранения параметра', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture()];
    Application::$request = new HttpRequest(['option' => [
        'code' => 'sample', 'name' => 'Sample', 'type' => 'string',
        'hint' => str_repeat('я', AbstractOption::HINT_MAX_LENGTH + 1),
    ]], ['ID' => 1]);
    CAdminMessage::$messages = [];

    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));

    assertSame(1, count(CAdminMessage::$messages), 'одно сообщение об ошибке');
    assertTrue(str_contains(CAdminMessage::$messages[0], 'Подсказка длиннее'), 'текст ошибки');
    assertSame(null, ClaramenteOptionsTable::$options[1]->settings, 'настройки не изменились');
});

test('hints: миграция переносит подсказку в SETTINGS без изменений', function (): void {
    $settings = serialize(['hint' => "Quote ' slash \\ newline\nUnicode: текст"]);
    $result = renderMigration([
        'TAB_ID' => null, 'NAME' => 'Sample', 'CODE' => 'sample', 'TYPE' => 'string',
        'SITE_ID' => null, 'VALUE' => null, 'SETTINGS' => $settings, 'SORT' => 100,
        'SINGLE_VALUE_FOR_ALL_LANG' => true,
    ]);
    assertSame($settings, prop($result['migration'], 'optionSettings'), 'значение в сгенерированной миграции');
});
