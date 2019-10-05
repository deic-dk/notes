listNotesRequest = false;

function listNotes(){
	if(listNotesRequest){
		listNotesRequest.abort();
		$('#spinner').remove();
	}
	var spinner = '<div id="spinner"><img src="'+ OC.imagePath('core', 'loading-small.gif') +'"></div>';
	$('#createNote').after(spinner);
	var arr = [];
	$('#notebooks a.chosen').each(function(){
		var path = "";
		/*$(this).parents('li.directory').each(function(){
			path =  $(this).find('a').first().attr('rel')+path;
		});*/
		path =  $(this).closest('li.directory').find('a').first().attr('rel')+path;
		arr.push(path);
	});
	if(arr.length===0){
		var baseDir = $('#app-content-notes').attr('basedir');
		arr.push(baseDir);
	}
	var tags = [];
	$('#loadTags ul#tags li.chosen').each(function(){
		tags.push($(this).find('i').first().attr('data-tag'));
	});
	listNotesRequest = $.post(OC.filePath('notes', 'ajax', 'actions.php'), {name: arr, tags: tags, action: "listnotes"} , function ( jsondata ){
		$('#spinner').remove();
		listNotesRequest = false;
		if(jsondata.status == 'success' ) {
			updateNotesList(tags,  jsondata.data);
		}
		else{
			OC.dialogs.alert( jsondata.data.message,  jsondata.data.title);
		}
	});
}

function updateNotesList(tags, data){
	var html = "";
	$('#notestable #fileList tr').remove();
	$('table#notestable thead tr th.metakey').remove();

	if(typeof tags!=='undefined' && tags.length===1 && typeof data[0] !=='undefined' && typeof data[0].dbkeys !=='undefined'){
		var headers =  mkNotesHeaders(data[0].dbkeys);
		$('table#notestable thead tr th#headerDelete').before(headers);
	}
	for (var i=0;  i<data.length; ++i) {
		html = html + '<tr data-id="'+data[i]["fileinfo"]["fileid"]+'">\n'+
'			<td class="notename">'+'\n'+
'				<div class="row">'+'\n'+
'					<div class="col-xs-8 filelink-wrap">'+'\n'+
//'						<a class="name"><i class="icon-pencil deic_green icon"></i>'+'\n'+
'						<a class="name" title="'+(typeof(data[i]["fileinfo"]["path"])==="undefined"?'':(data[i]["fileinfo"]["path"]).replace('"', '&#39;'))+
										'"><input id="select-files-'+i+'" type="checkbox" class="fileselect" path="'+data[i]["fileinfo"]["path"]+'" />'+'\n'+
'						<span class="nametext">'+data[i]["metadata"]["title"]+'</span></a>'+'\n'+
'					</div>'+'\n'+
		(typeof(data[i]["tags"])==="undefined" || $("#loadTags li.chosen").length===1?'':mkTagIcons(data[i]["tags"]))+
'				</div>'+'\n'+
'			</td>'+'\n'+
//'			<td><div class="path" title="'+(typeof(data[i]["fileinfo"]["path"])==="undefined"?'':data[i]["fileinfo"]["path"])+'">'+
//					(typeof(data[i]["fileinfo"]["path"])==="undefined"?'':data[i]["fileinfo"]["path"])+'</div></td>'+'\n'+
//'			<td><div class="tags">'+(typeof(data[i]["tags"])==="undefined"?'':data[i]["tags"].join(":"))+'</div></td>'+'\n'+
'			<td class="date"><div class="date">'+(typeof(data[i]["metadata"]["date"])==="undefined"?'':data[i]["metadata"]["date"])+'</div></td>'+'\n';
		
		if(typeof tags!=='undefined' && tags.length===1 && typeof data[i].dbkeys !=='undefined'){
			var newfields = mkNewFields(data[i].dbkeys, data[i]['dbmetadata'], tags[0]);
			html = html + newfields;
		}
		
		html = html + 
"			<td><a href='#' original-title='Delete' class='delete-note action icon icon-trash-empty'></a></td>"+"\n"+
"		</tr>";
	}
	$('#notestable #fileList').append(html);
	
	activateDeleteListen();
	activateShowMoreTags();
	setTableListeners();
	
	$('.summary .info').text(i+" "+t("notes", "note"+(i>1|| i==0?"s":"")));
	$('table.notestable tfoot tr td').attr('colspan', 5 + (typeof data.dbkeys !=='undefined'?data.dbkeys.length:0));
	$('#notestable tr').draggable(noteDragOptions);
}

function mkNotesHeaders(keys){
	if(typeof keys==='undefined' || keys==null){
		return "";
	}
	var html = "";
	for (var key in keys) {
		html = html +
"			<th id='header"+key+"' class='"+((typeof keys[key]!=='undefined' && typeof keys[key]['allowed_values']!=='undefined' &&
			keys[key]['allowed_values'].length>0)?"metakey predefined":"metakey")+" column-"+key+"'>"+"\n"+
"				<div keyid='"+keys[key]['id']+"' keyname='"+key+"' class='sort columntitle' data-sort='public'>"+"\n"+
"					<span>"+key+"</span>"+"\n"+
"				</div>"+"\n"+
"			</th>"
	}
	return html;
}

function activateDeleteListen(){
	$('table#notestable tbody#fileList span.deletetag').click(function(e){
		e.stopPropagation();
		var tagid = $(this).parent('span').attr('data-tag');
		var fileid = $(this).parent('span').parent('div').parent('div').parent('td').parent('tr').attr('data-id');
		fileid = parseInt(fileid);
		var fileIds = [fileid];
		$('#notestable td.notename input.fileselect:checked').each(function(){
			var id = parseInt($(this).attr('data-id'));
			if(fileIds.indexOf(id)==-1){
				fileIds.push(id);
			}
		});
		if(fileIds.length>1 || fileIds.length===1 && fileIds[0]!=fileid){
			OC.dialogs.confirm('Are you sure you want to delete a tag from multiple files? '+fileIds.length+':'+fileid+':'+fileIds.toSource(), 'Confirm deletion',
			function(res){
				if(res){
					removeTag(fileIds, tagid);
				}
			}
			 );
		}
		else{
			removeTag(fileIds, tagid);
		}
	});
}

function activateShowMoreTags(){
  $('tbody').on('click', 'span.more-tags', function(e){
		e.stopPropagation();
		$('.tipsy').last().remove();
		updateFileListTags($(this).parents('tr'), true, 10);
  });

	$('tbody').on('click', 'a.less-tags', function(e){
		e.stopPropagation();
		$('.tipsy').last().remove();
		updateFileListTags($(this).parents('tr'), false, 10);
	});
}

function removeTag(fileIds, tagid){
	$.ajax({
		async: false,
		url: OC.filePath('meta_data', 'ajax', 'removeFileTag.php'),
		data: {
			fileid: fileIds.join(':'),
			tagid:  tagid
		},
		success: function(response){
			listNotes();
		}
	});
}

function mkNewFields(keys, metadata, tag){
	var html = "";
	var i = 0;
	for(var key in keys){
		if(typeof keys[key]!=='undefined' && typeof keys[key]['allowed_values']!=='undefined' && keys[key]['allowed_values'].length>0){
			vals = JSON.parse(keys[key]['allowed_values']);
			html = html + "			<td column='"+key+"'><select>";
			html = html +
			"			<option></option>\n";
			for(var val in vals){
				html = html +
				"			<option"+(typeof metadata[key]!=='undefined' && metadata[key]==vals[val]?" selected='selected'":"")+">"+
								vals[val]+"</option>\n";
			}
			html = html + "			</select></td>";
		}
		else if(key=="due"){
			html = html +
			"			<td column='"+key+"'><div><input type='text' class='datepicker' value='"+
			(typeof metadata[key]==='undefined'?'':metadata[key])+"' /></div></td>"+"\n";
		}
		else{
			html = html +
			"			<td column='"+key+"'><div><input type='text' value='"+(typeof metadata[key]==='undefined'?'':metadata[key])+"'></div></td>"+"\n";

		}
		++i;
	}
	return html;
}

function mkTagIcons(tags){
	var tagwidth = 0;
	var overflow = 0;
	var html = '<div class="filetags-wrap col-xs-4">';
	if(typeof tags !== 'undefined'){
		$.each(tags, function(key, value) {
			var color = colorTranslate(value.color);
			if(tagwidth + value.name.length <= 10){
				html = html + '\
				<span data-tag=\''+value.id+'\' class=\'label outline label-'+color+'\'>\
				<span class="deletetag" style="display:none">\
				<i class=\'icon-cancel-circled\'></i>\
				</span>\
				<i class=\'icon-tag\'></i>\
				<span class=\'tagtext\'>'+value.name+'</span>\
				</span>\
				';
			}
			else{
				overflow += 1;
			}
			tagwidth += value.name.length;
		});
	}
	if(overflow > 0){
		 html = html + '<span class=\'label outline label-default more-tags\' title="Show more tags"><span class="tagtext">+'+overflow+' more</span></span>';
	}
	 html = html + '</div>';
	 return html;
}

function mkDragDroppable(){
	$('#notebooks #loadFolderTree li').droppable(notebookDropOptions);
	$('#notebooks #loadFolderTree li').draggable(notebookDragOptions);
}

function createNotebookFolderTree(){
	var baseDir = $('#app-content-notes').attr('basedir');
	$('#loadFolderTree').fileTree({
		script: '../../apps/chooser/jqueryFileTree.php',
		multiFolder: true,
		selectFile: false,
		showFiles: false,
		showRoot: false,
		showHidden: false,
		selectFolder: true,
		root: baseDir.replace(/\/$/, "")+'/',
		folder: '',
		file: $('#chosen_file').text(),
		dialogClass: 'notebooks',
		deleteIcons: true,
		callback: mkDragDroppable
}, /*single-click*/ function(file) {
	listNotes();
	//var targetPath = "" ;
	var targetPath = $('a.chosen').first().closest('li.directory').find('a').first().attr('rel');
	if($('a.chosen').length===1 && file===$('a.chosen').first().attr('rel')){
		/*$('a.chosen').first().parents('li.directory').each(function(){
			targetPath = $(this).find('a').first().attr('rel')+targetPath;
		});*/
	}
	if(typeof targetPath !== 'undefined'){
		var baseDir = $('#app-content-notes').attr('basedir');
		var replace = '|^'+baseDir+'|';
		var re = new RegExp('^'+baseDir);
		$('#newnotebook .editnote').val(targetPath.replace(re, ''));
		$('#newnote .editnote').val(targetPath.replace(re, ''));
	}
}, /*double-click*/function(file) {
		/*if(file.indexOf("/", file.length-1)==-1){// file double-clicked
			read_list_file();
			$("#dialog0").dialog("close");
		}*/
	}, /*delete callback*/ function(file) {
		// We're only deleting folders
		if(!file || !file.endsWith("/")){
			return false;
		}
		file = file.replace(/\/$/, "");
		OC.dialogs.confirm(t('notes', 'Are you sure you want to delete')+' '+file+'?', t('notes', 'Confirm delete'),
				function(res){
					if(res){
						$.post(OC.filePath('notes', 'ajax', 'actions.php'), {name: file, action: "deletenotebook" } , function ( jsondata ){
							if(jsondata.status == 'success' ) {
								createNotebookFolderTree();
							}
							else{
								OC.dialogs.alert( jsondata.data.message , jsondata.data.title );
							}
						});
					}
				},
				false
		);
	});
}

$(document).ready(function(){
	createNotebookFolderTree();
	$('#body-user tbody').unbind('click');
	activateDeleteListen();
	activateShowMoreTags();
});
