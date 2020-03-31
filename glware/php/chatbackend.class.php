<?php
	class glwChatBackend {
		private $modx;
		private $god_object;

		public __construct(&$god_object) {
			$this->god_object = $god_object;
			$this->modx = $this->god_object->modx;


		}
	
		public run() {
			$this->chatServer->run($this->command);
		}
	}
