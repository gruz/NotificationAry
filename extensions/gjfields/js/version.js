jQuery(document).ready(function($) { // We use the version field to store which tab is active
	setTimeout(function() {
		var a = $('#style-form ul.nav.nav-tabs li, div.panel>h3.title'); // isis and hathor templates
		$(a).on('click', function() {
			var el = $(this);
			if (!el.hasClass('active') && el.find('a')[0].hasAttribute('data-toggle')) { // If we click on a tab which is not active, but is going to be active
				var href =  el.find('a').attr('href');
				$('input[name="jform[params][@version]"]').val(href);
			}
			else if (el.closest('h3.title').hasClass('pane-toggler-down')) {
				var id =  el.closest('h3.title').attr('id');
				$('input[name="jform[params][@version]"]').val(id);
			}
			return true;
		});
		$('a[href="'+$('input[name="jform[params][@version]"]').val()+'"]').tab('show');
		var h3 = $('h3[id="'+$('input[name="jform[params][@version]"]').val()+'"]');
		if (!h3.hasClass('pane-toggler-down')) {
			h3.trigger('click');
		}
    },100);
});
