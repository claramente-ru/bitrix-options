<?php
/**
 * @var $version
 * @var $description
 * @var $extendUse
 * @var $extendClass
 * @var $moduleVersion
 * @var $author
 * @var Sprint\Migration\VersionBuilder $this
 * @formatter:off
 */
$params = $this->getVersionConfig()->getList();
$params = $params[array_key_first($params)]['values'] ?? [];
?><?php echo "<?php\n" ?>

namespace Sprint\Migration;

<?php echo $extendUse ?>
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Sprint\Migration\Exceptions\MigrationException;

class <?php echo $version ?> extends <?php echo $extendClass ?>

{
    protected $author = '<?php echo $author ?>';

    protected $description = '<?php echo $description ?>';

    protected $moduleVersion = '<?php echo $moduleVersion ?>';
    
    protected string $tabCode = '<?php echo $params['TAB_CODE'] ?>';
    protected string $tabName = '<?php echo $params['TAB_NAME'] ?>';
    protected int $tabSort = <?php echo $params['TAB_SORT'] ?? 100 ?>;
    protected string $optionCode = '<?php echo $params['OPTION_CODE'] ?>';
    protected string $optionName = '<?php echo $params['OPTION_NAME'] ?>';
    protected string $optionType = '<?php echo $params['OPTION_TYPE'] ?>';
    protected string $optionSiteId = '<?php echo $params['OPTION_SITE_ID'] ?>';
    protected ?string $optionValue = '<?php echo $params['OPTION_VALUE'] ?>';
    protected ?string $optionSettings = '<?php echo $params['OPTION_SETTINGS'] ?>';
    protected int $optionSort = <?php echo $params['OPTION_SORT'] ?? 100 ?>;

     public function __construct()
    {
        if (! \CModule::IncludeModule('claramente.options')) {
            throw new MigrationException('Ошибка подключения модуля claramente.options');
        }
    }

    /**
     * Установка опции
     * @return void
     * @throws MigrationException
     */
    public function up(): void
    {
        // При указании вкладки, проверим ее наличие и создадим при необходимости
        $tab = null;
        if ($this->tabCode && !($tab = ClaramenteOptionTabsTable::getByCode($this->tabCode))) {
            // Такой вкладки нет, создадим
            $addTab = ClaramenteOptionTabsTable::add([
                'NAME' => $this->tabName,
                'CODE' => $this->tabCode,
                'SORT' => $this->tabCode
            ]);
            if (! $addTab->isSuccess()) {
                throw new MigrationException(
                    implode(',', $addTab->getErrorMessages())
                );
            }
            $tab = ClaramenteOptionTabsTable::getTabById($addTab->getId());
        }

        // Информация об опции
        $data = [
            'SORT' => $this->optionSort,
            'NAME' => $this->optionName,
            'CODE' => $this->optionCode,
            'SITE_ID' => $this->optionSiteId,
            'VALUE' => $this->optionValue,
            'SETTINGS' => $this->optionSettings,
            'TYPE' => $this->optionType,
        ];
        if ($tab) {
            $data['TAB_ID'] = $tab->id;
        }

        // Проверим наличие опции
        $option = ClaramenteOptionsTable::getByCode($this->optionCode, $this->optionSiteId);
        if ($option) {
            // Такая опция уже имеется, обновим
            ClaramenteOptionsTable::update($option->id, $data);
        } else {
            // Опции не существует, создадим новую
            ClaramenteOptionsTable::add($data);
        }
    }

    /**
     * Откат миграции
     * @return void
     * @throws \Exception
     */
    public function down(): void
    {
        $tab = $this->tabCode ? $tab = ClaramenteOptionTabsTable::getByCode($this->tabCode) : null;
        $option = ClaramenteOptionsTable::getByCode($this->optionCode, $this->optionSiteId);
        // Опция уже удалена
        if (! $option) {
            return;
        }
        ClaramenteOptionsTable::delete($option->id);
        
        if ($tab) {
            // Проверим количество элементов во вкладке. Если их там нет, удалим вкладку
            if (count(ClaramenteOptionsTable::getByTabId($tab->id)) === 0) {
                ClaramenteOptionTabsTable::delete($tab->id);
            }
        }
    }
}
