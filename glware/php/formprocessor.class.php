<?php
	class glwFormProcessor {
	
		private $modx;
		private $god_object;

		public function __construct(&$god_object) {
			if ($god_object instanceof glwGodObject) {
				$this->god_object = $god_object;
				$this->modx = $this->god_object->modx;
			}
			

		}





	}
