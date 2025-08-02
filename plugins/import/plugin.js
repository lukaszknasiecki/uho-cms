
	function checkall()
	{
		$('.field_checkbox').attr('checked','checked');
	}
	function uncheckall()
	{
		$('.field_checkbox').attr('checked',false);
	}
	$('#b_all').unbind('click').bind('click',checkall);
    $('#b_none').unbind('click').bind('click',uncheckall);
	
	