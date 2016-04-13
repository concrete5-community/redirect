<?php
namespace Concrete\Package\Redirect\Block\Redirect;

use Exception;
use IPLib\Factory as IPFactory;

defined('C5_EXECUTE') or die('Access denied.');

class Controller extends \Concrete\Core\Block\BlockController
{
    /**
     * Never show message.
     *
     * @var int
     */
    const SHOWMESSAGE_NEVER = 1;

    /**
     * Show message only to editors.
     *
     * @var int
     */
    const SHOWMESSAGE_EDITORS = 2;

    /**
     * Always show message.
     *
     * @var int
     */
    const SHOWMESSAGE_ALWAYS = 3;

    /**
     * Main table for this block.
     *
     * @var string
     */
    protected $btTable = 'btRedirect';

    /**
     * Width of the add/edit dialog.
     *
     * @var int
     */
    protected $btInterfaceWidth = 700;

    /**
     * Height of the add/edit dialog.
     *
     * @var int
     */
    protected $btInterfaceHeight = 450;

    /**
     * Destination page: collection ID.
     *
     * @var int
     */
    protected $redirectToCID;

    /**
     * Destination page: external URL.
     *
     * @var string
     */
    protected $redirectToURL;

    /**
     * Redirect users belonging to these group IDs.
     *
     * @var string
     */
    protected $redirectGroupIDs;

    /**
     * Don't redirect users belonging to these group IDs.
     *
     * @var string
     */
    protected $dontRedirectGroupIDs;

    /**
     * Redirect users from these IP addresses.
     *
     * @var string
     */
    protected $redirectIPs;

    /**
     * Don't redirect users from these IP addresses.
     *
     * @var string
     */
    protected $dontRedirectIPs;

    /**
     * Redirect users that can edit the page containing the block?
     *
     * @var bool
     */
    protected $redirectEditors;

    /**
     * Show a message block when the block does not redirect?
     *
     * @var int
     */
    protected $showRedirectMessage;

    protected static $composerLoaded = false;

    protected static function loadComposer()
    {
        if (static::$composerLoaded === false) {
            require_once \Package::getByHandle('redirect')->getPackagePath().'/vendor/autoload.php';
            static::$composerLoaded = true;
        }
    }
    /**
     * Returns the name of the block type.
     *
     * @return string
     */
    public function getBlockTypeName()
    {
        return t('Redirect');
    }

    /**
     * Returns the description of the block type.
     *
     * @return string
     */
    public function getBlockTypeDescription()
    {
        return t("Redirect specific users to another page.");
    }

    /**
     * Normalize the data set by user when adding/editing a block.
     *
     * @param array $data
     *
     * @return \Concrete\Core\Error\Error|array
     */
    private function normalize($data)
    {
        if (!isset($this->app)) {
            $this->app = \Core::make('app');
        }
        $errors = $this->app->make('helper/validation/error');
        /* @var \Concrete\Core\Error\Error $errors */
        $normalized = array();
        if (!is_array($data) || empty($data)) {
            $errors->add(t('No data received'));
        } else {
            static::loadComposer();
            $normalized['redirectToCID'] = 0;
            $normalized['redirectToURL'] = '';
            switch (isset($data['redirectToType']) ? $data['redirectToType'] : '') {
                case 'cid':
                    $normalized['redirectToCID'] = isset($data['redirectToCID']) ? (int) $data['redirectToCID'] : 0;
                    if ($normalized['redirectToCID'] <= 0) {
                        $errors->add(t('Please specify the destination page'));
                    }
                    break;
                case 'url':
                    $normalized['redirectToURL'] = isset($data['redirectToURL']) ? trim((string) $data['redirectToURL']) : '';
                    if ($normalized['redirectToURL'] === '') {
                        $errors->add(t('Please specify the destination page'));
                    }
                    break;
                default:
                    $errors->add(t('Please specify the kind of the destination page'));
                    break;
            }
            foreach (array('redirectGroupIDs', 'dontRedirectGroupIDs') as $var) {
                $list = array();
                if (isset($data[$var]) && is_string($data[$var])) {
                    foreach (preg_split('/\D+/', $data[$var], -1, PREG_SPLIT_NO_EMPTY) as $gID) {
                        $gID = (int) $gID;
                        if ($gID > 0 && !in_array($gID, $list, true) && \Group::getByID($gID) !== null) {
                            $list[] = $gID;
                        }
                    }
                }
                $normalized[$var] = implode(',', $list);
            }
            foreach (array('redirectIPs', 'dontRedirectIPs') as $f) {
                $normalized[$f] = '';
                if (isset($data[$f])) {
                    $v = array();
                    foreach (preg_split('/[\\s,]+/', str_replace('|', ' ', (string) $data[$f]), -1, PREG_SPLIT_NO_EMPTY) as $s) {
                        $s = trim($s);
                        if ($s !== '') {
                            $ipRange = IPFactory::rangeFromString($s);
                            if ($ipRange === null) {
                                $errors->add(t('Invalid IP address: %s', $s));
                            } else {
                                $v[] = $ipRange->toString(false);
                            }
                        }
                    }
                    if (!empty($v)) {
                        $normalized[$f] = implode('|', $v);
                    }
                }
            }
            $normalized['showRedirectMessage'] = (isset($data['showRedirectMessage']) && $data['showRedirectMessage']) ? (int) $data['showRedirectMessage'] : 0;
            switch ($normalized['showRedirectMessage']) {
                case self::SHOWMESSAGE_NEVER:
                case self::SHOWMESSAGE_EDITORS:
                case self::SHOWMESSAGE_ALWAYS:
                    break;
                default:
                    $errors->add(t('Please specify if the message should be shown'));
                    break;
            }
            $normalized['redirectEditors'] = (isset($data['redirectEditors']) && $data['redirectEditors']) ? 1 : 0;
        }

        return $errors->has() ? $errors : $normalized;
    }

    /**
     * Validate the data set by user when adding/editing a block.
     *
     * @return \Concrete\Core\Error\Error|null
     */
    public function validate($data)
    {
        $normalized = $this->normalize($data);

        return is_array($normalized) ? null : $normalized;
    }

    /**
     * Save the data set by user when adding/editing a block.
     *
     * @throws Exception
     */
    public function save($data)
    {
        $normalized = $this->normalize($data);
        if (!is_array($normalized)) {
            throw new Exception(implode("\n", $normalized->getList()));
        }
        parent::save($normalized);
    }

    private function userCanEdit(\Concrete\Core\Page\Page $c)
    {
        static $canEdit;
        if (!isset($canEdit)) {
            $canEdit = false;
            if (\User::isLoggedIn()) {
                $me = new \User();
                if ($me->isRegistered()) {
                    $cp = new \Permissions($c);
                    if ($cp->canEditPageContents()) {
                        $canEdit = true;
                    }
                }
            }
        }

        return $canEdit;
    }

    private function performRedirect()
    {
        if ($this->redirectToCID) {
            $to = \Page::getByID($this->redirectToCID);
            if (is_object($to) && (!$to->isError())) {
                $this->redirect($to);
            }
        } elseif (is_string($this->redirectToURL) && $this->redirectToURL !== '') {
            $this->redirect($this->redirectToURL);
        }
    }

    private function getCurrentUserGroups()
    {
        static $result;
        if (!isset($result)) {
            $result = array();
            $me = \User::isLoggedIn() ? new \User() : null;
            if ($me !== null && $me->isRegistered()) {
                $result[] = (string) REGISTERED_GROUP_ID;
                foreach ($me->getUserGroups() as $gID) {
                    $gID = (string) $gID;
                    if ($gID != GUEST_GROUP_ID && $gID != REGISTERED_GROUP_ID) {
                        $result[] = (string) $gID;
                    }
                }
            } else {
                $result[] = (string) GUEST_GROUP_ID;
            }
        }

        return $result;
    }

    /**
     * @return \IPLib\Address\AddressInterface|null
     */
    private function getCurrentUserIP()
    {
        static $result;
        if (!isset($result)) {
            static::loadComposer();
            $ip = IPFactory::addressFromString($this->request->getClientIp());
            $result = ($ip === null) ? false : $ip;
        }

        return ($result === false) ? null : $result;
    }

    /**
     * @param string $ipList
     *
     * @return bool
     */
    protected function isUserIpInList($ipList)
    {
        $result = false;
        if ($ipList !== '') {
            $ip = $this->getCurrentUserIP();
            if ($ip !== null) {
                foreach (explode('|', $ipList) as $rangeString) {
                    $range = IPFactory::rangeFromString($rangeString);
                    if ($range !== null && $range->contains($ip)) {
                        $result = true;
                        break;
                    }
                }
            }
        }

        return $result;
    }

    public function on_start()
    {
        $c = $this->request->getCurrentPage();
        if (!is_object($c) || $c->isError()) {
            $c = null;
        }
        if ($c !== null) {
            if ($c->isEditMode()) {
                return;
            }
            if (!$this->redirectEditors && $this->userCanEdit($c)) {
                return;
            }
        }
        if ($this->dontRedirectGroupIDs !== '' && array_intersect(explode(',', $this->dontRedirectGroupIDs), $this->getCurrentUserGroups())) {
            return;
        }
        if ($this->isUserIpInList($this->dontRedirectIPs)) {
            return;
        }
        if ($this->isUserIpInList($this->redirectIPs)) {
            $this->performRedirect();

            return;
        }
        if ($this->redirectGroupIDs !== '' && array_intersect(explode(',', $this->redirectGroupIDs), $this->getCurrentUserGroups())) {
            $this->performRedirect();

            return;
        }
    }

    public function view()
    {
        $c = $this->request->getCurrentPage();
        if (!is_object($c) || $c->isError()) {
            $c = null;
        }
        if ($c !== null && $c->isEditMode()) {
            $this->set('output', '<div class="ccm-edit-mode-disabled-item"><div style="padding: 10px 5px">'.t('Redirect block').'</div></div>');
        } else {
            $showMessage = false;
            switch ($this->showRedirectMessage) {
                case self::SHOWMESSAGE_ALWAYS:
                    $showMessage = true;
                    break;
                case self::SHOWMESSAGE_EDITORS:
                    if ($c !== null && $this->userCanEdit($c)) {
                        $showMessage = true;
                    }
                    break;
            }
            if ($showMessage) {
                $this->set('output', '<span class="redirect-block-message">'.t('This block will redirect selected users.').'</span>');
            }
        }
    }
}
