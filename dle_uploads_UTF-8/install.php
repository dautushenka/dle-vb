<?PHP

/*
=====================================================
 Install & Update file v1.4.0
-----------------------------------------------------
 http://kaliostro.net/
-----------------------------------------------------
 Copyright (c) 2007-2009 kaliostro ICQ: 415-74-19
=====================================================
 Данный код защищен авторскими правами
=====================================================
*/

@ob_start(); 
@ob_implicit_flush(true);
set_time_limit(0);
error_reporting(E_ALL ^ E_NOTICE);
@ini_set('display_errors', true);
@ini_set('html_errors', false);
@ini_set('error_reporting', E_ALL ^ E_NOTICE);
@ini_set('zend.ze1_compatibility_mode', 0);
session_start();
define('DATALIFEENGINE', true);
define('ROOT_DIR', dirname (__FILE__));
define('ENGINE_DIR', ROOT_DIR . "/engine");
define("INSTALL", true);

class Spacer
{
    public function __call($func_name, Array $args)
    {
        
    }
}

$vb = new Spacer();


include_once ENGINE_DIR.'/data/config.php';

if ($config['version_id'] < 6.3)
	require_once ENGINE_DIR.'/inc/mysql.php';
else
	require_once ENGINE_DIR.'/classes/mysql.php';
	
require_once ENGINE_DIR.'/data/dbconfig.php';
require_once(ENGINE_DIR.'/modules/functions.php');
require_once(ENGINE_DIR.'/modules/sitelogin.php');

if (!defined('COLLATE'))
	define('COLLATE', 'cp1251');
if (!defined('USERPREFIX'))
	define('COLLATE', constant("PREFIX"));
	


class install_update
{
	private $image_patch='';
	private $db;
	private $step = '';
	private $module_name = '';
	private $version = '';
	private $button = "Продолжить>>";
	private $buttons = array();
	private $error = array();
	private $hidden_array = array();
	private $handler;
	private $finish = false;
	private $debug = false;
	
	public $year = '';
	public $steps_array = array();
	public $fields = array();
	public $setting_menu = array();
	public $show_setting_menu = false;
	
	public function __construct($module_name, $version, $steps_array, $licence, db &$db, $image_patch)
	{
		$this->module_name = $module_name;
		$this->image_patch = $image_patch;
		$this->version = $version;
		$this->steps_array = $steps_array;
		$this->step = (intval($_POST['step']))?intval($_POST['step']):0;
		$this->hidden_array['step'] = $this->step;
		$this->hidden_array['type'] = ($_POST['type'])?$_POST['type']:'';
		
		if (!empty($_POST['fields']))
		{
			$this->hidden_array['fields'] = $_POST['fields'];
			$this->fields = $_POST['fields'];
		}
		
		if (!empty($_GET['debug']))
		{
		    $this->SetAdditionalField('debug', 1);
		}
		
		if (!empty($this->fields['debug']))
		{
		    $this->debug = true;
		}
		
		if ($db)
			$this->db =& $db;
		
		if ($licence)
			$this->CheckLicence($licence);
		
		if (!$GLOBALS['is_logged'] || $GLOBALS['member_id']['user_group'] != 1)
			$this->Authorization();
			
		if ($_POST['action'] == "finish" && @unlink(__FILE__))
		{
			$this->finish = true;
			$this->button = false;
			$this->show(<<<TEXT
<div style="text-align:center;font-size:120%;">Файл установки(обноления) автоматически удалился. Переход на главную страницу сайта...<br />
<a href="{$GLOBALS['config']['http_home_url']}" >Нажмите здесь, если ваш обозреватель не поддерживает автоматической переадресации.</a></div>
TEXT
,false);
		}
		elseif ($_POST['action'] == "finish")
		{
		    $this->button = false;
		    
			$this->show(<<<TEXT
<div style="text-align:center;font-size:150%;">Удалите файл установки(обновления) из корня сайта</div>
TEXT
,false);
		}
	}
	
	public function SetType($type, $button=false)
	{
		$this->hidden_array['type'] = $type;
		
		if ($type == 'update')
		{
			$this->buttons['type'] = "Переустановить";
			$this->hidden_array['step'] = -1;
		}
		if ($button)
			$this->button = $button;
	}
	
	public function SetAdditionalField($name, $value)
	{
		$this->hidden_array['fields'][$name] = $value;
		$this->fields[$name] = $value;
	}
	
	private function CheckLogin()
	{
		if ($_SESSION['dle_log'] >= 5)
		{
			die("Hacking attempt!");
		}
		
		$GLOBALS['user_group'] = get_vars ("usergroup");

		if (!$GLOBALS['user_group'])
		{
			$GLOBALS['user_group'] = array ();
		
			$this->db->query("SELECT * FROM " . USERPREFIX . "_usergroups ORDER BY id ASC");
		
			while($row = $this->db->get_row())
			{
				$GLOBALS['user_group'][$row['id']] = array ();
				
				foreach ($row as $key => $value)
		     	{
		       		$GLOBALS['user_group'][$row['id']][$key] = $value;
		     	}
			}
			
			set_vars ("usergroup", $GLOBALS['user_group']);
			$this->db->free();
		}

		$hash_pass = md5(md5($_POST['password']));
		$login = $this->db->safesql($_POST['name']);
		
		if ($GLOBALS['member_id'] = $this->db->super_query("SELECT * FROM " . USERPREFIX . "_users WHERE name='{$login}' AND password='{$hash_pass}'"))
		{
		    if (!defined('DOMAIN'))
		    {
		        define( 'DOMAIN', "." . $_SERVER['HTTP_HOST']);
		    }
			
			setcookie("dle_password", md5($_POST['password']), time()+3600*24*365, "/", DOMAIN, NULL, TRUE);
			
	        @session_register('dle_password');
	        @session_register('member_lasttime');
	
	        if ($GLOBALS['config']['version_id'] < 7.2)
	        {
	        	@session_register('dle_name');
	        	setcookie("dle_name", $_POST['name'],time()+3600*24*365, "/", DOMAIN, NULL, TRUE);
	        	$_SESSION['dle_name'] = $_POST['name'];
			}
			else 
			{
				@session_register('dle_user_id');
				setcookie("dle_user_id", $GLOBALS['member_id']['user_id'], time()+3600*24*365, "/", DOMAIN, NULL, TRUE);
        		$_SESSION['dle_user_id'] = $GLOBALS['member_id']['user_id'];
			}
			
	        $_SESSION['dle_password']    = md5($_POST['password']);
	
			$_SESSION['dle_log'] = 0;
			
			return true;
		}
		else 
		{
			$_SESSION['dle_log']++;
			return false;
		}
	}
	
	private function Authorization()
	{
		if (empty($_POST['name']) || empty($_POST['password']) || !$this->CheckLogin())
		{
			if (isset($_SESSION['dle_log']))
			{
				if ($_SESSION['dle_log'] > 1)
					$count_login = " уже " . $_SESSION['dle_log'] . " раза из 5 возможных";
					
				$status_report = "Вы не вошли$count_login, попробуйте еще раз, если забыли пароль, то можно его востановить пройдя по след ссылки <a href=\"/index.php?do=lostpassword\" >Востановить пароль</a><br/>";
			}
			
			$text_full = "<table><tr><td align=right><b>Логин : </b> </td><td align=left height=\"20px\"><input class=edit type=edit name='name' value=''></td></tr><tr><td aling=right><b>Пароль : </b> </td><td height=\"20px\" align=left ><input class=edit type=password name='password' value=''></td></tr><tr><td></td><td><input class=buttons type='submit' value='Войти'></td></tr></table>";
			
			$this->button = false;
			$this->steps_array = array("Авторизация");
			$this->hidden_array['step'] = 0;
			$this->show($text_full, $status_report , 'module_error');
		}
	}
	
	private function CheckLicence($licence)
	{
			if (!preg_match("#" . $licence . "#i", $_SERVER['HTTP_HOST']) && 
			    !preg_match('#localhost#', $_SERVER['HTTP_HOST']) &&
                strpos($_SERVER['HTTP_HOST'], $_SERVER['SERVER_ADDR']) === false
			     )
			{
				if ($GLOBALS['config']['version_id'] < 6.3)
				{
					require_once ENGINE_DIR.'/inc/mail.class.php';
				}
				else
					require_once ENGINE_DIR.'/classes/mail.class.php';
					
				$mail = new dle_mail ($GLOBALS['config']);
				
				$text  = "Лиц домен:" . $licence . "\n";
				$text .= "Текущей домен: " . $_SERVER['HTTP_HOST'];
				
				$mail->send ("support@kaliostro.net", "Нарушение лицензии", $text);
				
				$this->FatalError("Вы используете не лицензионную версию модуля \"$this->module_name\".<br/>За информацией обращайтесь на форум <a href=\"http://forum.kaliostro.net/\" >http://forum.kaliostro.net/</a> или ICQ: 415-74-19");
			}
	}
	
	public function Main($description, $button=false)
	{
		if ($button)
			$this->button = $button;
		$this->hidden_array['step']++;
		$this->show($description);
	}
	
	public function Licence($licence, $licence_text)
	{
		if ($_POST['action'] == 'check_eula')
		{
			if (intval($_POST['eula']))
			{
				$this->step++;
				$this->hidden_array['step']++;
				return true;
			}
			else 
				$this->error[] = 'Если не примете соглашение лицензии, вы не имеете право устанавливать модуль';
		}
		$this->button .= '" disabled="disabled';
		$text = <<<HTML
<table width="100%">
    <tr>
        <td style="padding:2px;">$licence<br /><br /><div style="height: 300px; border: 1px solid #76774C; background-color: #FDFDD3; padding: 5px; overflow: auto;">$licence_text</div>
		<input onclick="agree();" type='checkbox' name='eula' value=1 id='eula'><b><label for="eula">Я принимаю данное соглашение</label></b>
		<br />
</td>
    </tr>
</table>
<script type="text/javascript" >
<!--
function agree()
{
if (document.form.eula.checked == true)
{
document.form.button.disabled=false;
}
else
{
document.form.button.disabled=true;
}
}
-->
</script>
HTML;
		$this->hidden_array['action'] = "check_eula";
		$this->show($text, false, 'module_error');
	}
	
	public function CheckHost(array $important_files = array(), $dle=false, $php=false, $mysql=false)
	{
		global $config; 
		
		$chmod_errors = 0;
		$not_found_errors = 0;
		
		function ShowCheckRow($name, $value, $status)
		{
			$text_full ="<tr>
			         <td height=\"22\" class=\"tableborder main\">&nbsp;$name</td>
			         <td>&nbsp; $value</td>
			         <td>&nbsp; $status</td>
			         </tr><tr><td background=\"engine/skins/images/mline.gif\" height=1 colspan=3></td></tr>";
			
			return $text_full;
		}
		
		if ($important_files)
		{
		    foreach($important_files as $file)
		    {
		
		        if(!file_exists($file))
		        {
		            $file_status = "<font color=red>не найден!</font>";
		            $not_found_errors ++;
		        }
		        elseif(is_writable($file))
		            $file_status = "<font color=green>разрешено</font>";
		        else
		        {
		            @chmod($file, 0777);
		            
		            if(is_writable($file))
		                $file_status = "<font color=green>разрешено</font>";
		            else
		            {
		                @chmod("$file", 0755);
		                
		                if(is_writable($file))
		                    $file_status = "<font color=green>разрешено</font>";
		                else
		                {
		                    $file_status = "<font color=red>запрещено</font>";
		                    $chmod_errors ++;
		                }
		            }
		        }
				$chmod_value = @decoct(@fileperms($file)) % 1000;
			
			    $text_full .= ShowCheckRow($file, $chmod_value, $file_status);
			}
		}
	
		if ($dle && version_compare($config['version_id'], $dle, ">"))
			$dle_v = "<font color=\"green\">" . $config['version_id'] . "</font><br/>";
		elseif ($dle)
		{
			$dle_v = "<font color=\"red\">" .$config['version_id']."</font>";
			$this->error[] = "Модуль не работает на версиях DLE ниже <font color=red >$dle</font>. У вас ". $config['version_id'];
		}
		
		if ($dle)
			 $text_full .= ShowCheckRow('DataLife Engine', $dle_v, $dle);
		
		$this->db->connect(DBUSER, DBPASS, DBNAME, DBHOST);

		if ($mysql && version_compare($this->db->mysql_version, $mysql, ">"))
			$sql = "<font color=\"green\">" . $this->db->mysql_version . "</font><br/>";
		elseif ($mysql)
		{
			$sql = "<font color=\"red\">" .$this->db->mysql_version."</font>";
			$this->error[] = "Данная версия базы данных не поддерживается.";
		}
		
		if ($mysql)
			 $text_full .= ShowCheckRow('MySQL', $sql, $mysql);
		
		if ($php && version_compare(phpversion(), $php, ">"))
			$php_ok = "<font color=\"green\">" . phpversion() . "</font><br/>";
		elseif ($php)
		{
			$php_ok = "<font color=\"red\">" .phpversion()."</font>";
			$this->error[] = "Данная версия PHP не поддерживается.";
		}
		
		if ($php)
			 $text_full .= ShowCheckRow('PHP', $php_ok, $php);
		
		if($chmod_errors > 0)
		{
			$this->error[] = "Запрещена запись в $chmod_errors файлов.<br />Вы должны выставить для папок CHMOD 777, для файлов CHMOD 666, используя ФТП-клиент.";
		}
		if($not_found_errors > 0)
		{
			$this->error[] = "$not_found_errors файлов не найдено!";
		}
			
		if(!$this->error)
		{
			$status_report = 'Проверка успешно завершена! Можете продолжить установку!';
			$this->hidden_array['step']++;
			$this->show($text_full, $status_report, "module_ok");
		}
		else
		{
			$this->button = "Обновить";
			$this->show($text_full, $status_report, "module_error");
		}
	}
	
	public function EditFiles($files_array)
	{
		
	}
	
	public function Settings(array $settings_array, array $default, $var='', $file='')
	{
		if (!file_exists(ENGINE_DIR . "/data/" . $file) || isset($_POST['rewrite']) || $_POST['action'] == 'save')
		{
			if ($_POST['action'] == "save" && ($save_con = $_POST['save_con']))
			{
				foreach ($settings_array as $setting)
				{
					if ($setting['regexp'] && !empty($setting['name']) && !preg_match($setting['regexp'], $save_con[$setting['name']]))
						$this->error[] = '"' . $setting['title'] . "\" -- Заполнено не верно";
				}
				if (!$this->error)
				{
					if ($default)
						$save_con = array_merge($default, $save_con);
					
					$save_con['version_id'] = $this->version;
					
					if (is_writable(ENGINE_DIR . "/data/"))
					{
					    $this->handler = fopen(ENGINE_DIR.'/data/'.$file, "w");
					    fwrite($this->handler, "<?PHP \n\n//$this->module_name Configurations\n\n\$$var = array (\n\n");
					    
					    $this->Save_conf($save_con);
					    fwrite($this->handler, ");\n\n?>");
					    fclose($this->handler);
					    $this->hidden_array['step']++;
					    $this->step++;
					    
					    return ;
					}
					else 
						$this->error[] = "Папка <b>./engine/data/</b> не доступна для записи";
				}
			}
			
			$text = "<table width=\"100%\">";
			if ($settings_array)
			{
			    $this->show_setting_menu = true;
			    
			    $i = 0;
				foreach ($settings_array as $setting)
				{
				    if (is_array($setting) && empty($setting['title']))
				    {
				        $text .= "<tr id='SetBlock$i' style='display:none'><td><table width=\"100%\">";
				        foreach ($setting as $block_name=>$set)
				        {
				            if (empty($set['noinstall'])) 
				            {
				                $text .= $this->SettingRow($set['title'], $set['descr'], $set['setting']);
				            }
				        }
				        $text .= "</table></td></tr>";
				        $i++;
				    }
				    else if(empty($setting['noinstall']))
				    {
				        $text .= $this->SettingRow($setting['title'], $setting['descr'], $setting['setting']);
				    }
				}
			}
			$text .= "</table>";
			$this->hidden_array['action'] = "save";
			$this->button = "Сохранить";
			$this->show($text, false);
		}
		elseif (file_exists(ENGINE_DIR . "/data/" . $file) && isset($_POST['skip']))
		{
			$this->hidden_array['step']++;
			$this->step++;
			return ;
		}
		else 
		{
			$text = "<div style='text-align:center;'>Обнаружен файл конфигурации молуля. Ваши действия?<br /><br /><input class='buttons' style='padding:2px' type='submit' name='rewrite' value='Перезаписать' /> &nbsp;&nbsp;&nbsp;<input class='buttons' type='submit' name='skip' value='Оставить' style='padding:2px' /></div>";
			$this->button = false;
			$this->show($text, false);
		}
	}
	
	public function Database(array $table_schema)
	{
		$error = FALSE;
		$create_tables = array();
		$add_fields = array();
		$isset_tables = array();
		$isset_column = array();
		$text_full = '';
		$status = true;

		if ($table_schema)
		{
			foreach ($table_schema as $table=>$action)
			{
				if (preg_match('#CREATE#i', $action))
					$create_tables[] = $table;
				elseif (preg_match('#^ALTER TABLE#i', $action) && preg_match_all('#(ADD +(COLUMN +)?`?([\w\d]+)`? .+?)(,|$)#i', $action, $fields))
				{
					foreach ($fields[3] as $key=>$field)
					{
						$add_fields[$table][$field] = $fields[1][$key];
					}
				}
			}
			
			if ($create_tables)
			{
				$table_resource = $this->db->query("SHOW TABLES", false);
				while ($row = $this->db->get_row($table_resource))
				{
					if (in_array(reset($row), $create_tables))
						$isset_tables[] = reset($row);
				}
			}
			
			if ($add_fields)
			{
				foreach ($add_fields as $table=>$fields)
				{
					$fileds_resource = $this->db->query("DESCRIBE " . $table, false);
					while ($row = $this->db->get_row($fileds_resource))
					{
						if (array_key_exists($row['Field'], $fields))
							$isset_column[$table][] = $row['Field'];
					}
				}
			}
			
			function ShowDBRow($desc, $status, $other = false)
			{
				$text_full = "<tr><td height=\"22\" class=\"tableborder main\">&nbsp;".$desc."</td>";
				
				if (!$other)
				{
					if ($status)
						$text_full .= "<td><font color=\"green\"><b>OK</b></font></td></tr><tr><td background=\"engine/skins/images/mline.gif\" height=1 colspan=2></td></tr>";
					else 
						$text_full .= "<td><font color=\"red\"><b>NO</b></font></td></tr><tr><td background=\"engine/skins/images/mline.gif\" height=1 colspan=2></td></tr>";
				}
				else 
					$text_full .= "<td>$status</td></tr><tr><td background=\"engine/skins/images/mline.gif\" height=1 colspan=2></td></tr>";
					
				return $text_full;
			}
			
			if ((!$isset_column && !$isset_tables) || $_POST['action'] == "doDB")
			{
				$this->db->query("SET SQL_MODE=''", false);
				
				foreach ($table_schema as $table=>$action)
				{
					$execute = true;
					
					if ($table && in_array($table, $isset_tables))
					{
						switch ($_POST['table_action'][$table])
						{
							case "recreate":
								$status = $this->db->query("DROP TABLE " . $table, false);
								$text_full .= ShowDBRow("Удаление таблици " . $table, $status);
								break;
								
							case "truncate":
								$status = $this->db->query("TRUNCATE " . $table, false);
								$text_full .= ShowDBRow("Очистка таблици " . $table, $status);
								$execute = false;
								break;
								
							default:
								$execute = false;
								break;
						}
					}
					
					if (array_key_exists($table, $isset_column))
					{
						foreach ($isset_column[$table] as $field)
						{
							if ($_POST['field_action'][$table][$field] == "truncate")
							{
								$status = $this->db->query("UPDATE $table SET $field=DEFAULT", false);
								$text_full .= ShowDBRow("Очистка поля $field таблици " . $table, $status);
							}
							unset($add_fields[$table][$field]);
						}
						if ($add_fields[$table])
							$action = "ALTER TABLE `$table` " . implode(", ", $add_fields[$table]);
						else 
							$execute = false;
					}
					
					if ($execute)
					{
						if ($table && in_array($table, $create_tables))
							$desc = "Создание таблици " . $table;
						else if (array_key_exists($table, $add_fields))
							$desc = "Добавление поля(ей) " . implode(", ", array_keys($add_fields[$table])) . " в таблицу " . $table;
						else if (preg_match('#^DROP#i', $action))
							$desc = "Удаление таблици " . $table;
						else if (preg_match('#^INSERT (INTO|IGNORE) `?(.+?)`?( |\()#i', $action, $action_table))
							$desc = "Вставка данных в таблицу " . $action_table[2];
						else if (preg_match('#^UPDATE `?(.+?)`?#i', $action, $action_table))
                            $desc = "Обновление данных таблици " . $action_table[1];
						else 
						    $desc = "Другие изменения";
						
						if ($this->debug)
						{
						    $status = $this->db->query($action);
						}
						else 
						{
						    $status = $this->db->query($action, false);
						}
						
						$text_full .= ShowDBRow($desc, $status);
						
						if (!$status)
							$error = true;
					}
				}
				
				if ($error)
				{
					$this->error[] = "Работа с базой данной произошла с ошибкой";
					$this->show($text_full, false, "error");
				}
				else 
				{
					$this->hidden_array['step']++;
					$this->show($text_full, "Работа с базой завершена успешно", "module_ok");
				}
			}
			else 
			{
				if ($isset_tables)
				{
					$text_full .= ShowDBRow("<b>Следующие таблици уже существуют</b>", '', true);
					$action_array = array(
										 "0" => "Оставить",
										 "recreate" => "Пересоздать",
										 "truncate" => "Очистить",
										);
					foreach ($isset_tables as $table)
						$text_full .=  ShowDBRow($table, $this->Selection($action_array, "table_action[$table]"), true);
				}
				
				if ($isset_column)
				{
					$text_full .= ShowDBRow("<b>Следующие поля уже существуют</b>", '', true);
					$action_array = array(
										 "0" => "Оставить",
										 "truncate" => "Очистить",
										);
					foreach ($isset_column as $table=>$fields)
					{
						$text_full .= ShowDBRow("<span style='margin-left:15px;'><b>Таблица $table</b><span>", '', true);
						foreach ($fields as $field)
							$text_full .=  ShowDBRow("<span style='margin-left:30px;'>$field<span>", $this->Selection($action_array, "field_action[$table][$field]"), true);
					}
				}
				$this->hidden_array['action'] = 'doDB';
				
				$this->show($text_full, "Укажите действия при работе с имеющимися данными", "module_info");
			}
		}
		
	}
	
	public function OtherPage($text, $status = '', $checkfunction = '')
	{
		if ($_POST['action'] == "check")
		{
			if (!$checkfunction || !function_exists($checkfunction) || !($error = $checkfunction($this)))
			{
				$this->step++;
				$this->hidden_array['step']++;
				return true;
			}
			else 
				$this->error = $error;
		}
		$this->hidden_array['action'] = 'check';
		$this->show($text, $status);
	}
	
	public function ChangeVersion($file, $var, $config, $new_value = array(), $version = '')
	{
		if ($new_value)
			$config = array_merge($new_value, $config);
			
		if (!$version)
			$version = $this->version;
			
		$config['version_id'] = $version;
			
		$this->handler = fopen(ENGINE_DIR.'/data/'.$file, "w");
		fwrite($this->handler, "<?PHP \n\n//$this->module_name Configurations\n\n\$$var = array (\n\n");
					    
		$this->Save_conf($config);
		fwrite($this->handler, ");\n\n?>");
		fclose($this->handler);
		return ;
	}
	
	public function Finish($text, $version = '')
	{
		if ($version && $version != $this->version)
			$this->hidden_array['step'] = '';
		else 
			$this->hidden_array['action'] = "finish";
			
		$this->button = "Закончить";
		$this->show($text, false);
	}
	
	private function Save_conf($save_con, $array=false) 
	{
		foreach($save_con as $name => $value)
		{
		  	if (is_array($value))
		  	{
		  		fwrite($this->handler, "'{$name}' => array (\n\n"); 
		  		$this->save_conf($value, true);
		  	}
		  	else
		  	{
		    	$value = strtr($value, '"', "'");
		    	fwrite($this->handler, "'{$name}' => \"".stripslashes($value)."\",\n\n");
		  	}
	    }
		if ($array) fwrite($this->handler, "),\n\n");
	}
	
	private function OpenTable()
	{
		echo <<<HTML
		<div style="padding-top:5px;padding-bottom:2px;">
		<table width="100%">
	    <tr>
	        <td width="4"><img src="engine/skins/images/tl_lo.gif" width="4" height="4" border="0"></td>
	        <td background="engine/skins/images/tl_oo.gif"><img src="engine/skins/images/tl_oo.gif" width="1" height="4" border="0"></td>
	        <td width="6"><img src="engine/skins/images/tl_ro.gif" width="6" height="4" border="0"></td>
	    </tr>
	    <tr>
	        <td background="engine/skins/images/tl_lb.gif"><img src="engine/skins/images/tl_lb.gif" width="4" height="1" border="0"></td>
	        <td style="padding:5px;" bgcolor="#FFFFFF">
HTML;
	}
	
	private function OpenSubtable($title='', $script="")
	{
		echo <<<HTML
		<table width="100%" $script >
		    <tr>
		        <td bgcolor="#EFEFEF" height="29" style="padding-left:10px;"><div class="navigation">{$title}</div></td>
		    </tr>
		</table>
		<div class="unterline"></div>
		<table width="100%">
		<tr><td>
HTML;
	}
	
	private function CloseSubtable()
	{
		echo <<<HTML
			</td>
		</tr>
		</table>
HTML;
	}
	
	private function CloseTable()
	{
		echo <<<HTML
		</td>
	        <td background="engine/skins/images/tl_rb.gif"><img src="engine/skins/images/tl_rb.gif" width="6" height="1" border="0"></td>
	    </tr>
	    <tr>
	        <td><img src="engine/skins/images/tl_lu.gif" width="4" height="6" border="0"></td>
	        <td background="engine/skins/images/tl_ub.gif"><img src="engine/skins/images/tl_ub.gif" width="1" height="6" border="0"></td>
	        <td><img src="engine/skins/images/tl_ru.gif" width="6" height="6" border="0"></td>
	    </tr>
		</table>
		</div>
HTML;
	}
	
	private function SettingRow($title="", $description="", $field="")
	{
		return "<tr>
		<td style=\"padding:4px\" class=\"option\">
	    <b>$title</b><br /><span class=small>$description</span>
	    <td width=394 align=middle >
	    $field
		</tr><tr><td background=\"engine/skins/images/mline.gif\" height=1 colspan=2></td></tr>";
	}
	
	private function OpenForm($action = "", $script='')
	{
		echo <<<HTML
		<form action="$action" method="POST" name="form" $script >
HTML;
		if ($this->hidden_array)
		{
			foreach ($this->hidden_array as $key=>$value)
			{
				if (is_array($value))
				{
					foreach ($value as $key2=>$value2)
						echo "<input type=\"hidden\" name=\"{$key}[{$key2}]\" value=\"$value2\" />\n";
				}
				else 
					echo "<input type=\"hidden\" name=\"$key\" value=\"$value\" />\n";
			}
		}
	}
	
	private function CloseForm()
	{
		echo "</form>";
	}
	
	public function Selection($options = array(), $name = "", $selected = "", $script = "")
	{
		if (!count($options) || $name == "") return false;
		
		$output =  "<select name=\"$name\" $script >\r\n";
        foreach($options as $value=>$description)
        {
          $output .= "<option value=\"$value\"";
          if($selected == $value){ $output .= " selected "; }
          $output .= ">$description</option>\n";
        }
        $output .= "</select>";
      
        return $output;
	}
	
	public function ShowSettingMenu()
	{
		$this->OpenTable();
		echo "<table cellpadding=\"0\" cellspacing=\"0\" id=\"setting_menu\" width=\"100%\"><tr>";
		$i = 0;
		foreach ($this->setting_menu as $name=>$image)
		{
			echo "<td align='center'><a href=\"#\" OnClick=\"ShowBlock('SetBlock$i');return false;\" title=\"$name\"><img src=\"$image\" border=\"0\" /></a></td>\n";
			$i++;
		}
		$i--;
		echo "</tr></table>";
		echo <<<JS
	   <script type='text/javascript' >
	   function ShowBlock(name)
	   {
	       for (i = 0; ; i++)
	       {
	           if (block = document.getElementById('SetBlock' + i))
	           {
	               block.style.display = 'none';
	           }
	           else
	           {
	               break;
	           }
	       }
	       block = document.getElementById(name);
	       block.style.display = '';
	   }
	   window.onload = function()
	   {
	       block = document.getElementById('SetBlock$i');
	       if (block)
	           block.style.display = '';
	   }
	   </script>
JS;
		$this->CloseTable();
	}
	
	public function AddAdminSection($file_name, $title, $descr, $image, $permision = 'all')
	{
        if ($GLOBALS['config']['version_id'] >= 8.2)
        {
            $title = $this->db->safesql($title);
            $descr = $this->db->safesql($descr);
            $file_name = $this->db->safesql($file_name);
            $image = $this->db->safesql($image);
            
            $this->db->query("INSERT IGNORE `" . PREFIX . "_admin_sections` (allow_groups, name, icon, title, descr) VALUES ('all', '$file_name', '$image', '$title', '$descr')");
        }
	}
	
	private function FatalError($text)
	{
		$this->hidden_array = array("step"=>0);
		$this->step = 0;
		$this->steps_array = array("Fatal Error");
		$this->button = false;
		$this->show('', $text, 'module_error');
	}
	
	private function show($text='', $status_report = false, $status_type = 'module_info')
	{
		$step_count = count($this->steps_array);
		if ($step_count == 0)
			$step_count = 1;
			
		if (preg_match("#IE#i", $_SERVER['HTTP_USER_AGENT']) || preg_match("#Opera#i", $_SERVER['HTTP_USER_AGENT']))
			$size = @round(100/$step_count, 5);
		else 
			$size = @ceil(100/$step_count);
		
		$bar = "<table width=\"100%\" align=center ><tr>";
		for ($i=0; $i<$step_count; $i++)
		{
			$bar .= "<td align=center width=\"$size%\" >";
			if ($i < $this->step && $i != $step_count) $bar .= "<img width='32px' heidth='32px' src=\"" . $config['http_home_url'] . $this->image_patch."/module_ok.png\" />";
			elseif ($i == $this->step && $this->error && $i != $step_count) $bar .= "<img width='32px' heidth='32px' src=\"" . $config['http_home_url'] . $this->image_patch."/module_stop.png\" />";
			elseif ($i == $this->step && !$this->error && $i != $step_count) $bar .= "<img width='32px' heidth='32px' src=\"" . $config['http_home_url'] . $this->image_patch."/module_now.png\" />";
			elseif ($i+1 == $step_count) $bar .= "<img width='32px' heidth='32px' src=\"" . $config['http_home_url'] . $this->image_patch."/module_finish.png\" />";
			else $bar .= "<img src=\"" . $config['http_home_url'] . $this->image_patch."/module_next.png\" />";
			$bar .= "</td>";
		}
		$bar .= "</tr><tr style=\"padding-top:10px;\">";
		for ($i=0; $i<$step_count; $i++)
		{
			$bar .= "<td style=\"font-size:10px\" align=center width=\"$size%\" >";
			if ($i < $this->step && $i != $step_count) $bar .= $this->steps_array[$i];
			elseif ($i == $this->step && $this->error && $i != $step_count) $bar .= "<b>" . $this->steps_array[$i] . "</b>";
			elseif ($i == $this->step && !$this->error && $i != $step_count) $bar .= "<b>" . $this->steps_array[$i] . "</b>";
			elseif ($i+1 == $step_count) $bar .= "<font color=\"#cccccc\" >" . $this->steps_array[$i] . "</font>";
			else $bar .= "<font color=\"#cccccc\" >" . $this->steps_array[$i] . "</font>";
			$bar .= "</td>";
		}
		$bar .= "</tr></table>";
		
		if ($this->error)
		{
			$errors = "<font color=\"red\" >Были допущены следующие ошибки</font>\n<ol>\n";
			foreach ($this->error as $error)
			{
				$errors .= "<li>".$error."</li>\n";
			}
			$errors .= "</ol>";
			$status_report = $errors . $status_report;
		}
		
		if ($this->finish)
			$meta = "\n<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"5;URL={$GLOBALS['config']['http_home_url']}\">";
		else 
			$meta = '';
		
		echo <<<HTML
<html>
<head>
<title>$this->module_name</title>
<meta content="text/html; charset={$GLOBALS['config']['charset']}" http-equiv="content-type" />$meta
<script type="text/javascript" src="engine/skins/default.js"></script>

<style type="text/css">
html,body{
height:100%;
margin:0px;
padding: 0px;
background: #F4F3EE;
}

form {
margin:0px;
padding: 0px;
}

table{
border:0px;
border-collapse:collapse;
}

table td{
padding:0px;
font-size: 11px;
font-family: verdana;
}

a:active,
a:visited,
a:link {
	color: #4b719e;
	text-decoration:none;
	}

a:hover {
	color: #4b719e;
	text-decoration: underline;
	}

.navigation {
	color: #999898;
	font-size: 11px;
	font-family: tahoma;
}

.option {
	color: #717171;
	font-size: 11px;
	font-family: tahoma;
}

.upload input {
	border:1px solid #9E9E9E;
	color: #000000;
	font-size: 11px;
	font-family: Verdana; 
}

.small {
	color: #999898;
}

.navigation a:active,
.navigation a:visited,
.navigation a:link {
	color: #999898;
	text-decoration:none;
	}

.navigation a:hover {
	color: #999898;
	text-decoration: underline;
	}

.list {
	font-size: 11px;
}

.list a:active,
.list a:visited,
.list a:link {
	color: #0B5E92;
	text-decoration:none;
	}

.list a:hover {
	color: #999898;
	text-decoration: underline;
	}

.quick {
	color: #999898;
	font-size: 11px;
	font-family: tahoma;
	padding: 5px;
}

.quick h3 {
	font-size: 18px;
	font-family: verdana;
	margin: 0px;
	padding-top: 5px;
}
.system {
	color: #999898;
	font-size: 11px;
	font-family: tahoma;
	padding-bottom: 10px;
	text-decoration:none;
}

.system h3 {
	font-size: 18px;
	font-family: verdana;
	margin: 0px;
	padding-top: 4px;
}
.system a:active,
.system a:visited,
.system a:link,
.system a:hover {
	color: #999898;
	text-decoration:none;
	}

.quick a:active,
.quick a:visited,
.quick a:link,
.quick a:hover {
	color: #999898;
	text-decoration:none;
	}

.unterline {
	background: url(engine/skins/images/line_bg.gif);
	width: 100%;
	height: 9px;
	font-size: 3px;
	font-family: tahoma;
	margin-bottom: 4px;
} 

.hr_line {
	background: url(engine/skins/images/line.gif);
	width: 100%;
	height: 7px;
	font-size: 3px;
	font-family: tahoma;
	margin-top: 4px;
	margin-bottom: 4px;
}

.edit {
	border:1px solid #9E9E9E;
	color: #000000;
	font-size: 11px;
	font-family: Verdana;
	background: #FFF; 
}

.bbcodes {
	background: #FFF;
	border: 1px solid #9E9E9E;
	color: #666666;
	font-family: Verdana, Tahoma, helvetica, sans-serif;
	padding: 2px;
	vertical-align: middle;
	font-size: 10px; 
	margin:2px;
	height: 21px;
}

.buttons {
	background: #FFF;
	border: 1px solid #9E9E9E;
	color: #666666;
	font-family: Verdana, Tahoma, helvetica, sans-serif;
	padding: 0px;
	vertical-align: absmiddle;
	font-size: 11px; 
	height: 21px;
}

select {
	color: #000000;
	font-size: 11px;
	font-family: Verdana; 
	border:1px solid #9E9E9E;
}

.cat_select {
	color: #000000;
	font-size: 11px;
	font-family: Verdana; 
	border:1px solid #9E9E9E;
	width:316px;
	height:73px;
}

textarea {
	border: #9E9E9E 1px solid;
	color: #000000;
	font-size: 11px;
	font-family: Verdana;
	margin-bottom: 2px;
	margin-right: 0px;
	padding: 0px;
}

.xfields textarea {
width:98%; height:100px;border: #9E9E9E 1px solid; font-size: 11px;font-family: Verdana;
}
.xfields input {
width:350px; height:18px;border: #9E9E9E 1px solid; font-size: 11px;font-family: Verdana;
}
.xfields select {
height:18px; font-size: 11px;font-family: Verdana;
}

.xfields {
height:30px; font-size: 11px;font-family: Verdana;
}
.xprofile textarea {
width:100%; height:90px; font-family:verdana; font-size:11px; border:1px solid #E0E0E0;
}
.xprofile input {
width:250px; height:18px; font-family:verdana; font-size:11px; border:1px solid #E0E0E0;
}
#dropmenudiv{
border:1px solid white;
border-bottom-width: 0;
font:normal 10px Verdana;
background-color: #6497CA;
line-height:20px;
margin:2px;
filter: alpha(opacity=95, enabled=1) progid:DXImageTransform.Microsoft.Shadow(color=#CACACA,direction=135,strength=3);
}

#dropmenudiv a{
display: block;
text-indent: 3px;
border: 1px solid white;
padding: 1px 0;
MARGIN: 1px;
color: #FFF;
text-decoration: none;
font-weight: bold;
}

#dropmenudiv a:hover{ /*hover background color*/
background-color: #FDD08B;
color: #000;
}

#hintbox{ /*CSS for pop up hint box */
position:absolute;
top: 0;
background-color: lightyellow;
width: 150px; /*Default width of hint.*/ 
padding: 3px;
border:1px solid #787878;
font:normal 11px Verdana;
line-height:18px;
z-index:100;
border-right: 2px solid #787878;
border-bottom: 2px solid #787878;
visibility: hidden;
}

.hintanchor{ 
padding-left: 8px;
}

.editor_button {
	float:left;
	cursor:pointer;
	padding-left: 2px;
	padding-right: 2px;
}
.editor_buttoncl {
	float:left;
	cursor:pointer;
	padding-left: 1px;
	padding-right: 1px;
	border-left: 1px solid #BBB;
	border-right: 1px solid #BBB;
}
.editbclose {
	float:right;
	cursor:pointer;
}
	.dle_tabPane{
		height:26px;	/* Height of tabs */
	}
	.dle_aTab{
		border:1px solid #CDCDCD;
		padding:5px;		
		
	}
	.dle_tabPane DIV{
		float:left;
		padding-left:3px;
		vertical-align:middle;
		background-repeat:no-repeat;
		background-position:bottom left;
		cursor:pointer;
		position:relative;
		bottom:-1px;
		margin-left:0px;
		margin-right:0px;
	}
	.dle_tabPane .tabActive{
		background-image:url('engine/skins/images/tl_active.gif');
		margin-left:0px;
		margin-right:0px;	
	}
	.dle_tabPane .tabInactive{
		background-image:url('engine/skins/images/tl_inactive.gif');
		margin-left:0px;
		margin-right:0px;
	}

	.dle_tabPane .inactiveTabOver{
		margin-left:0px;
		margin-right:0px;
	}
	.dle_tabPane span{
		font-family:tahoma;
		vertical-align:top;
		font-size:11px;
		line-height:26px;
		float:left;
	}
	.dle_tabPane .tabActive span{
		padding-bottom:0px;
		line-height:26px;
	}
	
	.dle_tabPane img{
		float:left;
	}
</style>
</head>
<body>
<table align="center" width="94%">
    <tr>
        <td width="4" height="16"><img src="engine/skins/images/tb_left.gif" width="4" height="16" border="0" /></td>
		<td background="engine/skins/images/tb_top.gif"><img src="engine/skins/images/tb_top.gif" width="1" height="16" border="0" /></td>
		<td width="4"><img src="engine/skins/images/tb_right.gif" width="3" height="16" border="0" /></td>
    </tr>
	<tr>
        <td width="4" background="engine/skins/images/tb_lt.gif"><img src="engine/skins/images/tb_lt.gif" width="4" height="1" border="0" /></td>
		<td valign="top" style="padding-top:12px; padding-left:13px; padding-right:13px;" bgcolor="#FAFAFA">
HTML;
		$this->OpenTable();
		echo <<<HTML
<center><font style="font-size:22px; font-weight:bold; font-family:Verdana, Arial, Helvetica, sans-serif; font-stretch:expanded; color:#333333;">$this->module_name</font> <font style="color:#666666">&nbsp;&nbsp;v$this->version</font></center>
HTML;
		$this->CloseTable();
		$this->OpenTable();
		echo $bar;
		$this->CloseTable();
		
		if ($this->show_setting_menu && $this->setting_menu)
		{
		    $this->ShowSettingMenu();
		}
		
		$this->OpenForm('', 'name="form"');
		$this->OpenTable();
		$this->OpenSubtable($this->steps_array[$this->step]);

		echo $text;
		
		if ($status_report)
		{
			echo <<<HTML
<tr>
<td>
<table width="100%">
	<tr>
		<td width=80px align=center valign=middle style="padding:15px;" >
			<img src="{$this->image_patch}/$status_type.png" />
		</td>
		<td style="padding:15px;">
			$status_report
		</td>
	</tr>
</table>
</td></tr>
HTML;
        }
        
		if ($this->buttons)
		{
			$buttons = '';
			foreach ($this->buttons as $name=>$value)
				$buttons .= "&nbsp;&nbsp;<input class='buttons' name='$name' type='submit' style='padding:2px;' value='$value' />";
		}
		else 
		{
			$buttons = '';
		}
			
		echo <<<HTML
     <tr>
     <td height="40" colspan=3 align="right">$buttons&nbsp;&nbsp;
HTML;
		if ($this->button)
		{
			echo <<<HTML
     <input class=buttons id='but' name="button" type="submit" style="padding:2px" value="$this->button" />&nbsp;&nbsp;
HTML;
		}
		
		echo "</tr>";
		
		$this->CloseSubtable();
		$this->CloseTable();
		$this->CloseForm();
		
		if (!$this->year)
			$this->year = date("Y");
		elseif ($this->year != date("Y"))
			$this->year = $this->year ." - " . date("Y");
			
		
		echo <<<HTML
	 <!--MAIN area-->
<div style="padding-top:5px; padding-bottom:10px;">
<table width="100%">
    <tr>
        <td bgcolor="#EFEFEF" height="20" align="center" style="padding-right:10px;"><div class="navigation">Copyright © $this->year <a href="http://www.kaliostro.net" style="text-decoration:underline;color:green">kaliostro</a></div></td>
    </tr>
</table></div>		
		</td>
		<td width="4" background="engine/skins/images/tb_rt.gif"><img src="engine/skins/images/tb_rt.gif" width="4" height="1" border="0" /></td>
    </tr>
	<tr>
        <td height="16" background="engine/skins/images/tb_lb.gif"></td>
		<td background="engine/skins/images/tb_tb.gif"></td>
		<td background="engine/skins/images/tb_rb.gif"></td>
    </tr>
</table>
</body>

</html>
HTML;
		exit();
	}
}

require_once(ROOT_DIR.'/language/'.$config['langs'].'/dle_vb.lng');

$version = '2.2.0';
$module_name = 'DLE + vB Integration';
$year = 2007;
$licence = /*lic*/"."/*/lic*/;
$var = 'dle_vb_conf';
$file= 'dle_vb_conf.php';
$dle = 7.3;
$php = '5.0';
$mysql = 4.1;
$image_patch = "engine/skins/images";
$important_files = array();

$text_main = <<<HTML
<b>Основные возможности:</b>
-Форум может находиться на поддомене 
-Базы форума и сайта могут различаться, если используется одна база то переключение не происходит 
-Прификсы таблиц тоже могут быть как разными так и одинаковыми 
-Каждую возможность можно выключить в админки 
-Двухсторонняя регистрация
-Общая авторизация (даже если сайт и форум на разных доменах и серверах)
-Общий профиль 
----Общие все стандартные поля скриптов
----Настраиваемые общие дополнительные поля профиля
----Общие аваторы
----Общая подпись пользователя
-Восстановление пароля в любом скрипте 
-Общие личные сообщения (общие папки outbox и indox), включая все фишки форума, такие как: 
----Отправка сообщение сразу нескольким пользователям 
----Удаление сразу несколько сообщений одновременно 
----Подтверждение о прочтении 
----Также при прочтении сообщения на форуме оно автоматически помечается как прочтённое на сайте и наоборот. 
-При редактировании/удалении/добавлении пользователей в админке DLE изменения происходят и на форуме, вплоть до изменения логина 
-На сайт можно повесить ссылку "Обсудить на форуме" при переходе по которой автоматически создается(если нету) тема на форуме. 
----Возле ссылки можно выводить количство постов обсуждения 
----Для ссылки может использоваться ЧПУ 
----Вид ссылки настраивается в админке 
----Возможно для разных категорий на сайте назначать отдельные форумы. 
-На сайте также может быть установлен блоки: "Последние сообщения с форума", "Именниники", "Кто на сайте". Блоки кэшируются.
-Все настройки производиться в админке сайта, включая вид отображения блоков, поста на форуме и ссылки на форум. Данные для блока "Кто на сайте беруться из базы сессий форума и отображают всех пользователей, которые находятся на сайте и на форуме следовательно используется один запрос 
-Постепенный перес пользователей с сайта на форум и наоборот. То есть интеграцию можно ставить на сайт с пользователями и новым форумом, пользователи смогу авторизоваться в любом скрипте, а также и наоборот.
HTML;
$text_main = nl2br($text_main);

if ($_POST['type'] == "update")
{
	$obj = new install_update($module_name, $version, array(), $licence, $db, $image_patch);
	$obj->year = $year;
	require(ENGINE_DIR . "/data/" . $file);
	$module_config = $$var;
	
	switch ($module_config['version_id'])
	{
        case '2.0.0':
            $to_version = $version;
            $obj->steps_array = array(
                                    "ChangeLog",
                                    "Проверка хостинга",
                                    "Завершение обновления"
                                    );
                                    $ChangeLog = <<<TEXT
<b>Обновление до версии $to_version</b>
            
[fix] - Исправдены все обнаруженные ошибки
[+] - Добавлена возможноть общей авторизации при разных доменах сайта и форума
[+] - Интегрированы аваторы
[+] - Интегрированы подписи пользователей
[+] - Интегрированы все стандартные поля обоих скриптов
[+] - Интегрированы дополнительные поля профеля сайта и форума

TEXT;

                                    $important_files = array(
                                                        './install.php',
                                                        './engine/data/dle_vb_conf.php',
                                    );
                                    
                                    $ChangeLog = nl2br($ChangeLog);
                                    $finish_text = <<<HTML
<div style="text-align:center;">Обновление модуля до версии $to_version прошло успешно.</div>
HTML;
                                    switch (intval($_POST['step']))
                                    {
                                        case 0:
                                            $obj->Main($ChangeLog, 'Начать обновление');
                                            break;

                                        case 1:
                                            $obj->CheckHost($important_files, $dle, $php, $mysql);
                                            break;
                                            
                                        case 2:
                                            $module_config['fields'] = array (
                                                'info' => "1",
                                                'land' => "2",
                                                );
                                            $obj->ChangeVersion($file, $var, $module_config, array(), $to_version);
                                            $obj->Finish($finish_text, $to_version);
                                            break;
                                    }
            break;
	    
	    
	    case '1.5.0':
        case '1.5.5':
        case '1.6.0':
	        $to_version = '2.0.0';
            $obj->steps_array = array(
                                    "ChangeLog",
                                    "Завершение обновления"
                                    );
                                    $ChangeLog = <<<TEXT
<b>Обновление до версии $to_version</b>
            
[+] - Добавлена поддержка 4-ой версии форума
[+] - Уменьшена нагрузка
[+] - Добавлена возможность размещать сайт и форум на разных серверах
[+] - Установка на форум не требует изменений в файлах
[+] - Добавлена возможность переноса пользователей с форума на сайт,

TEXT;
                                    $ChangeLog = nl2br($ChangeLog);
                                    $finish_text = <<<HTML
<div style="text-align:center;">Обновление модуля до версии $to_version прошло успешно.</div>
HTML;
                                    switch (intval($_POST['step']))
                                    {
                                        case 0:
                                            $obj->Main($ChangeLog, 'Начать обновление');
                                            break;
                                                
                                        case 1:
                                            $obj->ChangeVersion($file, $var, $module_config, array(), $to_version);
                                            $obj->Finish($finish_text, $to_version);
                                            break;
                                    }
            break;
	    
		case $version:
			$obj->Finish("<div style=\"text-align:center;font-size:150%;\">Вы используете актуальную версию скрипта. Обновление не требуется</div>");
			break;
			
		default:
			$text = <<<TEXT
<b>Не известная версия модуля. Переустановите модуль.</b>
TEXT;
			$obj->OtherPage($text);
			break;
	}
}
else 
{
	$title = array(
					"Описание модуля",
					"Лицензионное соглашение",
					"Проверка хостинга",
					"Создание файла настроек",
					"Найстройки БД форума",
					"Завершение установки"
				);
				
	$obj = new install_update($module_name, $version, $title, $licence, $db, $image_patch);
	$obj->year = $year;

	switch ($_POST['step'])
	{
	    case 1:
	        $head_licence = <<<HTML
Пожалуйста внимательно прочитайте и примите пользовательское соглашение по использованию модуля "$module_name".
HTML;

	        $text_licence = <<<HTML
Покупатель имеет право:</b><ul><li>Изменять дизайн и структуру программного продукта в соответствии с нуждами своего сайта.</li><br /><li>Производить и распространять инструкции по созданным Вами модификациям шаблонов и языковых файлов, если в них будет иметься указание на оригинального разработчика программного продукта до Ваших модификаций.</li><br /><li>Переносить программный продукт на другой сайт после обязательного уведомления меня об этом, а также полного удаления скрипта с предыдущего сайта.</li><br /></ul><br /><b>Покупатель не имеет право:</b><br /><ul><li>Передавать права на использование интеграции третьим лицам, кроме случаев, перечисленных выше в нашем соглашении.</li><br /><li>Изменять структуру программных кодов, функции программы или создавать родственные продукты, базирующиеся на нашем программном коде</li><br /><li>Использовать более одной копии модуля <b>$module_name</b> по одной лицензии</li><br /><li>Рекламировать, продавать или публиковать на своем сайте пиратские копии модуля</li><br /><li>Распространять или содействовать распространению нелицензионных копий модуля <b>$module_name</b></li><br /></ul>
HTML;
	        
			$obj->Licence($head_licence, $text_licence);
			
			
		case 2:
		    $important_files = array(
						'./install.php',
						'./engine/data/',
						'./engine/cache/'
						);
		    
			$obj->CheckHost($important_files, $dle, $php, $mysql);
			
        case 3:
            $dle_vb_conf = array (
                                'vb_lastpost_onoff' => "0",
                                'vb_block_new_count_post' => "10",
                                'vb_block_new_leght_name' => "45",
                                'vb_block_new_cache_time' => "60",
                                'vb_block_new_badf' => "",
                                'vb_block_new_goodf' => "",
                                'vb_birthday_onoff' => "0",
                                'vb_block_birthday_cache_time' => "1800",
                                'count_birthday' => "50",
                                'no_user_birthday' => "Именниников сегодня нет",
                                'vb_block_birthday_spacer' => ", ",
                                'birthday_block' => "<a href=\"{user_url}\">{name}</a> ({age})",
                                'vb_online_onoff' => "0",
                                'vb_block_online_cache_time' => "60",
                                'separator' => ", ",
                                'vb_goforum' => "0",
                                'link_title' => "title",
                                'link_text' => "short",
                                'link_on_news' => "0",
                                'link_user' => "author",
                                'vb_link_name_post_on_forum' => "Статья : {Post_name}",
                                'text_post_on_forum' => "Здесь обсуждается статья: [URL='{post_link}']{Post_name}[/URL]",
                                'vb_link_link_on_forum' => "<a href='{link_on_forum}' title='Перейти на форум'>Обсудить на форуме[count] ({count})[/count]</a>",
                                'postusername' => "SiteInformer",
                                'postuserid' => "0",
                                'vb_link_forumid' => array (),
                                'vb_onoff' => "1",
                                'vb_content_charset' => "UTF-8",
                                'vb_reg' => "1",
                                'vb_profile' => "1",
                                'vb_lost' => "1",
                                'vb_pm' => "1",
                                'vb_login' => "1",
                                'vb_logout' => "1",
                                'vb_admin' => "1",
                                'vb_login_create_account' => "0",
                                'vb_login_create_dle_account' => "0",
                                'fields' => array (
                                                'info' => "1",
                                                'land' => "2",
                                                ),
            );
        
            $obj->setting_menu = array(
				$dle_vb_lang['block_new'] => 'engine/skins/images/block_new.jpg', 
				$dle_vb_lang['block_birth'] => 'engine/skins/images/block_birth.jpg', 
				$dle_vb_lang['block_online'] => 'engine/skins/images/block_online.jpg', 
				$dle_vb_lang['link'] => 'engine/skins/images/link.jpg',
				$dle_vb_lang['settings'] => 'engine/skins/images/settings.jpg'
				);
        	
        	require(ENGINE_DIR . "/inc/dle_vb.php");
        	
			$obj->Settings($settings_array, $dle_vb_conf, $var, $file);
			
			$obj->setting_menu = array();
			
		case 4:
		    
		    $db_fields_array = array(
	                                  'VB_HOST' => array('Адрес сервера БД форума', 'localhost', '$config[\'MasterServer\'][\'servername\']'),
	                                  'VB_USER' => array('Имя пользоваетля БД форума', 'root', "\$config['MasterServer']['username']"),
	                                  'VB_PASS' => array('Пароль пользователя БД форума', '', "\$config['MasterServer']['password']"),
	                                  'VB_BASE' => array('Имя БД форума', DBNAME, "\$config['Database']['dbname']"),
	                                  'VB_COLLATE' => array('Кодировка БД форума', 'utf8', "\$config['Mysqli']['charset'] (Если переменная закомментирована то оставте поля пустым)"),
		                              'VB_PREFIX' => array('Префикс таблиц форума', '', "\$config['Database']['tableprefix']"),
		                              'COOKIE_PREFIX' => array('Префикс куков форума', 'bb', "\$config['Misc']['cookieprefix']"),
		                              'COOKIE_SALT' => array('Ключ безопасности куков', '', "\$config['Misc']['cookie_security_hash'] или для лицензионных форумов константа COOKIE_SALT в файле /includes/functions.php"),
		                              );
		    
		    $db_forum = "
		    <script type='text/javascript' >
		    
		    function EqualDLE(elm)
		    {
		        for (i in document.form.elements)
		        {
		              if (document.form.elements[i].type == 'text' && 
		                  document.form.elements[i].name != 'VB_PREFIX' &&
		                  document.form.elements[i].name != 'VB_COLLATE' &&
		                  document.form.elements[i].name != 'COOKIE_PREFIX' &&
		                  document.form.elements[i].name != 'COOKIE_SALT'
		                  )
		              {
		                  document.form.elements[i].disabled = elm.checked;
		                  //document.form.elements[i].style.color = (elm.checked)?'#9E9E9E':'';
		                  document.form.elements[i].style.backgroundColor = (elm.checked)?'#D0D0D0':'';
		              }
		        }
		    }
		    
		    </script>
		    <div style='padding:5px; margin-bottom:10px; font-size:120%'>Зпоните настройки форума, значение большинства настроек можно найти в файле форума /includes/config.php их переменные в этом файле написаны справа от поля ввода. Значение для поля \"Ключ безопасности куков\" для лицензионной версии форума нужно искать в файле /includes/functions.php значение для костанты COOKIE_SALT, срока примерно 34. </div>
		    
		    <table width='100%'>";
		    
		    foreach ($db_fields_array as $field=>$value)
		    {
		        $db_forum .= "<tr><td height='25px' width='250px;'>" . $value[0] . "</td>";
		        $db_forum .= "<td><input class='edit' type='text' name='$field' value='{$value[1]}' />&nbsp;{$value[2]}</td></tr>";
		    }
		    $db_forum .= "<tr><td colspan=2><input OnClick='EqualDLE(this);' type='checkbox' name='equal_dle' value=1 /> Таблицы форума находться в БД сайта (укажите префикс таблиц если сущестует)</td></tr>";
		    $db_forum .= "</table>";
		    
		    $db_forum_status = 'Форум должен быть установлен, если он находиться на другом сервере, то должен быть организован удалённый доступ к БД форума данным пользователем. После отправки данных доступность БД форума будет проверено.';
		    
        	function CheckDataBase(install_update &$obj)
        	{
        	    global $db;
        	    
        	    $errors = array();
        	    
        	    if (!empty($_POST['equal_dle']))
        	    {
        	        $host = DBHOST;
        	        $user = DBUSER;
        	        $pass = DBPASS;
        	        $dbname = DBNAME;
        	        $collate = '';
        	    }
        	    else 
        	    {
        	        $host = $_POST['VB_HOST'];
                    $user = $_POST['VB_USER'];
                    $pass = $_POST['VB_PASS'];
                    $dbname = $_POST['VB_BASE'];
                    $collate = $_POST['VB_COLLATE'];
                    
                    if (empty($host))
                    {
                        $errors[] = 'Не указан адрес сервера БД форума';
                    }
                    
                    if (empty($user))
                    {
                        $errors[] = 'Не указан пользователь БД форума';
                    }
                    
                    if (empty($dbname))
                    {
                        $errors[] = 'Не указана БД форума';
                    }
        	    }
        	    
        	    if (!$errors)
        	    {
                    if ($db->connect($user, $pass, $dbname, $host, 0))
                    {
                        if ($vb_version = $db->super_query("SELECT value FROM " . $_POST['VB_PREFIX'] . "setting WHERE varname='templateversion'", 0))
                        {
                            if (version_compare($vb_version['value'], '4.0.0', ">="))
                            {
                                $_POST['COOKIE_PREFIX'] = (empty($_POST['COOKIE_PREFIX'])?'bb':$_POST['COOKIE_PREFIX'] . "_");
                            }
                            else 
                            {
                                $_POST['COOKIE_PREFIX'] = (empty($_POST['COOKIE_PREFIX'])?'bb':$_POST['COOKIE_PREFIX']);
                            }
$vb_conf = <<<PHP
<?php

//vBulletin DataBase Configurations

define('VB_HOST', '$host');
define('VB_USER', '$user');
define('VB_PASS', '$pass');
define('VB_BASE', '$dbname');
define('CHARACTER', true);
define('VB_COLLATE', '$collate');
define('VB_PREFIX', '{$_POST['VB_PREFIX']}');
define('COOKIE_PREFIX', '{$_POST['COOKIE_PREFIX']}');
define('COOKIE_SALT', '{$_POST['COOKIE_SALT']}');

?>
PHP;
                            $handler = fopen(ENGINE_DIR.'/data/vb_conf.php', "w");
                            fwrite($handler, $vb_conf);
                            fclose($handler);
                            
                            $return = false;
                        }
                        else
                        {
                            $return = array('В данной БД форум не найден, проверте префикс таблиц и данные БД');
                        }
                    }
                    else
                    {
                        $return = array("Не возможно подключиться к БД форума, проверте настройки");
                    }
                    
                    $db->connect(DBUSER, DBPASS, DBNAME, DBHOST);
                    
                    return $return;
        	    }
        	    
        	    return $errors;
        	}
        	
	        $obj->OtherPage($db_forum, $db_forum_status, 'CheckDataBase');
		    
		    
		case 5:
		    $text_finish = <<<TEXT
	<div style="font-size:120%;text-align:center">Благодарим вас за покупку модуля. Надеемся что работа с ним доставит Вам только удовольствие!!! Все возникшие вопросы вы можете найти в документации или задать их на форуме поддержки <a href="http://forum.kaliostro.net/" >http://forum.kaliostro.net/</a> . </div>
TEXT;
            $obj->AddAdminSection('dle_vb', $module_name, 'Users integration', 'dle_vb.gif', 1);
		    
			$obj->Finish($text_finish);
			break;
			
		default:
			if (file_exists(ENGINE_DIR.'/data/'.$file) && empty($_POST['type']))
			{
				require(ENGINE_DIR . "/data/" . $file);
				$config = $$var;
				$obj->steps_array = array();
				$obj->steps_array[] = "Описание модуля";
				
				switch ($config['version_id'])
				{
					case '1.5.0':
					case '1.5.5':
					case '1.6.0':
						$obj->steps_array[] = '2.0.0';
						
					case '2.0.0':
						$obj->steps_array[] = $version;
						
					default:
						$obj->steps_array[] = "Завершение обновления";
				}
				$obj->SetType("update", "Начать обновление");
				$obj->Main($text_main, "Начать обновление");
			}
			else 
			{
				$obj->SetType("install");
				$obj->Main($text_main, "Начать установку");
			}
			
			break;
	}
}

?>
