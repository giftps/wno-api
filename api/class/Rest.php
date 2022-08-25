<?php
session_start();

class Users
{
	private $host  = 'localhost';
	private $user  = 'root';
	private $password   = "";
	private $database  = "wno";

	// temp values
	private $is_admin;

	private $per_page = 100;
	// private $userTable = 'users';
	private $db = false;
	public function __construct()
	{
		if (!$this->db) {
			$conn = new mysqli($this->host, $this->user, $this->password, $this->database);
			if ($conn->connect_error) {
				die("Error failed to connect to MySQL: " . $conn->connect_error);
			} else {
				$this->db = $conn;
			}
		}
	}



	function login($userData)
	{
		$username = $userData["username"];
		$password = $userData["password"];

		$userQuery = "
			SELECT * 
			FROM users WHERE username = '$username'
			";
		$resultData = mysqli_query($this->db, $userQuery) or die(mysqli_error($this->db));
		$userData = array();
		while ($userRecord = mysqli_fetch_assoc($resultData)) {
			$userData = $userRecord;
			$password_hash = $userRecord['password'];
			$_SESSION['wn_mobile_idu'] = $userRecord['idu'];
		}

		if (password_verify($password, $password_hash)) {

			// return
			header('Content-Type: application/json');
			echo json_encode($userData);
		} else {
			header('Content-Type: application/json');
			echo json_encode("Incorrect Data");
		}
	}

	function loginAdmin($userData)
	{
		$username = $userData["username"];
		$password = $userData["password"];

		$userQuery = "
			SELECT * 
			FROM admin WHERE username = '$username'
			";
		$resultData = mysqli_query($this->db, $userQuery) or die(mysqli_error($this->db));
		$userData = array();
		while ($userRecord = mysqli_fetch_assoc($resultData)) {
			$userData = $userRecord;
			$password_hash = $userRecord['password'];
			// $_SESSION['wn_mobile_idu'] = $userRecord['is_admin'];
		}

		if (password_verify($password, $password_hash)) {
			// return
			header('Content-Type: application/json');
			echo json_encode($userData);
		} else {
			header('Content-Type: application/json');
			echo json_encode("Incorrect Data");
		}
	}

	function id()
	{
		return $_SESSION['wn_mobile_idu'];
	}

	function logedInUser()
	{
		$id = $this->id();
		$userQuery = "SELECT * FROM users WHERE idu = '$id'";
		$resultData = mysqli_query($this->db, $userQuery) or die(mysqli_error($this->db));
		header('Content-Type: application/json');
		// echo json_encode($resultData->fetch_assoc());
		return $resultData->fetch_assoc();
	}

	function generateSalt($length = 10)
	{
		$str = '';
		$salt_chars = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
		for ($i = 0; $i < $length; $i++) {
			$str .= $salt_chars[array_rand($salt_chars)];
		}
		return password_hash($str . time(), PASSWORD_DEFAULT);
	}

	function logOut()
	{
		$this->db->query(sprintf("UPDATE `users` SET `login_token` = '%s' WHERE `idu` = '%s'", $this->generateSalt(), $this->db->real_escape_string($this->id())));
		session_destroy();
	}

	function getSingleUser($id)
	{
		$userQuery = "SELECT * FROM users WHERE idu = '$id' ";
		$query = mysqli_query($this->db, $userQuery) or die(mysqli_error($this->db));

		header('Content-Type: application/json');
		// echo json_encode($query->fetch_assoc());
		return $query->fetch_assoc();
	}

	function getFriendsList($type = null)
	{
		// Type 0: Returns both confirmed and pending friendships
		// Type 1: Returns only confirmed friendships

		if ($type) {
			$status = "";
		} else {
			$status = "AND `status` = '1'";
		}
		// The query to select the friends list
		$query = sprintf(
			"SELECT `user2` as `friends` FROM `friendships` 
			WHERE `user1` = '%s' %s
			
			UNION ALL SELECT `user1` as `friends` FROM `friendships` 
            WHERE `user2` = '%s' %s

			ORDER BY `friends` ASC",
			$this->db->real_escape_string($this->id()),
			$status,
			$this->db->real_escape_string($this->id()),
			$status
		);

		// Run the query
		$result = $this->db->query($query) or die(mysqli_error($this->db));

		// The array to store the subscribed users
		$friends = array();
		while ($row = $result->fetch_assoc()) {
			$friends[] = $row['friends'];
		}

		// include self on the list
		// $friends = array_push($friends, "user_id");

		// Close the query
		$result->close();

		// Return the friends list (e.g: 13,22,19)
		// return implode(',', array_slice($friends, 0, 2000));

		// header('Content-Type: application/json');
		// echo json_encode($friends);
		return implode(',', $friends);
	}

	function getFeeds($start, $value = null, $from = null)
	{
		// From: Load posts starting with a certain ID
		$this->friends = $this->getFriendsList();
		// $this->pages = $this->getPagesList();

		if (!empty($this->friends)) {
			$this->friendsList = $this->id() . ',' . $this->friends;
		} else {
			$this->friendsList = $this->id();
		}

		// Disable the per_page limit if $from is set
		if (is_numeric($from)) {
			$this->per_page = 9999;
			$from = 'AND `messages`.`id` > \'' . $this->db->real_escape_string($from) . '\'';
		} else {
			$from = '';
		}

		// If the $start value is 0, empty the query;
		if ($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND `messages`.`id` < \'' . $this->db->real_escape_string($start) . '\'';
		}

		// Get the user feed
		if (empty($this->pages)) {
			$query = sprintf(
				"SELECT * FROM `messages` USE INDEX(`news_feed`) 
			LEFT JOIN `users` ON `users`.`idu` = `messages`.`uid` 
			AND `users`.`suspended` = 0 
			WHERE (`messages`.`uid` IN (%s) 
			AND `messages`.`page` = 0 
			AND `messages`.`group` = 0 
			AND `messages`.`public` != 0 %s%s) 
			ORDER BY `messages`.`id` DESC LIMIT %s",
				$this->friendsList,
				$start,
				$from,
				($this->per_page + 1)
			);
		}
		// Get the user feed and pages feed
		else {
			$query = sprintf(
				"(SELECT * FROM `messages` USE INDEX(`news_feed`) 
			LEFT JOIN `users` ON `users`.`idu` = `messages`.`uid` 
			AND `users`.`suspended` = 0 
			WHERE (`messages`.`uid` IN (%s) 
			AND `messages`.`group` = 0 
			AND `messages`.`page` = 0 
			AND `messages`.`public` != 0 %s%s) 
			ORDER BY `messages`.`id` DESC LIMIT %s)

			UNION (SELECT * FROM `messages` 
			LEFT JOIN `users` ON `users`.`idu` = `messages`.`uid` 
			AND `users`.`suspended` = 0 
			WHERE (`messages`.`page` IN (%s)
			AND `messages`.`public` != 0 %s%s)
			ORDER BY `messages`.`id` DESC LIMIT %s) 

			ORDER BY `id` DESC LIMIT %s",
				$this->friendsList,
				$start,
				$from,
				($this->per_page + 1),
				$this->pages,
				$start,
				$from,
				($this->per_page + 1),
				($this->per_page + 1)
			);
		}

		// Run the query
		$result = $this->db->query($query);

		// Set the result into an array
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}

		header('Content-Type: application/json');
		// print_r($rows);
		// echo $this->friendsList;
		echo json_encode($rows);

		// return $this->getMessages($query, 'loadFeed', '\'' . saniscape($value) . '\'');
	}

	function getUserFeeds($id, $start = 0, $value = null, $from = null)
	{
		$profile = $this->getSingleUser($id);
		$logedInUser = $this->logedInUser();
		$this->profile_id = $profile['idu'];

		$index = '`uid`';
		// print_r($logedInUser['is_admin']);
		// return;
		// If the username exist
		if (!empty($profile['idu'])) {
			$private = '';
			if ($profile['suspended'] == 2) {
				$private = 'profile_not_exists';
			} elseif ($profile['suspended'] == 1) {
				$private = 'profile_suspended';
			} else {
				if ($this->is_admin) {
					$private = 0;
				} elseif ($this->id() == $this->profile_id) {
					$private = 0;
				} else {
					$friendship = $this->verifyFriendship($this->id(), $this->profile_id);

					// If the profile is set to friends only and there is no friendship
					if ($profile['private'] == 2 && $friendship['status'] !== '1') {
						$private = 'profile_semi_private';
					}

					// If the profile is fully private
					elseif ($profile['private'] == 1) {
						$private = 'profile_private';
					}
					// If the profile is blocked
					// elseif($this->getBlocked($this->profile_id, 2)) {
					// 	$private = 'profile_blocked';
					// }
				}
			}
			if ($private) {
				$error_msg = "Profile is private";
				return $error_msg;
			}
			// Allowed types
			$this->listTypes = $this->listTypes('profile');
			$this->listDates = $this->listDates($this->profile_id, 'profile');

			// Disable the per_page limit if $from is set
			if (is_numeric($from)) {
				$this->per_page = 99;
				$from = 'AND messages.id > \'' . $this->db->real_escape_string($from) . '\'';
				$index = '`uid`, PRIMARY';
			} else {
				$this->per_page = 99;
				$from = '';
			}

			// If the $start value is 0, empty the query;
			if ($start == 0) {
				$start = '';
			} else {
				// Else, build up the query
				$start = 'AND `messages`.`id` < \'' . $this->db->real_escape_string($start) . '\'';
			}

			// Decide if the query will include only public messages or not
			// if the user that views the profile is not the owner
			$public = '';
			if ($this->id() !== $this->profile_id) {
				// Check if is admin or not
				if ($this->is_admin) {
					$public = '';
				} else {
					// Check if there is any friendship relation
					$friendship = $this->verifyFriendship($this->id(), $this->profile_id);

					if ($friendship['status'] == '1') {
						$public = "AND `messages`.`public` <> 0";
					} else {
						$public = "AND `messages`.`public` = 1";
					}
				}
			}

			$type = $date = '';

			// Check for active filters

			if (in_array($value, $this->listTypes)) {
				$type = sprintf("AND `messages`.`type` = '%s'", $this->db->real_escape_string($value));
				$index = '`uid`, `type`';
			} elseif (in_array($value, $this->listDates)) {
				$date = sprintf("AND `time` >= '%s' AND `time` < '%s'", $this->db->real_escape_string($value) . '-01-01 00:00:00', ($this->db->real_escape_string($value) + 1) . '-01-01 00:00:00');
				$index = '`uid`, `time`';
			}

			// Set results to get / pahe
			$per_page = 100;

			$query = sprintf(
				"SELECT * FROM `messages` USE INDEX(%s), `users` 
				WHERE `messages`.`uid` = '%s' %s
				AND `messages`.`group` = 0 AND `messages`.`page` = 0
				AND `messages`.`uid` = `users`.`idu` %s %s %s 
				ORDER BY `messages`.`id` DESC LIMIT %s",
				$index,
				$this->db->real_escape_string($this->profile_id),
				$type . $date,
				$public,
				$start,
				$from,
				($this->per_page + 1)
			);
			$query_ = sprintf(
				"SELECT * FROM `messages` USE INDEX(%s), `users` 
				WHERE `messages`.`uid` = '%s' %s
				AND `messages`.`group` = 0 AND `messages`.`page` = 0
				AND `messages`.`uid` = `users`.`idu` %s %s %s 
				ORDER BY `messages`.`id` DESC LIMIT %s",
				$index,
				$this->db->real_escape_string($this->profile_id),
				$type . $date,
				$public,
				$start,
				$from,
				$per_page
			);






			// Run the query
			$result = $this->db->query($query);

			// Set the result into an array
			$rows = array();
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}

			header('Content-Type: application/json');
			// print_r($rows);
			// echo $this->friendsList;
			echo json_encode($rows);
		} else {
			echo json_encode("Profile Non Existant");
		}
	}

	function listTypes($friends = null)
	{
		// Removed any verification queries for performance purposes
		if ($friends == false) {
			return false;
		} elseif ($friends == 'profile') {
			$list = array('food', 'game', 'map', 'music', 'picture', 'shared', 'video');
		} elseif ($friends) {
			$list = array('food', 'game', 'map', 'music', 'picture', 'shared', 'video');
		}
		return $list;
	}

	function listDates($id, $friends = null)
	{
		$profile_data = $this->getSingleUser($id);
		if ($friends == false) {
			return false;
		} elseif ($friends == 'profile') {
			$start_date = ($profile_data['date'] ? $profile_data['date'] : $this->registration_date);
		} elseif ($friends) {
			if ($friends == 'hashtag') {
				$query = $this->db->query(sprintf("SELECT extract(YEAR from `messages`.`time`) AS `year` FROM `messages` WHERE `messages`.`tag` LIKE '%s' ORDER BY `messages`.`id` ASC LIMIT 1", '%' . $this->db->real_escape_string($_GET['tag']) . '%'));
			} else {
				$query = $this->db->query(sprintf("SELECT extract(YEAR from `users`.`date`) AS `year` FROM `users` WHERE (`users`.`idu` IN (%s) AND `users`.`suspended` = 0) ORDER BY `users`.`date` ASC LIMIT 1", $friends));
			}

			$result = $query->fetch_assoc();

			$start_date = $result['year'] . '-01-01';
		}

		$date = date("Y", strtotime($start_date));
		while ($date <= date("Y", strtotime(date('Y-m-d')))) {
			$list[] = $date;
			$date++;
		}

		return array_reverse($list);
	}

	function verifyFriendship($user_id, $profile_id)
	{
		if ($user_id == $profile_id) {
			$result = array();
			$result['status'] = 'owner';
			$result['user1'] = null;
			$result['user2'] = null;
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `friendships` WHERE ((`user1` = '%s' AND `user2` = '%s') OR (`user1` = '%s' AND `user2` = '%s'))", $this->db->real_escape_string($user_id), $this->db->real_escape_string($profile_id), $this->db->real_escape_string($profile_id), $this->db->real_escape_string($user_id)));

			$result = $query->fetch_assoc();
		}
		// Returns the friendship status
		// Status: 	0 Pending
		//			1 Confirmed

		return array(
			'status'	=> $result['status'] ?? null,
			'from'		=> $result['user1'] ?? null,
			'to'		=> $result['user2'] ?? null
		);
	}

	function getGroupData($id)
	{
		$query = sprintf(
			"SELECT * FROM `groups` WHERE `id` = '%s' ",
			$this->db->real_escape_string($id)
		);

		$result = $this->db->query($query);

		header('Content-Type: application/json');
		echo json_encode($result->fetch_assoc());
		// return $result->fetch_assoc();
	}

	function getGroupOwner($id)
	{
		// Return the group owner ID (Admin panel)
		$query = sprintf(
			"SELECT * FROM `groups_users` WHERE `group` = '%s' AND `permissions` = 2",
			$this->db->real_escape_string($id)
		);

		// Run the query
		$result = $this->db->query($query);

		// return $result->fetch_assoc();
		header('Content-Type: application/json');
		echo json_encode($result->fetch_assoc());
	}

	function getGroupMemberData($group = null)
	{
		if ($group && $this->id()) {
			$query = $this->db->query(sprintf(
				"SELECT `groups_users`.`status`,`groups_users`.`permissions`
				FROM `groups_users` 
				WHERE `groups_users`.`group` = '%s' 
				AND `groups_users`.`user` = '%s'",
				$this->db->real_escape_string($group),
				$this->db->real_escape_string($this->id())
			));
			return $query->fetch_assoc();
			header('Content-Type: application/json');
			echo json_encode($query->fetch_assoc());
		}
	}

	function groupActivity($type, $message = null, $group = null, $user_id = null) {
		// Type 0: Get the latest viewed message from the group
		// Type 1: Add or update the notifications with the last viewed message
		// Type 2: Get the new messages count since last group visit
		// Type 3: Select the last posted message from the group

		if($type == 3) {
			// Select the last group message
			$query = $this->db->query(sprintf("SELECT `id` FROM `messages` WHERE `group` = '%s' ORDER BY `id` DESC LIMIT 1", $group));
			$result = $query->fetch_assoc();

			// Insert into notifications
			$this->db->query(sprintf("INSERT INTO `notifications` (`from`, `parent`, `child`, `type`) VALUES ('%s', '%s', '%s', '7')", $this->db->real_escape_string(($user_id ? $user_id : $this->id)), $group, $result['id']));
		} elseif($type == 2) {
			// Check if there is a last message in the notifications
			$last = $this->groupActivity(0, 0, $group);

			// If there is any last message
			$query = $this->db->query(sprintf("SELECT count(`id`) FROM `messages` WHERE `group` = '%s' AND `id` > '%s'", $group, $last));

			$result = $query->fetch_array();
			return $result[0];
		} elseif($type == 1) {
			// Check if there is a last message in the notifications
			$last = $this->groupActivity(0);

			// If the user has no `notifications`.`type` 7 (pre 2.0.9 release) add one
			if($last === NULL) {
				$this->groupActivity(3, $message, $this->group_data['id']);
				return false;
			}

			// Check if the last message is higher than the current loaded one (prevents adding lower values when Loading Page on groups)
			if($message > $last) {
				// Update the last record with the new one
				$query = $this->db->query(sprintf("UPDATE `notifications` SET `child` = '%s' WHERE `from` = '%s' AND `parent` = '%s' AND `type` = '7'", $message, $this->id, $this->group_data['id']));
			}
		} else {
			$query = $this->db->query(sprintf("SELECT `child` FROM `notifications` WHERE `from` = '%s' AND `parent` = '%s' AND `type` = '7'", $this->id, ($group ? $group : $this->group_data['id'])));

			$result = $query->fetch_assoc();
			return $result['child'] ?? null;
		}
	}

	function groupMember($type, $user)
	{
		// Type 2: Block the member
		// Type 1: Accept the user
		// Type 0: Decline the user

		return;

		// Get the user group status
		$currQuery = $this->db->query(sprintf("SELECT * FROM `groups_users` WHERE `user` = '%s' AND `group` = '%s'", $this->db->real_escape_string($user), $this->group_data['id']));

		$old = $currQuery->fetch_assoc();

		if ($type == 1) {
			// Approve the user
			$this->db->query(sprintf("UPDATE `groups_users` SET `status` = '1', `permissions` = 0 WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
			$this->groupActivity(3, null, $this->group_data['id'], $user);
			return;
		} elseif ($type == 2) {
			// Block the member and remove any permissions
			$this->db->query(sprintf("UPDATE `groups_users` SET `status` = '2', `permissions` = 0 WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} elseif ($type == 3) {
			// Unblock the member and remove any permissions
			$this->db->query(sprintf("UPDATE `groups_users` SET `status` = '1', `permissions` = 0 WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} elseif ($type == 4) {
			// Promote a group member to Admin status
			$this->db->query(sprintf("UPDATE `groups_users` SET `status` = '1', `permissions` = 1, `time` = `time` WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} elseif ($type == 5) {
			// Remove the Admin status of a member
			$this->db->query(sprintf("UPDATE `groups_users` SET `permissions` = 0, `time` = `time` WHERE `user` = '%s' AND `group` = '%s' AND `permissions` != '2' AND `user` != '%s'", $this->db->real_escape_string($user), $this->group_data['id'], $this->id));
		} else {
			// Delete a group member
			$stmt = $this->db->prepare("DELETE FROM `groups_users` WHERE `user` = ? AND `group` = ? AND `permissions` != '2' AND `user` != ?");

			$stmt->bind_param('sss', $user, $this->group_data['id'], $this->id);
			$stmt->execute();
			$affected = $stmt->affected_rows;
			$stmt->close();

			if ($affected) {
				// Delete the message images posted in the group
				$this->deleteMessagesImages($user, $this->group_data['id']);

				// Get the messages id of that user
				$mids = $this->getMessagesIds($user, $this->group_data['id']);

				// If the user had any content in the group
				if ($mids) {
					$sids = $this->getMessagesIds(null, null, null, $mids);

					// If there are any messages shared
					if ($sids) {
						$this->deleteShared($sids);
					}

					// Delete the shared messages by other users
					$this->db->query(sprintf("DELETE FROM `messages` WHERE `type` = 'shared' AND `value` IN (%s)", $mids));

					// Delete all the comments made to the messages
					$this->db->query(sprintf("DELETE FROM `comments` WHERE `mid` IN (%s)", $mids));

					// Delete all the likes from messages
					$this->db->query(sprintf("DELETE FROM `likes` WHERE `post` IN (%s) AND `type` = 0", $mids));

					// Remove all the reports of the message
					$this->db->query(sprintf("DELETE FROM `reports` WHERE `post` IN (%s)", $mids));

					// Remove the notifications of the message
					$this->db->query(sprintf("DELETE FROM `notifications` WHERE `parent` IN (%s)", $mids));
				}

				// Delete all the messages posted in the group
				$this->db->query(sprintf("DELETE FROM `messages` WHERE `uid` = '%s' AND `group` = '%s'", $this->db->real_escape_string($user), $this->group_data['id']));

				// Delete the `last message` notification
				$this->db->query(sprintf("DELETE FROM `notifications` WHERE `type` = '7' AND `from` = '%s' AND `parent` = '%s'", $this->db->real_escape_string($user), $this->group_data['id']));
			}
		}

		// Get the user group status
		$newQuery = $this->db->query(sprintf("SELECT * FROM `groups_users` WHERE `user` = '%s' AND `group` = '%s'", $this->db->real_escape_string($user), $this->group_data['id']));

		$new = $newQuery->fetch_assoc();

		// If the user was approved from the Requests page
		if ($type == 1 && $old['status'] == 0 && $new['status'] == 1) {
			$add = 1;
		}
		// If the user was unblocked from the Blocked page
		if ($type == 3 && $old['status'] == 2 && $new['status'] == 1) {
			$add = 1;
		}
		// If the user was blocked from Members page
		if ($type == 2 && $old['status'] == 1 && $new['status'] == 2) {
			$remove = 1;
		}
		// If the user was removed from the Members page
		if ($type == 0 && $old['status'] == 1 && isset($new['status']) && $new['status'] === NULL) {
			$remove = 1;
		}

		if (isset($add) && $add) {
			$this->db->query(sprintf("UPDATE `groups` SET `members` = (`members` + 1), `time` = `time` WHERE `id` = '%s'", $this->group_data['id']));
		} elseif (isset($remove) && $remove) {
			$this->db->query(sprintf("UPDATE `groups` SET `members` = (`members` - 1), `time` = `time` WHERE `id` = '%s'", $this->group_data['id']));
		}
	}


	function joinOrLeaveGroup($id)
	{
		global $LNG, $CONF;

		$group_member_data = $this->getGroupMemberData($id);
		// echo json_encode($group_member_data['status']);
		return;

		// Type 0: Return buttons

		// If the user is not logged-in, or has been blocked from group 
		if (!$this->id()) {
			return false;
		} elseif (isset($group_member_data['status']) && $group_member_data['status'] == '2') {
			return false;
		} elseif (isset($group_member_data['permissions']) && $group_member_data['permissions'] == '2') {
			return false;
		}

		// if ($type == 1) {
		$old_id = $this->id();
		$this->id = '';
		if (isset($group_member_data['status']) && $group_member_data['status'] == '1') {
			// Remove the user
			// $this->groupMember(0, $old_id);
			// Update the group count
			$this->db->query(sprintf("UPDATE `groups` SET `members` = `members` - 1, `time` = `time` WHERE `id` = '%s'", $group_member_data['id']));
		} elseif (isset($group_member_data['status']) && $group_member_data['status'] == '0') {
			// Remove the user
			// $this->groupMember(0, $old_id);
		} else {
			$mgq = $this->db->query(sprintf("SELECT COUNT(*) as `count` FROM `groups_users` WHERE `user` = '%s'", $this->db->real_escape_string($old_id)));
			$mgr = $mgq->fetch_assoc();
			if ($mgr['count'] > $this->groups_limit) {
				return false;
			}

			// If the group is private, request to join
			// On second thought, upon a chat with client, just join anyways
			if ($group_member_data['privacy'] == 1) {
				$this->db->query(sprintf("INSERT INTO `groups_users` (`group`, `user`, `status`, `permissions`) VALUES ('%s', '%s', '%s', '%s')", $group_member_data['id'], $old_id, 1, 0));
			} else {
				// Add in group
				$this->db->query(sprintf("INSERT INTO `groups_users` (`group`, `user`, `status`, `permissions`) VALUES ('%s', '%s', '%s', '%s')", $group_member_data['id'], $old_id, 1, 0));
				$this->groupActivity(3, null, $group_member_data['id'], $old_id);
				// Update the group count
				$this->db->query(sprintf("UPDATE `groups` SET `members` = `members` + 1, `time` = `time` WHERE `id` = '%s'", $group_member_data['id']));
			}
		}

		$this->id = $old_id;
		$this->group_member_data = $this->getGroupMemberData($group_member_data['id']);
		// return $this->joinGroup(0);
		// }
		return $output;
	}
}
