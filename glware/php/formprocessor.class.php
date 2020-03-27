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

				case 'request_table':
					$output = array(
						array('name' => 'Конкурсант 1', 'anket' => false, 'presentation' => false, 'press' => false, 'manager_photo' => 'dsf', 'gallery' => 'sdf', 'logo' => 'sdf'),
						array('name' => 'Конкурсант 2', 'anket' => 'sdf', 'presentation' => 'df', 'press' => 'dsf', 'manager_photo' => 'dsf', 'gallery' => 'sdf', 'logo' => 'sdf'),
						array('name' => 'Конкурсант 3', 'anket' => false, 'presentation' => false, 'press' => false, 'manager_photo' => false, 'gallery' => false, 'logo' => false)
					);
					break;

				case 'judges_activity':
					$output = array(
						array('judge_name' => 'Судья 1', 'person' => 'Борец Виктор Астафьевич', 'voted' => false),
						array('judge_name' => 'Судья 2', 'person' => 'Николай Скороходов', 'voted' => true),
						array('judge_name' => 'Судья 3', 'person' => 'Пантон Саврасов', 'voted' => ''),
						array('judge_name' => 'Судья 4', 'person' => 'Геннадий Старопорохов', 'voted' => true),
						array('judge_name' => 'Судья 5', 'person' => '', 'voted' => true)
					);
					break;

				case 'voting_summary':
					$output = array(
						array(
							'info' => 'Номинация 1',
							'children' => array(
								array('name' => 'Конкурсант 2', 'score' => 8, 'voters' => 'Судья 1, Судья 4, Судья 5'),
								array('name' => 'Конкурсант 5', 'score' => 4, 'voters' => 'Судья 2, Судья 3, Судья 7'),
								array('name' => 'Конкурсант 6', 'score' => 2, 'voters' => 'Судья 6, Судья 8')
							)
						),
						array(
							'info' => 'Номинация 2',
							'children' => array(
								array('name' => 'Конкурсант 1', 'score' => 7, 'voters' => 'Судья 1, Судья 2, Судья 5'),
								array('name' => 'Конкурсант 4', 'score' => 1, 'voters' => 'Судья 2, Судья 5'),
								array('name' => 'Конкурсант 5', 'score' => 1, 'voters' => 'Судья 8')
							)
						)
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
