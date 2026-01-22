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
     * Преобразование множественного value из БД
     * @param OptionEntityStructure $option
     * @return array
     */
    public function getValuesForLangs(OptionEntityStructure $option): array
    {
        $values = null;

        if ($option->value) {
            $values = unserialize($option->value, ['allowed_classes' => false]);
        }

        if (is_array($values) && ! empty($values)) {
            // Приведение каждого значения к bool
            foreach ($values as &$value) {
                $value['value'] = $value['value'] === 'Y';
            }
            return $values;
        }

        // Если значение одно для всех языков, вернем его как множественное: одинаковое для каждого языка
        return parent::getSingleValueForAllLang($option);
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
            content: $this->getOptionLabel($option),
            required: false,
            value: ['Y', 'N'],
            checked: $option->value === 'Y'
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
        $regexp = addcslashes(preg_quote($postName, '/'), '\\') . '|' . str_replace(['[', ']'], '_', $postName);

        // Начало блока ввода
        $form->BeginCustomField($postName, $option->name);
        // Шапка
        echo $this->getFieldHeader($option);
        // Вывод значений в таблице в 2 колонки чекбокс и язык
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
    public function getOptionLangRow(string $fieldId, string $selectFieldId, array $value, array $params = []): string
    {
        return "
    <tr>
        <td>" . $this->getFieldCheckbox(
                $fieldId,
                ($value['value'] ?? null) === true
            ) . "</td>
        <td>" . $this->getLanguageDropdown($selectFieldId, $value['lang'] ?? null) . "</td>
    </tr>";
    }

    /**
     * Отрисовка чекбокса
     * @param  string  $name
     * @param  bool  $checked
     * @return string
     */
    protected function getFieldCheckbox(string $name, bool $checked = false): string
    {
        // Получение id чекбокса из name
        $id = preg_replace('/[\[\]]+/', '_', $name);
        $id = trim($id, '_');
        $html = '<input type="checkbox" name="' . $name . '" value="Y"' . ($checked ? ' checked' : '') . ' id="' . $id . '">';
        $hidden = '<input type="hidden" name="' . $name . '" value="N">';
        return $hidden . $html;
    }
}
