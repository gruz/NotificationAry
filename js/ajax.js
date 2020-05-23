jQuery( document ).ready(function( $ ) {

	if (Joomla.optionsStorage === null)
	{
		var Options = Joomla.getOptions('notificationary');

		Joomla.optionsStorage = {'notificationary':Options};
	}

	if (typeof(Joomla.optionsStorage.notificationary.ajax_place) === 'undefined')
	{
		return;
	}

	var resultElement = $('#'+Joomla.optionsStorage.notificationary.ajax_place);

	if (resultElement.length == 0)
	{
		return;
	}

	var favicon = $('link[rel="shortcut icon"]');
	var favicon_orig_attr = favicon.attr('href');
	var url = Joomla.optionsStorage.notificationary.ajax_url;
	var i = 0;
	var error_code = '##error##';
	//~ var max_iterations = 100;

	var num = -1;

	var favicons = [];

	for (c = 0; c < 4; c++) {
		favicons[c] = '/plugins/system/notificationary/images/favicon' + (c+1) + '.ico';
	}

	$("#continue").click(function() {
		sendAjax(url);
	});

	$("#clear").click(function() {
		resultElement.empty();
		sendAjax(url+'&restart=1');
		i = 0;
	});

	var stopflag = false;
	$( '#'+Joomla.optionsStorage.notificationary.ajax_place+"_close" ).click(function() {
		stopflag = true;
		resultElement.parent().delay(1000).hide('slow');
	});

	var sendAjax = function(url)
	{
		if ( stopflag )
		{
			return;
		}

		i++;

		if ($("#continue").length)
		{
			$("#continue").hide();
		}

		resultElement.css('background-image','url("/plugins/system/notificationary/images/ajax.gif")');
		resultElement.css('background-position','center center');
		resultElement.css('background-repeat','no-repeat');
		//favicon.attr('href','/plugins/system/notificationary/images/favicon.ico');
		//~ favicon.attr('href',favicons[3]);
		var interval = setInterval(function(){
			num++;
			if (num >= 4)
			{
				num = -1;
			}
			else
			{
				favicon.attr('href',favicons[num]);
			}

		},250);


		$.get(url, function(response)
		{
			if (response.finished)
			{
				var msg = Joomla.optionsStorage.notificationary.messages.sent+response.message;
				resultElement.append( msg  );
				var tmp_str = msg;

				if (tmp_str.length && $('#system-message-container').length )
				{
					$('#system-message-container').append('<div class="alert alert-success"><p class="alert-message">'+ msg +'</p></div>');
				}

				resultElement.parent().delay(5000).hide('slow');
			}
			else
			{
				//~ if (i>max_iterations) {
					//~ resultelement.append( response + 'infinite cycle');
					//~ return;
				//~ }
				resultElement.append( response.message );

				if ($("#continue").length)
				{
					$("#continue").show();
				}
			}

			resultElement.scrollTop(resultElement.prop("scrollHeight"));
			resultElement.css('background-image','none');
			clearInterval(interval); // stop the interval

			if (response.finished)
			{
				favicon.attr('href',favicon_orig_attr);
				//if ($("#continue").length) {	$("#continue").show(); }
				return true;
			}

			// resend the AJAX request by calling the sendAjax function again
			if (Joomla.optionsStorage.notificationary.debug !== true)
			{
				sendAjax(url);
			}
		},
		'json'
		);
	};

	if (typeof Joomla.optionsStorage.notificationary.start_delay === 'undefined' || Joomla.optionsStorage.notificationary.start_delay === null)
	{
		sendAjax(url);
	}
	else
	{
		var interval = setInterval(function()
		{
			 //var timer = resultElement.html();
			 var seconds = Joomla.optionsStorage.notificationary.start_delay;
			 seconds -= 1;
			 Joomla.optionsStorage.notificationary.start_delay -=1;

			 if (seconds < 0 )
			 {
				  seconds = 59;
			 }
			 else if (seconds < 10 && length.seconds != 2)
			 {
				 seconds = '0' + seconds;
			 }

			 resultElement.html(Joomla.JText._('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_START', 'Time to start')+': '+seconds);

			 if (seconds == 0) {
				  resultElement.html('');
				  clearInterval(interval);
				 sendAjax(url);
			 }
		}, 1000);
	}
});
