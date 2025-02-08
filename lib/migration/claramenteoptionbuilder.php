<?php

namespace Claramente\Options\Migration;

use Sprint\Migration\Locale;
use Sprint\Migration\VersionBuilder;

class ClaramenteOptionBuilder extends VersionBuilder
{
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

        $this->createVersionFile($template);
    }
}
