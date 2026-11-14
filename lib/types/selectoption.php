<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use Bitrix\Main\HttpRequest;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use CAdminForm;

/**
 * Список. Опция для модуля claramente.options
 */
class SelectOption extends AbstractOption
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Список';
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return 'select';
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
        // Объединение без перенумерации: ключ элемента списка и есть сохраненное значение
        $values = ['' => 'Не установлено'] + $this->getListValues($option);

        $form->AddDropDownField(
            id: $this->getPostOptionId($postName, $option),
            content: $this->getOptionLabel($option),
            required: false,
            arSelect: $values,
            value: $option->value
        );
    }

    /**
     * Элементы списка: ключ => название.
     * Старые настройки (без признака stable_keys) нумеровались по порядку, поэтому
     * для них ключи приводятся к 0..n-1 так же, как раньше при выводе списка
     * @param OptionEntityStructure $option
     * @return array
     */
    public function getListValues(OptionEntityStructure $option): array
    {
        $settings = $this->getSettings($option);
        $values = is_array($settings['values'] ?? null) ? $settings['values'] : [];
        $values = array_filter($values, static fn($value): bool => is_scalar($value) && trim((string)$value) !== '');
        if (empty($settings['stable_keys'])) {
            return array_values($values);
        }
        ksort($values);

        return $values;
    }

    /**
     * НЕ ПОДДЕРЖИВАЕТСЯ!
     * Множественный вывод свойства в административном разделе
     * Значение опции задается для языка
     * @param  string  $postName
     * @param  CAdminForm  $form
     * @param  OptionEntityStructure  $option
     * @return void
     */
    public function getFormFieldMultipleValue(
        string                $postName,
        CAdminForm            &$form,
        OptionEntityStructure $option
    ): void
    {
        // Вывод информации о том, что тип данных не поддерживает работу с языками
       parent::showTypeNotSupportedForLangs($postName, $form, $option);
    }

    /**
     * Обновление дополнительных настроек
     * @param HttpRequest $request
     * @param OptionEntityStructure $option
     * @return string|null
     */
    public function beforeSaveSettings(HttpRequest $request, OptionEntityStructure $option): ?string
    {
        $values = $request->getPost('option')['settings']['values'] ?? [];
        $values = is_array($values) ? $values : [];
        $result = [];
        foreach ($values as $key => $value) {
            // Ключи сохраняются: удаление элемента из середины не сдвигает значения других элементов
            if (is_numeric($key) && is_scalar($value) && trim((string)$value) !== '') {
                $result[(int)$key] = (string)$value;
            }
        }

        return serialize([
            'values' => $result,
            'stable_keys' => true,
        ]);
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
        $selectIndex = 0;
        $form->AddSection('option-settings', 'Настройки свойства');
        foreach ($this->getListValues($option) as $key => $value) {
            $form->AddEditField('option[settings][values][' . (int)$key . ']', '📑 Элемент списка', false, [], $value);
            $selectIndex = max($selectIndex, (int)$key + 1);
        }
        $form->AddEditField('option[settings][values][' . $selectIndex . ']', '📑 Элемент списка', false);
        $form->BeginCustomField('option-add', 'test test');
        echo '
<tr>
<td></td>
<td><input type="button" value="Добавить еще" title="Добавить еще" onclick="addNewElement()" class="adm-btn"></td>
<td></td>
<script>
        function addNewElement() {
            // Находим последний элемент списка
            let elements = document.querySelectorAll(\'tr[id^="tr_option[settings][values]"]\');
            let lastElement = elements[elements.length - 1]
            
            // Получаем текущий индекс
            let lastIndex = lastElement ? parseInt(lastElement.id.match(/\d+/)[0]) : -1;
            
            // Увеличиваем индекс на 1
            let newIndex = lastIndex + 1;
            
            // Клонируем последний элемент
            let newElement = lastElement.cloneNode(true);
            
            // Обновляем id и name нового элемента
            newElement.id = `tr_option[settings][values][${newIndex}]`;
            let input = newElement.querySelector(\'input[type="text"]\');
            input.name = `option[settings][values][${newIndex}]`;
            input.value = ""; // Очищаем значение в новом поле
            
            // Вставляем новый элемент после последнего элемента
            lastElement.insertAdjacentElement("afterend", newElement);
        }
    </script>
</tr>
';
        $form->EndCustomField('option-add', '');
    }
}
