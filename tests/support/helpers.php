<?php

declare(strict_types=1);

use Bitrix\Main\Type\DateTime;
use Claramente\Options\Structures\Entity\OptionEntityStructure;

/**
 * Опция для тестов
 */
function fixture(?string $settings = null, string $type = 'string', ?string $value = null): OptionEntityStructure
{
    return new OptionEntityStructure(
        1,
        'Sample',
        'sample',
        $value,
        $type,
        $settings,
        100,
        null,
        null,
        true,
        false,
        new DateTime(),
        new DateTime()
    );
}

/**
 * Пользователь Bitrix для административных страниц
 */
function fakeUser(bool $isAdmin): object
{
    return new class($isAdmin) {
        public function __construct(private bool $isAdmin)
        {
        }

        public function IsAdmin(): bool
        {
            return $this->isAdmin;
        }
    };
}

/**
 * Выполнить административный скрипт модуля как в Bitrix: $USER доступен глобально и локально,
 * $vars — переменные области видимости вызывающей страницы (например, $request).
 * Возвращает адрес редиректа или null, если редиректа не было
 */
function runAdminScript(string $file, object $user, array $vars = []): ?string
{
    $GLOBALS['USER'] = $user;
    $USER = $user;
    extract($vars, EXTR_SKIP);
    ob_start();
    try {
        require $file;
    } catch (RedirectException $redirect) {
        return $redirect->getMessage();
    } finally {
        ob_end_clean();
    }

    return null;
}
