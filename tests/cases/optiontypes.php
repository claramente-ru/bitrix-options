<?php

declare(strict_types=1);

use Claramente\Options\Services\OptionTypes;
use Claramente\Options\Types\AbstractOption;
use Claramente\Options\Types\StringOption;

/**
 * Промежуточный базовый класс проекта: наследует getCode() = 'string' от StringOption
 */
abstract class OptionTypesProjectStringOption extends StringOption
{
}

/**
 * Промежуточный базовый класс проекта без getCode()
 */
abstract class OptionTypesProjectBaseOption extends AbstractOption
{
}

test('option types: абстрактные наследники не попадают в список типов', function (): void {
    $types = OptionTypes::getTypes();
    assertSame(StringOption::class, $types['string'] ?? null, 'тип string не подменен абстрактным наследником');
    assertTrue(! in_array(OptionTypesProjectStringOption::class, $types, true), 'наследник StringOption');
    assertTrue(! in_array(OptionTypesProjectBaseOption::class, $types, true), 'наследник AbstractOption');
    assertSame('custom', array_search(HintsCustomOption::class, $types, true), 'обычный наследник по-прежнему регистрируется');
});

test('option types: объект типа создается для всех зарегистрированных типов', function (): void {
    (new ReflectionProperty(OptionTypes::class, 'typeClasses'))->setValue(null, []);
    foreach (array_keys(OptionTypes::getTypes()) as $code) {
        assertTrue(OptionTypes::getOptionTypeClass($code) instanceof AbstractOption, (string)$code);
    }
    assertTrue(count(OptionTypes::getTypeCodeNames()) > 0, 'названия типов для формы параметра');
});
