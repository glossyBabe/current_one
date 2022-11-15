;(function(){
	function fhImageStore(settings) {
		this.settings = {
			directoryPath: settings.directoryPath || '/',

			/* В результате рефакторинга появилась идея впредь
			делать объект содержащий все эелементы, вроде как view,
			чтобы отовсюду иметь доступ к кнопкам по именам без запросов */
			preview: settings.preview || 's_',
			deleteButton: settings.deleteButton || false,
			preloadedId: settings.preloadedId || '#preload_pictures',
			formInputName: settings.formInputName || 'pic[]',
			limit: settings.limit || 0,
			gallery: settings.gallery,
			containerClass: settings.containerClass || '',
			containerStyle: 'float:left; position:relative; margin:20px; margin-top:15px;',
			loaderId: settings.loaderId || "",
			loader: settings.loader || false
		};

		this.callbacks = {
			successLoad: settings.successLoadCallback || false,
			successIMGLoad: settings.successIMGLoad || false,
			successDelete: settings.successDeleteCallback || false
		}

		this.preloadedDiv = $(this.settings.preloadedId);

		// каждая позиция в store это одна картинка вместе с ее полями и т.д
		this.store = {};
		this.loader = this.settings.loader;
		this.currentLoading = "";
		this.limit = this.settings.limit;
		this.loaded = 0;
		this.form = $(document.forms[0]);
		this.gallery = this.settings.gallery;

		this.loaderId = this.settings.loaderId;

		var that = this;

		if (this.gallery.length == 0 || this.preloadedDiv.length == 0 || this.form.length == 0) {
			console.error("FileHandler: Not consistent DOM for store working", 
				{
					gallery: this.gallery.length,
					preloaded: this.preloadedDiv.length,
					form: this.form.length
				});
			return;
		} else {
			this.selectable = new fhSelectable({
				scope: this.gallery,
				onClick: function(allSelected) {
					var somethingSelected = false;

					if (!that.settings.deleteButton) {
						that.settings.deleteButton = $(that.gallery.find("div[data-action^=remove]"));
					}

					for (var i = 0, n = allSelected.length; i < n; ++i) {
						if (allSelected[i] != null) {
							somethingSelected = true;
						}
					}

					if (somethingSelected) {
						that.settings.deleteButton.show();			
					} else {
						that.settings.deleteButton.hide();
					}
				}	
			});
		}


		this.getPreloaded = function() {
			var children = [], pics = [];

			children = this.preloadedDiv[0].childNodes;

			console.log("Created successfully ", this.loaderId, this.preloadedDiv);
			for (var i = 0, n = children.length; i < n; ++i) {
				curChild = children[i];

				if (curChild && curChild.nodeName != 'INPUT') {
					continue;
				}

				pics.push(curChild.value);
//				this.store.push(this.nameTransform(curChild.value, 'path'));
//				this.preloaded[this.nameTransform(curChild.value, 'safe')] = $(curChild);
//
				this.add(pics);
			}
		}
	
		// Отменяет загрузку несчитываемого файла и чистит все, что уже было создано в DOM для него, поля формы и т.д.
		this.abort = function(safeName) {
			console.log("aborting loading of " + safeName, "state of store now: ", this.store);
			this.store[safeName].preloadField.remove();
			this.store[safeName].sendField.remove();
			delete this.store[safeName];
			this.loaded--;
		}

		// синхронизирует хранилище в инпуты предзагрузки и в галерею
		this.mirror = function() {
			var name = '', value = '', pic = '', forLoading = [], sends = {}, preloads = {},
				that = this;

			this.form.find('input').each(function(i) {
				name = $(this).attr('name');
				value = $(this).val();

				if (name == that.settings.formInputName) {
					preloads[value] = $(this);
				} else if (value == 'yes' || value == 'delete') {
					sends[name] = $(this);
				}
				
			});

			for (var safeName in this.store) {
				pic = this.store[safeName];

				// грузим только если не грузили раньше,
				// две переменных используется для команды удаления

				if (!pic.inGal && !pic.loaded && this.settings.preview == 'pic') {
					forLoading.push(pic.path);
				}

				if (!pic.sendField && !sends[safeName]) {
					field = document.createElement('INPUT'); 
					field.type = 'hidden';
					field.name = pic.safe;
					field.value = 'yes';
					this.store[safeName].sendField = $(field).appendTo(this.form);
				} else if (pic.sendField == 'delete') {
					sends[safeName].remove();
				}

				if (!pic.preloadField && !preloads[safeName]) {
					field = document.createElement('INPUT'); 
					field.type = 'hidden';
					field.name = this.settings.formInputName;
					field.value = pic.safe;
					this.store[safeName].preloadField = $(field).appendTo(this.preloadedDiv);
				} else if (pic.preloadField == 'delete') {
					preloads[safeName].remove();
				}
			}

			if (forLoading.length) {
				that = this;

				this.loadImages(forLoading, function() {
					that.refreshGallery();
				});
			} else {
				//console.log("Now loading: " + this.store);
				this.refreshGallery();	
			}
		},

		this.refreshGallery = function() {
			var container = false, imgElement, sp,
				action;

			for (var safeName in this.store) {
				if (this.store[safeName].inGal == false) {
					action = 'add';
				} else if (this.store[safeName].inGal == 'delete') {
					//console.log("Really deleting command? " + this.store[safeName].inGal);
					action = 'delete';
				}
				
				switch (action) {
					case 'add':
						console.log("update gallery: adding " + safeName);
						container = document.createElement('div');

						if (this.settings.containerClass) {
							container.class = this.settings.containerClass;
						} else {
							container.style = this.settings.containerStyle;
						}

						if (this.settings.preview == 'pic') {
							imgElement = this.store[safeName].loaded;
							if (!imgElement) {
								break;
							}

							if (this.callbacks.successIMGLoad) {
								this.callbacks.successIMGLoad.call(this.loader, safeName, imgElement);
							} else {
								container.appendChild(imgElement);
								this.gallery.append(container);
								this.selectable.subscribe(imgElement, safeName);
							}
						} else {
							if (this.callbacks.successLoad) {
								this.callbacks.successLoad.call(this.loader, safeName);
							} else {
								sp = document.createElement("span");
								sp.innerText = this.store[safeName].path;
								container.appendChild(sp);
								this.gallery.append(container);
							}
						}

						this.store[safeName].inGal = true;

						break;

					case 'delete':
						console.log("update gallery: removing " + safeName);
						if (this.callbacks.successDelete) {
							this.callbacks.successDelete.call(this.loader, safeName);
						}

						if (this.selectable.selected.length) {
							this.selectable.unsubscribe(safeName);
						}

						if (this.settings.preview == "pic") {
							this.store[safeName].loaded.remove();
						}

						delete this.store[safeName];

						break;
				}

				action = false;
			}

		};

		this.getPreloaded();
	}


	fhImageStore.prototype.add = function(pics, fresh) {
		var picname, fileName, safeName;

		if (pics.substring != undefined && pics.split != undefined) {
			pics = [pics];
		}
	
		for (var i = 0, n = pics.length; i < n; ++i) {
			picname = pics[i].replace(/^s_/, '');

			if (picname !== null) {
				safeName = fresh ? picname : this.nameTransform(picname, 'safe');
				fileName = fresh ? this.nameTransform(picname, 'path') : picname;

				this.store[safeName] = {
					type: false,
					path: fileName,
					safe: safeName,
					sendField: false,
					preloadField: false,
					loaded: false,
					inGal: false
				};

				this.loaded++;
			}
		}

		//console.log("STORE before mirroring: ", this.loaderId, this.store);

		if (i) {
			this.mirror();
		}
	}

	fhImageStore.prototype.remove = function(pics) {
		var safeName = '', toDeleting = [];

		// we can remove file even without its name in case fileloader stores only one file
		if (this.limit == 1 && this.loaded == 1) {
			for	(var filename in this.store) {
				toDeleting.push(filename);	
			}
		} else if (pics !== undefined) {
			if (pics.split != undefined && pics.substring != undefined) {
				toDeleting = [pics];
			}
	
			toDeleting = pics;
		}

		//console.log("we delete files", this, toDeleting, this.store);
		for (var i = 0, n = toDeleting.length; i < n; ++i) {
			safeName = toDeleting[i];
			this.store[safeName].sendField.val('delete');
			this.store[safeName].sendField = 'delete';

			this.store[safeName].preloadField = 'delete';
			this.store[safeName].inGal = 'delete';

			this.loaded--;
		}
	}

	fhImageStore.prototype.exists = function(filename) {
		filename = filename.replace('C:\\fakepath\\', '');
		return this.store[this.nameTransform(filename, 'safe')] != undefined;
	}


	fhImageStore.prototype.loadImages = function(pictureNames, callback) {
		var cur, img, queue = [], imgs = [], counter = -1, storeElement,
			that = this;
		
		while (pictureNames.length != 0) {
			cur = pictureNames.shift();
					
			if (!cur) {
				continue;
			}

			counter++;
			
			img = new Image();
			img.onerror = (function(numbr, currentName) {
				return function() {
					console.error("Bad file reading: ", numbr, currentName, "Deleting from queue ");
					that.abort(that.nameTransform(currentName, 'safe'));

					//console.log("Purged store now: ", that.store);
				};
			})(counter, cur);
			img.onload = (function(numbr, currentName) {
				
				return function() {
					var safeName, storeElement;

					//console.log("loading safe", that.nameTransform(currentName, 'safe'));
					safeName = that.nameTransform(currentName, 'safe');
					that.store[safeName]['loaded'] = this;
					queue.shift();
					
					if (queue.length == 0) {
						callback();
					} else {
						
					}
				};
			})(counter, cur); // сохраняем естественный порядок загрузки, а не "кто весит меньше тот и вперед".
			storeElement = that.store[cur] != undefined ? that.store[cur] : that.store[this.nameTransform(cur, "safe")];
			if (storeElement.type == "remote") {
				//img.src = cur;
			} else {
				//console.log("Trying to load: " + cur);
				this.currentLoading = this.nameTransform(cur, 'safe');
				img.src = this.settings.directoryPath + this.nameTransform(cur, 'preview');
	
			}
		}
	}


	fhImageStore.prototype.nameTransform = function(name, dest) {
		var result,
			token = new RegExp('^Prd[0-9]+(Lg|Md)\.[eEjJGgpPnNiIfF]+$');

		if (this.store[name] != undefined && this.store[name].type == "remote") {
			return name;
		}

		switch (dest) {
			case 'path':
				result = name.replace(/\^/g, '.');
				break;

			case 'safe':
				var parts, lastPart;

				if (name.search(".") != -1) {
					parts = name.split(".");
					lastPart = parts.pop();
					result = parts.length > 1 ? parts.join(".") + "^" + lastPart : parts + "^" + lastPart;
				}

//				result = name.replace(/[.]/g, '^');
				break;
				
			case 'preview':	
				if (name.search(token) != -1) {
					result = name.replace(/Lg/g, 'Md');
				} else {
					result = 's_' + name; 
				}
				break;
				
			case 'big':
				if (img.search(token) != -1) {
					result = name.replace(/Md/g, 'Lg');
				} else {
					result = name.substring(2);
				}			
		}
		
		return result;

	}

	if (window.fhImageStore == undefined) {
		window.fhImageStore = function(settings) {
			return new fhImageStore(settings);
		}
	} else {
		console.error('Error has occured; Can\' create constructor (fhImageStore)');
	}
})();
