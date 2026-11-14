<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;

/**
 * Сохранить параметр через форму. Возвращает сообщения об ошибках
 */
function saveOption(array $post, ?int $id = null): array
{
    CAdminMessage::$messages = [];
    Application::$request = new HttpRequest(
        ['option' => $post + ['name' => 'Sample', 'type' => 'string']],
        null === $id ? [] : ['ID' => $id]
    );
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));

    return CAdminMessage::$messages;
}

function optionsForSites(): void
{
    $forAll = fixture(null, 'string', 'для всех');
    $forAll->code = 'phone';
    $forSite = fixture(null, 'string', 'для s1');
    $forSite->id = 2;
    $forSite->code = 'phone';
    $forSite->siteId = 's1';
    ClaramenteOptionsTable::$options = [1 => $forAll, 2 => $forSite];
}

test('option save: второй параметр с тем же кодом для всех сайтов не создается', function (): void {
    optionsForSites();
    // «Не выбрано» в списке сайтов приходит пустой строкой
    $errors = saveOption(['code' => 'phone', 'site_id' => '']);
    assertSame(['Ошибка. Код опции phone уже существует'], $errors, 'ошибка');
    assertSame([1, 2], array_keys(ClaramenteOptionsTable::$options), 'новый параметр не создан');
});

test('option save: смена только сайта проверяет пару код + сайт', function (): void {
    optionsForSites();
    $errors = saveOption(['code' => 'phone', 'site_id' => 's1'], 1);
    assertSame(['Ошибка. Код опции phone уже существует'], $errors, 'ошибка вместо исключения БД по уникальному ключу');
    assertSame(null, ClaramenteOptionsTable::$options[1]->siteId, 'параметр не изменен');

    assertSame([], saveOption(['code' => 'phone', 'site_id' => 's2'], 1), 'на свободный сайт можно');
    assertSame('s2', ClaramenteOptionsTable::$options[1]->siteId, 'сайт изменен');
});

test('option save: сохранение без смены кода и сайта не считается дублем', function (): void {
    optionsForSites();
    assertSame([], saveOption(['code' => 'phone', 'site_id' => '', 'name' => 'Телефон'], 1), 'без ошибок');
    assertSame('Телефон', ClaramenteOptionsTable::$options[1]->name, 'сохранено');
    assertSame(null, ClaramenteOptionsTable::$options[1]->siteId, 'пустой сайт хранится как NULL');
});

test('option save: код без пробелов по краям и не пустой', function (): void {
    optionsForSites();
    assertSame(['Ошибка. Код опции phone уже существует'], saveOption(['code' => ' phone ', 'site_id' => '']), 'пробелы не обходят проверку');

    ClaramenteOptionsTable::$options = [];
    assertSame([], saveOption(['code' => ' email ']), 'новый параметр');
    assertSame('email', ClaramenteOptionsTable::$options[1]->code, 'код без пробелов');

    assertSame(['Ошибка. Не указан код параметра'], saveOption(['code' => '  ']), 'пустой код');
    assertSame(1, count(ClaramenteOptionsTable::$options), 'параметр с пустым кодом не создан');
});
