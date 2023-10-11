<?php
namespace Concrete\Package\Redirect;

use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Block\BlockType\Set as BlockTypeSet;
use Concrete\Core\Database\EntityManager\Provider\ProviderInterface;
use Concrete\Core\Package\Package;

defined('C5_EXECUTE') or die('Access denied.');

class Controller extends Package implements ProviderInterface
{
    /**
     * The package handle.
     *
     * @var string
     */
    protected $pkgHandle = 'redirect';

    /**
     * The package version.
     *
     * @var string
     */
    protected $pkgVersion = '2.4.1';

    /**
     * The minimum concrete5/ConcreteCMS version.
     *
     * @var string
     */
    protected $appVersionRequired = '8.2.0';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::$pkgAutoloaderRegistries
     */
    protected $pkgAutoloaderRegistries = [
        'src' => 'MLRedirect',
    ];

    /**
     * {@inheritdoc}
     *
     * @see Package::getPackageName()
     */
    public function getPackageName()
    {
        return t('Redirect');
    }

    /**
     * {@inheritdoc}
     *
     * @see Package::getPackageDescription()
     */
    public function getPackageDescription()
    {
        return t('This package offers a block to redirect users.');
    }

    /**
     * {@inheritdoc}
     *
     * @see Package::install()
     */
    public function install()
    {
        $pkg = parent::install();
        $bt = BlockType::getByHandle('redirect');
        if (!is_object($bt)) {
            $bt = BlockType::installBlockType('redirect', $pkg);
        }
        $bts = BlockTypeSet::getByHandle('navigation');
        if ($bts) {
            if (!$bts->contains($bt)) {
                $bts->addBlockType($bt);
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Database\EntityManager\Provider\ProviderInterface::getDrivers()
     */
    public function getDrivers()
    {
        return [];
    }
}
