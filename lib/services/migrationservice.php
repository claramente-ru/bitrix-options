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

        // Соберем данные для миграции
        $configValues = $this->getMigrationData($option, $tab);
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
     * Генерация информации для миграции
     * @param array $option
     * @param TabEntityStructure|null $tab
     * @return array
     */
    protected function getMigrationData(array $option, ?TabEntityStructure $tab): array
    {
        return [
            'TAB_CODE' => $this->stringClear($tab?->code),
            'TAB_NAME' => $this->stringClear($tab?->name),
            'TAB_SORT' => $tab?->sort,
            'OPTION_NAME' => $this->stringClear($option['NAME']),
            'OPTION_CODE' => $this->stringClear($option['CODE']),
            'OPTION_TYPE' => $this->stringClear($option['TYPE']),
            'OPTION_SITE_ID' => $this->stringClear($option['SITE_ID']),
            'OPTION_VALUE' => $this->stringClear($option['VALUE']),
            'OPTION_SETTINGS' => $this->stringClear($option['SETTINGS']),
            'OPTION_SORT' => $option['SORT']
        ];
    }

    /**
     * @param string|null $value
     * @return string|null
     */
    protected function stringClear(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        return str_replace("'", "\'", $value);
    }
}
