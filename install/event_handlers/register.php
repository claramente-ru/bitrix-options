<?php

/**
 * @var string $moduleId
 */
$eventManager = Bitrix\Main\EventManager::getInstance();

$eventManager->registerEventHandler(
	'main',
	'OnBuildGlobalMenu',
	$moduleId,
	'ClaramenteModuleAdminOptions',
	'onBuildGlobalMenu'
);
