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
     * Стандартные типы данных
     * @var array|array[]
     */
    public static array $defaultTypes = [
        'string' => 'Claramente\Options\Types\StringOption',
        'strings' => 'Claramente\Options\Types\StringsOption',
        'bool' => 'Claramente\Options\Types\BoolOption',
        'date' => 'Claramente\Options\Types\DateOption',
        'select' => 'Claramente\Options\Types\SelectOption',
        'text' => 'Claramente\Options\Types\TextOption',
        'file' => 'Claramente\Options\Types\FileOption'
    ];

    /**
     * Типы к классам
     * @var AbstractOption[]
     */
    private static array $typeClasses = [];

    /**
     * Получить все возможные типы свойств.
     * Код => Class
     * @return string[]
     */
    public static function getTypes(): array
    {
        $types = [];
        foreach (self::$defaultTypes as $code => $class) {
            if (! class_exists($class)) {
                continue;
            }
            $types[$code] = $class;
        }
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, AbstractOption::class)) {
                $types[$class::getCode()] = $class;
            }
        }

        return $types;
    }

    /**
     * Получить все возможные типы свойств.
     * Код => Имя
     * @return string[]
     */
    public static function getTypeCodeNames(): array
    {
        $result = [];
        foreach (self::getTypes() as $code => $class) {
            /**
             * @var AbstractOption $class
             */
            $result[$code] = $class::getName();
        }

        return $result;
    }

    /**
     * Получить объект свойства опции
     * @param string $code
     * @return AbstractOption|null
     */
    public static function getOptionTypeClass(string $code): ?AbstractOption
    {
        if (! isset(self::$typeClasses[$code])) {
            $types = self::getTypes();
            if (array_key_exists($code, $types)) {
                self::$typeClasses[$code] = new $types[$code];
            }
        }

        return self::$typeClasses[$code] ?? null;
    }
}
