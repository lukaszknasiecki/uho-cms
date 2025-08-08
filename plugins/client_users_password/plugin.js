
$('#generate').unbind('change').bind('change',generateClick);
$('#show').unbind('change').bind('change',showClick);
$('#copy_button').unbind('click').bind('click',copy);

function generateClick()
{
    if ($(this).is(":checked")) var val='disabled'; else var val=false;    
    $('#password').attr('disabled',val);
    $('#password2').attr('disabled',val);
}

function showClick()
{
    if ($(this).is(":checked")) var type='text'; else var type='password';
    $('#password').attr('type',type);
    $('#password2').attr('type',type);
}

function copy()
{
    let input = document.getElementById("copy");
    input.select();
    document.execCommand("copy");
    alert($(this).data('message'));
}