;(function($, w) {
	function Chatbox(options) {
		/* p - panel, f - field, b - button */
		this.textF			= $('.' + options.textField || null),
		this.usersP			= $('.' + options.userList || null),
		this.messagesP 		= $('.' + options.messagesBlock || null),
		this.enablerF		= $('.' + options.enabler || null),
		this.sendB			= $('.' + options.sendButton || null),
		this.nicknameF		= $('.' + options.nameField || null),

		this.someControlLost = (this.TextF.length == 0 || this.usersP.length == 0 ||
				this.messagesP.length == 0 || this.enablerF.length == 0 || this.sendB.length == 0 || nicknameF.length == 0),

		if (someControlLost) {			
			console.log({
				text: this.textF,
				userlist: this.usersP,
				msgs: this.messagesP,
				onliner: this.enablerF,
				sender: this.sendB,
				namef: this.nicknameF
				});
			throw new Error('Chatbox: required html-parts were not found;\n');
		}

	}

	Chatbox.prototyp.read = function(block) {
		switch (block) {
			case 'text': this.textF.val();
				break;
			case 'connectionStatus': this.enablerF.val();
				break;
		}

	}



	Chatbox.prototype.put = function(block, data) {
		switch (block) {
			case 'nickname': this.nicknameF.text(data);
				break;
			case 'connectionStatus': this.enablerF.val(data);



		}
	}

	Chatbox.prototype.lockNickname = function() {
		this.nicknameF.attr('disabled', true);
	}

	Chatbox.prototype.unlockNickname = function() {
		this.nicknameF.attr('disabled', false);
	}

	w.scGetChatbox = function(op){new Chatbox(op);};

})(jQuery, window);
