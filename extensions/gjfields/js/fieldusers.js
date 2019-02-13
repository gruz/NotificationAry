/**
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Field user
 */
;(function($){
	'use strict';

	$.fieldUsers = function(container, options){
		// Merge options with defaults
		this.options = $.extend({}, $.fieldUsers.defaults, options);
		// Set up elements
		this.$container = $(container);
		this.$modal = this.$container.find(this.options.modal);
		this.$modalBody = this.$modal.children('.modal-body');
		this.$input = this.$container.find(this.options.input);
		/* ##mygruz20160510085747 {
		It was:
		this.$inputName = this.$container.find(this.options.inputName);
		It became:*/
		/* ##mygruz20160510085747 } */
		this.$buttonSelect = this.$container.find(this.options.buttonSelect);

		// Bind events
		this.$buttonSelect.on('click', this.modalOpen.bind(this));
		this.$modal.on('hide', this.removeIframe.bind(this));

		// Check for onchange callback,
		var onchangeStr =  this.$input.attr('data-onchange'), onchangeCallback;
		if(onchangeStr) {
			onchangeCallback = new Function(onchangeStr);
			this.$input.on('change', onchangeCallback.bind(this.$input));
		}

	};

	// display modal for select the file
	$.fieldUsers.prototype.modalOpen = function() {
		var $iframe = $('<iframe>', {
			name: 'field-users-modal',
			src: this.options.url.replace('{field-users-id}', this.$input.attr('id')),
			width: this.options.modalWidth,
			height: this.options.modalHeight
		});
		this.$modalBody.append($iframe);
		this.$modal.modal('show');
		$('body').addClass('modal-open');

		var self = this; // save context
		$iframe.load(function(){



			var content = $(this).contents();
			/* ##mygruz20160510090803 {
			It was:
			It became:*/
			var selectOptions = $(self.$input).find('option:selected');
			var appendText = '<span class="icon-publish"></span>';
			$(selectOptions).each(function()	{
					var $elem = 	$(content).find('a[data-user-value="'+$(this).val()+'"]');
					$elem.append(appendText);
					$elem.addClass('disabled');
					$elem.removeClass('button-select');
					$elem.unbind( "click" );
			});
			/* ##mygruz20160510090803 } */

			// handle value select
			content.on('click', '.button-select', function(){
				/* ##mygruz20160510071339 {
				self.setValue($(this).data('user-value'), $(this).data('user-name'));
				self.modalClose();
				self.modalClose();
				It was:
				It became: */
				if (self.$input.is("input")) {
					self.setValue($(this).data('user-value'), $(this).data('user-name'));
				} else {
					var username_element = $(this).parent().parent().find('td')[1];
					var username = $(username_element).text();
					self.setValue($(this).data('user-value'), username);
				}

				var $elem = 	$(this);
				$elem.append(appendText);
				$elem.addClass('disabled');
				$elem.removeClass('button-select');
				$elem.unbind( "click" );
				//$(this).find('.label').delay(1000).hide('slow');
				/* ##mygruz20160510071339 } */

				$('body').removeClass('modal-open');
			});
		});
	};

	// close modal
	$.fieldUsers.prototype.modalClose = function() {
		this.$modal.modal('hide');
		this.$modalBody.empty();
		$('body').removeClass('modal-open');
	};

	// close modal
	$.fieldUsers.prototype.removeIframe = function() {
		this.$modalBody.empty();
		$('body').removeClass('modal-open');
	};

	// set the value
	/* ##mygruz20160510071229 { Reworked function : */
	$.fieldUsers.prototype.setValue = function(value, name) {
		var current_value = this.$input.val();
		if (current_value) {
			var res_value = current_value+','+value;
		} else {
			var res_value = value;
		}
		if (this.$input.is("input")) {
			this.$input.val(res_value).trigger('change');
			// this.$inputName.val(res_name || res_value).trigger('change');
		} else {
			this.$input.find('option[value="'+value+'"]').remove();
			this.$input.append('<option selected="selected" value="'+value+'">'+name+'</option>');
			this.$input.trigger('change');
			this.$input.trigger("liszt:updated");
		}
	};
	/* ##mygruz20160510071229 } */

	// default options
	$.fieldUsers.defaults = {
		buttonSelect: '.button-select', // selector for button to change the value
		input: '.field-users-input', // selector for the input for the user id
		inputName: '.field-users-input-name', // selector for the input for the user name
		modal: '.modal', // modal selector
		url : 'index.php?option=com_users&view=users&layout=modal&tmpl=component',
		modalWidth: '100%', // modal width
		modalHeight: '300px' // modal height
	};

	$.fn.fieldUsers = function(options){
		return this.each(function(){
			var $el = $(this), instance = $el.data('fieldUsers');
			if(!instance){
				var options = options || {},
					data = $el.data();

				// Check options in the element
				for (var p in data) {
					if (data.hasOwnProperty(p)) {
						options[p] = data[p];
					}
				}

				instance = new $.fieldUsers(this, options);
				$el.data('fieldUsers', instance);
			}
		});
	};

	// Initialise all defaults
	$(document).ready(function(){
		$('.field-users-wrapper').fieldUsers();
	});

})(jQuery);

// Compatibility with mootools modal layout
function jSelectUsers(element) { /* ##mygruz20160510174500 { Doesn't work in the original joomla user field  ##mygruz20160510174500 } */

	var $el = jQuery(element),
		value = $el.data('users-value'),
		name  = $el.data('users-name'),
		fieldId = $el.data('users-field'),
		$inputValue = jQuery('#' + fieldId + '_id'),
		$inputName  = jQuery('#' + fieldId);

	if (!$inputValue.length) {
		// The input not found
		return;
	}

	// Update the value
	$inputValue.val(value).trigger('change');
	$inputName.val(name || value).trigger('change');

	// Check for onchange callback,
	var onchangeStr = $inputValue.attr('data-onchange'), onchangeCallback;
	if(onchangeStr) {
		onchangeCallback = new Function(onchangeStr);
		onchangeCallback.call($inputValue[0]);
	}
	jModalClose();
}
