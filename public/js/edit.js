//const { setTimeout } = require("timers/promises");

var crp=null;
var Apps = Apps || {},
console = console || {log: function() { return true; } };
var ckInitialized=false;
var confirmUrl='';
var askSaveGoType='';
var page_update_field=null;
var page_update_required_field=null;
var serdeliaFormName='serdelia_edit_form';//'fileupload'
var CKEditors5 = [];

Apps.Edit = function($, window)
{
  var init = function()
  {

    // activating sort-tables
    try
    {
      $('.serdelia-sorttable').sorttable({
          placeholder: 'placeholder',
          helperCells: null
      }).disableSelection();
    }
    catch(err) { }

    var language = document.getElementsByTagName("html")[0].getAttribute("lang");


    $('.serdelia-edit-field').unbind('change').bind('change',changeField);
    $('.serdelia-edit-field').unbind('keyup').bind('keyup',changeField);
    $('.serdelia-edit-toggle-field').unbind('change').bind('change',changeToggleField);
    $('.serdelia-edit-show-on-demand').unbind('click').bind('click',showOnDemand);
    $('.serdelia-edit-cancel').unbind('click').bind('click',cancel);
    
    $('.serdelia-edit-clipboard').unbind('click').bind('click',clipboardCopy);

    $('.serdelia-edit-ask-save-go').unbind('click').bind('click',askSaveGo);
    $('.serdelia-edit-ask-save-go-confirm').unbind('click').bind('click',askSaveGoConfirm);
    $('.serdelia-edit-ask-save-go-cancel').unbind('click').bind('click',askSaveGoCancel);
    $('.serdelia-edit-ask-save-go-dismiss').unbind('click').bind('click',askSaveGoDismiss);
    $('.serdelia-edit-ask-save-go-required-cancel').unbind('click').bind('click',askSaveGoRequiredCancel);

    $('.serdelia-edit-elements-select').unbind('change').bind('change',elementsAdd);
    $('.serdelia-edit-elements-add--simple').unbind('click').bind('click',elementsSimpleShowAdd);

    //$('.serdelia-edit-elements-search-input').unbind('keypress').bind('keypress',elementsSearchEnter);
    //$('.serdelia-edit-elements-search-submit').unbind('click').bind('click',elementsSearch);
    $('.serdelia-edit-toggle-lang').unbind('click').bind('click',changeToggleLang);
    $('.serdelia-edit-toggle-help').unbind('click').bind('click',changeToggleHelp);

    $('.serdelia-edit-image-thumb-on-off').unbind('click').bind('click',imageOnOff);
    $('.serdelia-edit-image-editor').unbind('click').bind('click',imageEditorOn);
    
  
    $('.serdelia-edit-elements-double-select').unbind('change').bind('change',elementsDoubleChange);


    $('.serdelia-on-demand').unbind('change').bind('change',onDemand);
    
    
    $('.serdelia-edit-images-youtube').unbind('click').bind('click',imagesCustomAdd);
    $('.serdelia-edit-images-vimeo').unbind('click').bind('click',imagesCustomAdd);    
    $('.serdelia-edit-images-url').unbind('click').bind('click',imagesCustomAdd);

    //$('.serdelia-edit-images-custom').unbind('click').bind('click',imagesCustomAdd);
    //$('.serdelia-edit-images-url').unbind('click').bind('click',imagesUrlAdd);
    $('.serdelia-edit-imageUploadRemove').unbind('click').bind('click',imageUploadRemove);
    $('.serdelia-edit-elements-remove').unbind('click').bind('click',elementsRemove);
    $('.serdelia-edit-elementsDouble-remove').unbind('click').bind('click',elementsDoubleRemove);

    $('.serdelia-edit-pair-remove').unbind('click').bind('click',pairRemove);
    $('.serdelia-edit-pair-add').unbind('click').bind('click',pairAdd);
    $('.serdelia-edit-elements-pair-select').unbind('change').bind('change',pairSelect);
    $('.serdelia-edit-pair-tr').find('td').unbind('click').bind('click',pairEdit);


    $('.serdelia-edit-imageCrop').unbind('click').bind('click',imageCrop);
    $('.serdelia-edit-imageCrop-remove').unbind('click').bind('click',imageCropRemove);


    var $dates=$('.serdelia_edit_date');
    $dates.each(function() {
      $(this).datetimepicker({locale: language,format: $(this).data('date-format') });      
    });

    $('.serdelia_edit_date_clear').unbind('click').bind('click',dateClear);
    $('.serdelia_edit_date').on("dp.change", changeField);

    $('.serdelia_edit_datetime').datetimepicker({locale: language,format:'YYYY-MM-DD HH:mm'});
    $('.serdelia_edit_datetime').on("dp.change", changeField);


    $('.serdelia-submit').unbind('click').bind('click',submitForm);    

    $('#'+serdeliaFormName).on('submit', function(e)
        {
          e.preventDefault();
        });

    $('.serdelia_table_row_add').unbind('click').bind('click',tableRowAdd);


    $('.serdelia_table_col_add').unbind('click').bind('click',tableColAdd);
    $('.serdelia_table_col_remove').unbind('click').bind('click',tableColRemove);
    $('.serdelia-table-cell-remove').unbind('click').bind('click',tableRowRemove);

    $('.serdelia_table_cell_placeholder_parent').unbind('click').bind('click',tablePlaceholderClick);
    $('.serdelia_table_input').unbind('keydown').bind('keydown', tableInputTab);

    $('.serdelia_table_input').unbind('paste').bind('paste',tablePaste);
    
    $('.serdelia-source-searched').unbind('click').bind('click',sourceSearchedClick);
    $('.serdelia-edit-field-search').unbind('keypress').bind('keypress', sourceSearchKeypress);
    $('.serdelia-edit-field-search').unbind('keyup').bind('keyup', sourceSearchChange);
    $('.serdelia-edit-field-search').unbind('focus').bind('focus', sourceSearchFocus);
    
    $('.serdelia-lightbox-closer-js').unbind('click').bind('click',lightboxClose);


  },

  toggleSubmits=function (prop)
  {
    $('#serdelia-submit').prop('disabled', prop);
    $('#serdelia-submit-top').prop('disabled', prop);
    $('#serdelia-submit-edit').prop('disabled', prop);
  },

  checkRequired = function (focusFirst=false)
  {
    var pass=true;
    
    var $inputs = $('#'+serdeliaFormName+' :input');
    $inputs.each(function() {
      if ($(this).attr('required') || $(this).attr('pattern'))
      {   
        if (!$(this)[0].checkValidity() && $(this).not(':hidden'))
        {
          
          if (focusFirst)
          {            
            var id=$(this)[0].id;
            page_update_required_field=id;
            id=$('#'+id).parents('.tab-pane').attr('id');
            $('[aria-controls="'+id+'"]').tab("show");            
            focusFirst=false;
          }
          pass=false;
        }
      }
    });
    return pass;
  };

  submitForm=function (event,action=null,url_after=null)
  {
    
    var actionType=($(this).attr('value'));
    if (action) actionType=action;
    var abort=false;
    
    // removing required from hidden fields
    var $inputs = $('#'+serdeliaFormName+' :input');
    var values = {};    
    $inputs.each(function() {
      if ($(this).attr('required') || $(this).attr('pattern'))
      {   
        
        //if (!$(this).is(":visible")) $(this).attr('required',false);
        //if ($(this).is(":visible") && !$(this)[0].checkValidity())
        if (!$(this)[0].checkValidity() && $(this).not(':hidden'))
        {
          if (!abort)
          {
            var id=$(this)[0].id;
            var y=$('#'+id).offset().top;
            id=$('#'+id).parents('.tab-pane').attr('id');
            $('[aria-controls="'+id+'"]').tab("show");
            window.scrollTo(0,y-150);            
          }
          abort=true;
          
          
        }
      }
    });
    
    if (abort)  return;
    if (event) event.preventDefault();

    // add empty checkboxes
    var unchecked=$("#"+serdeliaFormName+" :input:checkbox:not(:checked)");
    unchecked.prop('checked', true);
    unchecked.attr('value', 'off');

    // update textareas TinyMCE
    $('textarea.tiny').each(function () {
      var $textarea = $(this);
      var id=$(this).attr('id');
      //tinymce.get(id).uploadImages().then(() => {        });      
      $textarea.val(tinymce.get(id).getContent());
      //console.log(tinymce.get(id).getContent());
    });

    // update copy from Quill
    $('.serdelia_copy_from').each(function ()
    {
          var $textarea = $(this);
          var id=$(this).data('id');        
          $textarea.val($("#"+id).find('.ql-editor').html());          
    });
    
    // add loader icon    
    var span=$(this).find('span').eq(0);//mdi-done-all');
    span.removeClass();
    span.addClass('spinner-border spinner-border-sm spinner-serdelia');
    Apps.Edit.disableSubmits();

    // performing AJAX
    var form=$("#"+serdeliaFormName);

    // update textarea CK
    $('textarea.ckeditor').each(function ()
    {
      var $textarea = $(this);
      var $value=CKEDITOR.instances[$textarea.attr('name')];
      if (!$value) $value=CKEDITOR.instances[$textarea.attr('id')];      
      if ($value) $value=$value.getData()
      $textarea.val($value);
    });

    // update textarea CK5
    
    $('textarea.ckeditor5').each(function ()
    {
      var $textarea = $(this);
      var $value=CKEditors5[$textarea.attr('name')];      
      if (!$value) $value=CKEDITOR.instances[$textarea.attr('id')];      
      if ($value) $value=$value.getData()
      $textarea.val($value);
    });

    var data=new FormData(document.getElementById(serdeliaFormName));

    // video positions for covers
    var videos=$('video');
    $.each(videos, function(i, item)
      {
        data.append($(this).attr('id'), $(item).get(0).currentTime);
      });

    var url_new=form.data('url-new');
    var lightbox=false;

    if ($(this).hasClass('serdelia-submit-lightbox'))
    {
      url_after='[lightbox_close]';
    }

    $.ajax({
      url: form.attr('action'),
      type: 'POST',
      //contentType: 'multipart/form-data',
      dataType: "JSON",
      data: data,
      processData: false,
      contentType: false,
      success: function(data, textStatus, jqXHR) {


      unchecked.prop('checked', false);
      unchecked.attr('value', 'on');


  
        if (data.result==true)
        {
          if (url_after=='[lightbox_close]') lightboxClose(null,true);
          else if (actionType==null && url_after) window.location.href=url_after;
          else if (actionType=='submit')
          {
            var url=form.data('url-back');
            if (url.indexOf('?')!=-1) url=url+'&'; else url=url+'?';
            url=url+'highlight='+data.id;
            window.location.href=url;
          }
          else if ((actionType=='submit-edit' || actionType=='edit') && url_new)
            {
              url_new=url_new.replace('%new%',data.id);
              window.location.href=url_new;
            }
          else if (actionType=='submit-edit' || actionType=='edit') window.location.reload(true);
        }
          else
          {
            $('#modalInfoLabel').html('Error');
            $('#modalInfoMessage').html(data.message);
            $('#modalInfo').modal();

          }
      },
      error: function(jqXHR, textStatus, errorThrown) {

      unchecked.prop('checked', false);
      unchecked.attr('value', 'on');


        var errorMessage = jqXHR.responseText;
        if (errorMessage.length > 0) {
            alert('ERROR'+errorMessage);
        }
      }
    });



  },
  cancel = function(event  )
  {
    /*
    $(':required').attr('required',false);
    $('[name="submit_type"]').val('cancel');
    var form=document.getElementById("fileupload");//.submit();
    form.submit();*/
    var form=$("#"+serdeliaFormName);
    window.location.href=form.data('url-back');

  },
  askSaveGo = function(event,edit=null,now=null)
  {    
    if (now==null) var now=$(this);

    if ($('#serdelia-submit').length && !$('#serdelia-submit').prop('disabled'))
    {
      if (checkRequired(true))
      {
        askSaveGoType=edit;
        confirmUrl=now.data('url');
        if (event) event.preventDefault();
        var url=now.data('url');
        if (!url) url=now.parent().attr('href'); // <a href="..."><button>
        $('#modalSaveGo').data('url',url);
        $('#modalSaveGo').data('name',now.data('name'));
        $('#modalSaveGo').data('val',now.value);
        $('#modalSaveGo').modal();
      } else
      {
        if (event) event.preventDefault();
        $('#modalSaveGoRequired').modal();
      }
    } else
    {
      var url=now.data('url');
      window.location.href=url;
    }
  },
  
  askSaveGoConfirm = function()
  {
    //'submit-edit'
    Apps.Edit.submitForm(null,askSaveGoType,$('#modalSaveGo').data('url'));
    
    //$('[name="url_parent"]').val(url);
    //if (askSaveGoType=='edit') $('[name="submit_type"]').val('edit');
      //else $('[name="submit_type"]').val('standard');
    //var form=document.getElementById("fileupload");
    //form.submit();
  },

  askSaveGoDismiss = function()
  {
    var form=$("#"+serdeliaFormName);
    var url=form.data('url-back');
    
    //if (url.indexOf('?')!=-1) url=url+'&'; else url=url+'?';
    //url=url+'highlight='+data.id;
    window.location.href=url;

  },

  askSaveGoCancel = function()
  {

    if (page_update_field)
    {
      // setting back initial value if cancelled
      var val=page_update_field.data('initial-value');
      page_update_field.val(val);
    }      
    page_update_field=null;
  },

  askSaveGoRequiredCancel = function()
  {

    if (page_update_field)
    {
      // setting back initial value if cancelled
      var val=page_update_field.data('initial-value');
      page_update_field.val(val);
    }      
    if (page_update_required_field)
    {
      $('#'+page_update_required_field).focus();
    }
    page_update_field=null;
    page_update_required_field=null;
  },  

  disableSubmits = function ()
  {
    $('#serdelia-submit').prop('disabled', true);
    $('#serdelia-submit-top').prop('disabled', true);
    $('#serdelia-submit-edit').prop('disabled', true);
    $('#serdelia-submit-edit-top').prop('disabled', true);    
  },

  changeField = function(  )
  {
    $('#serdelia-submit').prop('disabled', false);
    $('#serdelia-submit-top').prop('disabled', false);
    $('#serdelia-submit-edit').prop('disabled', false);
    $('#serdelia-submit-edit-top').prop('disabled', false);
    $('.serdelia-disable-on-change').prop('disabled', true);

    if ($(this).hasClass('serdelia-edit-hide-lock'))
    {
    }
    else {
      $('.hide-on-change').hide();
      $('.show-on-change').show();
    }

    var now=$(this).attr('name');
    if (now)
    {
      var then=$("body").find("[data-select-parent='" + now + "']");
      if (then.length>0)
      {        
          var value=$(this).val();
          if (then.data('large'))
          {
            var field=then.attr('name');
            //var url='?ajax=elements,'+field+','+value;
            var data={'action':'field_value','field':now,'value':value};

            $.ajax({
                url: '',
                global: false,
                type: "POST",
                data: data,
                dataType: "html",
                async: true,
                success: function(data)
                {
                  var html = $(data).find('[name="'+field+'"]').html();
                  $('[name="'+field+'"]').html(html);
                  Apps.Edit.selectParentClear(field);
                  //$('[name="'+field+'"]').eq(0).trigger("click");//attr('selected',true);
                }
            });
          
          } else
          {
            // hiding and unselecting all
            then.find('option').attr('hidden',true);
            then.find('option').attr('selected',false);
            
            // showing equal
            then.find("[data-parent='"+value+"']").attr('hidden',false);

            // if null present - showing and selecting null
            var nuller=then.find('option[value="0"]');
            if (nuller.length>0)
            {
              nuller.attr('hidden',false);
              nuller.attr('selected',true);
            }
            else
            // selecting the first
                then.find("[data-parent='"+value+"']").eq(0).attr('selected',true);


          }
      }
    }

  if ($(this).hasClass('serdelia_page_update'))
  {
    page_update_field=$(this);
    Apps.Edit.askSaveGo(null,'edit');
  }


  },


                    

  changeToggleLang = function(  )
  {
    var lang=$(this).data('lang');
    var d = new Date();
    d.setTime(d.getTime() + (365*24*60*60*1000));

    if ($(this).hasClass('btn-primary'))
    {
      $(this).addClass('btn-default')
      $(this).removeClass('btn-primary');
      $('[data-edit-lang='+lang+']').hide();
      document.cookie="serdelia_editlang_"+lang+"=0;path=/;domain="+window.location.host+";expires="+d.toUTCString();
    } else
    {
      $(this).removeClass('btn-default')
      $(this).addClass('btn-primary'); 
      $('[data-edit-lang='+lang+']').fadeTo( "fast" , 1);//show();
      document.cookie="serdelia_editlang_"+lang+"=1;path=/;domain="+window.location.host+";expires="+d.toUTCString();
    }
     
  },

  changeToggleHelp = function( e )
  {
    var banner=$(this);
    if ($(this).hasClass('close')) banner=$(this).parent();
    
    var invisible=banner.hasClass('d-none');
    var d = new Date();
    d.setTime(d.getTime() + (365*24*60*60*1000));

    // close infobox 
    if (banner.hasClass('alert-dismissible'))
    {
      var cl='serdelia_edit_help_'+banner.data('field');
      banner.parent().parent().find('.'+cl).addClass('d-none');
      banner.parent().parent().find('.btn.serdelia-edit-toggle-help').removeClass('d-none');
      var cookie=1;
    } else
    // click "?" and show infobox
    {
        banner.addClass('d-none');
        var cl='serdelia_edit_help_'+banner.data('field');
        banner.parent().parent().parent().find('.'+cl).removeClass('d-none');
        var cookie=0;
    }

    var name="serdelia_edit_help_"+$("#"+serdeliaFormName).data('page')+'_'+banner.data('field');
    var cookie=name+"="+cookie+";path=/;domain="+window.location.host+";expires="+d.toUTCString();
    
    document.cookie=cookie;

    

     
  },


  selectParentClear = function (field)
  {
    var then=$("body").find("[data-select-parent='" + field + "']");
    if (then.length>0)
    {
      //console.log('zero for child of '+field+ '->' +then.attr('name'));
      then.children().attr('selected',false);
      then.children().eq(0).attr("selected");
      Apps.Edit.selectParentClear(then.attr('name'));
    }
  },

   dateClear = function( event )
  {
    event.preventDefault();
    $(this).parent().parent().find('.serdelia-edit-field').val('');
  },
  showOnDemand = function(  )
  {
      //$('#'+fields_hide[i]+'_hidden_field').css('display',display_hide);
      $(this).css('display','none');
      var id=$(this).data('show');
      
      if ($.isArray(id))
        $.each( id, function( index, value ){
          $(value).show();
        });
      else $('#'+id).show();//css('display','block');
      
  },
  changeToggleField = function(  )
  {
    
    var data=$(this).data('toggle');
    var val=$(this).val();
    var found=null;
    Apps.Edit.changeField();
    
    $.each(data, function(key, item)
    {
      if (val==key) found=key;
      else
      {
        key=key+'';
        if (key.charAt(0)=='!' && key.substring(1)!=val)  found=key;
      }
    });
    
    if (found===null) return;
    data=data[found];


    if (data.hide)
      for (var i=0;i<data.hide.length;i++)
        $('[data-edit-field="e_'+data.hide[i]+'"]').css('display','none');
    if (data.show)
      for (var i=0;i<data.show.length;i++)
        $('[data-edit-field="e_'+data.show[i]+'"]').css('display','flex');

    

    
  },
  initQuill = function (editor)
  {
    
      for(var k=0; k<quill.length; k++)
      {
        quill[k].on('text-change', function(delta, oldDelta, source) {
        Apps.Edit.changeField();
      })    ;
     }
  },
  initCK = function (editor)
  {
    if (!ckInitialized)
    {
      ckInitialized=true;
      editor.on( 'change', function(e)
      {
        Apps.Edit.changeField();
      });
    }
  },
  imageOnOff = function (editor)
  {
    var field=$(this).data('field');
    field=$('[name="'+field+'"]');
    if (field.val()==1) $(this).css('color','#ccc');
     else $(this).css('color','#000');
    field.val(1-field.val());
  },
  imageEditorOn = function (editor)
  {
    var id=$(this).attr('id');
    $(this).hide();
    $(this).next().hide();
    $('#'+id+'_area').show();
    
  },

  //------------------------------------------------------------------------------------------------------------------------
  imageCropRemove = function (editor)
  {
    var id=''+$(this).data('field');
    var input=$(this).data('input');
    input=$('#'+input);
    input.val('');
    //crp.cropper('clear');
    //crp.cropper('disable');
    crp.cropper('destroy');
    crp=null;
    $(this).hide();
    var show=$(this).data('button_show');
    $('#'+show).show();
    $('#'+show).data('wasInit',false);
  },


  imageCrop = function (editor)
  {
    
    var id=''+$(this).data('field');
    var ratio=$(this).data('ratio');
    var button=$(this);
    var input=$(this).data('input');
    input=$('#'+input);
    
    var field=$('#'+id+'_crop');
    
    var hidden=$(this).data('');

    if (!field);
    else
    if (field.is(":visible") && 1==2)
    {
    } else
    {

      if (field.data('cors'))
      {
        source=field.data('cors');
        $('#'+id+'_crop').data('cors',null);
        button.parent().find('.spinner-border').show();
        button.hide();
        
          $.ajax({
            url: $('body').data('base-path')+'/api/cors_copy',
            global: false,
            type: "POST",
            dataType: "html",
            data: {'source':source,'destination':field.data('temp')},
            async: false,
            success: function(data, textStatus, jqXHR)
            {
              console.log('Image Copied to: '+field.data('temp'));
              button.show();
              button.parent().find('.spinner-border').hide();
              field.find('img').attr('src',$('body').data('base-path')+'_config-temp/'+field.data('temp'));
            }
          });

        return;
          
      } else
      {
        
      }

      field.show();

      if (!$(this).data('wasInit'))
      {
        $('.serdelia-edit-imageCrop').data('wasInit',false);
        $(this).data('wasInit',true);
        
        var data=$(this).data('crop_data');
        if (!data) data=input.val();

        if (data)
        {
          var data0=data.split(',');
          data={ "x":1*data0[0],"y":1*data0[1],"width":1*data0[2],"height":1*data0[3]};
          //console.log('Crop INIT');
        } else data=null;
        
        
        var image=$('#'+id+'_crop img');
        $('#'+id+'_crop').data('input',$(this).data('input'));
        
        if (crp)
        {          
          crp.cropper('setAspectRatio',ratio);
          if (data) crp.cropper('setData', data);
        }
        // CREATE CROPPER
        else
        {
          console.log('[create cropper]');
          console.log(data);          
        
          crp=image.cropper(
          {
            aspectRatio: ratio,
            zoomable:false,          
            background:false,
            data: data,
            dragend: function(e)
            {
              var img=$(this).cropper("getImageData");
              var input=$(this).parent().data('input');
              input=$('#'+input);
              data=$(this).cropper("getData");
              var data=data.x.toFixed(0)+','+data.y.toFixed(0)+','+data.width.toFixed(0)+','+data.height.toFixed(0);
              input.val(data);

              try
              {
                Apps.Edit.changeField();
              }
              catch(err) { }
            }

          });

          if (data)
              {
                var data=data.x+','+data.y+','+data.width+','+data.height;
                input.val(data);
              }


          }
        
      } else
        {
          
          //crp.cropper('enable');
          //crp.cropper('reset',true);
          //crp.cropper('setData',{x1:0, y1:0, width: 480, height: 270});
          //$('#'+id+'_crop_data').val($('#'+id+'_crop_data').data('last'));
        }
    }

    var hider=$(this).data('button_hide');
    if (hider)
    {
      $('#'+hider).show();
      $(this).hide();
    }

  },
  //------------------------------------------------------------------------------------------------------------------------
  // creating temporary select
  pairEdit = function (editor)
  {    
    if (!$(this).data('edited'))
    {
      var input=$(this).parent().parent().parent().parent().data('input');
      Apps.Edit.pairRemoveInputs(input);
      var html='<select class="form-control serdelia-edit-pair-select-edit">';
      var nr=$(this).index()+1;
      var options=$('#'+input+'_select_'+nr).html();
      $(this).data('edited',true);
      html=html+options;
      html=html+'</select>';
      $(this).html(html);
      var now=$(this).parent().data('id').split(',')[nr-1]; //="1:2033,2:0003"><td>Alber Michael
      $(this).find('option[value="'+now+'"]').attr('selected',true);
      $('.serdelia-edit-pair-select-edit').unbind('change').bind('change',pairSelectEdit);

    }
  
  },

  //------------------------------------------------------------------------------------------------------------------------
  // finish temporary edit on any select change
  pairSelectEdit = function (editor,now)
  {
    if (!now) var now=$(this);

    var selected=$(this).children("option:selected");
    if (selected.data('new-page')) 
    {
      Apps.Edit.askSaveGo(null,'edit',selected);
    }

    now.parent().parent().parent().find('.serdelia-edit-pair-tr').find('td').data('edited',false);

    var input=now.parent().parent().parent().parent().parent();

    var html=now.find("option:selected").text();
    var value=now.find("option:selected").attr('value');
    var vv=now.parent().parent().data('id').split(',');
    var nr=now.parent().index();
    vv[nr]=value;
    now.parent().parent().data('id',vv.join(','));
    now.parent().html(html);


    //now.data('edited',false);
    //now.parent().data('edited',false);
    //now.parent().parent().data('edited',false);

    Apps.Edit.pairSelectUpdateInput(input.data('input'));


  },
  
  // update input field based on TR values
  pairSelectUpdateInput = function (input)
  {
    var table=$('#'+input+'_pair').find('.serdelia-edit-pair-tr');

    var values=[];
    for (var i=0;i<table.length;i++)
    {
      values.push(table.eq(i).data('id'));
    }

    values=values.join(';');
    Apps.Edit.changeField();
    $('[name="'+input+'"]').attr('value',values);

  }, 
  // remove all temporary inputs
  pairRemoveInputs = function (input)
  {
    edited=$('#'+input+'_pair').find('select');
    for (i=0;i<edited.length;i++)
    {
      Apps.Edit.pairSelectEdit(null,edited.eq(i));
    }
    
  },
  // selecting item from standard section
  pairSelect = function (editor)
  {
    var selected=$(this).children("option:selected");
    if (selected.data('new-page')) 
    {
      Apps.Edit.askSaveGo(null,'edit',selected);
    }
    
     //
    // checking if all elements are set
    var id=$(this).parent().data('id');
    var count=$(this).parent().data('sections-count');
    var all=false;
    for (var i=0;i<count;i++)
    {
        var s=$('#'+id+'_select_'+(i+1));
        if (s.val()==0) all=true;
    }

    var add=$('#'+id+'_add');
    add.attr('disabled',all);

    // auto add
    if (!all)
    {
      var add=$('#'+id+'_add');
      Apps.Edit.pairAdd(null,add)
      for (var i=0;i<count;i++)
      {
        var s=$('#'+id+'_select_'+(i+1));
        s.val(0);        
      }
    }
    

  },
  //------------------------------------------------------------------------------------------------------------------------
  // adding item in elements_pair element
  pairAdd = function (editor,now=null)
  {
    // creating value
    if (!now) var now=$(this);

    var id=now.data('id');
    var count=now.data('sections-count');
    var val=[];
    var tds='';
    for (var i=0;i<count;i++)
    {
        var s=$('#'+id+'_select_'+(i+1));
        val.push(s.val());

        tds=tds+'<td>'+s.find(':selected').text()+'</td>';
    }

    // adding value to input

    val=val.join(',');
    input=$('#'+id);
    input.val(input.val()+';'+val);

    // adding table ROW to DOM
    html='<tr class="serdelia-edit-pair-tr" data-id="'+val+'">'+tds;
    html=html+'<td><span type="button" class="mdi mdi-clear serdelia-edit-pair-remove" style="float:right"></span></div></td></tr>';
    $('#'+id+'_pair').append(html);
    Apps.Edit.changeField();
    $('.serdelia-edit-pair-remove').unbind('click').bind('click',pairRemove);
    $('.serdelia-edit-pair-tr').find('td').unbind('click').bind('click',pairEdit);
  },
  //------------------------------------------------------------------------------------------------------------------------
  // removing item in elements_pair element
  pairRemove = function (editor)
  {
    var id=''+$(this).parent().parent().parent().parent().parent().data('input');
    input=$('#'+id);

    // finding row    
    var index=$(this).parent().parent().index();

    // removing row from input
    value=input.val().split(';');
    delete value[index];
    value=value.join(';');
    value=value.replace(';;',';');
    if (value.charAt(0)==';') value = value.substring(1);
    if (value.charAt(value.length-1)==';') value = value.substring(0, value.length - 1);
    input.val(value);
    
    // removing item from DOM
    $(this).parent().parent().remove();
    Apps.Edit.changeField();


  },
  //------------------------------------------------------------------------------------------------------------------------

  imageUploadRemove = function (editor)
  {

    var id=''+$(this).data('id');
    var field=$(this).parent().parent().parent().attr('id');
    console.log('FIELD='+field);

    // should be fixed as we cannot use fields with "files" and "_order"
    field=field.replace('_order','');
    
    if (field=='files_files') field='files_order';
    else if (field!='e_files') field=field.replace('files','order');
    
    var field_select=$('#'+field+'_select');
    if (field_select)
      field_select.find('option[value="'+id+'"]').attr('disabled',false);
      
    field=$('#'+field);

    var val=field.val().split(',');
    
    var index = val.indexOf(id);
    if(index!=-1) val.splice(index, 1);
    val=val.join(',');
    field.val(val);
    $(this).parent().parent().remove();
    Apps.Edit.changeField();
  },

  //------------------------------------------------------------------------------------------------------------------------
  elementsDoubleRemove = function (editor)
  {
    var id=''+$(this).data('id');
    var field=$(this).parent().parent().attr('id');
    field=field.replace('_order','');
    
    field=$('#'+field);

    var val=field.val().split(',');
    
    var index = val.indexOf(id);
    if(index!=-1) val.splice(index, 1);
    val=val.join(',');
    field.val(val);

    

    $(this).parent().remove();
    Apps.Edit.changeField();
    
  },

  elementsRemove = function (editor)
  {

    var id=''+$(this).data('id');
    
    var field='';  
    var parent=$(this).parent();
    if ($(this).hasClass('serdelia-edit-elements-remove--simple')) 
    {
      var parent=$(this).parent().parent();
      field=$(this).parent().parent().parent().attr('id');
    }
    else if ($(this).hasClass('serdelia-edit-elements-remove--card')) 
    {      
      var parent=$(this).parent().parent().parent().parent();
      field=$(this).parent().parent().parent().parent().parent().attr('id');
    }
    else
    {
      var field=$(this).parent().parent().attr('id');
    }


    // should be fixed as we cannot use fields with "files" and "_order"
    field=field.replace('_order','');
    
    if (field=='files_files') field='files_order';
    else if (field!='e_files') field=field.replace('files','order');
    
    var field_select=$('#'+field+'_select');
    if (field_select)
      field_select.find('option[value="'+id+'"]').attr('disabled',false);
      
      field_new=$('#'+field+'_new');
      field=$('#'+field);


    // removing val from input
    var val=field.val().split(',');    
    var index = val.indexOf(id);
    if(index!=-1) val.splice(index, 1);
    val=val.join(',');
    field.val(val);

    // remove val from elements new 
    if (field_new)
    {
      try { var d=JSON.parse(field_new.val()); }
      catch(e) { d={}; }
      delete d[id];
      field_new.val(JSON.stringify(d));
    }


    // removeing element
    parent.remove();
    Apps.Edit.changeField();
  },

  imagesUrlAdd = function () {
    // IDENTITY
    var id = 'new'+parseInt(Math.random()*100000);
    var field = $(this).data('elements');
    var captionsArr=$('#'+field+'_filename').data('captions');  
    var type=0;
    var i=0;
    for (var prop in captionsArr)
    {
      i++;
      if (prop=='url') type=i;
    }

    // ORDER
    var order = $('#' + field + '_order');
    order.val(order.val() + ',' + id);

    // CAPTIONS DOM

    var captions = $('#'+field+'_filename').data('captions').url;
    var captionsDom = '<input type="hidden" name="e_'+field+'_'+id+'_caption_type" value="'+type+'">';

    for (var index in captions) {
      var caption = captions[index];

      captionsDom += '<input \
                      placeholder="' + caption.label + '"\
                      class="form-control serdelia-edit-field serdelia-edit-imageUploadCaptionLinear"\
                      name="e_'+field+'_'+id+'_caption_'+ (parseInt(index)+1) +'"\
                    />';
    }

    // ELMENT DOM
    var domElement = '<div\
        style="z-index: 99;margin:5px"\
        data-id="' + id +'"\
        class="serdelia-edit-imageUploadThumb serdelia-edit-imageUploadThumbLinear">\
          <img \
          width="45" \
          height="45" \
          src="/serdelia/public/img/edit_images_url.png" />\
          ' + captionsDom + '\
        </div>';


    // ADD TO DOM
    $('#'+field+"_files").append(domElement);
    $('#'+field+'_filename').val($('#'+field+'_filename').val()+';'+id);

    Apps.Edit.changeField();
  },

  imagesCustomAdd = function ( )
  {
    var type=$(this).data('type');
    var field=$(this).parent().data('field');
    var html=$('#'+field+'_placeholders_'+type).html();
    var media=$('#'+field+'_files');
    
    var myid='new'+parseInt(Math.random()*100000);
    var order=$('#'+field+'_order');
    order.val(order.val()+','+myid);
    
    
    for (var i=0;i<30;i++)
      html=html.replace('%id%',myid);
    html=html.replace('temp-ckeditor','ckeditor')    ;    
    media.append(html);

    // let's refresh ck-editor
    media.find('textarea.ckeditor').each(function () {
      if (!CKEDITOR.instances[$(this).attr('id')])
      {
        CKEDITOR.replace($(this).attr('id'));  
      }
    });
    

    $('#'+field+'_filename').val($('#'+field+'_filename').val()+';'+myid);
    Apps.Edit.changeField();

  },



  tableRowAdd = function( event,id=null,click=true )
  {
    if (!id) var id=$(this).data('table');
    t=$('#'+id);
    var placeholders=t.data('placeholders');
    var cols= t.data('cols');
    var rows = $('#'+id+' tr.tr_data').length;

    var row=rows+1-1; // as there is a fake one, too

    var row_id=id+'_'+row;

    if (1==2)
    {
      var html='<tr class="tr_data serdelia-table-tr" id="tr_'+row_id+'" data-id="'+row_id+'">';
      if (t.data('counter')) html=html+'<td class="serdelia-table-cell serdelia-table-cell-nr" style="cursor:move"><span class="badge badge-secondary">'+(rows+1)+'</span></td>';
      var p='';
      for (col=1;col<=cols;col++)
      {
        //var name=id+'_'+row+'_'+col;
        html=html+'<td class="serdelia-table-cell serdelia_table_cell_placeholder_parent" data-id="'+id+'_'+row+'_'+col+'">';
        //html=html+'<span class="serdelia_table_cell_placeholder" name="'+name+'"></span>';
        if (placeholders && placeholders[col-1]) p=placeholders[col-1];
          else p='';
        
        html=html+'<textarea class="serdelia-edit-field serdelia_table_input new" name="'+id+'[]['+row+']['+col+']" placeholder="'+p+'"></textarea></td>';
        //html=html+'<input class="serdelia-edit-field serdelia_table_input new" name="'+name+'" placeholder="'+p+'" value=""></td>';      
      }
    } else
    {
      var fake_tr_id='tr_'+id+'__row_index_';
      var html=$('#'+fake_tr_id).get(0).outerHTML;
      
      html=html.replaceAll('display: none;','');
      html=html.replaceAll('_row_index0_',row-1);
      html=html.replaceAll('_row_index_plus_1_',row);
      html=html.replaceAll('_row_index_',row);
      html=html.replaceAll('ckeditor_temp','ckeditor');
      console.log(html);            

    }

    //html=html+'<td width="20px" class="serdelia-table-cell serdelia-table-cell-remove" style="cursor:pointer"><span class="mdi mdi-delete-forever"></span></td>';
    html=html+'</tr>';

    $('#'+id+' > tbody:last-child').append(html);
    $('.serdelia_table_cell_placeholder_parent').unbind('click').bind('click',tablePlaceholderClick);
    $('.serdelia-table-cell-remove').unbind('click').bind('click',tableRowRemove);    

    //console.log(CKEDITOR.instances);
    t.find('textarea.ckeditor').each(function () {
      if (!CKEDITOR.instances[$(this).attr('id')])
      {
        CKEDITOR.replace($(this).attr('id'));  
      }
    });

    
    if (click)
    setTimeout(function ()
  {
        var first=$('#tr_'+row_id);//
        var first=first.find('.serdelia_table_cell_placeholder_parent').eq(0);
        first.click();
        $('.serdelia_table_input').unbind('paste').bind('paste',tablePaste);
    }, 100);
    

/*
    $('body').on('click','.new',function ()
    {
      $(this).focus();
    } );*/
    
    changeField();


  }
  tableRowRemoveLast = function( event )
  {
    var id=$(this).data('table');
    t=$('#'+id);
    t.find("tr:last").remove();

    changeField();
  }
  tableRowRemove = function( event )
  {

    var table=$(this).parent().parent().parent();
    var id=table.attr('id');

   $(this).parent().remove();

    var rows = table.find('.tr_data');

      $.each(rows, function(i, item)
      {
        $(item).find('.serdelia-table-cell-nr>span').html(i+1);
        cols = $(item).find('input.serdelia-edit-field');
        $.each(cols, function(iCol, col)
        {
            var value=id+'_'+(i+1)+'_'+(iCol+1);
            $(col).attr("name",value);
        });
      });

    changeField();
  }
  
  tableColAdd = function( event )
  {
    var id=$(this).data('table');
    t=$('#'+id);
    var rows = $('#'+id+' tr.tr_data').length;    
    var cols= t.find("tr:last td").length;   // last because first may be header
    var colWidth=Math.floor(100/(+cols))+'%';
    var col=cols;

    // adding header in last column
    var thead=t.find('thead').first();
    thead.find('th').eq(cols-1).after('<th width="'+colWidth+'" style="background:#ccc;cursor:move;font-size:8px;padding-top:13px;text-align:center">'+String.fromCharCode(64+cols)+'</th>');

    t.find('th:gt(0)').attr('width',colWidth);

    // adding column in each row
    for (row=1;row<=rows;row++)
    {
      var name=id+'_'+row+'_'+col;
      var html='<td width="'+colWidth+'"><input class="serdelia-edit-field" style="background-color: transparent; border:none;width:100%;font-size:10px" name="'+name+'" value=""></td>';
      var tr=t.find('tr.tr_data').eq(row-1);
      tr.find('td').eq(cols-1).after(html);
      tr.find('td:gt(0)').attr('width',colWidth);
      
    }


    changeField();
  }
  // col remove actually :)
  tableColRemove = function( event )
  {
    var id=$(this).data('table');
    t=$('#'+id);
    var rows = $('#'+id+' tr').length;
    var cols= t.find("tr:first th").length-1;
    var col=cols+1;

    var colWidth=Math.floor(100/(cols-1))+'%';

    for (row=1;row<=rows;row++)
    {
      var tr=t.find('tr').eq(row-1);
      tr.find('td:last').remove();
      tr.find('td:gt(0)').attr('width',colWidth);
    }

    var thead=t.find('thead').first();
    thead.find('th:last').remove();

    changeField();
  }



  tablePlaceholderClick= function( event )
  {
    $('.serdelia_table_cell_placeholder_parent').removeClass('serdelia_table_cell_placeholder_parent_selected');
    $(this).addClass('serdelia_table_cell_placeholder_parent_selected');
    var t=$(this).find('.serdelia_table_cell_placeholder');
    t.hide();
    t.next().attr('type','text');
    t.next().focus();
  }

  tablePaste = function( e )
  {
    
    let fullpaste = paste = (event.clipboardData || window.clipboardData).getData('text');

    paste=paste.split("\n");
    var table=$(this).parent().parent().parent().parent()

    var settings=table.data('paste');
    

    // check if it's comma separated thing
    if  (settings.indexOf('new_line_comma')!='-1' && paste.length==1)
    {
      paste=fullpaste.split(", ");
      if (paste.length<2) paste=fullpaste.split("\n");
    }

    
    var row=this.parentElement.parentElement;
    var rowNow=row.rowIndex;
    var col=this.parentElement;
    var colNow=col.cellIndex;

    paste = paste.filter((a) => a); // remove empty
    {
      var id=$(this).parent().parent().parent().parent().attr('id');
      var slicers=[':','-','–','	'];
      var smax=0;
      var slicer='';
      var slicerIndex=-1;
      for (i=0;i<slicers.length;i++)
      {
        var ii=0;
        for (s=0;s<paste.length;s++)
          if (paste[s].indexOf(slicers[i])!=-1) ii++;
        if (ii>smax) { slicer=slicers[i]; slicerIndex=i; smax=ii; }
      }
      
      if (paste.length>1)
      {

        var rowCount=table.find('tr').length-1;
        var rowCountFinal=rowNow+paste.length-1;
        
        if (rowCountFinal>rowCount)
        for (var i=0;i<(rowCountFinal-rowCount);i++)
        {          
          Apps.Edit.tableRowAdd(null,id,false);
        }

        for (var i=0;i<paste.length;i++)
        {
          if (slicer)
          {
            var s=paste[i].split(slicer);
            for (var col=0;col<s.length;col++)
              {
                $('[data-id='+id+'_'+(i+rowNow)+'_'+(col+colNow+1)+']').find('textarea').val(s[col].trim());
              }
          } else $('[data-id='+id+'_'+(i+1)+'_'+(colNow+1)+']').find('textarea').val(paste[i]);
        
        }
      } else return;
      e.preventDefault();
    }

    
  }

  tableInputTab = function( e )
  {
        if (e.keyCode === 9)
        {
          $('.serdelia_table_cell_placeholder_parent').removeClass('serdelia_table_cell_placeholder_parent_selected');
          $(this).prev().html($(this).val());
          $(this).attr('type','hidden');
          $(this).prev().show();
          var next=$(this).parent().parent().parent().find('[data-order='+($(this).data('order')+1)+']');
          if (next)
          {
            next.attr('type','text');
            next.prev().hide();
            next.parent().addClass('serdelia_table_cell_placeholder_parent_selected');

          }
          
          //$(this).next().focus();
        }
  }

  elementsSimpleShowAdd = function(  e)
  {
    var field=$(this).data('field');
    $('#'+field).show();
    $(this).hide();
  }

  elementsAdd = function(  e)
  {
    $(this).parent().find('.serdelia-edit-images').show();
    var field=$(this).attr('id');

    field=field.substr(0,field.length-7);

    var val=$('#'+field).val();
    var newval=$(this).val();
    if (newval==0) return;
    var newobject=$(this).children(':selected');
    var newtext=$(this).children(':selected').text();
    if (val) val=val.split(','); else val=[];

    if ($(this).data('multiple') ||   val.indexOf(newval)=='-1')
    {
      if (!$(this).data('multiple')) newobject.attr('disabled',true);
      val.push(newval);
      val=val.join(',');
      $('#'+field).val(val);

      
      filename=$(this).children(':selected').data('img');
      sublabel=$(this).children(':selected').data('sublabel');

      var layout=$('#'+field+'_order').data('layout');
      if (!layout && filename) layout='image'; else layout='card';

      if (layout=='simple')
      var element='<div class="serdelia-edit-elements--simple">%label%<span data-id="{{item.fields.id}}" class="mdi mdi-clear serdelia-edit-elements-remove serdelia-edit-elements-remove--simple"></span></div>';
      else if (layout=='card') var element='<div class="card"><div class="card-body"><p class="card-title" style="margin-bottom:0.25rem">%label%<span data-id="{{item.fields.id}}" class="mdi mdi-clear serdelia-edit-elements-remove serdelia-edit-elements-remove--card"></span></p></div></div>';
      else var element='<div style="display:inline-block;padding-right:5px;width:200px">%image%<p style="font-size:12px;line-height:18px;margin-top:5px">%sublabel% %label%</p></div>';
      
      element=element.replace('%label%',newtext);
      
      
      if (!sublabel) sublabel='';
      
      if (filename) filename='<div style="width:200px;height:116px"><img style="object-fit:cover;width:200px;height:116px" src="'+filename+'"></div>'; else
       filename='<img width="200" height="116" style="background-color:white">';
      
      element=element.replace('%image%',filename);

      if (sublabel) sublabel='<span class="badge badge-secondary">'+sublabel+'</span>';
      element=element.replace('%sublabel%',sublabel);

      var remove='';
      if (layout=='card')
      {
        element=element.replace('{{item.fields.id}}',newval);        
      } else
      {
        remove='<span type="button" data-id="'+newval+'" class="mdi mdi-clear serdelia-edit-elements-remove"></span>';
      }

      element='<div data-id="'+newval+'" class="serdelia-edit-imageUploadThumb">'+element+remove+'</div>'; 

      
      $('#'+field+'_order').append(element);      

      $('.serdelia-edit-elements-remove').unbind('click').bind('click',elementsRemove);
      changeField();
      if ($(this).data('multiple')) $('#'+field+"_select").val(0);
      $(this).children().eq(0).attr('selected',true);
    }
  },


/*
  elementsSearch = function(  )
  {
    var field=$(this).attr('id');
    field=field.substr(2,field.length-16);
    var query=$('#e_'+field+'_search').val();
    if (!query) return;

    $('#e_'+field+'_search_ajax').show();

    var data=
          {
          type:'field',
          field:field,
          params:
                    {
                      input:{action:'search',query:query},
                      output:{action:'search'}
                    }
          };
   data={ serdelia_output:encodeURIComponent(JSON.stringify(data))};

    $.ajax({
              url: $(this).data('url'),
              global: false,
              type: "GET",
              dataType: "html",
              async: true,
              data: data,
              success: function(data)
              {               
                if (!data) data='No results';
                  $('#e_'+field+'_search_ajax').hide();
                  $("#e_"+field+"_search_results").html('<br>'+data);
                  $('.serdelia-edit-elements-search-add').unbind('change').bind('click',elementsSearchAdd);
              }
          });
  },
  elementsSearchAdd = function(  )
  {
    var field=$(this).parent().attr('id');
    field=field.substr(0,field.length-15);
    var newval=$(this).data('id')*1+'';

    var val=$('#'+field).val();
    val=val.split(',');
    if (val.indexOf(newval)=='-1')
    {
      val.push(newval);
      val=val.join(',');
      $('#'+field+'_order').show();
      $('#'+field).val(val);
      var item=$(this).first().html();//.'<div style="display:inline-block;padding-right:5px;width:100px;"><img width="100" height="100" src="/public/upload/works/thumb/52f2bdd2ba14e.jpg?53c91346951f0"><br><small>Bujnowski, <i>Louis Auguste Cyparis</i></small></div';
      var html='<div data-id="'+newval+'" class="serdelia-edit-imageUploadThumb">'+item+'<span typex="button" data-id="'+newval+'" class="mdi mdi-clear serdelia-edit-imageUploadRemove"></span></div>';
      $('#'+field+'_order').append(html);      
      $('.serdelia-edit-imageUploadRemove').unbind('click').bind('click',imageUploadRemove);
      changeField();      
    }
  },
  elementsSearchEnter = function( event )
  {
    if ( event.which == 13 )
    {
      event.preventDefault();
      var field=$(this).attr('id');
      field='e_'+field.substr(2,field.length-9)+'_search_submit';
      $('#'+field).trigger("click");
    }
  },*/
  elementsDoubleChange = function(  )
  {
    //var field=$(this).attr('id');
    //field=field.substr(0,field.length-14);
    //var ajax=$(this).data('ajax')+$(this).val();
    //$("#"+field+"_select").hide();
    
    //var query=$(this).val();
    var field_id=$(this).data('field');
    
    var data={'action':'elements_double','field':field_id,'value':$(this).val() };

    $.ajax({
      url: '',
      global: false,
      type: "POST",
      dataType: "html",
      data: data,
      async: true,
      success: function(data, textStatus, jqXHR) {
        var id='e_'+field_id+'_select';
        var select=$('#'+id);
        select.css('display','block');
        data=JSON.parse(data);
        var html='';
        for (i=0;i<data.length;i++)
        html=html+'<option value="'+data[i].value+'" data-sublabel="'+data[i].sublabel+'" data-img="'+data[i].image+'">'+data[i].label+'</option>';
        select.find('option').not(':first').remove();
        select.append(html);
        
        var val=$('#e_'+field_id).val().split(',');
        for (var i=0;i<val.length;i++)
          select.find('option[value="'+val[i]+'"]').attr('disabled',true);

              }
          });



  },

  sourceSearchedClick=function(e,val)
  {    
    if (!val)
    {
      var field=($(this).data('field'));
      var value=$(this).data('value');    
    } else
    {
      var field=val.field;
      var value=val.value;
      var new_label=val.label;
    }
    
    
    var $now=$('#'+field).parent().parent().find('[data-value='+value+']');
    
    
    var type=$('#'+field).data('type');
    if (!type) type='select';
    
    // select
    if (type=='select')
    {
      $('#'+field).val(value);
      $('#'+field+'_text').val($(this).find('span').html());
      $(this).parent().html('');
      changeField();
    }

    // elements
    if (type=='elements')
    { 
      
      var val=$('#'+field).val();
      val=val.split(',');
      value=''+value;
      
      if (val.indexOf(value)=='-1')
      {        
        val.push(value);
        val=val.join(',');
        $('#'+field+'_order').show();
        $('#'+field).val(val);

        if (new_label)
        {
          var label=new_label;
          try { var d=JSON.parse($('#'+field+'_new').val()); }
          catch(e) { d={}; }
          d[value]=new_label;
          $('#'+field+'_new').val(JSON.stringify(d));
        }
          else var label=$now.first().html();
        
        var element='<div data-id="'+value+'" class="serdelia-edit-elements--simple">'+label+'<span class="mdi mdi-clear serdelia-edit-elements-remove serdelia-edit-elements-remove--simple" data-id="'+value+'"></span></div>';
        html='<div data-id="'+value+'" class="serdelia-edit-imageUploadThumb ui-sortable-handle">'+element+'</div>';
        /*
        {
          var element='<div style="display:inline-block;padding-right:5px;width:100px;">%image%<small>%label%</small></div>';      
          element=element.replace('%label%',label);
          filename=$(this).children(':selected').data('img');
          if (filename) filename='<div style="width:100px;height:100px"><img style="max-width:100px;max-height:100px" src="'+filename+'"></div>'; else
          filename='<img width="100" height="24" style="background-color:white">';
          element=element.replace('%image%',filename);
          var html='<div data-id="'+value+'" class="serdelia-edit-imageUploadThumb">'+element+'<span type="button" data-id="'+value+'" class="mdi mdi-clear serdelia-edit-imageUploadRemove"></span></div>';
        }
        */
        $('#'+field+'_order').append(html);      
        $('.serdelia-edit-elements-remove').unbind('click').bind('click',elementsRemove);
        $('.serdelia-edit-imageUploadRemove').unbind('click').bind('click',imageUploadRemove);
        $(this).parent().html('');
        changeField();
      }
   }

  },

  sourceSearchKeypress=function (e)
  {
    if (e. keyCode === 13 || e. which === 13)
      {
        e. preventDefault(); 
        if ($(this).data('add'))
        {
          var field=($(this).data('field'));
          var input=$(this).val().trim();
          var found=false;
          var $result=$(this).parent().parent().find('.serdelia-source-searched');
          // if exists in searched - it's fine
          $result.each(function() {
            if ($(this).find('span').html()==input)
            {
              sourceSearchedClick(null,{'field':field,'value':$(this).data('value')});
              found=true;
            }
          });
          // if not found - let's add a new one
          if (!found)
          {
            var $result=$(this).parent().parent().find('.serdelia-edit-elements--simple');
            $result.each(function() {
              if ($(this).text()==input) found=true;
            });

            if (!found)
            {
              var id=$(this).attr('new_id');
              if (!id) id=1; else id=id*1+1;
              $(this).attr('new_id',id);
              sourceSearchedClick(null,{'field':field,'value':'new_'+id,'label':input});
            }
          }
        }
        return false;
      }
  },

  sourceSearchChange=function (e)
  {
    var query=$(this).val();
    var field_id=$(this).data('field');
    var data={'action':'search_source','field':field_id,'value':query};

  $.ajax({
      url: '',
      global: false,
      type: "POST",
      dataType: "html",
      data: data,
      async: true,
      success: function(data, textStatus, jqXHR) {

        data=JSON.parse(data);
        var div=$('ul[data-searched="'+field_id+'"');
        var html='';
        
        for (var i=0;i<data.items.length;i++)
        {
          html=html+'<li class="list-group-item list-group-item-secondary serdelia-source-searched" data-value="'+data.items[i].id+'" data-field="'+field_id+'" class="serdelia-source-searched">';
          if (data.items[i].image && data.items[i].image.thumb) html=html+'<img height=50 style="padding-right:10px;float:left" src="'+data.items[i].image.thumb+'">';
          html=html+'<span>'+data.items[i].label+'</span></li>';
        }
        
        div.html(html);
        $('.serdelia-source-searched').unbind('click').bind('click',sourceSearchedClick);
        
      },
      error: function(jqXHR, textStatus, errorThrown) {
            alert('editjs::sourceSearchChange::SYSTEM ERROR');
        }
      
    });

    
    
  },

  sourceSearchFocus=function ()
  {
    
  },

  lightboxClose=function (e,refresh=false)
  {
    var data = { type: 'close',refresh:refresh };
    var event = new CustomEvent('uho_serdelia_parent_event', { detail: data })
    window.parent.document.dispatchEvent(event)          
  },




  clipboardCopy=function (e)
  {

    var source=$(this).data('source');
    if (source) source=$('#'+source).html();
    if (!source)
    {
      alert('Input field is empty, nothing to copy...');
      return;
    }

    function isOS() {
      return navigator.userAgent.match(/ipad|iphone/i);
    }

      var text=source;
      textArea = document.createElement('textArea');
      textArea.value = text;
      document.body.appendChild(textArea);

      var range,
      selection;

  if (isOS()) {
      range = document.createRange();
      range.selectNodeContents(textArea);
      selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      textArea.setSelectionRange(0, 999999);
  } else {
      textArea.select();
  }

  document.execCommand('copy');
  document.body.removeChild(textArea);

    alert('URL copied');

  },



















  onDemand = function(  )
  {
    var data=$(this).data('field');
    $('#'+data).prop('readonly',!$('#'+data).prop('readonly'));
  };

  init();

  return { lightboxClose:lightboxClose, checkRequired:checkRequired, changeField: changeField,disableSubmits:disableSubmits, sourceSearchedClick:sourceSearchedClick, pairAdd:pairAdd, pairSelectEdit:pairSelectEdit, pairSelectUpdateInput:pairSelectUpdateInput, pairRemoveInputs:pairRemoveInputs, askSaveGo:askSaveGo, submitForm:submitForm, askSaveGoConfirm:askSaveGoConfirm, selectParentClear:selectParentClear, initQuill:initQuill,initCK:initCK, tableRowAdd:tableRowAdd };

}(jQuery, window);

if ($('#fileupload').data('changed'))    Apps.Edit.changeField();

// adding hashes to TABS

$(function(){
  var hash = window.location.hash;
  
  hash && $('ul.nav a[href="' + hash + '"]').tab('show');

  $('.nav-tabs a').click(function (e) {
    $(this).tab('show');
    var scrollmem = $('body').scrollTop();
    window.location.hash = this.hash;
    $('#hidden_tab').val(this.hash);
    $('html,body').scrollTop(scrollmem);
  });
});