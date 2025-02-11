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
     * Кэширование данных для загрузки всех опций сразу для @see self::preloaderOptions()
     * @var array
     */
    private static array $preloaderCacheCodes = [];

    /**
     * Кэширование данных для загрузки всех опций сразу для @see self::preloaderOptions()
     * @var array
     */
    private static array $preloaderCacheIds = [];

    /**
     * Статус загрузки всех элементов для @see self::preloaderOptions()
     * @var bool
     */
    private static bool $preloaderOptions = false;

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
     * @param int|null $cacheTtl Кэширование данных
     * @return OptionEntityStructure[]
     */
    public static function getByTabId(
        int     $tabId,
        ?string $siteId = null,
        ?int    $cacheTtl = null
    ): array
    {
        $options = [];
        $dbOptions = self::query()
            ->setFilter([
                'TAB_ID' => $tabId,
                'SITE_ID' => $siteId
            ])
            ->setSelect(['*']);
        // Кэширование данных
        if (null !== $cacheTtl && defined('CLARAMENTE_OPTIONS_CACHE_TIME')) {
            $cacheTtl = (int)CLARAMENTE_OPTIONS_CACHE_TIME;
        }
        if ($cacheTtl) {
            $dbOptions->setCacheTtl($cacheTtl);
        }
        foreach ($dbOptions->fetchAll() as $dbOption) {
            $options[] = OptionEntityStructure::fromArray($dbOption);
        }

        return $options;
    }

    /**
     * Получить опцию по коду
     * @param string $code
     * @param string|null $siteId
     * @param int|null $cacheTtl Кэширование данных
     * @return OptionEntityStructure|null
     */
    public static function getByCode(
        string  $code,
        ?string $siteId = null,
        ?int    $cacheTtl = null
    ): ?OptionEntityStructure
    {
        $option = null;
        $optionIsFilled = false;
        if (self::isEnabledPreloader()) {
            // Загрузим все элементы если ранее не были загружены
            self::preloaderOptions();
            $key = $code . $siteId ?: '';
            if (array_key_exists($key, self::$preloaderCacheCodes)) {
                $option = self::$preloaderCacheCodes[$key];
                // Заполнили option, отметим это
                $optionIsFilled = true;
            }
        }
        // Если опция не заполнена кэшем, то подгрузим ее
        if (! $optionIsFilled) {
            $option = self::query()
                ->setFilter([
                    'CODE' => $code,
                    'SITE_ID' => $siteId
                ])
                ->setSelect(['*']);
            // Кэширование данных
            if (null !== $cacheTtl && defined('CLARAMENTE_OPTIONS_CACHE_TIME')) {
                $cacheTtl = (int)CLARAMENTE_OPTIONS_CACHE_TIME;
            }
            if ($cacheTtl) {
                $option->setCacheTtl($cacheTtl);
            }
            $option = $option->fetch();
        }

        return $option ? OptionEntityStructure::fromArray($option) : null;
    }

    /**
     * Получить опцию по id
     * @param int $id
     * @param int|null $cacheTtl Кэширование данных
     * @return OptionEntityStructure|null
     */
    public static function getOptionById(int $id, ?int $cacheTtl = null): ?OptionEntityStructure
    {
        $option = null;
        $optionIsFilled = false;
        if (self::isEnabledPreloader()) {
            // Загрузим все элементы если ранее не были загружены
            self::preloaderOptions();
            if (array_key_exists($id, self::$preloaderCacheIds)) {
                $option = self::$preloaderCacheIds[$id];
                // Заполнили option, отметим это
                $optionIsFilled = true;
            }
        }
        // Если опция не заполнена кэшем, то подгрузим ее
        if (! $optionIsFilled) {
            $option = self::query()
                ->setFilter([
                    'ID' => $id
                ])
                ->setSelect(['*']);
            // Кэширование данных
            if (null !== $cacheTtl && defined('CLARAMENTE_OPTIONS_CACHE_TIME')) {
                $cacheTtl = (int)CLARAMENTE_OPTIONS_CACHE_TIME;
            }
            if ($cacheTtl) {
                $option->setCacheTtl($cacheTtl);
            }
            $option = $option->fetch();
        }

        return $option ? OptionEntityStructure::fromArray($option) : null;
    }

    /**
     * Включена загрузка всех элементов перед получением данных.
     * Сокращает количество запросов при большом количестве вызове метода cm_option
     * @return bool
     */
    private static function isEnabledPreloader(): bool
    {
        return defined('CLARAMENTE_OPTIONS_PRELOADER') && boolval(CLARAMENTE_OPTIONS_PRELOADER);
    }

    /**
     * Загрузка всех элементов в кэш
     * @return void
     */
    private static function preloaderOptions(): void
    {
        // Если элементы загружали ранее, нет смысла подгружать их снова
        if (self::$preloaderOptions) {
            return;
        }
        // Загрузим все элементы
        $options = self::query()
            ->setSelect(['*']);
        foreach ($options->fetchAll() as $option) {
            $key = $option['CODE'] . $option['SITE_ID'] ?: '';
            self::$preloaderCacheCodes[$key] = $option;
            self::$preloaderCacheIds[(int)$option['ID']] = $option;
        }

        // Отметим статус загрузки всех элементов
        self::$preloaderOptions = true;
    }
}
