;(function(){
	function fhImageStore(settings) {
		this.settings = {
			directoryPath: settings.directoryPath || '/',

			/* В результате рефакторинга появилась идея впредь
			делать объект содержащий все эелементы, вроде как view,
			чтобы отовсюду иметь доступ к кнопкам по именам без запросов */
			deleteButton: $('input[name^=delet]'),
			preloadedId: settings.preloadedId || '#preload_pictures',
			galleryId: settings.galleryId || '#pictures',
			containerClass: settings.containerClass || '',
			containerStyle: 'float:left; position:relative; margin:20px; margin-top:15px;',
		};

		this.preloadedDiv = $(this.settings.preloadedId);

		// каждая позиция в store это одна картинка вместе с ее полями и т.д
		this.store = {};
		this.form = $(document.forms[0]);

		var that = this;

		this.gallery = $(this.settings.galleryId);

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
					if (allSelected.length) {
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

			for (var i = 0, n = children.length; i < n; ++i) {
				curChild = children[i];
				console.log(children[i]);
				if (curChild && curChild.nodeName == 'INPUT') {
					pics.push(curChild.value);
//					this.store.push(this.nameTransform(curChild.value, 'path'));
//					this.preloaded[this.nameTransform(curChild.value, 'safe')] = $(curChild);
				}

				this.add(pics);
			}
		}

		// синхронизирует хранилище в инпуты предзагрузки и в галерею
		this.mirror = function() {
			var name = '', value = '', pic = '', forLoading = [], sends = {}, preloads = {};
			this.form.find('input').each(function(i) {
				name = $(this).attr('name');
				value = $(this).val();

				if (name == 'pic[]') {
					preloads[value] = $(this);
				} else if (value == 'yes' || value == 'delete') {
					sends[name] = $(this);
				}
				
			});
			console.log('mirror starts');
			console.log('preloads and sends', preloads, sends);
			console.log('store at begining:', this.store);

			for (var safeName in this.store) {
				pic = this.store[safeName];
				console.log('while mirroring', pic);

				// грузим только если не грузили раньше,
				// две переменных используется для команды удаления
				if (!pic.inGal && !pic.loaded) {
					forLoading.push(pic.path);
				}

				if (!pic.sendField && !sends[pic.safeName]) {
					field = document.createElement('INPUT'); 
					field.type = 'hidden';
					field.name = pic.safe;
					field.value = 'yes';
					this.store[safeName].sendField = $(field).appendTo(this.form);
				} else if (pic.sendField == 'delete') {
					sends[pic.safeName].remove();
				}

				if (!pic.preloadField && !preloads[pic.safeName]) {
					field = document.createElement('INPUT'); 
					field.type = 'hidden';
					field.name = 'pic[]';
					field.value = pic.safe;
					this.store[safeName].preloadField = $(field).appendTo(this.preloadedDiv);
				} else if (!pic.preloadField == 'delete') {
					preloads[pic.safeName].remove();
				}
			}

			if (forLoading.length) {
				that = this;

				this.loadImages(forLoading, function() {
					that.refreshGallery();
				});
			} else {
				this.refreshGallery();	
			}
		},

		this.refreshGallery = function() {
			var container = false, imgElement;

			console.log('refreshGallery begin:', this.store);
			for (var safeName in this.store) {
				imgElement = this.store[safeName].loaded;

				if (this.store[safeName].inGal == false && imgElement) {
					container = document.createElement('div');			
		
					if (this.settings.containerClass) {
						container.class = this.settings.containerClass;
					} else {
						container.style = this.settings.containerStyle;
					}

					container.appendChild(imgElement);
					
					this.gallery.append(container);
					this.selectable.subscribe(imgElement, safeName);
					this.store[safeName].inGal = true;
				} else if (this.store[safeName].inGal == 'delete') {
					this.store[safeName].loaded.remove();
					delete this.store[safeName];
				}
			}

			this.selectable.unsubscribe();
		};

		this.getPreloaded();
	}


	fhImageStore.prototype.loadImages = function(pictureNames, callback) {
		var cur, img, queue = [], imgs = [], counter = -1,
			that = this;
		
		while (pictureNames.length != 0) {
			cur = pictureNames.shift();
					
			if (!cur) {
				continue;
			}
			
			counter++;
			
			img = new Image();
			img.onload = (function(numbr, currentName) {
				
				return function() {
					var safeName, storeElement;

					safeName = that.nameTransform(currentName, 'safe');
					that.store[safeName]['loaded'] = this;
					queue.shift();
					
					if (queue.length == 0) {
						callback();
					} else {
						
					}
				};
			})(counter, cur); // сохраняем естественный порядок загрузки, а не "кто весит меньше тот и вперед".
			img.src = this.settings.directoryPath + this.nameTransform(cur, 'preview');
			queue.push(1);
		}
	}


	fhImageStore.prototype.add = function(pics) {
		var name, safeName;

		if (pics.split != undefined && pics.substring != undefined) {
			pics = [pics];
		}

		for (var i = 0, n = pics.length; i < n; ++i) {
			if (pics[i] !== null) {
				safeName = pics[i];
				name = this.nameTransform(pics[i], 'path');
				this.store[safeName] = {
					path: name,
					safe: safeName,
					sendField: false,
					preloadField: false,
					loaded: false,
					inGal: false
				};

			}
		}

		if (i) {
			this.mirror();
		}
	}

	fhImageStore.prototype.remove = function(pics) {
		var safeName = '';
		console.log('image store removing: ', pics);

		if (pics.split != undefined && pics.substring != undefined) {
			pics = [pics];
		}

		for (var i = 0, n = pics.length; i < n; ++i) {
			safeName = pics[i];
			this.store[safeName].sendField.val('delete');
			this.store[safeName].sendField = 'delete';

			this.store[safeName].preloadField = 'delete';
			this.store[safeName].inGal = 'delete';
		}
	}


	fhImageStore.prototype.nameTransform = function(name, dest) {
		var result,
			token = new RegExp('^Prd[0-9]+(Lg|Md)\.[eEjJGgpPnNiIfF]+$');

		switch (dest) {
			case 'path':
				result = name.replace(/\^/g, '.');
				break;
				
			case 'safe':
				result = name.replace(/[.]/g, '^');
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
