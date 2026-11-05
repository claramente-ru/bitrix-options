<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use Bitrix\Main\HttpRequest;
use CAdminForm;
use Claramente\Options\Structures\Entity\OptionEntityStructure;

/**
 * Строка. Опция для модуля claramente.options
 */
class StringOption extends AbstractOption
{
    // Размер поля ввода
    public const DEFAULT_SIZE = 20;

    // Список готовых решений для регулярных выражений для проверки поля - список
    public const FIELD_CHECK_SELECT = [
        null => 'Любые символы',
        'number' => 'Только цифры',
        'number_dot' => 'Только цифры и точки',
        'letter' => 'Только буквы',
        'letter_space' => 'Только буквы и отступы',
        'number_letter' => 'Только буквы и цифры',
        'number_dot_letter' => 'Только буквы, цифры и точки',
        'number_letter_space' => 'Только буквы, цифры и отступы',
        'number_dot_letter_space' => 'Только буквы, цифры, точки и отступы'
    ];

    // Список готовых решений для регулярных выражений для проверки поля - правила
    public const FIELD_CHECK_SELECT_REGEX = [
        null => '',
        'number' => '/[^0-9]/',
        'number_dot' => '/[^0-9.]/',
        'letter' => '/[^A-Za-zА-Яа-яЁё]/',
        'letter_space' => '/[^A-Za-zА-Яа-яЁё ]/',
        'number_letter' => '/[^A-Za-zА-Яа-яЁё0-9]/',
        'number_dot_letter' => '/[^A-Za-zА-Яа-яЁё0-9.]/',
        'number_letter_space' => '/[^A-Za-zА-Яа-яЁё0-9 ]/',
        'number_dot_letter_space' => '/[^A-Za-zА-Яа-яЁё0-9. ]/',
    ];

    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Строка';
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return 'string';
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
        // Регулярное выражение для проверки
        $pattern = $this->getClientFilterAttribute($option);

        // Размер поля ввода
        $size = $this->getSize($option);

        // Начало блока ввода
        $form->BeginCustomField($postName, $option->name);
        // Шапка
        echo $this->getFieldHeader($option);
        // Редактирование поле
        echo '<input type="text" name="' . htmlspecialcharsbx($postName) . '[value]" size="' . $size. '" value="' . htmlspecialcharsbx($option->value) . '"' . $pattern . '>';
        // Готовое решение для разрешенных символов
        $readyRegex = $this->getSettingValue($option, 'ready_regex');
        if ($readyRegex && isset($this::FIELD_CHECK_SELECT[$readyRegex])) {
            echo '<span style="margin-left: 10px;font-style: italic;font-size: 10px;">* ' . $this::FIELD_CHECK_SELECT[$readyRegex] . '</span>';
        }
        // Подвал
        echo $this->getFieldFooter();
        $form->EndCustomField($postName);
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
        // Размер поля ввода
        $size = $this->getSize($option);
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
        // Вывод значений в таблице в 2 колонки строка и язык
        $html =  "<table id='{$tableId}' data-option-type='{$this->getCode()}'>";
        for ($i = 0; $i < $numberFields; $i++) {
            $fieldId = sprintf('%s[%d][value]', $postName, $i);
            $selectFieldId = sprintf('%s[%d][lang]', $postName, $i);
            $html .= $this->getOptionLangRow($fieldId, $selectFieldId, $values[$i], ['size' => $size]);
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
        <td>" . $this->getFieldInput(
                $fieldId,
                $params['size'] ?? $this::DEFAULT_SIZE,
                is_string($value['value'] ?? null) ? htmlspecialchars($value['value']) : null
            ) . "</td>
        <td>" . $this->getLanguageDropdown($selectFieldId, $value['lang'] ?? null) . "</td>
    </tr>";
    }

    /**
     * Поле ввода
     * @param string $name
     * @param string|null $value
     * @return string
     */
    protected function getFieldInput(string $name, int $size, ?string $value): string
    {
        return '<input type="text" name="' . htmlspecialcharsbx($name) . '" size="' . $size. '" value="' . $value . '" style="margin-right: 10px;margin-top:3px;margin-bottom:0px;">';
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
        $form->AddEditField(
            'option[settings][regex]',
            '🈳 Регулярное выражение для проверки (приоритетнее символов)',
            false,
            [],
            $this->getSettingValue($option, 'regex', '')
        );
        $form->AddDropDownField(
            'option[settings][ready_regex]',
            '🔠 Разрешенные символы',
            false,
            $this::FIELD_CHECK_SELECT,
            $this->getSettingValue($option, 'ready_regex')
        );
        $form->AddEditField(
            'option[settings][size]',
            '📏 Размер поля ввода',
            false,
            [],
            intval($this->getSettingValue($option, 'size')) ?: $this::DEFAULT_SIZE
        );
    }

    /**
     * Итоговое поле для проверки заполнения
     * @param OptionEntityStructure $option
     * @return string|null
     */
    public function getCheckRegex(OptionEntityStructure $option): ?string
    {
        $readyRegex = $this::FIELD_CHECK_SELECT_REGEX[$this->getSettingValue($option, 'ready_regex')] ?? $this::FIELD_CHECK_SELECT_REGEX[null];

        return $this->getSettingValue($option, 'regex') ?: $readyRegex;
    }

    /**
     * Атрибут onkeyup для удаления недопустимых символов в браузере.
     * Регулярное выражение передается в JS как данные (new RegExp(JSON-строка)), а не как код,
     * поэтому текст настройки не может выполнить произвольный скрипт
     * @param OptionEntityStructure $option
     * @return string
     */
    protected function getClientFilterAttribute(OptionEntityStructure $option): string
    {
        $regex = (string)$this->getCheckRegex($option);
        if (! preg_match('~^/(.+)/([a-z]*)$~s', $regex, $matches)) {
            return '';
        }
        // В JS допустимы только флаги i, m, s, u; g добавляется для замены всех вхождений
        $flags = 'g' . preg_replace('/[^imsu]/', '', $matches[2]);
        $js = sprintf(
            'try{this.value=this.value.replace(new RegExp(%s,%s),\'\')}catch(e){}',
            json_encode($matches[1], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            json_encode(count_chars($flags, 3))
        );

        return ' onkeyup="' . htmlspecialcharsbx($js) . '"';
    }

    /**
     * Размер поля ввода
     * @param OptionEntityStructure $option
     * @return int
     */
    public function getSize(OptionEntityStructure $option): int
    {
        return intval($this->getSettingValue($option, 'size')) ?: $this::DEFAULT_SIZE;
    }
}
