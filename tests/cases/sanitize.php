<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Helpers\HtmlHelper;
use Claramente\Options\Types\StringOption;
use Claramente\Options\Types\StringsOption;
use Claramente\Options\Types\TextOption;

const SANITIZE_SAMPLE = '<b>Жирный</b> <a href="/x" rel="nofollow">ссылка</a><script>alert(1)</script><img src="/i.png" onerror="alert(2)">';
const SANITIZE_RESULT = '<b>Жирный</b> <a href="/x" rel="nofollow">ссылка</a><img src="/i.png">';

test('sanitize: настройки CBXSanitizer', function (): void {
    CBXSanitizer::$calls = [];
    HtmlHelper::sanitize('<b>x</b>');
    $call = CBXSanitizer::$calls[0] ?? [];
    assertSame(CBXSanitizer::SECURE_LEVEL_LOW, $call['level'] ?? null, 'низкий уровень');
    assertSame(true, $call['htmlSpecChars'], 'текст между тегами кодируется, как рекомендует Bitrix');
    assertSame(false, $call['doubleEncode'], 'уже закодированное не кодируется повторно');
    assertSame(true, $call['deleteSanitized'], 'запрещенные теги удаляются, а не экранируются');
    assertSame(['script', 'style', 'iframe', 'object', 'embed', 'noscript'], $call['deleteWithContent'], 'удаляются вместе с содержимым');
    assertTrue(in_array('rel', $call['tags']['a'] ?? [], true), 'у ссылок разрешен rel');
    assertTrue(in_array('href', $call['tags']['a'] ?? [], true), 'атрибуты ссылки уровня LOW сохранены');
});

test('sanitize: строки без HTML не меняются и не передаются в санитайзер', function (): void {
    CBXSanitizer::$calls = [];
    foreach (['Обычный текст', 'https://site.ru/?a=1&b=2', 'Кавычки "и" \'апостроф\'', '&amp; &nbsp;'] as $text) {
        assertSame($text, HtmlHelper::sanitize($text), $text);
    }
    assertSame([], CBXSanitizer::$calls, 'санитайзер не вызывался');
});

test('sanitize: «<» в тексте не принимается за тег', function (): void {
    CBXSanitizer::$calls = [];
    assertSame('a < b &gt; c и 1<2 <b>ok</b>', HtmlHelper::sanitize('a < b > c и 1<2 <b>ok</b>'), 'текст сохранен');
    assertTrue(! str_contains(CBXSanitizer::$calls[0]['html'], '< b') && ! str_contains(CBXSanitizer::$calls[0]['html'], '1<2'), 'санитайзер получил «<» текста заменой');
    assertTrue(str_contains(CBXSanitizer::$calls[0]['html'], '<b>ok</b>'), 'теги переданы как есть');
    assertSame('Цена &lt;', HtmlHelper::sanitize('Цена <'), '«<» в конце не склеится со следующим значением в тег');
});

test('sanitize: текст между тегами кодируется, повторное сохранение значение не меняет', function (): void {
    $once = HtmlHelper::sanitize('<b>"Рога" & копыта</b> &amp; &nbsp;');
    assertSame('<b>&quot;Рога&quot; &amp; копыта</b> &amp; &nbsp;', $once, 'кавычки и & закодированы, сущности не кодируются повторно');
    assertSame($once, HtmlHelper::sanitize($once), 'пересохранение');
    assertSame('&lt;x title=&quot;<b>&quot; data-x=&quot;1&quot;&gt;t</b>', HtmlHelper::sanitize('<x title="<b>" data-x="1">t</b>'), 'незакрытый тег перед разрешенным остается текстом');
});

test('sanitize: в поле формы значение с HTML показывается раскодированным и сохраняется так же', function (): void {
    foreach (['Скидка <b>10%</b> для "своих" & друзей', "a < b > c <i>x</i> 'q'", 'Цена < 100 <b>руб</b>'] as $input) {
        $stored = HtmlHelper::sanitize($input);
        assertSame($input, HtmlHelper::decodeForForm($stored), "в поле: $input");
        assertSame($stored, HtmlHelper::sanitize(HtmlHelper::decodeForForm($stored)), "пересохранение: $input");
    }
    assertSame('&lt;b&gt; буквами', HtmlHelper::decodeForForm('&lt;b&gt; буквами'), 'значение без HTML не раскодируется');
    assertSame(null, HtmlHelper::decodeForForm(null), 'null');
});

test('sanitize: поля строки, текста и строк выводят раскодированное значение', function (): void {
    $stored = '<b>&quot;Рога&quot; &amp; копыта</b>';
    $expected = htmlspecialcharsbx('<b>"Рога" & копыта</b>');

    $form = new CAdminForm();
    ob_start();
    (new StringOption())->getFormFieldValue('options[1]', $form, fixture(null, 'string', $stored));
    assertTrue(str_contains((string)ob_get_clean(), 'value="' . $expected . '"'), 'строка');

    $form = new CAdminForm();
    (new TextOption())->getFormFieldValue('options[1]', $form, fixture(null, 'text', $stored));
    assertTrue(str_contains($form->fields['options[1][value]'], '>' . $expected . '</textarea>'), 'текст');

    $row = (new TextOption())->getOptionLangRow('options[1][0][value]', 'options[1][0][lang]', ['value' => $stored, 'lang' => 'ru']);
    assertTrue(str_contains($row, '>' . $expected . '</textarea>'), 'текст по языкам');
});

test('sanitize: строка, одно значение и значения по языкам', function (): void {
    require_once __DIR__ . '/../../include/functions.php';
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', null)];
    assertTrue(cm_option_set('sample', null, SANITIZE_SAMPLE), 'сохранено');
    assertSame(SANITIZE_RESULT, ClaramenteOptionsTable::$options[1]->value, 'скрипт и обработчик удалены');

    $option = fixture(null, 'string', null);
    $option->singleValueForAllLang = false;
    ClaramenteOptionsTable::$options = [1 => $option];
    assertTrue(cm_option_set('sample', null, [['value' => SANITIZE_SAMPLE, 'lang' => 'ru'], ['value' => 'Hello', 'lang' => 'en']]), 'сохранено');
    assertSame(serialize([['value' => SANITIZE_RESULT, 'lang' => 'ru'], ['value' => 'Hello', 'lang' => 'en']]), ClaramenteOptionsTable::$options[1]->value, 'по языкам');
});

test('sanitize: текст и строки', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'text', null)];
    assertTrue(cm_option_set('sample', null, ['value' => SANITIZE_SAMPLE]), 'текст из формы');
    assertSame(SANITIZE_RESULT, ClaramenteOptionsTable::$options[1]->value, 'текст очищен');

    $saved = (new StringsOption())->getValueBeforeSave(fixture(null, 'strings'), [SANITIZE_SAMPLE, 'Вторая']);
    assertSame([SANITIZE_RESULT, 'Вторая'], unserialize($saved), 'каждая строка очищена');
});

test('sanitize: значения администратора на странице параметров тоже очищаются', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', 'old')];
    Application::$request = new HttpRequest(['options' => [1 => ['value' => SANITIZE_SAMPLE]]]);
    runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(true));
    assertSame(SANITIZE_RESULT, ClaramenteOptionsTable::$options[1]->value, 'администратор');
});

test('sanitize: пользовательский наследник строки очищает значение, остальные типы не трогаются', function (): void {
    assertSame(SANITIZE_RESULT, (new HintsCustomOption())->getValueBeforeSave(fixture(null, 'custom'), SANITIZE_SAMPLE), 'наследник StringOption');

    CBXSanitizer::$calls = [];
    foreach (['date' => '<i>01.02.2026</i>', 'select' => '<b>1</b>'] as $type => $value) {
        ClaramenteOptionsTable::$options = [1 => fixture(null, $type, null)];
        cm_option_set('sample', null, $value);
        assertSame($value, ClaramenteOptionsTable::$options[1]->value, $type);
    }
    assertSame([], CBXSanitizer::$calls, 'санитайзер не вызывался');
});
