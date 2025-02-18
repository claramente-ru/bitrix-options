<?php

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

/**
 * Установочный класс модуля claramente.options
 */
class claramente_options extends CModule
{
    const MODULE_ID = 'claramente.options';

    function __construct()
    {
        require __DIR__ . '/version.php';
        /**
         * @var array $version
         */
        $this->MODULE_ID = $this->GetModuleId();
        $this->PARTNER_NAME = Loc::getMessage('CLAREMENTE_MODULE_OPTIONS_NAME');
        $this->PARTNER_URI = 'https://claramente.ru';
        $this->MODULE_VERSION = $version['VERSION'];
        $this->MODULE_VERSION_DATE = $version['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('CLAREMENTE_MODULE_OPTIONS_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('CLAREMENTE_MODULE_OPTIONS_DESCRIPTION');
    }

    /**
     * @return string
     */
    public function GetModuleId(): string
    {
        return self::MODULE_ID;
    }

    /**
     * @return bool
     */
    public function DoInstall(): bool
    {
        global $APPLICATION, $DB;
        // Регистрация событий
        $this->RegisterEventHandlers();

        // Копированием файлов для административной панели
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');

        // Добавим таблицы модуля
        $connection = Application::getConnection();
        $dbInstall = $DB->RunSQLBatch(__DIR__ . '/db/' . $connection->getType() . '/install.sql');
        if (is_array($dbInstall)) {
            // Ошибка установки БД
            $APPLICATION->ThrowException(implode(',', $dbInstall));

            return false;
        }

        // Все шаги выполнены успешно, регистрируем модуль
        ModuleManager::RegisterModule(self::GetModuleId());

        return true;
    }

    /**
     * @return bool
     */
    public function DoUninstall(): bool
    {
        // Удаление событий
        $this->UnRegisterEventHandlers();

        // Удаление файлов из административной панели
        DeleteDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');

        // Все шаги выполнены успешно, удаляем модуль
        ModuleManager::UnRegisterModule(self::GetModuleId());

        return true;
    }

    /**
     * @return void
     */
    public function RegisterEventHandlers()
    {
        $moduleId = self::GetModuleId();
        require_once __DIR__ . '/event_handlers/register.php';
    }

    /**
     * @return void
     */
    public function UnRegisterEventHandlers()
    {
        $moduleId = self::GetModuleId();
        require_once __DIR__ . '/event_handlers/unregister.php';
    }
}
