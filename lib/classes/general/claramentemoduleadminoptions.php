<?php
declare(strict_types=1);

/**
 * Административные методы для модуля claramente.options
 */
final class ClaramenteModuleAdminOptions
{
    /**
     * Отображение настроек в глобальном меню сайта
     * @param array $adminMenu
     * @param array $moduleMenu
     * @return void
     */
    public static function onBuildGlobalMenu(array &$adminMenu, array &$moduleMenu): void
    {
        $moduleMenu[] = [
            'parent_menu' => 'global_menu_content',
            'section'     => 'object.manager',
            'sort'        => 4,
            'url'         => 'claramente_options.php?lang='.LANG,
            'text'        => 'Параметры сайта',
            'title'       => 'Параметры сайта',
            'icon'        => 'util_menu_icon',
            'page_icon'   => 'util_menu_icon',
            'items_id'    => 'claramente_settings',
            'items'       => []
        ];
    }
}

