<?php

use Claramente\Options\Entity\ClaramenteOptionsTable;

if (! function_exists('cm_option')) {

    /**
     * Получить опцию по коду
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
     * Проверка существования опции
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
     * Проверка заполнения опции
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
        return true;
    }
}

