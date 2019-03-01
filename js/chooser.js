
$(document).ready(function(){

	$("li").click(function(){
			$(this).css("font-weight", "bold");
	});

	$('#loadFolderTree').fileTree({
			//root: '/',
			script: '../../apps/chooser/jqueryFileTree.php',
			multiFolder: true,
      selectFile: false,
			showFiles: false,
			showRoot: false,
			showHidden: false,
			selectFolder: true,
			root: 'Notes/',
			folder: '',
			file: $('#chosen_file').text(),
			dialogClass: 'notebooks'
	}, /*single-click*/ function(file) {
		/*$('#chosen_file').text(file);*/
	}, /*double-click*/function(file) {
			/*if(file.indexOf("/", file.length-1)==-1){// file double-clicked
				read_list_file();
				$("#dialog0").dialog("close");
			}*/
		});
});
