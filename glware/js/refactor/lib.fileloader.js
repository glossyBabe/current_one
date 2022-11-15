(function() {
	function fhFileLoader(settings) {
		this.id = settings.id;
//		console.log('Constructor runs: ' + this.id);

		this.config = {
			callbacks: {
				notify: settings.notifyCallback || false,
				loadStatus: settings.loadFileCallback || false
			}
		}

		this.controlPanel = new ControlPanel(this, {
			loadB: settings.loadButton,
			clicker: settings.clicker,
			deleteB: settings.deleteButton,
			submitB: settings.submit,
			notify = settings.notify || false;
		});
/*
		this.controls = {
			loadButton: settings.loadButton || false,
			clicker: settings.clicker || false,
			deleteButton: settings.deleteButton || false,
			submit: settings.submit || false
		};
*/
		this.navPanel = settings.navPanel || false;
		
		this.loading = false;

		if (this.id != '') {
			settings.store.loader = this,
			settings.store.loaderId = this.id;
			settings.server.loaderId = this.id;
		}

		if (this.controls.deleteButton) {
			settings.store.deleteButton = this.controls.deleteButton;
		}

		this.store = new fhImageStore(settings.store);
		this.server = new fhServer(settings.server);
		this.optionSelectable = settings.selectable || false;
		this.selectable = this.store.selectable;
/*
		if (!this.controls.deleteButton) {
			var del = document.createelement('div'),
				a = document.createelement('a');

			a.setattribute('class', 'cr-icon glyphicon glyphicon-remove');
			a.setattribute('style', 'color: white;');
			a.href = '';
			del.setattribute(
				'style',
				'width: 20px; height: 20px; border-radius: 10px; margin: 5px 0; background-color: red; float: right; text-align: center; display:none;'
			);
			del.setattribute('data-action', 'remove');
			del.appendChild(a);
			this.controls.deleteButton = $(this.store.gallery[0].appendChild(del));
		}
*/

		this.runCallback = function(cbName, params) {
			var func = this.callbacks[cbName],
				exists = func !== undefined,
				funcIsFunc = typeof func == "function",
				callExists = func.call !== undefined,
				callIsFunc = typeof func.call == "function"
				ret = false;

			if (exists && funcIsFunc && callExists && callIsFunc) {
				ret = func.call(this, params);
			}
		}

// - extract to CheckboxRelatedControlPanel!!! LATER
// new Property navPanel
/*
		this.switchactivity = function(state) {
			if (state !== undefined) {
				this.navPanel.fadeIn();
				//this.controls.clicker.show();
			} else {
				if (this.store.loaded) {
					this.controls.loadButton.val('');
					this.controls.clicker.hide();
					this.deleteMethod();
				}
				this.navPanel.fadeOut();
			}	
		}
*/


		// universal public method that removes files
		this.deleteMethod = function(forDeleting, cb) {
			var loader = this;
	
			if (this.store.loaded == 0) {
				return;
			}

			this.store.remove(forDeleting);

			this.server.listen(function(response) {
				//console.log('deleteReact got response', response);
				if (response.deleted.length) {
					loader.store.mirror();
				}
			});

			this.controlPanel.submit();
			if (cb !== undefined && cb.call !== undefined && typeof cb == "function") {
				cb();
			}
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

				//console.log("add react loop");

				if (fileSelected && fileSelected != 'repeat') {
				}
			}, 600);
		};

		this.loading = function(fname) {
			if (fname !== undefined) {
				this.loading = fname;	
				this.runCallback("loadStatus", fname);
			} else {
				this.loading = false;
				this.runCallback("loadStatus", false);
			}
		}

		this.load = function() {
			unique = loader.uniqueFilter();
//			clearInterval(loader.selectFileLoop);

			loader.runCallback("loadStatus", loader.loading);

			if (unique.length < loader.controls.loadButton[0].files.length) {
				loader.errorNotice('Вы пытаетесь загрузить файл, который уже присутствует.');
				loader.controlPanel.loadBReset();
				return;
			}

			if (loader.store.limit && loader.store.loaded + 1 > loader.store.limit) {
				loader.errorNotice('Ограничение по количеству загруженных файлов.');
				loader.controlPanel.loadBReset();
				return;
			}

			loader.server.listen(function(response) {
				loader.controlPanel.loadBReset();
				loader.loading(false);

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

				loader.store.add(images, true);
				//console.log('addReact callback ends, store updated', images);
			});

			loader.controlPanel.submit();
		}

		this.selectLoop = function(cb) {
			var loader = this;

			this.selectFileLoop = setInterval(function() {
				var fname = "";

				loader.errorNotice(false);

				if (!!(fname = loader.controlPanel.isFileSelected())) {
					clearInterval(loader.selectFileLoop);
					loader.loading(fname);
					cb();
				}
			});
		}


		this.deleteReact = function() {
			var selected = this.selectable.getSelected(),
				forRemoving = [];

			this.errorNotice(false);
			this.controls.loadButton.val('');

			if (selected.length || !this.optionSelectable) {
				for (var i = 0, n = selected.length; i < n; ++i) {
					if (!selected[i]) {
						continue;
					}

					forRemoving.push(selected[i].name);
				}

				this.deleteMethod(forRemoving);
				this.controls.deleteButton.hide();
			}
		};


		this.changeState = function(state) {
			var s = false;
			if (state != undefined) {
				s = state;
			}

			if (s) {
	
			} else {
				this.controls.clicker.first().hide();	
				this.store.gallery.first().hide();
			}

		}


		this.selectFileLoop = 0;

		var loader = this;
		this.fileSelected = (function() {
			var lastFilename = '';

			return function() {
				var val = loader.controls.loadButton.val();
				this.errorNotice(false);
				//console.log("now value of button is ", val);

				if (lastFilename && val == lastFilename) {
					this.errorNotice('Attempting to load file twice');
					//console.log("last value is ", lastFilename);
					return 'repeat';
				}

				if (val) {
					lastFilename = val;
					loader.loading = val;
				}

				return !!val;
			};
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

		this.controlPanel.setLoadButtonReaction(function() {
			loader.selectLoop(loader.load);
		});

		this.controls.loadButton.on('click', function(e) {
			loader.addReact.call(loader);
		});

		this.controls.clicker.unbind().on('click', function(e) {
			loader.controls.loadButton.click();
		});

		if (this.controls.deleteButton != false) {
			//console.log("What do we have instead of jquery delete button? ", this.controls.deleteButton);
			this.controls.deleteButton.on('click', function(e) {
				e.preventDefault();
				loader.deleteReact.call(loader);
			});
		}
	};

	fhFileLoader.prototype.uniqueFilter = function() {
		var files = this.controls.loadButton[0].files,
			filtered = [];

		for (var i = 0, n = files.length; i < n; ++i) {
			if (!this.store.exists(files[i].name)) {
				filtered.push(files[i]);
			}
		}
		return filtered;
	};


	fhFileLoader.prototype.error(message) {
		if (this.callbacks.notify) {
			this.runCallback("notify", message);
		} else {
			this.controlPanel.displayError(message);
		}
	}


	if (window.fhFileLoader == undefined) {
		window.fhFileLoader = function(settings) {
			return new fhFileLoader(settings);
		};
	} else {
		console.error("Error has occured; Can' create constructor (fhFileLoader)");
	}
})();
