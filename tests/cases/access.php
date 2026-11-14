<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Admin\AdminForm;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use Claramente\Options\Structures\Entity\TabEntityStructure;

function accessAdminOnlyOption(): OptionEntityStructure
{
    $option = fixture(null, 'string', 'old');
    $option->id = 2;
    $option->code = 'admin_only';
    $option->isAdminOnly = true;

    return $option;
}

test('access: контентщик сохраняет значения обычных опций', function (): void {
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'string', 'old')];
    Application::$request = new HttpRequest(['options' => [1 => ['value' => 'new']]]);

    $redirect = runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(false));

    assertTrue(null !== $redirect, 'редирект после сохранения');
    assertSame('new', ClaramenteOptionsTable::$options[1]->value, 'значение сохранено');
});

test('access: контентщик не меняет опции «только для администраторов»', function (): void {
    ClaramenteOptionsTable::$options = [2 => accessAdminOnlyOption()];
    Application::$request = new HttpRequest(['options' => [2 => ['value' => 'changed']]]);
    CAdminMessage::$messages = [];

    runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(false));

    assertSame('old', ClaramenteOptionsTable::$options[2]->value, 'значение не изменилось');
    assertTrue(str_contains(implode("\n", CAdminMessage::$messages), 'только администраторам'), 'сообщение об ошибке');

    runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(true));
    assertSame('changed', ClaramenteOptionsTable::$options[2]->value, 'администратор меняет значение');
});

test('access: вкладки создает и удаляет только администратор', function (): void {
    ClaramenteOptionsTable::$options = [];
    ClaramenteOptionTabsTable::$tabs = [5 => new TabEntityStructure(5, 'Старая', 'old', 100)];
    Application::$request = new HttpRequest([
        'tab_add' => ['name' => 'Новая', 'code' => 'new'],
        'tabs' => [5 => ['del' => 'Y']],
    ]);

    runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(false));
    assertSame(['old'], array_values(array_map(static fn($tab) => $tab->code, ClaramenteOptionTabsTable::$tabs)), 'контентщик: вкладки не изменились');

    runAdminScript(__DIR__ . '/../../admin/save/main.php', fakeUser(true));
    assertSame(['new'], array_values(array_map(static fn($tab) => $tab->code, ClaramenteOptionTabsTable::$tabs)), 'администратор: новая добавлена, старая удалена');
});

test('access: вкладка настройки вкладок видна только администратору', function (): void {
    AdminForm::$options = [];
    ClaramenteOptionTabsTable::$tabs = [];

    runAdminScript(__DIR__ . '/../../admin/pages/main.php', fakeUser(false), ['request' => new HttpRequest()]);
    assertSame(['options'], AdminForm::$formTabs, 'контентщик');

    runAdminScript(__DIR__ . '/../../admin/pages/main.php', fakeUser(true), ['request' => new HttpRequest()]);
    assertSame(['options', 'tabs'], AdminForm::$formTabs, 'администратор');
});

test('access: добавление значения для языка доступно всем, ссылки редактирования — администратору', function (): void {
    AdminForm::$options = [];

    runAdminScript(__DIR__ . '/../../admin/pages/main.php', fakeUser(false), ['request' => new HttpRequest()], $output);
    assertTrue(str_contains($output, 'function addOptionRow('), 'контентщик: функция добавления строки есть');
    assertTrue(! str_contains($output, 'editLink'), 'контентщик: ссылок редактирования нет');

    runAdminScript(__DIR__ . '/../../admin/pages/main.php', fakeUser(true), ['request' => new HttpRequest()], $output);
    assertTrue(str_contains($output, 'function addOptionRow(') && str_contains($output, 'editLink'), 'администратор: есть и то, и другое');
});
