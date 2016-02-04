<?php


class vBIntegration
{
    /**
     * 
     * @var db
     */
    protected $db = null;
    
    protected $user = array();
    
    protected $config = array();
    
    protected $vb_config = array();
    
    protected $lang = array();
    
    protected $connect_method = 'connect';
    
    protected $connected = false;
    
    /**
     * 
     * @var ParseFilter
     */
    protected $_parse = null;
    
    protected $lock_connect = false;
    
    protected $user_vb_field = array(
                                    -5 => 'homepage',
                                    -4 => 'aim',
                                    -3 => 'yahoo',
                                    -2 => 'msn',
                                    -1 => 'skype'
                                    );
    
    public function __construct(db &$db)
    {        
        if (!file_exists(ENGINE_DIR . "/data/dle_vb_conf.php"))
        {
            if (defined('INSTALL'))
            {
                return;
            }
            
            @header("Content-type: text/html; charset=" . $GLOBALS['config']['charset'] );
            echo "Модуль не установлен<br />";
            echo "Module is not installed";
            exit();
        }
        
        if (!file_exists(ENGINE_DIR . "/data/vb_conf.php"))
        {
            if (defined('INSTALL'))
            {
                return;
            }
            
            @header("Content-type: text/html; charset=windows-1251");
            echo "Файл с настройками БД форума не найден<br />";
            echo "Configuration file of DB Forum not found";
            exit();
        }

        $dle_vb_lang = $dle_vb_conf = array();
        
        require ENGINE_DIR . "/data/dle_vb_conf.php";
        require ENGINE_DIR . "/data/vb_conf.php";
        require ROOT_DIR.'/language/'.$GLOBALS['config']['langs'].'/dle_vb.lng';
        
        if (isset($GLOBALS['lang']))
        {
            $this->lang = array_merge($GLOBALS['lang'], $dle_vb_lang);
        }
        else 
        {
            $this->lang = $dle_vb_lang;
        }
        
        $this->config = $dle_vb_conf;
        
        $this->db =& $db;
        
        if (DBHOST == VB_HOST &&
            DBUSER == VB_USER &&
            DBPASS == VB_PASS
            )
        {
            if (DBNAME == VB_BASE)
            {
                $this->connect_method = "none";
            }
            else 
            {
                $this->connect_method = "use";
            }
        }
        
        $this->_db_connect();
        $this->_vb_config();
        $this->_db_disconnect();
        
        if (!empty($this->config['vb_content_charset']))
        {
            define('VB_CHARSET', $this->config['vb_content_charset']);
        }
        else if (!empty($this->vb_config['charset']))
        {
            define('VB_CHARSET', $this->vb_config['charset']);
        } 
        else
        {
            define('VB_CHARSET', '');
        }
        
        if (!defined('COLLATE'))
        {
            define('COLLATE', 'cp1251');
        }
        
        define('DLE_CHARSET', $GLOBALS['config']['charset']);
        define('IPADRESS', $_SERVER['REMOTE_ADDR']);
        define('TIMENOW', time());
        
        if (isset($_REQUEST['do']) && 
            $_REQUEST['do'] == "goforum" && 
            $this->config['vb_goforum'] && 
            !empty($_REQUEST['postid']) && 
            $this->config['vb_onoff']
            )
            {
                $this->GoForum();
            }
            
        if (!empty($_GET['vbauth']))
        {
            $data = urldecode($_GET['vbauth']);
            
            if ($this->_validate_request($data, $_GET['hash']))
            {
                $info = @unserialize(base64_decode($data));
                
                if (is_array($info))
                {
                    $this->_external_dle_auth($info[0], $info[1], $info[2]);
                }
                else 
                {
                    die('Bad data');
                }
            }
        }
        
        if (!empty($_GET['vblogout']))
        {
            $this->_external_dle_logout();
        }
        
        if (!empty($_GET['data']) && !empty($_GET['hash']))
        {
            ignore_user_abort(true);
            $data = urldecode($_GET['data']);
            $hash = $_GET['hash'];
            
            if ($this->_get_request_hash($data) != $hash)
            {
                die('Hacking');
            }
            
            $info = @unserialize(base64_decode($data));
            
            if (is_array($info))
            {
                $this->UpdateDLEAvatar($info);
            }
            else 
            {
                die("Not array");
            }
            
            exit();
        }
    }
    
    protected function &_db_connect()
    {
        if (!$this->lock_connect && !$this->connected)
        {
            switch ($this->connect_method) 
            {
                case 'connect':
                    $this->db->connect(VB_USER, VB_PASS, VB_BASE, VB_HOST);
                    break;
                    
                case 'use':
                    $this->db->query("USE `" . VB_BASE . "`");
                    break;
            	
                default:
                    break;
            }
            
            if (VB_COLLATE && VB_COLLATE != COLLATE)
            {
                $this->db->query("SET NAMES '" . VB_COLLATE ."'");
                if (defined('CHARACTER') && CHARACTER)
                {
                    if (is_bool(CHARACTER))
                    {
                        $this->db->query("SET CHARACTER SET '" . VB_COLLATE . "'");
                    }
                    else
                    {
                        $this->db->query("SET CHARACTER SET '" . CHARACTER . "'");
                    }
                }
            }
            else 
            {
                $this->db->query("SET NAMES DEFAULT");
                //$this->db->query("SET CHARACTER SET DEFAULT");
            }
            
            $this->connected = true;
        }
        
        return $this->db;
    }
    
    protected function _db_disconnect()
    {
        if (!$this->lock_connect && $this->connected)
        {
            switch ($this->connect_method) 
            {
                case 'connect':
                    $this->db->connect(DBUSER, DBPASS, DBNAME, DBHOST);
                    //$this->db->query("SET CHARACTER SET '" . COLLATE . "'");
                    break;
                    
                case 'use':
                    $this->db->query("USE `" . DBNAME . "`");
                    $this->db->query("SET NAMES '" . COLLATE ."'");
                    break;
                    
                default:
                    $this->db->query("SET NAMES '" . COLLATE ."'");
                    //$this->db->query("SET CHARACTER SET '" . COLLATE . "'");
                    break;
            }
            
            $this->connected = false;
        }
    }
    
    protected function _init_parse()
    {
        if (!$this->_parse)
        {
            if (empty($GLOBALS['parse']) || !($GLOBALS['parse'] instanceof ParseFilter))
            {
                if (!class_exists('ParseFilter'))
                {
                    require_once(ENGINE_DIR . "/classes/parse.class.php");
                }
                $this->_parse = new ParseFilter();
            }
            else
            {
                $this->_parse =& $GLOBALS['parse'];
            }
        }
        
        return $this->_parse;
    } 
    
    private function _vb_config()
    {
        if (!$this->vb_config)
        {
            if (!function_exists("dle_cache") || !($cache = dle_cache("config_vb")))
            {
                $this->vb_config = array();
                $this->db->query("SELECT varname, value, defaultvalue, l.charset FROM 
                                " . VB_PREFIX . "setting AS s
                                LEFT JOIN " . VB_PREFIX . "language AS l
                                ON l.languageid=s.value AND s.varname='languageid'
                                WHERE varname IN (
                                        'ipcheck',
                                        'cookiepath',
                                        'cookiedomain',
                                        'cookietimeout',
                                        'activememberdays',
                                        'avatarpath',
                                        'profilepicpath',
                                        'sigpicpath',
                                        'bburl',
                                        'refresh',
                                        'usefileavatar',
                                        'timeoffset',
                                        'languageid')" );
                while ($row = $this->db->get_row()) 
                {
                    if ($row['varname'] == 'languageid')
                    {
                        $this->vb_config['charset'] = $row['charset'];
                    }
                    
                    $this->vb_config[$row['varname']] = $row['value'];
                }
                
                if (function_exists("create_cache"))
                {
                    create_cache("config_vb", serialize($this->vb_config));
                }
            }
            else
            {
                $this->vb_config = unserialize($cache);
            }
        }
    }
    
    protected function _is_diff_domain()
    {
        static $diff_domain;
        
        if ($diff_domain === null)
        {
            $dle_domain = substr(DOMAIN, -(strlen(DOMAIN)-1));
            $diff_domain = (strpos($this->vb_config['bburl'], $dle_domain) === false)?true:false;
        }
        
        return $diff_domain;
    }
    
    protected function _get_request_hash($data)
    {
        return md5(VB_PASS . $data . VB_BASE);
    }
    
    protected function _validate_request($data, $hash)
    {
        if (md5(VB_PASS . $data . VB_BASE) != $hash)
        {
            die('Hacking');
        }
        
        return true;
    }
    
    protected function _convert_charset(&$data)
    {
        if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
        {
            if (is_array($data))
            {
                foreach ($data as &$value)
                {
                    $this->_convert_charset($value);
                }
            }
            else
            {
                $data = iconv(DLE_CHARSET, VB_CHARSET, $data);
            }
        }
        
        return $data;
    }
    
    protected function _fetch_substr_ip($ip, $length = null)
    {
        if ($length === null OR $length > 3)
        {
            $length = $this->vb_config['ipcheck'];
        }
        
        return implode('.', array_slice(explode('.', $ip), 0, 4 - $length));
    }
    
    protected function _create_session($session_id, $user_id, $name, $pass, $location, $diff_domain = false)
    {
        if (!$session_id)
        {
            if ($diff_domain)
            {
                return false;
            }
            
            $session_id = md5(md5(COOKIE_PREFIX). SESSION_IDHASH.time());
        }
            
        $_IP = $this->db->safesql(IPADRESS);
        $location = $this->db->safesql($location);
        $name = $this->db->safesql($name);
        
        if ($user_id)
        {
            $this->db->query("DELETE FROM " . VB_PREFIX . "strikes WHERE strikeip = '$_IP' AND username='$name'");
        }
            
        if ($user_id)
        {
            $this->db->query("DELETE FROM " . VB_PREFIX . "session WHERE sessionhash='$session_id' OR userid='$user_id'");
        }
        else 
        {
            $this->db->query("DELETE FROM " . VB_PREFIX . "session WHERE sessionhash='$session_id'");
        }
            
        $this->db->query("INSERT IGNORE INTO ". VB_PREFIX ."session (sessionhash, userid, host, idhash, lastactivity, location, useragent, loggedin) values ('$session_id','$user_id','$_IP','" . SESSION_IDHASH . "', '" . TIMENOW . "','$location','" . $this->db->safesql($_SERVER['HTTP_USER_AGENT'])."', '2')");
                
        if ($diff_domain)
        {
            set_cookie(COOKIE_PREFIX . "sessionhash", $session_id, 365);
        }
        else 
        {
            setcookie(COOKIE_PREFIX."sessionhash", $session_id, TIMENOW + 60 * 60 * 24 * 365, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
        }
        
        $_SESSION['forum_session_id'] = $session_id;
        
        if ($user_id)
        {
            if (!$diff_domain)
            {
                setcookie(COOKIE_PREFIX . "userid", $user_id,  TIMENOW + 60 * 60 * 24 * 365, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
                setcookie(COOKIE_PREFIX . "password", md5($pass . COOKIE_SALT),  TIMENOW + 60 * 60 * 24 * 365, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
            }
            
            $this->db->query("UPDATE " . VB_PREFIX . "user set lastactivity='" . TIMENOW . "', ipaddress='$_IP' WHERE userid='$user_id'");
        }
    }
    
    protected function _user_salt($length = 3)
    {
        $salt = '';
        for ($i = 0; $i < $length; $i++)
        {
            $salt .= chr(rand(32, 126));
        }
        return $salt;
    }
    
    protected function _create_dle_account()
    {
        $member_id['email'] = $this->user['email'];
        $member_id['icq'] = $this->user['icq'];

        if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
        {
            $member_id['email'] = iconv(VB_CHARSET, DLE_CHARSET, $this->user['email']);
            $member_id['icq'] = iconv(VB_CHARSET, DLE_CHARSET, $this->user['icq']);
        }
        
        $member_id['user_group'] = $GLOBALS['config']['reg_group'];
        $member_id['name'] = $_POST['login_name'];
        
        $add = array();
        $add['name'] = $this->db->safesql($_POST['login_name']);
        $add['password'] = md5($_POST['login_password']);
        $add['email'] = $this->db->safesql($this->user['email']);
        $add['icq'] = $this->db->safesql($member_id['icq']);
        $add['reg_date'] = TIMENOW + $GLOBALS['config']['date_adjust'] * 60;
        $add['lastdate'] = TIMENOW + $GLOBALS['config']['date_adjust'] * 60;
        $add['logged_ip'] = $this->db->safesql(IPADRESS);
        
        $update_fields = array();
        $this->_init_parse();
        
        foreach ($this->config['fields'] as $dle_field => $vb_field_id)
        {
            if ($vb_field_id)
            {
                if ($vb_field_id < 0)
                {
                    $vb_field = array_search($vb_field_id, $this->user_vb_field);
                }
                else 
                {
                    $vb_field = 'field' . $vb_field_id;
                }
                
                if (empty($this->user[$vb_field]))
                {
                    continue;
                }
                else 
                {
                    $value = $this->user[$vb_field];
                }
                
                if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
                {
                    $value = iconv(VB_CHARSET, DLE_CHARSET, $value);
                }
                
                if (in_array($dle_field, array('info', 'land', 'fullname')))
                {
                    $member_id[$dle_field] = $add[$dle_field] = $this->db->safesql($value);
                }
                else 
                {
                    $value = $this->_parse->BB_Parse($this->_parse->process($value));
                    $value = str_replace( "|", "&#124;", $value );
                    $update_fields[] = $this->db->safesql($dle_field . "|" . $value);
                }
            }
        }
        $add['user_group'] = $GLOBALS['config']['reg_group'];
        $add['favorites'] = '';
        $add['signature'] = '';
        
        if ($update_fields)
        {
            $add['xfields'] = implode("||", $update_fields);
        }
        
        $this->db->query( "INSERT INTO " . USERPREFIX . "_users (" . implode(", ", array_keys($add)) . ") VALUES ('" . implode("', '", $add) ."')" );
        
        $member_id['user_id'] = $this->db->insert_id();
        $member_id['logged_ip'] = $_SERVER['REMOTE_ADDR'];
        $member_id['reg_date'] = $member_id['lastdate'] = time() + $GLOBALS['config']['date_adjust'] * 60;
        
        set_cookie( "dle_user_id", $member_id['user_id'], 365 );
        set_cookie( "dle_password", $_POST['login_password'], 365 );
        
        $_SESSION['dle_user_id'] = $member_id['user_id'];
        $_SESSION['dle_password'] = $_POST['login_password'];
        $_SESSION['member_lasttime'] = $member_id['lastdate'];
        $_SESSION['dle_log'] = 0;
        $GLOBALS['dle_login_hash'] = md5( strtolower( $_SERVER['HTTP_HOST'] . $member_id['name'] . $_POST['login_password'] . $GLOBALS['config']['key'] . date( "Ymd" ) ) );
        
        if($GLOBALS['config']['log_hash'])
        {
            $hash = md5(uniqid(time()) . time());
            
            $this->db->query( "UPDATE " . USERPREFIX . "_users set hash='" . $hash . "' WHERE user_id='$member_id[user_id]'" );
            
            set_cookie( "dle_hash", $hash, 365 );
            
            $_COOKIE['dle_hash'] = $hash;
            $member_id['hash'] = $hash;
        }
        
        $GLOBALS['member_id'] = $member_id;
        $GLOBALS['is_logged'] = true;
        
        if ($this->user['avatarid'])
        {
            $avatarid = $this->_db_connect()->super_query("SELECT avatarpath FROM " . VB_PREFIX . "avatar WHERE avatarid=" . $this->user['avatarid']);
            
            if ($avatarid)
            {
                $this->_db_disconnect();
                $this->UpdateDLEAvatar(array('username' => $this->user['username'], 'avatarurl' => $avatarid['avatarurl']));
            }
        }
        else if ($this->vb_config['usefileavatar'] && $this->user['avatarrevision'])
        {
            $this->UpdateDLEAvatar(array('username' => $this->user['username'], 'avatarurl' => $this->vb_config['bburl'] . "/" . $this->vb_config['avatarurl'] . "/avatar" . $this->user['userid'] . "_" . $this->user['avatarrevision'] . ".gif"));
        }
    
        return $member_id;
    }
    
    protected function _external_dle_auth($user_id, $password_md5, $session_id)
    {
        set_cookie(COOKIE_PREFIX . "sessionhash", $session_id, 365);
        
        set_cookie("dle_user_id", $user_id, 365);
        set_cookie("dle_password", $password_md5, 365);

        if (!session_id())
        {
            session_start();
        }
        
        
        $_SESSION['dle_user_id'] = $user_id;
        $_SESSION['dle_password'] = $password_md5;
        $_SESSION['forum_session_id'] = $session_id;
        
        if( $GLOBALS['config']['log_hash'] )
        {
            $hash = md5( uniqid() . time() );
            
            $this->db->query( "UPDATE " . USERPREFIX . "_users set hash='" . $hash . "', lastdate='{$GLOBALS['_TIME']}', logged_ip='" . IPADRESS . "' WHERE user_id='$user_id'" );
            
            set_cookie( "dle_hash", $hash, 365 );
        }
        else
        {
            $this->db->query( "UPDATE LOW_PRIORITY " . USERPREFIX . "_users set lastdate='{$GLOBALS['_TIME']}', logged_ip='" . IPADRESS . "' WHERE user_id='$user_id'" );
        }
        
        header("Location:" . $this->vb_config['bburl']);
        echo "Вы были перенаправлены сюда <a href='{$this->vb_config['bburl']}'>" . $this->vb_config['bburl'] . "</a>";
        
        exit();
    }
    
    protected function _external_dle_logout()
    {
        set_cookie( "dle_user_id", "", 0 );
        set_cookie( "dle_name", "", 0 );
        set_cookie( "dle_password", "", 0 );
        set_cookie( "dle_skin", "", 0 );
        set_cookie( "dle_newpm", "", 0 );
        set_cookie( "dle_hash", "", 0 );
        set_cookie( session_name(), "", 0 );
        $_SESSION['dle_user_id'] = false;
        @session_destroy();
        @session_unset();
        
        if (!empty($_SERVER['HTTP_REFERER']))
        {
            header('Location:' . $_SERVER['HTTP_REFERER']);
            echo "Вы были перенаправлены сюда <a href='{$_SERVER['HTTP_REFERER']}'>" . $_SERVER['HTTP_REFERER'] . "</a>";
        }
        else 
        {
            header("Location:" . $this->vb_config['bburl']);
            echo "Вы были перенаправлены сюда <a href='{$this->vb_config['bburl']}'>" . $this->vb_config['bburl'] . "</a>";
        }
    }
    
    private function _vb_redirect($uri, $data)
    {
        $url = $this->vb_config['bburl'] . "/$uri=" . urlencode($data) . "&hash=" . $this->_get_request_hash($data);

        header('Location:' . $url);

        die("Вы были перенаправлены сюда <a href='$url'>" . $url . "</a>");
    }
    
    public function Login(array $member_id)
    {
        $allow = true;
        
        if (!$this->config['vb_onoff'] || !$this->config['vb_login'])
        {
            $allow = false;
        }
        if ((empty($member_id) || empty($member_id['user_id'])) && !$this->config['vb_online_onoff'])
        {
            $allow = false;
        }
        if (isset($_REQUEST['action']) AND $_REQUEST['action'] == "logout")
        {
            $allow = false;
        }
        
        if (empty($member_id['user_id']) && 
            $this->config['vb_login_create_dle_account'] && 
            isset($_POST['login']) && $_POST['login'] == "submit" &&
            !empty($_POST['login_name']) &&
            !empty($_POST['login_password']))
        {
            $login_password  = $_POST['login_password'];
            $this->_convert_charset($login_password);
            
            $create_username = $_POST['login_name'];
            $create_username = $this->db->safesql($this->_convert_charset($create_username));
            
            $this->user = $this->_db_connect()->super_query("SELECT u.*, uf.field1, uf.field2 FROM " . VB_PREFIX . "user AS u
                                               LEFT JOIN " . VB_PREFIX . "userfield AS uf
                                               ON uf.userid=u.userid
                                               WHERE username='$create_username'");
            
            $this->_db_disconnect();
            
            if ($this->user && md5($login_password . $this->user['salt']) == $this->user['password'])
            {
               
                $member_id = $this->_create_dle_account();
                
                if ($this->config['vb_onoff'] && $this->config['vb_login'])
                {
                    $this->_db_connect();
                    $allow = true;
                }
            }
        }
        
        if ($allow && $this->_is_diff_domain())
        {
            if (!empty($member_id['name']) && 
                isset($_POST['login']) && $_POST['login'] == "submit" &&
                !empty($_POST['login_name']) &&
                !empty($_POST['login_password'])
                )
            {
                $vbusername = $this->db->safesql($this->_convert_charset($member_id['name']));
                
                if (empty($this->user['salt']) || empty($this->user['password']))
                {
                    $this->user = $this->_db_connect()->super_query("SELECT * FROM " . VB_PREFIX . "user WHERE username='$vbusername'");
                }
                
                if ($this->user)
                {
                    $login_password  = $_POST['login_password'];
                    $this->_convert_charset($login_password);
                    
                    if (md5($login_password . $this->user['salt']) == $this->user['password'])
                    {
                        $this->_vb_redirect('?dleauth', $this->user['userid']);
                    }
                    else 
                    {
                        $this->user = array();
                    }
                }
            }
            
            if (!empty($_GET['vbsession']))
            {
                $_SESSION['forum_session_id'] = $_GET['vbsession'];
            }
            
            $diff_doamin = true;
        }
        else 
        {
            $diff_doamin = false;
        }
        
        if ($allow)
        {
            $location = "";
            if ($this->config['vb_online_onoff'])
            {
                if (isset ($_REQUEST['do'])) $do = $_REQUEST['do']; else $do = "";
                if (isset ($_REQUEST['category'])) $category = $_REQUEST['category']; else $category = "";
                if (isset ($_REQUEST['subaction'])) $subaction = $_REQUEST['subaction']; else $subaction = "";
                if ($do == "cat" AND $category != '' AND $subaction == '')
                {
                    $location = "%incategory%" . stripslashes($GLOBALS['cat_info'][$GLOBALS['category_id']]['name']);
                }
                elseif ($subaction == 'userinfo') $location = "%view_pofile%" .$_REQUEST['user'];
                elseif ($subaction == 'newposts') $location = "%newposts%";
                elseif ($do == 'stats') $location = "%view_stats%"; 
                elseif ($do == 'addnews') $location = "%posin%" . $this->lang['title_addnews'];
                elseif ($do == 'register') $location = "%posin%" .$this->lang['title_register']; 
                elseif ($do == 'favorites') $location = "%posin%" .$this->lang['title_fav']; 
                elseif ($do == 'pm') $location = "%posin%" .$this->lang['title_pm']; 
                elseif ($do == 'feedback') $location = "%posin%" .$this->lang['title_feed'];
                elseif ($do == 'lastcomments') $location = "%posin%" .$this->lang['title_last'];
                elseif ($do == 'lostpassword') $location = "%posin%" .$this->lang['title_lost'];
                elseif ($do == 'search') $location = "%posin%" .$this->lang['title_search'];
                else 
                    $location = "%mainpage%";
            }
        
            define('SESSION_IDHASH', md5($_SERVER['HTTP_USER_AGENT'] . $this->_fetch_substr_ip(IPADRESS)));
            
            if (!empty($_COOKIE[COOKIE_PREFIX . "sessionhash"]))
            {
                $session_id = $this->db->safesql($_COOKIE[COOKIE_PREFIX . "sessionhash"]);
            }
            else if (!empty($_SESSION['forum_session_id']))
            {
                $session_id = $this->db->safesql($_SESSION['forum_session_id']);
            }
            else 
            {
                $session_id = '';
            }
            
            $this->_db_connect();
            
            $create_update_session = true;
            
            if ($session_id && $session = $this->db->super_query("
                        SELECT s.*, u.usergroupid, u.username
                        FROM " . VB_PREFIX . "session AS s
                        LEFT JOIN " . VB_PREFIX . "user AS u
                        ON u.userid = s.userid
                        WHERE sessionhash = '$session_id'
                            AND s.lastactivity > " . (TIMENOW - $this->vb_config['cookietimeout']) . "
                            AND idhash = '" . SESSION_IDHASH . "'"))
            {

                $user_name = '';
                if (!empty($member_id['user_id']))
                {
                    $user_name = $member_id['name'];
                    $this->_convert_charset($user_name);
                }
            
                if (
                    ($session['userid'] == 0 && empty($member_id['user_id']) && !isset($_POST['login'])) ||
                    ($session['userid'] != 0 && !empty($member_id['user_id']) && $user_name == $session['username'])
                    )
                {
                    if ($this->_fetch_substr_ip($session['host']) == $this->_fetch_substr_ip(IPADRESS))
                    {
                        $create_update_session = false;
                    }
                }
            }
            
            
            if ($create_update_session)
            {
                if (!empty($member_id['name']) && empty($this->user))
                {
                    $user_name = $member_id['name'];
                    $this->_convert_charset($user_name);
                    $this->user = $this->db->super_query("SELECT username, password, userid, sigpicrevision, usergroupid, salt FROM " . VB_PREFIX . "user WHERE username='" . $this->db->safesql($user_name) ."'");
                }

                if (!empty($_POST['login_password']))
                {
                    $pass_create = $_POST['login_password'];
                }
                else if (!empty($_SESSION['dle_password']))
                {
                    $pass_create = $_SESSION['dle_password'];
                }
                else if (!empty($_COOKIE['dle_password']))
                {
                    $pass_create = $_COOKIE['dle_password'];
                }
                else
                {
                    $pass_create = '';
                }
                
                if ($this->user)
                {
                    if (!$pass_create || $this->user['password'] != md5($pass_create . $this->user['salt']) )
                    {
                        $name = '';
                        $pass = false;
                        $user_id = 0;
                    }
                    else
                    {
                        $name = $this->user['username'];
                        $pass = $this->user['password'];
                        $user_id = $this->user['userid'];
                    }
                }
                else 
                {
                    $name = '';
                    $pass = false;
                    $user_id = 0;
                    
                    if (!empty($member_id['name']) && $this->config['vb_login_create_account'])
                    {
                        if ($pass_create)
                        {
                            $this->lock_connect = true;
                            
                            $username_safe = $this->db->safesql($member_id['name']);
                            
                            $this->CreateAccount($username_safe, $pass_create, $this->db->safesql($member_id['email']), TIMENOW);
                            
                            if ($member_id['foto'])
                            {
                                $GLOBALS['foto_name'] = $member_id['foto'];
                            }
                            
                            $this->UpdateRegister($username_safe, $this->db->safesql($member_id['icq']),
                                                                  $this->db->safesql($member_id['xfields']));
                            
                            $this->lock_connect = false;
                        }
                    }
                }
                $this->_create_session($session_id, $user_id, $name, $pass, $location, $diff_doamin);
            }
            else 
            {
                $this->user = $session;
                
                $this->db->query("UPDATE " . VB_PREFIX . "session SET lastactivity='" . TIMENOW . "', location='" . $this->db->safesql($location) . "' WHERE idhash='" . SESSION_IDHASH . "' AND sessionhash='{$session_id}' LIMIT 1");
                
                if ($diff_doamin)
                {
                    set_cookie(COOKIE_PREFIX . "sessionhash", $session_id, 365);
                }
                else 
                {
                    setcookie(COOKIE_PREFIX . "sessionhash", $session_id, TIMENOW + 60 * 60 * 24 * 365, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
                }
                
                $_SESSION['forum_session_id'] = $session_id;
            }
            
            $this->_db_disconnect();
            
            if ($this->config['vb_profile'] && !empty($this->user['usergroupid']) && $member_id['user_id'])
            {
                $dle_group = (int)array_search($this->user['usergroupid'], $this->config['groups']);
                if ($dle_group && $dle_group != $member_id['user_group'])
                {
                    $this->db->query('UPDATE ' . USERPREFIX . "_users SET user_group=$dle_group WHERE user_id=" . $member_id['user_id']);

                    $GLOBALS['member_id']['user_group'] = $dle_group;
                }
            }
            
            if (!$diff_doamin)
            {
                setcookie(COOKIE_PREFIX . "lastactivity", TIMENOW, TIMENOW + 60 * 60 * 24 * 365, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
            }
        }
    }
    
    public function changeUserGroup($member_id)
    {
        if (!$this->config['vb_onoff'] || !$this->config['vb_profile'])
        {
            $vb_group = (int)$this->config['groups'][$member_id['user_group']];
            
            if ($vb_group && (empty($this->user['usergroupid']) || $this->user['usergroupid'] != $vb_group))
            {
                $this->_db_connect();
                
                $username = $member_id['name'];
                $this->_convert_charset($username);
                
                $this->db->query('UPDATE ' . VB_PREFIX . "user SET usergroupid=$vb_group WHERE username='"  . $this->db->safesql($username) . "'");
                
                $this->_db_disconnect();
            }
        }
    }
    
    public function Logout()
    {
        if ($this->config['vb_onoff'] && $this->config['vb_logout'])
        {
            $id = 0;
            if (!empty($_SESSION['dle_user_id']))
            {
                $id = $_SESSION['dle_user_id'];
            }
            else if (!empty($_COOKIE['dle_user_id']))
            {
                $id = $_COOKIE['dle_user_id'];
            }
            $id = (int)$id;
            
            if ($id)
            {
                $user_dle = $this->db->super_query("SELECT name FROM " . USERPREFIX . "_users WHERE user_id=" . $id);    
                
                if ($user_dle)
                {
                    $this->_db_connect();
                    
                    $user_name = $this->db->safesql($this->_convert_charset($user_dle['name']));
                    
                    if (empty($this->user['userid']))
                    {
                        $this->user = $this->db->super_query("SELECT userid FROM " . VB_PREFIX . "user WHERE username='$user_name'"); 
                    }
                    
                    if ($this->_is_diff_domain())
                    {
                        if (!empty($this->user['userid']))
                        {
                            set_cookie( "dle_user_id", "", 0 );
                            set_cookie( "dle_name", "", 0 );
                            set_cookie( "dle_password", "", 0 );
                            set_cookie( "dle_skin", "", 0 );
                            set_cookie( "dle_newpm", "", 0 );
                            set_cookie( "dle_hash", "", 0 );
                            set_cookie( session_name(), "", 0 );
                            @session_destroy();
                            @session_unset();
                            
                            $this->_vb_redirect("?dlelogout", $this->user['userid']);
                        }
                    }
                    else 
                    {
                    //  setcookie(COOKIE_PREFIX."lastactivity","", TIMENOW - 3600, "/", $this->vb_config['cookiedomain']);
                    //  setcookie(COOKIE_PREFIX."lastvisit","", TIMENOW - 3600, "/", $this->vb_config['cookiedomain']);
                        setcookie(COOKIE_PREFIX."loggedin", "", TIMENOW - 3600, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
                        setcookie(COOKIE_PREFIX."password", "", TIMENOW - 3600, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
                        setcookie(COOKIE_PREFIX."userid", "", TIMENOW - 3600, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
                    //  setcookie(COOKIE_PREFIX."sessionhash","", TIMENOW - 3600, "/", $this->vb_config['cookiedomain']);
                        setcookie(COOKIE_PREFIX."forum_view", "", TIMENOW - 3600, $this->vb_config['cookiepath'], $this->vb_config['cookiedomain']);
                        
                        if (!empty($this->user['userid']))
                        {
                            $this->db->query("UPDATE " . VB_PREFIX . "session SET userid='0' WHERE userid={$this->user['userid']}");
                            $this->db->query("UPDATE " . VB_PREFIX . "user SET lastactivity='" . TIMENOW . "', ipaddress='" . $this->db->safesql(IPADRESS) . "' WHERE userid={$this->user['userid']} LIMIT 1");
                        }
                    }
                    $this->_db_disconnect();
                }
            }
        }
    }
    
    /**
     * Creating acount in the forum
     * 
     * @param string $name Username 
     * @param string $hashpasswd User password in md5()
     * @param string $email User e-mail adress
     * @param integer $add_time Time add in UNIX time format
     * @param boolean $admin from user add
     */
    public function CreateAccount($name, $hashpasswd, $email, $add_time, $admin = false)
    {
        if ($this->config['vb_onoff'] && (($this->config['vb_reg'] && !$admin) || ($this->config['vb_admin'] && $admin)))
        {
            $this->_db_connect();
            
            $salt = $this->_user_salt();
            $hashpasswd = md5($hashpasswd . $salt);
            $add_time = (int)$add_time;
            $name = $this->_convert_charset($name);
            
            $this->_db_connect();
           
            if (!function_exists("dle_cache") || !($cache = dle_cache("user_title")))
            {
                $usertitle = $this->db->super_query("SELECT title FROM ". VB_PREFIX ."usertitle WHERE usertitleid = '1'");
                if (function_exists('create_cache'))
                {
                    create_cache("user_title", serialize($usertitle));
                }
            }
            else 
                $usertitle = unserialize($cache);
                
            $usertitle['title'] = $this->db->safesql($usertitle['title']);
            
            $usergroupid = 2;
            if (!empty($this->config['groups'][$GLOBALS['config']['reg_group']])) $usergroupid = $this->config['groups'][$GLOBALS['config']['reg_group']];
    
            $this->db->query ("INSERT INTO ". VB_PREFIX . "user (usergroupid, username, password, passworddate, email, showvbcode,showbirthday, usertitle, joindate, lastvisit, lastactivity, reputationlevelid, options, startofweek, ipaddress, salt, timezoneoffset) values ('$usergroupid','$name','$hashpasswd', CURDATE(),'$email','1', '0', '{$usertitle['title']}','$add_time', '$add_time', '$add_time','5','3159','-1','".$this->db->safesql(IPADRESS)."','".$this->db->safesql($salt)."', '{$this->vb_config['timeoffset']}')");
            
            $id = $this->db->insert_id();
            
            $this->db->query ("INSERT INTO ". VB_PREFIX . "usertextfield (userid) values ('".$id."')"); 
            $this->db->query ("INSERT INTO ". VB_PREFIX . "userfield (userid) values ('$id')");
            
            $this->_update_user_stats($name, $id);

            $this->_create_session(null, $id, $name, $hashpasswd, '', $this->_is_diff_domain());
            
            $this->_db_disconnect();
        }
    } 
    
    protected function _update_user_stats($new_username = '', $new_userid = 0)
    {
        if (!$new_userid)
        {
            $new_user = $this->db->super_query("SELECT userid, username FROM " . VB_PREFIX . "user ORDER BY userid DESC");
            $new_userid = $new_user['userid'];
            $new_username = $new_user['username'];
        }
        
        $vbmembers = $this->db->super_query("SELECT COUNT(*) AS users, MAX(userid) AS maxid, SUM(IF(lastvisit >= " . (TIMENOW - $this->vb_config['activememberdays'] * 86400) . ", 1, 0)) AS active FROM " . VB_PREFIX . "user");
        
        $vbvalues = array(
                        'numbermembers' => $vbmembers['users'],
                        'activemembers' => $vbmembers['active'],
                        'newusername'   => $new_username,
                        'newuserid'     => $new_userid,
                        );
                        
        $this->db->query("REPLACE INTO " . VB_PREFIX . "datastore VALUES ('userstats', '" . $this->db->safesql(serialize($vbvalues)) . "', " . 1 . ")");
    }
    
    public function UpdateRegister($user_name, $icq, $filecontents, $admin = false)
    {
        if ($this->config['vb_onoff'] && (($this->config['vb_reg'] && !$admin) || ($this->config['vb_admin'] && $admin)))
        {
            $user_avatar = $user_name;
            $this->_convert_charset($user_name);
            $this->_convert_charset($icq);
            
            $this->_db_connect();
            
            if (empty($this->user['userid']))
            {
                $this->user = $this->db->super_query("SELECT userid FROM " . VB_PREFIX . "user WHERE username='$user_name'");
            }
            
            if (!empty($this->user['userid']))
            {
                $fields = $this->_generate_fields($filecontents);
                
                $this->db->query("UPDATE " . VB_PREFIX . "user SET icq='$icq'{$fields['user_vb_field']} WHERE userid=" . $this->user['userid']);
                
                if ($fields['update_fields'])
                {
                    $this->db->query("UPDATE " . VB_PREFIX . "userfield SET " . implode(", ", $fields['update_fields']) . " WHERE userid=" . $this->user['userid']);
                }
            }
            
            $this->_update_vB_Avatar($user_avatar);
            
            $this->_db_disconnect();
        }
    }

    public function UpdateProfile($user, $email, $password, $icq, $filecontents = '', $admin = false)
    {
        if ($this->config['vb_onoff'] && (($this->config['vb_profile'] && !$admin) || ($this->config['vb_admin'] && $admin)))
        {
            if ($admin)
            {
                $user_dle = $this->db->super_query("SELECT * FROM " . USERPREFIX . "_users WHERE user_id=" . $user);
                
                $user = $this->db->safesql($user_dle['name']);
                
                $GLOBALS['info'] = $GLOBALS['editinfo'];
                $GLOBALS['land'] = $GLOBALS['editland'];
                $GLOBALS['fullname'] = $GLOBALS['editfullname'];
                
                $signature = $_POST['editsignature'];
                
                $usergroupid = (int)$this->config['groups'][$GLOBALS['editlevel']];
            }
            else 
            {
                $user = $this->db->safesql($user);
                
                if( $GLOBALS['user_group'][$GLOBALS['row']['user_group']]['allow_signature'] )
                {
                    $signature = $_POST['signature'];
                }
                else 
                {
                    $signature = '';
                }
            }
            
            $username = $user;
            $this->_convert_charset($user);
            $this->_convert_charset($password);
            $this->_convert_charset($icq);
            
            $this->_db_connect();
            
            if (empty($this->user['userid']))
            {
                $this->user = $this->db->super_query("SELECT userid, sigpicrevision, usergroupid FROM " . VB_PREFIX . "user WHERE username='$user'");
            }
    
            if (!empty($this->user['userid']))
            {
                $fields = $this->_generate_fields($filecontents);
                
                if (iconv_strlen($password, VB_CHARSET) > 0) 
                {
                    $salt       = $this->_user_salt();
                    $hashpasswd = md5(md5($password) . $salt);
                    
                    $this->db->query("UPDATE " . VB_PREFIX . "user SET icq='$icq', email='$email', password='$hashpasswd', salt='".$this->db->safesql($salt)."'{$fields['user_vb_field']} WHERE userid='{$this->user['userid']}'");
                }
                else
                {
                    $this->db->query("UPDATE " . VB_PREFIX . "user SET icq='$icq', email='$email'{$fields['user_vb_field']} WHERE userid='{$this->user['userid']}'");
                }
                
                if (!empty($usergroupid) && $usergroupid != $this->user['usergroupid'])
                {
                    $this->db->query("UPDATE " . VB_PREFIX . "user SET usergroupid='$usergroupid' WHERE userid='{$this->user['userid']}'");
                }
            
                if ($fields['update_fields'])
                {
                    $this->db->query("UPDATE " . VB_PREFIX . "userfield SET " . implode(", ", $fields['update_fields']) . " WHERE userid={$this->user['userid']}");
                }
                
                $signature = $this->_init_parse()->process($signature);
                
                $signature = $this->_parse_signature($signature);
                
                $signature = $this->db->safesql($this->_convert_charset($signature));
                
                $this->db->query('UPDATE ' . VB_PREFIX . "usertextfield SET signature='$signature' WHERE userid=" . $this->user['userid']);
                $this->db->query('DELETE FROM ' . VB_PREFIX . "sigparsed WHERE userid=" . $this->user['userid']);
            }
            
            $this->_update_vB_Avatar($username);
            
            $this->_db_disconnect();
        }
    }
    
    protected function _generate_fields($filecontents)
    {
        if ($filecontents)
        {
            $dle_fields = explode('||', $filecontents);
        }
        else 
        {
            $dle_fields = array();
        }
        
        $dle_fields[] = 'info|' . $GLOBALS['info'];
        $dle_fields[] = 'land|' . $GLOBALS['land'];
        $dle_fields[] = 'fullname|' . $GLOBALS['fullname'];
        
        $dle_values = array();
        
        foreach ($dle_fields as $field)
        {
            $part = explode("|", $field);
            $dle_values[$part[0]] = $part[1];
        }
        
        $fields_array = (array)file(ENGINE_DIR . "/data/xprofile.txt");
        $fields_array[] = 'info';
        $fields_array[] = 'land';
        $fields_array[] = 'fullname';
        
        $update_fields = array(); $user_vb_field = '';                
        foreach ($fields_array as $field)
        {
            $part = explode('|', $field);
            
            if (!empty($this->config['fields'][$part[0]]))
            {
                $vb_field_id = (int)$this->config['fields'][$part[0]];
                $value = empty($dle_values[$part[0]])?'':$this->_convert_charset($dle_values[$part[0]]);
                
                if ($vb_field_id > 0)
                {
                    $update_fields[] = 'field' . $vb_field_id . "='" . $value . "'";
                }
                else if ($vb_field_id < 0 && isset($this->user_vb_field[$vb_field_id]))
                {
                    $user_vb_field .= ", " . $this->user_vb_field[$vb_field_id] . "='" . $value . "'";
                }
            }
        }
        
        return array('user_vb_field' => $user_vb_field, 'update_fields' => $update_fields);
    }
    
    protected function _parse_signature($signature)
    {
        if (iconv_strpos($signature, "[img", 0, DLE_CHARSET) !== false)
        {
            preg_match('#\[img[^\]]*\](.+?)\[/img\]#si', $signature, $matche);
            
            if (!empty($matche[1]))
            {
                if (empty($this->user['sigpicrevision']))
                {
                    $sigpicrevision = $this->db->super_query("SELECT sigpicrevision FROM " . VB_PREFIX . "user WHERE userid=" . $this->user['userid']);
                    $this->user['sigpicrevision'] = $sigpicrevision['sigpicrevision'];
                }
                
                
                $img = end(explode("/", $matche[1]));
                
                if ($img == ("sigpic" . $this->user['userid'] . "_" . $this->user['sigpicrevision'] . ".gif"))
                {
                    $signature = preg_replace('#\[img(=\|(.+?))?\].+?\[/img\]#si', "[SIGPIC]\\2[/SIGPIC]", $signature);
                }
            }
        }
        
        $signature = preg_replace('#\[img(=\|.+?)?\](.+?)\[/img\]#si', "[img]\\2[/img]", $signature);
        $signature = html_entity_decode($signature);
        $signature = $this->_parseBB($signature, 0, 'signature');
        
        return $signature;
    }
    
    private function _update_vB_Avatar($username)
    {
        $sendinfo = array();
        
        if( $_POST['del_foto'] == "yes" )
        {
            $sendinfo['delete'] = 1;
        }
        else if (!empty($GLOBALS['foto_name']))
        {
            $sendinfo['avatarurl'] = $GLOBALS['config']['http_home_url'] . "uploads/fotos/" . $GLOBALS['foto_name'];
        }
        
        if ($sendinfo)
        {
            $sendinfo['username'] = $username;
            
            $data = base64_encode(serialize($sendinfo));
            
            $url = $this->vb_config['bburl'] . "/dle_avatar.php?data=" . urlencode($data) . "&hash=" . $this->_get_request_hash($data);
            
            $this->_sendRequest($url);
        }
    }
    
    protected function _sendRequest($url)
    {
        if (function_exists('curl_init'))
        {
            $curl = curl_init($url);
            
            curl_setopt($curl, CURLOPT_NOBODY, 0);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, 3000);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            
            curl_exec($curl);
            
            curl_close($curl);
        }
        else
        {
            $stream = fsockopen(str_replace('http://', '', $url), 80);
            stream_set_timeout($stream, 1);
            fclose($stream);
        }
    }
    
    public function UpdateDLEAvatar(array $info)
    {
        if (empty($info['username']))
        {
            die('Username is empty');
        }
        
        $username = $info['username'];
        
        if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
        {
            $username = iconv(VB_CHARSET, DLE_CHARSET, $username);
        }
        
        $username = $this->db->safesql($username);
        
        $dleuser = $this->db->super_query("SELECT * FROM " . USERPREFIX . "_users WHERE name='$username'");
        
        if ($dleuser)
        {
            if (!empty($info['delete']))
            {
                if ($dleuser['foto'])
                {
                    @unlink( ROOT_DIR . "/uploads/fotos/" . $dleuser['foto'] );
                    $this->db->query( "UPDATE " . USERPREFIX . "_users set foto='' WHERE user_id=" . $dleuser['user_id'] );
                }
            }
            else if (!empty($info['avatarurl']))
            {
                $type = end(explode(".", $info['avatarurl']));
                $filename = "foto_" . $dleuser['user_id'] . "." . $type;
                
                if (@copy($info['avatarurl'], ROOT_DIR . "/uploads/fotos/" . $filename))
                {
                    $this->db->query( "UPDATE " . USERPREFIX . "_users set foto='$filename' WHERE user_id=" . $dleuser['user_id'] );
                }
                else 
                {
                    //die('Copy error from ' .$info['avatarurl']);
                }
            }
        }
        else
        {
            //die('Dle user not found');
        }
    }
    
    public function LostPassword($user_name, $new_pass)
    {
        if ($this->config['vb_onoff'] && $this->config['vb_lost'])
        {
            $this->_convert_charset($user_name);
            $this->_convert_charset($new_pass);
            
            $salt = $this->_user_salt();
            $pass_hash = md5(md5($new_pass) . $salt);
            
            $this->_db_connect();
            
            $this->db->query("UPDATE " . VB_PREFIX . "user SET password='$pass_hash', salt='".$this->db->safesql($salt)."' WHERE username='$user_name'");
            
            $this->_db_disconnect();
        }
    }

    public function NewPM($from_name, $to_name, $subj, $text, $date, $outboxcopy)
    {
        if ($this->config['vb_onoff'] && $this->config['vb_pm'])
        {
            $text = $this->_init_parse()->process(trim($text));
            $text = $this->db->safesql($this->_parseBB($text));
            
            $this->_convert_charset($from_name);
            $this->_convert_charset($to_name);
            $this->_convert_charset($subj);
            $this->_convert_charset($text);
    
            $this->_db_connect();
            
            $from_name_sql = $this->db->safesql($from_name);
            $to_name_sql = $this->db->safesql($to_name);
            
            $users_resourse = $this->db->query("SELECT userid, username FROM " . VB_PREFIX . "user WHERE username IN ('$from_name_sql', '$to_name_sql')");
            
            $users = array();
            while ($u = $this->db->get_row($users_resourse))
            {
                $users[$u['username']] = $u['userid'];
            }
            
            if (!empty($users[$from_name]) && !empty($users[$to_name]))
            {
                $touserarray = $this->db->safesql(serialize(array('cc'=> array($users[$to_name]=>$to_name))));
                
                $this->db->query("INSERT INTO ". VB_PREFIX . "pmtext (fromuserid, fromusername, title, message, touserarray, dateline, showsignature) values ('{$users[$from_name]}', '$from_name_sql', '$subj', '$text', '$touserarray', '$date', 1)");
                $id_pm = $this->db->insert_id();
                
                if ($outboxcopy)
                {
                    $this->db->query("INSERT INTO ". VB_PREFIX . "pm (pmtextid, userid, folderid, messageread) VALUES 
                                                                     ('{$id_pm}', '{$users[$from_name]}', '-1', '1'), 
                                                                     ('{$id_pm}', '{$users[$to_name]}', '0', '0')");
                    $this->db->query("UPDATE " . VB_PREFIX . "user SET pmtotal=pmtotal+1 WHERE userid='{$users[$from_name]}'");
                }
                else
                {
                    $this->db->query("INSERT INTO ". VB_PREFIX . "pm (pmtextid, userid, folderid, messageread) VALUES ('{$id_pm}', '{$users[$to_name]}', '0', '0')");
                }
            
                $this->db->query("UPDATE " . VB_PREFIX . "user SET pmtotal=pmtotal+1, pmunread=pmunread+1 WHERE userid='{$users[$to_name]}'");
            }
            
            $this->_db_disconnect();
        }
    }

    public function ReadPM($user_name, array &$pm_info)
    {
        if ($this->config['vb_onoff'] && $this->config['vb_pm'])
        {
            if ($pm_info)
            {
                $user_name = $this->db->safesql($this->_convert_charset($user_name));
                $user_from = $this->db->safesql($this->_convert_charset($pm_info['user_from']));
                
                $this->_db_connect();
                
                if (empty($this->user['userid']))
                {
                    $this->user = $this->db->super_query("SELECT userid FROM " . VB_PREFIX . "user WHERE username='$user_name'");
                }
                
                $id = $this->db->super_query("SELECT pmtextid FROM " . VB_PREFIX . "pmtext WHERE fromusername='{$user_from}' AND dateline='{$pm_info['date']}'");
                
                if ($id && !empty($this->user['userid']))
                {
                    
                    $this->db->query("UPDATE " . VB_PREFIX . "pm SET messageread=1 WHERE pmtextid='{$id['pmtextid']}' AND folderid='0' AND userid={$this->user['userid']}");
                    
                    $receipt = $this->db->super_query("SELECT pmid FROM " . VB_PREFIX . "pmreceipt WHERE pmid='{$id['pmtextid']}' AND userid={$this->user['userid']}");
                    
                    if (!empty($receipt['pmid']))
                    {
                        $this->db->query("UPDATE " . VB_PREFIX . "pmreceipt SET readtime=" . TIMENOW . " WHERE pmid='{$receipt['pmid']}' AND userid={$this->user['userid']}");
                    }
                        
                    $this->db->query("UPDATE " . VB_PREFIX . "user SET pmunread=IF(pmunread <= 1, 0, pmunread-1) WHERE userid='{$this->user['userid']}'");
                }
                
                $this->_db_disconnect();
            }
        }
    }
    
    public function DeletePM($user_name, $pm_id)
    {
        if ($this->config['vb_onoff'] && $this->config['vb_pm'])
        {
            $pm_dle = $this->db->super_query("SELECT date, user_from, folder FROM " . USERPREFIX . "_pm WHERE id=" . $pm_id);
            
            $this->_db_connect();
                
            $user_name = $this->db->safesql($this->_convert_charset($user_name));
            
            if (empty($this->user['userid']))
            {
                $this->user = $this->db->super_query("SELECT userid FROM " . VB_PREFIX . "user WHERE username='$user_name'");
            }
            
            if (!empty($this->user['userid']))
            {
                $user_from = $this->db->safesql($this->_convert_charset($pm_dle['user_from']));
                
                $folder = ($pm_dle['folder'] == "inbox")?0:-1;
                
                $pm = $this->db->super_query("SELECT p.* FROM " . VB_PREFIX . "pmtext as pt
                            LEFT JOIN " . VB_PREFIX . "pm as p
                            ON p.pmtextid=pt.pmtextid
                            WHERE pt.dateline='{$pm_dle['date']}' AND pt.fromusername='{$user_from}' AND p.folderid=$folder AND p.userid=" . $this->user['userid']);
                
                if ($pm)
                {
                    $this->db->query("DELETE FROM " . VB_PREFIX . "pm WHERE pmid=" . $pm['pmid']);
                    
                    if ($pm['messageread'])
                    {
                        $this->db->query("UPDATE " . VB_PREFIX . "user SET pmtotal=IF(pmtotal=0, 0, pmtotal-1) WHERE userid=" . $this->user['userid']);
                    }
                    else 
                    {
                        $this->db->query("UPDATE " . VB_PREFIX . "user SET pmtotal=IF(pmtotal=0, 0, pmtotal-1), pmunread=IF(pmunread=0, 0, pmunread-1) WHERE userid=" . $this->user['userid']);
                    }
                }
            }
            
            $this->_db_disconnect();
        }
    }
    
    public function DeleteUser($user_name)
    {
        if ($this->config['vb_onoff'] && $this->config['vb_admin'])
        {
            $this->_db_connect();
            
            $user_name = $this->db->safesql($this->_convert_charset($user_name));
            
            $this->user = $this->db->super_query('SELECT userid, profilepicrevision, avatarrevision, sigpicrevision FROM ' . VB_PREFIX . "user WHERE username='$user_name'");
            
            if (!empty($this->user['userid']))
            {
                $this->db->query("DELETE FROM " . VB_PREFIX . "user WHERE userid=" . $this->user['userid']);
                $this->db->query("DELETE FROM " . VB_PREFIX . "userfield WHERE userid=" . $this->user['userid']);
                $this->db->query("DELETE FROM " . VB_PREFIX . "usertextfield WHERE userid=" . $this->user['userid']);
                
                $this->db->query("
                    UPDATE " . VB_PREFIX . "post SET
                        username = '" . $user_name . "',
                        userid = 0
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    UPDATE " . VB_PREFIX . "groupmessage SET
                        postusername = '" . $user_name . "',
                        postuserid = 0
                    WHERE postuserid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    UPDATE " . VB_PREFIX . "discussion SET
                        lastposter = '" . $user_name . "',
                        lastposterid = 0
                    WHERE lastposterid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    UPDATE " . VB_PREFIX . "visitormessage SET
                        postusername = '" . $user_name . "',
                        postuserid = 0
                    WHERE postuserid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "visitormessage
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    UPDATE " . VB_PREFIX . "usernote SET
                        username = '" . $user_name . "',
                        posterid = 0
                    WHERE posterid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "usernote
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "access
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "event
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "customavatar
                    WHERE userid = " . $this->user['userid'] . "
                ");
                @unlink($this->vb_config['avatarpath'] . '/avatar' . $this->user['userid'] . '_' . $this->user['avatarrevision'] . '.gif');
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "customprofilepic
                    WHERE userid = " . $this->user['userid'] . "
                ");
                @unlink($this->vb_config['profilepicpath'] . '/profilepic' . $this->user['userid'] . '_' . $this->user['profilepicrevision'] . '.gif');
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "sigpic
                    WHERE userid = " . $this->user['userid'] . "
                ");
                @unlink($this->vb_config['sigpicpath'] . '/sigpic' . $this->user['userid'] . '_' . $this->user['sigpicrevision'] . '.gif');
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "moderator
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "reputation
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "subscribeforum
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "subscribethread
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "subscribeevent
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "subscriptionlog
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "session
                    WHERE userid = " . $this->user['userid'] . "
                ");
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "userban
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "usergrouprequest
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "announcementread
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "infraction
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "groupread
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "discussionread
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "subscribediscussion
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "subscribegroup
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "profileblockprivacy
                    WHERE userid = " . $this->user['userid'] . "
                ");
        
                $pendingfriends = array();
                $currentfriends = array();
        
                $friendlist = $this->db->query("
                    SELECT relationid, friend
                    FROM " . VB_PREFIX . "userlist
                    WHERE userid = " . $this->user['userid'] . "
                        AND type = 'buddy'
                        AND friend IN('pending','yes')
                ");
        
                while ($friend = $this->db->get_row($friendlist))
                {
                    if ($friend['friend'] == 'yes')
                    {
                        $currentfriends[] = $friend['relationid'];
                    }
                    else
                    {
                        $pendingfriends[] = $friend['relationid'];
                    }
                }
        
                if (!empty($pendingfriends))
                {
                    $this->db->query("
                        UPDATE " . VB_PREFIX . "user
                        SET friendreqcount = IF(friendreqcount > 0, friendreqcount - 1, 0)
                        WHERE userid IN (" . implode(", ", $pendingfriends) . ")
                    ");
                }
        
                if (!empty($currentfriends))
                {
                    $this->db->query("
                        UPDATE " . VB_PREFIX . "user
                        SET friendcount = IF(friendcount > 0, friendcount - 1, 0)
                        WHERE userid IN (" . implode(", ", $currentfriends) . ")
                    ");
                }
        
                $this->db->query("
                    DELETE FROM " . VB_PREFIX . "userlist
                    WHERE userid = " . $this->user['userid'] . " OR relationid = " . $this->user['userid']
                );
        
                $this->db->query("
                    UPDATE " . VB_PREFIX . "socialgroup
                    SET transferowner = 0
                    WHERE transferowner = " . $this->user['userid']
                );
        
                $this->db->query("DELETE FROM " . VB_PREFIX . "album WHERE userid = " . $this->user['userid']);
        
        
                $this->db->query("
                    UPDATE " . VB_PREFIX . "picturecomment SET
                        postusername = '" . $user_name . "',
                        postuserid = 0
                    WHERE postuserid = " . $this->user['userid'] . "
                ");
                
                $this->_update_user_stats();
            }
            
            $this->_db_disconnect();
        }
    }
    
    public function ChangeUserName($id, $new_username)
    {
        if ($this->config['vb_onoff'] && $this->config['vb_admin'])
        {
            $username = '';
            if (empty($this->user['userid']))
            {
                $dle_user = $this->db->super_query("SELECT name FROM " . USERPREFIX . "_users WHERE user_id=" . $id);
                
                $this->_db_connect();
                
                $username = $this->db->safesql($this->_convert_charset($dle_user['name']));
                
                $this->user = $this->db->super_query("SELECT userid, username " . VB_PREFIX . "user WHERE username='$username'");
            }
            
            if (!empty($this->user['userid']));
            {
                $this->_db_connect();
                
                $new_username = $this->_convert_charset($new_username);
            
                $this->db->query("
                    UPDATE " . VB_PREFIX . "pmreceipt SET
                        tousername = '" . $new_username . "'
                    WHERE touserid = {$this->user['userid']}
                ");
    
                // pm text 'fromusername'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "pmtext SET
                        fromusername = '" . $new_username . "'
                    WHERE fromuserid = {$this->user['userid']}
                ");
    
                // these updates work only when the old username is known,
                // so don't bother forcing them to update if the names aren't different
                if ($username != $new_username)
                {
                    // pm text 'touserarray'
                    $this->db->query("
                        UPDATE " . VB_PREFIX . "pmtext SET
                            touserarray = REPLACE(touserarray,
                                'i:{$this->user['userid']};s:" . iconv_strlen(stripslashes($username), VB_CHARSET) . ":\"" . $username . "\";',
                                'i:{$this->user['userid']};s:" . iconv_strlen(stripslashes($new_username), VB_CHARSET) . ":\"" . $new_username . "\";'
                            )
                        WHERE touserarray LIKE '%i:{$this->user['userid']};s:" . iconv_strlen(stripslashes($username), VB_CHARSET) . ":\"" . $username . "\";%'
                    ");
    
                    // forum 'lastposter'
                    $this->db->query("
                        UPDATE " . VB_PREFIX . "forum SET
                            lastposter = '" . $new_username . "'
                        WHERE lastposter = '" . $username . "'
                    ");
    
                    // thread 'lastposter'
                    $this->db->query("
                        UPDATE " . VB_PREFIX . "thread SET
                            lastposter = '" . $new_username . "'
                        WHERE lastposter = '" . $username . "'
                    ");
                }
    
                // thread 'postusername'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "thread SET
                        postusername = '" . $new_username . "'
                    WHERE postuserid = {$this->user['userid']}
                ");
    
                // post 'username'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "post SET
                        username = '" . $new_username . "'
                    WHERE userid = {$this->user['userid']}
                ");
    
                // usernote 'username'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "usernote
                    SET username = '" . $new_username . "'
                    WHERE posterid = {$this->user['userid']}
                ");
    
                // deletionlog 'username'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "deletionlog
                    SET username = '" . $new_username . "'
                    WHERE userid = {$this->user['userid']}
                ");
    
                // editlog 'username'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "editlog
                    SET username = '" . $new_username . "'
                    WHERE userid = {$this->user['userid']}
                ");
    
                // postedithistory 'username'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "postedithistory
                    SET username = '" . $new_username . "'
                    WHERE userid = {$this->user['userid']}
                ");
    
                // socialgroup 'lastposter'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "socialgroup
                    SET lastposter = '" . $new_username . "'
                    WHERE lastposterid = {$this->user['userid']}
                ");
    
                // discussion 'lastposter'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "discussion
                    SET lastposter = '" . $new_username . "'
                    WHERE lastposterid = {$this->user['userid']}
                ");
    
                // groupmessage 'postusername'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "groupmessage
                    SET postusername = '" . $new_username . "'
                    WHERE postuserid = {$this->user['userid']}
                ");
    
                // visitormessage 'postusername'
                $this->db->query("
                    UPDATE " . VB_PREFIX . "visitormessage
                    SET postusername = '" . $new_username . "'
                    WHERE postuserid = {$this->user['userid']}
    
                ");
                
                $this->db->query("
                    UPDATE " . VB_PREFIX . "user
                    SET username = '" . $new_username . "'
                    WHERE userid = {$this->user['userid']}
    
                ");
            }
            
            $this->_update_user_stats();
            
            $this->_db_disconnect();
        }
    } 
    
    private function _lastPosts_block(dle_template &$tpl)
    {
        $cache = '';
            
        if ($this->config['vb_block_new_cache_time'] && function_exists('dle_cache'))
        {
            $block_time = get_vars('vb_block_new_time');
            
            if ((time() - $block_time) < $this->config['vb_block_new_cache_time'])
            {
                $cache = dle_cache('vb_block_new_cache');
            }
        }
            
        if (!$cache)
        {
            $this->_db_connect();
            
            if ($this->config['vb_block_new_badf']) 
            {
                $forum_bad = explode(",", $this->config['vb_block_new_badf']);
            
                $forum_id = " WHERE th.forumid NOT IN('". implode("','", $forum_bad) ."') AND th.open=1 AND th.visible=1";
            } 
            else if ($this->config['vb_block_new_goodf']) 
            {
                $forum_good = explode(",", $this->config['vb_block_new_goodf']);
                $forum_id = " WHERE th.forumid IN('". implode("','", $forum_good) ."') AND th.open=1 AND th.visible=1";
            }
            else 
                $forum_id = " WHERE th.open=1 AND th.visible=1";
                
            $result = $this->db->query("SELECT th.replycount, th.views, th.threadid, th.title, th.lastpost, th.lastposter, p.userid, f.title AS forum_title, f.forumid FROM " . VB_PREFIX . "thread AS th
                                LEFT JOIN " . VB_PREFIX . "forum AS f
                                ON f.forumid=th.forumid
                                LEFT OUTER JOIN " . VB_PREFIX . "post AS p
                                ON p.postid=th.lastpostid
                                ". $forum_id ." ORDER BY lastpost DESC LIMIT 0 ,".$this->config['vb_block_new_count_post']);
        
            $tpl->load_template('block_forum_posts.tpl');
            preg_match("'\[row\](.*?)\[/row\]'si", $tpl->copy_template, $matches);
            while ($row = $this->db->get_row($result))
            {
                if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
                {
                    $row["title"] = iconv(VB_CHARSET, DLE_CHARSET, $row["title"]);
                    $row['forum_title']  = iconv(VB_CHARSET, DLE_CHARSET, $row['forum_title']);
                    $row['lastposter']  = iconv(VB_CHARSET, DLE_CHARSET, $row['lastposter']);
                }
                
                $short_name = $row["title"];

                if ($this->config['vb_block_new_leght_name'] && iconv_strlen($row["title"], DLE_CHARSET) > $this->config['vb_block_new_leght_name'])
                {
                    $short_name = iconv_substr($row["title"], 0, $this->config['vb_block_new_leght_name'], DLE_CHARSET)." ...";
                }
                        
                $row["lastpost"] = $row["lastpost"] + $GLOBALS['config']['date_adjust']*60;
                switch (date("d.m.Y", $row["lastpost"]))
                {
                    case date("d.m.Y"):
                        $date=date($this->lang['today_in'] . "H:i",$row["lastpost"]);  
                        break;
                        
                    case date("d.m.Y", time()-86400):
                        $date=date($this->lang['yestoday_in'] . "H:i",$row["lastpost"]);   
                        break;
                        
                    default:
                        $date=date("d.m.Y H:i", $row["lastpost"]);
                        break;
                }
                
                $replace = array('{user}'=> $row['lastposter'],
                                '{user_url}' => $this->vb_config['bburl'] . "/member.php?u=" . $row['userid'],
                                '{forum_url}' => $this->vb_config['bburl'] . "/forumdisplay.php?f=" . $row['forumid'],
                                '{forum}' => $row['forum_title'],
                                '{reply_count}'=> $row["replycount"],
                                '{view_count}'=> $row["views"],
                                '{full_name}'=> $row["title"],
                                '{post_url}'=> $this->vb_config['bburl']."/showthread.php?goto=newpost&amp;t=".$row["threadid"],
                                '{shot_name_post}'=> $short_name,
                                '{date}'=> $date,
                                );
                $tpl->copy_template = strtr($tpl->copy_template, $replace);
                $tpl->copy_template = preg_replace("'\[row\](.*?)\[/row\]'si", "\\1\n".$matches[0], $tpl->copy_template);
            }
            $tpl->set_block("'\[row\](.*?)\[/row\]'si", "");
            $tpl->compile('block_forum_posts');
            $tpl->clear();
            $this->db->free();
            
            if ((int)$this->config['vb_block_new_cache_time'] && function_exists('create_cache'))
            {
                create_cache("vb_block_new_cache", $tpl->result['block_forum_posts']);
                set_vars('vb_block_new_time', TIMENOW);
            }
        }
        else 
        {
            $tpl->result['block_forum_posts'] = $cache;
        }
    }

    private function _birthdayUser_block(dle_template &$tpl)
    {
        $cache = '';
        
        if ($this->config['vb_block_birthday_cache_time'] && function_exists('dle_cache'))
        {
            $block_time = get_vars('vb_block_birthday_cache_time');
            
            if ((time() - $block_time) < $this->config['vb_block_birthday_cache_time'])
            {
                $cache = dle_cache('vb_block_birthday_cache');
            }
        }
        
        if(!$cache)
        {
            $this->_db_connect();
            
            $result = $this->db->query("SELECT userid, username, showbirthday, birthday_search FROM " . VB_PREFIX . "user WHERE showbirthday!=0 AND DATE_FORMAT(birthday_search, '%m-%d') = '".date("m-d")."' ORDER BY birthday_search DESC LIMIT 0 ," . $this->config['count_birthday']);
            
            $block = ""; $i = 0;
            while ($row = $this->db->get_row($result))
            {
                if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
                {
                    $row['username']  = iconv(VB_CHARSET, DLE_CHARSET, $row['username']);
                }
//                quoted_printable_decode($name);

                if ($i != 0 && $block != "")
                {
                    $block .= $this->config['vb_block_birthday_spacer'];
                }
                
                $date = explode("-", $row['birthday_search']);
                
                if ($date['0'] == "0000")
                {
                    $age = "n/a"; 
                }
                else 
                {
                    $age = date("Y") - $date['0'];
                }
        
                $user = preg_replace('/{name}/',$row['username'], $this->config['birthday_block']);
                $user = preg_replace('/{age}/',$age, $user);
                $user = preg_replace('/{user_url}/',$this->vb_config['bburl']."/member.php?u=" . $row["userid"], $user);
        
                $block .= $user;
                $i++;
            }
            
            if (!$block)
            {
                $block = $this->config['no_user_birthday'];
            }
            $this->db->free();
            
            if ($this->config['birthday_cache_time'] && function_exists('create_cache'))
            {
                create_cache("vb_block_birthday_cache", $block);
                set_vars('vb_block_birthday_cache_time', time());
            }
            
            $tpl->result['vb_block_birthday'] = $block;
        }
        else 
        {
            $tpl->result['vb_block_birthday'] = $cache;
        }
    }
    
    private function _browser($useragent) 
    {
        $browser_type = "Unknown";
        $browser_version = "";
        if (preg_match('#MSIE ([0-9].[0-9]{1,2})#', $useragent, $version)) {
            $browser_type = "Internet Explorer";
            $browser_version = $version[1];
        } elseif (preg_match('#Opera ([0-9].[0-9]{1,2})#si', $useragent, $version)) {
            $browser_type = "Opera";
            $browser_version = $version[1];
            if ($browser_version == '9.80') $browser_version = substr($useragent, -5);
        } elseif (preg_match('/Opera/i', $useragent)) {
            $browser_type = "Opera";
            $val = stristr($useragent, "opera");
            if (preg_match("#/#", $val)){
                $val = explode("/",$val);
                $browser_type = $val[0];
                $val = explode(" ",$val[1]);
                $browser_version  = $val[0];
            } else {
                $val = explode(" ",stristr($val,"opera"));
                $browser_type = $val[0];
                $browser_version  = $val[1];
            }
        } elseif (preg_match('/Chrome\/([0-9\.]+)/i', $useragent, $version)) {
            $browser_type = "Chrome";
            $browser_version = $version[1];
        } elseif (preg_match('/Firefox\/(.*)/i', $useragent, $version)) {
            $browser_type = "Firefox";
            $browser_version = $version[1];
        } elseif (preg_match('/SeaMonkey\/(.*)/i', $useragent, $version)) {
            $browser_type = "SeaMonkey";
            $browser_version = $version[1];
        } elseif (preg_match('/Minimo\/(.*)/i', $useragent, $version)) {
            $browser_type = "Minimo";
            $browser_version = $version[1];
        } elseif (preg_match('/K-Meleon\/(.*)/i', $useragent, $version)) {
            $browser_type = "K-Meleon";
            $browser_version = $version[1];
        } elseif (preg_match('/Epiphany\/(.*)/i', $useragent, $version)) {
            $browser_type = "Epiphany";
            $browser_version = $version[1];
        } elseif (preg_match('/Flock\/(.*)/i', $useragent, $version)) {
            $browser_type = "Flock";
            $browser_version = $version[1];
        } elseif (preg_match('/Camino\/(.*)/i', $useragent, $version)) {
            $browser_type = "Camino";
            $browser_version = $version[1];
        } elseif (preg_match('/Firebird\/(.*)/i', $useragent, $version)) {
            $browser_type = "Firebird";
            $browser_version = $version[1];
        } elseif (preg_match('/Safari/i', $useragent)) {
            $browser_type = "Safari";
            $browser_version = "";
        } elseif (preg_match('/avantbrowser/i', $useragent)) {
            $browser_type = "Avant Browser";
            $browser_version = "";
        } elseif (preg_match('/America Online Browser [^0-9,.,a-z,A-Z]/i', $useragent)) {
            $browser_type = "Avant Browser";
            $browser_version = "";
        } elseif (preg_match('/libwww/i', $useragent)) {
            if (preg_match('/amaya/i', $useragent)) {
                $browser_type = "Amaya";
                $val = explode("/",stristr($useragent,"amaya"));
                $val = explode(" ", $val[1]);
                $browser_version = $val[0];
            } else {
                $browser_type = "Lynx";
                $val = explode("/",$useragent);
                $browser_version = $val[1];
            }
        } elseif (preg_match('#Mozilla/([0-9]\.[0-9]{1,2})#'. $useragent, $version)) {
            $browser_type = "Netscape";
            $browser_version = $version[1];
        }
    
        return $browser_type." ".$browser_version;
    }
    
    private function _robots($useragent)
    {
        $r_or=false;
        $remap_agents = array (
            'antabot'           =>  'antabot (private)',
            'aport'             =>  'Aport',
            'Ask Jeeves'        =>  'Ask Jeeves',
            'Asterias'          =>  'Singingfish Spider',
            'Baiduspider'       =>  'Baidu Spider',
            'Feedfetcher-Google'=>  'Feedfetcher-Google',
            'GameSpyHTTP'       =>  'GameSpy HTTP',
            'GigaBlast'         =>  'GigaBlast',
            'Gigabot'           =>  'Gigabot',
            'Accoona'           =>  'Google.com',
            'Googlebot-Image'   =>  'Googlebot-Image',
            'Googlebot'         =>  'Googlebot',
            'grub-client'       =>  'Grub',
            'gsa-crawler'       =>  'Google Search Appliance',
            'Slurp'             =>  'Inktomi Spider',
            'slurp@inktomi'     =>  'Hot Bot',
    
            'lycos'             =>  'Lycos.com',
            'whatuseek'         =>  'What You Seek',
            'ia_archiver'       =>  'Alexa',
            'is_archiver'       =>  'Archive.org',
            'archive_org'       =>  'Archive.org',
    
            'YandexBlog'        =>  'YandexBlog',
            'YandexSomething'   =>  'YandexSomething',
            'Yandex'            =>  'Yandex',
            'StackRambler'      =>  'Rambler',
    
            'WebAlta Crawler'   =>  'WebAlta Crawler',
    
            'Yahoo'             =>  'Yahoo',
            'zyborg@looksmart'  =>  'WiseNut',
            'WebCrawler'        =>  'Fast',
            'Openbot'           =>  'Openfind',
            'TurtleScanner'     =>  'Turtle',
            'libwww'            =>  'Punto',
    
            'msnbot'            =>  'MSN',
            'MnoGoSearch'       =>  'mnoGoSearch',
            'booch'             =>  'booch_Bot',
            'WebZIP'            =>  'WebZIP',
            'GetSmart'          =>  'GetSmart',
            'NaverBot'          =>  'NaverBot',
            'Vampire'           =>  'Net_Vampire',
            'ZipppBot'          =>  'ZipppBot',
    
            'W3C_Validator'     =>  'W3C Validator',
            'W3C_CSS_Validator' =>  'W3C CSS Validator',
        );
    
        $remap_agents=array_change_key_case($remap_agents, CASE_LOWER);
    
        $pmatch_agents="";
        foreach ($remap_agents as $k => $v) {
            $pmatch_agents.=$k."|";
        }
        $pmatch_agents=substr_replace($pmatch_agents, '', iconv_strlen($pmatch_agents, VB_CHARSET)-1, 1);
    
        if (preg_match( '/('.$pmatch_agents.')/i', $useragent, $match ))
    
        if (count($match)) {
            $r_or = @$remap_agents[strtolower($match[1])];
        }
        
        return $r_or;
    }
    
    private function _os($useragent)
    {
        $os = 'Unknown';
        if(iconv_strpos($useragent, "Win", 0, DLE_CHARSET) !== false) {
            if(iconv_strpos($useragent, "NT 7", 0, DLE_CHARSET)   !== false) $os = 'Windows Seven';
            if(iconv_strpos($useragent, "NT 6.1", 0, DLE_CHARSET)   !== false) $os = 'Windows Seven';
            if(iconv_strpos($useragent, "NT 6.0", 0, DLE_CHARSET) !== false) $os = 'Windows Vista';
            if(iconv_strpos($useragent, "NT 5.2", 0, DLE_CHARSET) !== false) $os = 'Windows Server 2003 или XPx64';
            if(iconv_strpos($useragent, "NT 5.1", 0, DLE_CHARSET) !== false || strpos($useragent, "Win32") !== false || strpos($useragent, "XP")) $os = 'Windows XP';
            if(iconv_strpos($useragent, "NT 5.0", 0, DLE_CHARSET) !== false) $os = 'Windows 2000';
            if(iconv_strpos($useragent, "NT 4.0", 0, DLE_CHARSET) !== false || strpos($useragent, "3.5") !== false) $os = 'Windows NT';
            if(iconv_strpos($useragent, "Me", 0, DLE_CHARSET) !== false) $os = 'Windows Me';
            if(iconv_strpos($useragent, "98", 0, DLE_CHARSET) !== false) $os = 'Windows 98';
            if(iconv_strpos($useragent, "95", 0, DLE_CHARSET) !== false) $os = 'Windows 95';
        }
    
        if(iconv_strpos($useragent, "Linux", 0, DLE_CHARSET)    !== false
        || iconv_strpos($useragent, "Lynx", 0, DLE_CHARSET)     !== false
        || iconv_strpos($useragent, "Unix", 0, DLE_CHARSET)     !== false) $os = 'Linux';
        if(iconv_strpos($useragent, "Macintosh", 0, DLE_CHARSET)!== false
        || iconv_strpos($useragent, "PowerPC", 0, DLE_CHARSET)) $os = 'Macintosh';
        if(iconv_strpos($useragent, "OS/2", 0, DLE_CHARSET)!== false) $os = 'OS/2';
        if(iconv_strpos($useragent, "BeOS", 0, DLE_CHARSET)!== false) $os = 'BeOS';
    
        return $os;
    }
    
    private function _changeend($value,$v1,$v2,$v3)
    {
        $endingret="";
        if (iconv_substr($value,-1, 0, DLE_CHARSET)==1) $endingret = $v1;
        if (iconv_substr($value,-1, 0, DLE_CHARSET)==2) $endingret = $v2;
        if (iconv_substr($value,-1, 0, DLE_CHARSET)==3) $endingret = $v2;
        if (iconv_substr($value,-1, 0, DLE_CHARSET)==4) $endingret = $v2;
        if (iconv_substr($value,-2, 0, DLE_CHARSET)==11) $endingret = $v3;
        if (iconv_substr($value,-2, 0, DLE_CHARSET)==12) $endingret = $v3;
        if (iconv_substr($value,-2, 0, DLE_CHARSET)==13) $endingret = $v3;
        if (iconv_substr($value,-2, 0, DLE_CHARSET)==14) $endingret = $v3;
        if (empty($endingret)) $endingret = $v3;
        return $endingret;
    }
    
    private function _timeagos($timestamp)
    {
        $current_time = time();
        $difference = $current_time - $timestamp;
    
        $lengths = array(1, 60, 3600, 86400, 604800, 2630880, 31570560, 315705600);
    
        for ($val = sizeof($lengths) - 1; ($val >= 0) && (($number = $difference / $lengths[$val]) <= 1); $val--);
    
        if ($val < 0) $val = 0;
        $new_time = $current_time - ($difference % $lengths[$val]);
        $number = floor($number);
    
        switch ($val) {
            case 0: $stamp = $this->_changeend($number,$this->lang['stamp01'],$this->lang['stamp02'],$this->lang['stamp03']); break;
            case 1: $stamp = $this->_changeend($number,$this->lang['stamp11'],$this->lang['stamp12'],$this->lang['stamp13']); break;
            case 2: $stamp = $this->_changeend($number,$this->lang['stamp21'],$this->lang['stamp22'],$this->lang['stamp23']); break;
            case 3: $stamp = $this->_changeend($number,$this->lang['stamp31'],$this->lang['stamp32'],$this->lang['stamp33']); break;
            case 4: $stamp = $this->_changeend($number,$this->lang['stamp41'],$this->lang['stamp42'],$this->lang['stamp43']); break;
            case 5: $stamp = $this->_changeend($number,$this->lang['stamp51'],$this->lang['stamp52'],$this->lang['stamp53']); break;
            case 6: $stamp = $this->_changeend($number,$this->lang['stamp61'],$this->lang['stamp62'],$this->lang['stamp63']); break;
            case 5: $stamp = $this->_changeend($number,$this->lang['stamp71'],$this->lang['stamp72'],$this->lang['stamp73']); break;
        }
        $text = sprintf("%d %s ", $number, $stamp);
        if (($val >= 1) && (($current_time - $new_time) > 0)){
            $text .= $this->_timeagos($new_time);
        }
        return $text;
    }
    
    private function _online_block(dle_template &$tpl)
    {
        global $PHP_SELF;

        $cache = '';
        
        if ($this->config['vb_block_online_cache_time'] && function_exists('dle_cache'))
        {
            $block_time = get_vars('vb_block_online_cache_time');
            
            if ((time() - $block_time) < $this->config['vb_block_online_cache_time'])
            {
                $cache = dle_cache('vb_block_online_cache');
            }
        } 

        if (!$cache)
        {
            $this->_db_connect();

            if (!$this->vb_config['refresh'])
            {
                $this->vb_config['refresh'] = 15;
            }
        
            $this->db->query("SELECT s.userid, s.host, s.lastactivity, s.location, s.useragent, u.username FROM " . VB_PREFIX . "session AS s
                        LEFT OUTER JOIN " . VB_PREFIX . "user AS u
                        ON u.userid=s.userid
                        WHERE s.lastactivity>".(time() - $this->vb_config['refresh'] *60));
            
            $users = $robots = array(); $guests = $count_user = $count_robots = 0;
            while ($user = $this->db->get_row())
            {
                
                if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
                {
                    $user['useragent'] = iconv(VB_CHARSET, DLE_CHARSET, $user['useragent']);
                    $user['location']  = iconv(VB_CHARSET, DLE_CHARSET, $user['location']);
                    $user['username']  = iconv(VB_CHARSET, DLE_CHARSET, $user['username']);
                }
                
                if($user['userid']==0) 
                {
                    $current_robot = $this->_robots($user['useragent']);
                    if ($current_robot != "")
                    {
                        $robots[$current_robot]['name']=$current_robot;
                        $robots[$current_robot]['lastactivity']=$user['lastactivity'];
                        $robots[$current_robot]['host']=$user['host'];
                        $robots[$current_robot]['location']=$user['location'];
                    }
                    else
                    {
                        $guests++;
                    }
                }
                else
                {
                    $users[$user['userid']]['username']=$user['username'];
                    $users[$user['userid']]['lastactivity']=$user['lastactivity'];
                    $users[$user['userid']]['useragent']=$user['useragent'];
                    $users[$user['userid']]['host']=$user['host'];
                    $users[$user['userid']]['location']=$user['location'];
                }
            }
            
            $location_array = array("%addcomments%" => $this->lang['paddcomments'],
                                    "%readnews%"    => $this->lang['preadnews'],
                                    "%incategory%"  => $this->lang['pincategory'],
                                    "%posin%"       => $this->lang['pposin'],
                                    "%mainpage%"    => $this->lang['pmainpage'],
                                    "%view_pofile%" => $this->lang['view_profile'],
                                    "%newposts%"    => $this->lang['newposts'],
                                    "%view_stats%"  => $this->lang['view_stats']);
            if (count($users))
            {
                foreach ($users AS $id=>$value)
                {
                    if($GLOBALS['member_id']['user_group'] == 1)
                    {
                        $user_array[$value['username']]= $this->lang['os']. $this->_os($users[$id]['useragent']).'<br />' . $this->lang['browser']. $this->_browser($users[$id]['useragent']).'<br />' . '<b>IP:</b>&nbsp;'.$users[$id]['host'].'<br />';
                    }
        
                    $user_array[$value['username']] .= $this->lang['was']. $this->_timeagos($users[$id]['lastactivity']).$this->lang['back'].'<br />' . $this->lang['location'];
                    
                    if (preg_match("'%(.*?)%'si", $users[$id]['location']))
                    {
                        foreach ($location_array as $find => $replace)
                        {
                            $users[$id]['location'] = str_replace($find, $replace, $users[$id]['location']);
                        }
                    }
                    else 
                    {
                        $users[$id]['location'] = $this->lang['pforum'];
                    }
                    $user_array[$value['username']] .= $users[$id]['location']."<br/>";
                    
                    $descr = $user_array[$value['username']];
                    $user_array[$value['username']] = array();
                    $user_array[$value['username']]['descr'] = $descr;
                    $user_array[$value['username']]['id'] = $id;
                    
                    $count_user++;
                }
            }
        
            if (count($robots))
            {
                foreach ($robots AS $name=>$value)
                {
                    if($GLOBALS['member_id']['user_group']==1)
                    {
                        $robot_array[$name]= $this->lang['os'] . $this->_os($robots[$name]['useragent']).'<br />' . $this->lang['browser']. $this->_browser($robots[$name]['useragent']).'<br />' . '<b>IP:</b>&nbsp;'.$robots[$name]['host'].'<br />';
                    }
        
                    $robot_array[$name] .= $this->lang['was'].$this->_timeagos($robots[$name]['lastactivity']).$this->lang['back'].'<br />' . $this->lang['location'];
                    if (preg_match("'%(.*?)%'si", $robots[$name]['location']))
                    {
                        foreach ($location_array as $find => $replace)
                        {
                            $robots[$name]['location'] = str_replace($find, $replace, $robots[$name]['location']);
                        }
                    }
                    else 
                    {
                        $robots[$name]['location'] = $this->lang['pforum'];
                    }
                    $robot_array[$name] .= $robots[$name]['location']."<br/>";
                    $count_robots++;
                }
            }
            
            $users = ""; $i=0;
            if (count($user_array))
            {
                foreach ($user_array as $name => $a)
                {
                    $desc = $a['descr'];
                    $id = $a['id'];
                    
                    if ($i) $users .= $this->config['separator'];
                    $desc = htmlspecialchars($desc, ENT_QUOTES);
                    
                    if (!$this->config['vb_block_online_user_link_forum'])
                    {
                        $user_url = ($GLOBALS['config']['allow_alt_url'] == "yes")?$GLOBALS['config']['http_home_url']."user/".urlencode($name)."/":$PHP_SELF."?subaction=userinfo&amp;user=".urlencode($name);
                    }
                    else
                    {
                        $user_url = $this->vb_config['bburl'] . "/member.php?u=" . $id;
                    }
                    
                    $users .= "<a onmouseover=\"showhint('{$desc}', this, event, '180px');\" href=\"" . $user_url . "\" >".$name."</a>"; 
                    $i++;
                }
            }
            else 
            {
                $users = $this->lang['notusers'];  
            }
            $robots = ""; $i = 0;
            if (count($robot_array))
            {
                foreach ($robot_array as $name=>$desc)
                {
                    if ($i) $robots .= $this->config['separator'];
                    $desc = htmlspecialchars($desc, ENT_QUOTES);
                    $robots .= "<span onmouseover=\"showhint('{$desc}', this, event, '180px');\"  style=\"cursor:hand;\" >".$name."</span>"; 
                    $i++;
                }
            }
            else 
            {
                $robots = $this->lang['notbots'];
            }
            
            $tpl->load_template('block_online.tpl');
            $tpl->set('{users}',$count_user);
            $tpl->set('{guest}',$guests);
            $tpl->set('{robots}',$count_robots);
            $tpl->set('{all}',($count_user+$guests+$count_robots));
            $tpl->set('{userlist}',$users);
            $tpl->set('{botlist}',$robots);
            $tpl->compile('block_online');
            $tpl->clear();
            
            if ($this->config['block_online_cache_time'] && function_exists('create_cache'))
            {
                create_cache("vb_block_online_cache", $tpl->result['block_online']);
                set_vars('vb_block_online_cache_time', time());
            }
        }
        else
        {
            $tpl->result['block_online'] = $cache;
        }
    }
    
    private function _get_news_link($row, $category_id)
    {
        if( $GLOBALS['config']['allow_alt_url'] == "yes" ) {
			
			if( $GLOBALS['config']['seo_type'] == 1 OR $GLOBALS['config']['seo_type'] == 2  ) {
				
				if( $category_id and $GLOBALS['config']['seo_type'] == 2 ) {
					
					$full_link = $GLOBALS['config']['http_home_url'] . get_url( $row['category'] ) . "/" . $row['id'] . "-" . $row['alt_name'] . ".html";
				
				} else {
					
					$full_link = $GLOBALS['config']['http_home_url'] . $row['id'] . "-" . $row['alt_name'] . ".html";
				
				}
			
			} else {
				
				$full_link = $GLOBALS['config']['http_home_url'] . date( 'Y/m/d/', $row['date'] ) . $row['alt_name'] . ".html";
			}
		
		} else {
			
			$full_link = $GLOBALS['config']['http_home_url'] . "index.php?newsid=" . $row['id'];
		
		}
            
        return $full_link;
    }
    
    protected function build_thumb($gurl = "", $url = "", $align = "")
    {
        $this->_init_parse();
        
        $url = trim( $url );
        $gurl = trim( $gurl );
        $option = explode( "|", trim( $align ) );
        
        $align = $option[0];
        
        if( $align != "left" and $align != "right" ) $align = '';
        
        $url = $this->_parse->clear_url( urldecode( $url ) );
        $gurl = $this->_parse->clear_url( urldecode( $gurl ) );
        
        if( $gurl == "" or $url == "" ) return;
        
        if( $align != '' )
        {
            return "[$align][url=\"$gurl\"][img]{$url}[/img][/url][/$align]";
        }
        else
        {
            return "[url=\"$gurl\"][img]{$url}[/img][/url]";
        }
    
    }
    
    protected function _parseBB($text_forum, $id = 0, $type = 'pm')
    {
        $this->_init_parse();
        
        if ($type == 'post' && iconv_strpos($text_forum, "[attachment=", 0, DLE_CHARSET) !== false)
        {
            $this->_db_disconnect();
            
            $this->db->query( "SELECT id, name, onserver, dcount FROM " . PREFIX . "_files WHERE news_id=$id" );
            
            
            while ($file = $this->db->get_row())
            {
                preg_match("#\[attachment={$file['id']}:(.+?)\]#i", $text_forum, $matche );
                
                $size = formatsize( @filesize( ROOT_DIR . '/uploads/files/' . $file['onserver'] ) );
                $file['name'] = explode( "/", $file['name'] );
                $file['name'] = end( $file['name'] );
                
                if (!empty($matche))
                {
                    $file['name'] = $matche[1];
                }
    
                if( $GLOBALS['config']['files_count'] == 'yes' )
                {
                    $link = "[URL=\"{$GLOBALS['config']['http_home_url']}engine/download.php?id={$file['id']}\"]{$file['name']}[/URL] [{$size}] ({$this->lang['att_dcount']} {$file['dcount']})";
                }
                else
                {
                    $link = "[URL=\"{$GLOBALS['config']['http_home_url']}engine/download.php?id={$file['id']}\"]{$file['name']}[/URL] [{$size}]";
                }
                
                $text_forum = preg_replace( "#\[attachment={$file['id']}(:.+?)?\]#i", $link, $text_forum );
            }
            
            $this->_db_connect();
        }
        
        if ($type == 'post')
        {
            $text_forum = preg_replace('#\[[^U]+\]#i', '', $text_forum);
            $text_forum = $this->_parse->decodeBBCodes( $text_forum, false );
            $text_forum = preg_replace('#\[page=[0-9]+\]#si', "", $text_forum);
            $text_forum = str_replace('{PAGEBREAK}', '', $text_forum);
            $text_forum = preg_replace('#\[hide\](.*?)\[/hide\]#si', "\\1", $text_forum);
        }

        $text_forum = html_entity_decode($text_forum);
        $text_forum = preg_replace('#\[s\](.*?)\[/s\]#si', "\\1", $text_forum);
        //$text_forum = preg_replace('#\[spoiler(=.+?)?\](.*?)\[/spoiler\]#si', "\\2", $text_forum);
        $text_forum = preg_replace('#\[img=(.+?)\](.*?)\[/img\]#si', "[\\1][img]\\2[/img][/\\1]", $text_forum);
        /*$text_forum = preg_replace('#<.+?>#s', '', $text_forum);*/
        
        $smilies_arr = explode( ",", $GLOBALS['config']['smilies'] );

        foreach ( $smilies_arr as $smile )
        {
            $smile = trim( $smile );
            $find[] = "#:$smile:#si";
            $replace[] = "[img]" . $GLOBALS['config']['http_home_url'] . "engine/data/emoticons/{$smile}.gif[/img]";
        }
        
        $text_forum = preg_replace($find, $replace, $text_forum);
        
        $text_forum = str_replace('leech', 'url', $text_forum);
        
        if ($type == 'post')
        {
            $text_forum = preg_replace( "#\[video\s*=\s*(\S.+?)\s*\]#ie", "\$this->_parse->build_video('\\1')", $text_forum );
            $text_forum = preg_replace( "#\[audio\s*=\s*(\S.+?)\s*\]#ie", "\$this->_parse->build_audio('\\1')", $text_forum );
            $text_forum = preg_replace( "#\[flash=([^\]]+)\](.+?)\[/flash\]#ies", "\$this->_parse->build_flash('\\1', '\\2')", $text_forum );
            $text_forum = preg_replace( "#\[youtube=([^\]]+)\]#ies", "\$this->_parse->build_youtube('\\1')", $text_forum );
            $text_forum = preg_replace( "'\[thumb\]([^\[]*)([/\\\\])(.*?)\[/thumb\]'ie", "\$this->build_thumb('\$1\$2\$3', '\$1\$2thumbs\$2\$3')", $text_forum );
            $text_forum = preg_replace( "'\[thumb=(.*?)\]([^\[]*)([/\\\\])(.*?)\[/thumb\]'ie", "\$this->build_thumb('\$2\$3\$4', '\$2\$3thumbs\$3\$4', '\$1')", $text_forum );
            $text_forum = str_replace('D27CDB6E', 'F27CDB6E', $text_forum);
            
            preg_match_all('#<object .+?</object>#si', $text_forum, $mathes);
            
            if (!empty($mathes[0]))
            {
                foreach ($mathes[0] as $obj)
                {
                    $obj_new = str_replace("\n", '', $obj);
                    $obj_new = str_replace("\r", '', $obj_new);
                    $obj_new = str_replace("\t", '', $obj_new);
                    $obj_new = preg_replace('# {2,}#si', " ", $obj_new);
                    
                    $text_forum = str_replace($obj, $obj_new, $text_forum);
                    $text_forum = urldecode($text_forum);
                }
            }
        }
        
        $text_forum = preg_replace('#<!--.+?-->#s', '', $text_forum);
        $text_forum = str_replace('{THEME}', $GLOBALS['config']['http_home_url'] . 'templates/' . $GLOBALS['config']['skin'], $text_forum);
        
        return $text_forum;
    }
    
    private function GoForum()
    {
        $news_id = intval($_REQUEST['postid']);
        
        if (!$news_id)
        {
            die("Hacking attempt!");
        }
                
        $post = $this->db->super_query("SELECT * FROM " . PREFIX . "_post p
                                         INNER JOIN " . PREFIX . "_post_extras e
                                         ON e.news_id=p.id
                                         WHERE id='$news_id'");
                
        $category_id = intval ($post['category']);
        
        $this->_db_connect();
        
        switch ($this->config['link_title'])
        {
            case "old":
                $title_forum = preg_replace('/{Post_name}/', stripslashes($post['title']), $this->config['vb_link_name_post_on_forum']);
                break;
                
            case "title":
                $title_forum = stripslashes($post['title']);
                break;
    
            default:
                break;
        }
        
        if (empty($title_forum))
        {
            die('Unknow title post');
        }
        
        $title_forum = $this->db->safesql($this->_convert_charset($title_forum));
        
        $isset_post = $this->db->super_query("SELECT threadid FROM ". VB_PREFIX ."thread WHERE title='$title_forum' AND visible='1' AND open=1");
        
        if (!empty($isset_post['threadid']))
        {      
            header("Location:{$this->vb_config['bburl']}/showthread.php?goto=newpost&t={$isset_post['threadid']}");
            exit;
        }
       
        switch ($this->config['link_text'])
        {
            case "full":
                if (iconv_strlen($post['full_story'], DLE_CHARSET) > 10)
                {
                    $text_forum = $post['full_story'];
                }
                else 
                {
                    $text_forum = $post['short_story'];
                }
                $text_forum = preg_replace('#(\A[\s]*<br[^>]*>[\s]*|'                                     
                                             .'<br[^>]*>[\s]*\Z)#is', '', $text_forum);
                                             
                $text_forum = stripslashes($text_forum);
                                             
                $news_seiten = explode("{PAGEBREAK}", $text_forum);                             
                if (count($news_seiten) > 1)
                {   
                    $text_forum = $news_seiten[0];
                    $text_forum .= "<br/><a href=\"" . $this->_get_news_link($post, $category_id)."\" >" . $this->lang['view_full'] . "</a>";
                }
                
                $text_forum = $this->_parseBB($text_forum, $post['id'], 'post');
                
                if ($this->config['link_on_news'] && count($news_seiten) <= 1)
                {
                    $this->config['text_post_on_forum'] = preg_replace('/{post_name}/is',$post['title'], $this->config['text_post_on_forum']);
                    $this->config['text_post_on_forum'] = preg_replace('/{post_link}/is', $this->_get_news_link($post, $category_id), $this->config['text_post_on_forum']);
                    $text_forum .= "\n" . $this->config['text_post_on_forum'];
                }
                break;
                
            case "short":
                if (iconv_strlen($post['short_story'], DLE_CHARSET) < 10)
                {
                    $text_forum = $post['full_story'];
                }
                else 
                {
                    $text_forum = $post['short_story'];
                }
                    
                $text_forum = preg_replace('#(\A[\s]*<br[^>]*>[\s]*|'                                     
                                             .'<br[^>]*>[\s]*\Z)#is', '', $text_forum);
                                             
                $text_forum = stripslashes($text_forum);
                                             
                $news_seiten = explode("{PAGEBREAK}", $text_forum);                             
                if (count($news_seiten) > 1)
                {   
                    $text_forum = $news_seiten[0];
                    $text_forum .= "<br/><a href=\"".$this->_get_news_link($post, $category_id)."\" >" . $this->lang['view_full'] . "</a>";
                }

                $text_forum = $this->_parseBB($text_forum, $post['id'], 'post');
                
                if ($this->config['link_on_news'] && count($news_seiten) <= 1)
                {
                    $this->config['text_post_on_forum'] = preg_replace('/{post_name}/is',$post['title'], $this->config['text_post_on_forum']);
                    $this->config['text_post_on_forum'] = preg_replace('/{post_link}/is', $this->_get_news_link($post, $category_id), $this->config['text_post_on_forum']);
                    $text_forum .= "\n" . $this->config['text_post_on_forum'];
                }
                break;
                
            case "old":
                $text_forum = preg_replace('/{post_name}/i',$post['title'], $this->config['text_post_on_forum']);
                $text_forum = preg_replace('/{post_link}/i', $this->_get_news_link($post, $category_id), $text_forum);
                break;
                
            default:
                return false;
        }
        
        if (empty($text_forum))
        {
            die('Unknow post text');
        }

        $user_id = 0;
        switch ($this->config['link_user'])
        {
            case "author":
                $author = $this->db->safesql($this->_convert_charset($post['autor']));
                $user_info = $this->db->super_query("SELECT userid FROM ". VB_PREFIX ."user WHERE username='$author' LIMIT 1");
                $user_id = $user_info['userid'];
                $user = $this->_convert_charset($post['autor']);
                break;
                
            case "cur_user":
                if ($GLOBALS['member_id']['name'])
                {
                    $user_name  = $this->db->safesql($this->_convert_charset($GLOBALS['member_id']['name']));
                    $user_info = $this->db->super_query("SELECT userid FROM ". VB_PREFIX ."user WHERE username='$user_name' LIMIT 1");
                    $user = $this->_convert_charset($GLOBALS['member_id']['name']);
                    $user_id = $user_info['userid'];
                    break;
                }
                
            case "old":
                $user_id = intval($this->config['postuserid']);
                if ($user_id && $user = $this->db->super_query("SELECT username FROM " . VB_PREFIX . "user WHERE userid=" . $user_id))
                {
                    $user = $user['username'];
                }
                else 
                {
                    $user = $this->config['postusername'];
                    $user_id = 0;
                }
                break;
                
            default:
                break;
        }
        
        if (empty($user))
        {
            die('Unknow post user name');
        }
        
        $categories = explode(",", $post['category']);
        foreach ($categories as $category)
        {
            if (intval($this->config['vb_link_forumid'][$category]))
            {
                $forum_id = $this->config['vb_link_forumid'][$category];
                break;
            }
        }
        
        if (!$forum_id || !$this->db->super_query("SELECT forumid FROM " . VB_PREFIX . "forum WHERE parentid!=-1 AND forumid=" . $forum_id))
        {
            die('Unknow forumid for current category');
        }
        
        $user = $this->db->safesql($user);
        $text_forum = $this->db->safesql($this->_convert_charset($text_forum));
        
        if ($user_id)
        {
            $this->db->query('UPDATE ' . VB_PREFIX . "user SET posts=posts+1 WHERE userid=$user_id");
        }
    
        $this->db->query("INSERT INTO ". VB_PREFIX ."thread (title, lastpost, forumid, open, postusername, postuserid, lastposter, dateline, visible) VALUES ('$title_forum', '" . TIMENOW ."', '$forum_id', '1', '{$user}', '$user_id', '{$user}', '" . TIMENOW ."', '1')");
        $th_id = $this->db->insert_id();
        $this->db->query("INSERT INTO ". VB_PREFIX ."post (threadid, username, userid, title, dateline, pagetext, allowsmilie, ipaddress, visible, showsignature) VALUES ('$th_id', '{$user}', '$user_id', '$title_forum', '" . TIMENOW . "', '$text_forum', '1', '".$this->db->safesql(IPADRESS)."', '1', 1)");
        $post_id = $this->db->insert_id();
        $this->db->query("UPDATE " . VB_PREFIX . "thread SET firstpostid='$post_id', lastpostid='$post_id' WHERE threadid='$th_id'");
        $this->db->query("UPDATE " . VB_PREFIX . "forum SET lastpost='" . TIMENOW . "', lastposter='$user', lastpostid='$post_id', lastthread='$title_forum', lastthreadid='$th_id', threadcount=threadcount+1 WHERE forumid='$forum_id'");
        
        header("Location:{$this->vb_config['bburl']}/showthread.php?t=$th_id");
        exit();
    }

    public function LinkForum(array &$row, dle_template &$tpl)
    {
        $categories = explode(",", $row['category']);
        foreach ($categories as $category)
        {
            if (intval($this->config['vb_link_forumid'][$category]))
            {
                $cat_id = $category;
                break;
            }
        }
        
        switch ($this->config['link_title'])
        {
            case "old":
                $title_forum = preg_replace('/{Post_name}/', stripslashes($row['title']), $this->config['vb_link_name_post_on_forum']);
                $title_forum = $this->db->safesql($title_forum);
                break;
                
            case "title":
                $title_forum = $this->db->safesql(stripslashes($row['title']));
                break;
    
            default:
                break;
        }
            
        if (!$this->config['vb_goforum'] || !$this->config['vb_onoff'] || !$this->config['vb_link_forumid'][$cat_id] || (!$this->config['vb_link_show_no_register'] && !$GLOBALS['is_logged']))
        {
            $tpl->set('{link_on_forum}', "");
        }
        else 
        {
            $link_on_forum = $this->config['vb_link_link_on_forum'];
            
            if ($GLOBALS['newsid'] || $GLOBALS['subaction'] == 'showfull')
            {
                $this->config['vb_link_show_count'] = $this->config['vb_link_show_count_full'];
            }
            
            $count = 0;
            
            if ($this->config['vb_link_type'] == 2 && isset($row['vb_threadid']))
            {
                
                if ((int)$row['vb_threadid'])
                {
                    $link_on_forum = str_replace('{link_on_forum}', $this->vb_config['bburl'] . "/showthread.php?t={$row['vb_threadid']}&amp;goto=newpost", $link_on_forum);
                    
                    if ($this->config['vb_link_show_count'])
                    {
                        $this->_db_connect();
                        $thread = $this->db->super_query("SELECT threadid, replycount FROM ". VB_PREFIX ."thread WHERE threadid='{$row['vb_threadid']}' AND visible='1' AND open=1");
                        $this->_db_disconnect();
                        
                        if (!isset($thread['threadid']))
                        {
                            $link_on_forum = preg_replace("'\[count\](.*?)\[/count\]'si", "", $link_on_forum);
                        }
                        else 
                        {
                            $count = $thread['replycount'];
                            $link_on_forum = preg_replace("'\[count\](.*?)\[/count\]'si", "\\1", $link_on_forum);
                        }
                    }
                }
                else 
                {
                    $link_on_forum = str_replace('{link_on_forum}', $this->vb_config['bburl'] . "/newthread.php?do=newthread&amp;f={$this->config['vb_link_forumid'][$category]}&amp;news_id={$this->config['forumid'][$cat_id]}&amp;news_title=" . urlencode(stripslashes($row['title'])), $link_on_forum);
                }
            }
            else 
            {
                if ($this->config['vb_link_show_count'] && !empty($title_forum))
                {
                    $this->_convert_charset($title_forum);
                    
                    $this->_db_connect();
                    
                    $thread = $this->db->super_query("SELECT threadid, replycount FROM ". VB_PREFIX ."thread WHERE title='$title_forum' AND visible='1' AND open=1");
                    if (!isset($thread['threadid']))
                    {
                        $link_on_forum = preg_replace("'\[count\](.*?)\[/count\]'si", "", $link_on_forum);
                    }
                    else 
                    {
                        $count = $thread['replycount'];
                        $link_on_forum = preg_replace("'\[count\](.*?)\[/count\]'si", "\\1", $link_on_forum);
                    }
                    
                    $this->_db_disconnect();
                }
                else 
                {
                    $link_on_forum = preg_replace("'\[count\](.*?)\[/count\]'si", "", $link_on_forum);
                }
                    
                if ($GLOBALS['config']['allow_alt_url'] == "yes")
                {
                    $link_on_forum = str_replace('{link_on_forum}', $GLOBALS['config']['http_home_url']."goforum/post-".$row['id']."/", $link_on_forum);
                }
                else 
                {
                    $link_on_forum = str_replace('{link_on_forum}', $GLOBALS['PHP_SELF'] ."?do=goforum&postid=".$row['id'], $link_on_forum);
                }
            }
            $link_on_forum = str_replace("{count}", $count, $link_on_forum);
            $tpl->set('{link_on_forum}', $link_on_forum); 
        }
    }
    
    public function InitBLocks(dle_template &$tpl)
    {
        if ($this->config['vb_onoff'])
        {
            if ($this->config['vb_lastpost_onoff'])
            {
                $this->_lastPosts_block($tpl);
            }
            
            if ($this->config['vb_birthday_onoff'])
            {
                $this->_birthdayUser_block($tpl);
            }
            
            if ($this->config['vb_online_onoff'])
            {
                $this->_online_block($tpl);
            }
            
            $this->_db_disconnect();
        }
    }
    
    public function __destruct()
    {
        
    }
}

if (isset($_REQUEST['vbdebug']))
{
    class vBDebug extends vBIntegration
    {
        public function __construct(db &$db)
        {
            parent::__construct($db);
            
            if (!empty($_REQUEST['connect']))
            {
                $this->connect_method = $_REQUEST['connect'];
            }
        }
        
        protected function _db_connect($collate = '', $charset = '')
        {
            if (!$this->lock_connect && !$this->connected)
            {
                switch ($this->connect_method) 
                {
                    case 'connect':
                        $this->db->connect(VB_USER, VB_PASS, VB_BASE, VB_HOST);
                        break;
                        
                    case 'use':
                        $this->db->query("USE `" . VB_BASE . "`");
                        break;
                	
                    default:
                        break;
                }
                
                if ($collate)
                {
                    $this->db->query("SET NAMES '" . $collate ."'");
                }
                if ($charset)
                {
                    $this->db->query("SET CHARACTER SET '" . $charset . "'");
                }
                
                $this->connected = true;
            }
        }
        
        protected function _db_disconnect()
        {
            if (!$this->lock_connect && $this->connected)
            {
                switch ($this->connect_method) 
                {
                    case 'connect':
                        $this->db->close();
//                        $this->db->connect(DBUSER, DBPASS, DBNAME, DBHOST);
                        //$this->db->query("SET CHARACTER SET '" . COLLATE . "'");
                        break;
                        
                    case 'use':
                        $this->db->query("USE `" . DBNAME . "`");
                        
                    default:
                        $this->db->query("SET NAMES '" . COLLATE ."'");
                        //$this->db->query("SET CHARACTER SET '" . COLLATE . "'");
                        break;
                }
                
                $this->connected = false;
            }
        }
        
        protected function query()
        {
            $thread = $this->db->super_query("SELECT title FROM " . VB_PREFIX . "thread ORDER BY threadid DESC LIMIT 1");
            
            if (!$thread)
            {
                die('Topic not found');
            }
            
            return $thread['title'];
        }
        
        protected function _convert_charset($data, $to, $from = '')
        {
            global $config;
            
            if (!$from)
            {
                $from = $config['charset'];
            }
            
            if ($to != $from)
            {
                return iconv($from, $to, $data);
            }
            
            return $data;
        }
        
        public function Debug()
        {
            $COLLATES = array(
                                'utf8',
                                'cp1251',
                                'latin1'
                                );
                                
            $CHARACTERS = array(
                                '',
                                'utf8',
                                'cp1251'
                                );
                                
            $CHARSETS = array(
                                '',
                                'UTF-8',
                                'windows-1251',
                               );
                               
            foreach ($COLLATES AS $collate)
            {
                foreach ($CHARACTERS AS $character)
                {
                    foreach ($CHARSETS as $charset)
                    {
                        $this->_db_connect($collate, $character);
                        
                        if ($charset)
                        {
                            $text = $this->_convert_charset($this->query(), $charset);
                        }
                        else
                        {
                            $text = $this->query();
                            $charset = 'NONE';
                        }
                        
                        echo  "COLLATE: $collate; CHARACTER: " . ($character?$character:"NONE") . "; CHARSET: $charset : " . $text . "<br />";
                        $this->_db_disconnect();
                    }
                }
            }
        }
    }
    
    header('Content-type: text/html; charset=' . $config['charset']);
    $debug = new vBDebug($db);
    $debug->Debug();
    exit();
}

?>