<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use Bitrix\Main\HttpRequest;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use CAdminForm;

/**
 * Несколько строк. Опция для модуля claramente.options
 */
class StringsOption extends AbstractOption
{
    // Стандартное значение строк
    public const DEFAULT_FIELDS = 2;

    // Делить строки на линии
    public const DEFAULT_SPLIT_FIELDS = 3;

    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Несколько строк';
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return 'strings';
    }

    /**
     * Преобразование value из БД
     * @param OptionEntityStructure $option
     * @return mixed
     */
    public function getValue(OptionEntityStructure $option): array
    {
        $value = null;
        if ($option->value) {
            $value = unserialize($option->value);
        }

        return is_array($value) ? $value : array_fill(0, $this->getNumberFields($option), '');
    }

    /**
     * Преобразование данных перед сохранением в БД.
     * Null если нужно удалить или
     * @param OptionEntityStructure $option
     * @param array|string|null $value
     * @return string|null
     */
    public function getValueBeforeSave(
        OptionEntityStructure $option,
        mixed                 $value,
    ): ?string
    {
       return is_array($value) ? serialize($value) : null;
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
        // Количество выводимых полей
        $numberFields = $this->getNumberFields($option);
        // Количество строк после которых нужно переносить строки
        $numberSplitFields = $this->getNumberSplitFields($option);

        $values = $option->getValue();

        // Начало блока ввода
        $form->BeginCustomField($postName,  $option->name);
        // Шапка
        echo $this->getFieldHeader($option);
        for ($i = 0; $i < $numberFields; $i++) {
            $fieldId = sprintf('%s[%d]', $postName, $i);
            echo $this->getFieldInput($fieldId, is_string($values[$i] ?? null) ? htmlspecialchars($values[$i]) : null);
            // Перенос строк <br>
            if ($i > 0 && ($i + 1) % $numberSplitFields === 0) {
                echo '<br><br>';
            }
        }
        // Подвал
        echo $this->getFieldFooter();
        $form->EndCustomField($postName);
    }

    /**
     * Редактирование свойства. Дополнительные поля
     * @param HttpRequest $request
     * @param CAdminForm &$form
     * @param OptionEntityStructure $option
     * @return void
     */
    public function getFormFieldSettings(
        HttpRequest           $request,
        CAdminForm            &$form,
        OptionEntityStructure $option
    ): void
    {
        $form->AddSection('option-settings', 'Настройки свойства');
        $form->AddEditField('option[settings][fields]', '🔢 Количество строк', false, [], $this->getNumberFields($option));
        $form->AddEditField('option[settings][split]', '🖇️ Количество строк для переноса строк', false, [], $this->getNumberSplitFields($option));
    }

    /**
     * Количество строк в опции относительно настроек
     * @param OptionEntityStructure $option
     * @return int
     */
    protected function getNumberFields(OptionEntityStructure $option): int
    {
        return intval($this->getSettingValue($option, 'fields', $this::DEFAULT_FIELDS)) ?: $this::DEFAULT_FIELDS;
    }

    /**
     * Количество строк в опции относительно настроек
     * @param OptionEntityStructure $option
     * @return int
     */
    protected function getNumberSplitFields(OptionEntityStructure $option): int
    {
        return intval($this->getSettingValue($option, 'split', $this::DEFAULT_SPLIT_FIELDS)) ?: $this::DEFAULT_SPLIT_FIELDS;
    }

    /**
     * Поле ввода
     * @param string $name
     * @param string|null $value
     * @return string
     */
    protected function getFieldInput(string $name, ?string $value): string
    {
        return '<input type="text" name="' . $name . '" value="' . $value . '" style="margin-right: 10px;margin-top:3px;margin-bottom:0px;">';
    }
}
