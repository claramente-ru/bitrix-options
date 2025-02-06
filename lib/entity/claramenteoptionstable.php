<?php
declare(strict_types=1);

namespace Claramente\Options\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Claramente\Options\Structures\Entity\OptionEntityStructure;

/**
 * Таблица - claramente_options.
 * Здесь хранятся все параметры модуля
 */
final class ClaramenteOptionsTable extends DataManager
{
    /**
     * Название таблицы
     * @return string
     */
    public static function getTableName(): string
    {
        return 'claramente_options';
    }

    /**
     * Поля таблицы
     * @return array
     */
    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'is_autocomplete' => true
            ]),
            new IntegerField('SORT', [
                'default_value' => 100,
                'nullable' => false
            ]),
            new IntegerField('TAB_ID', [
                'nullable' => true,
            ]),
            new StringField('NAME', [
                'required' => true,
                'size' => 255,
            ]),
            new StringField('CODE', [
                'required' => true,
                'size' => 255,
            ]),
            new StringField('VALUE', [
                'nullable' => true
            ]),
            new StringField('SETTINGS', [
                'nullable' => true
            ]),
            new StringField('TYPE', [
                'size' => 255
            ]),
            new StringField('SITE_ID', [
                'nullable' => true,
                'size' => 12
            ]),
            new DatetimeField('CREATED_AT'),
            new DatetimeField('UPDATED_AT')
        ];
    }

    /**
     * Получить опции по идентификатору вкладки
     * @param int $tabId
     * @param string|null $siteId
     * @return OptionEntityStructure[]
     */
    public static function getByTabId(int $tabId, ?string $siteId = null): array
    {
        $options = [];
        $dbOptions = self::query()
            ->setFilter([
                'TAB_ID' => $tabId,
                'SITE_ID' => $siteId
            ])
            ->setSelect(['*'])
            ->fetchAll();
        foreach ($dbOptions as $dbOption) {
            $options[] = OptionEntityStructure::fromArray($dbOption);
        }

        return $options;
    }

    /**
     * Получить опцию по коду
     * @param string $code
     * @param string|null $siteId
     * @return OptionEntityStructure|null
     */
    public static function getByCode(string $code, ?string $siteId = null): ?OptionEntityStructure
    {
        $option = self::query()
            ->setFilter([
                'CODE' => $code,
                'SITE_ID' => $siteId
            ])
            ->setSelect(['*'])
            ->fetch();

        return $option ? OptionEntityStructure::fromArray($option) : null;
    }

    /**
     * Получить опцию по id
     * @param int $id
     * @return OptionEntityStructure|null
     */
    public static function getOptionById(int $id): ?OptionEntityStructure
    {
        $option = self::query()
            ->setFilter([
                'ID' => $id
            ])
            ->setSelect(['*'])
            ->fetch();

        return $option ? OptionEntityStructure::fromArray($option) : null;
    }
}
