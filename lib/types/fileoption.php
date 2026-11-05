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
     * Удаление файлов после удаления опции: одного файла или файлов всех языков
     * @param OptionEntityStructure $option
     * @return void
     */
    public function onAfterDelete(OptionEntityStructure $option): void
    {
        if (! $option->value) {
            return;
        }
        if ($option->singleValueForAllLang) {
            if (is_numeric($option->value)) {
                CFile::Delete((int)$option->value);
            }
            return;
        }
        $values = @unserialize($option->value, ['allowed_classes' => false]);
        foreach (is_array($values) ? $values : [] as $item) {
            if (is_array($item) && is_numeric($item['value'] ?? null)) {
                CFile::Delete((int)$item['value']);
            }
        }
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
        mixed $value,
        bool $multiple = false,
    ): ?string {
        return $multiple === true
            ? $this->getMultipleValueBeforeSave($option, $value)
            : $this->getSingleValueBeforeSave($option, $value);
    }

    /**
     * Преобразование данных перед сохранением в БД для единичного значения
     * @param  OptionEntityStructure  $option
     * @param  mixed  $value
     * @return string|null
     */
    private function getSingleValueBeforeSave(
        OptionEntityStructure $option,
        mixed $value
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
            && (! isset($value['del']) || $value['del'] !== 'Y')
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
     * Преобразование данных перед сохранением в БД для множественного значения (для разных языков)
     * @param  OptionEntityStructure  $option
     * @param  array  $values
     * @return string|null
     */
    private function getMultipleValueBeforeSave(
        OptionEntityStructure $option,
        array $values
    ): ?string
    {
        // Получение множественного значения (отдельное значение для каждого языка)
        $currentValue = $option->getValuesForLangs();
        $result = [];
        $seenLangs = [];
        foreach ($values as $index => $data) {
            $value = $data['value'];
            // Если передали null или стоит флаг - удалить файл, пропускаем значение
            if (null === $value || isset($value['del']) && $value['del'] === 'Y') {
                continue;
            }
            // Если значение для языка уже задано, пропускаем значение
            if (in_array($data['lang'], $seenLangs)) {
                continue;
            }

            // Если загружаемая информация отсутствует, добавим текущее значение в результат
            if (
                empty($value['name'])
                && (! isset($value['del']) || $value['del'] !== 'Y')
                && $currentValue[$index]['value']
            ) {
                $result[] = [
                    'value' => $currentValue[$index]['value'],
                    'lang' => $data['lang'],
                ];
                $seenLangs[] = $data['lang'];

                continue;
            }

            // Сохранение файла
            $save = CFile::SaveFile($value, 'claramente');

            // Если сохранение успешно, добавим текущее значение в результат
            if ($save) {
                $result[] = [
                    'value' => (string)$save,
                    'lang' => $data['lang'],
                ];
                $seenLangs[] = $data['lang'];
            }
        }

        // Получим все id файлов новых значений
        $newFileValues = array_column($result, 'value');
        // Удалим старые файлы
        foreach ($currentValue as $data) {
            if (! in_array($data['value'], $newFileValues)) {
                CFile::Delete($data['value']);
            }
        }

        return ! empty($result) ? serialize($result) : null;
    }

    /**
     * Вывод поля загрузки/отображения файла для единичного значения
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

    /**
     * Вывод полей загрузки/отображения файла для множественного значения
     * Выбор файлов для каждого языка
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
        // Получение множественного значения (отдельное значение для каждого языка)
        $values = $option->getValuesForLangs();
        // Количество значений
        $numberFields = count($values);
        $tableId = 'table_' . $option->id;
        // Часть регулярного выражения для добавления новых строк для записи значений (в js)
        $regexp = addcslashes(preg_quote($postName, '/'), '\\') . '|' . str_replace(['[', ']'], '_', $postName);
        // Начало блока ввода
        $form->BeginCustomField($postName, $option->name);
        // Шапка
        echo $this->getFieldHeader($option);
        // Вывод значений в таблице в 2 колонки файл и язык
        $html =  "<table id='{$tableId}' data-option-type='{$this->getCode()}' style='border-collapse: separate; border-spacing: 0 20px;'>";
        for ($i = 0; $i < $numberFields; $i++) {
            $fieldId = sprintf('%s[%d][value]', $postName, $i);
            $selectFieldId = sprintf('%s[%d][lang]', $postName, $i);
            $html .= $this->getOptionLangRow($fieldId, $selectFieldId, $values[$i]);
        }
        // Вывод кнопки добавления новых строк для добавления значений для языка
        $html .= "
    <tr>
        <td colspan='2'>" . $this->getAddButton($tableId, $regexp) . "</td>
    </tr>
</table>";

        echo $html;
        // Подвал
        echo $this->getFieldFooter();
        // Конец блока ввода
        $form->EndCustomField($postName);
    }

    /**
     * Отрисовка строки таблицы значение + язык
     * @param  string  $fieldId
     * @param  string  $selectFieldId
     * @param  array  $value
     * @param  array  $params
     * @return string
     */
    public function getOptionLangRow(string $fieldId, string $selectFieldId, array $value, array $params = []): string
    {
        return "
    <tr>
        <td>" . $this->getFileField($fieldId, $value['value']) . "</td>
        <td>" . $this->getLanguageDropdown($selectFieldId, $value['lang'] ?? null) . "</td>
    </tr>";
    }

    /**
     * Отрисовка поля загрузки/отображения файла
     * @param  string  $name
     * @param  string  $value
     * @return string
     */
    public function getFileField(string $name, string $value): string
    {
        $html = CFile::InputFile($name, 20, $value, bShowNotes: false);
        if ($value) {
            $file = CFile::GetFileArray($value);
            $isImage = $file && (CFile::IsImage($file['SRC']) || str_contains((string)($file['CONTENT_TYPE'] ?? ''), 'image'));
            $showFile = $isImage === true ? CFile::ShowImage($value, 150, 150, "border=0", "", true) : CFile::ShowFile($value, 150, 150, "border=0", "", true);
            $html .= '<div class="adm-detail-file-image">' . $showFile . "</div>";

        }

        return $html;
    }
}
