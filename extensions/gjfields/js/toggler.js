jQuery( document ).ready(function($) {

	$.connectToggler = function (element) {

		var toBeToggled = $(element);
		var togglerData = toBeToggled.data('toggler');
		var togglerRulesGroup = toBeToggled.data('rules-group');


		$.each(togglerData, function( index, value ) {

			toBeToggled.isGroup = false;
			var toggler;
			if(toBeToggled.closest('.repeating_group').length ) {
				var togglerElementName ='jform[params]['+togglerRulesGroup+']['+index+'][]';
				var toggler = toBeToggled.closest('.repeating_group').find('[name="'+togglerElementName+'"]')[0];

			} else {
				var togglerElementName ='jform[params]['+index+']';
				var toggler = $('[name="'+togglerElementName+'"]')[0];
			}
			toggler = $(toggler);
			toggler.addClass('isToggler');
//console.log (toggler,toggler.data('tobetoggledId'));
//			toggler.data('tobetoggledId',toBeToggled.attr('id'));

			toggler.change(function() {
				if ($.inArray( this.value, value ) >-1) {
					toBeToggled.show('slow');
				} else {
					toBeToggled.hide('slow');
				}
			});
			toggler.trigger("change");
			toggler.trigger("chosen:updated");
			toggler.trigger("liszt:updated"); // At least with Joomla 3.4.8 it needs this trigger

		});
	}

	//$('.gjtoggler').each(connectToggler());
	$(".gjtoggler").each(function(){
       $.connectToggler(this);
   });


});
