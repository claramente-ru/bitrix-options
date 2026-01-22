<?php
declare(strict_types=1);

namespace Claramente\Options\Helpers;

use Bitrix\Main\Localization\LanguageTable;

/**
 * Вспомогательные методы для получения информации по языку
 */
final class LangHelper
{
    /**
     * Кэш языков
     * @var array|null
     */
    private static ?array $cacheLangs = null;


    /**
     * Получить информацию по языкам
     * @return array|null
     */
    public static function getLanguages(): ?array
    {
        if (null === self::$cacheLangs) {
            $languages = LanguageTable::getList([
                'select' => ['ID', 'NAME'],
                'filter' => ['=ACTIVE' => 'Y'],
                'order'  => ['SORT' => 'ASC']
            ])->fetchAll();
            self::$cacheLangs = array_combine(
                array_column($languages, 'ID'),
                array_values($languages)
            );
        }
        return self::$cacheLangs ?? null;
    }

    public static function getLanguagesNames(): ?array
    {
        if (null === self::$cacheLangs) {
            self::getLanguages();
        }

        return array_column(self::$cacheLangs, 'NAME', 'ID');
    }
}