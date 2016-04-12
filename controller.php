<?php
namespace Concrete\Package\Redirect;

use BlockType;

defined('C5_EXECUTE') or die('Access denied.');

/**
 * The ProgePack package controller.
 */
class Controller extends \Package
{
    protected $pkgHandle = 'redirect';

    protected $appVersionRequired = '5.7.5';

    protected $pkgVersion = '0.9.0';

    public function getPackageName()
    {
        return t('Redirect');
    }

    public function getPackageDescription()
    {
        return t('This package offers a block to redirect users.');
    }

    public function install()
    {
        $pkg = parent::install();
        if (!is_object(BlockType::getByHandle('redirect'))) {
            BlockType::installBlockType('redirect', $pkg);
        }
    }
}
