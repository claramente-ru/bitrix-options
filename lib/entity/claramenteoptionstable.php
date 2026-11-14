<?php
declare(strict_types=1);

namespace Claramente\Options\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
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
            new BooleanField('SINGLE_VALUE_FOR_ALL_LANG', [
                'default' => 1,
            ]),
            new BooleanField('IS_ADMIN_ONLY', [
                'default' => 0,
            ]),
            new DatetimeField('CREATED_AT'),
            new DatetimeField('UPDATED_AT')
        ];
    }

    /**
     * Получить опции по идентификатору вкладки
     * @param int $tabId
     * @param string|null $siteId Сайт; null — опции вкладки для всех сайтов
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
        $filter = ['TAB_ID' => $tabId];
        if (null !== $siteId) {
            $filter['SITE_ID'] = $siteId;
        }
        $dbOptions = self::query()
            ->setFilter($filter)
            ->setSelect(['*']);
        // Кэширование данных
        $cacheTtl = self::resolveCacheTtl($cacheTtl);
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
        ?int    $cacheTtl = null,
        ?bool   $getForAllLanguages = false
    ): ?OptionEntityStructure
    {
        $option = null;
        $optionIsFilled = false;
        if (self::isEnabledPreloader()) {
            // Загрузим все элементы если ранее не были загружены
            self::preloaderOptions();
            $key = self::getPreloaderKey($code, $siteId);
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
            $cacheTtl = self::resolveCacheTtl($cacheTtl);
            if ($cacheTtl) {
                $option->setCacheTtl($cacheTtl);
            }
            $option = $option->fetch();
        }
        if (isset($option['VALUE']) && ! $option['SINGLE_VALUE_FOR_ALL_LANG'] && ! $getForAllLanguages) {
            $values = @unserialize((string)$option['VALUE'], ['allowed_classes' => false]);
            $value = is_array($values) ? (array_column($values, 'value', 'lang')[LANGUAGE_ID] ?? '') : '';
            $option['VALUE'] = is_scalar($value) ? (string)$value : '';
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
            $cacheTtl = self::resolveCacheTtl($cacheTtl);
            if ($cacheTtl) {
                $option->setCacheTtl($cacheTtl);
            }
            $option = $option->fetch();
        }

        return $option ? OptionEntityStructure::fromArray($option) : null;
    }

    /**
     * Сброс загруженных предзагрузчиком данных.
     * Вызывается после изменения опций, чтобы в рамках одного хита не читать устаревшие значения
     * @return void
     */
    public static function clearPreloader(): void
    {
        self::$preloaderCacheCodes = [];
        self::$preloaderCacheIds = [];
        self::$preloaderOptions = false;
    }

    /**
     * Ключ предзагрузчика: код и сайт разделены, чтобы пары foo + s1 и foos1 + null не совпадали
     * @param string $code
     * @param string|null $siteId
     * @return string
     */
    private static function getPreloaderKey(string $code, ?string $siteId): string
    {
        return $code . "\0" . (string)$siteId;
    }

    /**
     * Время кэширования: явно переданное значение приоритетнее глобальной константы
     * CLARAMENTE_OPTIONS_CACHE_TIME; константа применяется, если время не передано или равно 0
     * @param int|null $cacheTtl
     * @return int
     */
    private static function resolveCacheTtl(?int $cacheTtl): int
    {
        if ($cacheTtl) {
            return $cacheTtl;
        }

        return defined('CLARAMENTE_OPTIONS_CACHE_TIME') ? (int)CLARAMENTE_OPTIONS_CACHE_TIME : 0;
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
            $key = self::getPreloaderKey((string)$option['CODE'], $option['SITE_ID']);
            self::$preloaderCacheCodes[$key] = $option;
            self::$preloaderCacheIds[(int)$option['ID']] = $option;
        }

        // Отметим статус загрузки всех элементов
        self::$preloaderOptions = true;
    }
}
