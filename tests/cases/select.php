<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use Claramente\Options\Types\SelectOption;

/**
 * Форма, которая запоминает выпадающие списки и поля ввода
 */
function selectForm(): CAdminForm
{
    return new class extends CAdminForm {
        public array $dropDowns = [];
        public array $edits = [];

        public function AddDropDownField(...$arguments): void
        {
            $this->dropDowns[] = $arguments;
        }

        public function AddEditField($id, $label, $required = false, $params = [], $value = false): void
        {
            $this->edits[$id] = $value;
        }
    };
}

/**
 * Форма настроек списка (скрипт кнопки «Добавить еще» в проверке не нужен)
 */
function selectSettingsForm(CAdminForm $form, OptionEntityStructure $option): void
{
    ob_start();
    (new SelectOption())->getFormFieldSettings(new HttpRequest(), $form, $option);
    ob_end_clean();
}

/**
 * Элементы выпадающего списка значения опции
 */
function selectDropDown(?string $settings, ?string $value = null): array
{
    $form = selectForm();
    (new SelectOption())->getFormFieldValue('options[1]', $form, fixture($settings, 'select', $value));

    return $form->dropDowns[0]['arSelect'];
}

/**
 * Сохранить настройки списка через форму параметра
 */
function selectSaveSettings(array $values): array
{
    ClaramenteOptionsTable::$options = [1 => fixture(null, 'select', '2')];
    Application::$request = new HttpRequest(['option' => [
        'code' => 'sample', 'name' => 'Sample', 'type' => 'select', 'settings' => ['values' => $values],
    ]], ['ID' => 1]);
    runAdminScript(__DIR__ . '/../../admin/save/option.php', fakeUser(true));

    return unserialize(ClaramenteOptionsTable::$options[1]->settings, ['allowed_classes' => false]);
}

test('select: удаление элемента из середины не сдвигает значения', function (): void {
    // Элементы A(0), B(1), C(2), в опции выбран C. Администратор очищает поле B и сохраняет параметр
    $saved = selectSaveSettings(['A', '', 'C']);
    assertSame([0 => 'A', 2 => 'C'], $saved['values'], 'ключи сохранены');
    assertSame(true, $saved['stable_keys'], 'признак сохраненных ключей');

    $dropDown = selectDropDown(serialize($saved), '2');
    assertSame(['' => 'Не установлено', 0 => 'A', 2 => 'C'], $dropDown, 'значение 2 по-прежнему C, значения 1 больше нет');
});

test('select: форма настроек выводит элементы под их ключами', function (): void {
    $form = selectForm();
    selectSettingsForm($form, fixture(serialize(['values' => [0 => 'A', 2 => 'C'], 'stable_keys' => true]), 'select'));
    assertSame([
        'option[settings][values][0]' => 'A',
        'option[settings][values][2]' => 'C',
        'option[settings][values][3]' => false,
    ], $form->edits, 'поля A, C и пустое поле нового элемента');
});

test('select: старые настройки без признака читаются как раньше, по порядку', function (): void {
    // Раньше array_merge перенумеровывал ключи: [0 => A, 2 => C] выводился как A(0), C(1),
    // и сохраненное значение 1 означало C. Так и остается до следующего сохранения параметра
    $legacy = serialize(['values' => [0 => 'A', 2 => 'C']]);
    assertSame(['' => 'Не установлено', 0 => 'A', 1 => 'C'], selectDropDown($legacy, '1'), 'выпадающий список');

    $form = selectForm();
    selectSettingsForm($form, fixture($legacy, 'select'));
    assertSame(['option[settings][values][0]' => 'A', 'option[settings][values][1]' => 'C', 'option[settings][values][2]' => false], $form->edits, 'форма настроек');
});

test('select: пустые и нестроковые элементы не попадают в список', function (): void {
    assertSame(['' => 'Не установлено', 1 => 'B'], selectDropDown(serialize(['values' => [0 => '  ', 1 => 'B', 2 => ['x']], 'stable_keys' => true])), 'список');
    assertSame([1 => 'B'], selectSaveSettings([' ', 'B', ['x'], 'key' => 'D'])['values'], 'сохранение');
});
