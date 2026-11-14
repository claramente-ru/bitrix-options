<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use CAdminForm;
use Claramente\Options\Helpers\HtmlHelper;
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
     * В значении сохраняется только безопасный HTML
     * @param OptionEntityStructure $option
     * @param mixed $value
     * @param bool $multiple
     * @return string|null
     */
    public function getValueBeforeSave(
        OptionEntityStructure $option,
        mixed                 $value,
        bool                  $multiple = false,
    ): ?string
    {
        return parent::getValueBeforeSave($option, $this->sanitizeValue($value, $multiple), $multiple);
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
            label: $this->getOptionLabel($option),
            value: htmlspecialchars((string)HtmlHelper::decodeForForm($option->value), ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'),
            arParams: [
                'cols' => 60,
                'rows' => 10
            ]
        );
    }

    /**
     * Множественный вывод свойства в административном разделе
     * Значение опции задается для языка
     * @param string $postName
     * @param CAdminForm $form
     * @param OptionEntityStructure $option
     * @return void
     */
    public function getFormFieldMultipleValue(
        string                $postName,
        CAdminForm            &$form,
        OptionEntityStructure $option
    ): void
    {
        // Получение множественного значения (отдельное значение для каждого языка)
        $values = $option->getValuesForLangs();
        // Количество значений
        $numberFields = count($values);
        $tableId = 'table_' . $option->id;
        // Часть регулярного выражения для добавления новых строк для записи значений (в js)
        $regexp = addcslashes(preg_quote($postName, '/'), '\\');

        // Начало блока ввода
        $form->BeginCustomField($postName, $option->name);
        // Шапка
        echo $this->getFieldHeader($option);
        // Вывод значений в таблице в 2 колонки текст и язык
        $html =  "<table id='{$tableId}' data-option-type='{$this->getCode()}'>";
        for ($i = 0; $i < $numberFields; $i++) {
            $fieldId = sprintf('%s[%d][value]', $postName, $i);
            $selectFieldId = sprintf('%s[%d][lang]', $postName, $i);
            $html .= $this->getOptionLangRow($fieldId, $selectFieldId, $values[$i]);
        }
        // Вывод кнопки добавления новых строк для добавления значений для языка
        $html .= "
    <tr>
        <td colspan='2'>" . $this->getAddButton($tableId, $regexp) . "</td>
    </tr>
</table>";

        echo $html;
        // Подвал
        echo $this->getFieldFooter();
        // Конец блока ввода
        $form->EndCustomField($postName);
    }

    /**
     * Отрисовка строки таблицы значение + язык
     * @param  string  $fieldId
     * @param  string  $selectFieldId
     * @param  array  $value
     * @param  array  $params
     * @return string
     */
    public function getOptionLangRow(string $fieldId, string $selectFieldId, array $value, array $params =[]): string
    {
        return "
    <tr>
        <td>" . $this->getFieldText(
                $fieldId,
                $value['value'] ?? null
            ) . "</td>
        <td>" . $this->getLanguageDropdown($selectFieldId, $value['lang'] ?? null) . "</td>
    </tr>";
    }

    /**
     * Поле ввода текста
     * @param string $name
     * @param string|null $value
     * @return string
     */
    protected function getFieldText(string $name, ?string $value): string
    {
        // Без htmlspecialcharsback: введенные текстом сущности (&amp;, &lt;) сохраняются без изменений
        $value = is_string($value) ? htmlspecialcharsbx(HtmlHelper::decodeForForm($value)) : null;

        return '<textarea name="' . $name . '" cols="60" rows="10">' . $value . '</textarea>';
    }
}
