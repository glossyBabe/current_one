$(function() {
	var checkboxes = {},
		form = $('.judge_form').find('form'),
		actionUrl = form[0].getAttribute('action');

	function voteFinish(formResults) {
		$.post(actionUrl, formResults, function() {
			form.find('button[type^=submit]').attr('disabled', true);
			$.fancybox.close();

			document.location.href = document.location.href;
		});
	}

	function cancel() {
		var buttonAccept = $('.vote-accept-btn'),
			buttonCancel = $('.vote-cancel-btn'),
			fb = $.fancybox;

		if (buttonAccept.length && buttonCancel.length && fb) {
			buttonAccept.off('click');
			buttonCancel.off('click');
	
			fb.close();
		}
	}

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
			$.fancybox.close();
			$.fancybox.open(
			  '<div class="message"><h2 style="color: white;">Подтверждение голоса</h2><h4 style="color: white;">Голос является окончательным. Вы уверены, что хотите продолжить?</h4>' +
				'<div><button class="btn btn-success vote-accept-btn">Подтведить</button><button class="btn btn-danger vote-cancel-btn">Отмена</button></div>' +
				'</div>'
			);

			var buttonAccept = $('.vote-accept-btn'),
				buttonCancel = $('.vote-cancel-btn'),
			
				insertResult = function(results) {
					return function() {
						voteFinish(results);
					}
				}

			buttonAccept.on('click', insertResult(formResults));
			buttonCancel.on('click', cancel);
		}
	});

});

		
