<?php

use Concrete\Core\Editor\LinkAbstractor;

defined('C5_EXECUTE') or die('Access denied.');

/**
 * @var Concrete\Package\Redirect\Block\Redirect\Controller $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Core\Application\Service\UserInterface $userInterface
 * @var MLRedirect\UI $ui
 * @var Concrete\Core\Editor\EditorInterface $editor
 * @var array $redirectCodes
 * @var string[] $operatingSystems
 * @var array $languages
 * @var array $scripts
 * @var array $territories
 * @var string $myIP
 * @var string $myOS
 * @var int|null $redirectToCID
 * @var string $redirectToURL
 * @var int $redirectCode
 * @var Concrete\Core\User\Group\Group[] $redirectGroups
 * @var Concrete\Core\User\Group\Group[] $dontRedirectGroups
 * @var string $redirectIPs
 * @var string $dontRedirectIPs
 * @var string $redirectOperatingSystems
 * @var string $dontRedirectOperatingSystems
 * @var string $redirectLocales
 * @var bool $redirectEditors
 * @var bool $keepQuerystring
 * @var int $showMessage
 * @var bool $useCustomMessage
 * @var string $customMessage
 */

echo $userInterface->tabs([
    ['redirect-to', t('Destination'), true],
    ['redirect-by-usergroup', t('User Groups')],
    ['redirect-by-ip', t('IP Addresses')],
    ['redirect-by-os', t('Operating Systems')],
    ['redirect-by-locale', t('Languages')],
    ['redirect-options', t('Options')],
]);
?>
<?= $ui->tabStartTabContainers ?>
    <div class="<?= $ui->tabContentClassActive ?>" id="<?= $ui->tabIDPrefix ?>redirect-to"<?= $ui->tabContentAdditionalAttributes ?>>
        <?php
        $redirectToCID = isset($redirectToCID) ? (int) $redirectToCID : 0;
        $redirectToURL = isset($redirectToURL) ? (string) $redirectToURL : '';
        $options = [];
        if ($redirectToCID === 0 && $redirectToURL === '') {
            $options[''] = t('Please select');
            $selected = '';
        }
        $options['cid'] = t('Another page');
        if ($redirectToCID !== 0) {
            $selected = 'cid';
        }
        $options['url'] = t('External URL');
        if ($redirectToCID === 0 && $redirectToURL !== '') {
            $selected = 'url';
        }
        ?>
        <div class="<?= $ui->formGroup ?>">
            <?= $form->select('redirectToType', $options, $selected) ?>
        </div>
        <div class="<?= $ui->formGroup ?> redirect-to-type redirect-to-type-cid"<?= $selected === 'cid' ? '' : ' style="display: none;"' ?>>
            <?= $form->label('redirectToCID', t('Choose page')) ?>
            <?= Core::make('helper/form/page_selector')->selectPage('redirectToCID', $redirectToCID) ?>
        </div>
        <div class="<?= $ui->formGroup ?> redirect-to-type redirect-to-type-url"<?= $selected === 'url' ? '' : ' style="display: none;"' ?>>
            <?= $form->label('redirectToURL', t('URL')) ?>
            <?= $form->text('redirectToURL', $redirectToURL) ?>
        </div>
        <script>
            $(document).ready(function() {
                var $s = $('#redirectToType');
                $s.on('change', function() {
                    $('div.redirect-to-type').hide('fast');
                    $('div.redirect-to-type-' + $s.val()).show('fast');
                });
            });
        </script>
    </div>

    <div class="<?= $ui->tabContentClassInactive ?>" id="<?= $ui->tabIDPrefix ?>redirect-by-usergroup"<?= $ui->tabContentAdditionalAttributes ?>>
        <?php
        $groupSections = [
            ['redirectGroupIDs', t('Redirect members of these groups'), $redirectGroups],
            ['dontRedirectGroupIDs', t('Never redirect members of these groups'), $dontRedirectGroups],
        ];
        if ($ui->majorVersion >= 9) {
            foreach ($groupSections as $info) {
                $fieldName = $info[0];
                $label = $info[1];
                ?>
                <div class="<?= $ui->formGroup ?>" id="app-<?= $fieldName ?>" v-cloak>
                    <a class="btn <?= $ui->btnSecondary ?> btn-sm pull-right" v-on:click.prevent="pickGroup" href="#"><?= t('Select group') ?></a>
                    <?= $form->label('', $label) ?>
                    <div class="row" v-for="group in groups" v-bind:class="group.initializing ? 'row' : 'row animated bounceIn'">
                        <div class="col-md-12">
                            <p><a href="#" v-on:click.prevent="removeGroup(group)"><i class="<?= $ui->faTrash ?>"></i></a> {{ group.name }}</p>
                        </div>
                    </div>
                    <?= $form->hidden($fieldName, '', ['v-bind:value' => 'serializedGroupIDs']) ?>
                </div>
                <?php
            }
            ?>
            <script>
            <?php
            foreach ($groupSections as $info) {
                $serializedGroups = [];
                $groups = $info[2];
                /** @var Concrete\Core\User\Group\Group[] $groups */
                foreach ($groups as $group) {
                    $serializedGroups[] = [
                        'id' => (int) $group->getGroupID(),
                        'name' => $group->getGroupDisplayName(false),
                        'initializing' => true,
                    ];
                }
                ?>
                new Vue({
                    el: '#app-<?= $info[0] ?>',
                    data() {
                        return {
                            groups: <?= json_encode($serializedGroups) ?>,
                        };
                    },
                    methods: {
                        pickGroup() {
                            window.ConcreteUserGroupManager.launchDialog((data) => {
                                const group = {
                                    id: data.gID,
                                    name: data.gDisplayName || data.gName,
                                };
                                if (this.groups.some((g) => g.id === group.id)) {
                                    return;
                                }
                                this.groups.push(group);
                            });
                        },
                        removeGroup(group) {
                            const index = this.groups.indexOf(group);
                            if (index >= 0) {
                                this.groups.splice(index, 1);
                            }
                        },
                    },
                    computed: {
                        serializedGroupIDs() {
                            const ids = [];
                            this.groups.forEach((group) => ids.push(group.id));
                            return ids.join('|');
                        },
                    },
                });
                <?php
            }
            ?>
            </script>
            <?php
        } else {
            $groups = [];
            /** @var Concrete\Core\User\Group\Group[][] $groups */
            foreach ($groupSections as $info) {
                $fieldName = $info[0];
                $label = $info[1];
                $groups[$fieldName] = $info[2];
                ?>
                <div class="<?= $ui->formGroup ?>">
                    <a
                        class="btn btn-default btn-xs pull-right"
                        data-button="assign-groups"
                        dialog-width="640"
                        dialog-height="480"
                        dialog-title="<?= t('Select group') ?>"
                        dialog-modal="true"
                        dialog-on-open="<?= h('window.redirectBlockCurrentRedirectGroup = ' . json_encode($fieldName)) ?>"
                        dialog-on-close="window.redirectBlockCurrentRedirectGroup = null"
                        href="<?= URL::to('/ccm/system/dialogs/group/search') ?>"
                    ><?= t('Select group') ?></a>
                    <?= $form->label('', $label) ?>
                    <div class="redirect-group-list"></div>
                    <?= $form->hidden($fieldName, '') ?>
                </div>
                <?php
            }
            ?>
            <script>
            window.redirectBlockCurrentRedirectGroup = null;
            $(document).ready(function() {
                function addGroup(category, id, name, initializing) {
                    var $value = $('#' + category), $parent = $value.closest('.<?= $ui->formGroup ?>'), cls = initializing ? 'row' : 'row animated bounceIn', $container;
                    $value.val(($value.val() === '') ? ('|' + id + '|') : ($value.val() + id + '|'));
                    $parent.find('div.redirect-group-list').append($container = $('<div class="' + cls + '" />')
                        .attr('data-group-id', id)
                        .append($('<div class="col-md-12" />')
                            .append($('<p />')
                                .text(' ' + name)
                                .prepend($('<a href="#"><i class="<?= $ui->faTrash ?>"></i></a>')
                                    .on('click', function(e) {
                                        e.preventDefault();
                                        var v = $value.val(), rm = '|' + id + '|';
                                        if (v === rm) {
                                            $value.val('');
                                        } else {
                                            $value.val(v.replace(rm, '|').replace(/\|\|/g, '|'));
                                        }
                                        $container.hide('fast', function() {$container.remove();});
                                    })
                                )
                            )
                        )
                    );
                }
                <?php
                foreach ($groups as $groupsCategory => $groupsList) {
                    foreach ($groupsList as $group) {
                        ?>addGroup(<?= json_encode($groupsCategory) ?>, <?= $group->getGroupID() ?>, <?= json_encode($group->getGroupDisplayName(false)) ?>, true);<?php
                    }
                }
                ?>
                ConcreteEvent.subscribe('SelectGroup', function(e, data) {
                    if (window.redirectBlockCurrentRedirectGroup === null) {
                        return;
                    }
                    addGroup(window.redirectBlockCurrentRedirectGroup, data.gID, data.gName);
                    jQuery.fn.dialog.closeTop();
                });
                $('#<?= $ui->tabIDPrefix ?>redirect-by-usergroup a[data-button=assign-groups]').dialog();
            });
            </script>
            <?php
        }
        ?>
    </div>

    <div class="<?= $ui->tabContentClassInactive ?>" id="<?= $ui->tabIDPrefix ?>redirect-by-ip"<?= $ui->tabContentAdditionalAttributes ?>>
        <?php
        foreach ([
            ['redirectIPs', t('Redirect these IP addresses')],
            ['dontRedirectIPs', t('Never redirect these IP addresses')],
        ] as $info) {
            $varName = $info[0];
            $value = isset($$varName) ? implode("\n", preg_split('/[\\s,]+/', str_replace('|', ' ', $$varName), -1, PREG_SPLIT_NO_EMPTY)) : ''; ?>
            <div class="<?= $ui->formGroup ?>">
                <?= $form->label(
                    $varName,
                    $info[1],
                    [
                        'class' => 'launch-tooltip',
                        'data-placement' => 'right',
                        'data-html' => 'true',
                        'title' => t(
                            'Separate multiple values by spaces, new lines or commas.<br />IPv4 and IPv6 addresses are supported.<br />You can specify single IP addresses as well as ranges (examples: %s)',
                            \Punic\Misc::join(['<code>100.200.*.*</code>', '<code>100.200.0.0/16</code>', '<code>1:2::0/8</code>', '<code>1:2::*:*</code>)'])
                        ),
                    ]
                ) ?>
                <?= $form->textarea($varName, $value, ['rows' => '5', 'style' => 'resize: vertical;']) ?>
            </div>
            <?php
        }
        ?>
        <div class="text-muted">
            <?php
            echo t(
                    'Accepted values are single addresses (IPv4 like %1$s, and IPv6 like %2$s) and ranges in subnet format (IPv4 like %3$s, and IPv6 like %4$s).',
                    '<code>127.0.0.1</code>',
                    '<code>::1</code>',
                    '<code>127.0.0.1/24</code>',
                    '<code>::1/8</code>'
            );
            if ($myIP !== '') {
                ?><br /><?= t('Your IP address is %s', "<code>$myIP</code>") ?>
                <?php
            }
            ?>
        </div>
    </div>

    <div class="<?= $ui->tabContentClassInactive ?>" id="<?= $ui->tabIDPrefix ?>redirect-by-os"<?= $ui->tabContentAdditionalAttributes ?>>
        <?php
        foreach ([
            ['redirectOperatingSystems', t('Redirect these Operating Systems')],
            ['dontRedirectOperatingSystems', t('Never redirect these Operating Systems')],
        ] as $info) {
            $varName = $info[0];
            $values = isset($$varName) ? preg_split('/\|+/', $$varName, -1, PREG_SPLIT_NO_EMPTY) : []; ?>
            <div class="<?= $ui->formGroup ?>">
                <?= $form->label(
                    $varName,
                    $info[1]
                ) ?>
                <?= $form->selectMultiple($varName, array_combine($operatingSystems, $operatingSystems), $values) ?>
            </div>
            <?php
        }
        if ($myOS !== '') {
            ?><div class="text-muted"><?= t('Your Operating System is %s', "<code>$myOS</code>") ?></div><?php
        }
        ?>
        <script>
        $('#redirectOperatingSystems,#dontRedirectOperatingSystems').selectize({
            plugins: ['remove_button']
        });
        </script>
    </div>

    <div class="<?= $ui->tabContentClassInactive ?>" id="<?= $ui->tabIDPrefix ?>redirect-by-locale"<?= $ui->tabContentAdditionalAttributes ?>>
        <?= $form->label('', t('Redirect browsers providing these languages')) ?>
        <table class="table table-condensed table-hover">
            <thead>
                <tr>
                    <th><?= t('Language') ?></th>
                    <th><?= t('Script') ?></th>
                    <th><?= t('Territory') ?></th>
                    <th style="width: 1px"></th>
                </tr>
            </thead>
            <tbody id="redirect-edit-locales"></tbody>
        </table>
        <?php
        $languageOptions = '<option value=""></option>';
        foreach ($languages as $languageCode => $languageName) {
            $languageOptions .= '<option value="' . h($languageCode) . '">' . h($languageName) . '</option>';
        }
        $scriptOptions = '<option value="*">&lt;' . h(tc('script', 'any')) . '&gt;</option><option value="_">&lt;' . h(tc('script', 'none')) . '&gt;</option>';
        foreach ($scripts as $scriptCode => $scriptName) {
            $scriptOptions .= '<option value="' . h($scriptCode) . '">' . h($scriptName) . '</option>';
        }
        $territoryOptions = '<option value="*">&lt;' . h(tc('territory', 'any')) . '&gt;</option><option value="_">&lt;' . h(tc('territory', 'none')) . '&gt;</option>';
        foreach ($territories as $territory) {
            $territoryOptions .= '<optgroup label="' . h($territory['name']) . '">';
            foreach ($territory['children'] as $childTerritoryID => $childTerritory) {
                $territoryOptions .= '<option value="' . h($childTerritoryID) . '">' . h($childTerritory['name']) . '</option>';
            }
            $territoryOptions .= '</optgroup>';
        }
        ?>
        <script>
        (function() {
            var $container = $('#redirect-edit-locales');
            function addRow(locale) {
                var chunks = typeof locale === 'string' && locale.length > 0 ? locale.split('-') : [];
                switch (chunks.length) {
                    case 0:
                        chunks = ['', '*', '*'];
                        break;
                    case 1:
                        chunks = [chunks[0], '*', '*'];
                        break;
                    case 2:
                        if (chunks[1].length < 4) {
                            chunks = [chunks[0], '*', chunks[1]];
                        } else {
                            chunks = [chunks[0], chunks[1], '*'];
                        }
                        break;
                }
                var $tr;
                $container.append($tr = $('<tr />'));
                $tr.append($('<td />')
                    .append($('<select name="redirectLocale_language[]"><?= $languageOptions ?></select>')
                        .val(chunks[0])
                    )
                );
                $tr.append($('<td />')
                    .append($('<select name="redirectLocale_script[]"><?= $scriptOptions ?></select>')
                        .val(chunks[1])
                    )
                );
                $tr.append($('<td />')
                    .append($('<select name="redirectLocale_territory[]"><?= $territoryOptions ?></select>')
                        .val(chunks[2])
                    )
                );
                $tr.append($('<td style="vertical-align: middle" />')
                    .append($('<a href="#"><i class="danger text-danger <?= $ui->faTrash ?>"></i></a>')
                        .on('click', function(e) {
                            e.preventDefault();
                            $tr.remove();
                            checkEmpty();
                        })
                ));
                $tr.find('select')
                    .selectize({
                        searchField: ['value', 'text'],
                        render: {
                            item: function(data, escape) {
                                if (data.text === data.value || data.value === '_' || data.value === '*') {
                                    return '<div class="item">' + escape(data.text) + '</div>';
                                }
                                return '<div class="item" style="width: 95%">' + escape(data.text) + ' <code class="pull-right" style="font-size:10px">' + escape(data.value) + '</code></div>';
                            },
                            option: function (data, escape) {
                                var r = '<div class="option">' + escape(data.text);
                                if (data.text !== data.value && data.value !== '_' && data.value !== '*') {
                                    r += '<code class="pull-right" style="font-size:10px">' + escape(data.value) + '</code>';
                                }
                                r += '</div>';
                                return r;
                            }
                        }
                    })
                    .on('change', function() {
                        checkEmpty();
                    })
                ;
            }
            function checkEmpty() {
                var someEmpty = false;
                $container.find('tr').each(function(_, tr) {
                    var isEmpty = true;
                    $(tr).find('select[name="redirectLocale_language[]"]').each(function(_, select) {
                        var val = $(select).val();
                        if (typeof val === 'string' && val.length > 0) {
                            isEmpty = false;
                            return false;
                        }
                    });
                    if (isEmpty) {
                        someEmpty = true;
                        return false;
                    }
                });
                if (someEmpty === false) {
                    addRow('');
                }
            }
            <?php
            foreach (preg_split('/\|/', isset($redirectLocales) ? (string) $redirectLocales : '', -1, PREG_SPLIT_NO_EMPTY) as $l) {
                ?>addRow(<?= json_encode($l) ?>);<?php
            }
            ?>
            checkEmpty();
        })();
        </script>
    </div>

    <div class="<?= $ui->tabContentClassInactive ?>" id="<?= $ui->tabIDPrefix ?>redirect-options"<?= $ui->tabContentAdditionalAttributes ?>>
        <?php
        $redirectCodeOptions = [];
        $redirectCodeMessages = [];
        foreach ($redirectCodes as $redirectCodesID => $redirectCodesData) {
            $redirectCodeOptions[$redirectCodesID] = $redirectCodesData[0];
            $redirectCodeMessages[$redirectCodesID] = $redirectCodesData[1];
        }
        ?>
        <div class="<?= $ui->formGroup ?>">
            <?= $form->label('redirectCode', t('Redirect type')) ?>
            <?= $form->select(
                'redirectCode',
                $redirectCodeOptions,
                $redirectCode,
                [
                    'required' => 'required',
                ]
            ) ?>
            <div id="redirectCodeMessage" class="text-muted small">&nbsp;</div>
            <script>
            $(document).ready(function() {
                var redirectCodeMessages = <?= json_encode($redirectCodeMessages) ?>,
                    $redirectCode = $('#redirectCode'),
                    $redirectCodeMessage = $('#redirectCodeMessage');
                $redirectCode
                    .on('change', function() {
                        var redirectCode = parseInt($redirectCode.val());
                        if (redirectCode) {
                            $redirectCodeMessage
                                .html('<code>' + redirectCode + '</code>&nbsp')
                                .append($('<span />').text(redirectCodeMessages[redirectCode]))
                            ;
                        } else {
                            $redirectCodeMessage.html('&nbsp;');
                        }
                    })
                    .trigger('change')
                ;
            });
            </script>
        </div>
        <div class="<?= $ui->formGroup ?>">
            <?= $form->label('showMessage', t('Show block message')) ?>
            <?= $form->select(
                'showMessage',
                [
                    $controller::SHOWMESSAGE_NEVER => t('Never'),
                    $controller::SHOWMESSAGE_EDITORS => t('Only for users that can edit the page contents'),
                    $controller::SHOWMESSAGE_ALWAYS => t('Always'),
                ],
                isset($showMessage) ? $showMessage : $controller::SHOWMESSAGE_EDITORS
            ) ?>
        </div>
        <?php
        $useCustomMessage = isset($useCustomMessage) ? (bool) $useCustomMessage : false;
        ?>
        <div class="<?= $ui->formGroup ?>">
            <?= $form->label('showMessage', t('Other options')) ?>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('redirectEditors', '1', isset($redirectEditors) ? $redirectEditors : '0') ?>
                    <?= t('Redirect users with permission to edit the page contents') ?>
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('keepQuerystring', '1', isset($keepQuerystring) ? $keepQuerystring : '0') ?>
                    <?= t('Keep querystring parameters when redirecting users') ?>
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('useCustomMessage', '1', $useCustomMessage) ?>
                    <?= t('Use a custom message') ?>
                </label>
            </div>
        </div>
        <div class="<?= $ui->formGroup ?>" id="reblo-customMessage"<?= $useCustomMessage ? '' : ' style="display: none"' ?>>
            <?= $form->label('customMessage', t('Custom message')) ?>
            <?= $editor->outputBlockEditModeEditor('customMessage', isset($customMessage) ? LinkAbstractor::translateFromEditMode($customMessage) : '') ?>
        </div>
        <script>
        $(document).ready(function() {
            $('#<?= $ui->tabIDPrefix ?>redirect-options .redactor-editor').css({'min-height': '0px', height: '100px'});
            $('#<?= $ui->tabIDPrefix ?>redirect-options #useCustomMessage').on('change', function() {
                $('#<?= $ui->tabIDPrefix ?>redirect-options #reblo-customMessage')[this.checked ? 'show' : 'hide']();
            });
        });
        </script>
    </div>
<?= $ui->tabEndTabContainers ?>
