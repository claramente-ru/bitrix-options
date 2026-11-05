<?php
declare(strict_types=1);

namespace Claramente\Options\Services;

use Bitrix\Main\SystemException;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Structures\Entity\TabEntityStructure;
use Sprint\Migration\VersionManager;
use Sprint\Migration\VersionConfig;
use CModule;
use InvalidArgumentException;

/**
 * Класс создания миграции для модуля claramente.options
 */
final class MigrationService
{
    /**
     * @var string
     */
    private string $builderName = 'ClaramenteOptionBuilder';

    /**
     * @var string
     */
    private string $prefix = 'ClaramenteOption';

    /**
     * @var string
     */
    private string $description = 'Миграция опции модуля claramente.options';

    /**
     * @var array|string[]
     */
    private array $versionBuilders = [
        'ClaramenteOptionBuilder' => 'Claramente\Options\Migration\ClaramenteOptionBuilder'
    ];

    public function __construct()
    {
        if (! CModule::IncludeModule('sprint.migration')) {
            throw new SystemException('Ошибка. Не найден модуль sprint.migration');
        }
        CModule::IncludeModule('claramente.options');
    }

    /**
     * Создать миграцию
     * @param int $optionId
     * @return bool
     */
    function createMigration(int $optionId): bool
    {
        // Заберем необработанную структурой информацию опции
        $option = ClaramenteOptionsTable::getById($optionId)->fetch();
        if (! $option) {
            return false;
        }
        // Возможно опция привязана к вкладке
        $tab = null;
        if ((int)$option['TAB_ID']) {
            $tab = ClaramenteOptionTabsTable::getTabById((int)$option['TAB_ID']);
        }

        // Соберем данные для миграции (готовые PHP-литералы для шаблона)
        $configValues = $this->getTemplateParams($option, $tab);
        $configValues['version_builders'] = $this->versionBuilders;

        $versionManager = new VersionManager(new VersionConfig('cfg', $configValues));

        // Ошибка. Нет директории для создания миграции
        if (! $versionManager->getWebDir()) {
            return false;
        }

        // Собственно создаем миграцию
        $builder = $versionManager->createBuilder(
            $this->builderName,
            [
                'builder_name' => $this->builderName,
                'prefix' => $this->prefix,
                'description' => $this->description,
            ]
        );

        return $builder->buildExecute();
    }


    /**
     * Генерация информации для миграции.
     * Значения приводятся к типам свойств миграции (string|null, int, bool), пустые
     * необязательные значения — к null. Для шаблона преобразуются в литералы (@see self::getTemplateParams())
     * @param array $option
     * @param TabEntityStructure|null $tab
     * @return array
     */
    protected function getMigrationData(array $option, ?TabEntityStructure $tab): array
    {
        return [
            // null, если опция не привязана к вкладке; у существующей вкладки код и название обязательны
            'TAB_CODE' => $tab?->code,
            'TAB_NAME' => $tab?->name,
            'TAB_SORT' => $tab ? $tab->sort : 100,
            'OPTION_NAME' => (string)$option['NAME'],
            'OPTION_CODE' => (string)$option['CODE'],
            'OPTION_TYPE' => (string)$option['TYPE'],
            'OPTION_SITE_ID' => $this->toNullableString($option['SITE_ID'] ?? null, emptyAsNull: true),
            'OPTION_VALUE' => $this->toNullableString($option['VALUE'] ?? null),
            'OPTION_SETTINGS' => $this->toNullableString($option['SETTINGS'] ?? null),
            'OPTION_SORT' => is_numeric($option['SORT'] ?? null) ? (int)$option['SORT'] : 100,
            'OPTION_SINGLE_VALUE_FOR_ALL_LANG' => (bool)($option['SINGLE_VALUE_FOR_ALL_LANG'] ?? false),
            'OPTION_IS_ADMIN_ONLY' => (bool)($option['IS_ADMIN_ONLY'] ?? false),
        ];
    }

    /**
     * Параметры для шаблона миграции: данные @see self::getMigrationData() в виде PHP-литералов
     * @param array $option
     * @param TabEntityStructure|null $tab
     * @return array<string, string>
     */
    protected function getTemplateParams(array $option, ?TabEntityStructure $tab): array
    {
        return array_map([self::class, 'toPhpLiteral'], $this->getMigrationData($option, $tab));
    }

    /**
     * PHP-литерал значения для вставки в код миграции.
     * Строка записывается в двойных кавычках:
     * - \r и \n — кодами, чтобы значение занимало одну строку файла и не менялось,
     *   если git преобразует окончания строк (core.autocrlf);
     * - \0 — кодом \x00, чтобы git не считал файл миграции бинарным;
     * - экранируются \, " и $ (иначе PHP подставит переменную).
     * int, bool и null выводятся через var_export
     * @param mixed $value
     * @return string
     */
    public static function toPhpLiteral(mixed $value): string
    {
        if (null === $value || is_bool($value) || is_int($value)) {
            return var_export($value, true);
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('В миграцию передаются только строки, целые числа, bool и null');
        }

        return '"' . strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            '$' => '\\$',
            "\r" => '\\r',
            "\n" => '\\n',
            "\0" => '\\x00',
        ]) . '"';
    }

    /**
     * Строка или null
     * @param mixed $value
     * @param bool $emptyAsNull Пустую строку считать отсутствием значения
     * @return string|null
     */
    private function toNullableString(mixed $value, bool $emptyAsNull = false): ?string
    {
        if (null === $value || ($emptyAsNull && $value === '')) {
            return null;
        }

        return (string)$value;
    }
}
