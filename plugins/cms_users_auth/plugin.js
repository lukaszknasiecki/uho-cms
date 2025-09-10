var Apps = Apps || {},
console = console || {log: function() { return true; } };

Apps.PluginAdminAuthorizations = function($, window)
{

  var init = function()
  {
    $('.serdelia-admin-dropdown li').unbind('click').bind('click',allowClick);
    $('.serdelia-admin-apply-dropdown li').unbind('click').bind('click',allowApplyClick);
    $('.serdelia-admin-apply').unbind('click').bind('click',allowApplySet);
    
    $('.serdelia-admin-dropdown-preset li').unbind('click').bind('click',allowClickPreset);
    $('.serdelia-admin-apply-preset').unbind('click').bind('click',allowApplyPresetSet);
    
  },
  allowClick = function(  )
  {
  	var now=$(this);
  	var parent=now.parent().prev();
    var id=now.data('id');
    var bid=parent.attr('id').substring(2);
	  $('#allow_'+bid).val(id);
    parent.removeClass('btn-success btn-danger btn-warning');
  	parent.html(now.text()+' <span class="caret"></span>');
  	parent.addClass('btn-'+now.data('class'));
  },
  allowApplyClick = function(  )
  {
    var now=$(this);
    var parent=now.parent().prev();
    var id=now.data('id');
    var bid=parent.attr('id').substring(2);
    $('#allow_'+bid).val(id);
    parent.removeClass('btn-success btn-danger btn-warning');
    parent.addClass(now.data('class'));
    parent.html(now.text()+' <span class="caret"></span>');
  },
  allowApplySet = function(  )
  {
    var now=$(this);
    var val=$('#allow_all').val();
    if (val>=0)
    {
      $('.serdelia-admin-dropdown li').parent().prev().removeClass('btn-success btn-danger btn-warning');    
      var selected=$('.serdelia-admin-apply-dropdown').children().eq(val);    
      $('.serdelia-admin-dropdown li').parent().prev().html(selected.data('label')+' <span class="caret"></span>');
      $('.serdelia-admin-hidden-field').val(val);
      $('.serdelia-admin-dropdown li').parent().prev().addClass(selected.data('class'));
    }
  };

  allowApplyPresetSet = function(  )
  {

    $('.serdelia-admin-dropdown [data-id="0"]').click();

    var json=$(this).data('json');
    
    if (json)
    {

      for (var key in json)
      {
        input=$('#allow_'+key);
        input.prev().find('[data-id="'+json[key]+'"]').click();
        
      }

   

    }
    
  };
   allowClickPreset = function(  )
  {
    var now=$(this);
    var parent=now.parent().prev();
    var json=now.data('json');    
    $('#apply_preset').data('json',json);
    parent.html(now.text()+' <span class="caret"></span>');
  },
 

  init();

  return {  };

}(jQuery, window);
