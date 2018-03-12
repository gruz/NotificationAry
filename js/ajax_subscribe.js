jQuery(document).ready(function($) {

	if (Joomla.optionsStorage === null)
	{
		var Options = Joomla.getOptions('notificationary');

		Joomla.optionsStorage = {'notificationary':Options};
	}

	if (typeof(Joomla.optionsStorage.notificationary.task) === 'undefined' || Joomla.optionsStorage.notificationary.task !== 'subscription')
	{
		return;
	}

	var options = Joomla.optionsStorage.notificationary;
	var url = options.ajax_url;

	var selector_form = '.nasubscribe.form .categories input[type="checkbox"]';

	var $elements = $(selector_form);

	$elements.each(function()
	{
		var $element = $(this);

		var timer;
		$element.change(function(index, element){
			punch(index, this, timer);
		});
	});

	var selector_form = '.nasubscribe.form select';

	var $elements = $(selector_form);

	$elements.each(function()
	{
		var $element = $(this);

		$element.change(function(index, element){
			var timer;
			punch(index, this);
		});
	});

	function punch(index, element, timer)
	{
		var $element = $(element);
		var $form = $element.closest('.nasubscribe.form');

		var select = $element.prop("tagName") == 'SELECT';

		if (select)
		{
			var $label = $form.find('label[for="' + element.id + '"]');

			var checked = true;
		}
		else
		{
			var $label = $element.closest('label');
			var checked = element.checked;
		}

		var $result = $label.find('.result');

		if (!$result.length)
		{
			$label.append('&nbsp;<small class="result"><span class="label"></span> <i class="icon-loop spinning"></i></small>');
			$result = $label.find('.result');
		}

		var $result_label = $result.find('.label');
		var $result_icon = $result.find('i');

		$result_label.html(Joomla.JText.strings.PLG_SYSTEM_NOTIFICATIONARY_LOADING);

		var cursor = $label.css('cursor');
		$label.css('cursor', 'not-allowed');
		$result_icon.attr('class', 'icon-loop spinning');
		$result_label.removeClass('label-success label-warning label-info');

		// Abort running AJAX
		// ~ var xhr = $this.data('xhr');

		// ~ if (xhr)
		// ~ {
			// ~ $this.removeClass('loading');

			// ~ xhr.abort();
		// ~ }

		// Here I need to serialize the form before disabling the element,
		// otherwise it's not posted together with the rest of the form
		$element.attr('disabled', false);

		var serialized;

		var $checkboxes = $form.find('.categories input[type="checkbox"]');

		if (select)
		{
			var $categories = $form.find('.categories');

			$checkboxes.attr('disabled', false);

			serialized = $form.find('input, select').serialize();

			if ($element.val() != 'selected')
			{
				$checkboxes.attr('disabled', true);

				// This is discussable if to hide/show the list of categories if subscribed to all/none
				// as if a user visits the page at the first time one is mainly subscribed to all categories
				// and doesn't see the list. So one can click none without seen the categories list at all.
				// $categories.hide('slow');
			}
			else
			{
				$checkboxes.attr('disabled', false);
				// $categories.show('slow');
			}
		}
		else
		{
			$checkboxes.attr('disabled', false);
			serialized = $form.find('input, select').serialize();
			$checkboxes.attr('disabled', true);
		}

		$element.attr('disabled', true);


		// Do not fire immediately
		if (timer)
		{
			clearTimeout(timer);
		}

		xhr = $.post(url,
			serialized,
			function (data)
			{
				$element.attr('disabled', false);

				if (!select)
				{
					$checkboxes.attr('disabled', false);
				}

				$result_label.html(data.message);
				$label.css('cursor', cursor);
				$result_icon.attr('class', 'icon-publish');
				if (data.success)
				{
					if (checked)
					{
						$result_label.addClass('label-success');
					}
					else
					{
						$result_label.addClass('label-info');
					}
				}
				else
				{
					$result_label.addClass('label-warning');
				}


				timer = setTimeout(function(){

				$result.fadeOut('slow', function() {
						$(this).remove();
					});
				},3000);
			},
			'json'
		);
		// ~ $this.data('xhr', xhr);

	}

});
