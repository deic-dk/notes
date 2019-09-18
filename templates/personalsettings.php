<fieldset id="notesPersonalSettings" class="section">
	<h2><?php p($l->t("Notes"));?></h2>
	<?php p($l->t("Notes folder"));?>
	<input type="text" id="notes_folder" value="<?php p(isset($_['notes_folder'])?$_['notes_folder']:''); ?>" placeholder="Choose folder"/>
	<label id="choose_notes_folder" class="btn btn-flat"><?php p($l->t("Browse"));?></label>
	<div class="notes_folder_dialog" display="none">
		<div class="loadFolderTree"></div>
		<div class="file" style="visibility: hidden; display:inline;"></div>
	</div>
	<br />
	<br />
	<label id="notes_settings_submit" class="button"><?php p($l->t('Save'));?></label>&nbsp;<label id="notes_msg"></label>
</fieldset>

