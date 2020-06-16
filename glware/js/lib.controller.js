;(function($){
	if ($ != undefined) {
		$(function() {
			var submit = $('input[type^=submit]'),
				presentationLoad = $('input[name^=presentation]'),
				pressrelLoad = $('input[name^=pressrelease]'),
				logoLoad = $('input[name^=logo]'),
				photoLoad = $('input[name^=photo]'),

				hoverDelete = function() {

				},

				formitSwitchForm = function(form, mode) {
					if (mode == 'files') {
						$(form).removeClass('ajax_form');
					} else {
						$(form).addClass('ajax_form');
					}
				},

				presentationSettings = {
					id: 'presentation',
					loadButton: presentationLoad,
					clicker: presentationLoad.parent().find('input[name^=clicker]'),
					submit: submit,

					deleteReact: hoverDelete,
					
					store: {
						preview: false,
						limit: 1,
						formInputName: 'presentation',
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: presentationLoad.parent().parent().find('.file_loader_preview')
					},
					server: {
						setupFormCallback: formitSwitchForm
					}
				},

				pressSettings = {
					id: 'pressrel',
					loadButton: pressrelLoad,
					clicker: pressrelLoad.parent().find('input[name^=clicker]'),
					submit: submit,

					deleteReact: hoverDelete,	

					store: {
						limit: 1,
						preview: false,
						formInputName: 'pressrelease',
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: pressrelLoad.parent().parent().find('.file_loader_preview')
					},
					server: {
						setupFormCallback: formitSwitchForm
					}
				},

				logoSettings = {
					id: 'logo',
					loadButton: logoLoad,
					clicker: logoLoad.parent().find('input[name^=clicker]'),
					submit: submit,

					deleteReact: hoverDelete,	
	
					store: {
						limit: 1,
						preview: 'pic',
						formInputName: 'logo',
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: logoLoad.parent().parent().find('.file_loader_preview')
					},
					server: {
						setupFormCallback: formitSwitchForm
					}
				},

				photoSettings = {
					id: 'photo_dir',
					loadButton: photoLoad,
					clicker: photoLoad.parent().find('input[name^=clicker]'),
					submit: submit,

					deleteReact: hoverDelete,	

					store: {
						limit: 1,
						preview: 'pic',
						formInputName: 'photo',
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: photoLoad.parent().parent().find('.file_loader_preview')
					},
					server: {
						setupFormCallback: formitSwitchForm
					}
				},

				gallerySettings = {
					id: 'gallery',
					loadButton: $('input[name^=new_picture]'),
					submit: $('input[type^=submit]'),
					clicker: $('input[name^=clicker]'),
					notify: $('div#notify'),
					deleteButton: $('input[name^=delet]'),

					store: {
						preview: 'pic',
						formInputName: 'pic[]',
						directoryPath: '/third_party/glware/images_buffer/gallery/',
						gallery: $('#pictures')
					},
					server: {
						setupFormCallback: formitSwitchForm
					}
				},

			gallery = new fhFileLoader(gallerySettings),
			presentation = new fhFileLoader(presentationSettings);
			logo = new fhFileLoader(logoSettings),
			//press = new fhFileLoader(pressSettings),
			photo = new fhFileLoader(photoSettings);
		});
	} else {
		console.error('jQuery not found');
	}
})(jQuery);
