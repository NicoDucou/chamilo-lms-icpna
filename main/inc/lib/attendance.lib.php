<?php
/* For licensing terms, see /license.txt */
/**
 * This file contains class used like library, provides functions for attendance tool. It's also used like model to attendance_controller (MVC pattern)
 * @author Christian Fasanando <christian1827@gmail.com>
 * @author Julio Montoya <gugli100@gmail.com> improvements
 * @package chamilo.attendance
 */
/**
 * Attendance can be used to instanciate objects or as a library to manage attendances
 * @package chamilo.attendance
 */
class Attendance
{
	private $session_id;
	private $course_id;
	private $date_time;
	private $name;
	private $description;
	private $attendance_qualify_title;
	private $attendance_weight;
	private $course_int_id;
    public $category_id;

    // constants
	const DONE_ATTENDANCE_LOG_TYPE = 'done_attendance_sheet';
    const UPDATED_ATTENDANCE_LOG_TYPE = 'updated_attendance_sheet';
    const LOCKED_ATTENDANCE_LOG_TYPE = 'locked_attendance_sheet';

	public function __construct() {
		//$this->course_int_id = api_get_course_int_id();
	}

	/**
	 * Get the total number of attendance inside current course and current session
	 * @see SortableTable#get_total_number_of_items()
	 */
	static function get_number_of_attendances() {
		$tbl_attendance = Database :: get_course_table(TABLE_ATTENDANCE);
		$session_id = api_get_session_id();
		$condition_session = api_get_session_condition($session_id);
        $course_id = api_get_course_int_id();
		$sql = "SELECT COUNT(att.id) AS total_number_of_items FROM $tbl_attendance att
		        WHERE c_id = $course_id AND att.active = 1 $condition_session ";
		$res = Database::query($sql);
		$obj = Database::fetch_object($res);
		return $obj->total_number_of_items;
	}

	/**
	 * Get attendance list only the id, name and attendance_qualify_max fields
	 * @param   string  course db name (optional)
	 * @param   int     session id (optional)
	 * @return  array	attendances list
	 */
	function get_attendances_list($course_id = '', $session_id = null) {
		// Initializing database table and variables
		$tbl_attendance = Database :: get_course_table(TABLE_ATTENDANCE);
		$data = array();
        //@todo user seters/gettrs
		if (empty($course_id)) {
			$course_id = api_get_course_int_id();
		} else {
		    $course_id = intval($course_id);
		}

        $session_id = isset($session_id) ? intval($session_id):api_get_session_id();
        $condition_session = api_get_session_condition($session_id);

		// Get attendance data
		$sql = "SELECT id, name, attendance_qualify_max
                FROM $tbl_attendance
		        WHERE c_id = $course_id AND active = 1 $condition_session ";
		$rs  = Database::query($sql);
		if (Database::num_rows($rs) > 0) {
			while ($row = Database::fetch_array($rs,'ASSOC')) {
				$data[$row['id']] = $row;
			}
		}
		return $data;
	}

	/**
	 * Get the attendaces to display on the current page (fill the sortable-table)
	 * @param   int     offset of first user to recover
	 * @param   int     Number of users to get
	 * @param   int     Column to sort on
	 * @param   string  Order (ASC,DESC)
	 * @see SortableTable#get_table_data($from)
	 */
	static function get_attendance_data($from = 0, $number_of_items = 20, $column = 1, $direction = 'desc') {

        $tbl_attendance = Database :: get_course_table(TABLE_ATTENDANCE);
        $course_id = api_get_course_int_id();
        $session_id = api_get_session_id();
        $condition_session = api_get_session_condition($session_id);
        $column = intval($column);
        $from = intval($from);
        $number_of_items = intval($number_of_items);

        if (!in_array($direction, array('ASC','DESC'))) {
            $direction = 'ASC';
        }

        $att = new Attendance();
        $att->set_session_id($session_id);
        $att->set_course_int_id($course_id);

        $active_plus = 'att.active = 1';

        if (api_is_platform_admin()) {
            $active_plus = ' 1 = 1 ';
        }

		$sql = "SELECT
                    att.id,
                    att.name,
                    att.description,
                    att.attendance_qualify_max,
                    att.locked,
                    att.active,
                    att.session_id
				FROM $tbl_attendance att
				WHERE c_id = $course_id AND $active_plus $condition_session
				ORDER BY $column $direction
                LIMIT $from, $number_of_items ";

		$res = Database::query($sql);
		$attendances = array();

		$param_gradebook = '';
		if (isset($_SESSION['gradebook'])) {
			$param_gradebook = '&gradebook='.$_SESSION['gradebook'];
		}
        $user_info = api_get_user_info();
		while ($attendance = Database::fetch_array($res,'ASSOC')) {
            $id = $attendance['id'];
			$student_param = '';
			if (api_is_drh() && ($_GET['student_id'])) {
				$student_param = '&student_id='.Security::remove_XSS($_GET['student_id']);
			}
            $session_star = '';

            if (api_get_session_id() == $attendance['session_id']) {
                $session_star = api_get_session_image(api_get_session_id(), $user_info['status']);
            }

            if ($attendance['active'] == 0) {
                $attendance['name'] = "<del>".$attendance['name']."</del>";
            }

            if ($attendance['locked'] == 1) {
                if (api_is_allowed_to_edit(null, true)) {
                    //Link to edit
                    $attendance['name'] = '<a href="index.php?'.api_get_cidreq().'&action=attendance_sheet_list&attendance_id='.$id.$param_gradebook.$student_param.'">'.$attendance['name'].'</a>'.$session_star;
                } else {
                    //Link to view
                    $attendance['name'] = '<a href="index.php?'.api_get_cidreq().'&action=attendance_sheet_list_no_edit&attendance_id='.$id.$param_gradebook.$student_param.'">'.$attendance['name'].'</a>'.$session_star;
                }
            } else {
                $attendance['name'] = '<a href="index.php?'.api_get_cidreq().'&action=attendance_sheet_list&attendance_id='.$id.$param_gradebook.$student_param.'">'.$attendance['name'].'</a>'.$session_star;
            }

            //description
			$attendance['description'] = '<center>'.$attendance['description'].'</center>';

			if (api_is_allowed_to_edit(null, true)) {
				$actions  = '';
				$actions .= '<center>';

                if (api_is_platform_admin()) {
                    $actions .= '<a href="index.php?'.api_get_cidreq().'&action=attendance_edit&attendance_id='.$id.$param_gradebook.'">'.
                                    Display::return_icon('edit.png',get_lang('Edit'), array(), ICON_SIZE_SMALL).'</a>&nbsp;';

                    if ($attendance['locked'] == 0) {
                    //    $actions .= '<a onclick="javascript:if(!confirm(\''.get_lang('AreYouSureToDelete').'\')) return false;" href="index.php?'.api_get_cidreq().'&action=attendance_delete&attendance_id='.$id.$param_gradebook.'">'.Display::return_icon('delete.png',get_lang('Delete'), array(), ICON_SIZE_SMALL).'</a>';
                    }

                    if ($attendance['active'] == 1) {
                        $actions .= '<a onclick="javascript:if(!confirm(\''.get_lang('AreYouSureToDelete').'\')) return false;" href="index.php?'.api_get_cidreq().'&action=attendance_delete&attendance_id='.$id.$param_gradebook.'">'.Display::return_icon('delete.png',get_lang('Delete'), array(), ICON_SIZE_SMALL).'</a>';
                    } else {
                        $actions .= '<a onclick="javascript:if(!confirm(\''.get_lang('AreYouSureToRestore').'\')) return false;" href="index.php?'.api_get_cidreq().'&action=attendance_restore&attendance_id='.$id.$param_gradebook.'">'.Display::return_icon('invisible.png',get_lang('Restore'), array(), ICON_SIZE_SMALL).'</a>';
                    }

                } else {
                    $is_locked_attendance = self::is_locked_attendance($attendance['id']);
                    if ($is_locked_attendance) {
                        $actions .= Display::return_icon('edit_na.png',get_lang('Edit')).'&nbsp;';
                        $actions .= Display::return_icon('delete_na.png',get_lang('Delete'));
                    } else {
                        $actions .= '<a href="index.php?'.api_get_cidreq().'&action=attendance_edit&attendance_id='.$id.$param_gradebook.'">'.Display::return_icon('edit.png',get_lang('Edit'), array(), ICON_SIZE_SMALL).'</a>&nbsp;';
                        $actions .= '<a onclick="javascript:if(!confirm(\''.get_lang('AreYouSureToDelete').'\')) return false;" href="index.php?'.api_get_cidreq().'&action=attendance_delete&attendance_id='.$id.$param_gradebook.'">'.Display::return_icon('delete.png',get_lang('Delete'), array(), ICON_SIZE_SMALL).'</a>';
                    }
                }

                // display lock/unlock icon
                $is_done_all_calendar = $att->is_all_attendance_calendar_done($id);

                if ($is_done_all_calendar) {
                    $locked  = $attendance['attendance_qualify_max'];
                    if ($locked == 0) {
                        if (api_is_platform_admin()) {
                            $message_alert = get_lang('AreYouSureToLockTheAttendance');
                        } else {
                            $message_alert = get_lang('UnlockMessageInformation');
                        }
                        $actions .= '&nbsp;<a onclick="javascript:if(!confirm(\''.$message_alert.'\')) return false;" href="index.php?'.api_get_cidreq().'&action=lock_attendance&attendance_id='.$id.$param_gradebook.'">'.
                                    Display::return_icon('unlock.png', get_lang('LockAttendance')).'</a>';
                    } else {
                        if (api_is_platform_admin()) {
                            $actions .= '&nbsp;<a onclick="javascript:if(!confirm(\''.get_lang('AreYouSureToUnlockTheAttendance').'\')) return false;" href="index.php?'.api_get_cidreq().'&action=unlock_attendance&attendance_id='.$id.$param_gradebook.'">'.
                                    Display::return_icon('lock.png', get_lang('UnlockAttendance')).'</a>';
                        } else {
                            $actions .= '&nbsp;'.Display::return_icon('locked_na.png',get_lang('LockedAttendance'));
                        }
                    }
                }
                $actions .= '</center>';
                $attendance['actions'] = $actions;
				$attendances[] = $attendance;
			} else {
				$attendances[] = $attendance;
			}
		}
		return $attendances;
	}

	/**
	 * Get the attendaces by id to display on the current page
	 * @param  int     attendance id
	 * @return array   attendance data
	 */
	public function get_attendance_by_id($attendance_id) {
		$tbl_attendance = Database :: get_course_table(TABLE_ATTENDANCE);
		$attendance_id = intval($attendance_id);
        $course_id = $this->get_course_int_id();
	    $attendance_data = array();
		$sql = "SELECT *  FROM $tbl_attendance WHERE c_id = $course_id AND  id = '$attendance_id'";
		$res = Database::query($sql);
		if (Database::num_rows($res) > 0) {
			while ($row = Database::fetch_array($res)) {
				$attendance_data = $row;
			}
		}
		return $attendance_data;
	}

	/**
	 * Add attendaces sheet inside table. This is the *list of* dates, not
         * a specific date in itself.
	 * @param  bool   true for adding link in gradebook or false otherwise (optional)
	 * @return int    last attendance id
	 */
	public function attendance_add($link_to_gradebook = false, $user_id = null) {
		$tbl_attendance	= Database :: get_course_table(TABLE_ATTENDANCE);
		$table_link 	= Database :: get_main_table(TABLE_MAIN_GRADEBOOK_LINK);

        if (empty($user_id)) {
            $user_id = api_get_user_id();
        } else {
            $user_id = intval($user_id);
        }
        $course_code  = $this->get_course_id();
        $session_id   = $this->get_session_id();
        $course_info  = api_get_course_info($course_code);
        $course_id    = $course_info['real_id'];

		$title_gradebook = Database::escape_string($this->attendance_qualify_title);
        $weight_calification =	floatval($this->attendance_weight);

		$value_calification  = 0;
                $aid = $this->get_last_attendance_id($course_id)+1;
		$sql = "INSERT INTO $tbl_attendance SET
					c_id =  $course_id,
                                        id = $aid,
					name ='".Database::escape_string($this->name)."',
					description = '".Database::escape_string($this->description)."',
					attendance_qualify_title = '$title_gradebook',
					attendance_weight = '$weight_calification',
					session_id = '$session_id'";
		$result = Database::query($sql);
		$affected_rows = Database::affected_rows($result);
		$last_id = 0;
		if (!empty($affected_rows)) {
			// save inside item property table
			$last_id = $aid;
			api_item_property_update($course_info, TOOL_ATTENDANCE, $last_id, "AttendanceAdded", $user_id);
		}
		// add link to gradebook
		if ($link_to_gradebook && !empty($this->category_id)) {
			$description = '';
			$link_info = is_resource_in_course_gradebook($course_code, 7, $last_id, $session_id);
			$link_id = $link_info['id'];
			if (!$link_info) {
				add_resource_to_course_gradebook($this->category_id, $course_code, 7, $last_id, $title_gradebook,$weight_calification,$value_calification,$description,1,$session_id);
			} else {
				Database::query('UPDATE '.$table_link.' SET weight='.$weight_calification.' WHERE id='.$link_id.'');
			}
		}
		return $last_id;
	}

	/**
	 * edit attendaces inside table
	 * @param 	int	   attendance id
	 * @param  	bool   true for adding link in gradebook or false otherwise (optional)
	 * @return 	int    last id
	 */
	public function attendance_edit($attendance_id, $link_to_gradebook = false) {
        $_course = api_get_course_info();
		$tbl_attendance     = Database :: get_course_table(TABLE_ATTENDANCE);
		$table_link         = Database :: get_main_table(TABLE_MAIN_GRADEBOOK_LINK);
		$session_id         = api_get_session_id();
		$user_id            = api_get_user_id();
		$attendance_id      = intval($attendance_id);
		$course_code        = $this->get_course_id();
        $course_id          = $this->get_course_int_id();
		$title_gradebook	= Database::escape_string($this->attendance_qualify_title);
		$value_calification = 0;
		$weight_calification= floatval($this->attendance_weight);

        if (!empty($attendance_id)) {

            $sql = "UPDATE $tbl_attendance
                    SET name ='".Database::escape_string($this->name)."',
                        description = '".Database::escape_string($this->description)."',
                        attendance_qualify_title = '".$title_gradebook."',
                        attendance_weight = '".$weight_calification."'
                    WHERE c_id = $course_id AND id = '$attendance_id'";
            Database::query($sql);

            api_item_property_update($_course, TOOL_ATTENDANCE, $attendance_id,"AttendanceUpdated", $user_id);

            // add link to gradebook

            if ($link_to_gradebook && !empty($this->category_id)) {
                $description = '';
                $link_id = is_resource_in_course_gradebook($course_code, 7, $attendance_id, $session_id);
                if (!$link_id) {
                    add_resource_to_course_gradebook($this->category_id, $course_code, 7, $attendance_id, $title_gradebook,$weight_calification,$value_calification,$description,1,$session_id);
                } else {
                    Database::query('UPDATE '.$table_link.' SET weight='.$weight_calification.' WHERE id='.$link_id.'');
                }
            }
            return $attendance_id;
        }
        return null;
	}

	/**
	 * Restore attendance
	 * @param 	int|array	   one or many attendances id
	 * @return 	int    		   affected rows
	 */
	public function attendance_restore($attendance_id) {
		$tbl_attendance	= Database :: get_course_table(TABLE_ATTENDANCE);
		$user_id 		= api_get_user_id();
        $course_id      = $this->get_course_int_id();
        $course_info    = api_get_course_info_by_id($course_id);

		if (is_array($attendance_id)) {
			foreach ($attendance_id as $id) {
				$id	= intval($id);
				$sql = "UPDATE $tbl_attendance SET active = 1 WHERE c_id = $course_id AND id = '$id'";
				$result = Database::query($sql);
				$affected_rows = Database::affected_rows($result);
				if (!empty($affected_rows)) {
					// update row item property table
					api_item_property_update($course_info, TOOL_ATTENDANCE, $id,"restore", $user_id);
				}
			}
		} else  {
			$attendance_id	= intval($attendance_id);
			$sql = "UPDATE $tbl_attendance SET active = 1 WHERE c_id = $course_id AND id = '$attendance_id'";
			$result = Database::query($sql);
			$affected_rows = Database::affected_rows($result);
			if (!empty($affected_rows)) {
				// update row item property table
				api_item_property_update($course_info, TOOL_ATTENDANCE, $attendance_id,"restore", $user_id);
			}
		}
		return $affected_rows;
	}


	/**
	 * delete attendaces
	 * @param 	int|array	   one or many attendances id
	 * @return 	int    		   affected rows
	 */
	public function attendance_delete($attendance_id) {
		$tbl_attendance	= Database :: get_course_table(TABLE_ATTENDANCE);
		$user_id 		= api_get_user_id();
		$course_id      = $this->get_course_int_id();
        $course_info    = api_get_course_info_by_id($course_id);

		if (is_array($attendance_id)) {
			foreach ($attendance_id as $id) {
				$id	= intval($id);
				$sql = "UPDATE $tbl_attendance SET active = 0 WHERE c_id = $course_id AND id = '$id'";
				$result = Database::query($sql);
				$affected_rows = Database::affected_rows($result);
				if (!empty($affected_rows)) {
					// update row item property table
					api_item_property_update($course_info, TOOL_ATTENDANCE, $id,"delete", $user_id);
				}
			}
		} else  {
			$attendance_id	= intval($attendance_id);
			$sql = "UPDATE $tbl_attendance SET active = 0 WHERE c_id = $course_id AND id = '$attendance_id'";
			$result = Database::query($sql);
			$affected_rows = Database::affected_rows($result);
			if (!empty($affected_rows)) {
				// update row item property table
				api_item_property_update($course_info, TOOL_ATTENDANCE, $attendance_id,"delete", $user_id);
			}
		}
		return $affected_rows;
	}

    /**
     * Lock or unlock an attendance
     * @param   int     attendance id
     * @param   bool    True to lock or false otherwise
     */
    public function lock_attendance($attendance_id, $lock = true) {
        $tbl_attendance = Database::get_course_table(TABLE_ATTENDANCE);
        $course_id = $this->get_course_int_id();
        $attendance_id = intval($attendance_id);
        $locked = ($lock)?1:0;
        $upd = "UPDATE $tbl_attendance SET locked = $locked WHERE c_id = $course_id AND id = $attendance_id";
        $result = Database::query($upd);
        $affected_rows = Database::affected_rows($result);
        if ($affected_rows && $lock) {
            //save attendance sheet log
            $lastedit_type = self::LOCKED_ATTENDANCE_LOG_TYPE;
            $lastedit_user_id = api_get_user_id();
            $this->save_attendance_sheet_log($attendance_id, api_get_utc_datetime(), $lastedit_type, $lastedit_user_id);
        }
        return $affected_rows;
    }

	/**
	 * Get registered users inside current course
	 * @param 	int	   attendance id for showing attendance result field (optional)
	 * @return 	array  users data
	 */
	public function get_users_rel_course($attendance_id = 0) {
        $current_course_id  = $this->get_course_id();
        $courseInfo = api_get_course_info($current_course_id);
        $courseId = $courseInfo['real_id'];

        $current_session_id  = $this->get_session_id();

		if (!empty($current_session_id)) {
			$a_course_users = CourseManager :: get_user_list_from_course_code($current_course_id, $current_session_id,'', 'lastname');
		} else {
			$a_course_users = CourseManager :: get_user_list_from_course_code($current_course_id, 0, '','lastname');
		}

		// get registered users inside current course
		$a_users = array();
		foreach ($a_course_users as $key =>$user_data) {
			$value	= array();
			$uid 	= $user_data['user_id'];
			$status = $user_data['status'];

			$user_status_in_session = null;
			$user_status_in_course  = null;

			if ($current_session_id) {
                $user_status_in_session = SessionManager::get_user_status_in_course_session($uid, $courseId, $current_session_id);
			} else {
			    $user_status_in_course = CourseManager::get_user_in_course_status($uid, $courseId);
			}

			//Not taking into account DRH or COURSEMANAGER
			if ($uid <= 1 || $status == DRH || $user_status_in_course == COURSEMANAGER || $user_status_in_session == 2) continue;

			if (!empty($attendance_id)) {
				$user_faults = $this->get_faults_of_user($uid, $attendance_id);

				$value['attendance_result'] = $user_faults['faults'].'/'.$user_faults['total'].' ('.$user_faults['faults_porcent'].'%)';
				$value['result_color_bar'] 	= $user_faults['color_bar'];
			}

			// user's picture
			$image_path 	= UserManager::get_user_picture_path_by_id($uid, 'web', false);
			$user_profile 	= UserManager::get_picture_user($uid, $image_path['file'], 22, USER_IMAGE_SIZE_SMALL, ' width="22" height="22" ');

			if (!empty($image_path['file'])) {
				$photo = '<center><a class="thickbox" href="'.$image_path['dir'].$image_path['file'].'"  ><img src="'.$user_profile['file'].'" '.$user_profile['style'].' alt="'.api_get_person_name($user_data['firstname'], $user_data['lastname']).'"  title="'.api_get_person_name($user_data['firstname'], $user_data['lastname']).'" /></a></center>';
			} else {
				$photo = '<center><img src="'.$user_profile['file'].'" '.$user_profile['style'].' alt="'.api_get_person_name($user_data['firstname'], $user_data['lastname']).'"  title="'.api_get_person_name($user_data['firstname'], $user_data['lastname']).'" /></center>';
			}

			$value['photo']      = $photo;
			$value['firstname']  = $user_data['firstname'];
			$value['lastname']	 = $user_data['lastname'];
			$value['username']   = $user_data['username'];
            $value['user_id']    = $uid;

			//Sending only 5 items in the array instead of 60
			$a_users[$key] = $value;
		}
		return $a_users;
	}

    public function attendance_sheet_disable($calendar_id, $user_id) {
        $tbl_attendance_sheet 	= Database::get_course_table(TABLE_ATTENDANCE_SHEET);
        $course_id = $this->get_course_int_id();
        $user_id = intval($user_id);
        $calendar_id = intval($calendar_id);

        $sql = "UPDATE $tbl_attendance_sheet SET presence = ''
                WHERE c_id = $course_id AND user_id ='$user_id' AND attendance_calendar_id = '$calendar_id'";
        Database::query($sql);
        return true;
    }

    public function attendance_sheet_get_info($calendar_id, $user_id) {
        $tbl_attendance_sheet 	= Database::get_course_table(TABLE_ATTENDANCE_SHEET);
        $course_id = $this->get_course_int_id();
        $user_id = intval($user_id);
        $calendar_id = intval($calendar_id);

        $sql = "SELECT *  FROM $tbl_attendance_sheet
                WHERE c_id = $course_id AND user_id =  '$user_id' AND attendance_calendar_id = '$calendar_id'";
        $result = Database::query($sql);

        if (Database::num_rows($result)) {
            return Database::store_result($result, 'ASSOC');
        }
        return false;
    }

	/**
	 * Add attendaces sheet inside table
	 * @param 	int	   attendance calendar id
	 * @param  	array  present users during current class (array with user_ids)
	 * @param	int	   attendance id
	 * @return 	int    affected rows
	 */
	public function attendance_sheet_add($calendar_id, $user_results, $attendance_id, $save_user_absents = true, $done_attendance = true) {
		$tbl_attendance_sheet 	= Database::get_course_table(TABLE_ATTENDANCE_SHEET);
		$tbl_attendance_calendar= Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);

		$calendar_id = intval($calendar_id);
		$attendance_id = intval($attendance_id);
		$users = $this->get_users_rel_course();
        $course_id = $this->get_course_int_id();

		$user_ids = array_keys($users);
		$users_absent = array_diff($user_ids, $user_results);
		$affected_rows = 0;

        // get last edit type
        $calendar_data = $this->get_attendance_calendar_by_id($calendar_id);

        $lastedit_type = self::DONE_ATTENDANCE_LOG_TYPE;
        if (isset($calendar_data['done_attendance']) && $calendar_data['done_attendance']) {
            $lastedit_type = self::UPDATED_ATTENDANCE_LOG_TYPE;
        }

        if (!empty($user_results)) {
            // Save users present in class
            foreach ($user_results as $uid => $presence) {
                $presence = intval($presence);

                // check if user already was registered with the $calendar_id
                $sql = "SELECT user_id FROM $tbl_attendance_sheet WHERE c_id = $course_id AND user_id = '$uid' AND attendance_calendar_id = '$calendar_id'";
                $rs  = Database::query($sql);
                if (Database::num_rows($rs) == 0) {
                    $sql = "INSERT INTO $tbl_attendance_sheet SET
                            c_id					= $course_id,
                            user_id 				= '$uid',
                            attendance_calendar_id 	= '$calendar_id',
                            presence 				= $presence";
                    $result = Database::query($sql);
                    $affected_rows += Database::affected_rows($result);
                } else {
                    $sql = "UPDATE $tbl_attendance_sheet SET presence = $presence
                            WHERE c_id = $course_id AND user_id ='$uid' AND attendance_calendar_id = '$calendar_id'";
                    $result = Database::query($sql);
                    $affected_rows += Database::affected_rows($result);
                }
            }
        }
        /*
		// save users absent in class
        if ($save_user_absents) {
            foreach ($users_absent as $user_absent) {
                $uid = intval($user_absent);
                // check if user already was registered with the $calendar_id
                $sql = "SELECT user_id FROM $tbl_attendance_sheet WHERE c_id = $course_id AND user_id='$uid' AND attendance_calendar_id = '$calendar_id'";
                $rs  = Database::query($sql);
                if (Database::num_rows($rs) == 0) {
                    $sql = "INSERT INTO $tbl_attendance_sheet SET
                            c_id = $course_id,
                            user_id ='$uid',
                            attendance_calendar_id = '$calendar_id',
                            presence = 0";
                    Database::query($sql);
                    $affected_rows += Database::affected_rows();
                } else {
                    $sql = "UPDATE $tbl_attendance_sheet SET presence = 0 WHERE c_id = $course_id AND user_id ='$uid' AND attendance_calendar_id = '$calendar_id'";
                    Database::query($sql);
                    $affected_rows += Database::affected_rows();
                }
            }
        }*/

        if ($done_attendance) {
            // update done_attendance inside attendance calendar table
            $sql = "UPDATE $tbl_attendance_calendar SET done_attendance = 1 WHERE c_id = $course_id AND id = '$calendar_id'";
            Database::query($sql);
        }

		// Save user's results
		$this->update_users_results($user_ids, $attendance_id);

        if ($affected_rows) {
            //save attendance sheet log
            $lastedit_user_id = api_get_user_id();
            $calendar_date_value = $calendar_data['date_time'];
            $this->save_attendance_sheet_log($attendance_id, api_get_utc_datetime(), $lastedit_type, $lastedit_user_id, $calendar_date_value);
        }
		return $affected_rows;
	}

    /**
     * Unstable version! Needs review! Use only for speed boosts!
     * Add attendaces sheet inside table, by group to reduce db stress
     * @param       array  Array of calendar_id, user_id, status, attendance_id, course_id, date, all assumed pre-filtered
     * @param       bool   Whether to save absent users
     * @param       bool   Whether to set done_attendance to 1 in db
     * @return      int    affected rows
     */
    public function attendance_sheet_group_add($list, $save_user_absents = true, $done_attendance = true) {
        $tbl_attendance_sheet   = Database::get_course_table(TABLE_ATTENDANCE_SHEET);
        $tbl_attendance_calendar= Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);

        $user_ids = array();
        $date_now = api_get_utc_datetime();
        //$calendar_date_value = $calendar_data['date_time'];
        $lastedit_user_id = api_get_user_id();
        $lastedit_type = self::DONE_ATTENDANCE_LOG_TYPE;
        $sql = "INSERT INTO $tbl_attendance_sheet (c_id, user_id, attendance_calendar_id, presence) VALUES ";
        foreach ($list as $row) {
            if ($row[0] != null) { //ignore placeholders
                $sql .= "(".intval($row[4]).','.intval($row[1]).','.intval($row[0]).','.$row[2].'),';
                $user_ids[] = intval($row[1]);
            }
            if ($done_attendance) {
                // update done_attendance inside attendance calendar table
                $sql2 = "UPDATE $tbl_attendance_calendar SET done_attendance = 1 WHERE c_id = ".intval($row[4])." AND id = '".intval($row[0])."'";
                $r2 = Database::query($sql2);
            }
            // Save user's results
            $this->update_users_results(array(intval($row[1])), $attendance_id);
            //save attendance sheet log
            $this->save_attendance_sheet_log(intval($row[3]), $date_now, $lastedit_type, $lastedit_user_id, $row[5]);
        }
        $sql = substr($sql,0,-1);
        $r = Database::query($sql);
        $users_absent = array_diff($user_ids, $user_results);
        $affected_rows = Database::affected_rows($r);
        return $affected_rows;
    }

	/**
	 * update users' attendance results
	 * @param 	array  registered users inside current course
	 * @param	int	   attendance id
	 * @return 	void
	 */
	public function update_users_results($user_ids, $attendance_id) {
		$tbl_attendance_sheet 	= Database::get_course_table(TABLE_ATTENDANCE_SHEET);
		$tbl_attendance_result  = Database::get_course_table(TABLE_ATTENDANCE_RESULT);
		$tbl_attendance			= Database::get_course_table(TABLE_ATTENDANCE);
        $course_id              = $this->get_course_int_id();

		$attendance_id = intval($attendance_id);
		// fill results about presence of students
		$attendance_calendar = $this->get_attendance_calendar($attendance_id);
		$calendar_ids = array();
		// get all dates from calendar by current attendance
		foreach ($attendance_calendar as $cal) {
			$calendar_ids[] = $cal['id'];
		}
        //See BT#5419
        $status_that_score_point_to_users = self::get_status_that_score_point_to_users();
        $status_that_score_point_to_users = "'".implode("','", $status_that_score_point_to_users)."'";

		// Get count of presences by users inside current attendance and save like results
		$count_presences = 0;
		if (count($user_ids) > 0) {
			foreach ($user_ids as $uid) {
				$count_presences = 0;
				if (count($calendar_ids) > 0) {
					$sql = "SELECT count(presence) as count_presences
                            FROM $tbl_attendance_sheet
					        WHERE   c_id = $course_id AND
                                    user_id = '$uid' AND
                                    attendance_calendar_id IN (".implode(',', $calendar_ids).") AND
                                    presence IN ($status_that_score_point_to_users)";
					$rs_count  = Database::query($sql);
					$row_count = Database::fetch_array($rs_count);
					$count_presences = $row_count['count_presences'];
				}

				// Save results
				$sql = "SELECT id FROM $tbl_attendance_result WHERE c_id = $course_id AND user_id = '$uid' AND attendance_id='$attendance_id'";
				$rs_check_result = Database::query($sql);
				if (Database::num_rows($rs_check_result) > 0) {
					// update result
					$sql = "UPDATE $tbl_attendance_result SET
							score = '$count_presences'
							WHERE c_id = $course_id AND user_id='$uid' AND attendance_id='$attendance_id'";
					Database::query($sql);
				} else {
					// insert new result
                                        $aid = $this->get_last_attendance_result_id($course_id)+1;
					$sql = "INSERT INTO $tbl_attendance_result SET
								c_id            = $course_id ,
								id            = $aid ,
								user_id			= '$uid',
								attendance_id 	= '$attendance_id',
								score			= '$count_presences'";
					Database::query($sql);
				}
			}
		}
		// update attendance qualify max
		$count_done_calendar = $this->get_done_attendance_calendar($attendance_id);
		$sql = "UPDATE $tbl_attendance SET attendance_qualify_max='$count_done_calendar' WHERE c_id = $course_id AND id = '$attendance_id'";
		Database::query($sql);
	}

    /**
        * update attendance_sheet_log table, is used as history of an attendance sheet
        * @param   int     Attendance id
        * @param   string  Last edit datetime
        * @param   string  Event type ('locked_attendance', 'done_attendance_sheet' ...)
        * @param   int     Last edit user id
        * @param   string  Calendar datetime value (optional, when event type is 'done_attendance_sheet')
        * @return  int     Affected rows
        */
    public function save_attendance_sheet_log($attendance_id, $lastedit_date, $lastedit_type, $lastedit_user_id, $calendar_date_value = null) {
        $course_id = $this->get_course_int_id();

        // define table
        $tbl_attendance_sheet_log = Database::get_course_table(TABLE_ATTENDANCE_SHEET_LOG);

        // protect data
        $attendance_id = intval($attendance_id);
        $lastedit_date = Database::escape_string($lastedit_date);
        $lastedit_type = Database::escape_string($lastedit_type);
        $lastedit_user_id = intval($lastedit_user_id);

        if (isset($calendar_date_value)) {
            $calendar_date_value = Database::escape_string($calendar_date_value);
        } else {
            $calendar_date_value = '';
        }

        // save data
        $aid = $this->get_last_attendance_sheet_log_id($course_id)+1;
        $ins = "INSERT INTO $tbl_attendance_sheet_log(c_id, id, attendance_id, lastedit_date, lastedit_type, lastedit_user_id, calendar_date_value)
                VALUES ($course_id, $aid, $attendance_id, '$lastedit_date', '$lastedit_type', $lastedit_user_id, '$calendar_date_value')";
        $result = Database::query($ins);
        return Database::affected_rows($result);
   }

	/**
	 * Get number of done attendances inside current sheet
	 * @param	int	   attendance id
	 * @return 	int	   number of done attendances
	 */
	public function get_done_attendance_calendar($attendance_id) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$attendance_id = intval($attendance_id);
        $course_id = $this->get_course_int_id();
		$sql = "SELECT count(done_attendance) as count
                FROM $tbl_attendance_calendar
                WHERE   c_id = $course_id AND
                        attendance_id = '$attendance_id' AND
                        done_attendance = 1";
		$rs  = Database::query($sql);
		$row = Database::fetch_array($rs);
		$count = $row['count'];
		return $count;
	}

	/**
	 * Get results of faults (absents) by user
	 * @param	int	   user id
	 * @param	int	   attendance id
	 * @return 	array  results containing number of faults, total done attendance, porcent of faults and color depend on result (red, orange)
	 */
	public function get_faults_of_user($user_id, $attendance_id) {
		$user_id 		= intval($user_id);
		$attendance_id 	= intval($attendance_id);
		$results = array();
		$attendance_data 	= $this->get_attendance_by_id($attendance_id);
		$calendar_count 	= $this->get_number_of_attendance_calendar($attendance_id);

		$total_done_attendance 	= $attendance_data['attendance_qualify_max'];

		$attendance_user_score  = $this->get_user_score($user_id, $attendance_id);

		//This is the main change of the BT#1381
		//$total_done_attendance = $calendar_count;

		// calculate results
		$faults = $total_done_attendance - $attendance_user_score;
		$faults = $faults > 0 ? $faults:0;
		$faults_porcent = $calendar_count > 0 ?round(($faults*100)/$calendar_count,0):0;

		$results['faults'] 			= $faults;
		$results['total']			= $calendar_count;
		$results['faults_porcent'] 	= $faults_porcent;

		$color_bar = 'success';
		if ($faults_porcent > 25  ) {
			$color_bar = 'important';
		} elseif ($faults_porcent > 10) {
			$color_bar = 'warning';
		} elseif ($faults_porcent > 1) {
            $color_bar = 'info';
        }
		$results['color_bar'] = $color_bar;
		return $results;
	}

	/**
	 * Get results of faults average for all courses by user
	 * @param	int	   user id
	 * @return 	array  results containing number of faults, total done attendance, porcent of faults and color depend on result (red, orange)
	 */
	public function get_faults_average_inside_courses($user_id) {

		// get all courses of current user
		$courses = CourseManager::get_courses_list_by_user_id($user_id, true);

		$user_id = intval($user_id);
		$results = array();
		$total_faults = $total_weight = $porcent = 0;
		foreach ($courses as $course) {
			//$course_code = $course['code'];
			//$course_info = api_get_course_info($course_code);
			$course_id = $course['real_id'];
			$tbl_attendance_result 	= Database::get_course_table(TABLE_ATTENDANCE_RESULT);

			$attendances_by_course = $this->get_attendances_list($course_id);

			foreach ($attendances_by_course as $attendance) {
				// get total faults and total weight
				$total_done_attendance 	= $attendance['attendance_qualify_max'];
				$sql = "SELECT score FROM $tbl_attendance_result
                        WHERE c_id = $course_id AND user_id = $user_id AND attendance_id = ".$attendance['id'];
				$rs = Database::query($sql);
				$score = 0;
				if (Database::num_rows($rs) > 0) {
					$row = Database::fetch_array($rs);
					$score = $row['score'];
				}
				$faults = $total_done_attendance-$score;
				$faults = $faults > 0 ? $faults:0;
				$total_faults += $faults;
				$total_weight += $total_done_attendance;
			}
		}

		$porcent = $total_weight > 0 ?round(($total_faults*100)/$total_weight,0):0;
		$results['faults'] 	= $total_faults;
		$results['total']	= $total_weight;
		$results['porcent'] = $porcent;

		return $results;
	}

	/**
	 * Get results of faults average by course
	 * @param	int	   user id
	 * @param	int	   Session id (optional)
	 * @return 	array  results containing number of faults, total done attendance, porcent of faults and color depend on result (red, orange)
	 */
	public function get_faults_average_by_course($user_id, $courseId, $session_id = null) {
		// Database tables and variables
		$tbl_attendance_result 	= Database::get_course_table(TABLE_ATTENDANCE_RESULT);
		$user_id = intval($user_id);
        $courseId = intval($courseId);
		$results = array();
		$total_faults = $total_weight = $porcent = 0;
		$attendances_by_course = $this->get_attendances_list($courseId, $session_id);

		foreach ($attendances_by_course as $attendance) {
			// Get total faults and total weight
			$total_done_attendance 	= $attendance['attendance_qualify_max'];
			$sql = "SELECT score FROM $tbl_attendance_result WHERE c_id = $courseId AND user_id = $user_id AND attendance_id=".$attendance['id'];
			$rs = Database::query($sql);
			$score = 0;
			if (Database::num_rows($rs) > 0) {
				$row = Database::fetch_array($rs);
				$score = $row['score'];
			}
			$faults = $total_done_attendance-$score;
			$faults = $faults > 0 ? $faults:0;
			$total_faults += $faults;
			$total_weight += $total_done_attendance;
		}

		$porcent = $total_weight > 0 ?round(($total_faults*100)/$total_weight,0):0;
		$results['faults'] 	= $total_faults;
		$results['total']	= $total_weight;
		$results['porcent'] = $porcent;
		return $results;
	}

	/**
	 * Get registered users' attendance sheet inside current course
	 * @param	int	   attendance id
	 * @param	int	   user id for showing data for only one user (optional)
	 * @return 	array  users attendance sheet data
	 */
	public function get_users_attendance_sheet($attendance_id, $user_id = 0) {
		$tbl_attendance_sheet 	= Database::get_course_table(TABLE_ATTENDANCE_SHEET);
		$tbl_attendance_calendar= Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);

		$attendance_calendar = $this->get_attendance_calendar($attendance_id);
		$calendar_ids = array();
		// get all dates from calendar by current attendance
		foreach ($attendance_calendar as $cal) {
			$calendar_ids[] = $cal['id'];
		}

		$course_id = $this->get_course_int_id();

		$data = array();
		if (empty($user_id)) {
			// get all registered users inside current course
			$users = $this->get_users_rel_course();
			$user_ids = array_keys($users);
			if (count($calendar_ids) > 0 && count($user_ids) > 0) {
				foreach ($user_ids as $uid) {
					$sql = "SELECT * FROM $tbl_attendance_sheet
					        WHERE   c_id = $course_id AND
                                    user_id = '$uid' AND
                                    attendance_calendar_id IN(".implode(',', $calendar_ids).") ";
					$res = Database::query($sql);
					if (Database::num_rows($res) > 0) {
						while ($row = Database::fetch_array($res)) {
							$data[$uid][$row['attendance_calendar_id']]['presence'] = $row['presence'];
						}
					}
				}
			}
		} else {
			// Get attendance for current user
			$user_id = intval($user_id);
			if (count($calendar_ids) > 0) {
				$sql = "SELECT cal.date_time, att.presence
                        FROM $tbl_attendance_sheet att
                            INNER JOIN  $tbl_attendance_calendar cal
                            ON cal.id = att.attendance_calendar_id
						WHERE 	att.c_id = $course_id AND
								cal.c_id =  $course_id AND
								att.user_id = '$user_id' AND
								att.attendance_calendar_id IN(".implode(',',$calendar_ids).")
                        ORDER BY date_time";
				$res = Database::query($sql);
				if (Database::num_rows($res) > 0) {
					while ($row = Database::fetch_array($res)) {
						$row['date_time'] = api_convert_and_format_date($row['date_time'], null, date_default_timezone_get());
						$data[$user_id][] = $row;
					}
				}
			}
		}
		return $data;
	}

	/**
	 * Get next attendance calendar without presences (done attendances)
	 * @param	int	attendance id
	 * @return 	int attendance calendar id
	 */
	public function get_next_attendance_calendar_id($attendance_id) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$attendance_id = intval($attendance_id);
        $course_id = $this->get_course_int_id();

		$sql = "SELECT id FROM $tbl_attendance_calendar
		        WHERE c_id = $course_id AND attendance_id = '$attendance_id' AND done_attendance = 0 ORDER BY date_time limit 1";
		$rs = Database::query($sql);
		$next_calendar_id = 0;
		if (Database::num_rows($rs) > 0) {
			$row = Database::fetch_array($rs);
			$next_calendar_id = $row['id'];
		}
		return $next_calendar_id;
	}

	/**
	 * Get next attendance calendar datetime without presences (done attendances)
	 * @param	int	attendance id
	 * @return 	int UNIX time format datetime
	 */
	public function get_next_attendance_calendar_datetime($attendance_id) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
        $course_id = $this->get_course_int_id();
		$attendance_id = intval($attendance_id);
		$sql = "SELECT id, date_time FROM $tbl_attendance_calendar WHERE c_id = $course_id AND attendance_id = '$attendance_id' AND done_attendance = 0 ORDER BY date_time limit 1";
		$rs = Database::query($sql);
		$next_calendar_datetime = 0;
		if (Database::num_rows($rs) > 0) {
			$row = Database::fetch_array($rs);
			$next_calendar_datetime = api_get_local_time($row['date_time']);
		}
		return $next_calendar_datetime;
	}

	/**
	 * Get user' score from current attendance
	 * @param	int	user id
	 * @param	int attendance id
	 * @return	int score
	 */
	public function get_user_score($user_id, $attendance_id) {
		$tbl_attendance_result 	= Database::get_course_table(TABLE_ATTENDANCE_RESULT);
		$user_id = intval($user_id);
		$attendance_id = intval($attendance_id);
        $course_id = $this->get_course_int_id();
		$sql = "SELECT score FROM $tbl_attendance_result
                WHERE c_id = $course_id AND user_id='$user_id' AND attendance_id='$attendance_id'";
		$rs = Database::query($sql);
		$score = 0;
		if (Database::num_rows($rs) > 0) {
			$row = Database::fetch_array($rs);
			$score = $row['score'];
		}
		return $score;
	}

	/**
	 * Get attendance calendar data by id
	 * @param	int	attendance calendar id
	 * @return	array attendance calendar data
	 */
	public function get_attendance_calendar_by_id($calendar_id) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$calendar_id = intval($calendar_id);
        $course_id = $this->get_course_int_id();
		$sql = "SELECT * FROM $tbl_attendance_calendar WHERE c_id = $course_id AND id = '$calendar_id' ";
		$rs = Database::query($sql);
		$data = array();
		if (Database::num_rows($rs) > 0) {
			while ($row = Database::fetch_array($rs)) {
				$row['date_time'] = api_get_local_time($row['date_time']);
				$data = $row;
			}
		}
		return $data;
	}

	/**
	 * Get all attendance calendar data inside current attendance
	 * @param	int	attendance id
	 * @return	array attendance calendar data
	 */
	public function get_attendance_calendar($attendance_id, $type = 'all', $calendar_id = null) {
		global $dateFormatShort, $timeNoSecFormat;
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$attendance_id = intval($attendance_id);
        $course_id = $this->get_course_int_id();

		$sql = "SELECT * FROM $tbl_attendance_calendar WHERE c_id = $course_id AND attendance_id = '$attendance_id' ";
		if (!in_array($type, array('today', 'all', 'all_done', 'all_not_done','calendar_id'))) {
			$type = 'all';
		}
		switch ($type) {
		    case 'calendar_id':
		        $calendar_id = intval($calendar_id);
		        if (!empty($calendar_id)) {
		            $sql.= " AND id = $calendar_id";
		        }
		        break;
			case 'today':
				//$sql .= ' AND DATE_FORMAT(date_time,"%d-%m-%Y") = DATE_FORMAT("'.api_get_utc_datetime().'", "%d-%m-%Y" )';
				break;
			case 'all_done':
				$sql .= " AND done_attendance = 1 ";
				break;
			case 'all_not_done':
				$sql .= " AND done_attendance = 0 ";
				break;
			case 'all':
			default:
				break;
		}
		$sql .= " ORDER BY date_time ";

		$rs = Database::query($sql);
		$data = array();
		if (Database::num_rows($rs) > 0) {
			while ($row = Database::fetch_array($rs,'ASSOC')) {
                $row['db_date_time']    = $row['date_time'];
                $row['date_time']       = api_get_local_time($row['date_time']);
                $row['date']            = api_format_date($row['date_time'], DATE_FORMAT_SHORT);
                $row['time']            = api_format_date($row['date_time'], TIME_NO_SEC_FORMAT);
                if ($type == 'today') {
                    if (date('d-m-Y', api_strtotime($row['date_time'], 'UTC')) == date('d-m-Y', time())) {
                        $data[] = $row;
                    }
                } else {
                	$data[] = $row;
                }
			}
		}
		return $data;
	}


    /**
	 * Get number of attendance calendar inside current attendance
	 * @param	int	attendance id
	 * @return	int     number of dates in attendance calendar
	 */
	public function get_number_of_attendance_calendar($attendance_id) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$attendance_id = intval($attendance_id);
        $course_id = $this->get_course_int_id();
		$sql = "SELECT count(id) FROM $tbl_attendance_calendar
                WHERE c_id = $course_id AND attendance_id = '$attendance_id'";
		$rs = Database::query($sql);
        $row = Database::fetch_row($rs);
		$count = $row[0];
		return $count;
	}

        /**
	 * Get count dates inside attendance calendar by attendance id
	 * @param	int	attendance id
	 * @return	int     count of dates
	 */
	public function get_count_dates_inside_attendance_calendar($attendance_id) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$attendance_id = intval($attendance_id);
        $course_id = $this->get_course_int_id();
		$sql = "SELECT count(id) FROM $tbl_attendance_calendar
                WHERE c_id = $course_id AND attendance_id = '$attendance_id'";
		$rs = Database::query($sql);
		$count = 0;
		if (Database::num_rows($rs) > 0) {
            $row = Database::fetch_row($rs);
            $count = $row[0];
		}
		return $count;
	}


    /**
     * check if all calendar of an attendance is done
     * @param   int     attendance id
     * @return  bool    True if all calendar is done, otherwise false
     */
    public function is_all_attendance_calendar_done($attendance_id) {
        $attendance_id = intval($attendance_id);
        $done_calendar = $this->get_done_attendance_calendar($attendance_id);
        $count_dates_in_calendar = $this->get_count_dates_inside_attendance_calendar($attendance_id);
        $number_of_dates = $this->get_number_of_attendance_calendar($attendance_id);
        $result = false;
        if ($number_of_dates && (intval($count_dates_in_calendar) == intval($done_calendar))) {
            $result = true;
        }
        return $result;
    }

    /**
     * check if an attendance is locked
     * @param   int   attendance id
     * @param   bool
     */
    public static function is_locked_attendance($attendance_id) {
        //use gradebook lock
        $result = api_resource_is_locked_by_gradebook($attendance_id, LINK_ATTENDANCE);
        return $result;
    }

    public function get_attendance_calendar_data_by_date($attendance_id, $date_time) {
        $tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
         $course_id = $this->get_course_int_id();
         $attendance_id = intval($attendance_id);

        // check if datetime already exists inside the table
		$sql = "SELECT * FROM $tbl_attendance_calendar
		        WHERE   c_id = $course_id AND
                        date_time = '".Database::escape_string($date_time)."' AND
                        attendance_id = '$attendance_id'";
        $result = Database::query($sql);
        if (Database::num_rows($result)) {
            return Database::fetch_array($result, 'ASSOC');
        } else {
            return false;
        }
    }

	/**
	 * Add new datetime inside attendance calendar table
	 * @param	int	attendance id
	 * @return	int affected rows
	 */
	public function attendance_calendar_add($attendance_id, $return_inserted_id = false) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$affected_rows = 0;
        $cal_id = null;
		$attendance_id = intval($attendance_id);
        $course_id = $this->get_course_int_id();

        $calendar_info = $this->get_attendance_calendar_data_by_date($attendance_id, $this->date_time);

		if ($calendar_info == false) {
                        $aid = $this->get_last_attendance_calendar_id($course_id)+1;
			$sql = "INSERT INTO $tbl_attendance_calendar SET
					c_id			= $course_id,
                                        id			= $aid,
					date_time		= '".Database::escape_string($this->date_time)."',
					attendance_id 	= '$attendance_id'";
			$result = Database::query($sql);
			$cal_id = $aid;
			$affected_rows = Database::affected_rows($result);
		}

        // update locked attendance
        $is_all_calendar_done = $this->is_all_attendance_calendar_done($attendance_id);
        if (!$is_all_calendar_done) {
            $unlock = self::lock_attendance($attendance_id, false);
        } else {
            $unlock = self::lock_attendance($attendance_id);
        }
        if ($return_inserted_id) {
            return $cal_id;
        }
		return $affected_rows;
	}

    /**
     * save repeated date inside attendance calendar table
     * @param   int attendance id
     * @param   int start date in tms
     * @param   int end date in tms
     * @param   string  repeat type  daily, weekly, monthlyByDate

     */
    public function attendance_repeat_calendar_add($attendance_id, $start_date, $end_date, $repeat_type) {
        $attendance_id = intval($attendance_id);
        // save start date
        $datetimezone = api_get_utc_datetime($start_date);
        $this->set_date_time($datetimezone);
        $res = $this->attendance_calendar_add($attendance_id);

        //86400 = 24 hours in seconds
        //604800 = 1 week in seconds
        // Saves repeated dates
        switch($repeat_type) {
            case 'daily':
                $j = 1;
                for ($i = $start_date + 86400; ($i <= $end_date); $i += 86400) {
                    //$datetimezone = api_get_utc_date_add(api_get_utc_datetime($start_date), 0 , 0, $j);    //to support europe timezones
                    $datetimezone = api_get_utc_datetime($i);
                    $this->set_date_time($datetimezone);
                    $res = $this->attendance_calendar_add($attendance_id);
                    $j++;
                }
                break;
                exit;
            case 'weekly':
                $j = 1;
                for ($i = $start_date + 604800; ($i <= $end_date); $i += 604800) {
                    $datetimezone = api_get_utc_datetime($i);
                    //$datetimezone = api_get_utc_date_add(api_get_utc_datetime($start_date), 0 , 0, $j*7); //to support europe timezones
                    $this->set_date_time($datetimezone);
                    $res = $this->attendance_calendar_add($attendance_id);
                    $j++;
                }
                break;
            case 'monthlyByDate':
                $j = 1;
                //@todo fix bug with february
                for ($i = $start_date + 2419200; ($i <= $end_date); $i += 2419200) {
                    $datetimezone = api_get_utc_datetime($i);
                    //$datetimezone = api_get_utc_date_add(api_get_utc_datetime($start_date), 0 , $j); //to support europe timezones
                    $this->set_date_time($datetimezone);
                    $res = $this->attendance_calendar_add($attendance_id);
                    $j++;
                }
                break;
        }
    }

    /**
     * Adds x months to a UNIX timestamp
     * @param   int     The timestamp
     * @param   int     The number of years to add
     * @return  int     The new timestamp
     */
    private function add_month($timestamp, $num=1) {
        $values = api_get_utc_datetime($timestamp);
        $values = str_replace(array(':','-',' '), '/', $values);
        list($y, $m, $d, $h, $n, $s) = split('/',$values);
        if($m+$num>12) {
            $y += floor($num/12);
            $m += $num%12;
        } else {
            $m += $num;
        }
        //date_default_timezone_set('UTC');
       // return mktime($h, $n, $s, $m, $d, $y);
        $result = api_strtotime($y.'-'.$m.'-'.$d.' '.$h.':'.$n.':'.$s, 'UTC');
        if (!empty($result)) {
            return $result;
        }
        return false;
    }

	/**
	 * edit a datetime inside attendance calendar table
	 * @param	int	attendance calendar id
	 * @param	int	attendance id
	 * @return	int affected rows
	 */
	public function attendance_calendar_edit($calendar_id, $attendance_id) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$affected_rows = 0;
		$attendance_id = intval($attendance_id);
        $course_id = api_get_course_int_id();
		// check if datetime already exists inside the table
		$sql = "SELECT id FROM $tbl_attendance_calendar
		        WHERE c_id = $course_id AND date_time = '".Database::escape_string($this->date_time)."' AND attendance_id = '$attendance_id'";
		$rs = Database::query($sql);
		if (Database::num_rows($rs) == 0) {
			$sql = "UPDATE $tbl_attendance_calendar SET date_time='".Database::escape_string($this->date_time)."' WHERE c_id = $course_id AND id = '".intval($calendar_id)."'";
			$result = Database::query($sql);
			$affected_rows = Database::affected_rows($result);
		}

        // update locked attendance
        $is_all_calendar_done = self::is_all_attendance_calendar_done($attendance_id);
        if (!$is_all_calendar_done) {
            $unlock = self::lock_attendance($attendance_id, false);
        } else {
            $unlock = self::lock_attendance($attendance_id);
        }

		return $affected_rows;
	}

	/**
	 * delete a datetime from attendance calendar table
	 * @param	int		attendance calendar id
	 * @param	int		attendance id
	 * @param	bool	true for removing all calendar inside current attendance, false for removing by calendar id
	 * @return	int affected rows
	 */
	public function attendance_calendar_delete($calendar_id, $attendance_id , $all_delete = false) {
		$tbl_attendance_calendar = Database::get_course_table(TABLE_ATTENDANCE_CALENDAR);
		$tbl_attendance_sheet 	 = Database::get_course_table(TABLE_ATTENDANCE_SHEET);
		$session_id = api_get_session_id();
		$attendance_id = intval($attendance_id);
		// get all registered users inside current course
		$users = $this->get_users_rel_course();
		$user_ids = array_keys($users);
        $course_id = api_get_course_int_id();

		if ($all_delete) {
			$attendance_calendar = $this->get_attendance_calendar($attendance_id);
			$calendar_ids = array();
			// get all dates from calendar by current attendance
			if (!empty($attendance_calendar)) {
				foreach ($attendance_calendar as $cal) {
					// delete all data from attendance sheet
					$sql = "DELETE FROM $tbl_attendance_sheet WHERE c_id = $course_id AND attendance_calendar_id = '".intval($cal['id'])."'";
					Database::query($sql);
					// delete data from attendance calendar
					$sql = "DELETE FROM $tbl_attendance_calendar WHERE c_id = $course_id AND id = '".intval($cal['id'])."'";
					$result = Database::query($sql);
				}
			}
		} else {
			// delete just one row from attendance sheet by the calendar id
			$sql = "DELETE FROM $tbl_attendance_sheet WHERE c_id = $course_id AND attendance_calendar_id = '".intval($calendar_id)."'";
			Database::query($sql);
			// delete data from attendance calendar
			$sql = "DELETE FROM $tbl_attendance_calendar WHERE c_id = $course_id AND id = '".intval($calendar_id)."'";
			$result = Database::query($sql);
		}

		$affected_rows = Database::affected_rows($result);
		// update users' results
		$this->update_users_results($user_ids, $attendance_id);

		return $affected_rows;
	}

	/**
	 * buid a string datetime from array
	 * @param	array	array containing data e.g: $array('Y'=>'2010',  'F' => '02', 'd' => '10', 'H' => '12', 'i' => '30')
	 * @return	string	date and time e.g: '2010-02-10 12:30:00'
	 */
	public function build_datetime_from_array($array) {
        return Text::return_datetime_from_array($array);
	}

	/** Setters for fields of attendances tables **/
	public function set_session_id($session_id) {
		$this->session_id = intval($session_id);
	}

	public function set_course_id($course_id) {
		$this->course_id = Database::escape_string($course_id);
	}

	public function set_date_time($datetime) {
		$this->date_time = $datetime;
	}

	public function set_name($name) {
		$this->name = $name;
	}

	public function set_description($description) {
		$this->description = $description;
	}

	public function set_attendance_qualify_title($attendance_qualify_title) {
		$this->attendance_qualify_title = $attendance_qualify_title;
	}

	public function set_attendance_weight($attendance_weight) {
		$this->attendance_weight = $attendance_weight;
	}

	/** Getters for fields of attendances tables **/
	public function get_session_id() {
		return isset($this->session_id) ? $this->session_id : api_get_session_id();
	}

	public function get_course_id() {
        return isset($this->course_id) ? $this->course_id : api_get_course_id();
	}

	public function get_date_time() {
		return $this->date_time;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_description() {
		return $this->description;
	}

	public function get_attendance_qualify_title() {
		return $this->attendance_qualify_title;
	}

	public function get_attendance_weight() {
		return $this->attendance_weight;
	}

    public function get_course_int_id() {
		return isset($this->course_int_id) ? $this->course_int_id : api_get_course_int_id();
	}

    public function set_course_int_id($course_id) {
		$this->course_int_id = intval($course_id);
	}

    public function get_attendance_states() {
        $attendance_states = array(
            '1' => array('label' => get_lang('Present'), 'class' => 'btn-success') ,
            '2' => array('label' => get_lang('Late'), 'class' => 'btn-info') ,
            '3' => array('label' => get_lang('VeryLate'), 'class' => 'btn-warning') ,
            '0' => array('label' => get_lang('Absent'), 'class' => 'btn-danger') ,
            '4' => array('label' => get_lang('Default'), 'class' => 'btn') ,
        );
        return $attendance_states;
    }

    public function get_status_that_score_point_to_users() {
        return array(
            1, //presence
            2, //late
            3, //very late
        );
    }

    public function get_default_attendance_state() {
        return 4;
    }

    public function get_attendance_state_button($state_id, $add_label = false, $extra_attributes = array()) {
        $state_list = $this->get_attendance_states();
        if (isset($state_list[$state_id])) {
            $label = '&nbsp;';
            if ($add_label) {
                $label = $state_list[$state_id]['label'];
            }
            $default_class = isset($extra_attributes['class']) ? $extra_attributes['class'] : null;
            $extra_attributes['class'] = $default_class." btn ".$state_list[$state_id]['class'];
            return Display::url($label, '', $extra_attributes);
        }
        return null;
    }
    /**
     * Gets the last attendance ID for this course (helper for auto_increment substitution)
     * @param int Course ID (if not provided, will be guessed from context)
     * @result int 0 if none, the max "id" value for this course, if found
     */
    function get_last_attendance_id($course_id=null) 
    {
        $tbl_attendance = Database :: get_course_table(TABLE_ATTENDANCE);
        if (empty($course_id)) {
            $course_id = api_get_course_int_id();
        } else {
            $course_id = filter_var($course_id, FILTER_SANITIZE_NUMBER_INT);
        }
        $id = 0;
        $sql = "SELECT max(id) FROM $tbl_attendance WHERE c_id = $course_id";
        $res = Database::query($sql);
        if ($res === false or Database::num_rows($res)===0) {
            return $id;
        } //else
        $row = Database::fetch_row($res);
        return $row[0];        
    }
    /**
     * Gets the last attendance result ID for this course (helper for auto_increment substitution)
     * @param int Course ID (if not provided, will be guessed from context)
     * @result int 0 if none, the max "id" value for this course, if found
     */
    function get_last_attendance_result_id($course_id=null) 
    {
        $tbl_attendance = Database :: get_course_table(TABLE_ATTENDANCE_RESULT);
        if (empty($course_id)) {
            $course_id = api_get_course_int_id();
        } else {
            $course_id = filter_var($course_id, FILTER_SANITIZE_NUMBER_INT);
        }
        $id = 0;
        $sql = "SELECT max(id) FROM $tbl_attendance WHERE c_id = $course_id";
        $res = Database::query($sql);
        if ($res === false or Database::num_rows($res)===0) {
            return $id;
        } //else
        $row = Database::fetch_row($res);
        return $row[0];        
    }
    /**
     * Gets the last attendance sheet log ID for this course (helper for auto_increment substitution)
     * @param int Course ID (if not provided, will be guessed from context)
     * @result int 0 if none, the max "id" value for this course, if found
     */
    function get_last_attendance_sheet_log_id($course_id=null) 
    {
        $tbl_attendance = Database :: get_course_table(TABLE_ATTENDANCE_SHEET_LOG);
        if (empty($course_id)) {
            $course_id = api_get_course_int_id();
        } else {
            $course_id = filter_var($course_id, FILTER_SANITIZE_NUMBER_INT);
        }
        $id = 0;
        $sql = "SELECT max(id) FROM $tbl_attendance WHERE c_id = $course_id";
        $res = Database::query($sql);
        if ($res === false or Database::num_rows($res)===0) {
            return $id;
        } //else
        $row = Database::fetch_row($res);
        return $row[0];        
    }
    /**
     * Gets the last attendance calendar ID for this course (helper for auto_increment substitution)
     * @param int Course ID (if not provided, will be guessed from context)
     * @result int 0 if none, the max "id" value for this course, if found
     */
    function get_last_attendance_calendar_id($course_id=null) 
    {
        $tbl_attendance = Database :: get_course_table(TABLE_ATTENDANCE_CALENDAR);
        if (empty($course_id)) {
            $course_id = api_get_course_int_id();
        } else {
            $course_id = filter_var($course_id, FILTER_SANITIZE_NUMBER_INT);
        }
        $id = 0;
        $sql = "SELECT max(id) FROM $tbl_attendance WHERE c_id = $course_id";
        $res = Database::query($sql);
        if ($res === false or Database::num_rows($res)===0) {
            return $id;
        } //else
        $row = Database::fetch_row($res);
        return $row[0];        
    }
}
