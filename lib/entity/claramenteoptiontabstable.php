<?php
declare(strict_types=1);

namespace Claramente\Options\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\SystemException;
use Claramente\Options\Structures\Entity\TabEntityStructure;

/**
 * Таблица - claramente_option_tabs.
 * Здесь хранятся вкладки для настроек модуля
 */
final class ClaramenteOptionTabsTable extends DataManager
{
    /**
     * Название таблицы
     * @return string
     */
    public static function getTableName(): string
    {
        return 'claramente_option_tabs';
    }

    /**
     * Поля таблицы
     * @return array
     * @throws SystemException
     */
    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'is_autocomplete' => true
            ]),
            new IntegerField('SORT', [
                'default_value' => 100
            ]),
            new StringField('NAME', [
                'required' => true,
                'size' => 255,
            ]),
            new StringField('CODE', [
                'required' => true,
                'size' => 255,
            ])
        ];
    }

    /**
     * Получить вкладку по коду
     * @param string $code
     * @param string|null $siteId
     * @return TabEntityStructure|null
     */
    public static function getByCode(string $code, ?string $siteId = null): ?TabEntityStructure
    {
        $tab = self::query()
            ->setFilter([
                'CODE' => $code
            ])
            ->setSelect(['*'])
            ->fetch();

        return $tab ? TabEntityStructure::fromArray($tab) : null;
    }

    /**
     * Получить вкладку по id
     * @param int $id
     * @return TabEntityStructure|null
     */
    public static function getTabById(int $id): ?TabEntityStructure
    {
        $tab = self::query()
            ->setFilter([
                'ID' => $id
            ])
            ->setSelect(['*'])
            ->fetch();

        return $tab ? TabEntityStructure::fromArray($tab) : null;
    }
}
