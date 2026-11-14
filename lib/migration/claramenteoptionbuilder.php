<?php

namespace Claramente\Options\Migration;

use Sprint\Migration\Locale;
use Sprint\Migration\VersionBuilder;
use Throwable;

class ClaramenteOptionBuilder extends VersionBuilder
{
    /**
     * Имя созданной миграции
     * @var string
     */
    private string $createdVersion = '';

    /**
     * Текст ошибки создания миграции
     * @var string
     */
    private string $errorMessage = '';

    protected function isBuilderEnabled()
    {
        return true;
    }

    protected function initialize()
    {
        $this->setTitle(Locale::getMessage('BUILDER_Version1'));
        $this->setGroup(Locale::getMessage('BUILDER_GROUP_Tools'));

        $this->addVersionFields();
    }

    protected function execute()
    {
        $template = __DIR__ . '/template.php';

        // sprint.migration 5.8+ не выводит сообщения вне своих страниц, поэтому результат запоминается
        try {
            $this->createdVersion = $this->createVersionFile($template);
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Имя созданной миграции, пустая строка при ошибке
     * @return string
     */
    public function getCreatedVersion(): string
    {
        return $this->createdVersion;
    }

    /**
     * Текст ошибки создания миграции
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
