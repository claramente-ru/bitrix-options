<?php

use Bitrix\Main\Request;
use Claramente\Options\Admin\AdminForm;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Services\MigrationService;
use Claramente\Options\Services\OptionTypes;
use Claramente\Options\Types\AbstractOption;
use Sprint\Migration\VersionManager;

/**
 * @var Request $request
 * @var CUser $USER
 */

// Страница доступна только администраторам
if (! $USER->IsAdmin()) {
    CAdminMessage::ShowMessage('Страница доступна только администраторам');
    return;
}

// Сохранение опции
if ($request->isPost()) {
    require_once __DIR__ . '/../save/option.php';
}

// Модуль миграций
$migrationModuleEnabled = CModule::IncludeModule('sprint.migration');

// Открытая опция
$optionId = $request->get('ID');
$option = null;
$isNewOption = $optionId === null;
if (! $isNewOption) {
    $option = ClaramenteOptionsTable::getOptionById((int)$optionId);
    if (! $option) {
        CAdminMessage::ShowMessage('Опция не найдена');
        return;
    }
}

// Удаление и создание миграции выполняются только по ссылке с токеном сессии (защита от CSRF)
$isActionRequest = $request->get('delete') === 'Y' || $request->get('migrate') === 'Y';
$isActionAllowed = ! $isActionRequest || check_bitrix_sessid();
if (! $isActionAllowed) {
    CAdminMessage::ShowMessage('Ошибка. Сессия истекла, обновите страницу и повторите действие');
}

// Действие: Удаление опции (вместе со связанными данными типа, например файлами)
if ($isActionAllowed && $request->get('delete') === 'Y' && null !== $option) {
    $delete = ClaramenteOptionsTable::delete($option->id);
    if ($delete->isSuccess()) {
        $option->getOptionType()?->onAfterDelete($option);
        LocalRedirect('/bitrix/admin/claramente_options.php?lang=' . LANG);
    }
    CAdminMessage::ShowMessage('Ошибка удаления. ' . implode(',', $delete->getErrorMessages()));
}

// Действие: Миграция опции
if ($isActionAllowed && $request->get('migrate') === 'Y' && is_numeric($request->get('ID'))) {
    $optionService = new MigrationService();
    $optionMigrate = $optionService->createMigration((int)$request->get('ID'));
    if (! $optionMigrate) {
        CAdminMessage::ShowMessage('Ошибка создания миграции');
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
// Защита от CSRF: токен сессии проверяется при сохранении
$tabControl->BeginEpilogContent();
echo bitrix_sessid_post();
$tabControl->EndEpilogContent();
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
$tabControl->AddDropDownField('option[type]', '🛠️ Формат данных', false, OptionTypes::getTypeCodeNames(), $option?->type);
$tabControl->AddDropDownField('option[site_id]', '🖥️ Сайт', false, $form->getSelectSites(), $option?->siteId);
$tabControl->AddCheckBoxField(
    'option[single_value_for_all_lang]',
    '🌐 Одно значение для всех языков (опция мультиязычности недоступна для типов "список" и "несколько строк")',
    false,
    ['Y', 'N'],
    $option?->singleValueForAllLang
);
$tabControl->AddCheckBoxField('option[is_admin_only]', '🔒 Доступен только администраторам', false, ['Y', 'N'], $option?->isAdminOnly);

// Общая подсказка не зависит от реализации настроек конкретного типа.
$hint = $option?->getHint() ?? '';
$tabControl->AddTextField(
    'option[hint]',
    sprintf('💡 Подсказка (текст без HTML, до %d символов)', AbstractOption::HINT_MAX_LENGTH),
    htmlspecialchars($hint, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'),
    [
        'cols' => 60,
        'rows' => 4,
    ]
);

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
    <a href="/bitrix/admin/claramente_options.php?lang=' . LANG . '&page=option&ID=' . $option->id . '&delete=Y&' . bitrix_sessid_get() . '" onclick="return confirm(\'' . $text. '\')">
<input type="button" class="adm-btn" value="🗑️ Удалить">
</a>';
}
// Кнопка "Создать миграцию"
$buttonMigration = '';
if ($migrationModuleEnabled) {
    $text = 'Создать миграцию в модуле sprint.migration? Значение опции будет перенесено вместе с миграцией';
    $buttonMigration = '<a href="/bitrix/admin/claramente_options.php?lang=' . LANG . '&page=option&ID=' . $option->id . '&migrate=Y&' . bitrix_sessid_get() . '" onclick="return confirm(\'' . $text. '\')">
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