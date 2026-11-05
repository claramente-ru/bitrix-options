<?php

declare(strict_types=1);

use Bitrix\Main\EventManager;

test('install: при удалении модуля снимаются те же обработчики, что регистрируются при установке', function (): void {
    EventManager::$registered = [];
    EventManager::$unregistered = [];
    $moduleId = 'claramente.options';
    require __DIR__ . '/../../install/event_handlers/register.php';
    require __DIR__ . '/../../install/event_handlers/unregister.php';

    assertTrue(count(EventManager::$registered) > 0, 'обработчики регистрируются');
    assertSame(EventManager::$registered, EventManager::$unregistered, 'событие, модуль, класс и метод совпадают');
});
