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
use Claramente\Options\Services\MigrationService;

$params = $this->getVersionConfig()->getList();
$params = $params[array_key_first($params)]['values'] ?? [];

// $params — готовые PHP-литералы из MigrationService::getTemplateParams() и выводятся как есть.
// Значения от sprint.migration ($author, $description, $moduleVersion) приводятся к литералам тем же методом.
?><?php echo "<?php\n" ?>

namespace Sprint\Migration;

<?php echo $extendUse ?>
use Claramente\Options\Entity\ClaramenteOptionTabsTable;
use Claramente\Options\Entity\ClaramenteOptionsTable;
use Sprint\Migration\Exceptions\MigrationException;
use CModule;

class <?php echo $version ?> extends <?php echo $extendClass ?>

{
    protected $author = <?php echo MigrationService::toPhpLiteral((string)$author) ?>;

    protected $description = <?php echo MigrationService::toPhpLiteral((string)$description) ?>;

    protected $moduleVersion = <?php echo MigrationService::toPhpLiteral((string)$moduleVersion) ?>;

    protected ?string $tabCode = <?php echo $params['TAB_CODE'] ?>;
    protected ?string $tabName = <?php echo $params['TAB_NAME'] ?>;
    protected int $tabSort = <?php echo $params['TAB_SORT'] ?>;
    protected string $optionCode = <?php echo $params['OPTION_CODE'] ?>;
    protected string $optionName = <?php echo $params['OPTION_NAME'] ?>;
    protected string $optionType = <?php echo $params['OPTION_TYPE'] ?>;
    protected ?string $optionSiteId = <?php echo $params['OPTION_SITE_ID'] ?>;
    protected ?string $optionValue = <?php echo $params['OPTION_VALUE'] ?>;
    protected ?string $optionSettings = <?php echo $params['OPTION_SETTINGS'] ?>;
    protected int $optionSort = <?php echo $params['OPTION_SORT'] ?>;
    protected bool $optionSingleValueForAllLang = <?php echo $params['OPTION_SINGLE_VALUE_FOR_ALL_LANG'] ?>;
    protected bool $optionIsAdminOnly = <?php echo $params['OPTION_IS_ADMIN_ONLY'] ?>;

    public function __construct()
    {
        if (! CModule::IncludeModule('claramente.options')) {
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
        if ($this->tabCode && ! ($tab = ClaramenteOptionTabsTable::getByCode($this->tabCode))) {
            // Такой вкладки нет, создадим
            $addTab = ClaramenteOptionTabsTable::add([
                'NAME' => $this->tabName,
                'CODE' => $this->tabCode,
                'SORT' => $this->tabSort,
            ]);
            if (! $addTab->isSuccess()) {
                throw new MigrationException(implode(',', $addTab->getErrorMessages()));
            }
            $tab = ClaramenteOptionTabsTable::getTabById((int)$addTab->getId());
        }

        // Информация об опции
        $data = [
            'SORT' => $this->optionSort,
            'NAME' => $this->optionName,
            'CODE' => $this->optionCode,
            'SITE_ID' => $this->optionSiteId,
            'VALUE' => $this->optionValue,
            'SETTINGS' => $this->optionSettings,
            'SINGLE_VALUE_FOR_ALL_LANG' => $this->optionSingleValueForAllLang,
            'IS_ADMIN_ONLY' => $this->optionIsAdminOnly,
            'TYPE' => $this->optionType,
        ];
        if ($tab) {
            $data['TAB_ID'] = $tab->id;
        }

        // Проверим наличие опции
        $option = ClaramenteOptionsTable::getByCode($this->optionCode, $this->optionSiteId);
        $result = $option
            // Такая опция уже имеется, обновим
            ? ClaramenteOptionsTable::update($option->id, $data)
            // Опции не существует, создадим новую
            : ClaramenteOptionsTable::add($data);

        if (! $result->isSuccess()) {
            throw new MigrationException(implode(',', $result->getErrorMessages()));
        }
    }

    /**
     * Откат миграции
     * @return void
     * @throws MigrationException
     */
    public function down(): void
    {
        $tab = $this->tabCode ? ClaramenteOptionTabsTable::getByCode($this->tabCode) : null;
        $option = ClaramenteOptionsTable::getByCode($this->optionCode, $this->optionSiteId);
        // Опция уже удалена
        if (! $option) {
            return;
        }
        $result = ClaramenteOptionsTable::delete($option->id);
        if (! $result->isSuccess()) {
            throw new MigrationException(implode(',', $result->getErrorMessages()));
        }

        if ($tab) {
            // Проверим количество элементов во вкладке. Если их там нет, удалим вкладку
            if (count(ClaramenteOptionsTable::getByTabId($tab->id)) === 0) {
                ClaramenteOptionTabsTable::delete($tab->id);
            }
        }
    }
}
