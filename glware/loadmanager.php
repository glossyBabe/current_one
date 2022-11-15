<?php

class LoadQueue {
	private $id;
	private $fh;
	private $glo;
	private $path;
	private $queue;

	public __construct($god_object, $id) {
		$this->glo = $god_object;
		$this->path = dirname($this->glo->work_dir);

		if (!empty($arg)) {
			$this->id = $id;
		}

	}


	public function get_id() {
		return $this->id;
	}


	public function get_queue() {
		return $this->queue;
	}


	public function add_task($category, $ftype, $fileName) {
		if ($category) {
			if (!array_key_exists($category, $this->queue)) {
				$this->queue[$category] = array();
			}
	
			$this->queue[$category][] = $fileName;
		} else {
			$this->queue[$ftype] = $fileName;
		}
	}


	public function done($category, $fname) {
		if (!$this->queue) {
			return;
		}

		$modified = false;

		if ($category && $fname) {
			if ($key = array_search($fname, $this->queue[$category])) {
				unset($this->queue[$category][$fname]);
				$modified = true;
			}	
		} else if ($category && !$fname) {
			if (array_key_exists($category, $this->queue)) {
				$this->queue[$category] = array();
				$modified = true;
			}
		} else if (!$category && $fname) {
			if ($key = array_search($fname, $this->queue)) {
				unset($this->queue[$fname]);	
				$modified = true;
			}
		}

		if ($modified) {
			$this->save_queue();
			$this->glo->log("Status (old): filesize after \"opening\" is " . filesize($this->filename));
		}
	}
/*
	function done($ftype, $name = "", $subarray = "files") {
		if (!$name && $subarray == "files") {
			if (array_key_exists($ftype, $this->queue)) {
				$this->queue[$ftype] = array();
			}
		} else {
			$parts = explode("-", $name);
			$transformed_name = trim(array_pop($parts));
			$parts = explode(".", $transformed_name);
			$ext = array_pop($parts);
			$name_before_ext = implode(".", $parts);
			$transformed_name = $name_before_ext . "^" . strtolower($ext);
			
			$this->glo->log("Finished " . $subarray . " -> " . $transformed_name . " and type: " . $ftype . ": " . print_r($this->queue[$subarray], true));

			if ($ftype) {
				if (array_key_exists($ftype, $this->queue[$subarray])) {
					unset($this->queue[$subarray][$ftype]);
				}
			} else {
				if (strstr($name, "s_")) {
					$transformed_name = substr($name, 2);
				}
				$pos = array_search($transformed_name, $this->queue[$subarray]);
				$this->glo->log("Finished position: " . $pos . "; " . $transformed_name . " was searched");
				unset($this->queue[$subarray][$pos]);
			}
		}

		$this->save_queue();
		$this->glo->log("Status (old): filesize after \"opening\" is " . filesize($this->filename));
	}
*/


	public function finish() {
		$this->glo->log("LAST STATE OF QUEUE: " . print_r($this->queue, true));
		$gallery = $this->queue["gallery"];
		unset($this->queue["gallery"]);
		$files = $this->queue;

		if (count($files) || count($gallery)) {
			$this->queue["loading_status"] = "NOT_FOUND";
			$this->queue["gallery"] = array();
			$this->queue["files"] = array();

			$this->save_queue();
		}

	}


	public function abort() {

	}


	public function get_error() {

	}


	public function get_queue_as_json() {
		return trim(json_encode($this->queue), "\0");
	}


	public function restore() {
		$this->fh = fopen($this->path . "/LOADERDATA_" . $this->id, "c+");
		$this->glo->log("Status (old): " . $old . "; before init filesize of " . $this->filename . " is: " . filesize($this->filename));
		$temp = fgets($this->fh);
		$this->glo->log("Status (old): " . $old . "; after init queue is: " . $temp);
		$this->queue = json_decode(trim($temp, "\0"), true);
	}


	public function save() {
		if (!$this->fh) {
			$this->fh = fopen($this->path . "LOADERDATA_" . $this->id, "w");
		}

		if (is_writable(dirname($this->glo->work_dir))) {
			ftruncate($this->fh, 0);
			rewind($this->fh);
			fwrite($this->fh, $this->get_queue_as_json());
			fflush($this->fh);
		} else {
			$this->glo->log("Not writable loadmanager store");
		}
	}


	function __destruct() {
		$res = fclose($this->fh);
	}
}
