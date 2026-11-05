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
     * При ошибке возвращается false, текст ошибки — в $error
     * Для опции с отдельными значениями по языкам передавать массив [['value' => ..., 'lang' => 'ru'], ...]
     * @param string $code
     * @param string|null $siteId
     * @param mixed|null $value
     * @param string|null $error
     * @return bool
     */
    function cm_option_set(
        string  $code,
        ?string $siteId = null,
        mixed   $value = null,
        ?string &$error = null
    ): bool
    {
        $error = null;
        $option = ClaramenteOptionsTable::getByCode($code, $siteId);
        if (! $option) {
            $error = sprintf('Опция %s не найдена', $code);
            return false;
        }

        $result = $option->setValue($value);
        $error = $option->getLastError();

        return $result;
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

