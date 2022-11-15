;(function() {
	
	function controlPanel(scheme) {

		this.config = {
			deleteBStyle: 'width: 20px; height: 20px; border-radius: 10px; margin: 5px 0; background-color: red; float: right; text-align: center; display:none;',
			errorBlockStyle: {backgroundcolor: 'pink', display: 'inline-block', margin: '2px', padding: '5px'}
		}

		this.loadB = scheme.loadB 			|| false;
		this.clicker = scheme.clicker		|| false;
		this.deleteB = scheme.deleteB		|| false;
		this.submitB = scheme.submitB		|| false;
		this.notify = scheme.notify			|| false;
		this.previewPanel = scheme.gallery	|| false;
		
		if (!this.deleteB) {
			this.makeDefaultDeleteButton();
		}
	}

	controlPanel.prototype.makeDefaultDeleteButton = function() {
		var del = document.createElement('div'),
			a = document.createElement('a');

		a.setAttribute('class', 'cr-icon glyphicon glyphicon-remove');
		a.setAttribute('style', 'color: white;');
		a.href = '';

		del.setAttribute('style', this.config.deleteBStyle);
		del.setAttribute('data-action', 'remove');
		del.appendChild(a);

		this.deleteB = $(this.previewPanel[0].appendChild(del));
	}

	controlPanel.prototype.isFileSelected() {
		return !!this.loadB.val();
	}

	controlPanel.prototype.loadBReset = function() {	
		this.loadB.val('');
	}
	
	controlPanel.prototype.submit = function() {
		this.submit.click();
	}

	controlPanel.prototype.setLoadButtonReaction(func) {
		this.loadB.on("click", func);
	}

	controlPanel.prototype.displayError = function() {
		var msgBlock = false,
			msg = this.loader.getError();

		if (this.notify) {
			if (msg) {
				$(this.notify).append($("<div>" + msg + "</div>").css(this.config.errorBlockStyle));
			} else {
				console.log("notify zero: ", this.notify, "id: ", this.id);
				this.notify[0].innerHTML = "";	
			}
		} else {
			console.error(msg);
		}
	}

})();
