;(function(){
	function fhFileLoader(settings) {
		this.id = settings.id;
		console.log("Constructor runs: " + this.id);
	
		this.controls = {
			loadButton: settings.loadButton || false,
			clicker: settings.clicker || false,
			deleteButton: settings.deleteButton || false,
			submit: settings.submit || false
		};

		this.notify = settings.notify || false;

		this.store = new fhImageStore(settings.store);
		this.server = new fhServer(settings.server);
		this.selectable = this.store.selectable;

		if (!this.deleteButton) {
			var del = document.createElement('div'),
				a = document.createElement('a');

			a.setAttribute("class", "cr-icon glyphicon glyphicon-remove");
			a.setAttribute("style", "color: white;");
			a.href = '';
			del.setAttribute("style", "width: 20px; height: 20px; background-color: red; float: right; display:none;");
			del.setAttribute("data-action", "remove");
			del.appendChild(a);
			this.controls.deleteButton = $(this.store.gallery[0].appendChild(del));
		}
		
		this.addReact = function() {
			this.errorNotice(false);
			var loader = this;

			var result, fileSelected;
			if (this.selectFileLoop) {
				clearInterval(this.selectFileLoop);
			}

			this.selectFileLoop = setInterval(function() {
				var unique = [];
				fileSelected = loader.fileSelected();
				loader.errorNotice(false);

				if (fileSelected && fileSelected != 'repeat') {
					unique = loader.uniqueFilter();
					clearInterval(loader.selectFileLoop);

					if (unique.length < loader.controls.loadButton[0].files.length) {
						loader.errorNotice("Вы пытаетесь загрузить файл, который уже присутствует.");
						loader.controls.loadButton.val('');
						return;
					}

					if (loader.store.limit && (loader.store.loaded + 1) > loader.store.limit) {
						loader.errorNotice("Ограничение по количеству загруженных файлов.");
						loader.controls.loadButton.val('');
						return;
					}
				
					loader.server.listen(function(response) {
						loader.controls.loadButton.val('');
						var images = [];

						for (var i = 0, n = response.errors.length; i < n; ++i) {
							err = response.errors[i];

							if (!err) {
								continue;	
							}

							loader.errorNotice(err);
						}

						for (var i = 0, n = response.loaded.length; i < n; ++i) {
							images.push(response.loaded[i]);
						}

						loader.store.add(images);
						console.log('addReact callback ends, store updated', images);
					});

					loader.controls.submit.click();
				}
			}, 600);
		};

		this.deleteReact = function() {
			var loader = this,
				selected = this.selectable.getSelected(),
				forRemoving = [];

			this.errorNotice(false);
			this.controls.loadButton.val('');

			if (selected.length) {
				for (var i = 0, n = selected.length; i < n; ++i) {
					if (!selected[i]) {
						continue;
					}

					forRemoving.push(selected[i].name);
				}
				this.store.remove(forRemoving);
				this.server.listen(function(response) {
					console.log('deleteReact got response', response);
					if (response.deleted.length) {
						loader.store.mirror();
					}
				});

				this.controls.submit.click();
				this.controls.deleteButton.hide();
			}
		};

		this.selectFileLoop = 0;
		
		var loader = this;
		this.fileSelected = (function() {
			var lastFilename = '';

			return function() {
				var val = loader.controls.loadButton.val();
				this.errorNotice(false);

				if (lastFilename && val == lastFilename) {
					//this.errorNotice('Attempting to load file twice');
					return 'repeat';
				}

				if (val) {
					lastFilename = val;	
				}

				return !!val;
			}
		})();
/*
		var bindFileSending = (function() {
			return function(but) {
				loader.controls.loadButton.on('click', function(e) {
					loader.addReact();
				});

				loader.controls.clicker.unbind().on('click', function(e) {
					loader.controls.loadButton.click();
				});
			}
		})();
*/

		this.bindEvents();
		this.errorNotice(false);
	}

	fhFileLoader.prototype.bindEvents = function() {
		var loader = this;
		
		this.controls.loadButton.on('click', function(e) {
			loader.addReact.call(loader);	
		});

		this.controls.clicker.unbind().on('click', function(e) {
			loader.controls.loadButton.click();
		});

		if (this.controls.deleteButton != false) {
			this.controls.deleteButton.on('click', function(e) {
				e.preventDefault();
				loader.deleteReact.call(loader);
			});
		}
	}


	fhFileLoader.prototype.uniqueFilter = function() {
		var files = this.controls.loadButton[0].files,
			filtered = [];

		for (var i = 0, n = files.length; i < n; ++i) {
			if (!this.store.exists(files[i].name)) {
				filtered.push(files[i]);
			}
		}
		return filtered;
	}


	fhFileLoader.prototype.errorNotice = function(message) {
		if (this.notify) {
			if (message) {
				$(notify).append($('<div>' + message + '</div>').css({
						backgroundColor: 'pink',
						display: 'inline-block',
						margin: '2px',
						padding: '5px'		
					})
				);
			} else {
				notify.innerHTML = '';
			}
		} else {
			if (message) {
				console.error(message);
			}
		}
	}

	if (window.fhFileLoader == undefined) {
		window.fhFileLoader = function(settings) {
			return new fhFileLoader(settings);
		}
	} else {
		console.error('Error has occured; Can\' create constructor (fhFileLoader)');
	}
})();
