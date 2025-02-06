<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use CAdminForm;
use Claramente\Options\Structures\Entity\OptionEntityStructure;

/**
 * Текст. Опция для модуля claramente.options
 */
class TextOption extends AbstractOption
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Текст';
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return 'text';
    }

    /**
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
        $form->AddTextField(
            id: $this->getPostOptionId($postName, $option),
            label: trim($option->name . ' ' . $option->siteId),
            value: $option->value,
            arParams: [
                'cols' => 60,
                'rows' => 10
            ]
        );
    }
}
