$(function() {
	var checkboxes = {},
		form = $('.judge_form').find('form'),
		actionUrl = form[0].getAttribute('action');

	form.on('submit', function(e) {
		e.preventDefault();
		var formResults = {},
			emptyCheck = [];

		form.find('input').each(function(i) {
			var name = this.getAttribute('name'),
				type = this.getAttribute('type');

			if (type == 'checkbox' && this.checked) {
				if (formResults[name] == undefined) {
					formResults[name] = [];
				}

				formResults[name].push(this.getAttribute('value'));
				emptyCheck.push(true);

			} else if (type == 'hidden' || type == 'text') {
				if (type != 'hidden' && $(this).val()) {
					emptyCheck.push(true);	
				}

				if ($(this).val) {
					formResults[name] = $(this).val();
				}
			}

		});

		if (emptyCheck.length) {
			$.post(actionUrl, formResults, function() {
				form.find('button[type^=submit]').attr('disabled', true);
				document.location.href = document.location.href;
			});
		}
	});

});
