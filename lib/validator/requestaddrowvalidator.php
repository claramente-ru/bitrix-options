<?php
declare(strict_types=1);

namespace Claramente\Options\Validator;

use Claramente\Options\Services\OptionTypes;
use Exception;

class RequestAddRowValidator extends AbstractRequestValidator
{

    /**
     * @throws Exception
     */
    public function validate(): array
    {
        if (empty($this->fields['option_type'])) {
            throw new Exception('Не передан тип данных опции');
        }

        $validOptionTypes = array_keys(OptionTypes::getTypes());

        if (! in_array($this->fields['option_type'], $validOptionTypes)) {
            throw new Exception('Неизвестный тип данных опции: ' . $this->fields['option_type']);
        }

        if (empty($this->fields['id'])) {
            throw new Exception('Не передан id для опции');
        }


        return $this->fields;
    }
}