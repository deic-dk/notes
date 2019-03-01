$(document).ready(function() {
	
	$('a#createNote').click(function() {
		$('#newnote').slideToggle();
	});
	
	$('a#createNotebook').click(function() {
		$('#newnotebook').slideToggle();
	});
	
	$('a#syncNotes').click(function() {
		$('#sync').slideToggle();
	});

	$('#newnotebook #ok').on('click', function() {
		if($('#newnotebook .editnote').val() != "") {
			$.post(OC.filePath('notes', 'ajax', 'actions.php'), {name: $('#newnotebook .editnote').val(), action: "addnotebook" } , function ( jsondata ){
				if(jsondata.status == 'success' ) {
					$('#newnotebook').slideToggle();
					$('#newnotebook .editnote').val("");
					location.reload();
				}
				else{
					OC.dialogs.alert( jsondata.data.message , jsondata.data.title );
				}
			});
		}
	});
	
	$('#newnote #ok').on('click', function() {
		if( $('#newnote .editnote').val() == "") {
			return false;
		}
		$('#dialogalert').dialog({ buttons: [
			{id:'new_note', text: 'OK', click: function() {
			OC.UserGroup.joinGroup($('#newnote .editnote').val());
			$(this).dialog('close');
			}},
			{id:'new_note_cancel', text: 'Cancel',
			click: function() {
				$(this).dialog('close');
			}
			}]});
	});

	$('#newnotebook #cancel').click(function() {
		$('#newnotebook').slideToggle();
	});
	
	$('#newnote #cancel_join').click(function() {
		$('#newnote .editnote').val("");
		$('#newnote').slideToggle();
	});

	$("#notestable td .delete-note").live('click', function(ev) {
		ev.stopPropagation();
		var role = $(this).closest('tr').attr('role') ;
		var noteSelected = $(this).closest('tr').attr('note') ;
		var textHtml = $('#dialogalert').html().replace(
			role=='owner'?t('notes', 'Unshare'):t('notes', 'Delete'),
				role=='owner'? t('notes', 'Delete'):t('notes', 'Unshare'));
		 $('#dialogalert').html(textHtml);
		 $('#dialogalert').dialog({ buttons: [ { id:'delete_unshare_note', text: role == 'owner'?t('notes', 'Delete'):t('notes', 'Unshare'),
				click: function() {
			if (role == 'owner' || role == 'admin') {
				$.post(OC.filePath('notes', 'ajax', 'actions.php'), { note : noteSelected , action : "deletenote"} , function ( jsondata){
					if(jsondata.status == 'success' ) {
						location.reload();
					}
					else{
						OC.dialogs.alert( jsondata.data.message , jsondata.data.title ) ;
						}
					});
			}
			else{
				$.post(OC.filePath('notes', 'ajax', 'actions.php'), { note : noteSelected , action : "unsharenote"} , function ( jsondata){
					if(jsondata.status == 'success' ) {
						location.reload();
					}
					else{
						OC.dialogs.alert( jsondata.data.message , jsondata.data.title ) ;
					}
				});
			}
			$(this).dialog( 'close' );
		}},
		{id:'delete_unshare_note_cancel', text: 'Cancel',
			click: function() {
				$(this).dialog( 'close' );
			}
		}]});
	});

	$(document).click(function(e){
		if($(e.target).attr('notename')){
			editNote($(e.target).attr('notename'));
		}
	});
	
});

