jQuery( document ).ready(function($) {
	$('div.variablefield_div input.groupState[value=0]').parent().find('div.sliderContainer').hide();
	$('div.variablefield_div input.groupState[value=0]').parent().find('a.reset_current_slide').hide();
	$('.groupSlider, span.hdr-wrppr ').click(function() {
		element = $(this);
		if (!element.hasClass('inactive')) { return; }
		var slidePanelElement = element.closest('div.variablefield_div').find('div.sliderContainer');
		$( slidePanelElement).toggle( "slow", function() {
			// Animation complete.
			var groupState = $($(this).parent().find('.groupState')[0]);
			var resetButton = $($(this).parent().find('a.reset_current_slide')[0]);
			if ($(this).is(":hidden")) {
				resetButton.hide();
				groupState.val(0);
			} else {
				resetButton.show();
				groupState.val(1);
			}
		});
	});

	$('a.editGroupName').click(function(e) {
		e.preventDefault();
		var me = $(this);
		var input = $(me.parent().find('span.hdr-wrppr input.groupnameEditField')[0]);
		$(me.parent()).attr('data-value',input.val());
		var hdrspan = me.parent().find('span.hdr-wrppr');
		me.parent().find('a.cancelGroupNameEdit').toggle();
		if (me.text() == '✍') {
			me.text('✓') ;
			hdrspan.removeClass('inactive');
			input.focus();
			input.prop('readonly', false);
		}
		else {
			me.text('✍') ;
			input.prop('readonly', true);
			hdrspan.addClass('inactive');
		}
	});
	$('a.cancelGroupNameEdit').click(function(e) {
		e.preventDefault();
		var me = $(this);
		var hdrspan = me.parent().find('span.hdr-wrppr');
		var input = $(me.parent().find('span.hdr-wrppr input.groupnameEditField')[0]);

		me.toggle();
		input.readOnly = true;
		$(me.parent().find('a.editGroupNameButton')[0]).text('✍') ;
		hdrspan.addClass("inactive");
		input.val(me.parent().attr('data-value'));

	});

	$('a.delete_current_slide').click(function(e) {
		e.preventDefault();
		var me = $(this);
		var currPanel = me.closest('div.variablefield_div');
		var allPanels = currPanel.parent().find('div.variablefield_div'); // go to top level and find all collapsable groups
		if (allPanels.length ==1 )  {
			currPanel.fadeOut( "300" ).fadeIn('300');
			return false;
		}
		else {
			currPanel.fadeOut( "300", function() { $(this).remove(); } );
		}
	});

	$('a.add_new_slide').click(function(e) {
		e.preventDefault();
		var me = $(this);
		var currPanel = me.closest('div.variablefield_div');
		var maxRepeat = me.attr('data-max-repeat-length');
		if (maxRepeat>0) {
			var allPanels = currPanel.parent().find('div.variablefield_div'); // go to top level and find all collapsable groups
			if (allPanels.length>=maxRepeat) {
				currPanel.fadeOut( "100" ).fadeIn('100');
				return;
			}
		}

		var currPanelSelect = currPanel.find( 'select' );
		currPanelSelect.chosen("destroy");
		var newPanel = currPanel.clone(true);
		currPanelSelect.chosen({disable_search_threshold: 10});
		newPanel.find( '.isToggler' ).unbind( "change" ).removeClass( "isToggler" );
		newPanel.find( '.gjtoggler' ).each(function() {
			jQuery.connectToggler(this);
		});

		jQuery(newPanel).find('input.ruleUniqID')[0].value = uniqid(); // Make uniqId for a group
		// Add smth. like (2) to the name of the group when copying
		var groupNameValue = jQuery(newPanel).find('.groupnameEditField')[0].value;
		var i = 2;
		while (true) {
			var unique = true;
			jQuery('.groupnameEditField').each(function() {//iterate all toBeToggled blocks and find switches
				if (groupNameValue+' ('+i+')' == this.value) {
					unique = false;
				}
			});
			if (unique) { break; }
			i = i+1;
		}
		jQuery(newPanel).find('.groupnameEditField')[0].value = jQuery(newPanel).find('.groupnameEditField')[0].value + ' ('+i+')';

		newPanel.insertAfter(currPanel).find('select').chosen({disable_search_threshold: 10});
	});

	$('a.move_up_slide').click(function(e) {
		e.preventDefault();
		var me = $(this);
		var currPanel = $(me.closest('div.variablefield_div'));
		$(currPanel).insertBefore($(currPanel).prev());

	});
	$('a.move_down_slide').click(function(e) {
		e.preventDefault();
		var me = $(this);
		var currPanel = $(me.closest('div.variablefield_div'));
		$(currPanel).insertAfter($(currPanel).next());

	});
	$('a.reset_current_slide').click(function(e) {
		e.preventDefault();
		 if (!confirm(lang_reset)) {
			  return;
		 }
		var me = $(this);
		var currPanel = $(me.closest('div.variablefield_div'));

		currPanel.find( 'input, select , textarea, text' ).each(function() {
			var field = $(this);
			var default_value = $( this ).attr( "data-default" );
			if (default_value || (!default_value && typeof default_value !== 'undefined' )) {
				field.val(default_value);
				field.trigger("change");
				field.trigger("chosen:updated");
				field.trigger("liszt:updated"); // At least with Joomla 3.4.8 it needs this trigger
			}
		});
	});



	var uniqid = function () {
		 var ts=String(new Date().getTime()), i = 0, out = '';
		 for(i=0;i<ts.length;i+=2) {
			 out+=Number(ts.substr(i, 2)).toString(36);
		 }
		 return ('d'+out);
	}

});

