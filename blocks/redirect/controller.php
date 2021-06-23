<?php

namespace Concrete\Package\Redirect\Block\Redirect;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Editor\LinkAbstractor;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Http\Response;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Localization\Localization;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Exception;
use Group;
use IPLib\Factory as IPFactory;
use League\Url\Url;
use MLRedirect\OSDetector;
use Punic\Language;
use Punic\Misc;
use Punic\Territory;
use RuntimeException;
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
     * HTTP redirect code.
     *
     * @var int|string|null
     */
    protected $redirectCode;

    /**
     * Destination page: collection ID.
     *
     * @var int|string
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
     * Redirect users using these operating systems.
     *
     * @var string
     */
    protected $redirectOperatingSystems;

    /**
     * Don't redirect users using these operating systems.
     *
     * @var string
     */
    protected $dontRedirectOperatingSystems;

    /**
     * Redirect users by browser language.
     *
     * @var string
     */
    protected $redirectLocales;

    /**
     * Redirect users that can edit the page containing the block?
     *
     * @var bool|string
     */
    protected $redirectEditors;

    /**
     * Redirect users that can edit the page containing the block?
     *
     * @var bool|string
     */
    protected $keepQuerystring;

    /**
     * Show a message block when the block does not redirect?
     *
     * @var int|string
     */
    protected $showMessage;

    /**
     * Use a custom message?
     *
     * @var bool|string
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

    public function add()
    {
        $this->addOrEdit();
    }

    public function edit()
    {
        $this->addOrEdit();
    }

    /**
     * Validate the data set by user when adding/editing a block.
     *
     * @param mixed $data
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
     * @param mixed $data
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
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Controller\AbstractController::on_start()
     */
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
        if ($this->dontRedirectIPs !== '' && $this->isUserIpInList($this->dontRedirectIPs)) {
            return;
        }
        // Never redirect users belonging to specific groups
        if ($this->dontRedirectGroupIDs !== '' && array_intersect(explode(',', $this->dontRedirectGroupIDs), $this->getCurrentUserGroups()) !== []) {
            return;
        }
        // Never redirect users with specific operating systems
        if ($this->dontRedirectOperatingSystems !== '' && in_array($this->getCurrentUserOS(), explode('|', $this->dontRedirectOperatingSystems))) {
            return;
        }
        if ($this->redirectIPs !== '' && $this->isUserIpInList($this->redirectIPs)) {
            $redirect = true;
        } elseif ($this->redirectGroupIDs !== '' && array_intersect(explode(',', $this->redirectGroupIDs), $this->getCurrentUserGroups())) {
            $redirect = true;
        } elseif ($this->redirectOperatingSystems !== '' && in_array($this->getCurrentUserOS(), explode('|', $this->redirectOperatingSystems))) {
            $redirect = true;
        } elseif ($this->redirectLocales !== '' && $this->matchLocalesPatterns(explode('|', $this->redirectLocales))) {
            $redirect = true;
        } else {
            $redirect = false;
        }

        if (!$redirect) {
            return;
        }
        $response = $this->createRedirectResponse();
        if ($response === null) {
            return;
        }
        $response->prepare($this->request);
        $response->send();
        exit(0);
    }

    public function view()
    {
        $c = $this->getCurrentPage();
        if ($c !== null && $c->isEditMode()) {
            $output = '<div class="ccm-edit-mode-disabled-item"><div style="padding: 10px 5px">';
            $destinationUrl = $this->buildDestinationUrl(false);
            $loc = Localization::getInstance();
            $loc->pushActiveContext(Localization::CONTEXT_UI);
            try {
                if ($destinationUrl === '') {
                    $output .= t('This block will redirect selected users.');
                } else {
                    $output .= t('This block redirects selected users to %s', sprintf('<a href="%1$s">%1$s</a>', h($destinationUrl)));
                }
            } finally {
                $loc->popActiveContext();
            }
            $output .= '</div></div>';
            $this->set('output', $output);
            return;
        }
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
                $output = (string) $this->customMessage;
                if ($output !== '') {
                    $output = LinkAbstractor::translateFrom($output);
                }
            } else {
                $output = '<span class="redirect-block-message">';
                $destinationUrl = $this->buildDestinationUrl(false);
                $loc = Localization::getInstance();
                $loc->pushActiveContext(Localization::CONTEXT_UI);
                try {
                    if ($destinationUrl === '') {
                        $output .= t('This block will redirect selected users.');
                    } else {
                        $output = t('This block redirects selected users to %s', sprintf('<a href="%1$s">%1$s</a>', h($destinationUrl)));
                    }
                } finally {
                    $loc->popActiveContext();
                }
                $output .= '</span>';
            }
            $this->set('output', $output);
        }
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
            $validOperatingSystems = $this->app->make(OSDetector::class)->getOperatingSystemsList();
            foreach (['redirectOperatingSystems', 'dontRedirectOperatingSystems'] as $f) {
                $normalized[$f] = [];
                if (isset($data[$f]) && is_array($data[$f])) {
                    foreach ($data[$f] as $os) {
                        $os = is_string($os) ? $os : '';
                        if ($os !== '' && !in_array($os, $normalized[$f], true)) {
                            $normalized[$f][] = $os;
                            if (!in_array($os, $validOperatingSystems, true)) {
                                $errors->add(t('Invalid Operating System: %s', $os));
                            }
                        }
                    }
                }
                $normalized[$f] = implode('|', $normalized[$f]);
            }
            foreach (['redirectLocales' => 'redirectLocale'] as $f => $prefix) {
                $normalized[$f] = $this->normalizeLocales($prefix, $data, $errors);
            }
            $normalized['redirectEditors'] = (isset($data['redirectEditors']) && $data['redirectEditors']) ? 1 : 0;
            $normalized['keepQuerystring'] = (isset($data['keepQuerystring']) && $data['keepQuerystring']) ? 1 : 0;
            $normalized['redirectCode'] = empty($data['redirectCode']) ? 0 : (int) $data['redirectCode'];
            $redirectCodes = $this->getRedirectCodes();
            if (!isset($redirectCodes[$normalized['redirectCode']])) {
                $errors->add(t('Please specify the redirect type'));
            }
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

    private function addOrEdit()
    {
        $this->requireAsset('selectize');
        $ip = $this->getCurrentUserIP();
        $this->set('redirectCode', $this->getRedirectCode());
        $this->set('redirectCodes', $this->getRedirectCodes());
        $this->set('operatingSystemsList', $this->app->make(OSDetector::class)->getOperatingSystemsList());
        $this->set('myIP', ($ip === null) ? '' : $ip->toString());
        $this->set('myOS', $this->getCurrentUserOS());
        $this->set('allLanguages', Language::getAll(true, true));
        $this->set('allScripts', $this->getAllScripts());
        $this->set('allTerritories', Territory::getContinentsAndCountries());
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

    /**
     * @param bool $keepQuerystring
     *
     * @return string
     */
    private function buildDestinationUrl($keepQuerystring)
    {
        if ($this->redirectToCID) {
            $to = Page::getByID($this->redirectToCID);
            if (is_object($to) && !$to->isError()) {
                $destinationUrl = (string) $this->app->make('url/manager')->resolve([$to]);
            } else {
                $destinationUrl = '';
            }
        } else {
            $destinationUrl = (string) $this->redirectToURL;
        }
        if ($destinationUrl !== '' && $keepQuerystring) {
            $destinationUrl = $this->copyQuerystring($destinationUrl);
        }
        return $destinationUrl;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    private function createRedirectResponse()
    {
        $destinationUrl = $this->buildDestinationUrl($this->keepQuerystring);
        if ($destinationUrl === '') {
            return null;
        }
        $rf = $this->app->make(ResponseFactoryInterface::class);

        return $rf->redirect($destinationUrl, $this->getRedirectCode());
    }

    /**
     * @return string
     */
    private function getCurrentUserOS()
    {
        static $result;
        if (!isset($result)) {
            $result = $this->app->make(OSDetector::class)->detectOS($this->request);
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private static function getCurrentBrowserLocales()
    {
        static $result;
        if (!isset($result)) {
            $result = array_keys(Misc::getBrowserLocales());
        }

        return $result;
    }

    /**
     * @param string[] $patterns
     *
     * @return bool
     */
    private function matchLocalesPatterns(array $patterns)
    {
        $locales = $this->getCurrentBrowserLocales();
        foreach ($patterns as $pattern) {
            if ($this->matchLocalesPattern($locales, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $locales
     * @param string $pattern
     *
     * @return bool
     */
    private function matchLocalesPattern(array $locales, $pattern)
    {
        foreach ($locales as $locale) {
            if ($this->matchLocalePattern($locale, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $locales
     * @param string $pattern
     *
     * @return bool
     */
    private function matchLocalePattern($locale, $pattern)
    {
        $localeChunks = explode('-', $locale);
        $patternChunks = explode('-', $pattern);
        if ($localeChunks[0] !== $patternChunks[0]) {
            return false;
        }
        switch (count($localeChunks)) {
            case 1:
                $localeChunks = [$localeChunks[0], '', ''];
                break;
            case 2:
                if (strlen($localeChunks[1]) === 4) {
                    $localeChunks = [$localeChunks[0], $localeChunks[1], ''];
                } else {
                    $localeChunks = [$localeChunks[0], '', $localeChunks[1]];
                }
                break;
        }
        for ($i = 1; $i <= 2; $i++) {
            switch ($patternChunks[$i]) {
                case '*':
                    break;
                case '_':
                    if ($localeChunks[$i] !== '') {
                        return false;
                    }
                    break;
                default:
                    if ($localeChunks[$i] !== $patternChunks[$i]) {
                        return false;
                    }
                    break;
            }
        }
        return true;
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

    /**
     * @return int
     */
    private function getRedirectCode()
    {
        $redirectCode = (int) $this->redirectCode;

        return $redirectCode ?: Response::HTTP_TEMPORARY_REDIRECT;
    }

    /**
     * @return array
     */
    private function getRedirectCodes()
    {
        return [
            // 300
            Response::HTTP_MULTIPLE_CHOICES => [
                t('Multiple Choices'),
                t('For example when offering different languages'),
            ],
            // 301
            Response::HTTP_MOVED_PERMANENTLY => [
                t('Moved Permanently'),
                t('Redirect permanently from one URL to another passing link equity to the redirected page'),
            ],
            // 302
            Response::HTTP_FOUND => [
                t('Found'),
                t('Originally named "temporary redirect" in HTTP/1.0, superseded by 303 and 307 in HTTP/1.1'),
            ],
            // 303
            Response::HTTP_SEE_OTHER => [
                t('See Other'),
                t('Force a GET request to the new URL even if original request was POST'),
            ],
            // 307
            Response::HTTP_TEMPORARY_REDIRECT => [
                t('Temporary Redirect'),
                t('Provide a new URL for the browser to resubmit a GET or POST request'),
            ],
            // 308
            Response::HTTP_PERMANENTLY_REDIRECT => [
                t('Permanent Redirect'),
                t('Provide a new URL for the browser to resubmit a GET or POST request'),
            ],
        ];
    }

    /**
     * @param string $prefix
     *
     * @return string
     */
    private function normalizeLocales($prefix, array $data, ErrorList $errors)
    {
        $languages = isset($data["{$prefix}_language"]) ? $data["{$prefix}_language"] : null;
        if (!is_array($languages) || $languages === []) {
            return '';
        }
        $scripts = isset($data["{$prefix}_script"]) ? $data["{$prefix}_script"] : [];
        if (!is_array($scripts)) {
            $scripts = [];
        }
        $territories = isset($data["{$prefix}_territory"]) ? $data["{$prefix}_territory"] : [];
        if (!is_array($territories)) {
            $territories = [];
        }

        $locales = [];
        foreach ($languages as $index => $languageID) {
            $good = true;
            if (!is_string($languageID)) {
                $languageID = '';
            }
            if ($languageID === '') {
                $good = false;
            }
            $scriptID = isset($scripts[$index]) ? $scripts[$index] : '';
            if (!is_string($scriptID)) {
                $scriptID = '';
            }
            if ($languageID === '') {
                if ($scriptID !== '*') {
                    $errors->add(t(/*i18n: 'script' is the part of a locale identifier, for example 'Latn' in 'it_IT_Latn'*/'If you specify the script, you have to specify the language too'));
                    $good = false;
                }
            } elseif ($scriptID === '') {
                $errors->add(t(/*i18n: 'script' is the part of a locale identifier, for example 'Latn' in 'it_IT_Latn'*/'The script for the language %s is missing', $languageID));
                $good = false;
            }
            $territoryID = isset($territories[$index]) ? $territories[$index] : '';
            if (!is_string($territoryID)) {
                $territoryID = '';
            }
            if ($languageID === '') {
                if ($territoryID !== '*') {
                    $errors->add(t('If you specify the territory, you have to specify the language too'));
                    $good = false;
                }
            } elseif ($territoryID === '') {
                $errors->add(t('Missing territory for the language #%s', $languageID));
                $good = false;
            }
            if ($good) {
                $locales[] = "{$languageID}-{$scriptID}-{$territoryID}";
            }
        }

        return implode('|', $locales);
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private function copyQuerystring($url)
    {
        $qs = $this->request->query->all();
        if (!is_array($qs)) {
            return $url;
        }
        unset($qs['cID']);
        $token = $this->app->make('token');
        unset($qs[$token::DEFAULT_TOKEN_NAME]);
        if ($qs === []) {
            return $url;
        }
        try {
            $obj = Url::createFromUrl($url);
        } catch (RuntimeException $x) {
            return $url;
        }
        $obj->getQuery()->modify($qs);

        return (string) $obj;
    }

    /**
     * @return array
     */
    private function getAllScripts()
    {
        if (class_exists('Punic\Script')) {
            return \Punic\Script::getAllScripts(\Punic\Script::ALTERNATIVENAME_STANDALONE);
        }
        $scriptIDs = explode('|', 'Adlm|Afak|Aghb|Ahom|Arab|Aran|Armi|Armn|Avst|Bali|Bamu|Bass|Batk|Beng|Bhks|Blis|Bopo|Brah|Brai|Bugi|Buhd|Cakm|Cans|Cari|Cham|Cher|Chrs|Cirt|Copt|Cprt|Cyrl|Cyrs|Deva|Diak|Dogr|Dsrt|Dupl|Egyd|Egyh|Egyp|Elba|Elym|Ethi|Geok|Geor|Glag|Gong|Gonm|Goth|Gran|Grek|Gujr|Guru|Hanb|Hang|Hani|Hano|Hans|Hant|Hatr|Hebr|Hira|Hluw|Hmng|Hmnp|Hrkt|Hung|Inds|Ital|Jamo|Java|Jpan|Jurc|Kali|Kana|Khar|Khmr|Khoj|Kits|Knda|Kore|Kpel|Kthi|Lana|Laoo|Latf|Latg|Latn|Lepc|Limb|Lina|Linb|Lisu|Loma|Lyci|Lydi|Mahj|Maka|Mand|Mani|Marc|Maya|Medf|Mend|Merc|Mero|Mlym|Modi|Mong|Moon|Mroo|Mtei|Mult|Mymr|Nand|Narb|Nbat|Newa|Nkgb|Nkoo|Nshu|Ogam|Olck|Orkh|Orya|Osge|Osma|Palm|Pauc|Perm|Phag|Phli|Phlp|Phlv|Phnx|Plrd|Prti|Qaag|Rjng|Rohg|Roro|Runr|Samr|Sara|Sarb|Saur|Sgnw|Shaw|Shrd|Sidd|Sind|Sinh|Sogd|Sogo|Sora|Soyo|Sund|Sylo|Syrc|Syre|Syrj|Syrn|Tagb|Takr|Tale|Talu|Taml|Tang|Tavt|Telu|Teng|Tfng|Tglg|Thaa|Thai|Tibt|Tirh|Ugar|Vaii|Visp|Wara|Wcho|Wole|Xpeo|Xsux|Yezi|Yiii|Zanb|Zinh|Zmth|Zsye|Zsym|Zxxx|Zyyy|Zzzz');

        return array_combine($scriptIDs, $scriptIDs);
    }
}
