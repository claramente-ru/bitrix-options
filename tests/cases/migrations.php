<?php

declare(strict_types=1);

use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Services\MigrationService;
use Claramente\Options\Structures\Entity\TabEntityStructure;
use Sprint\Migration\Exceptions\MigrationException;

/**
 * Значения, которые раньше терялись или ломали файл миграции
 */
function migrationTrickyValues(): array
{
    return [
        'quote' => "It's",
        'double_backslash' => 'a\\\\b',
        'trailing_backslash' => 'C:\\dir\\',
        'newlines' => "line1\nline2\r\nline3",
        'injection' => '\\\'; public function __destruct(){ echo "INJECTED"; } //',
        'php_tags' => '<?php echo 1; ?> $var {$x} ${y}',
        'unicode' => "Подсказка — «текст» 😀",
        'null_byte' => "a\0b",
        'control_chars' => "bell\x07 backspace\x08 tab\t vt\x0B ff\x0C esc\x1B del\x7F",
        'escape_like_text' => '\\x41 \\n \\0 \\u{41} \\$ "quoted"',
        'hex_digit_after_newline' => "\nB\nAF\r9",
        'null_before_hex_digits' => "\0" . '7F' . "\0" . '0',
        'dollar_brace' => '{$a} ${b} $c->d $e[0]',
        'multi_language' => serialize([['value' => "x\\\\y 'q'", 'lang' => 'ru'], ['value' => '12.5', 'lang' => 'en']]),
    ];
}

const MIGRATION_SETTINGS_HINT = "Quote ' slash \\ newline\nUnicode: текст &amp; <b>";

function migrationOptionRow(string $case, string $value): array
{
    return [
        'TAB_ID' => 3, 'NAME' => "Name $value", 'CODE' => "code_$case", 'TYPE' => 'string',
        'SITE_ID' => $case === 'quote' ? 's1' : null, 'VALUE' => $value,
        'SETTINGS' => serialize(['hint' => MIGRATION_SETTINGS_HINT, 'values' => ["x\\y", "it's"]]),
        'SORT' => '250', 'SINGLE_VALUE_FOR_ALL_LANG' => $case === 'multi_language' ? '0' : '1',
        'IS_ADMIN_ONLY' => $case === 'unicode' ? '1' : '0',
    ];
}

test('migrations: данные для шаблона приведены к типам свойств миграции', function (): void {
    $service = (new ReflectionClass(MigrationService::class))->newInstanceWithoutConstructor();
    $data = (new ReflectionMethod(MigrationService::class, 'getMigrationData'))->invoke(
        $service,
        migrationOptionRow('quote', "It's"),
        new TabEntityStructure(3, 'Tab', 'tab', 7)
    );
    foreach ($data as $key => $item) {
        assertTrue(null === $item || is_string($item) || is_int($item) || is_bool($item), "тип $key");
    }
    assertSame(250, $data['OPTION_SORT'], 'сортировка числом');
    assertSame(false, $data['OPTION_IS_ADMIN_ONLY'], 'флаг bool');
});

test('migrations: все поля опции переносятся без изменений', function (): void {
    foreach (migrationTrickyValues() as $case => $value) {
        $row = migrationOptionRow($case, $value);
        ob_start();
        $result = renderMigration($row, new TabEntityStructure(3, "Tab 'x' \\", "tab\\code", 7));
        $output = ob_get_clean();
        $migration = $result['migration'];

        assertSame('', $output, "при загрузке миграции код не выполняется: $case");
        assertTrue(! method_exists($migration, '__destruct'), "значение не добавляет методы в миграцию: $case");
        assertSame($value, prop($migration, 'optionValue'), "VALUE: $case");
        assertSame("Name $value", prop($migration, 'optionName'), "NAME: $case");
        assertSame("code_$case", prop($migration, 'optionCode'), "CODE: $case");
        assertSame($row['SETTINGS'], prop($migration, 'optionSettings'), "SETTINGS: $case");
        assertSame(MIGRATION_SETTINGS_HINT, unserialize(prop($migration, 'optionSettings'))['hint'], "подсказка: $case");
        assertSame($case === 'quote' ? 's1' : null, prop($migration, 'optionSiteId'), "SITE_ID: $case");
        assertSame($case === 'unicode', prop($migration, 'optionIsAdminOnly'), "IS_ADMIN_ONLY: $case");
        assertSame($case !== 'multi_language', prop($migration, 'optionSingleValueForAllLang'), "одно значение для всех языков: $case");
        assertSame(250, prop($migration, 'optionSort'), "сортировка опции: $case");
        assertSame(7, prop($migration, 'tabSort'), "сортировка вкладки: $case");
        assertSame("Tab 'x' \\", prop($migration, 'tabName'), "название вкладки: $case");
        assertSame("tab\\code", prop($migration, 'tabCode'), "код вкладки: $case");
        assertSame("O'Brien", prop($migration, 'author'), "автор: $case");
    }
});

test('migrations: значения записываются в одну строку файла без сырых переводов строк', function (): void {
    foreach (migrationTrickyValues() as $case => $value) {
        $result = renderMigration(migrationOptionRow($case, $value));
        assertTrue(! str_contains($result['code'], "\r"), "нет CR в файле: $case");
        foreach ($result['params'] as $key => $literal) {
            assertTrue(! str_contains($literal, "\n"), "литерал $key в одну строку: $case");
        }
    }
});

test('migrations: мультиязычное значение после миграции читается', function (): void {
    $value = migrationTrickyValues()['multi_language'];
    $migration = renderMigration(migrationOptionRow('multi_language', $value))['migration'];
    assertSame("x\\\\y 'q'", unserialize(prop($migration, 'optionValue'))[0]['value'], 'значение языка');
});

test('migrations: опция без вкладки и с пустыми значениями', function (): void {
    $migration = renderMigration([
        'TAB_ID' => null, 'NAME' => 'N', 'CODE' => 'c', 'TYPE' => 'string', 'SITE_ID' => '',
        'VALUE' => null, 'SETTINGS' => null, 'SORT' => 100, 'SINGLE_VALUE_FOR_ALL_LANG' => 1,
    ])['migration'];
    assertSame(null, prop($migration, 'tabCode'), 'код вкладки');
    assertSame(100, prop($migration, 'tabSort'), 'сортировка вкладки по умолчанию');
    assertSame(null, prop($migration, 'optionValue'), 'VALUE');
    assertSame(null, prop($migration, 'optionSettings'), 'SETTINGS');
    assertSame(null, prop($migration, 'optionSiteId'), 'пустой сайт становится NULL');
});

test('migrations: up() создает опцию и вкладку', function (): void {
    ClaramenteOptionsTable::$options = [];
    ClaramenteOptionTabsTable::$tabs = [];
    $row = migrationOptionRow('unicode', 'Значение');
    renderMigration($row, new TabEntityStructure(3, 'Вкладка', 'tab_code', 7))['migration']->up();

    $tab = ClaramenteOptionTabsTable::getByCode('tab_code');
    assertTrue(null !== $tab, 'вкладка создана');
    $option = ClaramenteOptionsTable::getByCode('code_unicode', null);
    assertTrue(null !== $option, 'опция создана');
    assertSame('Значение', $option->value, 'значение');
    assertSame(true, $option->isAdminOnly, 'IS_ADMIN_ONLY');
    assertSame($tab->id, $option->tabId, 'опция во вкладке');
});

test('migrations: up() без вкладки не меняет вкладку существующей опции', function (): void {
    ClaramenteOptionTabsTable::$tabs = [];
    $existing = fixture(null, 'string', 'old');
    $existing->code = 'c';
    $existing->tabId = 42;
    ClaramenteOptionsTable::$options = [1 => $existing];

    renderMigration([
        'TAB_ID' => null, 'NAME' => 'N', 'CODE' => 'c', 'TYPE' => 'string', 'SITE_ID' => null,
        'VALUE' => 'new', 'SETTINGS' => null, 'SORT' => 100, 'SINGLE_VALUE_FOR_ALL_LANG' => 1,
    ])['migration']->up();

    assertSame('new', ClaramenteOptionsTable::$options[1]->value, 'значение обновлено');
    assertSame(42, ClaramenteOptionsTable::$options[1]->tabId, 'вкладка на целевом окружении сохранена');
});

test('migrations: up() и down() сообщают об ошибке записи', function (): void {
    ClaramenteOptionsTable::$options = [];
    ClaramenteOptionTabsTable::$tabs = [];
    $migration = renderMigration(migrationOptionRow('quote', 'x'))['migration'];

    ClaramenteOptionsTable::$failNext = 'ошибка БД';
    $thrown = null;
    try {
        $migration->up();
    } catch (MigrationException $exception) {
        $thrown = $exception->getMessage();
    }
    assertSame('ошибка БД', $thrown, 'up()');

    $migration->up();
    ClaramenteOptionsTable::$failNext = 'ошибка удаления';
    $thrown = null;
    try {
        $migration->down();
    } catch (MigrationException $exception) {
        $thrown = $exception->getMessage();
    }
    assertSame('ошибка удаления', $thrown, 'down()');
});

test('migrations: down() не удаляет вкладку, пока в ней есть опции других сайтов', function (): void {
    ClaramenteOptionsTable::$options = [];
    ClaramenteOptionTabsTable::$tabs = [];
    $tab = new TabEntityStructure(3, 'Вкладка', 'tab_code', 7);
    $migration = renderMigration(migrationOptionRow('newlines', 'x'), $tab)['migration'];
    $migration->up();
    $tabId = ClaramenteOptionTabsTable::getByCode('tab_code')->id;

    $other = fixture(null, 'string', 'other');
    $other->id = 99;
    $other->code = 'other_site_option';
    $other->siteId = 's2';
    $other->tabId = $tabId;
    ClaramenteOptionsTable::$options[99] = $other;

    $migration->down();
    assertSame(null, ClaramenteOptionsTable::getByCode('code_newlines', null), 'опция удалена');
    assertTrue(null !== ClaramenteOptionTabsTable::getTabById($tabId), 'вкладка осталась: в ней опция сайта s2');

    unset(ClaramenteOptionsTable::$options[99]);
    $migration->up();
    $migration->down();
    assertSame(null, ClaramenteOptionTabsTable::getByCode('tab_code'), 'пустая вкладка удалена');
});
