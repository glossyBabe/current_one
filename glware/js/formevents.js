$(function() {
	var links = $('.request_holder a'),
		inputs = {}, tabs = {},
		slaves = $('div.form-group'),
		masters = {};

	function bindMasterControlElement(slave, master) {
		if (masters[master] == undefined) {
			masters[master] = [];
		}

		masters[master].push(slave);
	}
	
	links.each(function(i) {
		if (this.getAttribute('role') == 'tab') {
			var selector = this.getAttribute('href'),
				localInputs = $(selector).find('input'),
				name;
		
			localInputs.each(function(i) {
				name = this.getAttribute('name');

				// collect elements for highlighting errors in tabs
				if (inputs[name] == undefined) {
					inputs[name] = selector;
					tabs[selector] = $("a[href^='" + selector + "']");
				}

			});
		}
	});

	slaves.each(function(e) {
		// collect elements (within a function call) for checkbox showing and hiding
		if (this.getAttribute('master-control')) {
			bindMasterControlElement(this, this.getAttribute('master-control'));
		}
	});

	$(document).on('af_complete', function(e, response) {
		if (!response.success) {
			var first = 1;
			
			for (var name in response.data) {
				errorSel = inputs[name];
				tabs[errorSel].addClass('tab_error');

				if (first) {
					tabs[errorSel].tab('show');

					first--;
				}
			}
		}
	});

	// showing and hiding areas
	function toggleSlaveAreas (key, state) {
		var func = state ? 'show' : 'hide';
		for (var i = 0, n = masters[key].length; i < n; $(masters[key][i])[func](), ++i);
	}

	console.log(masters);
	for (var sl in masters) {
		toggleSlaveAreas(sl, false);
	}

	$('.request_holder').on('click', function(e) {
		elemId = e.target.getAttribute('id');

		if (elemId && (e.target.type == 'checkbox' || e.target.type == 'radio') && masters[elemId] != undefined) {
			toggleSlaveAreas(elemId, e.target.checked);
		}
	});
});
