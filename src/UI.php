<?php

namespace MLRedirect;

use Concrete\Core\Config\Repository\Repository;

defined('C5_EXECUTE') or die('Access Denied.');

final class UI
{
    /**
     * Major concrete5 / ConcreteCMS version.
     *
     * @var int
     * @readonly
     */
    public $majorVersion;

    /**
     * @var string
     * @readonly
     */
    public $btnSecondary;

    /**
     * @var string
     * @readonly
     */
    public $faTrash;

    /**
     * @var string
     * @readonly
     */
    public $formGroup;

    /**
     * @var string
     * @readonly
     */
    public $tabStartTabContainers;

    /**
     * @var string
     * @readonly
     */
    public $tabContentClassInactive;

    /**
     * @var string
     * @readonly
     */
    public $tabContentClassActive;

    /**
     * @var string
     * @readonly
     */
    public $tabContentAdditionalAttributes;

    /**
     * @var string
     * @readonly
     */
    public $tabEndTabContainers;

    /**
     * @var string
     * @readonly
     */
    public $tabIDPrefix;

    public function __construct(Repository $config)
    {
        $version = $config->get('concrete.version');
        list($majorVersion) = explode('.', $version, 2);
        $this->majorVersion = (int) $majorVersion;
        if ($this->majorVersion >= 9) {
            $this->initializeV9();
        } else {
            $this->initializeV8();
        }
    }

    /**
     * @see https://fontawesome.com/v5/search?m=free
     * @see https://getbootstrap.com/docs/5.2
     */
    private function initializeV9()
    {
        $this->btnSecondary = 'btn-secondary';
        $this->faTrash = 'far fa-trash-alt';
        $this->formGroup = 'mb-3';
        $this->tabIDPrefix = '';
        $this->tabStartTabContainers = '<div class="tab-content">';
        $this->tabEndTabContainers = '</div>';
        $this->tabContentClassInactive = 'tab-pane';
        $this->tabContentClassActive = 'tab-pane active';
        $this->tabContentAdditionalAttributes = ' role="tabpanel"';
    }

    /**
     * @see https://fontawesome.com/v4/icons/
     * @see https://getbootstrap.com/docs/3.4/
     */
    private function initializeV8()
    {
        $this->btnSecondary = 'btn-default';
        $this->faTrash = 'fa fa-trash-o';
        $this->formGroup = 'form-group';
        $this->tabIDPrefix = 'ccm-tab-content-';
        $this->tabStartTabContainers = '';
        $this->tabEndTabContainers = '';
        $this->tabContentClassInactive = 'ccm-tab-content';
        $this->tabContentClassActive = 'ccm-tab-content';
        $this->tabContentAdditionalAttributes = '';
    }
}
