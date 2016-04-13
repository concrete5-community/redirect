<?php
defined('C5_EXECUTE') or die('Access denied.');

/* @var \Concrete\Core\Form\Service\Form $form */

echo Core::make('helper/concrete/ui')->tabs(array(
    array('redirect-to', t('Destination page'), true),
    array('redirect-by-usergroup', t('Redirect by user groups')),
    array('redirect-by-ip', t('Redirect by IP address')),
    array('redirect-options', t('Options')),
));

?>

<div class="ccm-tab-content" id="ccm-tab-content-redirect-to">
	<?php
        $redirectToCID = isset($redirectToCID) ? (int) $redirectToCID : 0;
        $redirectToURL = isset($redirectToURL) ? (string) $redirectToURL : '';
        $options = array();
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
		<?php echo $form->select('redirectToType', $options, $selected); ?>
	</div>
	<div class="form-group redirect-to-type redirect-to-type-cid"<?php echo $selected === 'cid' ? '' : ' style="display: none;"'; ?>>
		<?php echo $form->label('redirectToCID', t('Choose page')); ?>
		<?php echo Core::make('helper/form/page_selector')->selectPage('redirectToCID', $redirectToCID); ?>
	</div>
	<div class="form-group redirect-to-type redirect-to-type-url"<?php echo $selected === 'url' ? '' : ' style="display: none;"'; ?>>
		<?php echo $form->label('redirectToURL', t('URL')); ?>
		<?php echo $form->text('redirectToURL', $redirectToURL); ?>
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
    $groups = array();
    foreach (array(
        array('redirectGroupIDs', t('Redirect members of these groups')),
        array('dontRedirectGroupIDs', t('Never redirect members of these groups')),
    ) as $info) {
        $varName = $info[0];
        $groups[$varName] = array();
        $list = isset($$varName) ? preg_split('/\D+/', $$varName, -1, PREG_SPLIT_NO_EMPTY) : array();
        foreach ($list as $gID) {
            $g = \Group::getByID($gID);
            if ($g !== null) {
                $groups[$varName][$g->getGroupID()] = $g->getGroupDisplayName(false);
            }
        }
        ?>
	    <fieldset>
	    	<legend>
	    		<?php echo $info[1]; ?>
    			<a
    				class="btn btn-default btn-xs pull-right"
    				data-button="assign-groups"
    				dialog-width="640"
    				dialog-height="480"
    				dialog-title="<?php echo t('Select group') ?>"
    				dialog-modal="true"
    				dialog-on-open="<?php echo h('window.redirectBlockCurrentRedirectGroup = '.json_encode($varName)); ?>"
    				dialog-on-close="window.redirectBlockCurrentRedirectGroup = null"
    				href="<?php echo URL::to('/ccm/system/dialogs/group/search') ?>"
    			><?php echo t('Select group') ?></a>
	    	</legend>
	    	<div class="redirect-group-list"></div>
	    	<?php echo $form->hidden($varName, ''); ?>
	    </fieldset>
	    <?php
    }
    ?>
	<script>
	window.redirectBlockCurrentRedirectGroup = null;
	$(document).ready(function() {
		function addGroup(category, id, name, initializing) {
			var $value = $('#' + category), $parent = $value.closest('fieldset'), cls = initializing ? 'row' : 'row animated bounceIn', $container;
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
                ?>addGroup(<?php echo json_encode($groupsCategory); ?>, <?php echo $gID; ?>, <?php echo json_encode($gName); ?>, true);<?php

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
	foreach (array(
	    array('redirectIPs', t('Redirect these IP addresses')),
	    array('dontRedirectIPs', t('Never redirect these IP addresses')),
	) as $info) {
	    $varName = $info[0];
	    $value = isset($$varName) ? implode("\n", preg_split('/[\\s,]+/', str_replace('|', ' ', $$varName), -1, PREG_SPLIT_NO_EMPTY)) : '';
	    ?>
	    <div class="form-group">
	    	<?php echo $form->label(
	    	    $varName,
	    	    $info[1],
	    	    array(
    	    	    'class' => 'launch-tooltip',
	    	        'data-placement' => 'right',
	    	        'data-html' => 'true',
	    	        'title' => t(
	    	            "Separate multiple values by spaces, new lines or commas.<br />IPv4 and IPv6 addresses are supported.<br />You can specify single IP addresses as well as ranges (examples: %s)",
	    	            \Punic\Misc::join(array('<code>100.200.*.*</code>', '<code>100.200.0.0/16</code>', '<code>1:2::0/8</code>', '<code>1:2::*:*</code>)'))
	    	        ),
	    	    )
	    	); ?>
        	<?php echo $form->textarea($varName, $value, array('rows' => '5', 'style' => 'resize: vertical;')); ?>
	    </div>
	    <?php
	}
	?>
</div>

<div class="ccm-tab-content" id="ccm-tab-content-redirect-options">
	<div class="form-group">
		<div class="checkbox">
			<label>
				<?php echo $form->checkbox('redirectEditors', '1', isset($redirectEditors) ? $redirectEditors : '0'); ?>
				<?php echo t('Redirect users with permission to edit the page contents'); ?>
			</label>
		</div>
	</div>
	<div class="form-group">
		<?php echo $form->label('showRedirectMessage', t('Show block message')); ?>
		<?php echo $form->select(
            'showRedirectMessage',
            array(
                $controller::SHOWMESSAGE_NEVER => t('Never'),
                $controller::SHOWMESSAGE_EDITORS => t('Only for users that can edit the page contents'),
                $controller::SHOWMESSAGE_ALWAYS => t('Always'),
            ),
            isset($showRedirectMessage) ? $showRedirectMessage : $controller::SHOWMESSAGE_EDITORS
        ); ?>
	</div>
</div>
