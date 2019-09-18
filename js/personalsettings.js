function submit_form(){
	var folder = $('#notes_folder').text();
	$.ajax({
		type:'POST',
		url:OC.linkTo('notes','ajax/actions.php'),
				data:{'action': 'setnotesfolder', 'name': folder},
				async:false,
				success:function(msg){
					$("#notes_msg").html("Settings saved");
				},
				error:function(s){
					$("#notes_msg").html("Unexpected error!");
				}
	});
}

function chooseNotesFolder(folder){
  $('#notes_folder').val(folder)
}

function stripTrailingSlash(str) {
  if(str.substr(-1)=='/') {
	str = str.substr(0, str.length - 1);
  }
  if(str.substr(1)!='/') {
	str = '/'+str;
  }
  return str;
}

function stripLeadingSlash(str) {
  if(str.substr(0,1)=='/') {
	str = str.substr(1, str.length-1);
  }
  return str;
}

$(document).ready(function(){

	var choose_notes_folder_dialog;
	var buttons = {};
	buttons[t("notes", "Choose")] = function() {
		folder = stripTrailingSlash($('#notes_folder').text());
		chooseNotesFolder(folder);
		choose_notes_folder_dialog.dialog("close");
 	};
 	buttons[t("importer", "Cancel")] = function() {
		choose_notes_folder_dialog.dialog("close");
	};
	choose_notes_folder_dialog = $("div.notes_folder_dialog").dialog({//create dialog, but keep it closed
   title: t("notes", "Choose notes folder"),
    autoOpen: false,
    height: 440,
    width: 620,
    modal: true,
    dialogClass: "no-close",
    autoOpen: false,
    resizeable: false,
    draggable: false,
    buttons: buttons
  });

  $('#choose_notes_folder').live('click', function(){
	choose_notes_folder_dialog.dialog('open');
	choose_notes_folder_dialog.show();
	folder = stripLeadingSlash($('#notes_folder').val());
	$('.notes_folder_dialog div.loadFolderTree').fileTree({
	  //root: '/',
	  script: '../../apps/chooser/jqueryFileTree.php',
	  multiFolder: false,
	  selectFile: false,
	  selectFolder: true,
	  folder: folder,
	  file: ''
	},
	// single-click
	function(file) {
	  $('#notes_folder').text(file);
	},
	// double-click
	function(file) {
	  if(file.indexOf("/", file.length-1)!=-1){// folder double-clicked
			chooseNotesFolder(file);
			choose_notes_folder_dialog.dialog("close");
	  }
	});
  });

	$("fieldset#notesPersonalSettings #notes_settings_submit").click(function(){
		if($('#notes_folder').val()){
			submit_form();
		}
	});
  
});
