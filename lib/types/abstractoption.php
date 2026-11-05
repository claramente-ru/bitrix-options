<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use Bitrix\Main\HttpRequest;
use Bitrix\Main\Localization\LanguageTable;
use Claramente\Options\Helpers\LangHelper;
use Claramente\Options\Helpers\SiteHelper;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use CAdminForm;

/**
 * Родительский класс для свойств опций модуля claramente.options
 */
abstract class AbstractOption
{
    /**
     * Максимальная длина подсказки в символах
     */
    public const HINT_MAX_LENGTH = 2000;

    /**
     * Название свойства
     * @return string
     */
    abstract public static function getName(): string;

    /**
     * Код свойства
     * @return string
     */
    abstract public static function getCode(): string;

    /**
     * Преобразование value из БД
     * @param OptionEntityStructure $option
     * @return mixed
     */
    public function getValue(OptionEntityStructure $option): mixed
    {
        return $option->value;
    }

    /**
     * Преобразование множественного value из БД
     * @param OptionEntityStructure $option
     * @return array
     */
    public function getValuesForLangs(OptionEntityStructure $option): array
    {
        $value = null;

        if ($option->value) {
            $value = unserialize($option->value, ['allowed_classes' => false]);
        }

        if (is_array($value) && ! empty($value)) {
            return $value;
        }

        // Если значение одно для всех языков, вернем его как множественное: одинаковое для каждого языка
        return self::getSingleValueForAllLang($option);
    }

    /**
     * Преобразование value для всех языков (при отмеченной опции - одно значение для всех языков)
     * @param  OptionEntityStructure  $option
     * @return array[]
     */
    public function getSingleValueForAllLang(OptionEntityStructure $option): array
    {
        // Возвращаем пустые данные, для языков не включена настройка - одно значение для всех языков
        if (! $option->singleValueForAllLang) {
            return [['value' => '', 'lang' => '']];
        }

        // Получаем список языков
        $languages = LangHelper::getLanguages();
        if (! empty($languages)) {
            $langIds = array_keys($languages);

            // Возвращаем одно значение для каждого языка
            return array_map(function ($lang) use ($option) {
                return ['value' => $option->getValue(), 'lang' => $lang];
            }, $langIds);
        }

        // Возвращаем пустые данные, если список языков пуст
        return [['value' => '', 'lang' => '']];
    }

    /**
     * Преобразование данных перед сохранением в БД
     * @param OptionEntityStructure $option
     * @param array|string|null $value
     * @return string|null
     */
    public function getValueBeforeSave(
        OptionEntityStructure $option,
        mixed                 $value,
        bool                  $multiple = false,
    ): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string)$value;
        }
        if (is_array($value) && ! $multiple) {
            return $value['value'] ?? null;
        }

        if ($multiple) {
            // Для множественных свойств (отдельное значение для каждого языка) сериализуем данные
            if (! is_array($value)) {
                return null;
            }
            $seenLangs = [];
            foreach ($value as $key => $optionItem) {
                // Удаляем пустые значения
                if ($optionItem['value'] === '') {
                    unset($value[$key]);
                    continue;
                }
                // Удаляем значение, если задано больше одного значения для языка
                if (in_array($optionItem['lang'], $seenLangs)) {
                    unset($value[$key]);
                    continue;
                }

                $seenLangs[] = $optionItem['lang'];
            }
            $value = array_values($value);

            return serialize($value);
        }

        return null;
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
        $form->AddEditField(
            id: $this->getPostOptionId($postName, $option),
            content: trim($option->name . ' ' . $option->siteId),
            required: false,
            value: $option->value
        );
    }

    /**
     * Подсказка к полю, общая для всех типов и языков.
     * Строка формы получает id с префиксом hint_, чтобы скрипт ссылок редактирования
     * (tr[id^='tr_options']) не принимал ее за поле значения.
     * @param string $postName
     * @param CAdminForm $form
     * @param OptionEntityStructure $option
     * @return void
     */
    public function getFormFieldHint(
        string $postName,
        CAdminForm &$form,
        OptionEntityStructure $option
    ): void {
        $hint = $option->getHint();
        if ($hint === '') {
            return;
        }

        $form->AddViewField(
            'hint_' . $postName,
            '',
            sprintf(
                '<p class="adm-info-message">%s</p>',
                nl2br(htmlspecialcharsbx(
                    $hint,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    false
                ))
            )
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
    }

    /**
     * Отрисовка строки таблицы значение + язык
     * @param  string  $fieldId
     * @param  string  $selectFieldId
     * @param  array  $value
     * @param  array  $params
     * @return string
     */
    public function getOptionLangRow(
        string $fieldId,
        string $selectFieldId,
        array $value,
        array $params = []
    ): string
    {
        return '';
    }

    /**
     * Вывод информации о том, что тип данных не поддерживает работу с языками
     * @param  string  $postName
     * @param  CAdminForm  $form
     * @param  OptionEntityStructure  $option
     * @return void
     */
    public function showTypeNotSupportedForLangs(
        string                $postName,
        CAdminForm            &$form,
        OptionEntityStructure $option,
    ): void
    {
        // Начало блока ввода
        $form->BeginCustomField($postName, $option->name);
        // Шапка
        echo $this->getFieldHeader($option);
        echo '<div style="color: red">Не поддерживает несколько значений для разных языков.<br>Для корректной работы свойства отметьте опцию "Одно значение для всех языков"</div>';
        // Конец блока ввода
        $form->EndCustomField($postName);
    }

    /**
     * Получить div id свойства
     * @param string $postName
     * @param OptionEntityStructure $option
     * @return string
     */
    public function getPostOptionId(string $postName, OptionEntityStructure $option): string
    {
        return sprintf('%s[value]', $postName);
    }

    /**
     * Обновление дополнительных настроек
     * @param HttpRequest $request
     * @param OptionEntityStructure $option
     * @return string|null
     */
    public function beforeSaveSettings(HttpRequest $request, OptionEntityStructure $option): ?string
    {
        $values = $request->getPost('option')['settings'] ?? [];

        return is_array($values) ? serialize($values) : null;
    }

    /**
     * Редактирование свойства. Дополнительные поля
     * @param HttpRequest $request
     * @param CAdminForm $form
     * @param OptionEntityStructure $option
     * @return void
     */
    public function getFormFieldSettings(
        HttpRequest           $request,
        CAdminForm            &$form,
        OptionEntityStructure $option
    ): void
    {
    }

    /**
     * Получить настройки свойства
     * @param OptionEntityStructure $option
     * @return array
     */
    public function getSettings(OptionEntityStructure $option): array
    {
        if (! is_string($option->settings) || $option->settings === '') {
            return [];
        }
        $settings = unserialize($option->settings, ['allowed_classes' => false]);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Получить настройку свойства по коду
     * @param OptionEntityStructure $option
     * @param string $setting
     * @param mixed|null $default
     * @return mixed
     */
    public function getSettingValue(
        OptionEntityStructure $option,
        string                $setting,
        mixed                 $default = null
    ): mixed
    {
        return $this->getSettings($option)[$setting] ?? $default;
    }

    /**
     * Шапка поля
     * @param OptionEntityStructure $option
     * @return string
     */
    protected function getFieldHeader(OptionEntityStructure $option): string
    {
        return '<tr id="tr_options[' . $option->id . '][value]">
    <td class="adm-detail-content-cell-l">' . htmlspecialcharsbx($this->getOptionLabel($option)) . '</td>
    <td class="adm-detail-content-cell-r">';
    }

    /**
     * Подвал поля
     * @return string
     */
    protected function getFieldFooter(): string
    {
        return '</td></tr>';
    }

    /**
     * Вывод кнопки добавления новых строк для добавления значений для языка
     * @param  string  $tableId
     * @param  string  $optionName
     * @return string
     */
    protected function getAddButton(string $tableId, string $optionName): string
    {
        return '<input type="button" value="Добавить значение для языка" class="adm-btn-add" onclick="addOptionRow(\'' . $tableId . '\', \'' . $optionName . '\')">';

    }

    /**
     * Получить label для опции
     * @param OptionEntityStructure $option
     * @return string
     */
    protected function getOptionLabel(OptionEntityStructure $option): string
    {
        $label = $option->name;
        // Только для администраторов
        $protected = '';
        if ($option->isAdminOnly) {
            $protected .= ' 🔒';
        }

        // Название сайта
        $siteName = $option->siteId;
        if ($option->siteId) {
            $siteName = sprintf(
                '%s - %s',
                $option->siteId,
                preg_replace('/[)(]/', '', SiteHelper::getSiteName($option->siteId))
            );
        }
        $siteId = $option->siteId ? sprintf(' (%s)', $siteName) : '';

        return trim($label . $siteId . $protected);
    }

    protected function getDropdown(
        string $selectName,
        array $params = [],
        array $options = [],
        ?string $selectedValue = null
    ): string {
        $html = '<select name="' . $selectName . '"';

        foreach ($params as $param) {
            $html .= ' ' . $param;
        }
        $html .= '>';

        foreach ($options as $key => $val) {
            $html .= '<option value="' . htmlspecialcharsbx($key) . '"' . ($selectedValue == $key ? ' selected' : '') . '>'
                . htmlspecialcharsex($val) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    protected function getLanguageDropdown(string $selectName, ?string $selectedValue = null): string
    {
        $languages = LangHelper::getLanguagesNames();

        return $this->getDropdown(selectName: $selectName, options: $languages, selectedValue: $selectedValue);
    }
}
