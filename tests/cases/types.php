<?php

declare(strict_types=1);

use Bitrix\Main\Type\Date;
use Claramente\Options\Types\DateOption;
use Claramente\Options\Types\FileOption;

test('types: незаполненная или некорректная дата читается как null', function (): void {
    $type = new DateOption();
    foreach ([null, '', '  ', 'не дата'] as $value) {
        assertSame(null, $type->getValue(fixture(null, 'date', $value)), var_export($value, true));
    }
    $date = $type->getValue(fixture(null, 'date', '01.02.2026'));
    assertTrue($date instanceof Date && $date->timestamp === strtotime('01.02.2026'), 'заполненная дата');
});

test('types: дата по языкам без значения читается как null', function (): void {
    $option = fixture(null, 'date', serialize([
        ['value' => '01.02.2026', 'lang' => 'ru'],
        ['value' => null, 'lang' => 'en'],
        ['lang' => 'de'],
    ]));
    $option->singleValueForAllLang = false;
    $values = (new DateOption())->getValuesForLangs($option);
    assertTrue($values[0]['value'] instanceof Date, 'ru');
    assertSame(null, $values[1]['value'], 'en: null');
    assertSame(null, $values[2]['value'], 'de: нет ключа value');
});

test('types: файл без CONTENT_TYPE выводится как файл', function (): void {
    CFile::$files = [
        5 => ['SRC' => '/upload/doc.pdf', 'CONTENT_TYPE' => null],
        6 => ['SRC' => '/upload/pic.bin', 'CONTENT_TYPE' => 'image/png'],
    ];
    $type = new FileOption();
    assertTrue(str_contains($type->getFileField('options[1]', '5'), '<a data-file="5">'), 'без CONTENT_TYPE');
    assertTrue(str_contains($type->getFileField('options[1]', '6'), '<img data-file="6">'), 'картинка по CONTENT_TYPE');
});
