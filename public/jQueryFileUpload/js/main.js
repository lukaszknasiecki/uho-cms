jQueryUploadStyle='single';
jQueryUploadLang=$('html').attr('lang');
jQueryUploadTrans =  {
                'processing': {'pl':'Wczytuję...','en':'Processing...'},
                'upload-error': {'pl':'Błąd podczas pobierania obrazka.','en':'File upload failed.'}
        };


// ------------------------ single upload ------------------------------------------------------------

var Apps = Apps || {},
console = console || {log: function() { return true; } };

Apps.jQueryFileUpload = function($, window)
{
    'use strict';
    var url = $('body').data('base-path')+'/api/uploader',
    isDropping=false,
    uploadButton = $('<button/>').addClass('btn btn-primary').prop('disabled', true).text(jQueryUploadTrans['processing'][jQueryUploadLang]);

    //------------------------------------------------------------------
    // single File Upload

    //=======================================================================================
    // dropzone ON OFF tweaks

    $('.serdelia-drag-upload').on('dragstart dragenter dragover', function (event)
    {
        $(this).css('border-color','#000');        
        dropZoneObject=$(this);
        event.stopPropagation();
        event.preventDefault();
        event.originalEvent.dataTransfer.effectAllowed= 'copy';
        event.originalEvent.dataTransfer.dropEffect= 'copy';
        if (dropZoneTimer) clearTimeout(dropZoneTimer);
    });

    var dropZoneTimer=null;
    var dropZoneHideDelay=50;
    var dropZoneObject=null;

    $('.serdelia-drag-upload').on('drop dragleave dragend', function (event)
    {
        if (dropZoneTimer) clearTimeout(dropZoneTimer);
        dropZoneTimer= setTimeout(
            function(){
                dropZoneObject.css('border-color','#ccc');
        }, dropZoneHideDelay); 

        
    });

    // removing default dragover icon
    $(document).bind('drop dragover', function (e) { e.preventDefault(); });

    //=======================================================================================
    //=======================================================================================
    // SINGLE FILE  UPLOAD
    //=======================================================================================
    //=======================================================================================

    $('.basic_fileupload').fileupload(
    {
        url: url,
        pasteZone: null,
        dataType: 'json',
        done: function (e, data)
        {
            
            $.each(data.result.files, function (index, file)
            {
                $('<p/>').text(file.name).appendTo('#files');
            });
        },
        progressall: function (e, data)
        {
            var progress = parseInt(data.loaded / data.total * 100, 10);
            var id=$(this).attr('id');
            $('#'+id+'_progress').show();
            $('#'+id+'_progress .progress-bar').css(
                'width',
                progress + '%'
            );
        }
    })
    // removing default drag icon all over the screen
    .on('fileuploaddragover', function (e,data) { e.preventDefault();  })
    // checking if dropped on this field
    .on('fileuploaddrop', function (e,data)
        {
            isDropping=true;
            if (!dropZoneObject || $(this).attr('id')!=dropZoneObject.data('field')) e.preventDefault();  
         })
    // proceding upload only in allowed box box
    .on('fileuploadsubmit', function (e,data) { if (isDropping && !dropZoneObject) e.preventDefault(); isDropping=false;  })
    // after upload is done
    .on('fileuploaddone', function (e, data)
    {
        isDropping=false;
        var id=$(this).attr('id');
        $('#'+id+'_progress .progress-bar').css('width','0');
        $('#'+id+'_progress').hide();
        
        
        $.each(data.result.files, function (index, file)
        {
            var extensions=$("#"+id).data('extensions');
            if (extensions) extensions=extensions.split(',');
            
            if (file.error)
            {
                $('#'+id+'_filename_shown').append('<br><span class="text-danger"/>'+file.error+'</span>');
                
            }
            else if (file.url)
            {
                Apps.Edit.changeField();
                var parts = file.name.split('.');
                var ext='';
                if (parts.length > 1) ext = parts.pop().toLowerCase();

                if (!extensions || extensions.indexOf(ext)>=0)
                {                   
                    $('#'+id+'_filename').val(file.name);
                    $('#'+id+'_filename_upload').val(1);
                    $('#'+id+'_filename_shown').html('<span class="glyphicon glyphicon-file"></span> '+file.name);//'<a href="'+file.url+'" target="_blank">'+file.name+'</a>'); HTTP ACCESS disabled there
                } else
                {
                    //var error = $('<span class="text-danger"/>').text(file.error);
                    if (document.documentElement.lang=='pl') $('#'+id+'_filename_shown').html('<span class="text-danger">Rozszerzenie pliku niedozwolone</span>');
                        else $('#'+id+'_filename_shown').html('<span class="text-danger">extension not supported</span>');
                }
            }
        });
       
    }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');

    //=======================================================================================
    //=======================================================================================
    // SINGLE IMAGE UPLOAD
    //=======================================================================================
    //=======================================================================================

    $('.fileupload').fileupload(
    {
        url: url,
        dataType: 'json',
        pasteZone: null,
        autoUpload: true,
        acceptFileTypes: /(\.|\/)(gif|jpe?g|webp|png)$/i,
        maxFileSize: 6000000000, // 6000 MB
        disableImageResize: /Android(?!.*Chrome)|Opera/.test(window.navigator.userAgent),
        previewMaxWidth: 200,
        previewMaxHeight: 100,
        previewCrop: false
    })
    .on('fileuploadadd', function (e, data)
    {        
        var extensions=$(this).data('extensions');
        //acceptFileTypes=/(\.|\/)(gif|jpe?g|svg|png)$/i;
        var id=$(this).attr('id');
        // uploading thumbnail start
        $('#'+id+'_files').html('');
        data.context = $('<div/>').appendTo('#'+id+'_files');
        $.each(data.files, function (index, file)
        {
            var node = $('<p/>').append($('<span/>').text(file.name));
            if (!index) { node.append('<br>').append(uploadButton.clone(true).data(data));
            }
            node.appendTo(data.context);
        });
    })
    // checking if dropped on this field
    .on('fileuploaddrop', function (e,data)
        {
            if (!dropZoneObject || $(this).attr('id')!=dropZoneObject.data('field')) e.preventDefault();  
         })
    // removing default drag icon all over the screen
    .on('fileuploaddragover', function (e,data) { e.preventDefault();  })
    // after upload is done
    .on('fileuploadprocessalways', function (e, data)
    {
        var index = data.index, file = data.files[index], node = $(data.context.children()[index]);
        if (file.preview) node.prepend('<br>').prepend(file.preview);
        if (file.error) node.append('<br>').append($('<span class="text-danger"/>').text(file.error));
        
        if (index + 1 === data.files.length)
        {
            if (jQueryUploadStyle=='standard') data.context.find('button').text('Upload').prop('disabled', !!data.files.error);
            if (jQueryUploadStyle=='single') data.context.find('button').hide();
        } 
        
    })
    // progress bar
    .on('fileuploadprogressall', function (e, data)
    {
        var progress = parseInt(data.loaded / data.total * 100, 10);
        var id=$(this).attr('id');
        $('#'+id+'_progress').show();
        $('#'+id+'_progress .progress-bar').css('width',progress + '%');
    })
    // file uploaded
    .on('fileuploaddone', function (e, data)
    {
        
        var id=$(this).attr('id');
        $('#'+'e_'+id+'_crop_button').hide();
        $('#'+id+'_progress .progress-bar').css('width','0');
        $('#'+id+'_progress').hide();
        Apps.Edit.changeField();
        
        $.each(data.result.files, function (index, file)
        {
            if (file.url)
            {
                $('#'+id+'_filename').val(file.name);
                //var link = $('<a>').attr('target', '_blank').prop('href', file.url);
                //$(data.context.children()[index]).wrap(link);
            } else if (file.error)
            {
                var error = $('<span class="text-danger"/>').text(file.error);
                $(data.context.children()[index]).append('<br>').append(error);
            }
        });
    })
    // file uploaded with error
    .on('fileuploadfail', function (e, data)
      {
        $.each(data.files, function (index, file)
        {
            var error = $('<span class="text-danger"/>').text(jQueryUploadTrans['upload-error'][jQueryUploadLang]);
            $(data.context.children()[index])
                .append('<br>')
                .append(error);
        });
      }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');


    //=======================================================================================
    //=======================================================================================
    // MULTIPLE IMAGES UPLOAD
    //=======================================================================================
    //=======================================================================================

    $('.filemultiupload').fileupload (
    {
        url: url,
        dataType: 'json',
        pasteZone: null,
        autoUpload: true,
        acceptFileTypes: /(\.|\/)(gif|jpe?g|webp|png)$/i,
        maxFileSize: 6000000000, // 6000 MB
        disableImageResize: /Android(?!.*Chrome)|Opera/.test(window.navigator.userAgent),
        previewMaxWidth: 100,
        previewMaxHeight: 100,
        previewCrop: false
    })
    // checking if dropped on this field
    .on('fileuploaddrop', function (e,data)
        {
            if (!dropZoneObject || $(this).attr('id')!=dropZoneObject.data('field')) e.preventDefault();  
         })
    // removing default drag icon all over the screen
    .on('fileuploaddragover', function (e,data) { e.preventDefault();  })
    // add files
    .on('fileuploadadd', function (e, data)
    {
        var id=$(this).attr('id');
        
        //$(this).data('caption_type',1);
        data.context = $('<div/>').appendTo('#'+id+'_files');
        $.each(data.files, function (index, file)
        {
            var node = $('<p/>').append($('<span/>').text(file.name));
            if (!index) { node.append('<br>').append(uploadButton.clone(true).data(data));
            }
            node.appendTo(data.context);
        });
    })
    // after upload
    .on('fileuploadprocessalways', function (e, data)
    {
        console.log('fileuploadprocessalways#1');
        var index = data.index, file = data.files[index], node = $(data.context.children()[index]);

        if (file.error) console.log('error');
        
        if (file.preview && !file.error)
            node.prepend('<br>').prepend(file.preview);
        else if (!file.error)
        {
            var id=$(this).attr('id');
            var ext=file.name.split('.').pop();
            var thumb='<div style="z-index:98" data-id="" class="serdelia-edit-imageUploadThumb serdelia-edit-imageUploadThumb-wide"><img src="'+$('body').data('serdelia-path')+'/public/img/edit_images_file_'+ext+'.png"><strong class="serdelia-edit-imageUploadThumb-wide-info">'+file.name+'</strong></div>';
            node.prepend('<br>').prepend(thumb);
        }
        if (file.error) node.append('<br>').append($('<span class="text-danger"/>').text(file.error));
        
        console.log(index+' vs '+data.files.length);

        if (index + 1 === data.files.length)
        {
            data.context.find('button').hide();
        } 
        
    })
    // progress bar
    .on('fileuploadprogressall', function (e, data)
    {
        console.log('fileuploadprogressall#1');
        var progress = parseInt(data.loaded / data.total * 100, 10);
        var id=$(this).attr('id');
        $('#'+id+'_progress').show();
        $('#'+id+'_progress .progress-bar').css('width',progress + '%');
    })
    // file uploaded
    .on('fileuploaddone', function (e, data)
    {
        console.log('fileuploaddone');
        
        var id=$(this).attr('id');
        //var captionType=$(this).data('caption');
        $('#'+id+'_progress .progress-bar').css('width','0');
        $('#'+id+'_progress').hide();
        Apps.Edit.changeField();


        $.each(data.result.files, function (index, file)
        {
            
            if (file.url)
            {
                var now=$(data.context.children()[index]);
                
                //now.find('span').hide();
                //now.find('br').hide();                
                //now.parent().addClass("serdelia-edit-imageUploadThumb serdelia-edit-imageUploadThumbLinear");
                //var captionsArr=$('#'+id+'_filename').data('captions');
                //var watermark=$('#'+id+'_filename').data('watermark');
                //captionsArr=captionsArr['image']; // image captions
                //var first_padding='style="margin-top:-5px"';
                
                var myid='new'+parseInt(Math.random()*100000);
                var order=$('#'+id+'_order');
                order.val(order.val()+','+myid);

                $('#'+id+'_filename').val($('#'+id+'_filename').val()+';'+myid);
                var media_type=data.fileInput.data('type');
                var html=$('#'+id+'_placeholders_'+media_type).html();
                var ext=file.type.split('/');
                ext=ext.pop().toUpperCase();
                html=html.replace('%extension%',ext);
                //html=html.replace('%filename%',file.name);
                
                html=html+'<input type="hidden" name="e_'+id+'_filename_'+myid+'" value="'+file.name+'">';
                html=html.split('%id%').join(myid);
                html=html.split('%image%').join(file.url);                
                html=html.replace('temp-ckeditor','ckeditor')    ;                


                // add real one
                now.parent().parent().append(html);
                // removing last element auto-created by JqueryFileUpload

                // update ckeditor
                now.parent().parent().find('textarea.ckeditor').each(function () {
                    if (!CKEDITOR.instances[$(this).attr('id')])
                    {
                        CKEDITOR.replace($(this).attr('id'));        
                    }
                  });
              
                  now.parent().remove(); 

                /*
                var captions='<input type="hidden" name="e_'+id+'_filename_'+myid+'" value="'+file.name+'">';

                now.parent().data('id',myid);
                
                if (captionsArr)
                for (var i=1;i<=captionsArr.length;i++)
                if (captionsArr[i-1])
                {
                    if (i>1) first_padding='';
                    if (captionsArr[i-1]['rows'])
                    captions=captions+'<textarea rows="'+captionsArr[i-1]['rows']+'" placeholder="'+captionsArr[i-1]['label']+'" class="form-control serdelia-edit-field serdelia-edit-imageUploadCaptionLinear" '+first_padding+'name="e_'+id+'_'+myid+'_caption_'+i+'"></textarea>';
                    else if (captionsArr[i-1]['select'])
                    {
                        var select=captionsArr[i-1]['select'];
                        select=select.replace('%myid%',myid);
                        captions=captions+select;
                    }
                    else captions=captions+'<input placeholder="'+captionsArr[i-1]['label']+'" class="form-control serdelia-edit-field serdelia-edit-imageUploadCaptionLinear" '+first_padding+'name="e_'+id+'_'+myid+'_caption_'+i+'">';
                }
        
                if (watermark) captions=captions+'<div class="form-check"><br><input type="checkbox" class="form-check-input serdelia-edit-field" name="e_'+id+'_'+myid+'_caption_watermark"> <label>Bez watermarku</label></div>';

                now.append(captions);
                */

            } else if (file.error)
            {
                var error = $('<span class="text-danger"/>').text(file.error);
                $(data.context.children()[index]).append('<br>').append(error);
            }
        });
/*
        var order='';
        var elements=$('#'+id+'_files').children();
        for (var i=0;i<elements.length;i++)
        {
            var now=elements.eq(i);
            if (order!='') order=order+',';
            
            var idd=now.data('id');
            order=order+idd;

        }

        var orderField=$('#'+id+'_order');
        orderField.val(order);
*/
    })
    // file uploaded with error
    .on('fileuploadfail', function (e, data)
      {
        $.each(data.files, function (index, file)
        {
            var error = $('<span class="text-danger"/>').text(jQueryUploadTrans['upload-error'][jQueryUploadLang]);
            $(data.context.children()[index])
                .append('<br>')
                .append(error);
        });
      }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');


    $('.filemultiupload_video').fileupload (
    {
        acceptFileTypes: /(\.|\/)(mp4)$/i
    });

    $('.filemultiupload_audio').fileupload (
    {
        acceptFileTypes: /(\.|\/)(mp3)$/i
    });

    $('.filemultiupload_m4a').fileupload (
    {
        acceptFileTypes: /(\.|\/)(m4a)$/i
    });

    var filetypes="pdf|doc|zip";

    $( ".filemultiupload_file" ).each(function( index )
    {
         $(this).fileupload
         (
            {                    
                acceptFileTypes: new RegExp("\\.("+$(this).data('extensions')+")$","i")
            }
            );
    });

    $( ".fileupload" ).each(function( index )
    {
        if ($(this).data('extensions'))
        $(this).fileupload
         (
            {                    
                acceptFileTypes: new RegExp("\\.("+$(this).data('extensions')+")$","i")
            }
            );
    });



}(jQuery, window);
