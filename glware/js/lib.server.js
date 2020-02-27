;(function(){
	function fhServer(settings) {
		this.settings = {
			formSelector: settings.formSelector || 'form',
		};
		this.iframe = false;
		this.form = $(this.settings.formSelector)[0];
		this.formHandlers = {
			'file': '/third_party/glware/ajhandler.php?glw_action=load',
			'default': this.form.getAttribute('action')
		};
		this.answer = '';
		this.commandFlag = $(this.form).find('input[name^=command]');
		
		this.parseDefaultAnswer = function(cb) {
			var frstMarker, lstMarker, content,
				start, end, mode, 
				response = {images: [], errors: []},
				answer = this.answer;

			// maybe better to use json
			if (answer.search(/\[loaded\]/) != -1) {
				mode  = 'load';
				start = new RegExp('\\[loaded\\]');
				end   = new RegExp('\\[\\/loaded\\]');
			} else if (this.answer.search(/\[deleted\]/) != -1) {
				mode  = 'delete';
				start = new RegExp('\\[deleted\\]');
				end   = new RegExp('\\[\\/deleted\\]');
			} else {
				console.error('fileHandler: can\t parse server answer.');
				return;
			}
			
			console.log(mode, 'режим');
			
			frstMarker  = answer.search(start) - 1;
			answer      = answer.replace(start, '');
			lstMarker   = answer.search(end);
			answer      = answer.replace(end, '');
			content     = answer.substring(frstMarker, lstMarker);
			parts       = (content.search(/\|/) != -1) ? content.split('|') : [content];

			if (parts.length > 1) {
				parts.shift();
			}

			for (var i = 0, n = parts.length; i < n; ++i) {
				if (!parts[i]) {
					continue;
				}
				category = mode == 'deleted' ? 'deleted' : 'loaded';

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

		this.formSwitch = function(mode) {

		}


		// create iframe for data exchanging with backend
		this.iframe = $('iframe[name^=fileserver]');
		if (this.iframe.length == 0) {
			var iframe = document.createElement('iframe');
			iframe.name = 'fileserver';
			iframe.style = 'display:none;';
			this.iframe = $(document.body.appendChild(iframe));
		}
	}

	fhServer.prototype.listen = function(cb) {
		var server = this,
			answ, loaded, parts, ifr, i, n, t;

		// here we use 2 form handlers for different purposes
		this.form.setAttribute('action', this.formHandlers['file']);
		this.form.setAttribute('target', 'fileserver');
		this.form.setAttribute('enctype', 'multipart/form-data');
		this.commandFlag.val('files');

		if (this.iframe.length) {
			t = setInterval(function() {
				console.log('sending now...');
				answer = server.iframe.contents().find('body').text();
					
				if (answer.length > 1) {
					server.answer = answer;
					server.form.setAttribute('action', server.formHandlers['default']);
					server.form.setAttribute('target', '_self');
					server.form.setAttribute('enctype', 'application/x-www-form-urlencoded');
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
