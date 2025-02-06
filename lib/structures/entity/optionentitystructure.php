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
     * @param int $id
     * @param string $name
     * @param string $code
     * @param string|null $value
     * @param string $type
     * @param string|null $settings
     * @param int $sort
     * @param int|null $tabId
     * @param string|null $siteId
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
            createdAt: $option['CREATED_AT'],
            updatedAt: $option['UPDATED_AT']
        );
    }

    /**
     * Установка нового значения
     * @param mixed $value
     * @return bool
     */
    public function setValue(mixed $value): bool
    {
        $update = ClaramenteOptionsTable::update(
            $this->id,
            [
                'value' => $this->getOptionType()->getValueBeforeSave(
                    option: $this,
                    value: $value
                ),
            ]
        );

        return $update->isSuccess();
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
     * Получить value без обработки
     * @return string|null
     */
    public function getDirtyValue(): ?string
    {
        return $this->value;
    }
}
