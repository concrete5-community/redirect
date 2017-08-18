<?php
use Concrete\Core\Editor\LinkAbstractor;

/* @var Concrete\Package\Redirect\Block\Redirect\Controller $controller */
/* @var Concrete\Core\Form\Service\Form $form */
/* @var Concrete\Core\Block\View\BlockView $this */
/* @var Concrete\Core\Block\View\BlockView $view */

/* @var int|null $redirectToCID */
/* @var string|null $redirectToURL */
/* @var string|null $redirectGroupIDs */
/* @var string|null $dontRedirectGroupIDs */
/* @var string|null $redirectIPs */
/* @var string|null $dontRedirectIPs */
/* @var string $myIP */
/* @var int|null $redirectEditors */
/* @var int|null $showMessage */
/* @var int|null $useCustomMessage */
/* @var string|null $customMessage */

defined('C5_EXECUTE') or die('Access denied.');

echo Core::make('helper/concrete/ui')->tabs([
    ['redirect-to', t('Destination page'), true],
    ['redirect-by-usergroup', t('Redirect by user groups')],
    ['redirect-by-ip', t('Redirect by IP address')],
    ['redirect-options', t('Options')],
]);

?>

<div class="ccm-tab-content" id="ccm-tab-content-redirect-to">
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
	<div class="form-group">
		<?= $form->select('redirectToType', $options, $selected) ?>
	</div>
	<div class="form-group redirect-to-type redirect-to-type-cid"<?= $selected === 'cid' ? '' : ' style="display: none;"' ?>>
		<?= $form->label('redirectToCID', t('Choose page')) ?>
		<?= Core::make('helper/form/page_selector')->selectPage('redirectToCID', $redirectToCID) ?>
	</div>
	<div class="form-group redirect-to-type redirect-to-type-url"<?= $selected === 'url' ? '' : ' style="display: none;"' ?>>
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

<div class="ccm-tab-content" id="ccm-tab-content-redirect-by-usergroup">
	<?php
    $groups = [];
    foreach ([
        ['redirectGroupIDs', t('Redirect members of these groups')],
        ['dontRedirectGroupIDs', t('Never redirect members of these groups')],
    ] as $info) {
        $varName = $info[0];
        $groups[$varName] = [];
        $list = isset($$varName) ? preg_split('/\D+/', $$varName, -1, PREG_SPLIT_NO_EMPTY) : [];
        foreach ($list as $gID) {
            $g = Group::getByID($gID);
            if ($g !== null) {
                $groups[$varName][$g->getGroupID()] = $g->getGroupDisplayName(false);
            }
        } ?>
        <div class="form-group">
			<a
				class="btn btn-default btn-xs pull-right"
				data-button="assign-groups"
				dialog-width="640"
				dialog-height="480"
				dialog-title="<?= t('Select group') ?>"
				dialog-modal="true"
				dialog-on-open="<?= h('window.redirectBlockCurrentRedirectGroup = ' . json_encode($varName)) ?>"
				dialog-on-close="window.redirectBlockCurrentRedirectGroup = null"
				href="<?= URL::to('/ccm/system/dialogs/group/search') ?>"
			><?= t('Select group') ?></a>
            <?= $form->label('', $info[1]) ?>
	    	<div class="redirect-group-list"></div>
	    	<?= $form->hidden($varName, '') ?>
	    </div>
	    <?php
    }
    ?>
	<script>
	window.redirectBlockCurrentRedirectGroup = null;
	$(document).ready(function() {
		function addGroup(category, id, name, initializing) {
            debugger;
			var $value = $('#' + category), $parent = $value.closest('.form-group'), cls = initializing ? 'row' : 'row animated bounceIn', $container;
			$value.val(($value.val() === '') ? ('|' + id + '|') : ($value.val() + id + '|')); 
			$parent.find('div.redirect-group-list').append($container = $('<div class="' + cls + '" />')
				.attr('data-group-id', id)
				.append($('<div class="col-md-12" />')
					.append($('<p />')
						.text(' ' + name)
						.prepend($('<a href="#"><i class="fa fa-trash-o"></i></a>')
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
            foreach ($groupsList as $gID => $gName) {
                ?>addGroup(<?= json_encode($groupsCategory) ?>, <?= $gID ?>, <?= json_encode($gName) ?>, true);<?php
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
		$('#ccm-tab-content-redirect-by-usergroup a[data-button=assign-groups]').dialog();
	});
	</script>
</div>


<div class="ccm-tab-content" id="ccm-tab-content-redirect-by-ip">
	<?php
    foreach ([
        ['redirectIPs', t('Redirect these IP addresses')],
        ['dontRedirectIPs', t('Never redirect these IP addresses')],
    ] as $info) {
        $varName = $info[0];
        $value = isset($$varName) ? implode("\n", preg_split('/[\\s,]+/', str_replace('|', ' ', $$varName), -1, PREG_SPLIT_NO_EMPTY)) : ''; ?>
	    <div class="form-group">
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
    if ($myIP !== '') {
        ?><div class="text-muted"><?= t('Your IP address is %s', "<code>$myIP</code>") ?></div><?php
    }
    ?>
</div>

<div class="ccm-tab-content" id="ccm-tab-content-redirect-options">
	<div class="form-group">
		<div class="checkbox">
			<label>
				<?= $form->checkbox('redirectEditors', '1', isset($redirectEditors) ? $redirectEditors : '0') ?>
				<?= t('Redirect users with permission to edit the page contents') ?>
			</label>
		</div>
	</div>
	<div class="form-group">
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
	<div class="form-group">
		<div class="checkbox">
			<label>
				<?= $form->checkbox('useCustomMessage', '1', $useCustomMessage) ?>
				<?= t('Use a custom message') ?>
			</label>
		</div>
	</div>
	<div class="form-group" id="reblo-customMessage"<?= $useCustomMessage ? '' : ' style="display: none"' ?>>
		<?= $form->label('customMessage', t('Custom message')) ?>
        <?= Core::make('editor')->outputBlockEditModeEditor('customMessage', isset($customMessage) ? LinkAbstractor::translateFromEditMode($customMessage) : '') ?>
    </div>

	<script>
	$(document).ready(function() {
		$('#ccm-tab-content-redirect-options .redactor-editor').css({'min-height': '0px', height: '100px'});
		$('#ccm-tab-content-redirect-options #useCustomMessage').on('change', function() {
			$('#ccm-tab-content-redirect-options #reblo-customMessage')[this.checked ? 'show' : 'hide']();
		});
	});
	</script>
</div>
