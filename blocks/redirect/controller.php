<?php
namespace Concrete\Package\Redirect\Block\Redirect;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Editor\LinkAbstractor;
use Concrete\Core\Http\Response;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Exception;
use Group;
use IPLib\Factory as IPFactory;
use User;

defined('C5_EXECUTE') or die('Access denied.');

class Controller extends BlockController
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
    protected $btInterfaceWidth = 750;

    /**
     * Height of the add/edit dialog.
     *
     * @var int
     */
    protected $btInterfaceHeight = 460;

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
    protected $showMessage;

    /**
     * Use a custom message?
     *
     * @var bool
     */
    protected $useCustomMessage;

    /**
     * Custom message.
     *
     * @var string
     */
    protected $customMessage;

    /**
     * @var \Concrete\Core\Page\Page|null|false
     */
    private $currentPage = false;

    /**
     * @var bool|null
     */
    private $userCanEditCurrentPage = null;

    /**
     * {@inheritdoc}
     *
     * @see BlockController::getBlockTypeName()
     */
    public function getBlockTypeName()
    {
        return t('Redirect');
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::getBlockTypeDescription()
     */
    public function getBlockTypeDescription()
    {
        return t('Redirect specific users to another page.');
    }

    /**
     * Normalize the data set by user when adding/editing a block.
     *
     * @param array $data
     *
     * @return \Concrete\Core\Error\ErrorList\ErrorList|array
     */
    private function normalize($data)
    {
        $errors = $this->app->make('helper/validation/error');
        /* @var \Concrete\Core\Error\ErrorList\ErrorList $errors */
        $normalized = [];
        if (!is_array($data) || empty($data)) {
            $errors->add(t('No data received'));
        } else {
            $normalized['redirectToCID'] = 0;
            $normalized['redirectToURL'] = '';
            switch (isset($data['redirectToType']) ? $data['redirectToType'] : '') {
                case 'cid':
                    $normalized['redirectToCID'] = isset($data['redirectToCID']) ? (int) $data['redirectToCID'] : 0;
                    if ($normalized['redirectToCID'] <= 0) {
                        $errors->add(t('Please specify the destination page'));
                    } else {
                        $c = $this->getCurrentPage();
                        if ($c !== null && $c->getCollectionID() == $normalized['redirectToCID']) {
                            $errors->add(t('The destination page is the current page.'));
                        }
                    }
                    break;
                case 'url':
                    $normalized['redirectToURL'] = isset($data['redirectToURL']) ? trim((string) $data['redirectToURL']) : '';
                    if ($normalized['redirectToURL'] === '') {
                        $errors->add(t('Please specify the destination page'));
                    } else {
                        $c = $this->getCurrentPage();
                        if ($c !== null) {
                            $myURL = (string) $this->app->make('url/manager')->resolve([$c]);
                            if (rtrim($myURL, '/') === rtrim($normalized['redirectToURL'], '/')) {
                                $errors->add(t('The destination page is the current page.'));
                            }
                        }
                    }
                    break;
                default:
                    $errors->add(t('Please specify the kind of the destination page'));
                    break;
            }
            foreach (['redirectGroupIDs', 'dontRedirectGroupIDs'] as $var) {
                $list = [];
                if (isset($data[$var]) && is_string($data[$var])) {
                    foreach (preg_split('/\D+/', $data[$var], -1, PREG_SPLIT_NO_EMPTY) as $gID) {
                        $gID = (int) $gID;
                        if ($gID > 0 && !in_array($gID, $list, true) && Group::getByID($gID) !== null) {
                            $list[] = $gID;
                        }
                    }
                }
                $normalized[$var] = implode(',', $list);
            }
            foreach (['redirectIPs', 'dontRedirectIPs'] as $f) {
                $normalized[$f] = '';
                if (isset($data[$f])) {
                    $v = [];
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
            $normalized['redirectEditors'] = (isset($data['redirectEditors']) && $data['redirectEditors']) ? 1 : 0;
            $normalized['showMessage'] = (isset($data['showMessage']) && $data['showMessage']) ? (int) $data['showMessage'] : 0;
            switch ($normalized['showMessage']) {
                case self::SHOWMESSAGE_NEVER:
                case self::SHOWMESSAGE_EDITORS:
                case self::SHOWMESSAGE_ALWAYS:
                    break;
                default:
                    $errors->add(t('Please specify if the message should be shown'));
                    break;
            }
            $normalized['useCustomMessage'] = (isset($data['useCustomMessage']) && $data['useCustomMessage']) ? 1 : 0;
            if (isset($data['customMessage']) && is_string($data['customMessage']) && $data['customMessage'] !== '') {
                $normalized['customMessage'] = LinkAbstractor::translateTo($data['customMessage']);
            } else {
                $normalized['customMessage'] = '';
            }
        }

        return $errors->has() ? $errors : $normalized;
    }

    public function add()
    {
        $this->addOrEdit();
    }

    public function edit()
    {
        $this->addOrEdit();
    }

    private function addOrEdit()
    {
        $ip = $this->getCurrentUserIP();
        $this->set('myIP', ($ip === null) ? '' : $ip->toString());
    }

    /**
     * Validate the data set by user when adding/editing a block.
     *
     * @return \Concrete\Core\Error\ErrorList\ErrorList|null
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

    /**
     * Get the current page.
     *
     * @return Page|null
     */
    private function getCurrentPage()
    {
        if ($this->currentPage === false) {
            $c = $this->request->getCurrentPage();
            $this->currentPage = $c && !$c->isError() ? $c : null;
        }
        return $this->currentPage;
    }

    /**
     * Get the currently logged in user.
     *
     * @return User|null
     */
    private function getCurrentUser()
    {
        static $result;
        if (!isset($result)) {
            $result = false;
            if (User::isLoggedIn()) {
                $u = new User();
                if ($u->isRegistered()) {
                    $result = $u;
                }
            }
        }

        return ($result === false) ? null : $result;
    }

    /**
     * Return the list of the ID of the current user.
     *
     * @return string[]
     */
    private function getCurrentUserGroups()
    {
        static $result;
        if (!isset($result)) {
            $result = [];
            $me = $this->getCurrentUser();
            if ($me === null) {
                $result[] = (string) GUEST_GROUP_ID;
            } else {
                $result[] = (string) REGISTERED_GROUP_ID;
                foreach ($me->getUserGroups() as $gID) {
                    $gID = (string) $gID;
                    if ($gID != GUEST_GROUP_ID && $gID != REGISTERED_GROUP_ID) {
                        $result[] = $gID;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param Page $c
     *
     * @return bool
     */
    private function userCanEditCurrentPage()
    {
        if ($this->userCanEditCurrentPage === null) {
            $userCanEditCurrentPage = false;
            $me = $this->getCurrentUser();
            if ($me !== null) {
                $p = $this->getCurrentPage();
                if ($p !== null) {
                    $cp = new Checker($p);
                    if ($cp->canEditPageContents()) {
                        $userCanEditCurrentPage = true;
                    }
                }
            }
            $this->userCanEditCurrentPage = $userCanEditCurrentPage;
        }

        return $this->userCanEditCurrentPage;
    }

    private function performRedirect()
    {
        $destinationURL = null;
        if ($this->redirectToCID) {
            $to = Page::getByID($this->redirectToCID);
            if (is_object($to) && (!$to->isError())) {
                $destinationURL = (string) $this->app->make('url/manager')->resolve([$to]);
            }
        } elseif (is_string($this->redirectToURL) && $this->redirectToURL !== '') {
            $destinationURL = $this->redirectToURL;
        }

        if ($destinationURL !== null) {
            $rf = $this->app->make(ResponseFactoryInterface::class);
            $response = $rf->redirect($destinationURL, Response::HTTP_TEMPORARY_REDIRECT);
            $response->send();
            exit();
        }
    }

    /**
     * @return \IPLib\Address\AddressInterface|null
     */
    private function getCurrentUserIP()
    {
        static $result;
        if (!isset($result)) {
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
    private function isUserIpInList($ipList)
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
        $user = $this->getCurrentUser();
        // Never redirect the administrator
        if ($user !== null && $user->isSuperUser()) {
            return;
        }
        $c = $this->getCurrentPage();
        if ($c !== null) {
            // Never redirect if the page is in edit mode
            if ($c->isEditMode()) {
                return;
            }
            // Don't redirect users that can edit the page
            if (!$this->redirectEditors && $this->userCanEditCurrentPage()) {
                return;
            }
        }
        // Never redirect visitors from specific IP addresses
        if ($this->dontRedirectIPs && $this->isUserIpInList($this->dontRedirectIPs)) {
            return;
        }
        // Never redirect users belonging to specific groups
        if ($this->dontRedirectGroupIDs !== '' && array_intersect(explode(',', $this->dontRedirectGroupIDs), $this->getCurrentUserGroups())) {
            return;
        }
        if ($this->redirectIPs || $this->redirectGroupIDs) {
            $redirect = false;
            // Redirect visitors from specific IP addresses
            if ($redirect === false && $this->redirectIPs && $this->isUserIpInList($this->redirectIPs)) {
                $redirect = true;
            }
            // Redirect users belonging to specific groups
            if ($redirect === false && $this->redirectGroupIDs && array_intersect(explode(',', $this->redirectGroupIDs), $this->getCurrentUserGroups())) {
                $redirect = true;
            }
        } else {
            $redirect = true;
        }

        if ($redirect) {
            $this->performRedirect();
        }
    }

    public function view()
    {
        $c = $this->getCurrentPage();
        if ($c !== null && $c->isEditMode()) {
            $this->set('output', '<div class="ccm-edit-mode-disabled-item"><div style="padding: 10px 5px">' . t('Redirect block') . '</div></div>');
        } else {
            $showMessage = false;
            switch ($this->showMessage) {
                case self::SHOWMESSAGE_ALWAYS:
                    $showMessage = true;
                    break;
                case self::SHOWMESSAGE_EDITORS:
                    if ($this->userCanEditCurrentPage()) {
                        $showMessage = true;
                    }
                    break;
            }
            if ($showMessage) {
                if ($this->useCustomMessage) {
                    $msg = (string) $this->customMessage;
                    if ($msg !== '') {
                        $msg = LinkAbstractor::translateFrom($msg);
                    }
                } else {
                    $msg = '<span class="redirect-block-message">' . t('This block will redirect selected users.') . '</span>';
                }
                $this->set('output', $msg);
            }
        }
    }
}
