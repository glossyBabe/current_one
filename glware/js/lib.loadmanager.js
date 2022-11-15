;(function($) {
	function glwLoadManager(settings) {
		
		this.loadId = false;

		this.queue = [];
		this.currentPopup = false;
		this.popupIsActive = false;
	
		this.checkInterval = 200;
		this.timeoutStamp = 0;

		this.getFilesCount = function() {
			var count = 0;
			for (var fname in this.queue.files) {
				count++;	
			}	
			return count;
		}

		this.getGalleryIMGCount = function() {
			var count = 0;
			for (var i = 0, n = this.queue.gallery.length; i < n; ++i) {
				count++;
			}
			return count;
		}
		
		this.showPopup = function(title, msg) {
			var div = "";

			if (!this.currentPopup) {
				div = "<div class='message'><h2 class='glw_loader_header' style='color: white;'><img src='/assets/template/images/loader.gif' width='30' height='30' />" + title + "</h2>";
				div += "<p class='glw_loader_msg' style='color: white;'>" + msg + "</p></div>";

				this.currentPopup = $.fancybox.open(div);
				this.popupIsActive = true;
			}
		}

		this.closePopup = function() {
			if (this.popupIsActive) {
				this.currentPopup.close();	
				this.popupIsActive = false;
			}
		}


		this.purgeFakeName = function(fakeName) {
			return fakeName.replace('C:\\fakepath\\', '');
		}
	};

	glwLoadManager.prototype.setInitialQueue = function(q) {
		var goalList = "";
		
		if (q.form_loading != undefined && q.files != undefined && q.gallery != undefined) {
//			console.log("got queue for load manager: ", q);
			this.queue = q;

			this.galIMGCountInit = this.getGalleryIMGCount();
			this.otherFilesCountInit = this.getFilesCount();

			goalList = "<ul>";
			goalList += "<li><span id='loader_task_form_loading'><strong>Загрузка формы</strong></span></li>";
			goalList += "<li><span id='loader_task_files'>Загрузка файлов галереи [<span id='gal_images_count'>" + 0 + "</span>/" + this.galIMGCountInit + "]</span></li>";
			goalList += "<li><span id='loader_task_gallery'>Загрузка прочих файлов [<span id='other_files_count'>" + 0 + "</span>/" + this.otherFilesCountInit + "]</span></li></ul>";

			this.currentPopup.current.$content.find(".glw_loader_msg").html(goalList);
		}

	}
		
//      '<div class="message"><h2 style="color: white;">Спасибо!</h2><h4 style="color: white;">Сообщение отправлено!</h4></div>'
	glwLoadManager.prototype.handle = function(args) {
		if (this.currentPopup.current == undefined) {
			this.closePopup();
			this.popupIsActive = false;
		}

		if (args.state == "finish") {
			$.fancybox.close();
		} else {
			if (!this.popupIsActive) {
				// build new popup
				var title, msg, fileName;

				switch (args.operation) {
					case "LOCAL_FILE":
						title = "Загрузка файла";
						msg = "Загружаем " + this.purgeFakeName(args.object) + "...";
						break;

					case "DOWNLOAD_PROFILE":
						title = "Загрузка профиля";
						msg = "Загружаем данные анкеты...";
						break;

					case "UPLOAD_PROFILE":
						title = "Сохранение формы";
						break;

					case "EXTRA":
						title = "Экстренное сообщение";
						msg = args;
				}

				this.showPopup(title, msg);

			} else {
				switch (args.operation) {
					case "DOWNLOAD_PROFILE":
						var galImgCount, filesCount,
							curContent = "", tmp = false;

						this.queue = args.object;

						galImgCount = this.galIMGCountInit - this.getGalleryIMGCount(),
						filesCount = this.otherFilesCountInit - this.getFilesCount();

						tmp = this.currentPopup.current.$content.find(".glw_loader_msg");
						tmp.find('span#gal_images_count').text(galImgCount);
						tmp.find('span#other_files_count').text(filesCount);

//						console.log("queue count: ", filesCount, this.otherFilesCountInit);

/*
						if (galImgCount == this.galIMGCountInit) {
							tmp.find("span#loader_task_gallery").css("font-weight", "bold");
						}
	
						if (filesCount == this.otherFilesCountInit) {
							tmp.find("span#loader_task_files").css("font-weight", "bold");
						}
*/
	
						if (galImgCount == this.galIMGCountInit && filesCount == this.otherFilesCountInit) {
							this.finishSync();
						}

						break;

					case "UPLOAD_PROFILE":
		
						break;

					case "ERROR_NOT_RESPOND":
						var header, msg;

						header = this.currentPopup.current.$content.find(".glw_loader_header");
						msg = this.currentPopup.current.$content.find(".glw_loader_msg");
						header.text("ОШИБКА");
						msg.text("Сервер перестал отвечать. Возможно, загрузка была завершена некорректно.");
	
						break;

					case "ERROR_NOT_FOUND":
						var header, msg;
	
						header = this.currentPopup.current.$content.find(".glw_loader_header");
						msg = this.currentPopup.current.$content.find(".glw_loader_msg");
						header.text("ОШИБКА");
						msg.text("Некоторые файлы не были найдены. Попробуйте загрузить их снова.");
						
				}
			}
		}
	}

	
	glwLoadManager.prototype.finishSync = function(error) {
		if (this.syncTimer) {
			clearInterval(this.syncTimer);
		}

		if (this.timeoutStamp) {
			clearTimeout(this.timeoutStamp);
		}

		if (!error) {
			this.closePopup();
		} else {
			this.handle({operation: error, object: false});
		}
	}


	glwLoadManager.prototype.setPingTimeout = function(period) {
		var that = this;

		if (!this.timeoutStamp) {
			this.timeoutStamp = setTimeout(function() {
				that.finishSync("ERROR_NOT_RESPOND");
			}, period);
		}
	}


	glwLoadManager.prototype.sync = function(loadId, opType) {
		var textData = "", that = this
			counter = 0;

		if (!this.loadId) {
			this.loadId = "/third_party/glware/" + loadId;
		}

		this.syncTimer = setInterval(function() {
			// get info from remote file
			that.setPingTimeout(20000);

			$.ajax({
				cache: false,
				url: that.loadId,
				success: function(response) {
					if (!response) {
						clearInterval(that.syncTimer);
						return;
					}

					try {
						// dirty hack because reducing queue file just filled with nulls.
						response = response.replace('\0', '');
						textData = JSON.parse(response);

						if (textData.loading_status == "OK") {
							that.handle({operation: opType, object: textData});
						} else {
							that.finishSync("ERROR_" + textData.loading_status);
						}

					} catch (e) {

					}

					if (!that.popupIsActive) {
						that.finishSync();
					}
				}
			});

		}, this.checkInterval);
		
	}


	glwLoadManager.prototype.stop = function() {

	}


	if (window.glwLoadManager == undefined) {
		window.glwLoadManager = function(settings) {
			return new glwLoadManager(settings);
		};
	} else {
		console.error("Error has occured; Can't create constructor (glwLoadManager)");
	}
})(jQuery);
