(function($) {
	
console.log('Изменения повторно получены');
	// инициализация, функции передаются настройки (дивы, куда выводить информацию и кнопки, на нажатия которых реагировать),
	// прикрепляются события кнопки отправить, войти в сеть.
	$.fn['sillychat'] = function(options) {

		//css-классы контролов
		var textField     = '.' + options.textField     || null,
			userList      = '.' + options.userList      || null,
			messagesBlock = '.' + options.messagesBlock || null,
			enabler       = '.' + options.enabler       || null,
			sendButton    = '.' + options.sendButton    || null,
			nameField     = '.' + options.nameField     || null,

		//ссылки на контролы
			controlTextField      = $(textField),
			controlUserList       = $(userList),
			controlMessagesPanel  = $(messagesBlock),
			controlEnabler        = $(enabler),
			controlSendButton     = $(sendButton),
			controlNameField      = $(nameField),

		//служебные
			requestedName   = null,
			nick            = null,
			session         = {},
			sessionExists   = false,
			queue           = [],
			timeout         = 2500,
			phpPath         = '/third_party/glware/ajhandler.php',
			
			someControlLost = (controlTextField.length == 0 || controlUserList.length == 0 ||
				controlMessagesPanel.length == 0 || controlEnabler.length == 0 || controlSendButton.length == 0 ||
				controlNameField.length == 0),
			online = null;
			
			var modxName = $('h4.username_for_chat').text();
			controlNameField.val(modxName);

		if (someControlLost) {			
			console.log({
				text: controlTextField,
				userlist: controlUserList,
				msgs: controlMessagesPanel,
				onliner: controlEnabler,
				sender: controlSendButton,
				namef: controlNameField
				});
			throw new Error('Can\'t run the app: required html-parts were not found;\n');
		}


		// коллбеки для обработки событий
		var callbacks = {
			colorize: function() {
				//console.log(controlColors.filter('input:checked').val());

			},

			send: function() {
				var text = controlTextField.val();
				if (online && sessionExists && text != '') {
					putMessageQueue(text);
				}
			},

			netStatusChange: function() {
				switch (controlEnabler.val()) {
					case 'offline':
						//console.log(session);

						destroySession();						

						break;

					case 'online':
						//console.log(session);

						openSession();
						
				}
			},

			sessionCreateReact: function(data) {
				if (!online) {
					return;
				}

				var decoded = JSON.parse(data);
				
				if (decoded.got) {
					
					requestedName = null;
					controlNameField.attr('disabled', true);
					controlNameField.val(decoded.you);

					nick              = decoded.you;
					sessionExists     = true;
					session['create'] = false;
					controlEnabler.val('online');

					callbacks.refresh(decoded);
				} else {
					clearInterval(online);
					online = null;
					controlNameField.attr('disabled', false);
					
					putMessageLocal({
						text: 'Нет соединения. Для того, чтобы войти в чат, введите имя и нажмите Enter.',
						nick: 'System',
						date: getChatTime()
					}); // error, need name for connection
					sessionExists      = false;
					session['create']  = false;

				}
				
			},

			sessionDeleteReact: function(data) {
					var decoded = JSON.parse(data);		

					if (decoded.deleted) {
						controlNameField.attr('disabled', false);

						nick              = null;
						online            = null;
						sessionExists     = false;
						session['delete'] = false;
						controlEnabler.val('offline');
					}

			},

			refresh: function(serverdata) {
				if (serverdata.messages != null && serverdata.messages.length != 0) {
				
					for (var i = 0, n = serverdata.messages.length; i < n; ++i) {
						putMessageLocal({
							text: serverdata.messages[i]['message_text'],
							nick: serverdata.messages[i]['username'] == 'Guest' ? 'Guest' + serverdata.messages[i]['id'] : serverdata.messages[i]['username'],
							date: serverdata.messages[i]['date']
						});
					}

					controlMessagesPanel[0].scrollTop += i * 50;
				}

				if (serverdata.userlist != null) {
					var userlistStr = '';

					for (var i = 0, n = serverdata.userlist.length; i < n; ++i) {
						if (serverdata.userlist[i]['username'] == 'Guest') {
							var uniqueNick = serverdata.userlist[i]['username'] + serverdata.userlist[i]['id'];
						} else {
							var uniqueNick = serverdata.userlist[i]['username'];
						}

						isItYou = uniqueNick == serverdata.you;

						bold = isItYou ? '<b>' : '';
						boldEnd = isItYou ? '</b>' : '';

						userlistStr += '<div>' + bold + uniqueNick + boldEnd + '</div>';
					}					

					nick = serverdata.you;

					controlUserList.html(userlistStr);
				}
			}
		}

		controlEnabler.val('offline');
		controlEnabler.hide();

		//controlColors.on('change', callbacks.colorize);
		controlEnabler.on('change', callbacks.netStatusChange);
		controlSendButton.on('click', function(e) {
			e.preventDefault();
			callbacks.send();
		});
		
		openSession();

		controlNameField.on('keypress', function(e) {
			var code = e.keyCode || e.which;
			if (code == 13) {
				e.preventDefault();

				if (controlEnabler.val() == 'offline') {
					//console.log(session);
					console.log('activate!');
					controlEnabler.val('online');
					openSession();

				}
			}
		});
		
		controlTextField.on('keypress', function(e) {
			console.log('yes, key pressed');
			var code = e.keyCode || e.which;
			if (code == 13) {
				e.preventDefault();
				callbacks.send();
 			}
		});



		// после инициализации чат делает только одно действие, проверяет статус каждые 2-3 секунды.
		// распихивает информацию из джсон объекта по указанным в настройках элементам (пихает в один див, сообщения, в другой див список кто в сети и т.д.)
		
		// чат проверяет, что обновилось
		function ping() {			
			
			console.log(session['create']);

			console.log(session['delete']);
			if (session['create'] && !session['delete']) {
				$.post(phpPath, {

					glw_action: 'chat',
					wishname: requestedName,
					command: 'sessionstart'

				}, function(data) {
					callbacks.sessionCreateReact(data)
				});

				return;

			} else if (session['delete'] && !session['create']) {
				clearInterval(online);

				$.post(phpPath, {

					glw_action: 'chat',
					command: 'sessiondelete'

				}, function(data) {
					callbacks.sessionDeleteReact(data);
				});

				return;
			}

			if (queue.length > 0) {

				$.post(phpPath, {
					glw_action: 'chat',
					command: 'newmessages',
					messages: JSON.stringify(queue)

				}, function(data) {
					if (!online) {
						return;
					}

					callbacks.refresh(JSON.parse(data));
				});

				queue = [];
				return;
			}

			$.post(phpPath, {
				glw_action: 'chat',
				command:'refresh'
			}, function(data) {
				if (!online) {
					return;
				}

				callbacks.refresh(JSON.parse(data));
				//console.log(data);
			});
			
		}

		function getChatTime() {
			var d = new Date(),

			    h = d.getHours().toString(),
			    m = d.getMinutes().toString(),
			    s = d.getSeconds().toString(),

			    h = h.length < 2 ? '0' + h : h,
			    m = m.length < 2 ? '0' + m : m,
			    s = s.length < 2 ? '0' + s : s;

			return h + ':' + m + ':' + s;

		}

		function putMessageLocal(message) {
			if (message.date != '' && message.text != '' && message.nick) {
				/*controlMessagesPanel.append("<div class='sillychat_messagecontainer'>" +
					"<div class='sillychat_timeclass'>" + message.date + "</div>" +
					"<div class='sillychat_nickclass'>" + message.nick + "</div>" +
					"<div class='sillychat_messagetext'>" + message.text + "</div>" +
				"</div>");*/
				controlMessagesPanel.append("<div class='sillychat_messagecontainer'>" +
					'[' + message.date + '] ' + '<b>' + message.nick + '</b>: ' + message.text +
				"</div>");

				controlMessagesPanel[0].scrollTop += 7000;
			}
		}

		// отправка сообщения это помещение его в очередь, сетевой метод обнаруживает сообщения после очередной проверки и посылает их куда надо.
		function putMessageQueue(text) {
			if (online && sessionExists) {
				var time = getChatTime();

				message = {
					text: text,
					date: time,
					nick: nick
				};

				queue.push(message);

				controlTextField.val('');
				putMessageLocal(message);
			}
		}

		// войти в сеть
		function openSession() {
		
			if (!online) {
				if (controlNameField.val() != '') {
					requestedName = controlNameField.val();
					controlNameField.attr('disabled', true);
				}

				session['delete'] = false;
				session['create'] = true;
				
				online = setInterval(ping, timeout);
			} else {
				console.log('open session failed')
			}
		}

		// выйти из сети
		function destroySession() {
			queue = [];

			if (online) {				
				session['create'] = false;
				session['delete'] = true;
			}
		}
	};

})(jQuery);
