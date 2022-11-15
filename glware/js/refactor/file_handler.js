console.log('javascript is running');

// задача по рефакторингу следующая: выделить вначале три базовых больших класса Selectable, Images, Server
// после этого чистка уже сильно упростится. Останется только немного клиентского кода использующего эти классы и пара служебных методов.


$(function() {
	var button     = $('input[name^=new_picture]'),
		clicker    = $('input[name^=clicker]'),
		submit     = $('input[type^=submit]');
		
		$('body').append($('<iframe name=\'fileserver\' style=\'display:none\' ></iframe>'));
		
		iframe     = $('iframe[name^=fileserver]'),
		div        = $('#pictures'),
		notify     = $('#notify'),
		preloaded  = $('#preload_pictures'),
		form       = submit[0].form,		
		images     = [],
		fields     = [],
		state      = 'normal',
		
		directoryPath = '/img/buffer/',
		
		preloadedFields = [],
		deletedImages = [],
		
		mainFormActionUrl = form.getAttribute('action'),
		handlerUrl = '/controls/handler.php',
		deleteButt = $('input[name^=delet]').hide();

		// вызывается из селектыбла, проверяет нужно ли показывать кнопку делит.
		check_delete_availability = function() {
			
			if (!!Selectable.getSelected().length) {
				deleteButt.show();
			} else {
				deleteButt.hide();				
			}
		};
		
		deleteButt.click(function(e) {
			var selected = Selectable.getSelected();
			
			for (var i = 0, n = selected.length; i < n; ++i) {
				if (!selected[i]) {
					continue;
				}
				
				var picname = $(selected[i].img).attr('src'); // ================== Gallery.delete(img);
				picname = picname.replace(/\/img\/buffer\//, '');
				picname = imgNameTransform(imgNameTransform(picname, 'big'), 'safe') // убираем 's_' для превьюшек.
				console.log(picname, 'пытались удалить')
				$(form[picname]).val('delete');
				
			}
			
			listenToServ();
			formSwitch('files');
			submit.click();
			
		});
		
		
	
	// миниобъект, дает возможность выбирать превьюшки
	
	function Selectable() {
		
		this.store = [];
		this.selected = [];
		this.buffer = []; // for events and timers
		
		this.bindEvents();
	}	
	
	
	Selectable.prototype.bindEvents = function() {
		var that = this;
			
		// присабачиваем онклик к всему диву с картинками, чтобы ловить всплытие кликов только внутри него. И загрузка очередной картинки
		// не вызывала внезапного развыделения
		div.click(function(e) {
								
			if (that.store === undefined || that.store.length == 0) {
				return;
			}
			
			var i, n, indexInSList,
			
				/* Нужно универсализировать и ловить контейнер элемента, по которому был клик - не сам элемент. */
				target = $(e.target).parent()[0],
				elem = that.findInStore('container', target);
				//console.log(target);
			
			if (!elem) {
				// если щелкнули по свободному от превьюшек месту
				that.unselectAll();
			} else {
				
				that.toggle(elem);
				
			}
			
			// вот и весь этот ваш селектыбл
			
			check_delete_availability();
		});
		
	}
	
	
	Selectable.prototype.subscribe = function(elem) {
		// вызывается строго по загрузке изображения, так что размеры у нас уже есть.
		var selectSquare, index,
			img_h = $(elem).height(),
			img_w = $(elem).width(),
			container = $(elem).parent()[0];
		
		// добавим квадратик для выделения, который просто потом будем показывать и скрывать.
		$('<div></div>')
			.addClass('select_square')
			.css({
				zIndex: 2,
				display: 'none',
				position: 'absolute',
				backgroundColor: 'blue',
				top: 0,
				left: 0,
				width: img_w,
				height: img_h,
				opacity: 0.3
			})
			.appendTo($(container));
		
		selectSquare = $(container).find('div.select_square')[0];
		index = this.store.length;
		
		this.store.push({
			img: elem,
			container: container,
			select: selectSquare,
			index: index
		});
	};

	
	Selectable.prototype.unsubscribe = function(elem) {
		var store = this.store,
			selected = this.selected,
			i = elem.index;
		
		$(elem.select).remove();
		$(elem.container).click();		
		
		selected.splice(elem.index, 1);
		store.splice(elem.index, 1);
		
		
		
		if (store[elem.index]) {
			this.reindex(elem.index);
		}
		
		check_delete_availability();
	};

	
	Selectable.prototype.reindex = function(index) {
		var i, n, store = this.store;
		
		for (i = index, n = store.length; i < n; i++) {
			store[i].index = i;
		}
	}
	
	
	Selectable.prototype.getSelected = function() {
		var selected = this.selected,
			found = false;
		
		// очистим от нуллов
		for (var i = 0, n = selected.length; i < n; ++i) {
			if (selected[i]) {
				found = true;
			}
		}
		
		return found ? selected : [];
	};
	
	
	Selectable.prototype.select = function(elem) {
		var selected = this.selected;
		

		
		if (!elem) {
			return;
		}
		
		$(elem.select).show();
		selected[elem.index] = elem;
	};

	
	Selectable.prototype.unselect = function(elem) {
		var selected = this.selected;
		
		if (!elem) {
			return;
		}
		
		$(elem.select).hide();
		selected[elem.index] = null;
	};
	
		
	Selectable.prototype.findInStore = function(key, param) {
		var result = false,
			store = this.store;
		
		for (i = 0, n = store.length; i < n; ++i) {
			if (!store[i]) {
				continue;
			}
			
			if (store[i][key] == param) {
				result = store[i];
			}
		}
		
		return result;
	};
	
	Selectable.prototype.unselectAll = function() {
		var store = this.store,
			selected = this.selected;
		
		for (i = 0, n = store.length; i < n; ++i) {
			this.unselect(store[i]);			
		}
	};
	
	// выбранный развыделяется, невыделенный выделяется.
	Selectable.prototype.toggle = function(elem) {
		var store = this.store,
			selected = this.selected;
		
		// предосторожность: если вообще не относится к селектабл
		if (!store[elem.index]) {
			return;
		}
			
		if (store[elem.index] == selected[elem.index]) {			
			this.unselect(elem);
		} else {
			this.select(elem);			
		}
	};
	/*
	Selectable.prototype.reposition = function() { // ======================================= сделать приватным, вызывать из самого Selectable автоматически, придумать как.
		var i, n, nowPosition, lastPosition,
			store = this.store;
			
		for (i = 0, n = store.length; i < n; ++i) {
			if (!store[i]) {
				continue;
			}
			
			nowPosition = $(store[i].img).offset();
			$(store[i].select).show().offset(nowPosition).hide();
			//lastPosition = store[i].selectArea.offset();
		}
	};
	*/

	var Selectable = new Selectable();
	
	// end of SELECTABLE //
	
	
		
	// проверяет нужно ли при отрисовке страницы показать картинки, уже загруженные на страницу из базы или ранее.
	var checkPreload = function(){ // ========== явно внутренний метод Images, не должен выполнять операций, а просто обогащать хранилище класса изображениями.
		var curPic = '';
		state = 'preloading';
		
		for (var i = 0, n = preloaded[0].childNodes.length; i < n; ++i) {
			curChild = preloaded[0].childNodes[i];
			
			if (curChild.nodeName == 'INPUT') {
				images.push(imgNameTransform(curChild.value, 'path'));
				preloadedFields[imgNameTransform(curChild.value, 'safe')] = $(curChild);
			}
		}
		
		if (i > 0) {
			
			addAlbumFields();
			state = 'normal';
			
			loadPics(putPic);
		}
	};
	
	var listenToServ = function() {
		
		
			
		var iframeAnsw, parts, ifr, i, n, t;
		
		t = setInterval(function() {
			
			console.log('sending now...');
				
			if (isFileLoaded()) {
				formSwitch('main'); // возвращаем обычное поведение форме.
				ifr = iframe.contents().find('body');
				iframeAnsw = ifr.text();
				ifr.html(null);
				
				clearInterval(t);
				parseServAnswer(iframeAnsw);
			}
		
		}, 300);
	};
	
	var formSwitch = function(mode) {
		var form = submit[0].form;
		
		switch (mode) {
			case 'files':
				form.setAttribute('action', handlerUrl);
				form.setAttribute('target', 'fileserver');
				form.setAttribute('enctype', 'multipart/form-data');
				$('input[name^=command]').val('files');
				break;
				
			case 'main':
				form.setAttribute('action', mainFormActionUrl);
				form.setAttribute('target', '_self');
				form.setAttribute('enctype', 'application/x-www-form-urlencoded');
				$('input[name^=command]').val('');
		}		
	};
	
	var notice = function(text) {
		if (text === false) {
			notify.html('');
		} else {
			notify.append($('<div>' + text + '</div>').css({
					backgroundColor: 'pink',
					display: 'inline-block',
					margin: '2px',
					padding: '5px'		
				})
			);
		}		
	};
	
	var parseServAnswer = function(answ) {
		console.log(answ);
		// по всем заветам Фаулера здесь наиявнейшим образом просится что-то вроде фабрики server.query('delete') и server.query('load').
		// Да, мне никогда не нравился этот метод, что-то уж слишком много ифов;
			var frstMarker, lstMarker, content, parts, start, end, tempArr, mode;
			tempArr = [];
			
			if (answ.search(/\[loaded\]/) != -1) {
				mode  = 'load';
				start = new RegExp('\\[loaded\\]');
				end   = new RegExp('\\[\\/loaded\\]');
			
			} else if (answ.search(/\[deleted\]/) != -1) {
			
				mode  = 'delete';
				start = new RegExp('\\[deleted\\]');
				end   = new RegExp('\\[\\/deleted\\]');
			}
			
			console.log(mode, 'режим');
			
			frstMarker  = answ.search(start) - 1;
			answ        = answ.replace(start, '');
			lstMarker   = answ.search(end);
			answ        = answ.replace(end, '');
			content     = answ.substring(frstMarker, lstMarker);
			parts       = (content.search(/\|/) != -1) ? content.split('|') : [content];

			if (parts.length > 1) {
				parts.shift();
			}
			
			iframe
			
			notice(false);
			
			if (parts.length > 1) {
			
				for (i = 0, n = parts.length; i < n; ++i) {
					if (!parts[i]) {
						continue;
					}
					
					// замененную на циркумфлекс точку возвращаем, чтобы можно было считать полученные изображения.
					if (parts[i].search('error') == -1) {
						tempArr.push(imgNameTransform(parts[i], 'path'))
					} else {
						tempArr.push(null);
						notice(parts[i]);
					}
				}
				
			} else if (parts.length == 1) {
				if (parts[0].search('error') == -1) {
					tempArr.push(imgNameTransform(parts[0], 'path'))
				} else {
					tempArr.push(null);
					notice(parts[0]);
				}
			}
				
			if (mode == 'load') {
				
				images = tempArr;
				addAlbumFields();
				console.log(tempArr, "имагес сразу после парсинга");
				
				/* коллбечная функция грузит все имаги асинхронно, как только они доступны вызывается пут пик
				и ему передается получившийся массив. */
				loadPics(putPic);
				
			} else if (mode == 'delete') {
				deletedImages = tempArr;
				removeAlbumFields();
				deletePic();
				
			}			
			
			return;			
		},
		
		// ============ есть группа функций которая занимается манипуляцией с DOM, в основном кнопками.
	
		sendPic = function() {
			formSwitch('files');
			listenToServ();
			submit.click();
			
			p = button.parent();
			button.remove();
			
			var input = $('<input/>', {
				type: 'file',
				name: 'new_picture[]',
				multiple: true
			});
			
			input.css({display:'none'});
			
			var newButton = input.prependTo(p);
			button = newButton;
			bindFilesending(button);
			
			return;
		};
	
	var imgNameTransform = function(img, dest) { // =========== служебный метод Images
		var result,
			token = new RegExp('^Prd[0-9]+(Lg|Md)\.[eEjJGgpPnNiIfF]+$');
			
		switch (dest) {
			case 'path':
				result = img.replace(/\^/g, '.');
				break;
				
			case 'safe':
				result = img.replace(/[.]/g, '^');
				break;
				
			case 'preview':				
				if (img.search(token) != -1) {
					result = img.replace(/Lg/g, 'Md');
				} else {
					result = 's_' + img;
				}
				break;
				
			case 'big':
				if (img.search(token) != -1) {
					result = img.replace(/Md/g, 'Lg');
				} else {
					result = img.substring(2);
				}			
		}
		
		return result;
	};
	
	var removeAlbumFields = function() { // ==================== служебный метод Images, потому что картинка прочно спаяна с ее представлением в виде поля.
										// ===================== фактически это доставание в глобальный неймспейс скрипта одной из самых глубоких абстракций: передачи имени файла.
										// ===================== спрятать, спрятать как можно дальше.
		var name;
		
		inputs = div.find('input');
		
		for (var i = 0, n = deletedImages.length; i < n; ++i) {
			name = imgNameTransform(deletedImages[i], 'safe');
			
			fields[name].remove();
			fields[name] = null;
			
			preloadedFields[name].remove();
			preloadedFields[name];
			
		}
	};
	
	
	
	var addAlbumFields = function() { // ======================= аналогично - глубокая абстракция, не имеет ничего общего с полезными данными
		var f = clicker[0].form,
			i, n, name, field;
		for (i = 0, n = images.length; i < n; ++i) {
			
			if (images[i] === null) {
				continue;
			}
			
			name = imgNameTransform(images[i], 'safe');
			
			// чтобы прошло в пост без изменений заменяем в именах файлов точку на циркумфлекс.
			field = $('<input type=\'hidden\' name=\'' + name + '\' value=\'yes\'>');
			fields[name] = field.appendTo(f);
			
			// добавим поля также в предазгрузку, чтобы после например добавления полей в форму и перезагрузки страницы добавленные ранее картинки были сразу видны
			if (state != 'preloading') {
				field = $('<input type=\'hidden\' name=\'pic[]\' value=\'' + name + '\'>');
				preloadedFields[name] = field.appendTo(preloaded);
				
			}
		}
	};
	
	var loadPics = function(callb) { // приватный загрузчик класса Images, используется и предзагрузчиком.
		var cur, img, queue = [], imgs = [], counter = -1;
		
		while (images.length != 0) {
			cur = images.shift();
					
			if (!cur) {
				continue;
			}
			
			counter++;
			
			img = new Image();
			img.onload = (function(numbr) {
				
				return function() {
					queue.shift();
					imgs[numbr] = this;
					
					if (queue.length == 0) {
						callb(imgs);
					} else {
						
					}
				};
			})(counter); // сохраняем естественный порядок загрузки, а не "кто весит меньше тот и вперед".
			
			img.src = directoryPath + imgNameTransform(cur, 'preview');
			queue.push(1);
		}
	};
	
	
	var putPic = function(imgs) {
		
		for (var i = 0, n = imgs.length; i < n; ++i) {
			div.append($('<div></div>').css({'float': 'left', 'position': 'relative', 'margin': '20px', 'margin-top': '15px'}).append(imgs[i]));
			Selectable.subscribe(imgs[i]);
		}
	};
	
	var deletePic = function() {
		var cur, i, elem, n, arr = deletedImages;
		
		while (arr.length != 0) {
			cur = arr.shift();
			
			if (!cur) {
				continue;
			}
			
			cur = '/img/buffer/' + imgNameTransform(cur, 'preview');
			
			// todo: хорошо бы запилить единое обращение по псевдоимени с циркумфлексом а не перебирать массив изображений каждый раз.
			for (i = 0, n = document.images.length; i < n; ++i) {
				//console.log(document.images[i]);
				
				if (document.images[i].getAttribute('src') == cur) {
					elem = Selectable.findInStore('img', document.images[i]);
					$(elem.container).remove();					
					Selectable.unsubscribe(elem);
					
										
					//$(document.images[i]).parent().remove();
					//console.log(cur);
					break;
				}
			}
		}		
	};
	
	var isFileLoaded = function() {
		return iframe.contents().find('body').text() !== '';
	};
	
	var isFileSelected = (function() {
		var lastFilename = '';
	
		return function() {
			var val = button.val();
			
			if (lastFilename && val == lastFilename) {
				notice(false);
				notice('Этот файл загружался в прошлый раз');
				return 'repeat';
			}
			
			if (val) {
				lastFilename = val;
			}
			
			return !!val;
		};
	})();
	
	// внешняя переменная для адресации к лупу "проверки выбран ли файл в кнопке", а то они твари множатся в замыканиях
	var selectFileLoop;

	var bindFilesending = (function(){
	
		return function(b) {
			b.click(function(e) {
				var result;
				
				if (selectFileLoop) {
					clearInterval(selectFileLoop);
				}
				
				selectFileLoop = setInterval(function() {
					var fileSelected = isFileSelected();
					//console.log('selecting file...');
					if (fileSelected) {
						if (fileSelected == 'repeat') {
							//clearInterval(selectFileLoop);
							return;
						}
						
						clearInterval(selectFileLoop);
						
						sendPic();
					}
				}, 600);
			});
			
			clicker.unbind().click(function(){
				button.click();
			});
		};
	})();
	
	checkPreload(); // =============================== на самом деле создать new Images(), который уже вызовет эту функцию в конструкторе.
					// =============================== Если нет предзагруженных просто хранилище будет пустым.
	bindFilesending(button);
});
