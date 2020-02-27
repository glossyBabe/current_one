;(function($){
	if ($ != undefined) {
		$(function() {
			var server = new fhServer({
				}),
				store = new fhImageStore({
					directoryPath: '/img/buffer/'
				}),
				selectable = store.selectable;

				selectFileLoop = 0,
				button = $('input[name^=new_picture]'),
				submit = $('input[type^=submit]'),
				clicker = $('input[name^=clicker]'),
				deleteButt = $('input[name^=delet]'),

				isFileSelected = (function() {
					var lastFilename = '';

					return function() {
						var val = button.val();

						if (lastFilename && val == lastFilename) {
							//errorNotice(false);
							//errorNotice('Attempting to load file twice');
							return 'repeat';
						}
		
						if (val) {
							lastFilename = val;
						}

						return !!val;
					};
				})(),
	
				deleteReact = function() {
					var selected = selectable.getSelected(), forRemoving = [];
					for (var i = 0, n = selected.length; i < n; ++i) {
						if (!selected[i]) {
							continue;
						}

						forRemoving.push(selected[i].name);
					}
					store.remove(forRemoving);
					server.listen(function(response) {
						console.log('deleteReact got response', response);
						if (response.deleted.length) {
							store.mirror();
						}
					});

					submit.click();
					deleteButt.hide();
				},

				addReact = function() {
					var result, fileSelected;

					if (selectFileLoop) {
						clearInterval(selectFileLoop);
					}

					selectFileLoop = setInterval(function() {
						fileSelected = isFileSelected();

						if (fileSelected && fileSelected != 'repeat') {
							clearInterval(selectFileLoop);

							server.listen(function(response) {
								var images = [];

								for (var i = 0, n = response.errors.length; i < n; ++i) {
									err = response.errors[i];

									if (!err) {
										continue;	
									}

									errorNotice(err);
								}

								for (var i = 0, n = response.loaded.length; i < n; ++i) {
									images.push(response.loaded[i]);
								}

								store.add(images);
								console.log('addReact callback ends, store updated', images);
							});
	
							submit.click();
						}
					}, 600);
				},
	
				bindFilesending = (function() {
					return function(but) {
						but.on('click', function(e) {
							addReact();
						});

						clicker.unbind().on('click', function(e) {
							button.click();
						});
					}
				})();

			deleteButt.on('click', deleteReact);

			bindFilesending(button);
			errorNotice(false);

			function errorNotice(msg) {
				if (msg) {
					console.error(msg);
				}
			}
		});
	} else {
		console.error('jQuery not found');
	}
})(jQuery);
