var noteDragOptions = {
		revert: true,
		revertDuration: 300,
		opacity: 0.7,
		zIndex: 100,
		appendTo: 'body',
		cursorAt: { left: 24, top: 18 },
//		cursor: 'move',
		start: function(event, ui){
			$('div.app-notes div#notes').css('overflow-x', 'visible');
			$('div.app-notes div#notes').css('overflow-y', 'visible');
		},
		stop: function(event, ui) {
			$('div.app-notes div#notes').css('overflow-x', 'auto');
			$('div.app-notes div#notes').css('overflow-y', 'auto');
			}
	};

var notebookDragOptions = {
		revert: true,
		revertDuration: 300,
		opacity: 0.7,
		zIndex: 100,
		appendTo: 'body',
		cursorAt: { left: 24, top: 18 },
//		cursor: 'move',
		start: function(event, ui){
		},
		stop: function(event, ui) {
		}
	};

//sane browsers support using the distance option
if ( $('html.ie').length === 0) {
	noteDragOptions['distance'] = 20;
	notebookDragOptions['distance'] = 20;
}

var notebookDropOptions = {
		hoverClass: "canDrop",
		greedy: true,
		drop: function( event, ui ) {
			var targetPath = "";
			targetPath = targetPath+$(this).find('a').first().attr('rel');
			/*$(this).parents('li.directory').each(function(){
				targetPath = $(this).find('a').first().attr('rel')+targetPath;
			});*/
			
			if(ui.draggable.find('td.notename input.fileselect').length>0){
				var files = $('#notestable td.notename input.fileselect:checked');
				if (files.length===0) {
					// single one selected without checkbox?
					files = ui.draggable.closest('tr').find('td.notename input.fileselect');
				}
				var baseDir = $('#app-content-notes').attr('basedir');
				files.each(function(){
					var name = $(this).attr('path').split(/\//).pop();
					var src = $(this).attr('path');
					var dest = /*baseDir+*/targetPath+name;
					//alert(src+'-->'+dest);
					moveNote(src, dest);
				});
				}
			
			if(ui.draggable.hasClass('directory') || ui.draggable.find('li.directory a').length>0){
				if(ui.draggable.closest('li.directory').find('a.chosen').length){
					var folders = $('#notebooks a.chosen');
				}
				if(typeof folders==='undefined' ||  folders.length===0) {
					// single one selected without choosing?
					folders = ui.draggable.closest('li.directory').find('a');
				}
				var baseDir = $('#app-content-notes').attr('basedir');
				folders.each(function(){
					var folderPath = "";
					/*$(this).parents('li.directory').each(function(){
						folderPath = $(this).find('a').first().attr('rel')+folderPath;
					});*/
					folderPath = $(this).closest('li.directory').find('a').first().attr('rel')+folderPath;
					folderPath = folderPath.replace(/\/$/, "");
					var name = folderPath.split(/\//).pop();
					var src = /*baseDir+*/folderPath;
					var dest = /*baseDir+*/targetPath+name;
					var count = (folderPath.match(/\//g) || []).length;
					var len = folderPath.length;
					if(targetPath.substr(0, len)!=folderPath){
						//alert(src+'-->'+dest);
						moveNotebook(src, dest);
					}
				});
			}
			
			return !event;
			
		},
		tolerance: 'pointer'
	};

var tagDropOptions = {
		hoverClass: "canDrop",
		greedy: true,
		drop: function( event, ui ) {
			var targetTag = $(this).closest('li[data-id^=tag-]').find('i').first().attr('data-tag');
			
			if(ui.draggable.find('td.notename input.fileselect').length>0){
				var files = $('#notestable td.notename input.fileselect:checked');
				if (files.length===0) {
					// single one selected without checkbox?
					files = ui.draggable.closest('tr').find('td.notename input.fileselect');
				}
				files.each(function(){
					//alert($(this).attr('path')+'-->'+targetTag);
					tagNote($(this).closest('tr').attr('data-id'), targetTag);
				});
				}
			
			if(ui.draggable.hasClass('directory') || ui.draggable.find('li.directory a').length>0){
				// Ignore dragged folders
			}
			
			return !event;
			
		},
		tolerance: 'pointer'
	};

function tagNote(fileid, tagid){
	$.ajax({
		url: OC.filePath('meta_data', 'ajax', 'updateFileInfo.php'),
		async: false,
		timeout: 200,
		data: {
			fileid: fileid,
			tagid: tagid
		},
		type: "POST",
		success: function(result) {
			listNotes();
		},
	});
}

function fixTags(){
	$('#notebooks #loadTags li').droppable(tagDropOptions);
	$('#loadTags ul#tags li').click(function(ev){
		if(!ev.metaKey && !ev.ctrlKey){
			$('#loadTags ul#tags li').removeClass('chosen');
		}
		if($(ev.target).closest('li').hasClass('chosen')){
			$(ev.target).closest('li').removeClass('chosen');
		}
		else{
			$(ev.target).closest('li').addClass('chosen');
		}
		listNotes();
	});
}

function updateTags(){
	var fileIds = $('#notestable #fileList tr').map(function() {
    	return $(this).attr('data-id');
    }).get();
	$('#loadTags ul#tags li[data-id^=tag-]').remove();
	defaultTags = $('#defaultTags li');
	$('#loadTags ul#tags').append(defaultTags.clone());
	if(!fileIds.length){
		fixTags();
		return;
	}
	$.ajax({
		url: OC.filePath('meta_data', 'ajax', 'getTags.php'),
		data: {sortValue: 'color', direction: 'asc', onlyFileId: fileIds.join(':')},
		success: function(response){
		if(response){
				var tags = '';
				$('#loadTags ul li#tags').show();
				$.each(response['tags'].reverse(), function(key, value) {
					if(!$('i.icon-tag[data-tag='+value.id+']').length){
						tags = tags+'\
						<li data-id="tag-'+value.id+'">\
						<i class="icon icon-tag tag-'+colorTranslate(value.color)+'" data-tag="'+value.id+'"></i>\
						<span>'+value.name+'</span>\
						</li>';
					}
				});
				$('#loadTags ul#tags').append(tags);
				fixTags();
			}
		}
	});
}

function deleteNotes(paths){
	$('#deleteNoteAlert').dialog({ buttons: [ {id:'delete_note', text: t('notes', 'Delete'),
		click: function() {
			$.post(OC.filePath('notes', 'ajax', 'actions.php'), {name: paths , action: "deletenote"} , function (jsondata){
				if(jsondata.status == 'success' ) {
					listNotes();
				}
				else{
					OC.dialogs.alert(t('notes',  jsondata.data.message) , t('notes',  jsondata.data.title)) ;
					}
				});
			$(this).dialog( 'close' );
		}
	},
	{id:'delete_note_cancel', text: 'Cancel',
		click: function() {
			$(this).dialog( 'close' );
		}
	}]});
}

function moveNote(path, target){
	$('#moveNoteAlert').dialog({ buttons: [ {id:'move_note', text: t('notes', 'Move'),
		click: function() {
			$.post(OC.filePath('notes', 'ajax', 'actions.php'), {name: path, target: target , action: "movenote"} , function (jsondata){
				if(jsondata.status == 'success' ) {
					listNotes();
				}
				else{
					OC.dialogs.alert(t('notes',  jsondata.data.message) , t('notes',  jsondata.data.title)) ;
				}
			});
			$(this).dialog( 'close' );
		}
	},
	{id:'delete_note_cancel', text: 'Cancel',
		click: function() {
			$(this).dialog( 'close' );
		}
	}]});
}

function moveNotebook(path, target){
	$('#moveNotebookAlert').dialog({ buttons: [ {id:'move_notebook', text: t('notes', 'Move'),
		click: function() {
			$.post(OC.filePath('notes', 'ajax', 'actions.php'), {name: path, target: target , action: "movenotebook"} , function (jsondata){
				if(jsondata.status == 'success' ) {
					createNotebookFolderTree();
				}
				else{
					OC.dialogs.alert(t('notes',  jsondata.data.message) , t('notes',  jsondata.data.title)) ;
					}
				});
			$(this).dialog( 'close' );
		}
	},
	{id:'delete_note_cancel', text: 'Cancel',
		click: function() {
			$(this).dialog( 'close' );
		}
	}]});
}

function searchNotes(query){
	var tags = [];
	$('#loadTags ul#tags li.chosen').each(function(){
		tags.push($(this).find('i').first().attr('data-tag'));
	});
	var folders = [];
	$('#notebooks a.chosen').each(function(){
		folders.push($(this).closest('li.directory').find('a').first().attr('rel'));
	});
	$.post(OC.filePath('notes', 'ajax', 'actions.php'), {name: query, folders: folders, tags: tags, action: "searchnotes" } , function ( jsondata ){
		if(jsondata.status == 'success' ) {
			updateNotesList(tags, jsondata.data)
		}
		else{
			OC.dialogs.alert(t('notes',  jsondata.data.message) , t('notes',  jsondata.data.title)) ;
		}
	});
}

function setTableListeners(){
	$("#notestable td .delete-note").live('click', function(ev) {
		ev.stopPropagation();
		var path = $(ev.target).closest('tr').find('input.fileselect').attr('path') ;
		deleteNotes([path]);
	});
	
	$("#notestable th#headerDelete a.delete-note").click(function(ev) {
		ev.stopPropagation();
		var paths = [];
		$('input.fileselect:checked').each(function(){
			paths.push($(this).attr('path'))
		});
		deleteNotes(paths);
	});

	// Don't believe this is used...
	/*$(document).click(function(e){
		if($(e.target).attr('notename')){
			editNote($(e.target).attr('notename'));
		}
	});*/
	
	$('#notestable tr').draggable(noteDragOptions);
	
	$('input#select_all_files').change(function(){
		if(this.checked) {
			$('input.fileselect').prop('checked', true);
			$('#notestable th#headerDelete a.delete-note').removeClass('hidden');
		}
		else{
			$('input.fileselect').prop('checked', false);
			$('#notestable th#headerDelete a.delete-note').addClass('hidden');
		}
	});
	
	$('#notestable td span.nametext').live('click', function(ev){
		oldTop = $('#content-wrapper').scrollTop();
		$('#content-wrapper').scrollTop(0);
		var path = $(ev.target).closest('a').find('input.fileselect').first().attr('path');
		var dir = OC.dirname(path);
		var filename = OC.basename(path);
		// Text
		$.when(typeof window.showFileEditor !== 'undefined' && window.showFileEditor(dir, filename)).then(function () {
			$('#notes').fadeTo('slow', 0.1);
			$('#notebooks').fadeTo('slow', 0.1);
			$('#app-content-notes #search').fadeTo('slow', 0.1);
			// Markdown
			var editor = new OCA.Files_Markdown.Editor($('#editor'), $('head')[0], dir);
			typeof window.aceEditor !== 'undefined' && window.aceEditor.setAutoScrollEditorIntoView(true);
			typeof window.aceEditor !== 'undefined' && editor.init(window.aceEditor.getSession());
			$('#app-content-notes #controls .button-row').hide();
		}).then(function(){
			// Joplin apparently strips the class jop-noMdConv when resizing, so we cannot use this to identify jopin images.
			// We'll have to rely on src^=":/"
			//$('img.jop-noMdConv[src^=":/"]').each(function(){
			$('img[src^=":/"]').each(function(){
				var src = $(this).attr('src').replace(/:/, '/apps/notes/ajax/actions.php?action=getresource&requesttoken='+oc_requesttoken+'&name=');
				$(this).attr('src', src);
				$(this).removeAttr('height');
			});
			$('#editor_close').click(function(){
				$('#app-content-notes #controls .button-row').show();
				$('#content-wrapper').scrollTop(oldTop);
				$('#notes').fadeTo('slow', 1);
				$('#notebooks').fadeTo('slow', 1);
				$('#app-content-notes #search').fadeTo('slow', 1);
				// The title might have changed
				newName = $('#preview_wrapper #md_preview h1').first().text();
				path = $('#editor.ace_editor').attr('data-dir')+'/'+ $('#editor.ace_editor').attr('data-filename');
				$('#notestable #fileList input.fileselect[path="'+path+'"]').parent().find('.nametext').text(newName);
			});
		});
	});
	
	$('input.fileselect').change(function(){
		if(this.checked || $('input.fileselect:checked').length){
			$('#notestable th#headerDelete a.delete-note').removeClass('hidden');
			if($('input.fileselect:checked').length==$('input.fileselect').length){
				$('input#select_all_files').prop('checked', true);
			}
			else{
				$('input#select_all_files').prop('checked', false);
			}
		}
		else{
			$('#notestable th#headerDelete a.delete-note').addClass('hidden');
			$('input#select_all_files').prop('checked', false);
		}
	});
	$('.dropdown-filter-dropdown').remove();
	var options = {};
	//options.columnSelector = '.metakey.predefined';
	var tags = [];
	var tagids = [];
	$('#loadTags ul#tags li.chosen').each(function(){
		tags.push($(this).find('span').first().text());
		tagids.push($(this).find('i').first().attr('data-tag'));
	});
	// TODO: perhaps generalize this beyond hiding todo.done - make hide_column_key_values configurable
	if(tags.length==1 && tags[0]=='todo'){
		options.hide_column_key_values = {'status':'done'};
	}
	$('table#notestable').excelTableFilter(options);
	
	if(tagids.length==1){
		$('table#notestable tr td select, #notestable tr td .datepicker, #notestable tr td input').change({tagid: tagids[0]}, function(ev){
			var fileid = $(this).parents('tr').first().attr('data-id');
			var tagid = ev.data.tagid;
			var keyname = $(this).parents('td').attr('column');
			var keyid = $('#notestable thead th div.columntitle[keyname='+keyname+']').attr('keyid');
			var val = $(this).val();
			$.ajax({
				url: OC.filePath('notes', 'ajax', 'actions.php'),
				type: "POST",
				data: {
				action: 'update_file_key',
				keyId: keyid,
				tagId: tagid,
				fileId: fileid,
				//type: 'controlled',
				value: val,
				touch: 'yes'
				},
				success: function(result) {
					// TODO: also touch the note file to trigger Joplin clients to pick up the metadata change (notably todo done)
				}
			});
		});
	}
	
	$('.datepicker').each(function(){
		if($(this).hasClass('hasDatepicker')){
			return false;
		}
		var valdate = $(this).val();
		$(this).datepicker();
		$(this).datepicker("option", "dateFormat", "yy-mm-dd");
		if(typeof valdate!=='undefined' && valdate!=''){
			$(this).datepicker("setDate", new Date(valdate+"T12:00:00-00:00"));
		}
	});

}

function addNote(position){
	if($('#newnote .editnote').val() != "") {
	var params = {name: $('#newnote .editnote').val(), template: $('#template').val(), 
			action: "addnote" };
	if(typeof position !== 'undefined'){
		params.position = position;
	} 
	$.post(OC.filePath('notes', 'ajax', 'actions.php'), params , function ( jsondata ){
		if(jsondata.status == 'success' ) {
			//$('#newnotebook').slideToggle();
			$('#newnotebook .editnote').val("");
			listNotes();
			updateTags();
		}
		else{
			OC.dialogs.alert(t('notes',  jsondata.data.message) , t('notes',  jsondata.data.title)) ;
		}
	});
	}
}

$(document).ready(function() {

	$("#app-content-notes input#search").on('keyup', function (e) {
		if (e.keyCode == 13) {
			searchNotes($("#app-content-notes input#search").val());
		}
	});
	
	$('a#createNote').click(function() {
		$('#newnote').slideToggle();
		if($('div#notes').hasClass('lowered') && !$('div#newnotebook:visible').length){
			$('div#notes').removeClass('lowered');
		}
		else{
			$('div#notes').addClass('lowered');
		}
	});
	
	$('a#createNotebook').click(function() {
		$('#newnotebook').slideToggle();
		if($('div#notes').hasClass('lowered') && !$('div#newnote:visible').length){
			$('div#notes').removeClass('lowered');
		}
		else{
			$('div#notes').addClass('lowered');
		}
	});

	$('#newnotebook #ok').on('click', function() {
		if($('#newnotebook .editnote').val() != "") {
			$.post(OC.filePath('notes', 'ajax', 'actions.php'), {name: $('#newnotebook .editnote').val(), action: "addnotebook" } , function ( jsondata ){
				if(jsondata.status == 'success' ) {
					//$('#newnotebook').slideToggle();
					$('#newnotebook .editnote').val("");
					createNotebookFolderTree();
				}
				else{
					OC.dialogs.alert(t('notes',  jsondata.data.message) , t('notes',  jsondata.data.title)) ;
				}
			});
		}
	});
	
	
	
	$('#newnote #ok').on('click', function() {
		if($('#newnote .editnote').val().indexOf("/")<0) {
			OC.dialogs.alert(t("notes", "Please create note inside a notebook.") ,  t("notes", "Select notebook"));
			return;
		}
		
		var options = {
			  enableHighAccuracy: true,
			  timeout: 5000,
			  maximumAge: 0
			};
		
	  if (navigator.geolocation) {
	    navigator.geolocation.getCurrentPosition(addNote,
	    		function(err){
	    			OC.dialogs.alert(t('notes',  "Location information not available") , t('notes', "No location"));
	    			addNote();
	    		},
	    		options);
	  }
	  else {
	  	addNote();
	  }
	});
	
	$('#newnotebook #cancel').click(function() {
		$('#newnotebook').slideToggle();
		$('div#notes').removeClass('lowered');
	});
	
	$('#newnote #cancel').click(function() {
		//$('#newnote .editnote').val("");
		$('#newnote').slideToggle();
		$('div#notes').removeClass('lowered');
	});
	
	setTableListeners();

	updateTags();
	
	OCA.Files_Markdown.Editor.prototype.getUrl = function (path) {
		if (!path) {
			return path;
		}
		if (path.substr(0, 7) === 'http://' || path.substr(0, 8) === 'https://' || path.substr(0, 3) === '://') {
			return path;
		}
		else {
			if (path.substr(0, 2) == ':/' && $('#app-content-notes:visible').length) {
				// Support Joplin-style image links
				path = path.replace(/:/, '/'+$('#app-content-notes:visible').attr('basedir')+'/.resource');
			}
			if (path.substr(0, 1) !== '/') {
				path = this.dir + '/' + path;
			}
			if(path.replace(/\/\//, '/').substr(0, 17) == '/Notes/.resource/'){
				return OC.webroot+
	      '/apps/notes/ajax/actions.php?action=getresource&name='+path.replace(/\/\//, '/').substr(17)+'&requesttoken='+oc_requesttoken+'"';
			}
			else{
				return OC.generateUrl('apps/files/ajax/download.php?dir={dir}&files={file}', {
					dir: OC.dirname(path),
					file: OC.basename(path)
				});
			}
		}
	};

	$('#content.app-notes #notebooks i.toggle-notebooks').click(function(ev){
		if($(ev.target).hasClass('icon-angle-down')){
			$('#content.app-notes #notebooks #loadFolderTree').slideUp()
			$(ev.target).removeClass('icon-angle-down');
			$(ev.target).addClass('icon-angle-right')
		}
		else{
			$('#content.app-notes #notebooks #loadFolderTree').slideDown()
			$(ev.target).removeClass('icon-angle-right');
			$(ev.target).addClass('icon-angle-down')
		}
	});
	
	$('#content.app-notes #notebooks i.toggle-tags').click(function(ev){
		if($(ev.target).hasClass('icon-angle-down')){
			$('#content.app-notes #notebooks #loadTags').slideUp()
			$(ev.target).removeClass('icon-angle-down');
			$(ev.target).addClass('icon-angle-right')
		}
		else{
			$('#content.app-notes #notebooks #loadTags').slideDown()
			$(ev.target).removeClass('icon-angle-right');
			$(ev.target).addClass('icon-angle-down')
		}
	});
	
});

