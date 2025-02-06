<?php
declare(strict_types=1);

namespace Claramente\Options\Structures\Entity;

use Claramente\Options\Entity\ClaramenteOptionTabsTable;

/**
 * Структура объекта данных сущности @see ClaramenteOptionTabsTable
 */
final class TabEntityStructure
{
    /**
     * @param int $id
     * @param string $name
     * @param string $code
     * @param int $sort
     */
    public function __construct(
        public int    $id,
        public string $name,
        public string $code,
        public int    $sort,
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
            sort: intval($option['SORT'])
        );
    }
}
