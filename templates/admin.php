<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="teamfolder-nodownload" class="section">
	<h2><?php p($l->t('Restrict downloads')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Default: download allowed. Tick a box to hide the download button for that group in the team folder and to block sync and WebDAV clients. A user who has access through another group without a block may still download.')); ?></p>
	<p class="settings-hint"><?php p($l->t('Also blocked: copy or move out of the folder, ZIP, versions, trash, direct links and download/print in ONLYOFFICE. Viewing stays possible – this is a hurdle, not copy protection.')); ?></p>
	<p class="settings-hint"><?php p($l->t('Remove the "Share" permission from blocked groups, otherwise a public link allows downloading. Teams (circles) are not taken into account.')); ?></p>
	<?php if (empty($_['names'])): ?>
		<p><?php p($l->t('No team folders found.')); ?></p>
	<?php else: ?>
		<table>
			<thead><tr><th><?php p($l->t('Team folder')); ?></th><th><?php p($l->t('Group')); ?></th><th><?php p($l->t('Block download')); ?></th></tr></thead>
			<tbody>
			<?php foreach ($_['names'] as $id => $name): ?>
				<?php foreach ($_['groups'][$id] ?? [] as $group): ?>
					<tr>
						<td><?php p($name); ?></td>
						<td><?php p($group); ?></td>
						<td><input type="checkbox" class="checkbox" id="tnd-<?php p($id . '-' . $group); ?>"
							data-folder="<?php p($id); ?>" data-group="<?php p($group); ?>"
							<?php if (in_array($group, $_['blocked'][$id] ?? [], true)) { print_unescaped('checked'); } ?>>
							<label for="tnd-<?php p($id . '-' . $group); ?>"></label></td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		<button id="teamfolder-nodownload-save" class="button primary"><?php p($l->t('Save')); ?></button>
		<span id="teamfolder-nodownload-msg"></span>
	<?php endif; ?>
</div>
