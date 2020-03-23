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

		public function get_table($table_name) {
			$output = array();

			switch ($table_name) {
				case 'judge_voting':
					$output = array(
						array(
							'nominants' => array('Участник 1', 'Участник 2', 'Участник 3', 'Участник 4', 'Участник 5'),
							'info' => 'Номинация 1'
						),
						array(
							'nominants' => array('Участник 1', 'Участник 2', 'Участник 3', 'Участник 4', 'Участник 5'),
							'info' => 'Номинация 2'
						),
						array(
							'nominants' => array('Участник 1', 'Участник 2', 'Участник 3', 'Участник 4', 'Участник 5'),
							'info' => 'Номинация 3'
						),
						array(
							'nominants' => array('Участник 1', 'Участник 2', 'Участник 3', 'Участник 4', 'Участник 5'),
							'info' => 'Номинация 4'
						)
					);
					break;

				case 'judge_activity':
					$output = array(

					);
					break;

				case 'attendee_list':
					$output = array(

					);
					break;

				case 'nominations':
					$output = array(

					);
			}
	
			return $output;
		}

		public function create_request() {
			$success = false;
			$valid_codes = array();
	
			$usr = $this->modx->getUser();
			$id = $usr->get('id');
			$tp = $this->god_object->table_prefix;
			$hash = $this->god_object->params['hash'];

			$nom = array_map(function($el) {
				return "'" . $el . "'";
			}, $this->god_object->params['nominations']);

			$this->god_object->log("nominations: " . print_r($nom, true));

			$res = $this->modx->query("INSERT INTO {$tp}user_formit_request (formit_hash, user_id, date) VALUES (
			'{$hash}', {$id}, " . time() . ")");
	
			if ($res) {

				$request_id = $this->modx->lastInsertId();
				$res = $this->modx->query("SELECT * FROM {$tp}nomination_list WHERE code IN (" . implode(',', $nom) . ")");
				$nom = array();
				if (is_object($res)) {
					while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
						$values[] = "({$request_id}, {$row['id']})";
						$nom[] = $row['code'];
					}

					$sql = "INSERT INTO {$tp}attendee_nomination_links (request_id, nomination_id) VALUES ";

					$this->god_object->log("Created the requst. Found following correct nominations in request: ".
					implode(',', $nom));
					$this->god_object->log("SQL: " . $sql . implode(',' , $values));

					$success = $this->modx->query($sql . implode(',', $values));
				}
			}
				
			if ($success) {
				$this->god_object->log("Successfuly created all nomination links. Preparng file routines");
				$this->god_object->fileuploader->_ya_upload();
			} else {
				// roll-back of transaction... kind of
				$this->modx->query("DELETE FROM {$tp}user_formit_request WHERE formit_hash = {$hash}");
			}
		}


		public function get_voters() {

		}


		public function vote() {

		}
	}
