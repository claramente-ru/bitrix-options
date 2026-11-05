<?php

declare(strict_types=1);

use Bitrix\Main\Type\DateTime;
use Claramente\Options\Services\MigrationService;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use Claramente\Options\Structures\Entity\TabEntityStructure;

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

/**
 * Результат операции ORM (add/update/delete)
 */
function fakeResult(bool $success, int $id = 0, array $errors = []): object
{
    return new class($success, $id, $errors) {
        public function __construct(private bool $success, private int $id, private array $errors)
        {
        }

        public function isSuccess(): bool
        {
            return $this->success;
        }

        public function getId(): int
        {
            return $this->id;
        }

        public function getErrorMessages(): array
        {
            return $this->errors;
        }
    };
}

/**
 * Сгенерировать миграцию по шаблону модуля для строки таблицы опций и загрузить ее класс.
 * Возвращает код миграции, параметры шаблона и экземпляр миграции (без вызова конструктора)
 * @return array{code: string, params: array, migration: object}
 */
function renderMigration(array $option, ?TabEntityStructure $tab = null): array
{
    static $counter = 0;
    $className = 'SyntheticMigration' . (++$counter);
    $service = (new ReflectionClass(MigrationService::class))->newInstanceWithoutConstructor();
    $params = (new ReflectionMethod(MigrationService::class, 'getTemplateParams'))->invoke($service, $option, $tab);

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

        public function render(string $className): string
        {
            $version = $className;
            $description = "Описание с 'кавычкой' и \\слешем";
            $extendUse = '';
            $extendClass = '\\stdClass';
            $moduleVersion = '1.0.3';
            $author = "O'Brien";
            ob_start();
            require __DIR__ . '/../../lib/migration/template.php';

            return ob_get_clean();
        }
    };
    $code = $builder->render($className);
    // Синтаксическая ошибка в сгенерированном коде приводит к ParseError
    eval(substr($code, 5));
    $migration = (new ReflectionClass('Sprint\\Migration\\' . $className))->newInstanceWithoutConstructor();

    return ['code' => $code, 'params' => $params, 'migration' => $migration];
}

/**
 * Значение защищенного свойства
 */
function prop(object $object, string $name): mixed
{
    return (new ReflectionProperty($object, $name))->getValue($object);
}
