;(function($){
	if ($ != undefined) {
		$(function() {
			var popupTemplate = "",
				loadFileStatus = function(fileName) {
				if (fileName !== undefined && fileName) {
					//console.log("THis is loadManager:", window.glwLoadManager);
					window.loadManager.handle({operation: "LOCAL_FILE", object: fileName, state: 'load'});
				} else {
					window.loadManager.handle({object: 'last', state: 'finish'});
				}
			};

			function renderPresentationFL(destination, id) {
				var toReturn = {},
					elements = {
						simpleDiv: "div",
						small: "small",
						preview: "div",
						notify: "p",
						preloaded: "div",
						input: "input",
						delet: "input",
						clicker: "input",
						span: "span",
						par: "p"
					},
					lButton, clicker, notify, preview,
					dom = {},

					notifyChangeView = function(message) {
						if (message) {
							this.navPanel[0].className = "alert alert-danger";
							this.notify.append("<strong>Ошибка:</strong> " + message)
						} else {
							this.navPanel[0].className = "alert alert-info";
							this.notify[0].innerHTML = "";
						}
					},
					successChangeView = function(sname) {
						this.navPanel[0].className = "alert alert-success";
						this.navPanel.find("p.fileloader_info").fadeOut();
						this.navPanel.find("div.file_loader_preview").html(
						"Файл загружен: " + sname
						);
						this.controls.clicker.hide();
						this.controls.deleteButton.fadeIn();
					},
					deleteChangeView = function() {
						this.navPanel[0].className = "alert alert-info";
						this.navPanel.find("p.fileloader_info").fadeIn();
						this.navPanel.find("div.file_loader_preview").html(false);
						this.controls.clicker.fadeIn();
						this.controls.deleteButton.hide();
					};

				// рендерит нужный хтмл в destination, возвращает объект со ссылками в заданном формате для конструктора файллоадера
				for (var elName in elements) {
					dom[elName] = document.createElement(elements[elName]);
				}

				dom.small.textContent = "Загрузите файл презентации, формат файла должен быть PPTX или PDF, размер файла не больше 8 мегабайт.";
				dom.par.appendChild(dom.small);	
				dom.par.className = "fileloader_info";

				dom.simpleDiv.className = "alert alert-info";
				dom.role = "alert";

				dom.input.type = "file";
				dom.input.name = "_" + id;
				dom.input.style = "display:none;";

				dom.clicker.type = "button";
				dom.clicker.className = "form-control";
				dom.clicker.value = "Загрузить презентацию";

				dom.delet.type = "button";
				dom.delet.className = "form-control btn";
				dom.delet.value = "Удалить презентацию";
				dom.delet.style = "display:none;";

				dom.notify.id = "notify_" + id;
		
				dom.preview.className = "file_loader_preview";

				dom.preloaded.id = "preloaded_" + id;
				dom.preloaded.style = "display:none;";

				lButton = dom.simpleDiv.appendChild(dom.input);
				clicker = dom.simpleDiv.appendChild(dom.clicker);
				deleteButton = dom.simpleDiv.appendChild(dom.delet);
				notify = dom.simpleDiv.appendChild(dom.notify);

				preview = dom.simpleDiv.appendChild(dom.preview);
				dom.simpleDiv.appendChild(dom.par);
				
				destination.appendChild(dom.preloaded);
				navPanel = destination.appendChild(dom.simpleDiv);

				
				toReturn = {
					id: id,
					loadButton: $(lButton),
					clicker: $(clicker),
					submit: submit,
					notify: $(notify),
	
					navPanel: $(navPanel),
					
					deleteReact: hoverDelete,
					deleteButton: $(deleteButton),
					notifyCallback: notifyChangeView,
					loadFileCallback: loadFileStatus,

					store: {
						selectable: false,
						preview: false,
						limit: 1,
						preloadedId: "#preloaded_" + id,
						formInputName: id,
						directoryPath: '/third_party/glware/presentations/',
						gallery: $(preview),
						successLoadCallback: successChangeView,
						successDeleteCallback: deleteChangeView
						
					},
 					server: {
						setupFormCallback: formitSwitchForm	
					}
				};

				//console.log("send to fileloader constructor " + id + ":", toReturn);

				return toReturn;
			}

			function setFileLoaderFactoryEvents(templates) {
				
				templates.each(function(index) {
					var cb = $(this).parent().find("input[name^=" + $.escapeSelector("nominant[]") + "]"),
						cbId = $(this).attr("data-checkbox-id"),
						template = this,

						settings = {};

					$(cb).on("click", function(e) {
						//console.log("checkbox state is " + e.target.checked);
						console.log("PicLoader: ", picLoadersRegistry);
						if (e.target.checked == true) {
							if (picLoadersRegistry["presentation_" + cbId] !== undefined) {
								picLoadersRegistry["presentation_" + cbId].switchActivity(true);	
							} else {
								settings = renderPresentationFL(template, "presentation_" + cbId);
								picLoadersRegistry["presentation_" + cbId] = new fhFileLoader(settings);
							}

						} else {
							console.log("Not checked, so: ", cbId, e.target);
							picLoadersRegistry["presentation_" + cbId].switchActivity();
						}
					});
				});

			}

			var submit = $('input[type^=submit]'),
				//presentationLoad = $('input[name^=presentation]'),
				pressrelLoad = $('input[name^=_pressrelease]'),
				logoLoad = $('input[name^=_logo]'),
				photoLoad = $('input[name^=_photo]'),

				otherFileNotifyCB = function(message) {
					if (message) {
						this.navPanel[0].className = "alert alert-danger";
						this.notify.append("<strong>Ошибка:</strong> " + message)
					} else {
						this.navPanel[0].className = "";
						this.notify[0].innerHTML = "";
					}
				},
				otherFileSuccessCB = function(sname) {
					this.navPanel[0].className = "alert alert-success";
					this.navPanel.find("div.file_loader_preview").html(
					"Файл загружен: " + sname
					);
					this.controls.clicker.hide();
					this.controls.deleteButton.fadeIn();
				},
				imageSuccessCB = function(sname, imgElement) {
					this.navPanel[0].className = "alert alert-success";
					this.navPanel.find("div.file_loader_preview").append(imgElement);
					this.controls.clicker.hide();
					this.controls.deleteButton.fadeIn();
				},
				otherFileDeleteCB = function() {
					this.navPanel[0].className = "";
					this.navPanel.find("div.file_loader_preview").html(false);
					this.controls.clicker.fadeIn();
					this.controls.deleteButton.hide();
				};

				fileloaderPlaceholders = $("div.glw_presentation_fileloader"),

				hoverDelete = function() {

				},

				

				formitSwitchForm = function(form, mode) {
					if (mode == 'files') {
						$(form).removeClass('ajax_form');
					} else {
						$(form).addClass('ajax_form');
					}
				},

				pressSettings = {
					id: 'pressrel',
					controls: {
						loadButton: pressrelLoad,
						deleteButton: pressrelLoad.parent().find('input[name^=deleter]'),
						clicker: pressrelLoad.parent().find('input[name^=clicker]'),
						submit: submit,
						notify: $('p#notify_pressrelease'),
						gallery: pressrelLoad.parent().parent().find('.file_loader_preview')
					}

					wrapper: $('div#alertblock_pressrelease'), // используется только в каллбаках
					deleteReact: hoverDelete,	

					callbacks: {
						notifyCallback: otherFileNotifyCB,
						successLoadCallback: otherFileSuccessCB,
						successDeleteCallback: otherFileDeleteCB,
						setupFormCallback: formitSwitchForm
					}

					storeLimit: 1,
					displayPreview: false,
					preloadedId: "#preload_pressrelease",
					formInputName: 'pressrelease',
					directoryPath: '/third_party/glware/images_buffer/',
				},

				logoSettings = {
					id: 'logo',
					loadButton: logoLoad,
					clicker: logoLoad.parent().find('input[name^=clicker]'),
					submit: submit,
					notify: $('p#notify_logo'),

					navPanel: $("div#alertblock_logo"),

					deleteReact: hoverDelete,	
					deleteButton: logoLoad.parent().find("input[name^=deleter]"),
					notifyCallback: otherFileNotifyCB,
	
					store: {
						limit: 1,
						preview: 'pic',
						preloadedId: "#preload_logo",
						formInputName: 'logo',
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: logoLoad.parent().parent().find('.file_loader_preview'),
						successIMGLoad: imageSuccessCB,
						successDeleteCallback: otherFileDeleteCB
					},
					server: {
						setupFormCallback: formitSwitchForm
					}
				},

				photoSettings = {
					id: 'dir_photo',
					loadButton: photoLoad,
					clicker: photoLoad.parent().find('input[name^=clicker]'),
					submit: submit,
					notify: $('p#notify_photo'),
		
					navPanel: $("div#alertblock_photo"),

					deleteButton: photoLoad.parent().find("input[name^=deleter]"),
					deleteReact: hoverDelete,	

					store: {
						limit: 1,
						preview: 'pic',
						preloadedId: "#preload_photo",
						formInputName: 'photo',
						directoryPath: '/third_party/glware/images_buffer/',
						gallery: photoLoad.parent().parent().find('.file_loader_preview'),
						successIMGLoad: imageSuccessCB,
						successDeleteCallback: otherFileDeleteCB
					},
					server: {
						setupFormCallback: formitSwitchForm
					}
				},

				gallerySettings = {
					id: 'gallery',
					loadButton: $('input[name^=_pic]'),
					submit: $('input[type^=submit]'),
					clicker: $('input[name^=clicker]'),
					notify: $('div#notify'),
					//deleteButton: $('input[name^=delet]'),

					store: {
						preview: 'pic',
						formInputName: 'pic[]',
						directoryPath: '/third_party/glware/images_buffer/gallery/',
						gallery: $('#pictures')
					},
					server: {
						setupFormCallback: formitSwitchForm
					}
				};

			// static fileloaders for other files
			picLoadersRegistry = {
				gallery: new fhFileLoader(gallerySettings),
				logo: new fhFileLoader(logoSettings),
				pressrelease: new fhFileLoader(pressSettings),
				photo: new fhFileLoader(photoSettings)	
			};

			window.loadManager = window.glwLoadManager({});

			// fileloaders switches on when nominations selected
			setFileLoaderFactoryEvents($("div.glw_presentation_fileloader"));
		
			window.getPicloader = function(title) {
				if (picLoadersRegistry[title] != undefined) {
					return picLoadersRegistry[title];
				} else {
					console.log(picLoadersRegistry);
					return false;
				}
			}
		});
		
	} else {
		console.error('jQuery not found');
	}
})(jQuery);
