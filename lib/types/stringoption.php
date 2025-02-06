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
        $regex = $this->getCheckRegex($option);
        $pattern = '';
        if ($regex) {
            $pattern = 'onkeyup="this.value=this.value.replace(' . $regex . ',\'\')"';
        }

        // Размер поля ввода
        $size = $this->getSize($option);

        // Начало блока ввода
        $form->BeginCustomField($postName, $option->name);
        // Шапка
        echo $this->getFieldHeader($option);
        // Редактирование поле
        echo '<input type="text" name="' . $postName . '[value]" size="' . $size. '" value="' . htmlspecialcharsbx($option->value) . '"' . $pattern . '>';
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
     * Размер поля ввода
     * @param OptionEntityStructure $option
     * @return int
     */
    public function getSize(OptionEntityStructure $option): int
    {
        return intval($this->getSettingValue($option, 'size')) ?: $this::DEFAULT_SIZE;
    }
}
