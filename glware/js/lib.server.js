;(function(){
	function fhServer(settings) {
		this.settings = {
			formSelector: settings.formSelector || 'form',
		};
		this.callbacks = {
			'setupFormCallback': settings.setupFormCallback != undefined ? settings.setupFormCallback : false
		};


		this.loaderId = settings.loaderId || '';
		this.servername = this.loaderId + '_fileserver';

		this.iframe = false;
		this.form = $(this.settings.formSelector)[0];
		this.answer = '';
		this.commandFlag = $(this.form).find('input[name^=command]');

		this.setupForm = function(mode) {
			var ret = false,
				formRequisite = {
					'files': {
						'action': '/third_party/glware/ajhandler.php?glw_action=files',
						'target': this.servername,
						'enctype': 'multipart/form-data'
					},
					'default': {
						'action': this.form.getAttribute('action'),
						'target': '_self',
						'enctype': 'application/x-www-form-urlencoded'
					}
				};

			if (this.callbacks.setupFormCallback != undefined) {
				ret = this.callbacks.setupFormCallback.call(this, this.form, mode);
				if (ret != undefined && ret.action != undefined && ret.target != undefined && ret.enctype != undefined) {
					formRequisite = ret;
				}
			}
		
			this.form.setAttribute('action', formRequisite[mode]['action']);
			this.form.setAttribute('target', formRequisite[mode]['target']);
			this.form.setAttribute('enctype', formRequisite[mode]['enctype']);
		}
		
		this.parseDefaultAnswer = function(cb) {
			var frstMarker, lstMarker, content,
				start, end, mode, 
				response = {loaded: [], deleted: [], errors: []},
				answer = this.answer,
				formatJson = true;

			try {
				jsonAnswer = JSON.parse(answer);
			} catch (e) {
				formatJson = false;
			}

			if (formatJson) {
				for (var category in jsonAnswer) {
					parts = jsonAnswer[category];
				}
			} else {
				if (answer.search(/\[loaded\]/) != -1) {
					mode  = 'load';
					start = new RegExp('\\[loaded\\]');
					end   = new RegExp('\\[\\/loaded\\]');
				} else if (this.answer.search(/\[deleted\]/) != -1) {
					mode  = 'delete';
					start = new RegExp('\\[deleted\\]');
					end   = new RegExp('\\[\\/deleted\\]');
				} else {
					console.error('fileHandler: can\'t parse server answer.');
					return;
				}
				
				frstMarker  = answer.search(start) - 1;
				answer      = answer.replace(start, '');
				lstMarker   = answer.search(end);
				answer      = answer.replace(end, '');
				content     = answer.substring(frstMarker, lstMarker);
				parts       = (content.search(/\|/) != -1) ? content.split('|') : [content];
			}

			for (var i = 0, n = parts.length; i < n; ++i) {
				if (!parts[i]) {
					continue;
				}

				if (category == undefined || category == '') {
					category = mode == 'deleted' ? 'deleted' : 'loaded';
				}

				console.log('parsing server answer', parts);
				if (parts[i].search('error') == -1) {
					// img name transform вынесена в клиента читающего ответ, не забыть
					response[category].push(parts[i]);
				} else {
					response.errors.push(parts[i]);	
				}
			}

			if (cb != undefined) {
				cb(response);
			} else {
				console.log("FileHandler: Server got answer, but no callbacks was called");
			}
		}


		// create iframe for data exchanging with backend
		this.iframe = $('iframe[name^=' + this.servername + ']');
		if (this.iframe.length == 0) {
			var iframe = document.createElement('iframe');
			iframe.name = this.servername
			iframe.style = 'display:none;';
			this.iframe = $(document.body.appendChild(iframe));
		}
	}

	fhServer.prototype.listen = function(cb) {
		var server = this,
			answ, loaded, parts, ifr, i, n, t;

		// here we use 2 form handlers for different purposes
		this.setupForm('files');
		this.commandFlag.val('files');
		this.iframe.contents().find('body').html(false);

		if (this.iframe.length) {
			t = setInterval(function() {
				console.log('sending now...');
				answer = server.iframe.contents().find('body').text();
					
				if (answer.length > 1) {
					server.answer = answer;
					server.setupForm('default');
					server.commandFlag.val('');

					server.iframe.html(null);
					
					clearInterval(t);
					server.parseDefaultAnswer(cb);
				}
			}, 300);
		}
	}

	if (window.fhServer == undefined) {
		window.fhServer = function(settings) {
			return new fhServer(settings);
		}
	} else {
		console.error('Error has occured; Can\' create constructor (fhServer)');
	}
})();
