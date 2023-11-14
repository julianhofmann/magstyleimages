<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

call_user_func(static function (): void {
    ExtensionManagementUtility::addStaticFile('magstyleimages', 'Configuration/TypoScript/', 'Magazine Style Images');
});
