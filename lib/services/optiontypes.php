<?php
declare(strict_types=1);

namespace Claramente\Options\Services;

use Claramente\Options\Types\AbstractOption;

/**
 * Типы данных настроек модуля claramente.options
 */
final class OptionTypes
{
    /**
     * Типы к классам
     * @var AbstractOption[]
     */
    private static array $typeClasses = [];

    /**
     * Свойства
     * @var array
     */
    private static array $types = [];

    /**
     * Получить все возможные типы свойств
     * @return string[]
     */
    public static function getTypes(): array
    {
        if (! self::$types) {
            foreach (get_declared_classes() as $class) {
                if (is_subclass_of($class, AbstractOption::class)) {
                    self::$types[$class::getCode()] = $class::getName();
                }
            }
        }

        return self::$types;
    }

    /**
     * Получить объект свойства опции
     * @param string $code
     * @return AbstractOption|null
     */
    public static function getOptionTypeClass(string $code): ?AbstractOption
    {
        if (! isset(self::$typeClasses[$code])) {
            foreach (get_declared_classes() as $class) {
                if (is_subclass_of($class, AbstractOption::class) && $class::getCode() === $code) {
                    self::$typeClasses[$code] = new $class;

                    return self::$typeClasses[$code];
                }
            }
        }

        return self::$typeClasses[$code] ?? null;
    }
}
