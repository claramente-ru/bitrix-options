<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Type\DateTime;
use Claramente\Options\Entity\ClaramenteOptionsTable;

test('pgsql: в таблице опций нет внешнего ключа CODE на вкладки', function (): void {
    $sql = file_get_contents(__DIR__ . '/../../install/db/pgsql/install.sql');
    $create = substr($sql, strpos($sql, 'CREATE TABLE IF NOT EXISTS claramente_options'));
    $create = substr($create, 0, strpos($create, ');'));
    assertTrue(! preg_match('~FOREIGN KEY\s*\(\s*CODE\s*\)~i', $create), 'в CREATE TABLE нет FOREIGN KEY (CODE)');
    assertTrue(str_contains($sql, 'DROP CONSTRAINT IF EXISTS code_fk'), 'ключ удаляется у уже созданной таблицы');
});

test('pgsql: время изменения задается при сохранении значения и параметра', function (): void {
    $option = fixture(null, 'string', 'old');
    $before = $option->updatedAt;
    ClaramenteOptionsTable::$options = [1 => $option];
    ClaramenteOptionsTable::getOptionById(1)->setValue('new');
    assertTrue(ClaramenteOptionsTable::$options[1]->updatedAt instanceof DateTime, 'setValue()');
    assertTrue(ClaramenteOptionsTable::$options[1]->updatedAt !== $before, 'setValue() задает новое время');

    $before = ClaramenteOptionsTable::$options[1]->updatedAt;
    Application::$request = new HttpRequest(['option' => ['code' => 'sample', 'name' => 'Sample', 'type' => 'string']], ['ID' => 1]);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));
    assertTrue(ClaramenteOptionsTable::$options[1]->updatedAt !== $before, 'сохранение параметра задает новое время');
});
