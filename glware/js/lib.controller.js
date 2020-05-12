;(function($){
	if ($ != undefined) {
		$(function() {
			var submit = $('input[type^=submit]'),
				presentationLoad = $('input[name^=presentation]'),
				pressrelLoad = $('input[name^=pressrel]'),
				logoLoad = $('input[name^=logo]'),
				photoLoad = $('input[name^=photo]'),

				hoverDelete = function() {

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
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: presentationLoad.parent().parent().find('.file_loader_preview')
					},
					server: {
						setupFormCallback: function(form, mode) {
							if (mode == 'files') {
								$(form).removeClass('ajax_form');
							} else {
								$(form).addClass('ajax_form');
							}
						}
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
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: logoLoad.parent().parent().find('.file_loader_preview')
					},
					server: {
						setupFormCallback: function(form, mode) {
							if (mode == 'files') {
								$(form).removeClass('ajax_form');
							} else {
								$(form).addClass('ajax_form');
							}
						}
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
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: photoLoad.parent().parent().find('.file_loader_preview')
					},
					server: {
						setupFormCallback: function(form, mode) {
							if (mode == 'files') {
								$(form).removeClass('ajax_form');
							} else {
								$(form).addClass('ajax_form');
							}
						}
					}
				},
/*
			pressSettings = {
				store: {
					directoryPath: '/third_party/glware/images_buffer/gallery/'
				},
				server: {
					setupFormCallback: function(form, mode) {
						if (mode == 'files') {
							$(form).removeClass('ajax_form');
						} else {
							$(form).addClass('ajax_form');
						}
					}
				}
			},*/
				gallerySettings = {
					id: 'gallery',
					loadButton: $('input[name^=new_picture]'),
					submit: $('input[type^=submit]'),
					clicker: $('input[name^=clicker]'),
					notify: $('div#notify'),
					deleteButton: $('input[name^=delet]'),

					store: {
						preview: 'pic',
						directoryPath: '/third_party/glware/images_buffer/gallery/',
						gallery: $('#pictures')
					},
					server: {
						setupFormCallback: function(form, mode) {
							if (mode == 'files') {
								$(form).removeClass('ajax_form');
							} else {
								$(form).addClass('ajax_form');
							}
						}
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
