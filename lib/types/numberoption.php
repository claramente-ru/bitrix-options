<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use Bitrix\Main\HttpRequest;
use CAdminForm;
use Claramente\Options\Structures\Entity\OptionEntityStructure;

/**
 * Число (целое или десятичная дробь, без знака). Опция для модуля claramente.options
 *
 * Правила:
 * - допускаются только цифры и один разделитель дробной части: точка или запятая; хранится точка
 * - допускаются ".5" и "5."; пробелы по краям отбрасываются
 * - любое другое значение (знаки, пробелы внутри, буквы, 1e3, несколько разделителей) — ошибка «Значение должно быть числом»
 * - значение хранится строкой в нормализованном виде: без ведущих нулей целой части,
 *   без незначащих нулей дробной части (12,50 -> 12.5; 007 -> 7)
 * - округления нет: если знаков после разделителя больше точности, значение отклоняется с ошибкой
 * - точность 0 означает «только целые числа»
 * - не более self::MAX_DIGITS значащих цифр в целой и дробной части вместе: столько float хранит без искажений,
 *   поэтому cm_option() возвращает int или float, равный сохраненному значению
 * - при сохранении значение не проходит через float
 */
class NumberOption extends AbstractOption
{
    // Точность по умолчанию (знаков после разделителя)
    public const DEFAULT_SCALE = 2;

    // Максимальная точность
    public const MAX_SCALE = 10;

    // Максимальное количество значащих цифр (целая и дробная часть вместе)
    public const MAX_DIGITS = 15;

    // Размер поля ввода
    public const DEFAULT_SIZE = 20;

    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Число';
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return 'number';
    }

    /**
     * Значение числом: int для целых, float для дробных, null если значение пустое или некорректное
     * @param OptionEntityStructure $option
     * @return int|float|null
     */
    public function getValue(OptionEntityStructure $option): int|float|null
    {
        return self::toNumber($option->value);
    }

    /**
     * Значения для языков числами
     * @param OptionEntityStructure $option
     * @return array
     */
    public function getValuesForLangs(OptionEntityStructure $option): array
    {
        $values = parent::getValuesForLangs($option);
        foreach ($values as &$value) {
            $value['value'] = self::toNumber(self::toString($value['value'] ?? null));
        }

        return $values;
    }

    /**
     * Нормализация перед сохранением (значение уже проверено @see self::validateValue())
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
        if (! $multiple) {
            return self::normalize(self::toString(is_array($value) ? ($value['value'] ?? null) : $value));
        }
        if (! is_array($value)) {
            return null;
        }
        foreach ($value as &$item) {
            $item['value'] = self::normalize(self::toString($item['value'] ?? null)) ?? '';
        }

        return parent::getValueBeforeSave($option, $value, true);
    }

    /**
     * Проверка значения: формат числа и точность
     * @param OptionEntityStructure $option
     * @param mixed $value
     * @param bool $multiple
     * @return string|null
     */
    public function validateValue(OptionEntityStructure $option, mixed $value, bool $multiple = false): ?string
    {
        $items = $multiple
            ? (is_array($value) ? $value : [])
            : [['value' => is_array($value) ? ($value['value'] ?? null) : $value]];
        foreach ($items as $item) {
            $itemValue = $item['value'] ?? null;
            if (null === $itemValue || $itemValue === '') {
                continue;
            }
            $error = $this->validateNumber($option, self::toString($itemValue));
            if (null !== $error) {
                return $multiple ? sprintf('%s (язык %s)', $error, $item['lang'] ?? '') : $error;
            }
        }

        return null;
    }

    /**
     * Сохраненное значение должно подходить под новую точность
     * @param OptionEntityStructure $option
     * @return string|null
     */
    public function validateStoredValue(OptionEntityStructure $option): ?string
    {
        if ($option->singleValueForAllLang) {
            return $this->validateValue($option, $option->value);
        }
        $values = @unserialize((string)$option->value, ['allowed_classes' => false]);

        return is_array($values) ? $this->validateValue($option, $values, true) : null;
    }

    /**
     * Проверка точности. Пустое поле — точность по умолчанию
     * @param HttpRequest $request
     * @return string|null
     */
    public function validateSettings(HttpRequest $request): ?string
    {
        $scale = trim((string)($request->getPost('option')['settings']['scale'] ?? ''));
        if ($scale !== '' && (! ctype_digit($scale) || (int)$scale > self::MAX_SCALE)) {
            return sprintf('Точность должна быть целым числом от 0 до %d', self::MAX_SCALE);
        }

        return null;
    }

    /**
     * Сохранение настроек
     * @param HttpRequest $request
     * @param OptionEntityStructure $option
     * @return string|null
     */
    public function beforeSaveSettings(HttpRequest $request, OptionEntityStructure $option): ?string
    {
        $settings = $request->getPost('option')['settings'] ?? [];
        $scale = trim((string)($settings['scale'] ?? ''));

        return serialize([
            'scale' => $scale === '' ? self::DEFAULT_SCALE : (int)$scale,
            'size' => (int)($settings['size'] ?? 0) ?: self::DEFAULT_SIZE,
        ]);
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
        $form->AddSection('option-settings', 'Настройки свойства');
        $form->AddEditField(
            'option[settings][scale]',
            sprintf('🔢 Точность: знаков после разделителя (0 — только целые, максимум %d)', self::MAX_SCALE),
            false,
            [],
            (string)$this->getScale($option)
        );
        $form->AddEditField('option[settings][size]', '📏 Размер поля ввода', false, [], (string)$this->getSize($option));
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
        $form->BeginCustomField($postName, $option->name);
        echo $this->getFieldHeader($option);
        echo $this->getFieldInput($this->getPostOptionId($postName, $option), $this->getSize($option), $option->value);
        echo '<span style="margin-left: 10px;font-style: italic;font-size: 10px;">* '
            . htmlspecialcharsbx($this->getRulesDescription($option)) . '</span>';
        echo $this->getFieldFooter();
        $form->EndCustomField($postName);
    }

    /**
     * Множественный вывод свойства в административном разделе
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
        $tableId = 'table_' . $option->id;
        $regexp = addcslashes(preg_quote($postName, '/'), '\\');

        $form->BeginCustomField($postName, $option->name);
        echo $this->getFieldHeader($option);
        $html = "<table id='{$tableId}' data-option-type='{$this->getCode()}'>";
        // В поле выводится сохраненная строка: float вывел бы маленькие числа как 1.0E-10
        foreach (array_values(parent::getValuesForLangs($option)) as $i => $value) {
            $html .= $this->getOptionLangRow(
                sprintf('%s[%d][value]', $postName, $i),
                sprintf('%s[%d][lang]', $postName, $i),
                is_array($value) ? $value : [],
                ['size' => $this->getSize($option)]
            );
        }
        $html .= "
    <tr>
        <td colspan='2'>" . $this->getAddButton($tableId, $regexp) . "</td>
    </tr>
</table>";
        echo $html;
        echo '<div style="font-style: italic;font-size: 10px;">* ' . htmlspecialcharsbx($this->getRulesDescription($option)) . '</div>';
        echo $this->getFieldFooter();
        $form->EndCustomField($postName);
    }

    /**
     * Отрисовка строки таблицы значение + язык
     * @param string $fieldId
     * @param string $selectFieldId
     * @param array $value
     * @param array $params
     * @return string
     */
    public function getOptionLangRow(string $fieldId, string $selectFieldId, array $value, array $params = []): string
    {
        return "
    <tr>
        <td>" . $this->getFieldInput($fieldId, (int)($params['size'] ?? self::DEFAULT_SIZE), $value['value'] ?? null) . "</td>
        <td>" . $this->getLanguageDropdown($selectFieldId, $value['lang'] ?? null) . "</td>
    </tr>";
    }

    /**
     * Описание правил ввода для подписи у поля
     * @param OptionEntityStructure $option
     * @return string
     */
    public function getRulesDescription(OptionEntityStructure $option): string
    {
        $scale = $this->getScale($option);

        return $scale === 0
            ? 'Целое число'
            : sprintf('Число, не более %d зн. после запятой или точки', $scale);
    }

    /**
     * Точность из настроек
     * @param OptionEntityStructure $option
     * @return int
     */
    public function getScale(OptionEntityStructure $option): int
    {
        return (int)$this->getSettingValue($option, 'scale', self::DEFAULT_SCALE);
    }

    /**
     * Размер поля ввода
     * @param OptionEntityStructure $option
     * @return int
     */
    public function getSize(OptionEntityStructure $option): int
    {
        return (int)$this->getSettingValue($option, 'size', self::DEFAULT_SIZE) ?: self::DEFAULT_SIZE;
    }

    /**
     * Нормализованная строка числа ("12.5") или null, если строка пустая или не является числом
     * @param string|null $value
     * @return string|null
     */
    public static function normalize(?string $value): ?string
    {
        $number = null === $value ? null : self::parse($value);

        return null === $number ? null : $number['int'] . ($number['frac'] !== '' ? '.' . $number['frac'] : '');
    }

    /**
     * Нормализованная строка в int (целое) или float (дробное)
     * @param string|null $value
     * @return int|float|null
     */
    protected static function toNumber(?string $value): int|float|null
    {
        $normalized = self::normalize($value);
        if (null === $normalized) {
            return null;
        }

        return str_contains($normalized, '.') ? (float)$normalized : (int)$normalized;
    }

    /**
     * Проверка числа по правилам опции
     * @param OptionEntityStructure $option
     * @param string|null $value
     * @return string|null
     */
    protected function validateNumber(OptionEntityStructure $option, ?string $value): ?string
    {
        $number = null === $value ? null : self::parse($value);
        if (null === $number) {
            return 'Значение должно быть числом';
        }
        $scale = $this->getScale($option);
        if (strlen($number['frac']) > $scale) {
            return $scale === 0
                ? 'Допускаются только целые числа'
                : sprintf('Допускается не более %d зн. после разделителя, округление не выполняется', $scale);
        }
        if (strlen(ltrim($number['int'] . $number['frac'], '0')) > self::MAX_DIGITS) {
            return sprintf('Допускается не более %d значащих цифр', self::MAX_DIGITS);
        }

        return null;
    }

    /**
     * Разбор строки на целую и дробную часть без лишних нулей: "007,50" -> ['int' => '7', 'frac' => '5']
     * Null, если строка не является числом
     * @param string $value
     * @return array{int: string, frac: string}|null
     */
    protected static function parse(string $value): ?array
    {
        if (! preg_match('/^(\d*)[.,]?(\d*)$/', trim($value), $matches) || $matches[1] . $matches[2] === '') {
            return null;
        }

        return [
            'int' => ltrim($matches[1], '0') ?: '0',
            'frac' => rtrim($matches[2], '0'),
        ];
    }

    /**
     * Строка из значения формы или кода; остальные типы (bool, массив) — null
     * Float через var_export: (string) округлил бы 0.1 + 0.2 до "0.3" по настройке precision
     * @param mixed $value
     * @return string|null
     */
    protected static function toString(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string)$value,
            is_float($value) => var_export($value, true),
            default => null,
        };
    }

    /**
     * Поле ввода
     * @param string $name
     * @param int $size
     * @param mixed $value
     * @return string
     */
    protected function getFieldInput(string $name, int $size, mixed $value): string
    {
        return '<input type="text" inputmode="decimal" autocomplete="off" name="' . htmlspecialcharsbx($name)
            . '" size="' . $size . '" value="' . htmlspecialcharsbx(is_scalar($value) ? (string)$value : '')
            . '" style="margin-right: 10px;margin-top:3px;margin-bottom:0px;">';
    }
}
