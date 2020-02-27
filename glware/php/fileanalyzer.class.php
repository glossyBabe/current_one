<?php
	class glwFileAnalyzer {

		public function __construct() {
			$this->errors = array(
				'class is under construction'
			);	
		}

		public function validate() {

		}

		public function get_errors() {
			return $this->errors;	
		}
	}
