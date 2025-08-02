var Apps = Apps || {},
console = console || {log: function() { return true; } };

Apps.Page = function($, window)
{

  var init = function()
  {
    $('.serdelia-updateField').unbind('click').bind('click',updateField);
    $('.serdelia-removeRecord').unbind('click').bind('click',removeRecord);
    $('.serdelia-removeRecordConfirm').unbind('click').bind('click',removeRecordConfirm);
    $('.serdelia-page-tooltip').tooltip();
    $('.serdelia-button-loader').unbind('click').bind('click',addLoaderIcon);
    $('.serdelia_page_search-control-trigger').unbind('click').bind('click',pageSearchControlTrigger);
    $('.serdelia-page-search-toggle').unbind('click').bind('click',pageSearchToggle);
    $('.serdelia-grid-container').unbind('mouseover').bind('mouseover',gridItemMouseOver);
    $('.serdelia-grid-container').unbind('mouseout').bind('mouseout',gridItemMouseOut);
    $('.serdelia-edit-new-window').unbind('click').bind('click',newWindowClick);
    $('.serdelia-page-toggle-help').unbind('click').bind('click',changeToggleHelp);

    setTimeout(function() 
    {
      var object=$('.serdelia-highlight');
      if (object && object.offset()) $('html,body').animate({scrollTop:object.offset().top-180}, 1000,"easeInOutQuint");
    }, 0);


  },
  newWindowClick=function (event)
  {
    event.stopPropagation();
  },
  pageSearchControlTrigger=function (event)
  {
    var f=$(this).data('field');
    var ff=$('.'+f);
    f=$('#'+f);
    if (!f.is(":visible"))
    {
      f.show();
      $('span:first', this).removeClass('glyphicon-plus');
      $('span:first', this).addClass('glyphicon-minus');
      f.attr("disabled",false);
      ff.attr("disabled",false);
    }
    else
      {
        f.css('display','none');
        f.attr("disabled",true);
        ff.attr("disabled",true);
        $('span:first', this).removeClass('glyphicon-minus');
        $('span:first', this).addClass('glyphicon-plus');
      }
  },
  pageSearchToggle = function( event  )
  {
    var f=$('#serdelia_page_search');
    if (!f.is(":visible"))
      {
        f.show();
      }
      else
        {
          f.hide();
        }
  },
  updateField = function( event  )
  {
      event.stopPropagation();

      var data=$(this).data('update');
      var value=data['value'];
      var parent=$(this).closest('.serdelia-grid-container');
      if (data['type']=='checkbox' && $(this).is(':checked'))
      {
        value=data['value'];
        if (parent && parent.hasClass('serdelia-grid-activate')) parent.removeClass('serdelia-grid-inactive');
      }
      else if (data['type']=='checkbox')
      {
        value=data['value_not'];
        if (parent && parent.hasClass('serdelia-grid-activate')) parent.addClass('serdelia-grid-inactive');
      }
   
      var url=$('#serdelia_page').data('url-write');
      url=url.replace('%id%',data['record']);
      var reload=data['page_reload'];

      var post = { ['e_'+data['field']] : value,['update_payload_only']:true,
                      ['_srdcsrftkn']:$('#serdelia_page').data('_srdcsrftkn')
                  };
      
      $.post(
                  url,
                  post,
                  function(data) {
                     var data=JSON.parse(data);
                     if (data.result!=true) alert('ERROR::'+data.message);
                      else if (reload) location.reload();
                  }
               );



  },
  addLoaderIcon = function (event)
  {
    if ($(this.form).length==0 || $(this.form)[0].checkValidity())
    {
      $(this).addClass('btn-warning');
      $(this).find(':first-child').show();
      $(this).find(':first-child').addClass('glyphicon-refresh');
      $(this).find(':first-child').addClass('spinning');
    }
  },
  removeRecordConfirm = function( event  )
  {    
      $('#modalRemoveRecord').modal('hide');
      var url = $('#modalRemoveRecord').data('url');

      if (url=='-')
      {
        var name = $('#modalRemoveRecord').data('name');
        var val = $('#modalRemoveRecord').data('val');
        var input = $("<input>").attr("name",name).val(val);
        $( "#fileupload" ).append($(input));
        $( "#fileupload" ).submit();
      }
      else
      {

        var post = { ['_srdcsrftkn']:$('#serdelia_page').data('_srdcsrftkn') };
        $.post(
          url,
          post,
          function(data) {
            location.reload();
          }
        );
      }

        
  },
  removeRecord = function( event  )
  {
      $('#modalRemoveRecord').data('url',$(this).data('url'));
      $('#modalRemoveRecord').data('name',$(this).data('name'));
      $('#modalRemoveRecord').data('val',$(this).data('val'));
      $('#modalRemoveRecord').modal();
      event.stopPropagation();
  },

  gridItemMouseOver = function( event  )
  {
    $(this).find('.serdelia-grid-on-rollover').show();
  },
  gridItemMouseOut = function( event  )
  {
    $(this).find('.serdelia-grid-on-rollover').hide();
  },

  changeToggleHelp = function( e )
  {
    var top= $('.serdelia-page-toggle-help').eq(0);
    var info=$('.serdelia-page-toggle-help').eq(1);
    var page=info.data('page-help');

    var invisible=info.parent().hasClass('d-none');
    var d = new Date();
    d.setTime(d.getTime() + (365*24*60*60*1000));

    // close infobox 
    if ($(this).hasClass('alert-dismissible') || !invisible)
    {
      info.parent().addClass('d-none');
      var cookie=1;
    } else
    // click "i" and show infobox
    {
        info.parent().removeClass('d-none');
        var cookie=0;
    }

    name="serdelia_page_help_"+page;
    document.cookie=name+"="+cookie+";path=/;domain="+window.location.host+";expires="+d.toUTCString();

    

     
  }

  ;

  init();

  return {    }


}(jQuery, window);
