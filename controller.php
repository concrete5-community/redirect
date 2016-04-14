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

    protected $pkgVersion = '0.9.2';

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
        $bt = BlockType::getByHandle('redirect');
        if (!is_object($bt)) {
            $bt = BlockType::installBlockType('redirect', $pkg);
        }
        $bts = \BlockTypeSet::getByHandle('navigation');
        if ($bts) {
            if (!$bts->contains($bt)) {
                $bts->addBlockType($bt);
            }
        }
    }
}
