	var progress=0;
	var stepprogress=0;
	var count=$('.progress').data('count');
	var post=$('.progress').data('post');

	function setProgress(progress,step,steps)
	{
		if (progress>0) progress=(progress-1)+step/steps;
		$('.progress-bar').css('width',parseInt(progress/count*100)+'%');
		if (progress==count)
		{
			$('.progress-bar').removeClass('bg-warning');
			$('.progress-bar').removeClass('progress-bar-animated');
			$('.progress-bar').removeClass('progress-bar-striped');
			$('.progress-bar').addClass('bg-success');
			$('.progress-bar').css('color','white');
			$('.progress-bar').html($('.progress').data('complete'));
			$('#button_plugin_back').show();
		} else
		$('.progress-bar').html(progress+' / '+count);
	}

	function runAjax()
	{
		var data=post;
		var update_fields=$('.progress').data('fields');

		// plugins

		var plugins=$('.progress').data('plugins');

		if (plugins) var plugins_length=plugins.length; else var plugins_length=0;
		if (update_fields) var update_fields_length=update_fields.length; else var update_fields_length=0;

		var insteps=plugins_length+update_fields_length;

		for (i=0;i<plugins_length;i++)
		if (i==stepprogress)
		{
			stepprogress++;
			var url=plugins[i].replace('%id%',$('.progress').data('ids')[progress]);
			url=decodeURI(url);
			url=url.replace('{{id}}',$('.progress').data('ids')[progress]);
			url=url.replace('%7B%7Bid','%7B%7B'+$('.progress').data('ids')[progress]);
			
			//console.log(url);
			
			$.ajax({
				type: "POST",
				url: url,
				data: data,
				dataType: 'JSON',
				success: function(result)
				{
					setProgress(progress,stepprogress,insteps);
					if (result.message)
					{
						$('#errors').append('<li class="list-group-item"><code>'+result.message+'</code></li>');
					}
					if (progress<count) runAjax();						
					}			
			  });
			return;
		}
			

		// write
		if ($('.progress').data('ajax'))
		{
			var url=$('.progress').data('ajax')+$('.progress').data('ids')[progress];
		} else var url='';

		var data={update_payload_only:true,update_fields:update_fields};
		progress++;		
		stepprogress=0;
//		console.log(data);
//		return;


		if (update_fields_length>0)
		$.ajax({
			type: "POST",
			url: url,
			data: data,
			dataType: 'JSON',
			success: function(result)
			{
				setProgress(progress-1,1,1);
				if (result.message)
					$('#errors').append('<li class="list-group-item">'+result.message+'</li>');
				if (progress<count) runAjax();	
					else setProgress(progress,1,1);					
			}			
		  });
		  else
		  {
			setProgress(progress,1,1);
			if (progress<count) runAjax();
		  }

	}

	setProgress(1,1,1);
	if (count>0) runAjax();
