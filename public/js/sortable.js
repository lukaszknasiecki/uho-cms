var Apps = Apps || {},
console = console || {log: function() { return true; } };

Apps.Sort = function($, window)
{

  var init = function() {
    if($("[data-sortable]").length) {
      var list = $("[data-sortable]");      
      var onsort_function = $("[data-onsort]").data('onsort');
      if (onsort_function)
      {        
        list.sortable({ stop: onCustomSort });
      }
        else list.sortable({ helper:'clone', stop: onSort });

      //list.sortable('option', 'cancel', '.quill_editor');
      //list.disableSelection(); // blocks inputfield on firefox!
      
    }
  },
  timeOut = null,
  onSort = function( li, ui )
  {
      clearTimeout(timeOut);
      //alert('what?');
      timeOut = setTimeout(saveOrder($(this).attr('id')), 400);
  },
  onCustomSort = function( li, ui )
  {
    var onsort_function = $("[data-onsort]").data('onsort');
    window[onsort_function].call();
  },
  saveOrder = function(id)
  {

    var  list = $("#"+id);

    // let's refresh ck-editor
    list.find('textarea.ckeditor').each(function ()
    {
      CKEDITOR.instances[$(this).attr('id')].destroy();
      CKEDITOR.replace($(this).attr('id'));      
    });

      var separator = list.data("separator");
      var sortable_table = list.data("sortable-table");

      if (!separator) separator="tr";
      items = list.children().filter(separator),
      
      order = [];

      $.each(items, function(i, item)
      {
          order.push($(item).data("id"));

          // ----- edit - table change -------------------------
          if (sortable_table)
          {
            // label
            var first=$(item).find('td').first();
            var first_span=first.find('span');
            if (first_span) first_span.html(i+1);
              else first.html(i+1);
            
            // setting proper names for cols and rows, changing input names
            cols = $(item).find('input.serdelia-edit-field');
            $.each(cols, function(iCol, col)
            {
              // change column label
              if (i==0) 
              { 
                list.parent().find('thead').first().find('th').eq(iCol+1).html(String.fromCharCode(65+iCol));//'<th style="background:#ccc;cursor:move;font-size:8px;padding-top:13px;text-align:center">'+String.fromCharCode(64+col)+'</th>');
              }
              var value=id+(i+1)+'_'+(iCol+1);
              $(col).attr("name",value);
              $(col).next().attr("name",value+'_style');
            });

            Apps.Edit.changeField();
          }  else
          {
            var first=$(item).find('td').first();
            var first_span=first.find('span');
            if (first_span.hasClass('sortable-number')) first_span.html(i+1);

          }



      });

      var joiner=$("#"+id).data('join');
      if (!joiner) joiner=',';

      order = order.join(joiner);

      var data = {
          "order": order,
          "field": list.data("field"),
          "_srdcsrftkn": $('#serdelia_page').data('_srdcsrftkn')
      };

     // set local input field

      if (list.data("input"))
      {
        $('#'+list.data("input")).val(order);
        Apps.Edit.changeField();
      }

      

     // run ajax
     
     if (list.data("path"))
     $.ajax({
              url: list.data("path"),
              global: false,
              type: "POST",
              dataType: "json",
              async: true,
              data: data,
              success: function(data)
              {
                  if (list.data("on_sort")) window[list.data("on_sort")]();
                  //console.log("success");
              }
          });

      else if (list.data("on_sort")) window[list.data("on_sort")]();


  };


  init();

  return { saveOrder: saveOrder };

}(jQuery, window);