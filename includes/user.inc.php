<?php


function bo_show_login()
{
	global $_BO;

	if (!defined('BO_LOGIN_ALLOW') || (BO_LOGIN_ALLOW != 1 && BO_LOGIN_ALLOW != 2))
	{
		echo _BL('Login not allowed');
		return;
	}

	$login_fail = false;

	$remove_vars = array('bo_*','login','id');

	if (bo_user_get_id())
	{
		$level = bo_user_get_level();
		$show = $_GET['bo_action'];

		echo '<ul id="bo_menu">';

		echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=" class="bo_navi'.($show == '' ? '_active' : '').'">'._BL('Start').'</a>';
		if (bo_user_get_id() > 1 && $_SESSION['bo_external_login'] !== true)
			echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=password" class="bo_navi'.($show == 'password' ? '_active' : '').'">'._BL('Password').'</a>';

		if (BO_PERM_ADMIN & $level)
			echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=user_settings" class="bo_navi'.($show == 'user_settings' ? '_active' : '').'">'._BL('Add/Remove User').'</a>';

		if (BO_PERM_ADMIN & $level)
			echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=admin" class="bo_navi'.($show == 'admin' ? '_active' : '').'">'._BL('Administration').'</a>';

		if (defined('BO_ALERTS') && BO_ALERTS && ($level & BO_PERM_ALERT))
			echo '<li><a href="'.bo_insert_url($remove_vars).'&bo_action=alert" class="bo_navi'.($show == 'alert' ? '_active' : '').'" class="bo_navi'.($show == 'alert' ? '_active' : '').'">'._BL('Strike alert').'</a></li>';

		echo '</ul>';

		if (bo_user_get_id() == 1)
		{
			include 'update.inc.php';

			if (bo_check_for_update() == true)
			{
				bo_copyright_footer();
				return;
			}
		}

		switch($show)
		{

			case 'admin':
				if (BO_PERM_ADMIN & $level)
					bo_user_show_admin();
				break;

			case 'user_settings':
				if (BO_PERM_ADMIN & $level)
					bo_user_show_useradmin();
				break;

			case 'password':
				if (bo_user_get_id() > 1)
					bo_user_show_passw_change();
				break;

			case 'alert':
				if (BO_PERM_ALERT & $level)
					bo_alert_settings();
				break;



			default:

				$lastlogin = bo_get_conf_user('lastlogin');
				$sessiontime = time() - $_SESSION['bo_login_time'];

				echo '<h3>'._BL('Welcome to MyBlitzortung user area').'!</h3>';
				echo '<ul class="bo_login_info">';
				echo '<li>'._BL('user_welcome_text').': <strong>'._BC(bo_user_get_name()).'</strong></li>';
 
				if ($lastlogin && $_SESSION['bo_external_login'] !== true)
					echo '<li>'._BL('user_lastlogin_text').': <strong>'._BDT($lastlogin).'</strong></li>';

				echo '<li>'._BL('user_sessiontime_text').': <strong>'._BN($sessiontime / 60, 1).' '._BL('unit_minutes').'</strong></li>';
				echo '</ul>';

				if (BO_PERM_ADMIN & $level)
				{
					if (file_exists(BO_DIR.'settings.php'))
						echo '<p style="color:red"><strong>Warning: File <u>settings.php</u> found!</strong><br>Since version 0.3.1 standard values and settings are saved internally. For individual setting edit config.php and enter your individual settings there. Delete settings.php to hide this message.</p>';
				}

				echo '<h4>'._BL('Version information').'</h4>';
				echo '<ul>';
				echo '<li>'._BL('MyBlitzortung version').': <strong>'.bo_get_conf('version').'</strong></li>';
				if (BO_PERM_ADMIN & $level)
				{
					$res = BoDb::query("SHOW VARIABLES LIKE 'version'");
					$row = $res->fetch_assoc();
					$mysql_ver = $row['Value'];

					echo '<li>'._BL('PHP version').': '.phpversion().' (<a href="'.bo_insert_url($remove_vars).'&bo_action=phpinfo" target="_blank">'._BL('Show PHP info').'</a>)</li>';
					echo '<li>'._BL('MySQL version').': '.$mysql_ver.'</li>';
				}
				echo '</ul>';

				break;
		}



	}
	else
	{
		bo_show_login_form($_BO['login_fail']);
	}

	bo_copyright_footer();

}

function bo_user_show_admin()
{
	$show = $_GET['bo_action_admin'];
	$url = bo_insert_url(array('bo_*','login','id')).'&bo_action=admin&bo_action_admin=';

	switch($show)
	{
		case 'calibrate':
			bo_show_calibrate_antennas();
			break;

		case 'mybo_station_update':
			bo_my_station_update_form();
			break;

		case 'update':
			echo '<h4>'._BL('Importing data...').'</h4>';
			echo '<div style="border: 1px solid #999; padding: 10px; font-size:8pt;"><pre>';
			bo_update_all(true, $_GET['bo_only']);
			echo '</div></pre>';
			break;

		case 'cache_info':
			bo_cache_info();
			break;

		case 'strike_keys':
			echo '<h4>'._BL('Updating database keys...').'</h4>';
			echo '<div style="border: 1px solid #999; padding: 10px; font-size:8pt;"><pre>';
			bo_db_recreate_strike_keys();
			echo '</div></pre>';
			break;

		case 'cities':
			bo_import_cities();
			break;

		default:

			echo '<h4>'._BL('Admin tools').'</h4>';

			echo '<ul>';
			echo '<li><a href="'.$url.'mybo_station_update">'._BL('Update MyBlitzortung Stations').'</a></li>';
			echo '<li><a href="'.$url.'cache_info">'._BL('File cache info').'</a></li>';
			echo '<li><a href="'.$url.'cities">'._BL('Read cities.txt').'</a></li>';
			echo '<li><a href="'.$url.'calibrate" class="bo_navi'.($show == 'calibrate' ? '_active' : '').'">'._BL('Calibrate Antennas').'</a>';
			echo '</ul>';

			echo '<h5>'._BL('Import/update data').'</h5>';
			echo '<ul>';
			echo '<li><strong><a href="'.$url.'update">'._BL('Do manual update').'</strong></a></li>';
			echo '<li><a href="'.$url.'update&bo_only=strikes">'._BL('Update only strikes').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=stations">'._BL('Update only stations').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=signals">'._BL('Update only signals').'</a></li>';
			//echo '<li><a href="'.$url.'update&bo_only=daily">'._BL('Update only daily statistics').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=density">'._BL('Update only densities').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=tracks">'._BL('Update only tracks').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=download">'._BL('Download only external files').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=purge">'._BL('Force data purge only').'</a></li>';
			echo '<li><a href="'.$url.'update&bo_only=alerts">'._BL('Check alerts only').'</a></li>';
			echo '<li><a href="'.$url.'strike_keys">'._BL('Update database keys').'</a></li>';
			echo '</ul>';

			echo '<h5>'._BL('Specials').'</h5>';
			echo '<ul>';
			echo '<li><a href="'.bo_bofile_url().'?kml">Google Earth KML</a></li>';
			echo '</ul>';

			echo '<h5>'._BL('Documentation').'</h5>';
			echo '<ul>';
			echo '<li><a href="'.dirname(BO_FILE).'/README" target="_blank">README</a></li>';
			echo '<li><a href="http://www.myblitzortung.org" target="_blank">www.myblitzortung.org</a></li>';
			echo '<li><a href="http://www.wetter-board.de/index.php?page=Board&boardID=381" target="_blank">Board</a></li>';
			echo '<li><a href="http://www.faq-blitzortung.org/index.php?sid=267611&lang=de&action=show&cat=18" target="_blank">FAQ</a></li>';
			echo '</ul>';

			echo '<h5>'._BL('Links').'</h5>';
			echo '<ul>';

/*
			$bo_login_id = bo_get_conf('bo_login_id');
			if ($bo_login_id)
				echo '<li><a href="http://www.blitzortung.org/Webpages/index.php?page=3&login_string='.$bo_login_id.'" target="_blank">Blitzortung.org</a> (with your Login-String)</li>';
*/

			echo '</ul>';

			break;
	}


}

function bo_show_login_form($fail = false)
{
	global $_BO;

	echo '<div id="bo_login">';

	if ($fail)
		echo '<div class="bo_info_fail">'._BL('Login fail!').'</div>';

	echo '<form action="'.bo_insert_url('bo_logout').'" method="POST" class="bo_login_form">';

	echo '<fieldset class="bo_login_fieldset">';
	echo '<legend>'._BL('login_legend').'</legend>';

	echo '<span class="bo_form_descr">'._BL('Login').':</span>';
	echo '<input type="text" name="bo_user" id="bo_login_user" class="bo_form_text bo_login_input">';

	echo '<span class="bo_form_descr">'._BL('Password').':</span>';
	echo '<input type="password" name="bo_pass" id="bo_login_pass" class="bo_form_text bo_login_input">';

	if (!$_BO['headers_sent'] && intval(BO_LOGIN_COOKIE_TIME))
	{
		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" value="1" name="bo_login_cookie" id="bo_login_cookie_check" class="bo_form_checkbox">';
		echo '<label for="bo_login_cookie_check" class="bo_form_descr_checkbox">'._BL('stay logged in').'</label>';
		echo '</span>';
	}

	echo '<input type="submit" name="ok" value="'._BL('Login').'" id="bo_login_submit" class="bo_form_submit">';

	echo '<input type="hidden" name="bo_do_login" value="1">';

	echo '</fieldset>';

	echo '</form>';

	echo '</div>';

}

function bo_user_do_login($user, $pass, $cookie, $md5pass = false)
{
	$pass = trim($pass);

	if (!$user || !$pass)
		return false;

	if (BO_LOGIN_ALLOW > 0 && $user == BO_USER && defined('BO_USER') && strlen(BO_USER))
	{
		if ( ($pass == BO_PASS || ($md5pass && $pass == md5(BO_PASS))) && defined('BO_PASS') && strlen(BO_PASS))
		{
			if (!$md5pass)
				$pass = md5($pass);

			bo_user_set_session(1, pow(2, BO_PERM_COUNT) - 1, $cookie, $pass);
			return true;
		}
	}

	if (BO_LOGIN_ALLOW == 2)
	{
		if ($md5pass == false)
			$pass = md5($pass);

		$res = BoDb::query("SELECT id, login, level FROM ".BO_DB_PREF."user WHERE login='$user' AND password='$pass'");

		if ($res->num_rows == 1)
		{
			$row = $res->fetch_assoc();
			if ($row['id'] > 1)
			{
				bo_user_set_session($row['id'], $row['level'], $cookie, $pass);
				return true;
			}
		}

	}

	return false;
}

function bo_user_do_login_byid($id, $pass)
{
	$id = intval($id);

	if ($id == 1)
	{
		$user = BO_USER;
	}
	else
	{
		$row = BoDb::query("SELECT login FROM ".BO_DB_PREF."user WHERE id='$id'")->fetch_assoc();
		$user = $row['login'];
	}

	bo_user_do_login($user, $pass, false, true);
}

function bo_user_do_logout()
{
	if ($_COOKIE['bo_login'] && !$_BO['headers_sent'])
		setcookie("bo_login", '', time()+3600*24*9999,'/');

	bo_set_conf_user('cookie', '');

	$_SESSION['bo_user'] = 0;
	$_SESSION['bo_user_level'] = 0;
	$_SESSION['bo_logged_out'] = true;
	$_SESSION['bo_login_time'] = 0;
	
	unset($_SESSION['bo_external_login']);
	unset($_SESSION['bo_external_name']);
	
}

function bo_user_set_session($id, $level, $cookie, $pass='')
{
	$_SESSION['bo_user'] = $id;
	$_SESSION['bo_user_level'] = $level;
	$_SESSION['bo_logged_out'] = false;
	$_SESSION['bo_login_time'] = time();

	$cookie_days = intval(BO_LOGIN_COOKIE_TIME);

	if ($cookie && !$_BO['headers_sent'] && $cookie_days)
	{
		$data = unserialize(bo_get_conf_user('cookie', $id));

		if (!$data['uid'])
			$data['uid'] = md5(uniqid('', true));

		$data['pass'] = $pass;
		bo_set_conf_user('user_cookie', serialize($data), $id);

		setcookie("bo_login", $id.'_'.$data['uid'], time()+3600*24*$cookie_days,'/');
	}

	$lastlogin = bo_get_conf_user('lastlogin_next', $id);
	bo_set_conf_user('lastlogin', $lastlogin, $id);
	bo_set_conf_user('lastlogin_next', time(), $id);
}

function bo_user_get_id()
{
	return $_SESSION['bo_user'];
}

function bo_user_get_level($user_id = 0)
{
	if (!$user_id)
		return $_SESSION['bo_user_level'];

	if ($user_id == 1)
		return pow(2, BO_PERM_COUNT) - 1;

	$res = BoDb::query("SELECT level FROM ".BO_DB_PREF."user WHERE id='".intval($user_id)."'");
	$row = $res->fetch_assoc();

	return $row['level'];
}

function bo_user_get_name($user_id = 0)
{
	static $names;

	if (!$user_id)
		$user_id = $_SESSION['bo_user'];

	if ($user_id == 1)
		return BO_USER;

	if (!isset($names[$user_id]))
	{
		$res = BoDb::query("SELECT login FROM ".BO_DB_PREF."user WHERE id='".intval($user_id)."'");
		$row = $res->fetch_assoc();
		$names[$user_id] = $row['login'];
		
		if ($_SESSION['bo_external_login'] === true && trim($_SESSION['bo_external_name']))
		{
			$names[$user_id] .= ' ('.$_SESSION['bo_external_name'].')';
		}
	}

	return $names[$user_id];
}

function bo_user_get_mail($user_id = 0)
{
	static $mails;

	if (!$user_id)
		$user_id = $_SESSION['bo_user'];

	if (!isset($mails[$user_id]))
	{
		$res = BoDb::query("SELECT mail FROM ".BO_DB_PREF."user WHERE id='".intval($user_id)."'");
		$row = $res->fetch_assoc();
		$mails[$user_id] = $row['mail'];
	}

	return $mails[$user_id];
}

function bo_user_show_passw_change()
{
	if ($_POST['ok'])
	{
		$pass1 = bo_gpc_prepare($_POST['pass1']);
		$pass2 = bo_gpc_prepare($_POST['pass2']);

		if ($pass1 && $pass2 && $pass1 == $pass2)
		{
			$pass = md5($pass1);
			BoDb::query("UPDATE ".BO_DB_PREF."user SET password='$pass' WHERE id='".bo_user_get_id()."'");
			echo '<div class="bo_info_ok">';
			echo _BL('Password changed!');
			echo '</div>';
		}
		else
		{
			echo '<div class="bo_info_fail">';
			echo _BL('Password was not changed!');
			echo '</div>';
		}
	}

	echo '<h3>'._BL('Change password').'</h3>';

	echo '<form action="'.bo_insert_url().'" method="POST" class="bo_admin_user_form">';

	echo '<fieldset class="bo_admin_user_fieldset">';
	echo '<legend>'._BL('user_change_passw_legend').'</legend>';

	echo '<span class="bo_form_descr">'._BL('New password').':</span>';
	echo '<input type="password" name="pass1" value="" id="bo_change_pass1" class="bo_form_text bo_form_input">';

	echo '<span class="bo_form_descr">'._BL('Repeat password').':</span>';
	echo '<input type="password" name="pass2" value="" id="bo_change_pass1" class="bo_form_text bo_form_input">';

	echo '<input type="submit" name="ok" value="'._BL('Change').'" id="bo_user_admin_submit" class="bo_form_submit">';
	echo '</fieldset>';

	echo '</form>';
}

function bo_user_show_useradmin()
{
	$user_id = intval($_GET['id']);
	$failure = false;

	if (isset($_POST['bo_admin_user']) && (bo_user_get_level() & BO_PERM_ADMIN) )
	{
		$user_id = intval($_POST['user_id']);

		$new_user_login = BoDb::esc(bo_gpc_prepare($_POST['bo_user_login']));
		$new_user_pass = BoDb::esc(bo_gpc_prepare($_POST['bo_user_pass']));
		$new_user_mail = BoDb::esc(bo_gpc_prepare($_POST['bo_user_mail']));

		if ($user_id == 1 || $new_user_login)
		{
			$new_user_level = 0;
			if (is_array($_POST['bo_user_perm']))
			{
				foreach($_POST['bo_user_perm'] as $perm => $checked)
				{
					if ($checked)
						$new_user_level += $perm;
				}
			}

			$sql = " ".BO_DB_PREF."user SET mail='$new_user_mail' ";

			if ($user_id != 1)
			{
				$sql .= ", login='$new_user_login', level='$new_user_level' ";

				if (strlen(trim($new_user_pass)))
				{
					$new_user_pass = md5($new_user_pass);
					$sql .= ", password='$new_user_pass'";
				}
			}

			//To be sure, if creation of main user during install failed
			BoDb::query("INSERT IGNORE INTO ".BO_DB_PREF."user SET id=1", false);

			if ($user_id)
				$ok = BoDb::query("UPDATE $sql WHERE id='$user_id'", false);
			else
				$ok = BoDb::query("INSERT INTO $sql", false);

			if (!$ok)
				$failure = true;

			$user_id = 0;
		}
		else
		{
			$user_login = $new_user_login;
			$user_pass = $new_user_pass;
			$user_mail = $new_user_mail;
		}
	}

	if ($_GET['bo_action2'] == 'delete' && $user_id > 1 && (bo_user_get_level() & BO_PERM_ADMIN) )
	{
		BoDb::query("DELETE FROM ".BO_DB_PREF."user WHERE id='$user_id'");
		BoDb::query("DELETE FROM ".BO_DB_PREF."conf WHERE name LIKE 'alert_$user_id%'");
		$user_id = 0;
	}


	echo '<div id="bo_user_admin">';

	echo '<h3>'._BL('User list').'</h3>';
	echo '<table class="bo_table" id="bo_user_table">';
	echo '<tr>
			<th rowspan="2">ID</th>
			<th rowspan="2">'._BL('Login').'</th>
			<th rowspan="2">'._BL('E-Mail').'</th>
			<th colspan="'.BO_PERM_COUNT.'">'._BL('Level').'</th>
			<th rowspan="2">'._BL('Delete').'</th>
			<th rowspan="2">'._BL('Alert').'</th>
			</tr>';

	for ($i=0; $i<BO_PERM_COUNT;$i++)
	{
		echo '<th>'.($i+1).'</th>';
	}

	$sql = "SELECT id, login, password, level, mail
			FROM ".BO_DB_PREF."user
			ORDER BY id
			";
	$res = BoDb::query($sql);
	while ($row = $res->fetch_assoc())
	{
		if ($row['id'] == 1)
		{
			$row['login'] = BO_USER;
			$row['pass'] = BO_PASS;
			$row['level'] = pow(2, BO_PERM_COUNT) - 1;
		}

		echo '<tr>
			<td><a href="'.bo_insert_url(array('bo_action2', 'id')).'&id='.$row['id'].'">'.$row['id'].'</a></td>
			<td>'._BC($row['login']).'</td>
			<td>'._BC($row['mail']).'</td>';

		for ($i=0; $i<BO_PERM_COUNT;$i++)
		{
			$l = pow(2, $i);
			echo '<td>'.(($row['level'] & $l) ? 'X' : '-').'</td>';
		}

		echo '<td>';

		if ($row['id'] > 1)
			echo '<a href="'.bo_insert_url(array('bo_action2')).'&bo_action2=delete&id='.$row['id'].'" style="color:red" onclick="return confirm(\''._BL('Sure?').'\');">X</a>';

		echo '</td>';

		echo '<td><a href="'.bo_insert_url(array('bo_action', 'bo_action2')).'&bo_action=alert&bo_action2=alert_form%2C'.$row['id'].'">'._BL('new').'</a></td>';

		echo '</tr>';

		if ($user_id == $row['id'])
		{
			$user_mail = $row['mail'];
			$user_level = $row['level'];
			$user_login = $row['login'];
		}

	}

	echo '</table>';

	if ($user_id == 1)
	{
		$disabled = ' disabled="disabled"';
	}

	if ($failure)
		echo '<div class="bo_info_fail">'._BL('Failure!').'</div>';


	echo '<form action="'.bo_insert_url(array('bo_logout', 'id', 'bo_action2')).'" method="POST" class="bo_admin_user_form">';

	echo '<fieldset class="bo_admin_user_fieldset">';
	echo '<legend>'._BL('admin_user_legend').'</legend>';

	echo '<span class="bo_form_descr">'._BL('Login').':</span>';
	echo '<input type="text" name="bo_user_login" value="'._BC($user_login).'" id="bo_user_login" class="bo_form_text bo_admin_input" '.$disabled.'>';

	echo '<span class="bo_form_descr">'._BL('Password').':</span>';
	echo '<input type="password" name="bo_user_pass" value="'._BC($user_pass).'" id="bo_user_login" class="bo_form_text bo_admin_input" '.$disabled.'>';

	//echo '<span class="bo_form_descr">'._BL('Level').':</span>';
	//echo '<input type="text" name="bo_user_level" value="'._BC($user_level).'" id="bo_user_level" class="bo_form_text bo_admin_input" '.$disabled.'>';

	echo '<span class="bo_form_descr">'._BL('E-Mail').':</span>';
	echo '<input type="text" name="bo_user_mail"  value="'._BC($user_mail).'" id="bo_user_mail" class="bo_form_text bo_login_input">';

	echo '<span class="bo_form_descr">'._BL('Level').':</span>';
	echo '<div class="bo_input_container">';
	for ($i=0; $i<BO_PERM_COUNT;$i++)
	{
		$l = pow(2, $i);

		echo '<span class="bo_form_checkbox_text">';
		echo '<input type="checkbox" value="1" name="bo_user_perm['.$l.']" id="bo_user_perm'.$i.'" class="bo_form_checkbox" '.$disabled.(($user_level & $l) ? ' checked="checked"' : '').'>';
		echo '<label for="bo_user_perm'.$i.'" class="bo_form_descr_checkbox">'._BL('user_perm'.$i).'&nbsp;('.($i+1).')</label>';
		echo '</span>';
	}
	echo '</div>';
	echo '<input type="hidden" name="bo_admin_user" value="1">';
	echo '<input type="hidden" name="user_id" value="'.$user_id.'">';

	echo '<input type="submit" name="ok" value="'._BL('Add/Edit').'" id="bo_user_admin_submit" class="bo_form_submit">';


	echo '</fieldset>';

	echo '</form>';


	echo '</div>';

}

function bo_show_calibrate_antennas()
{
	$channels = bo_get_conf('raw_channels');

	if (!$_POST['bo_calibrate'])
	{
		if ($_POST['bo_calibrate_manual'] && (bo_user_get_level() & BO_PERM_ADMIN))
		{
			if (strlen(trim($_POST['bo_antenna1_bearing'])))
				bo_set_conf('antenna1_bearing', (double)$_POST['bo_antenna1_bearing']);

			if (strlen(trim($_POST['bo_antenna2_bearing'])))
				bo_set_conf('antenna2_bearing', (double)$_POST['bo_antenna2_bearing']);

			if (strlen(trim($_POST['bo_antenna1_bearing_elec'])))
				bo_set_conf('antenna1_bearing_elec', (double)$_POST['bo_antenna1_bearing_elec']);

			if (strlen(trim($_POST['bo_antenna2_bearing_elec'])))
				bo_set_conf('antenna2_bearing_elec', (double)$_POST['bo_antenna2_bearing_elec']);
		}

		echo '<h3>'._BL('Manual antenna calibration').'</h3>';

		echo '<form action="'.bo_insert_url().'" method="POST" class="bo_admin_user_form">';

		echo '<fieldset class="bo_admin_user_fieldset">';
		echo '<legend>'._BL('admin_calibrate_manual_legend').'</legend>';

		echo '<span class="bo_form_descr">'._BL('Antenna 1 bearing').' (0-180&deg;):</span>';
		echo '<input type="text" name="bo_antenna1_bearing" value="'.(double)bo_get_conf('antenna1_bearing').'" id="bo_antenna1_bearing_id" class="bo_form_text bo_form_input">';
		echo '<span class="bo_form_descr">'._BL('Antenna 2 bearing').' (0-180&deg;):</span>';
		echo '<input type="text" name="bo_antenna2_bearing" value="'.(double)bo_get_conf('antenna2_bearing').'" id="bo_antenna2_bearing_id" class="bo_form_text bo_form_input">';

		echo '<span class="bo_form_descr">'._BL('Antenna 1 electrical bearing').' (0-360&deg;):</span>';
		echo '<input type="text" name="bo_antenna1_bearing_elec" value="'.(double)bo_get_conf('antenna1_bearing_elec').'" id="bo_antenna1_elec_bearing_id" class="bo_form_text bo_form_input">';
		echo '<span class="bo_form_descr">'._BL('Antenna 2 electrical bearing').' (0-360&deg;):</span>';
		echo '<input type="text" name="bo_antenna2_bearing_elec" value="'.(double)bo_get_conf('antenna2_bearing_elec').'" id="bo_antenna2_elec_bearing_id" class="bo_form_text bo_form_input">';


		echo '<input type="submit" name="bo_calibrate_manual" value="'._BL('Ok').'" id="bo_admin_submit" class="bo_form_submit">';

		echo '</fieldset>';
		echo '</form>';
	}

	/*** Auto-calibration begins here ***/
	if ($channels == 2)
	{

		$dist = intval($_POST['bo_max_dist']) * 1000;
		$age = (double)$_POST['bo_max_age'];
		$limit = intval($_POST['bo_limit']);
		$limit = $limit ? $limit : 5000;

		if (!$dist && intval(BO_EXPERIMENTAL_POLARITY_MAX_DIST))
			$dist = intval(BO_EXPERIMENTAL_POLARITY_MAX_DIST) * 1000;

		echo '<h3>'._BL('Automatic antenna calibration').'</h3>';

		echo '<form action="'.bo_insert_url().'" method="POST" class="bo_admin_user_form">';

		echo '<fieldset class="bo_admin_user_fieldset">';
		echo '<legend>'._BL('admin_calibrate_legend').'</legend>';

		echo '<span class="bo_form_descr">'._BL('Limit').':</span>';
		echo '<input type="text" name="bo_limit" value="'.$limit.'" id="bo_calibrate_limit" class="bo_form_text bo_form_input">';

		echo '<span class="bo_form_descr">'._BL('Max Distance (Kilometers)').':</span>';
		echo '<input type="text" name="bo_max_dist" value="'.($dist ? $dist/1000 : '').'" id="bo_calibrate_dist" class="bo_form_text bo_form_input">';

		echo '<span class="bo_form_descr">'._BL('Max Age (Days)').':</span>';
		echo '<input type="text" name="bo_max_age" value="'.($age ? $age : 10).'" id="bo_calibrate_age" class="bo_form_text bo_form_input">';


		echo '<input type="submit" name="bo_calibrate" value="'._BL('Calculate').'" id="bo_admin_submit" class="bo_form_submit">';

		echo '</fieldset>';
		echo '</form>';

		$count = null;
		if ($_POST['bo_calibrate'])
		{
			$min_sig = 0.1;
			$count = 0;
			$ant_alpha[0] = array();
			$ant_alpha[1] = array();

			$sql = "SELECT r.id raw_id, s.time strike_time, s.lat lat, s.lon lon, s.current current, r.data data
					FROM ".BO_DB_PREF."raw r, ".BO_DB_PREF."strikes s
					WHERE r.strike_id=s.id
						".($dist ? " AND s.distance < $dist " : '')."
						".($age ? " AND s.time > '".gmdate('Y-m-d H:i:s', time() - 3600 * 24 * $age)."' " : '')."
					ORDER BY RAND()
					LIMIT $limit";
			$res = BoDb::query($sql);
			while ($row = $res->fetch_assoc())
			{
				$bearing = bo_latlon2bearing($row['lat'], $row['lon']);

				/*** find direction (0-180�) ***/
				for($i=0;$i<2;$i++)
				{
					//only the first sample of each channel
					$signal = (ord(substr($row['data'],$i,1)) - 128) / 128;
					$ant[$i] = $signal;

				}

				if ( ($ant[0] == 0 && $ant[1] == 0) || (abs($ant[0]) < $min_sig && abs($ant[1]) < $min_sig) )
					continue;

				if (abs($ant[0]) < abs($ant[1]))
				{
					$calc_ant = 0;
					$ratio = $ant[0] / ($ant[1] ? $ant[1] : 1E-9);
				}
				else
				{
					$calc_ant = 1;
					$ratio = $ant[1] / ($ant[0] ? $ant[0] : 1E-9);
				}

				if (abs($ratio) < 0.02)
					$ant_alpha[$calc_ant][] = $bearing % 180;

				/*
				echo '<p><img src="'.BO_FILE.'?graph='.$row['raw_id'].'" style="width:'.BO_GRAPH_RAW_W.'px;height:'.BO_GRAPH_RAW_H.'px"></p>';
				*/

				for($i=0;$i<2;$i++)
				{
					if ($signal != 0)
					{
						$sign[$i][intval($bearing)][$signal > 0 ? 1 : -1]++;
						$current[$i][intval($bearing)][$signal > 0 ? 1 : -1][] = $row['current'];
					}
				}

				$count++;
			}
		}

		if ($count === 0)
		{
			echo '<h3>'._BL('Results').'</h3>';
			echo '<ul>';
			echo '<li>'._BL('No strikes found!').'</li>';
			echo '</ul>';
		}
		elseif ($count)
		{
			$alpha[0] = null;
			if (count($ant_alpha[0]))
				$alpha[0] = round(array_sum($ant_alpha[0]) / count($ant_alpha[0]),1);

			$alpha[1] = null;
			if (count($ant_alpha[1]))
				$alpha[1] = round(array_sum($ant_alpha[1]) / count($ant_alpha[1]),1);

			echo '<form action="'.bo_insert_url().'" method="POST" class="bo_admin_user_form">';

			echo '<h3>'._BL('Results').'</h3>';

			echo '<ul>';
			echo '<li>'._BL('Analyzed').': '.$count.' '._BL('random datasets').'</li>';
			echo '</ul>';

			echo '<h4>'._BL('Direction').'</h4>';
			echo '<ul>';
			echo '<li>'._BL('Antenna').' 1: '.$alpha[0].'&deg; ('._BL(bo_bearing2direction($alpha[0])).' <-> '._BL(bo_bearing2direction($alpha[0] + 180)).')';

			if ((bo_user_get_level() & BO_PERM_ADMIN))
				echo '<input type="text" name="bo_antenna1_bearing" value="'.$alpha[0].'" id="bo_antenna1_bearing_id" class="bo_form_text bo_form_input">';

			echo '</li>';
			echo '<li>'._BL('Antenna').' 2: '.$alpha[1].'&deg; ('._BL(bo_bearing2direction($alpha[1])).' <-> '._BL(bo_bearing2direction($alpha[1] + 180)).')';

			if ((bo_user_get_level() & BO_PERM_ADMIN))
				echo '<input type="text" name="bo_antenna2_bearing" value="'.$alpha[1].'" id="bo_antenna2_bearing_id" class="bo_form_text bo_form_input">';

			echo '</li>';
			echo '<li>'._BL('Difference').': '.abs($alpha[1]-$alpha[0]).'&deg;</li>';

			echo '</ul>';




			//find polarity (+/-) from statistics (suppose: more negative lightning than positve)
			echo '<h4>'._BL('Polarity').' ('._BL('Very experimental').')</h4>';

			for ($i=0;$i<2;$i++)
			{
				echo '<h6>'._BL('Antenna').' '.($i+1)." (".$alpha[$i]."&deg;)</h6>";
				echo '<ul>';


				$deltas = array(90, 270);
				$arc = 45;

				$c = 0;
				foreach($deltas as $delta)
				{

					//count positive/negative lighning in a arc verticaly to the antenna
					$beta1 = ($alpha[$i] + $delta + $arc/2) % 360;
					$beta2 = ($alpha[$i] + $delta - $arc/2) % 360;

					if ($beta1 > $beta2)
					{
						$tmp = $beta2;
						$beta2 = $beta1;
						$beta1 = $tmp;
					}

					$neg = 0;
					$pos = 0;

					$j = 0;
					for ($a=$beta1;$a<$beta2;$a++)
					{
						$neg += $sign[$i][$a][-1];
						$pos += $sign[$i][$a][1];

						$cur_neg += count($current[$i][$a][-1]) ? array_sum($current[$i][$a][-1]) / count($current[$i][$a][-1]) : 0;
						$cur_pos += count($current[$i][$a][1]) ? array_sum($current[$i][$a][1]) / count($current[$i][$a][1]) : 0;

						$j++;
					}

					$cur_neg /= $j;
					$cur_pos /= $j;

					if ($neg)
						$pol_ratio[$c] = $pos / $neg;

					$c++;

					echo '<li>'.round($beta1).'&deg; to '.round($beta2).'&deg; :';
					echo ' '._BL('Positive').": $pos / "._BL('Negative').": $neg ";
					//echo " (Current: ".round($cur_pos,1)." / ".round($cur_neg,1)." kA/perStrike) ";
					echo '</li>';
				}

				if ($pol_ratio[0] > 1 && $pol_ratio[1] < 1)
					$pos_dir[$i] = ($alpha[$i] + $deltas[1]) % 360;
				else if ($pol_ratio[0] < 1 && $pol_ratio[1] > 1)
					$pos_dir[$i] = ($alpha[$i] + $deltas[0]) % 360;
				else
					$pos_dir[$i] = null;

				echo '<li>'._BL('Positive electrical direction').': ';

				if ($pos_dir[$i] === null)
				{
					echo _BL('Not definite').' :-(';
				}
				else
				{
					echo $pos_dir[$i].'&deg';

					if ((bo_user_get_level() & BO_PERM_ADMIN))
						echo '<input type="text" name="bo_antenna'.($i+1).'_bearing_elec" value="'.$pos_dir[$i].'" id="bo_antenna'.($i+1).'_elec_bearing_id" class="bo_form_text bo_form_input">';
				}

				echo '</li>';

				echo '</ul>';

			}

			echo '<input type="submit" name="bo_calibrate_manual" value="'._BL('Ok').'" id="bo_admin_submit" class="bo_form_submit">';

			echo '</form>';
		}
	}
}

function bo_cache_info()
{
	$dirs['Tiles'] = array('tiles/', 8);
	$dirs['Icons'] = array('icons/', 8);
	$dirs['Maps']  = array('maps/',  8);

	if (BO_CACHE_SUBDIRS === true)
		$dirs['Density maps'] = array('densitymap/', 8);

	$dirs['Graphs'] = array('graphs/', 0);
		
	$dirs['Other'] = array('', 0);

	
	echo '<h3>'._BL('File cache info').'</h3>';
	echo '<p><a href="'.bo_insert_url().'&bo_action2=unlink">'._BL('Click here to delete all files').'</a></p>';
	flush();
	
	echo '<h3>'._BL('Folders').'</h3>';
	foreach($dirs as $name => $d)
	{
		$dir = BO_CACHE_DIR.'/'.$d[0];
		$depth = $d[1];

		echo '<h4>'.$name.': <em>'.$dir.'</em></h4>';

		$dir = BO_DIR.$dir;

		if ($_GET['bo_action2'] == 'unlink')
		{
			bo_delete_files($dir, 0, $depth);
			flush();
			clearstatcache();
		}



		$files = glob($dir.'*');

		if ($depth && is_array($files))
		{
			for ($i = 0; $i < count($files); $i++)
			{
				if (is_dir($files[$i]))
				{
					$add = glob($files[$i].'/*');

					if ($add && is_array($add))
						$files = array_merge($files, $add);
				}
			}
		}

		if (is_array($files))
		{
			$size = 0;
			$count = 0;
			foreach($files as $file)
			{
				$file = $file;
				if (!is_dir($file))
				{
					$size += filesize($file);
					$count++;
				}
			}
		}

		echo '<p>'._BL('Files').': <strong>'.$count.'</strong> ('._BN($size / 1024, 1).' kB)</p>';

		flush();
	}


}

function bo_my_station_update_form()
{
	if ($_POST['ok'])
	{
		$url = trim($_POST['bo_url']);

		echo '<pre>';
		$ret = bo_my_station_update($url);
		echo '</pre>';

		if ($ret && $url)
			bo_set_conf('mybo_stations_autoupdate', $_POST['bo_auto'] ? 1 : 0);
		else
			bo_set_conf('mybo_stations_autoupdate', 0);
	}
	else
	{

		$st_urls = unserialize(bo_get_conf('mybo_stations'));

		if (is_array($st_urls) && $st_urls[bo_station_id()])
			$url = $st_urls[bo_station_id()];
		else
			$url = 'http://'.$_SERVER["HTTP_HOST"].dirname($_SERVER["REQUEST_URI"]);


		echo '<h3>'._BL('Link with the MyBlitzortung network').'</h3>';
		echo strtr(_BL('mybo_station_update_info'), array('{LINK_HOST}' => BO_LINK_HOST));
		echo '<form action="'.bo_insert_url().'" method="POST" class="bo_login_form">';
		echo '<fieldset class="bo_mylink_fieldset">';
		echo '<span class="bo_form_descr">'._BL('URL of your website').' ('._BL('Leave blank to remove your station from the list').'):</span>';
		echo '<input type="text" name="bo_url" id="bo_mylink_url" value="'._BC($url).'" class="bo_form_text" style="width:100%">';
		echo '<div class="bo_input_container">';
		echo '<span class="bo_form_descr">'.' '._BL('Do an auto update every 24h to retrieve new stations').':</span>';
		echo '<input type="checkbox" value="1" name="bo_auto" '.(bo_get_conf('mybo_stations_autoupdate') == 1 ? ' checked ' : '').'>';
		echo '</div>';
		echo '<input type="submit" name="ok" value="'._BL('Agree and Send').'" id="bo_login_submit" class="bo_form_submit" onclick="return confirm(\''._BL('Really continue?').'\');">';
		echo '</fieldset>';
		echo '</form>';

	}

}

function bo_import_cities()
{
	$fp = @fopen(BO_DIR."cities.txt", "r");

	echo '<h3>'._BL('Importing cities').'</h3>';

	if ($fp)
	{
		$cities = array();
		while (($line = fgets($fp, 4096)) !== false)
		{
			$p = explode(',', $line);
			$cities[] = $p;
		}

		fclose($fp);

		echo '<p>'._BL('Cities read').': '.count($cities).'</p>';
		flush();

		if (count($cities))
		{
			echo '<p>'._BL('Deleting existing cities from DB').'</p>';
			flush();
			BoDb::query("DELETE FROM ".BO_DB_PREF."cities");

			echo '<p>'._BL('Cities imported').': ';
			flush();

			$i = 0;
			foreach($cities as $city)
			{
				if (count($city) > 4) //cities with borders --> big cities
					$city[3] += 4;

				$ok = BoDb::query("INSERT INTO ".BO_DB_PREF."cities
						SET name='".BoDb::esc($city[0])."',
							lat ='".BoDb::esc($city[1])."',
							lon ='".BoDb::esc($city[2])."',
							type='".BoDb::esc($city[3])."'");
				if ($ok) $i++;
			}

			echo $i.'</p>';
		}
	}
	else
	{
		echo _BL('Error');
	}

}

function bo_set_conf_user($name, $data, $id=0)
{
	$id = $id > 0 ? $id : bo_user_get_id();

	if ($id > 0)
		return bo_set_conf('user_'.$name.'_'.$id, $data);
	else
		return false;
}


function bo_get_conf_user($name, $id=0)
{
	$id = $id > 0 ? $id : bo_user_get_id();

	if ($id > 0)
		return bo_get_conf('user_'.$name.'_'.$id);
	else
		return false;
}



function bo_user_init()
{
	global $_BO;
	
	//don't create a session if request comes through non-cookie-domain
	if (strpos(BO_FILE_NOCOOKIE, 'http://'.$_SERVER['HTTP_HOST'].'/') !== false)
	{
		$_BO['radius'] = BO_RADIUS;
		return;
	}
	
	//Session handling
	@session_start();

	//Set user_id
	if (!isset($_SESSION['bo_user']))
		$_SESSION['bo_user'] = 0;

	//Cookie login
	bo_user_cookie_login();


	$_BO['radius'] = (bo_user_get_level() & BO_PERM_NOLIMIT) ? 0 : BO_RADIUS;
}



function bo_user_cookie_login()
{
	//Check for stored login in cookie
	if (!bo_user_get_id() && intval(BO_LOGIN_COOKIE_TIME) && isset($_COOKIE['bo_login']) && preg_match('/^([0-9]+)_([0-9a-z]+)$/i', trim($_COOKIE['bo_login']), $r) )
	{
		$cookie_user_id = $r[1];
		$cookie_uid = $r[2];

		$data = unserialize(bo_get_conf_user('user_cookie', $cookie_user_id));

		if ($cookie_uid == $data['uid'] && trim($data['uid']))
		{
			bo_user_do_login_byid($cookie_user_id, $data['pass']);
		}

	}
}

?>