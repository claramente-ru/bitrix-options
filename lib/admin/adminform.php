<?php
declare(strict_types=1);

namespace Claramente\Options\Admin;

use CAdminForm;
use CSite;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Structures\Entity\OptionEntityStructure;
use Claramente\Options\Structures\Entity\TabEntityStructure;

/**
 * Административные методы для модуля claramente.options
 */
final class AdminForm
{
    /**
     * Tabs
     * @var TabEntityStructure[]
     */
    private array $tabs = [];

    /**
     * Опции
     * @var OptionEntityStructure[]
     */
    private array $options = [];

    public function __construct()
    {
        // Заполнение объектов информацией
        $this->fillData();
    }

    /**
     * Получить все вкладки
     * @return TabEntityStructure[]
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }

    /**
     * Получить вкладки для select поля
     * @param bool $withEmpty Не указывать
     * @return array
     */
    public function getSelectTabs(bool $withEmpty = true): array
    {
        $result = [];
        // Без вкладки
        if ($withEmpty) {
            $result[0] = 'Не выбрано';
        }
        foreach ($this->getTabs() as $tab) {
            $result[$tab->id] = $tab->name;
        }

        return $result;
    }

    /**
     * Получить список сайтов для select поля
     * @return array
     */
    public function getSelectSites(bool $withEmpty = true): array
    {
        $result = [];
        if ($withEmpty) {
            $result[null] = 'Не выбрано';
        }
        $sites = CSite::GetList();
        while ($site = $sites->Fetch()) {
            $result[$site['ID']] = $site['ID'];
        }

        return $result;
    }

    /**
     * Получить настройки tab
     * @param int|null $tabId
     * @return OptionEntityStructure[]
     */
    public function getTabOptions(int|null $tabId): array
    {
        $result = [];
        foreach ($this->options as $setting) {
            if ($tabId === $setting->tabId) {
                $result[] = $setting;
            }
        }

        return $result;
    }

    /**
     * Получить вкладки
     * @param bool $withTechnicalTabs Включить технические вкладки
     * @return array
     */
    public function getFormTabs(bool $withTechnicalTabs = true): array
    {
        $tabs = [];
        foreach ($this->tabs as $tab) {
            $tabs[] = $this->collectTab(
                name: $tab->name,
                div: 'tabs[' . $tab->id . ']',
                id: $tab->id,
                sort: $tab->sort
            );
        }
        // Технические вкладки
        if (true === $withTechnicalTabs) {
            $tabs[] = $this->collectTab('💾 Опции', 'options');
            // Tab для настроек tabs
            $tabs[] = $this->collectTab('📑 Вкладки', 'tabs');
        }

        return $tabs;
    }

    /**
     * Экземпляр построения административной панели
     * @param string $name
     * @param array $tabs
     * @param bool $canExpand
     * @param bool $denyAutosave
     * @return CAdminForm
     */
    public function getForm(
        string $name = 'tabControl',
        array  $tabs = [],
        bool   $canExpand = true,
        bool   $denyAutosave = false
    ): CAdminForm
    {
        return new CAdminForm(
            $name,
            $tabs,
            $canExpand,
            $denyAutosave
        );
    }

    /**
     * Формирование Tab
     * @param string $name
     * @param string $div
     * @param int|null $id
     * @param int $sort
     * @param bool $required
     * @param string $icon
     * @param string|null $code
     * @return array @see CAdminForm
     */
    public function collectTab(
        string $name,
        string $div,
        ?int   $id = null,
        int    $sort = 100,
        bool   $required = true,
        string $icon = 'fileman',
        string $code = null
    ): array
    {
        return [
            'CODE' => $code,
            'ID' => $id,
            'SORT' => $sort,
            'TAB' => $name,
            'ICON' => $icon,
            'TITLE' => $name,
            'DIV' => $div,
            'required' => $required
        ];
    }

    /**
     * Заполнение информации
     * @return void
     */
    private function fillData(): void
    {
        // Tabs
        $tabs = ClaramenteOptionTabsTable::query()
            ->setSelect(['*'])
            ->setOrder([
                'SORT' => 'ASC',
                'NAME' => 'ASC'
            ])
            ->fetchAll();
        foreach ($tabs as $tab) {
            $this->tabs[] = new TabEntityStructure(
                id: intval($tab['ID']),
                name: $tab['NAME'],
                code: $tab['CODE'],
                sort: intval($tab['SORT'])
            );
        }

        // Опции
        $options = ClaramenteOptionsTable::query()
            ->setSelect(['*'])
            ->setOrder([
                'SORT' => 'ASC',
                'NAME' => 'ASC'
            ])
            ->fetchAll();
        foreach ($options as $option) {
            $this->options[] = OptionEntityStructure::fromArray($option);
        }
    }
}
