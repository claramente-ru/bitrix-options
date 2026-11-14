<?php
declare(strict_types=1);

namespace Claramente\Options\Structures\Entity;

use Bitrix\Main\Type\DateTime;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Types\AbstractOption;
use Claramente\Options\Services\OptionTypes;

/**
 * Структура объекта данных сущности @see ClaramenteOptionsTable
 */
final class OptionEntityStructure
{
    /**
     * Ошибка последнего сохранения значения
     * @var string|null
     */
    private ?string $lastError = null;

    /**
     * @param int $id
     * @param string $name
     * @param string $code
     * @param string|null $value
     * @param string $type
     * @param string|null $settings
     * @param int $sort
     * @param int|null $tabId
     * @param string|null $siteId
     * @param bool  $singleValueForAllLang
     * @param bool $isAdminOnly
     * @param DateTime $createdAt
     * @param DateTime $updatedAt
     */
    public function __construct(
        public int      $id,
        public string   $name,
        public string   $code,
        public ?string  $value,
        public string   $type,
        public ?string  $settings,
        public int      $sort,
        public ?int     $tabId,
        public ?string  $siteId,
        public bool     $singleValueForAllLang,
        public bool     $isAdminOnly,
        public DateTime $createdAt,
        public DateTime $updatedAt,
    )
    {
    }

    /**
     * Объект из массива
     * @param array $option
     * @return self
     */
    public static function fromArray(array $option): self
    {
        return new self(
            id: intval($option['ID']),
            name: $option['NAME'],
            code: $option['CODE'],
            value: $option['VALUE'],
            type: $option['TYPE'],
            settings: $option['SETTINGS'],
            sort: intval($option['SORT']),
            tabId: is_numeric($option['TAB_ID']) ? intval($option['TAB_ID']) : null,
            siteId: $option['SITE_ID'],
            singleValueForAllLang: boolval($option['SINGLE_VALUE_FOR_ALL_LANG']),
            isAdminOnly: boolval($option['IS_ADMIN_ONLY']),
            createdAt: $option['CREATED_AT'],
            updatedAt: $option['UPDATED_AT']
        );
    }

    /**
     * Пустая структура для новой опции
     * @return self
     */
    public static function empty(): self
    {
        return new self(
            id: 0,
            name: '',
            code: '',
            value: null,
            type: 'string',
            settings: null,
            sort: 100,
            tabId: null,
            siteId: null,
            singleValueForAllLang: true,
            isAdminOnly: false,
            createdAt: new DateTime(),
            updatedAt: new DateTime()
        );
    }

    /**
     * Установка нового значения
     * Значение предварительно проверяется типом опции (@see AbstractOption::validateValue())
     * При ошибке значение не сохраняется, текст ошибки доступен через @see self::getLastError()
     * @param mixed $value
     * @return bool
     */
    public function setValue(mixed $value): bool
    {
        $this->lastError = null;
        $optionType = $this->getOptionType();
        if (null === $optionType) {
            $this->lastError = sprintf('Ошибка конфигурации: тип данных %s опции %s не найден', $this->type, $this->code);
            return false;
        }

        $multiple = ! $this->singleValueForAllLang;
        // Для отдельных значений по языкам ожидается массив [['value' => ..., 'lang' => ...], ...]
        if ($multiple && ! is_array($value) && null !== $value) {
            $this->lastError = sprintf(
                'Ошибка вызова: опция %s хранит значения по языкам, ожидается массив [[\'value\' => ..., \'lang\' => ...]], передан %s',
                $this->code,
                get_debug_type($value)
            );
            return false;
        }

        $error = $optionType->validateValue($this, $value, $multiple);
        if (null !== $error) {
            $this->lastError = $error;
            return false;
        }

        $update = ClaramenteOptionsTable::update(
            $this->id,
            [
                'value' => $optionType->getValueBeforeSave(
                    option: $this,
                    value: $value,
                    multiple: $multiple
                ),
                // В PostgreSQL нет ON UPDATE CURRENT_TIMESTAMP, поэтому время изменения задается явно
                'UPDATED_AT' => new DateTime(),
            ]
        );
        ClaramenteOptionsTable::clearPreloader();

        if (! $update->isSuccess()) {
            $this->lastError = implode(', ', $update->getErrorMessages());
        }

        return $update->isSuccess();
    }

    /**
     * Текст ошибки последнего вызова @see self::setValue()
     * @return string|null
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Текст подсказки к параметру из SETTINGS. Пустая строка, если подсказки нет
     * @return string
     */
    public function getHint(): string
    {
        $settings = is_string($this->settings) && $this->settings !== ''
            ? @unserialize($this->settings, ['allowed_classes' => false])
            : [];
        $hint = is_array($settings) ? ($settings['hint'] ?? null) : null;

        return is_string($hint) && trim($hint) !== '' ? $hint : '';
    }

    /**
     * Получить свойство опции
     * @return AbstractOption|null
     */
    public function getOptionType(): ?AbstractOption
    {
        return OptionTypes::getOptionTypeClass($this->type);
    }

    /**
     * Получить обработанный value через свойство
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->getOptionType()?->getValue($this);
    }

    /**
     * Получить обработанный множественный value (одно значение для каждого языка) через свойство
     * @return array
     */
    public function getValuesForLangs(): array
    {
        return $this->getOptionType()?->getValuesForLangs($this) ?? [];
    }

    /**
     * Получить value без обработки
     * @return string|null
     */
    public function getDirtyValue(): ?string
    {
        return $this->value;
    }
}
