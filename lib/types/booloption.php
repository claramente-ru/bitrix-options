<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use Claramente\Options\Structures\Entity\OptionEntityStructure;
use CAdminForm;

/**
 * Флаг. Опция для модуля claramente.options
 */
class BoolOption extends AbstractOption
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Флаг';
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return 'bool';
    }

    /**
     * Преобразование value
     * @param OptionEntityStructure $option
     * @return bool
     */
    public function getValue(OptionEntityStructure $option): bool
    {
        return $option->value === 'Y';
    }

    /**
     * Вывод свойства в административном разделе
     * @param string $postName
     * @param CAdminForm $form
     * @param OptionEntityStructure $option
     * @return void
     */
    public function getFormFieldValue(
        string                $postName,
        CAdminForm            &$form,
        OptionEntityStructure $option
    ): void
    {
        $form->AddCheckBoxField(
            id: $this->getPostOptionId($postName, $option),
            content: trim($option->name . ' ' . $option->siteId),
            required: false,
            value: ['Y', 'N'],
            checked: $option->value === 'Y'
        );
    }
}
