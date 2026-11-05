<?php

use Claramente\Options\Entity\ClaramenteOptionsTable;

if (! function_exists('cm_option')) {

    /**
     * Получить значение опции по коду для текущего языка
     * @param string $code
     * @param string|null $siteId
     * @param mixed $default
     * @param int $cacheTtl Кэширование
     * @return mixed
     */
    function cm_option(
        string  $code,
        ?string $siteId = null,
        mixed   $default = null,
        int     $cacheTtl = 0,
    ): mixed
    {
        $option = ClaramenteOptionsTable::getByCode($code, $siteId, $cacheTtl);

        return $option?->getOptionType() ? $option->getOptionType()->getValue($option) : $default;
    }
}

if (! function_exists('cm_option_all_lang')) {
    /**
     * Получить значение опции по коду для всех языков
     * @param  string  $code
     * @param  string|null  $siteId
     * @param  int  $cacheTtl
     * @return array
     */
    function cm_option_all_lang(
        string  $code,
        ?string $siteId = null,
        int     $cacheTtl = 0,
    ): array
    {
        $option = ClaramenteOptionsTable::getByCode($code, $siteId, $cacheTtl, getForAllLanguages: true);

        return $option?->getOptionType() ? $option->getOptionType()->getValuesForLangs($option) : [];
    }
}

if (! function_exists('cm_option_set')) {
    /**
     * Установка нового значения опции
     * Внимание: опция должна существовать
     * @param string $code
     * @param string|null $siteId
     * @param mixed|null $value
     * @return bool
     */
    function cm_option_set(
        string  $code,
        ?string $siteId = null,
        mixed   $value = null
    ): bool
    {
        $option = ClaramenteOptionsTable::getByCode($code, $siteId);
        if (! $option || ! $option->getOptionType()) {
            return false;
        }

        return $option->setValue($value);
    }
}

if (! function_exists('cm_option_exists')) {
    /**
     * Проверка существования опции для текущего языка
     * @param string $code
     * @param string|null $siteId
     * @return bool
     */
    function cm_option_exists(string $code, ?string $siteId = null): bool
    {
        return null !== ClaramenteOptionsTable::getByCode($code, $siteId);
    }
}

if (! function_exists('cm_option_filled')) {
    /**
     * Проверка заполнения опции для текущего языка
     * @param string $code
     * @param string|null $siteId
     * @return bool
     */
    function cm_option_filled(string $code, ?string $siteId = null): bool
    {
        $option = ClaramenteOptionsTable::getByCode($code, $siteId);

        return null !== $option && ! empty($option->value);
    }
}

if (! function_exists('cm_option_delete')) {
    /**
     * Удаление опции
     * @param string $code
     * @param string|null $siteId
     * @return bool
     */
    function cm_option_delete(string $code, ?string $siteId = null): bool
    {
        $option = ClaramenteOptionsTable::getByCode($code, $siteId);
        if (! $option) {
            return false;
        }
        ClaramenteOptionsTable::delete($option->id);
        ClaramenteOptionsTable::clearPreloader();

        return true;
    }
}

