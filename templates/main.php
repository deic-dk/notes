<div id="app-content" style="transition: all 0.3s ease 0s;">
	<div id="app-content-notes" basedir="<?php echo $_['notesdir'];?>" class="viewcontainer">
		<div id="controls">
		<?php echo OCA\Notes\Lib::mkTagsList($_['default_tags']);?>
			<div class="button-row">
				<div class="actions creatable">
					<a id="createNotebook" class="btn btn-primary btn-flat" href="#">
						<i class="icon-folder"></i><i class="icon-plus"></i>
						<i class="icon"></i><?php p($l->t("New notebook"));?>
					</a>
					<a id="createNote" class="btn btn-primary btn-flat" href="#">
						<i class="icon-pencil"></i>
						<?php p($l->t("New note"));?>
					</a>
					<span class="float-right">
						<input type="text" id="search" placeholder="<?php p($l->t('Search notes'));?>" />
						<!--<a id="searchNotes" type="button" class="btn btn-default btn-flat">
							<?php p($l->t("Search"));?>-->
						</a>
					</span>
				</div>
			</div>
			
			<div id="newnote" class="apanel">
				<span class="spanpanel" >
					<input class="editnote" type="text" placeholder="<?php p($l->t("New note name"));?>" />
					<select id="template" title="<?php p($l->t("Template"));?>"><option selected></option>
					<?php foreach($_['templates'] as $template){
						$path = preg_replace('|^files/|', '', $template['path']);
						echo("<option value=\"".$path."\">".$template['title']."</option>");
					}?>
					</select>
					<span class="newnote-span">
						<div id="ok" class="btn-note" title="<?php p($l->t("Create new note"));?>">
							<a class="btn btn-default btn-flat" href="#"><?php p($l->t("Add"));?></a>
						</div>
						<div id="cancel" class="btn-note" original-title="">
							<a class="btn btn-default btn-flat" href="#"><?php p($l->t("Cancel"));?></a>
						</div>
					</span>
				</span>
			</div>
			<div id="newnotebook" class="apanel">
				<span class="spanpanel" >
					<input class="editnote" type="text" placeholder="<?php p($l->t("New notebook name"));?>" />
					<span class="newnote-span">
						<div id="ok" class="btn-note" original-title="">
							<a class="btn btn-default btn-flat" href="#"><?php p($l->t("Add"));?></a>
						</div>
						<div id="cancel" class="btn-note" original-title="">
							<a class="btn btn-default btn-flat" href="#"><?php p($l->t("Cancel"));?></a>
						</div>
					</span>
				</span>
			</div>
			<div id="sync" class="apanel">
				<span class="spanpanel" >
					<span class="newnote-span">
						<div id="ok" class="btn-note" original-title="">
							<a class="btn btn-default btn-flat" href="#"><?php p($l->t("Add"));?></a>
						</div>
						<div id="cancel" class="btn-note" original-title="">
							<a class="btn btn-default btn-flat" href="#"><?php p($l->t("Cancel"));?></a>
						</div>
					</span>
				</span>
			</div>
		</div>
	</div>

	<div id="notebooks">
		<div><i class="icon icon-folder"></i>Notebooks<i class="toggle-notebooks icon-angle-down"></i></div>
		<div id="loadFolderTree"></div>
		<div><i class="icon icon-tags"></i>Tags<i class="toggle-tags icon-angle-down"></i></div>
		<div id="loadTags">
			<ul id="tags"></ul>
		</div>
	</div>

	<div id="notes">
	<table id="notestable" class="panel">
	<thead class="panel-heading" >
		<tr>
			<th id="headerName" class="column-name">
				<div id="headerName-container" class="row">
					<div class="col-xs-4 col-sm-1">
						<input type="checkbox" id="select_all_files" class="select-all"/>
						<label for="select_all_files"></label>
					</div>
						<div>
						<div class="name sort columntitle" data-sort="descr">
							<span class="text-semibold"><?php p($l->t("Name"));?></span>
						</div>
					</div>
				</div>
			</th>
<!--			<th id="headerPath" class="column-path">
				<div class="path sort columntitle" data-sort="public">
					<span><?php p($l->t("Path"));?></span>
				</div>
			</th>
 			<th id="headerTags" class="column-tags">
				<div class="tags sort columntitle" data-sort="size">
					<span><?php p($l->t("Tags"));?></span>
				</div>
			</th>-->
			<th id="headerDate" class="column-date">
				<div class="date sort columntitle" data-sort="size">
					<span><?php p($l->t("Date"));?></span>
				</div>
			</th>
			<th id="headerDelete" class="column-delete">
				<a href='#' title='<?php p($l->t('Delete all checked'));?>' class='delete-note icon icon-trash-empty hidden'></a>
			</th>
		</tr>
	</thead>
	
	<tbody id='fileList'>
<?php

$count = 0;
foreach ($_['notes'] as $note) {
	$count++;
	echo "<tr data-id='".$note['fileinfo']['fileid']."'>
		<td class='notename'>
			<div class='row'>
				<div class='col-xs-8 filelink-wrap'>
					<a class='name' title='".
					(empty($note['fileinfo']['path'])?"":str_replace("'", "&#39;", $note['fileinfo']['path']))."'>
					<input id='select-files-".$count."' type='checkbox' class='fileselect' path='".$note['fileinfo']['path']."'/>
					<!--<i class='icon-pencil deic_green icon'></i>-->
					<span class='nametext'>".$note['metadata']['title']."</span>
					</a>
				</div>".
				(empty($note['tags'])?"":OCA\Notes\Lib::mkTagIcons($note['tags'])).
"			</div>
		</td>".
//		<td><div class='path' title='".(empty($note['fileinfo']['path'])?"":$note['fileinfo']['path'])."'>".
//			(empty($note['fileinfo']['path'])?"":$note['fileinfo']['path'])."</div></td>".
//"		<td><div class='tags'>".(empty($note['tags'])?"":implode(":", $note['tags']))."</div></td>"."
"		<td class='date'><div class='date'>".(empty($note['metadata']['date'])?"":$note['metadata']['date'])."</div></td>
		<td><a href='#' title='".$l->t("Delete")."' class='delete-note action icon icon-trash-empty'></a></td>
	</tr>";
}

?>
		</tbody>
		<tfoot>
			<tr class="summary text-sm">
				<td colspan="5">
				<span class="info"><?php echo $count." ".$l->t("note".($count>1||$count==0?"s":"")); ?></span>
				</td>
			</tr>
		</tfoot>
	</table>
	</div>
</div>

<div id='deleteNoteAlert' title=<?php p($l->t('Delete note confirmation'));?> style='display:none;' >
	<p><?php p($l->t('Are you sure you want to delete this note?'));?></p>
</div>
<div id='deleteNotebookAlert' title=<?php p($l->t('Delete notebook confirmation'));?> style='display:none;' >
	<p><?php p($l->t('Are you sure you want to delete this notebook?'));?></p>
</div>
<div id='moveNoteAlert' title=<?php p($l->t('Delete note confirmation'));?> style='display:none;' >
	<p><?php p($l->t('Are you sure you want to move this note?'));?></p>
</div>
<div id='moveNotebookAlert' title=<?php p($l->t('Delete notebook confirmation'));?> style='display:none;' >
	<p><?php p($l->t('Are you sure you want to move this notebook?'));?></p>
</div>

	<div id="app-navigation">
	</div>

