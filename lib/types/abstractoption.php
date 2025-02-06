<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use Bitrix\Main\HttpRequest;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use CAdminForm;

/**
 * Родительский класс для свойств опций модуля claramente.options
 */
abstract class AbstractOption
{
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
     * Преобразование данных перед сохранением в БД
     * @param OptionEntityStructure $option
     * @param array|string|null $value
     * @return string|null
     */
    public function getValueBeforeSave(
        OptionEntityStructure $option,
        mixed                 $value,
    ): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string)$value;
        }
        if (is_array($value)) {
            return $value['value'] ?? null;
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
        $settings = unserialize($option->settings ?: '');

        return $settings ?: [];
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
    <td class="adm-detail-content-cell-l">' . $option->name . '</td>
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
}
