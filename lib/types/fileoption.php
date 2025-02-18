<?php
declare(strict_types=1);

namespace Claramente\Options\Types;

use CAdminForm;
use CFile;
use Claramente\Options\Structures\Entity\OptionEntityStructure;

/**
 * Файл. Опция для модуля claramente.options
 */
class FileOption extends AbstractOption
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Файл';
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return 'file';
    }

    /**
     * Преобразование данных перед сохранением в БД.
     * Null если нужно удалить или
     * @param OptionEntityStructure $option
     * @param array|string|null $value
     * @return string|null
     */
    public function getValueBeforeSave(
        OptionEntityStructure $option,
        mixed                 $value,
    ): ?string
    {
        // Если передали null - удалим файл
        if (null === $value) {
            if ($option->value) {
                CFile::Delete($option->value);
            }

            return null;
        }
        // Если загружаемая информация отсутствует, вернем текущее значение
        if (
            empty($value['name'])
            && ! isset($value['del'])
            && $value['del'] !== 'Y'
        ) {
            return $option->value;
        }

        $save = null;
        // Запрос на удаление файла
        if (isset($value['del']) && $value['del'] === 'Y') {
            if ($option->value) {
                CFile::Delete($option->value);
            }
        } else {
            // Сохранение файла
            $save = CFile::SaveFile($value, 'claramente');
            // Удалим старый файл
            if ($save && $option->value !== $save) {
                CFile::Delete($option->value);
            }
        }

        return is_int($save) ? (string)$save : null;
    }

    /**
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
        $form->AddFileField(
            id: $this->getPostOptionId($postName, $option),
            label: $this->getOptionLabel($option),
            value: $option->value
        );
    }
}
