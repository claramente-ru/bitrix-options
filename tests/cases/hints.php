<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Admin\AdminForm;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Services\MigrationService;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
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
            assertSame(1, count($form->fields), "одна строка подсказки: $context");
            $html = $form->fields['options[1][hint]'];
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
        assertSame(['options[1]', 'options[1][hint]'], array_keys($fields), 'порядок поля и подсказки');
        assertTrue(str_contains($fields['options[1][hint]'], 'Visible hint'), 'подсказка выведена');
        assertSame($single ? 'single value' : 'multiple values', $fields['options[1]'], 'вывод значения типа не изменился');
    }
});

test('hints: подсказка создается, редактируется и очищается вместе с настройками типа', function (): void {
    foreach (['string', 'select', 'custom'] as $type) {
        foreach (['new', 'edit', 'clear', 'legacy'] as $scenario) {
            ClaramenteOptionsTable::$options = $scenario === 'new' ? [] : [1 => fixture()];
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
            if ($scenario === 'legacy') {
                unset($post['hint']);
            }
            Application::$request = new HttpRequest(['option' => $post], $scenario === 'new' ? [] : ['ID' => 1]);

            runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));

            $saved = unserialize(ClaramenteOptionsTable::$options[1]->settings);
            assertSame(in_array($scenario, ['clear', 'legacy'], true) ? '' : HINTS_SAMPLE, $saved['hint'] ?? '', "подсказка: $type/$scenario");
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

test('hints: миграция переносит подсказку в SETTINGS без изменений', function (): void {
    $service = (new ReflectionClass(MigrationService::class))->newInstanceWithoutConstructor();
    $settings = serialize(['hint' => "Quote ' slash \\ newline\nUnicode: текст"]);
    $params = (new ReflectionMethod(MigrationService::class, 'getMigrationData'))->invoke($service, [
        'TAB_ID' => null, 'NAME' => 'Sample', 'CODE' => 'sample', 'TYPE' => 'string',
        'SITE_ID' => null, 'VALUE' => null, 'SETTINGS' => $settings, 'SORT' => 100,
        'SINGLE_VALUE_FOR_ALL_LANG' => true,
    ], null);
    assertSame($settings, $params['OPTION_SETTINGS'], 'данные миграции');

    $builder = new class($params) {
        public function __construct(private array $params)
        {
        }

        public function getVersionConfig(): object
        {
            return new class($this->params) {
                public function __construct(private array $params)
                {
                }

                public function getList(): array
                {
                    return [['values' => $this->params]];
                }
            };
        }

        public function render(): string
        {
            $version = 'SyntheticHintMigration';
            $description = 'Sample';
            $extendUse = '';
            $extendClass = '\\stdClass';
            $moduleVersion = '1.0.0';
            $author = 'Sample';
            ob_start();
            require __DIR__ . '/../../lib/migration/template.php';

            return ob_get_clean();
        }
    };
    eval(substr($builder->render(), 5));
    $migration = (new ReflectionClass('Sprint\\Migration\\SyntheticHintMigration'))->newInstanceWithoutConstructor();
    assertSame($settings, (new ReflectionProperty($migration, 'optionSettings'))->getValue($migration), 'значение в сгенерированной миграции');
});
