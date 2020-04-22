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


		public function is_member($user, $group) {
			$result = false;

			if (!($user instanceof xPDO) && is_numeric($user)) {
				$user = $this->modx->getObject('modUser', intval($user));
			}

			$user_group = $this->modx->getObject('modUserGroup', $user->get('primary_group'));
			$primary_group = $user_group->get('name');
			$result = $primary_group == $group;

			return $result;
		}


		public function get_members($group_name) {
			$group = $this->modx->getObject('modUserGroup', array('name' => $group_name));
			$members = $this->modx->getCollection('modUser', array('primary_group' => $group->get('id')));
			$result = array();
		
			foreach ($members as $member) {
				$result[$member->get('id')] = $member;
			}

			return $result;
		}


		public function get_table($table_name) {
			$output = array();
			$tp = $this->god_object->table_prefix;

			switch ($table_name) {
				case 'judge_voting':

					if ($this->vote_already_registered()) {
						$output = array("success" => false, "message" => "Спасибо за участие в голсовании!");
					} else {

						$users_cache = array();
						$nominations = array();
						$result = $this->modx->query("SELECT max(id) as last_id FROM {$tp}user_formit_request GROUP BY user_id");
						$in = array();

						while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
							$in[] = $row['last_id'];
						}

						if (count($in)) {
							$where = count($in) > 1
								? "anl.request_id IN (" . implode(',', $in) . ")"
								: "anl.request_id = " . array_pop($in);

							$result = $this->modx->query("SELECT fr.*, anl.*, nl.* FROM {$tp}user_formit_request as fr
									LEFT JOIN {$tp}attendee_nomination_links as anl ON anl.request_id = fr.id
									LEFT JOIN {$tp}nomination_list as nl ON anl.nomination_id = nl.id
									WHERE {$where}");

							while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
								if (!isset($users_cache[$row['user_id']])) {
									$users_cache[$row['user_id']] = $this->modx->getObject('modUser', $row['user_id']);
								}

								if ($this->is_member($users_cache[$row['user_id']], 'Contestants')) {
									if (!empty($row['code']) && !isset($nominations[$row['code']])) {
										$nominations[$row['code']] = array('code' => $row['code'], 'info' => $row['public_name'], 'nominants' => array());
									}

									$nominations[$row['code']]['nominants'][] = array('nominant_val' => $row['request_id'], 'nominant_title' => $users_cache[$row['user_id']]->get('username'));
								}
							}

							foreach ($nominations as $nomination) {
								$output[] = $nomination;
							}
						}				
					}

					break;

				case 'request_table':
					$output = array(
						array('name' => 'Конкурсант 1', 'anket' => false, 'presentation' => false, 'press' => false, 'manager_photo' => 'dsf', 'gallery' => 'sdf', 'logo' => 'sdf'),
						array('name' => 'Конкурсант 2', 'anket' => 'sdf', 'presentation' => 'df', 'press' => 'dsf', 'manager_photo' => 'dsf', 'gallery' => 'sdf', 'logo' => 'sdf'),
						array('name' => 'Конкурсант 3', 'anket' => false, 'presentation' => false, 'press' => false, 'manager_photo' => false, 'gallery' => false, 'logo' => false)
					);
					break;

				case 'judges_activity':
					$year = date("Y", time());
					// get judges from users and AFTER that fetch voting process
					$judges = $this->get_members('Judges');
					$voting_process = array();
					$output = array();
					
					$sql = "SELECT * FROM {$this->god_object->table_prefix}voting_process
							WHERE year = '{$year}'";
					$res = $this->modx->query($sql);

					while (is_object($res) && $row = $res->fetch(PDO::FETCH_ASSOC)) {
						$voting_process[$row['judge_id']] = $row;
					}

					foreach ($judges as $current_id => $judge) {
						$person_of_year = isset($voting_process[$current_id]) ? $voting_process[$current_id]['person_of_year'] : '';
						$voted = isset($voting_process[$current_id]);
						$output[] = array('judge_name' => $judge->get('username'), 'person' => $person_of_year, 'voted' => $voted);
					}
					break;

				case 'voting_summary':
					$nominations = array();
					$judges = $this->get_members('Judges');
					$contestants = $this->get_members('Contestants');
					$year = date('Y', time());

					$sql = "SELECT nl.*, vr.*, vp.judge_id, fr.user_id FROM {$this->god_object->table_prefix}voting_results as vr
								LEFT JOIN {$this->god_object->table_prefix}nomination_list as nl ON nl.id = vr.nomination_id
								LEFT JOIN {$this->god_object->table_prefix}voting_process as vp ON vp.id = vr.process_id
								LEFT JOIN {$this->god_object->table_prefix}user_formit_request as fr ON fr.id = vr.request_id
								WHERE vp.year = '{$year}' ORDER BY nomination_id ASC";

					$res = $this->modx->query($sql);
					$nomination_link = false;
					$request_link = false;

					while (is_object($res) && $row = $res->fetch(PDO::FETCH_ASSOC)) {
						$id = $row['id'];
						$code = $row['code'];
						$public_name = $row['public_name'];
						$request_id = $row['request_id'];
						$judge_id = $row['judge_id'];

						if (!isset($nominations[$code])) {
							$nominations[$code] = array('info' => $public_name, 'children' => array());
							$nomination_link =& $nominations[$code];
						}


						if (!isset($nomination_link['children'][$request_id])) {
							$nomination_link['children'][$request_id] = array('score' => 0, 'voters' => array());
							$request_link =& $nomination_link['children'][$request_id];
						}

						$request_link['name'] = $contestants[$row['user_id']]->get('username');
						$request_link['score']++;
						$request_link['voters'][] = $judges[$judge_id]->get('username');
					}

					foreach ($nominations as $nomination) {
						$prepared_nomination = array('info' => $nomination['info'], 'children' => array());

						foreach ($nomination['children'] as $child) {
							$prepared_nomination['children'][] = array('name' => $child['name'], 'score' => $child['score'], 'voters' => implode(', ', $child['voters']));
						}
						
						$output[] = $prepared_nomination;
					}

					$zoutput = array(
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


		public function vote_already_registered() {
			$id = $this->modx->getUser()->get('id');
			$sql = "SELECT * FROM {$this->god_object->table_prefix}voting_process WHERE judge_id = {$id}";
			$result = $this->modx->query($sql);
			
			return (is_object($result) && $result->fetch(PDO::FETCH_ASSOC));	
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



		public function vote() {
			$judge_id = $this->modx->getUser()->get('id');
			$tp = $this->god_object->table_prefix;

			if ($this->vote_already_registered()) {
				return;
			}
			
			if ($this->is_member($judge_id, 'Judges')) {
				$year = date("Y", time());
				$pers_of_y = preg_replace("/[^-a-zа-я ]*/i", '', $_POST['person_of_year']);
				$sql = "INSERT INTO {$tp}voting_process (judge_id, year, person_of_year) VALUES (
					{$judge_id}, '{$year}', '{$pers_of_y}'
				)";

				$this->god_object->log("SQL query: " . $sql);

				if ($this->modx->query($sql)) {
					$process_id = $this->modx->lastInsertId();
					$contestant_in_nominations = array();
					$where_clause = array();
					
					foreach ($_POST as $key => $val) {
						if (strstr($key, 'nomination_')) {
							$where_clause[] = "code = '" . str_replace('nomination_', '', $key) . "'";
						}
					}

					$where = count($where_clause) > 1 ? implode(' OR ', $where_clause) : array_pop($where_clause);
					$sql = "SELECT * FROM {$tp}nomination_list WHERE {$where}";
					$res = $this->modx->query($sql);

					$sql = "INSERT INTO {$tp}voting_results (process_id, request_id, nomination_id) VALUES ";
					$values = array();

					while (is_object($res) && $row = $res->fetch(PDO::FETCH_ASSOC)) {
						$nomination_id = intval($row['id']);
						if (!$nomination_id) {
							continue;
						}

						$post_name = 'nomination_' . $row['code'];

						for ($i = 0, $n = count($_POST[$post_name]); $i < $n; ++$i) {
							$values[] = "({$process_id}, " . intval($_POST[$post_name][$i]) . ", {$nomination_id})"; 
						}
					}

					$this->modx->query($sql . implode(',', $values));
				}
			}
		}
	}
