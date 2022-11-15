<?php
	class glwVoteProcessor {
		
		public function __construct($glw) {
			$this->glo = $glw;
			$this->modx = $this->glo->modx;
			$this->me = $this->modx->getUser();
			$this->rp =& $this->glo->requestprocessor;
		}


		public function is_member($user, $grp) {
			return $this->glo->is_member($user, $grp);
		}


		public function clean_all_data() {
			$tp = $this->glo->table_prefix;
			$this->modx->query("DELETE FROM {$tp}voting_results");
			$this->modx->query("DELETE FROM {$tp}voting_process");
		}


		public function get_judge_voting_table() {
			$tp = $this->glo->table_prefix;
			$cur_id = $this->modx->getUser()->get('id');

			$in = $this->rp->get_user_last_requests();
			$nominations = array();
			$already_voted = $this->vote_already_registered();

			if (!$already_voted && empty($in)) {
				return;
			}

			$sql = $already_voted
				? "SELECT fr.*, vres.*, nl.*, pr.judge_id, pr.person_of_year FROM {$tp}user_formit_request as fr
					LEFT JOIN {$tp}voting_results as vres ON vres.request_id = fr.id
					LEFT JOIN {$tp}nomination_list as nl ON vres.nomination_id = nl.id
					LEFT JOIN {$tp}voting_process as pr ON vres.process_id = pr.id
					WHERE pr.judge_id = {$cur_id}"

				: "SELECT fr.*, anl.*, nl.* FROM {$tp}user_formit_request as fr
					LEFT JOIN {$tp}attendee_nomination_links as anl ON anl.request_id = fr.id
					LEFT JOIN {$tp}nomination_list as nl ON anl.nomination_id = nl.id
					WHERE " . (count($in) > 1 ? "anl.request_id IN (" . implode(',', $in) . ")" : "anl.request_id = " . array_pop($in));


			$output = array(
				"success" => true,
				"status" => $already_voted ? "voted" : "not_voted"
			);

			$result = $this->modx->query($sql);
			while (is_object($result) && $row = $result->fetch(PDO::FETCH_ASSOC)) {
				if (!isset($users_cache[$row['user_id']])) {
					$users_cache[$row['user_id']] = $this->modx->getObject('modUser', $row['user_id']);
				}

				if ($this->is_member($users_cache[$row['user_id']], 'Contestants')) {
					if (!$output["person_of_year"]) {
						$output['person_of_year'] = $row["person_of_year"];
					}

					if (!empty($row['code']) && !isset($nominations[$row['code']])) {
						$nominations[$row['code']] = array('code' => $row['code'], 'info' => $row['public_name'], 'nominants' => array());	
					}

					$nominations[$row['code']]['nominants'][] = array(
						'nominant_val' => $row['request_id'],
						'nominant_title' => $users_cache[$row['user_id']]->get('username'),
						'presentation_href' => !$already_voted ? $this->rp->get_user_file_link($row['user_id'], 'presentation_' . $row["code"]) : false
					);
				}
			}

			foreach ($nominations as $nomination) {
				$output[] = $nomination;
			}

			return $output;
		}


		public function get_judges_activity_table() {
			$tp = $this->glo->table_prefix;
			$year = date("Y", time());
			// get judges from users and AFTER that fetch voting process
			$judges = $this->glo->get_members('Judges');
			$voting_process = array();
			$output = array();
			
			$sql = "SELECT * FROM {$tp}voting_process
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

			return $output;
		}


		public function get_voting_summary_table() {
			$nominations = array();
			$tp = $this->glo->table_prefix;
			$judges = $this->glo->get_members('Judges');
			$contestants = $this->glo->get_members('Contestants');
			$year = date('Y', time());

			foreach ($contestants as $contestant) {
				$this->glo->log("Contestant: " . $contestant->get("username"));	
			}
			foreach ($judges as $judge_key => $judge) {
				$this->glo->log("Judge " . $judge_key . " (" . $judge->get("id") . "): " . $judge->get("username"));	
			}

			$sql = "SELECT nl.*, vr.*, vp.judge_id, fr.user_id FROM {$tp}voting_results as vr
						LEFT JOIN {$tp}nomination_list as nl ON nl.id = vr.nomination_id
						LEFT JOIN {$tp}voting_process as vp ON vp.id = vr.process_id
						LEFT JOIN {$tp}user_formit_request as fr ON fr.id = vr.request_id
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

				if (!($contestants[$row["user_id"]] instanceof modUser)) {
					$this->glo->log("Not found user " . $row["user_id"]);
					continue;
				}

				if (!($judges[$judge_id] instanceof modUser)) {
					$this->glo->log("not found judge " . $judge_id);
					continue;
				}

				if (!isset($nominations[$code])) {
					$nominations[$code] = array('info' => $public_name, 'children' => array());
					$nomination_link =& $nominations[$code];
				}


				if (!isset($nomination_link['children'][$request_id])) {
					$nomination_link['children'][$request_id] = array('score' => 0, 'voters' => array());
					$request_link =& $nomination_link['children'][$request_id];
				}

				if (is_null($contestants[$row['user_id']])) {
					$this->glo->log("Destructed id (group not foud?): " . $row['user_id']);

					continue;
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

			return $output;
		}


		public function vote_already_registered() {
			$id = $this->modx->getUser()->get('id');
			$sql = "SELECT * FROM {$this->glo->table_prefix}voting_process WHERE judge_id = {$id}";
			$result = $this->modx->query($sql);
			
			return (is_object($result) && $result->fetch(PDO::FETCH_ASSOC));	
		}


		public function vote() {
			$judge_id = $this->modx->getUser()->get('id');
			$tp = $this->glo->table_prefix;

			if ($this->vote_already_registered()) {
				return;
			}
			
			if ($this->is_member($judge_id, 'Judges')) {
				$year = date("Y", time());
				$pers_of_y = preg_replace("/[^-a-zа-я ]*/ui", '', $_POST['person_of_year']);
				$sql = "INSERT INTO {$tp}voting_process (judge_id, year, person_of_year) VALUES (
					{$judge_id}, '{$year}', '{$pers_of_y}'
				)";

				$this->glo->log("SQL query: " . $sql);

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
