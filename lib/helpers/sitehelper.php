<?php
declare(strict_types=1);

namespace Claramente\Options\Helpers;

use Bitrix\Main\SiteTable;

/**
 * Вспомогательные методы для получения информации по сайту
 */
final class SiteHelper
{
    /**
     * Кэш сайтов
     * @var array|null
     */
    private static ?array $cacheSites = null;

    /**
     * Получить название сайта
     * @param string $siteId
     * @return string|null
     */
    public static function getSiteName(string $siteId): ?string
    {
        return self::getSite($siteId)['NAME'] ?? null;
    }

    /**
     * Получить информацию по сайту
     * @param string $siteId
     * @return array|null
     */
    public static function getSite(string $siteId): ?array
    {
        if (null === self::$cacheSites) {
            $sites = SiteTable::query()->setSelect(['*'])->fetchAll();
            self::$cacheSites = array_combine(
                array_column($sites, 'LID'),
                array_values($sites)
            );
        }
        return self::$cacheSites[$siteId] ?? null;
    }
}