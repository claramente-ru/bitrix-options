<?php

use Bitrix\Main\Request;
use Claramente\Options\Admin\AdminForm;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Services\OptionTypes;
use Sprint\Migration\VersionManager;

/**
 * @var Request $request
 */

// Сохранение опции
if ($request->isPost()) {
    require_once __DIR__ . '/../save/option.php';
}

// Модуль миграций
$migrationModuleEnabled = CModule::IncludeModule('sprint.migration');


// Действие: Удаление опции
if ($request->get('delete') === 'Y' && is_numeric($request->get('ID'))) {
    ClaramenteOptionsTable::delete((int)$request->get('ID'));
    LocalRedirect('/bitrix/admin/claramente_options.php?lang=' . LANG);
}

// Действие: Миграция опции
if ($request->get('migrate') === 'Y' && is_numeric($request->get('ID'))) {
    $moduleMigrate = new VersionManager();
    $builder = $moduleMigrate->createBuilder(
        'BlankBuilder',
        [
            'builder_name' => 'BlankBuilder',
            'prefix' => 'test',
            'description' => 'test',
            'step_code' => 'migration_create'
        ]
    );

    $builder->buildExecute();
}

// Редактируемая опция
$optionId = $request->get('ID');
$option = null;
$isNewOption = $optionId === null;
if (! $isNewOption) {
    $option = ClaramenteOptionsTable::getOptionById((int)$optionId);
    if (!$option) {
        CAdminMessage::ShowMessage('Опция не найдена');
        return;
    }
}

// Страница редактирования
$form = new AdminForm();
$tabControl = $form->getForm(
    name: 'claramente_edit_option',
    tabs: [
        $form->collectTab('Редактировать', 'option-edit')
    ],
    canExpand: false,
    denyAutosave: true
);
$tabControl->SetShowSettings(false);
$tabControl->Begin([
    'FORM_ACTION' => $request->getRequestUri()
]);
$tabControl->BeginNextFormTab();

// Информация для существующего опция
if (! $isNewOption) {
    $tabControl->AddViewField('option[created_at]', '📅 Дата и время создания', $option->createdAt->format('H:i:s d.m.Y'));
    $tabControl->AddViewField('option[updated_at]', '🕐 Последнее обновление', $option->updatedAt->format('H:i:s d.m.Y'));
}
$tabControl->AddEditField('option[name]', '📝 Название', true, [], $option?->name);
$tabControl->AddEditField('option[code]', '🆔 Код', true, [], $option?->code);
$tabControl->AddEditField('option[sort]', '🔝️️ Сортировка', false, [], $option ? $option->sort : 100);
$tabControl->AddDropDownField('option[tab_id]', '🗂️️ Вкладка', false, $form->getSelectTabs(), $option?->tabId);
$tabControl->AddDropDownField('option[type]', '🛠️ Формат данных', false, OptionTypes::getTypes(), $option?->type);
$tabControl->AddDropDownField('option[site_id]', '🖥️ Сайт', false, $form->getSelectSites(), $option?->siteId);

// Настройки типа данных
if (! $isNewOption) {
    $optionType = $option->getOptionType();
    if (! $optionType) {
        $optionType = OptionTypes::getOptionTypeClass('string');
    }
    $optionType->getFormFieldSettings($request, $tabControl, $option);
}

// Кнопка отменить
$buttonCancel = '<a href="/bitrix/admin/claramente_options.php?lang=' . LANG. '"><input type="button" value="Отменить" title="Отменить" class="adm-btn-cancel"></a>';
// Кнопка удалить
$buttonDelete = '';
if (! $isNewOption) {
    $text = 'Удалить опцию? Это действие нельзя будет отменить';
    $buttonDelete = '
    <a href="/bitrix/admin/claramente_options.php?lang=' . LANG . '&page=option&ID=' . $option->id . '&delete=Y" onclick="return confirm(\'' . $text. '\')">
<input type="button" class="adm-btn" value="🗑️ Удалить">
</a>';
}
// Кнопка "Создать миграцию"
// TODO: в разработке
$buttonMigration = '';
if (false && $migrationModuleEnabled) {
    $text = 'Создать миграцию в модуле sprint.migration? Значение опции будет перенесено вместе с миграцией';
    $buttonMigration = '<a href="/bitrix/admin/claramente_options.php?lang=' . LANG . '&page=option&ID=' . $option->id . '&migrate=Y" onclick="return confirm(\'' . $text. '\')">
<input type="button" class="adm-btn" value="💾️ Создать миграцию">
</a>';
}

$tabControl->Buttons(
    [
        'disabled' => false,
        'btnApply' => true,
    ],
    $buttonCancel . $buttonMigration . $buttonDelete
);

$tabControl->Show();