<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use Bitrix\Main\Type\Date;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use CAdminForm;

/**
 * Календарь. Опция для модуля claramente.options
 */
class DateOption extends AbstractOption
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Дата';
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return 'date';
    }

    /**
     * Преобразование value
     * @param OptionEntityStructure $option
     * @return Date|null
     */
    public function getValue(OptionEntityStructure $option): ?Date
    {
        return $option->value ? Date::createFromText($option->value) : null;
    }

    /**
     * Вывод поля в административном разделе
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
        $form->AddCalendarField(
            id: $this->getPostOptionId($postName, $option),
            label: trim($option->name . ' ' . $option->siteId),
            value: $option->value
        );
    }
}
