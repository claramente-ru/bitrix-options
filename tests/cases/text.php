<?php

declare(strict_types=1);

use Claramente\Options\Types\TextOption;

const TEXT_SAMPLES = [
    'a &amp; b',
    '&lt;b&gt;не тег&lt;/b&gt;',
    "It's \"ok\"",
    '<b>bold</b> &nbsp; &copy;',
    "строка 1\nстрока 2",
];

/**
 * Что увидит в textarea браузер, а значит что придет обратно при сохранении формы
 */
function textareaContent(string $html): string
{
    preg_match('~<textarea[^>]*>(.*)</textarea>~s', $html, $match);

    return html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

test('text: значение в поле формы совпадает с сохраненным (одно на все языки)', function (): void {
    foreach (TEXT_SAMPLES as $text) {
        $form = new CAdminForm();
        (new TextOption())->getFormFieldValue('options[1]', $form, fixture(null, 'text', $text));
        assertSame($text, textareaContent($form->fields['options[1][value]'] ?? ''), $text);
    }
});

test('text: значение в поле формы совпадает с сохраненным (по языкам)', function (): void {
    foreach (TEXT_SAMPLES as $text) {
        $html = (new TextOption())->getOptionLangRow('options[1][0][value]', 'options[1][0][lang]', ['value' => $text, 'lang' => 'ru']);
        assertSame($text, textareaContent($html), $text);
    }
});
