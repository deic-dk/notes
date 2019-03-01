<div id="app-content" style="transition: all 0.3s ease 0s;">
	<div id="app-content-notes" class="viewcontainer">
		<div id="controls">
		
			<div class="button-row">
				<div class="actions creatable">
					<a id="createNotebook" class="btn btn-primary btn-flat" href="#">
						<i class="icon-folder"></i>
						<i class="icon"></i><?php p($l->t("New notebook"));?>
					</a>
					<a id="createNote" class="btn btn-primary btn-flat" href="#">
						<i class="icon-pencil"></i>
						<?php p($l->t("New note"));?>
					</a>
					<span class="float-right">
						<select id="priority"><option selected>Priority</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option></select>
						<select id="status"><option selected>Status</option><option value="0">0%</option><option value="10">10%</option><option value="20">20%</option><option value="30">30%</option><option value="40">40%</option><option value="50">50%</option><option value="60">60%</option><option value="70">70%</option><option value="80">80%</option><option value="90">90%</option><option value="100">100%</option></select>
						<input type="text" id="search" placeholder="<?php p($l->t('Search'));?>" />
						<!--<a id="searchNotes" type="button" class="btn btn-default btn-flat">
							<?php p($l->t("Search"));?>-->
						</a>
					</span>
				</div>
			</div>
			
			<div id="newnote" class="apanel">
				<span class="spanpanel" >
					<input class="editnote" type="text" placeholder="<?php p($l->t("New note name"));?>" />
					<select id="priority"><option selected>Template</option>
					<?php foreach($_['templates'] as $template){
						echo("<option value=\"".$template['path']."\">".$template['name']."</option>");
					}?>
					</select>
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
		<div><i class="icon icon-folder"></i>Notebooks</div>
		<div id="loadFolderTree"></div>
		<div><i class="icon icon-tag"></i>Tags</div>
		<div id="loadTags"></div>
	</div>

	<table id="notestable" class="panel">
	<thead class="panel-heading" >
		<tr>
			<th id="headerName" class="column-name">
				<div id="headerName-container" class="row">
					<div class="col-xs-3 col-sm-6">
						<div class="name sort columntitle" data-sort="descr">
							<span class="text-semibold"><?php p($l->t("Name"));?></span>
						</div>
					</div>
				</div>
			</th>
			<th id="headerPriority" class="column-priority">
				<div class="priority sort columntitle" data-sort="public">
					<span><?php p($l->t("Priority"));?></span>
				</div>
			</th>
			<th id="headerStatus" class="column-status">
				<div class="status sort columntitle" data-sort="size">
					<span><?php p($l->t("Status"));?></span>
				</div>
			</th>
			<th id="headerCreated" class="column-created">
				<div class="created sort columntitle" data-sort="size">
					<span><?php p($l->t("Created"));?></span>
				</div>
			</th>
			<th id="headerDue" class="column-due">
				<div class="due sort columntitle" data-sort="size">
					<span><?php p($l->t("Due"));?></span>
				</div>
			</th>
			<th></th>
		</tr>
	</thead>
	
	<tbody id='fileList'>
<?php

$count = 0;
foreach ($_['notes'] as $note) {
	$count++;
	echo "<tr>
		<td class='notename'>
			<div class='row'>
				<div class='col-xs-8 filelink-wrap'>
					<a class='name'><i class='icon-pencil deic_green icon'></i>
					<span class='nametext'>".$note['title']."</span></a>
				</div>
			</div>
		</td>
		<td><div class='priority'>".$note['priority']."</div></td>
		<td><div class='status'>".$note['status']."</div></td>
		<td><div class='created'>".$note['created']."</div></td>
		<td><div class='due'>".$note['due']."</div></td>
		<td><a href='#' original-title='Delete' class='delete-group action icon icon-trash-empty'></a></td>
	</tr>";
}

?>
		</tbody>
		<tfoot>
			<tr class="summary text-sm">
				<td colspan="6">
				<span class="info"><?php echo $count." notes"; ?></span>
				</td>
			</tr>
		</tfoot>
	</table>
</div>

<div id='dialogalert' title='Delete note confirmation' style='display:none;' >
	<p>Are you sure you want to delete this note?</p>
</div>
<div id='dialogalert' title='Delete notebook confirmation' style='display:none;' >
	<p>Are you sure you want to delete this notebook?</p>
</div>



