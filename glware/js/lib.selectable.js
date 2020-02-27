// миниобъект, дает возможность выбирать превьюшки
(function() {	
	function fhSelectable(settings) {
		this.mainContainer = settings.scope;
		this.callbacks = {
			onClick: settings.onClick || false
		}
		
		this.store = [];
		this.selected = [];
		this.buffer = []; // for events and timers
		
		this.bindEvents();
	}	
	
	fhSelectable.prototype.bindEvents = function() {
		var that = this;
			
		// присабачиваем онклик к всему диву с картинками, чтобы ловить всплытие кликов только внутри него. И загрузка очередной картинки
		// не вызывала внезапного развыделения
		this.mainContainer.click(function(e) {
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

			if (that.callbacks.onClick != false) {
				that.callbacks.onClick(that.selected);
			}

			// вот и весь этот ваш селектыбл
		});
	}
	
	
	fhSelectable.prototype.subscribe = function(elem, name) {
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
			index: index,
			name: false
		});

		if (name != undefined) {
			this.store[this.store.length - 1].name = name;
		}
	};

	
	fhSelectable.prototype.unsubscribe = function(elem) {
		for (var i = 0, n = this.selected.length; i < n; ++i) {
			this.selected[i].select.remove();
			this.selected[i].container.remove();
			this.store.splice(i, 1);
		}
	
		this.selected = [];
	};

	
	fhSelectable.prototype.reindex = function(index) {
		var i, n, store = this.store;
		
		for (i = index, n = store.length; i < n; i++) {
			store[i].index = i;
		}
	}
	
	
	fhSelectable.prototype.getSelected = function() {
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
	
	
	fhSelectable.prototype.select = function(elem) {
		var selected = this.selected;
		
		if (!elem) {
			return;
		}

		$(elem.select).show();
		selected[elem.index] = elem;
	};

	
	fhSelectable.prototype.unselect = function(elem) {
		var selected = this.selected;
		
		if (!elem) {
			return;
		}
		
		$(elem.select).hide();
		selected[elem.index] = null;
	};
	
		
	fhSelectable.prototype.findInStore = function(key, param) {
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
	
	fhSelectable.prototype.unselectAll = function() {
		var store = this.store,
			selected = this.selected;
		
		for (i = 0, n = store.length; i < n; ++i) {
			this.unselect(store[i]);			
		}
	};
	
	// выбранный развыделяется, невыделенный выделяется.
	fhSelectable.prototype.toggle = function(elem) {
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

	if (window.fhSelectable == undefined) {
		window.fhSelectable = function(div) {
			return new fhSelectable(div);
		}
	} else {
		console.error('Error has occured; Can\' create constructor (fhSelectable)');
	}

})();
