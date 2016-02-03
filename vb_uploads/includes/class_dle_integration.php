<?php


define('LIC_DOMAIN', /*lic*/'.'/*/lic*/);

class DLEIntegration
{
    /**
     * 
     * @var vB_Registry
     */
    private $vbulletin;
    
    private $connect_method = 'none';
    
    /**
     * 
     * @var boolean
     */
    private $connected = false;
    
    private $lock_connect = false;
    
    /**
     * 
     * @var vB_Database
     */
    private $db = null;
    
    private $cookie_domain = '';
    
    static private $instance = null;
    
    private function __construct(vB_Registry $vbulletin)
    {
        if (!preg_match("#" . LIC_DOMAIN . "#i", $_SERVER['HTTP_HOST']) && 
            !preg_match('#localhost#', $_SERVER['HTTP_HOST']) &&
            strpos($_SERVER['HTTP_HOST'], $_SERVER['SERVER_ADDR']) === false
             )
        {
            @header("Content-type: text/html; charset=UTF-8");
            echo "Вы используете не лицензионную версию модуля DLE + vBulletin.<br/>";
            echo "За информацией обращайтесь на форум <a href='http://forum.kaliostro.net/'>http://forum.kaliostro.net/</a><br/>";
            echo "You are not using licensed version of the module DLE + vBulletin.<br/>";
            echo "For information, visit the forum <a href='http://forum.kaliostro.net/'>http://forum.kaliostro.net/</a>";
            exit(); 
        }
        
        if (file_exists(DIR . '/includes/config_dle.php'))
        {
            require_once DIR . '/includes/config_dle.php';
        }
        else
        {
            @header("Content-type: text/html; charset=UTF-8");
            echo("Не найден конфиг c параметрами БД DataLife Engine<br/>");
            echo("Can not find config c parameters database DataLife Engine");
            exit();
        }
        
        $this->vbulletin = $vbulletin;
        
        if (!defined('COLLATE'))
        {
            define('COLLATE', 'cp1251');
        }
        
        $this->cookie_domain = ($vbulletin->options['homeurl'])?$vbulletin->options['homeurl']:$vbulletin->options['bburl'];
        
        $this->cookie_domain = str_replace('http://', '', $this->cookie_domain);
        $this->cookie_domain = str_replace('www.', '', $this->cookie_domain);
        $this->cookie_domain = reset(explode('/', $this->cookie_domain));
        
        $domain_cookie = explode (".", $this->cookie_domain);
        $domain_cookie_count = count($domain_cookie);
        $domain_allow_count = -2;
        
        if ( $domain_cookie_count > 2 )
        {
            if (in_array($domain_cookie[$domain_cookie_count-2], array('com', 'net', 'org')))
            {
                $domain_allow_count = -3;
            }
            if ($domain_cookie[$domain_cookie_count-1] == 'ua')
            {
                $domain_allow_count = -3;
            }
            $domain_cookie = array_slice($domain_cookie, $domain_allow_count);
        }
        
        $this->cookie_domain = implode (".", $domain_cookie);
        
        if ($this->vbulletin->options['homeurl']{strlen($this->vbulletin->options['homeurl'])-1} != "/")
        {
            $this->vbulletin->options['homeurl'] .= "/";
        }
        
        define('DLE_CHARSET', $this->vbulletin->options['dle_charset']);
        
        $this->vbulletin->options['dle_fields'] = @unserialize($this->vbulletin->options['dle_fields']);
        $this->vbulletin->options['dle_groups'] = @unserialize($this->vbulletin->options['dle_groups']);
        
        if (DBHOST == $this->vbulletin->config['MasterServer']['servername'] &&
            DBUSER == $this->vbulletin->config['MasterServer']['username'] &&
            DBPASS == $this->vbulletin->config['MasterServer']['password']
            )
        {
            if (DBNAME == $this->vbulletin->config['Database']['dbname'])
            {
                $this->connect_method = "none";
            }
            else 
            {
                $this->connect_method = "use";
            }
            
            $this->db =& $this->vbulletin->db;
        }
        else 
        {
            $this->connect_method = "connect";
            
            $db_class = get_class($this->vbulletin->db);
            
            if (empty($this->vbulletin->db) || empty($db_class))
            {
                //var_dump($db_class, $this->vbulletin);
            //debug_print_backtrace();
            //exit();
            }
            
            $this->db = new $db_class($this->vbulletin);
        }
        
    }
    
    static public function getInstance(vB_Registry $vbulletin)
    {
        if (self::$instance === null)
        {
            self::$instance = new self($vbulletin);
        }
        
        return self::$instance;
    }
    
    private function &_db_connect()
    {
        if ($this->lock_connect || $this->connected)
        {
            return $this->db;
        }
        
        switch ($this->connect_method) 
        {
            case 'none':
                {
                    
                }
                break;
        	
            case 'use':
                {
                    $this->db->select_db(DBNAME);
                }
                break;
                
            default:
                {
                    $this->db->connect(
                                DBNAME,
                                DBHOST,
                                defined('DBPORT')?DBPORT:3306,
                                DBUSER,
                                DBPASS
                            );
                            
                     $this->db->force_sql_mode('');
                }
                break;
        }
        
        if (COLLATE != '')
        {
            $this->_set_charset(COLLATE, defined('CHARACTER')?CHARACTER:false);
        }
        
        $this->connected = true;
        
        return $this->db;
    }
    
    private function _db_disconnect()
    {
        if (!$this->lock_connect && $this->connected)
        {
            switch ($this->connect_method) 
            {
                case 'use':
                    {
                        $this->db->select_db($this->vbulletin->config['Database']['dbname']);
                    }
                
                case 'none':
                    {
                        if (COLLATE != '')
                        {
                            if (!empty($this->vbulletin->config['Mysqli']['charset']))
                            {
                                $this->_set_charset($this->vbulletin->config['Mysqli']['charset'], $this->vbulletin->config['Mysqli']['charset']);
                            }
                            else 
                            {
                                $this->_set_charset('');
                            }
                        }
                    }
                    break;
                    
                default:
                    {
                        $this->db->close();
                    }
                    break;
            }
            
            
            $this->connected = false;
        }
    }
    
    private function _set_charset($names, $character = null)
    {
        if ($names)
        {
            $this->db->query_write("SET NAMES '" . $names . "'");
        }
        else 
        {
            $this->db->query_write("SET NAMES DEFAULT");
        }
        
        if ($character)
        {
            $this->db->query_write("SET CHARACTER SET '" . $character . "'");
        }
        else if ($character === null)
        {
            $this->db->query_write("SET CHARACTER SET DEFAULT");
        }
    }
    
    protected function _fetch_home_url()
    {
        if (!$this->vbulletin->basepath)
        {
            if (method_exists($this->vbulletin->input, 'fetch_basepath'))
            {
                $this->vbulletin->basepath = $this->vbulletin->input->fetch_basepath();
            }
            else 
            {
                $this->vbulletin->basepath = "http://" . $_SERVER['HTTP_HOST'] . substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], "/")) . "/";
            }
        }
        
        return $this->vbulletin->basepath = str_replace('www.', '', $this->vbulletin->basepath);
    }
    
    public function login()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_login'] && THIS_SCRIPT != 'register' && !defined('EXTERNAL_AUTH'))
        {
            $member_name = $this->vbulletin->GPC['vb_login_username'];
            $member_pass = $this->vbulletin->GPC['vb_login_password'];
            
            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $member_name = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $member_name);
                $member_pass = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $member_pass);
            }
            
            $username_safe = $this->db->escape_string($member_name);
            $member_pass_md5 = ($member_pass)?md5($member_pass):$this->vbulletin->GPC['vb_login_md5password'];
            
            $this->_db_connect();
            $dleuser = $this->db->query_first("SELECT * FROM " . USERPREFIX . "_users WHERE name='$username_safe' AND password='" . md5($member_pass_md5) . "'");
            
            if (!$dleuser && $this->vbulletin->options['dle_login_create_account'])
            {
                $this->vbulletin->GPC['username'] = $this->vbulletin->GPC['vb_login_username'];
                $this->vbulletin->GPC['password'] = $this->vbulletin->GPC['vb_login_password'];
                $this->vbulletin->GPC['password_md5'] = ($this->vbulletin->GPC['vb_login_password'])?md5($this->vbulletin->GPC['vb_login_password']):$this->vbulletin->GPC['vb_login_md5password'];
                $this->vbulletin->GPC['email'] = $this->vbulletin->userinfo['email'];
                
                $this->lock_connect = true;
                
                $this->CreateAccount();
                
                $this->lock_connect = false;
                
                $dleuser = $this->db->query_first("SELECT * FROM " . USERPREFIX . "_users WHERE name='$username_safe' AND password='" . md5($member_pass_md5) . "'");
            }
            
            if ($dleuser)
            {
                if (strpos($this->vbulletin->options['bburl'], $this->cookie_domain) === false)
                {
                    $this->_db_disconnect();
                    
                    $data = base64_encode(serialize(array($dleuser['user_id'], $member_pass_md5, $this->vbulletin->session->vars['sessionhash'])));
                    $hash = md5($this->vbulletin->config['MasterServer']['password'] . $data . $this->vbulletin->config['Database']['dbname']);
                    
                    $this->vbulletin->session->save();
                    
                    $this->db->close();
                    
                    $url = $this->vbulletin->options['homeurl'] . "?vbauth=" . urlencode($data) . "&hash=" . $hash;
                    
                    header('Location:' . $url);
                    
                    die("Вы были перенаправлены сюда <a href='$url'>" . $url . "</a>");
                }
                else
                {
                    if ($this->vbulletin->options['dle_hash'])
                    {
                        $hash = md5(uniqid(time()));
                        
                        $this->db->query_write('UPDATE ' . USERPREFIX . "_users SET hash='$hash' WHERE user_id=" . $dleuser['user_id']);
                        
                        $this->setcookie('dle_hash', $hash, 365);
                    }
            
                    $this->setcookie("dle_user_id", $dleuser['user_id'], 365);
                    $this->setcookie("dle_password", $member_pass_md5, 365);
    
                    if (!session_id())
                    {
                        session_start();
                    }
                    
                    
                    $_SESSION['dle_user_id'] = $dleuser['user_id'];
                    $_SESSION['dle_password'] = $member_pass_md5;
                    $_SESSION['member_lasttime'] = $this->vbulletin->GPC['bblastvisit'];
                }
            }
            
            $this->_db_disconnect();
        }
    }
    
    public function CheckLoginDLE($username, $password, $md5password, $cookieuser)
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_login_create_vb_account'])
        {
            if ($username && ($password || $md5password))
            {
                if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                {
                    if ($password)
                    {
                        $password = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $password);
                    }
                    $dle_username = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $username);
                }
                
                $md5password = ($password)?md5($password):$md5password;
                
                $this->_db_connect();
                
                if ($user_dle = $this->db->query_first("SELECT * FROM " . USERPREFIX . "_users WHERE name='" . $this->db->escape_string($dle_username) . "'"))
                {
                    $this->_db_disconnect();
                    
                    if ($user_dle['password'] == md5($md5password))
                    {
                        $userdata =& datamanager_init('User', $this->vbulletin, ERRTYPE_ARRAY);
                        
                        $userdata->set('email', iconv(DLE_CHARSET, $this->vbulletin->userinfo['lang_charset'], $user_dle['email']));
                        $userdata->set('password', $md5password);
                        $userdata->set('username', $username);
                        $userdata->set('languageid', $this->vbulletin->userinfo['languageid']);
                        $userdata->set('usergroupid', 2);
                        $userdata->set('showvbcode', 1);
                        $userdata->set('showbirthday', 0);
                        $userdata->set_usertitle('', false, $this->vbulletin->usergroupcache["2"], false, false);
                        $userdata->set_bitfield('options', 'adminemail', 1);
                        $userdata->set('ipaddress', IPADDRESS);
                        $userdata->set('posts', 0);
                        $userdata->set('joindate', $user_dle['reg_date']);
                        $userdata->set('lastvisit', $user_dle['lastdate']);
                        $userdata->set('lastactivity', $user_dle['lastdate']);
                        
                        $isset_field = array();
                        
                        if ($user_dle['xfields'])
                        {
                            $fieldcontent_array = explode("||", $user_dle['xfields']);
                            
                            foreach ($fieldcontent_array as $field)
                            {
                                $part = explode("|", $field);
                                
                                $isset_field[$part[0]] = $part[1];
                            }
                        }
                        
                        $insert_field = array();
                        foreach ($this->vbulletin->options['dle_fields'] as $vb_field => $dle_field)
                        {
                            if ($dle_field)
                            {
                                if (in_array($dle_field, array('info', 'land', 'fullname')))
                                {
                                    $value = $user_dle[$dle_field];
                                }
                                else if(!empty($isset_field[$dle_field]))
                                {
                                    $value = $isset_field[$dle_field];
                                }
                                else 
                                {
                                    continue;
                                }
                                
                                if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                                {
                                    $value = iconv(DLE_CHARSET, $this->vbulletin->userinfo['lang_charset'], $value);
                                }
                                
                                if (in_array($vb_field, $vb_std_fields))
                                {
                                    $userdata->set($vb_field, $value);
                                }
                                else 
                                {
                                    $insert_field[$vb_field] = $value;
                                }
                            }
                        }
                        
                        if ($insert_field)
                        {
                            $userdata->set_userfields($insert_field, false, 'register');
                        }
                        
                        if ($user_dle['signature'])
                        {
                            include_once DIR . '/includes/class_dle_parse.php';
                            $parse = new DLE_ParseFilter();
                            
                            $userdata->set('signature', $parse->decodeBBCodes($user_dle['signature']));
                        }
                        
                        $userdata->pre_save();
                        
                        if (empty($userdata->errors))
                        {
                            $this->vbulletin->userinfo['userid'] = $userdata->save();
                            
                            if ($cookieuser)
                            {
                                vbsetcookie('userid', $this->vbulletin->userinfo['userid'], true, true, true);
                                vbsetcookie('password', md5($this->vbulletin->userinfo['password'] . COOKIE_SALT), true, true, true);
                            }
                            else if ($this->vbulletin->GPC[COOKIE_PREFIX . 'userid'] AND $this->vbulletin->GPC[COOKIE_PREFIX . 'userid'] != $this->vbulletin->userinfo['userid'])
                            {
                                vbsetcookie('userid', '', true, true, true);
                                vbsetcookie('password', '', true, true, true);
                            }
                            
                            $this->vbulletin->options['dle_login_create_account'] = 0;
                            
                            if ($user_dle['foto'])
                            {
                                $this->UpdatevBAvatar(array('username' => $user_dle['name'], 'avatarurl' => $this->vbulletin->options['homeurl'] . "uploads/fotos/" . $user_dle['foto']));
                            }
                            
                            return true;
                        }
                        else 
                        {
                            //print_r($username->errors);
                        }
                    }
                }
                
                $this->_db_disconnect();
            }
        }
        
        return false;
    }
    
    public function logout()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_logout'] && !defined('EXTERNAL_LOGOUT'))
        {
            if (strpos($this->vbulletin->options['bburl'], $this->cookie_domain) === false)
            {
                $url = $this->vbulletin->options['homeurl'] . "?vblogout=1";
                    
                header('Location:' . $url);
                    
                die("Вы были перенаправлены сюда <a href='$url'>" . $url . "</a>");
            }
            
            $this->setcookie("dle_user_id", "");
            $this->setcookie("dle_password", "");
            $this->setcookie("dle_newpm", "");
            $this->setcookie("dle_hash", "");
            $this->setcookie("PHPSESSID", "");
            $this->setcookie("PHPSESSID", "", 0, false);
            
            if (!session_id())
            {
                session_start();
            }
            
            $this->setcookie(session_name(), "");
            $this->setcookie(session_name(), "", 0, false);
            $_SESSION['dle_user_id'] = "";
            $_SESSION['dle_password'] = "";
            
        }
    }
    
    public function CreateAccount()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_register'])
        {
            $user_name     = $this->vbulletin->GPC['username'];
            $user_pass     = $this->vbulletin->GPC['password'];
            $user_pass_md5 = $this->vbulletin->GPC['password_md5'];
            $user_email    = $this->vbulletin->GPC['email'];
            
            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $user_name = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_name);
                $user_pass = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_pass);
                $user_email = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_email);
                $user_pass_md5 = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_pass_md5);
            }
            
            if ($user_pass)
            {
                $user_pass_hash = md5(md5($user_pass));
                $user_pass_md5 = md5($user_pass);
            }
            else 
            {
                $user_pass_hash = md5($user_pass_md5);
            }
            
            $this->_db_connect();
            
            $user_name = $this->db->escape_string($user_name);
            $user_email = $this->db->escape_string($user_email);
            
            $user_id = $this->db->query_first("SELECT user_id FROM " . USERPREFIX . "_users WHERE name='$user_name' OR email='$user_email'");
            
            if (!$user_id)
            {
                $reg_group = ($this->vbulletin->options['dle_reg_group'] < 3)?4:$this->vbulletin->options['dle_reg_group'];
                
                if ($this->vbulletin->options['dle_hash'])
                {
                    $hash = md5(uniqid(time()));
                    
                    $this->setcookie('dle_hash', $hash, 365);
                }
                else 
                {
                    $hash = '';
                }
                
                $this->db->query_write("INSERT INTO " . USERPREFIX . "_users 
                                            (name, password, email, reg_date, lastdate, user_group, logged_ip, hash) VALUES 
                                            ('$user_name', '$user_pass_hash', '$user_email', '" . TIMENOW . "', '" . TIMENOW . "', '$reg_group', '".$this->db->escape_string(IPADDRESS)."', '$hash')");

                if (!$this->lock_connect)
                {
                    $id = $this->db->insert_id();
                
                    $this->setcookie("dle_user_id", $id, 365);
                    $this->setcookie("dle_password", $user_pass_md5, 365);
    
                    if (!session_id())
                    {
                        session_start();
                    }
                    
                    $_SESSION['dle_user_id'] = $id;
                    $_SESSION['dle_password'] = $user_pass_md5;
                    $_SESSION['member_lasttime'] = TIMENOW;
                }
            }
            
            $this->_db_disconnect();
        }
    }
    
    public function ChangePasswordEmail()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_change_pass_email'])
        {
            $this->_db_connect();
            
            $user_name = $this->vbulletin->userinfo['username'];
            
            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $user_name = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_name);
            }
            
            $user_name = $this->db->escape_string($user_name);
            $email = $this->db->escape_string($this->vbulletin->GPC['email']);
            
            if (strlen($this->vbulletin->GPC['newpassword_md5']) == 32)
            {
                $new_pass = md5($this->vbulletin->GPC['newpassword_md5']);
                
                $this->db->query_write("UPDATE " . USERPREFIX . "_users SET password='$new_pass', email='$email' WHERE name='$user_name'");
                
                $this->setcookie('dle_password', $this->vbulletin->GPC['newpassword_md5'], 365);
            }
            else
            {
                $this->db->query_write("UPDATE " . USERPREFIX . "_users SET email='$email' WHERE name='$user_name'");
            }
            
            $this->_db_disconnect();
        }
    }
    
    public function UpdateProfile()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_profile'])
        {
            $user_name = $this->vbulletin->userinfo['username'];
            $icq = $this->vbulletin->GPC['icq'];
            
            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $user_name = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_name);
                $icq = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $icq);
            }
            
            $this->_db_connect();
            
            $user_name = $this->db->escape_string($user_name);
            
            $dleuser = $this->db->query_first("SELECT user_id, xfields FROM " . USERPREFIX . "_users WHERE name='$user_name'");
            
            if (empty($dleuser['user_id']))
            {
                $this->_db_disconnect();
                return false;
            }
            
            $xfields_array = array();
            
            if ($dleuser['xfields'])
            {
                $isset_fields = explode("||", $dleuser['xfields']);
                
                foreach ($isset_fields as $field_name_value)
                {
                    $part = explode("|", $field_name_value);
                    
                    $xfields_array[$part[0]] = $part[1];
                }
            }
            
            include_once DIR . '/includes/class_dle_parse.php';
            $parse = new DLE_ParseFilter();
            
            $update_field = '';
            
            $fields = array(
                        'homepage' => $this->vbulletin->GPC['homepage'],
                        'aim' => $this->vbulletin->GPC['aim'],
                        'msn' => $this->vbulletin->GPC['msn'],
                        'skype' => $this->vbulletin->GPC['skype'],
                        'yahoo' => $this->vbulletin->GPC['yahoo'],
                        );
                        
            $fields += $this->vbulletin->GPC['userfield'];
            
            foreach ($this->vbulletin->options['dle_fields'] as $vb_field => $dle_field)
            {
                if (!empty($dle_field))
                {
                    $value = $fields[$vb_field];
                    
                    if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                    {
                        $value = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $value);
                    }
                    
                    if (in_array($dle_field, array('land', 'info', 'fullname')))
                    {
                        $update_field .= ", $dle_field='" . $this->db->escape_string($value) . "'";
                    }
                    else 
                    {
                        $value = str_replace( "|", "&#124;", $value );
                        $value = $parse->BB_Parse($parse->process($value));
                        
                        $xfields_array[$dle_field] = $value;
                    }
                }
            }
            
            if ($xfields_array)
            {
                $xfields_str = '';
                foreach ($xfields_array as $field_dle_name => $value)
                {
                    if ($xfields_str)
                    {
                        $xfields_str .= "||";
                    }
                    
                    $xfields_str .= $field_dle_name . "|" . $value;
                }
                
                $update_field .= ", xfields='" . $this->db->escape_string($xfields_str) . "'";
            }
            
            $icq = $this->db->escape_string($icq);
            $query = "UPDATE " . USERPREFIX . "_users SET icq='$icq'$update_field WHERE user_id=" . $dleuser['user_id'];
            
            $this->db->query_write($query);
            
            $this->_db_disconnect();
        }
    }
    
    public function UpdateProfileAJAX()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_profile'])
        {
            
            if (!$this->vbulletin->userinfo['userid'])
            {
                print_no_permission();
            }
        
            if (!($this->vbulletin->userinfo['permissions']['genericpermissions'] & $this->vbulletin->bf_ugp_genericpermissions['canmodifyprofile']))
            {
                print_no_permission();
            }
            
            $user_name = $this->vbulletin->userinfo['username'];
            
            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $user_name = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_name);
            }
            
            $this->_db_connect();
            
            $user_name = $this->db->escape_string($user_name);
            
            $dleuser = $this->db->query_first("SELECT user_id, xfields FROM " . USERPREFIX . "_users WHERE name='$user_name'");
            
            if (!empty($dleuser['user_id']))
            {
                $xfields_array = array();
                
                if ($dleuser['xfields'])
                {
                    $isset_fields = explode("||", $dleuser['xfields']);
                    
                    foreach ($isset_fields as $field_name_value)
                    {
                        $part = explode("|", $field_name_value);
                        
                        $xfields_array[$part[0]] = $part[1];
                    }
                }
                
                $this->vbulletin->input->clean_array_gpc('p', array(
                                                            'fieldid'   => TYPE_UINT,
                                                            'userfield' => TYPE_ARRAY
                                                        ));
                
                function dle_convert_urlencoded_unicode_recursive($item)
                {
                    if (is_array($item))
                    {
                        foreach ($item AS $key => $value)
                        {
                            $item["$key"] = dle_convert_urlencoded_unicode_recursive($value);
                        }
                    }
                    else
                    {
                        $item = convert_urlencoded_unicode(trim($item));
                    }
            
                    return $item;
                }
            
                // handle AJAX posting of %u00000 entries
                $this->vbulletin->GPC['userfield'] = dle_convert_urlencoded_unicode_recursive($this->vbulletin->GPC['userfield']);
                           
                $update_field = '';
                $fields = $this->vbulletin->GPC['userfield'];
                
                foreach ($this->vbulletin->options['dle_fields'] as $vb_field => $dle_field)
                {
                    if (!empty($dle_field) && isset($fields[$vb_field]))
                    {
                        $value = $fields[$vb_field];
                        
                        if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                        {
                            $value = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $value);
                        }
                        
                        if (in_array($dle_field, array('land', 'info', 'fullname')))
                        {
                            if ($update_field)
                            {
                                $update_field .= ", ";
                            }
                            
                            $update_field .= $dle_field . "='" . $this->db->escape_string($value) . "'";
                        }
                        else 
                        {
                            $value = str_replace( "|", "&#124;", $value );
                            $value = $parse->BB_Parse($parse->process($value));
                            
                            $xfields_array[$dle_field] = $value;
                        }
                    }
                }
                
                if ($xfields_array)
                {
                    $xfields_str = '';
                    foreach ($xfields_array as $field_dle_name => $value)
                    {
                        if ($xfields_str)
                        {
                            $xfields_str .= "||";
                        }
                        
                        $xfields_str .= $field_dle_name . "|" . $value;
                    }
                    
                    if ($update_field)
                    {
                        $update_field .= ", xfields='" . $this->db->escape_string($xfields_str) . "'";
                    }
                    else
                    {
                        $update_field .= "xfields='" . $this->db->escape_string($xfields_str) . "'";
                    }
                }
                
                if ($update_field)
                {
                    $this->db->query_write("UPDATE " . USERPREFIX . "_users SET $update_field WHERE user_id=" . $dleuser['user_id']);
                }
            }
            
            $this->_db_disconnect();
        }
    }
    
    public function UpdateSignature($userinfo_sigpic, $signature)
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_profile'])
        {
            $this->_db_connect();
            
            $user_name = $this->vbulletin->userinfo['username'];
            
            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $user_name = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_name);
                $signature = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $signature);
            }
            
            $signature = $this->_parseBB($signature, 'signature');
//            echo $previewmessage;exit();
            $signature = $this->db->escape_string($signature);
            $user_name = $this->db->escape_string($user_name);
            
            $this->db->query_write("UPDATE " . USERPREFIX . "_users SET signature='$signature' WHERE name='$user_name'");
            
            $this->_db_disconnect();
        }
    }
    
    public function UpdatevBAvatar(array $info)
    {
        if (empty($info['username']))
        {
            return false;
        }
        
        $username = $info['username'];
        
        if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
        {
            $username = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $username);
        }
        
        if ($this->vbulletin->userinfo['username'] != $username)
        {
            $this->vbulletin->userinfo = $this->vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "user WHERE username='$username'");
        }
        
        if ($this->vbulletin->userinfo['userid'])
        {
            $this->vbulletin->userinfo = fetch_userinfo($this->vbulletin->userinfo['userid'], (defined('IN_CONTROL_PANEL') ? 16 : 0) + (defined('AVATAR_ON_NAVBAR') ? 2 : 0));
            cache_permissions($this->vbulletin->userinfo);
            
            if (!empty($info['delete']))
            {
                if ($this->vbulletin->userinfo['avatarid'])
                {
                    $userdata =& datamanager_init('User', $this->vbulletin, ERRTYPE_STANDARD);
                    $userdata->set_existing($this->vbulletin->userinfo);
                    $userdata->set('avatarid', 0);
                    $userdata->save();
                }
                else 
                {
                    $userpic =& datamanager_init('Userpic_Avatar', $this->vbulletin, ERRTYPE_STANDARD, 'userpic');
                    $userpic->condition = 'userid = ' . $this->vbulletin->userinfo['userid'];
                    $userpic->delete();
                }
            }
            elseif (!empty($info['avatarurl']))
            {
                require_once(DIR . '/includes/class_upload.php');
                require_once(DIR . '/includes/class_image.php');
                
                $upload = new vB_Upload_Userpic($this->vbulletin);

                $upload->data =& datamanager_init('Userpic_Avatar', $this->vbulletin, ERRTYPE_STANDARD, 'userpic');
                $upload->image =& vB_Image::fetch_library($this->vbulletin);
                $upload->maxwidth = $this->vbulletin->userinfo['permissions']['avatarmaxwidth'];
                $upload->maxheight = $this->vbulletin->userinfo['permissions']['avatarmaxheight'];
                $upload->maxuploadsize = $this->vbulletin->userinfo['permissions']['avatarmaxsize'];
                $upload->allowanimation = ($this->vbulletin->userinfo['permissions']['genericpermissions'] & $this->vbulletin->bf_ugp_genericpermissions['cananimateavatar']) ? true : false;
    
                $upload->process_upload($info['avatarurl']);
                
                $userdata =& datamanager_init('User', $this->vbulletin, ERRTYPE_STANDARD);
                $userdata->set_existing($this->vbulletin->userinfo);
                $userdata->set('avatarid', 0);
                $userdata->save();
                
            }
       }
    }
    
    public function UpdateDLEAvatar()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_profile'])
        {
            $sendinfo = array();
            
            if (!$GLOBALS['useavatar'])
            {
                $sendinfo['delete'] = 1;
            }
            else if ($this->vbulletin->GPC['avatarid'])
            {
                $path = $this->vbulletin->db->query_first("SELECT avatarpath FROM " . TABLE_PREFIX . "avatar WHERE avatarid=" . $this->vbulletin->GPC['avatarid']);
                
                $sendinfo['avatarurl'] = $path['avatarpath'];
            }
            else 
            {
                if (method_exists($this->vbulletin->input, 'fetch_basepath'))
                {
                    $this->vbulletin->input->fetch_basepath();
                }
                else 
                {
                    $this->vbulletin->basepath = "http://www." . $_SERVER['HTTP_HOST'] . substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], "/")) . "/";
                }
                
                $this->_fetch_home_url();
                
                if ($this->vbulletin->options['usefileavatar'])
                {
                    $sendinfo['avatarurl'] = $this->vbulletin->basepath . $this->vbulletin->options['avatarurl'] . '/avatar' . $this->vbulletin->userinfo['userid'] . '_' . ($this->vbulletin->userinfo['avatarrevision'] + 1) . '.gif';
                }
                else
                {
                    $sendinfo['avatarurl'] = $this->vbulletin->basepath . 'image.php?u=' . $this->vbulletin->userinfo['userid'] . "&amp;dateline=" . $GLOBALS['upload']->data->dataline;
                }
            }
            
            if ($sendinfo)
            {
                $sendinfo['username'] = $this->vbulletin->userinfo['username'];
                
                $data = base64_encode(serialize($sendinfo));
                $hash = md5($this->vbulletin->config['MasterServer']['password'] . $data . $this->vbulletin->config['Database']['dbname']);
                $url = $this->vbulletin->options['homeurl'] . "index.php?data=" . urlencode($data) . "&hash=" . $hash;
                
                $this->_sendRequest($url);
            }
        }
    } 
    
    public function changeUserGroup()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_profile'])
        {
            if ($dle_group = (int)$this->vbulletin->options['dle_groups'][$this->vbulletin->GPC['user']['usergroupid']])
            {
                $username = $this->vbulletin->GPC['user']['username'];
                
                if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                {
                    $username = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $username);
                }
                
                $this->_db_connect();
                $username = $this->db->escape_string($username);
                
                $this->db->query_write('UPDATE ' . USERPREFIX . "_users SET user_group=$dle_group WHERE name='$username'");
                
                $this->_db_disconnect();
            }
        }
    }
    
    private function _sendRequest($url)
    {
        if (function_exists('curl_init'))
        {
            $curl = curl_init($url);
            
            curl_setopt($curl, CURLOPT_NOBODY, 0);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, 1000);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            
            curl_exec($curl);
            curl_close($curl);
        }
        else
        {
            $stream = fsockopen($url, 80);
            stream_set_timeout(1);
            fclose($stream);
        }
    }
    
    public function ChangePass($user_name, $newpassword)
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_lostpass'])
        {
            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $user_name = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_name);
                $newpassword = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $newpassword);
            }
            
            $this->_db_connect();
            
            $pass_hash = md5(md5($newpassword));
            $user_name = $this->db->escape_string($user_name);
            
            $this->db->query_write("UPDATE " . USERPREFIX . "_users SET password='$pass_hash' WHERE name='$user_name'");
            
            $this->_db_disconnect();
        }
    }
    
    public function NewPM(vB_DataManager_PM &$pmdm)
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_pm'])
        {
            $user_from = $pmdm->pmtext['fromusername'];
            $to_user   = $pmdm->pmtext['touserarray'];
            $subj      = $pmdm->pmtext['title'];
            $text      = $pmdm->pmtext['message'];
            $send = "<b>Это сообщение было отправленно c форума следующим пользователям: </b>";
            
            $text = $this->_parseBB($text);
//            $text = preg_replace('#<!--.*?-->#si', "", $text);

            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $user_from = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user_from);
                $to_user   = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $to_user);
                $subj      = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $subj);
                $text      = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $text);
//                $send      = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $send);
            }

            if (strtolower($this->vbulletin->userinfo['lang_charset']) != 'utf-8') {
                $send      = iconv('utf-8', $this->vbulletin->userinfo['lang_charset'], $send);
            }
            
            $to_user = unserialize($to_user);
            
            $this->_db_connect();
            
            $subj = $this->db->escape_string($subj);
            $text = $this->db->escape_string($text);
            $user_from = $this->db->escape_string($user_from);
            
            if (empty($to_user['cc']))
            {
                if (!empty($to_user['bcc']))
                {
                    $to_user_a = $to_user['bcc'];
                }
                else 
                {
                    $this->_db_disconnect();
                    return ;
                }
            }
            else 
            {
                $to_user_a = $to_user['cc'];
            }
            
            $to_user_name = array();
            foreach ($to_user_a as $user)
            {
                if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                {
                    $user = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $user);
                }
                
                $to_user_name[] = $this->db->escape_string($user);
            }
            
            $result_users = $this->db->query_read("SELECT user_id, name FROM " . USERPREFIX . "_users WHERE name IN('". implode("','", $to_user_name) ."')");
            
            if ($this->db->num_rows($result_users))
            {
                $i=0;
                $send_user_array = $users_id = array();
                $values = '';
                
                while ($value = $this->db->fetch_array($result_users)) 
                {
                    $users_id[] = $value['user_id'];
                    
                    if (in_array($value['user_id'], $send_user_array)) 
                    {
                        continue;
                    }
                        
                    if ($i != 0)
                    {
                        $send .= ", ";
                        $values .= ", ";
                    }
                    
                    $send .="<a href=\"{$config['http_home_url']}index.php?subaction=userinfo&user={$value['name']}\" >{$value['name']}</a>";
                    $values .= "('$subj', '$text', '{$value['user_id']}', '$user_from', '{$pmdm->pmtext['dateline']}', 'no', 'inbox')"; 
                    $send_user_array[] = $value['user_id'];
                    $i++;
                }
                
                $this->db->query_write("INSERT INTO " . USERPREFIX . "_pm (subj, text, user, user_from, date, pm_read, folder) VALUES $values");
                $this->db->query_write("UPDATE " . USERPREFIX . "_users SET pm_all=pm_all+1, pm_unread=pm_unread+1 WHERE user_id IN('". implode("','", $users_id) ."')");
                
                
                if ($pmdm->info['savecopy']) 
                {
                    $user = $users_id[0];
                    
                    if ($user)
                    {
                        if ($i > 1) 
                        {
                            $text = $this->db->escape_string($send) . "<hr></br></br>" . $text;
                        }
                        
                        $this->db->query_write("INSERT INTO " . USERPREFIX . "_pm (subj, text, user, user_from, date, pm_read, folder) values ('$subj', '$text', '{$user}', '$user_from', '{$pmdm->pmtext['dateline']}', 'yes', 'outbox')");
                        $this->db->query_write("UPDATE " . USERPREFIX . "_users SET pm_all=pm_all+1 where name='$user_from'");
                    }
                }
            }
            
            $this->_db_disconnect();
        }
    } 
    
    public function EmptyFolderPM()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_pm'])
        {
            $this->_db_connect();
            
            $user_name = $this->db->escape_string($this->vbulletin->userinfo['username']);
    
            $user_id = $this->db->query_first("SELECT user_id FROM " . USERPREFIX . "_users WHERE name='$user_name'");
            
            if ($user_id)
            {
                if ($this->vbulletin->GPC['folderid'] == -1)
                {
                    $this->db->query_write("DELETE FROM " . USERPREFIX . "_pm WHERE folder='outbox' AND user={$user_id['user_id']}");
                }
                else 
                {
                    $this->db->query_write("DELETE FROM " . USERPREFIX . "_pm WHERE folder='inbox' AND user={$user_id['user_id']}");
                }
                
                if ($num_rows = $this->db->affected_rows())
                {
                    $pm_unread = '';
                    
                    if ($this->vbulletin->GPC['folderid'] != -1)
                    {
                        $pm_unread = ", pm_unread=0";
                    }
                    
                    $this->db->query_write("UPDATE " . USERPREFIX . "_users SET pm_all=IF(pm_all-$num_rows<=0, 0, pm_all-$num_rows)$pm_unread WHERE user_id=" . $user_id['user_id']);
                }
            }
            
            $this->_db_disconnect();
        }
    }

    public function DeletePM(array $messageids)
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_pm'] && $this->vbulletin->GPC['dowhat'] == 'delete')
        {
            $result_pm = $this->vbulletin->db->query_read("SELECT pmtextid, folderid, messageread FROM  " . TABLE_PREFIX . "pm WHERE pmid IN(". implode(', ', $messageids) .") AND userid = " . $this->vbulletin->userinfo['userid']);
            
            while ($value = $this->vbulletin->db->fetch_array($result_pm))
            {
                $pm_tx_id[] = $value['pmtextid'];
                
                if (!$value['messageread'])
                {
                    $pm_unread++;
                }
                    
                $pm_all++;
                
                if ($value['folderid'] == -1)
                {
                    $folder = "outbox";
                }
                else
                {
                    $folder = "inbox";
                }
            }
            
            if ($pm_tx_id)
            {
                $result = $this->vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "pmtext WHERE pmtextid IN(". implode(', ', $pm_tx_id) .")");
                
                $this->_db_connect();
                
                $to_user = $this->db->escape_string($this->vbulletin->userinfo['username']);
                $user_id = $this->db->query_first("SELECT user_id FROM " . USERPREFIX . "_users WHERE name='$to_user' LIMIT 1");
                
                if ($user_id)
                {
                    while ($pm_info = $this->vbulletin->db->fetch_array($result))
                    {
                        if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                        {
                            $pm_info['fromusername'] = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $pm_info['fromusername']);
                        }
                        
                        $fromusername = $this->db->escape_string($pm_info['fromusername']);
                        
                        $this->db->query_write("DELETE FROM " . USERPREFIX . "_pm WHERE user_from='{$fromusername}' AND date='{$pm_info['dateline']}' AND folder='$folder' AND user='{$user_id['user_id']}'");
                    }
                    
                    if ($pm_unread > 0)
                    {
                        $this->db->query_write("UPDATE " . USERPREFIX . "_users SET pm_unread=IF(pm_unread <= $pm_unread, 0, pm_unread-$pm_unread), pm_all=IF(pm_all <= $pm_all, 0, pm_all-$pm_all) WHERE user_id='{$user_id['user_id']}'");
                    }
                    else
                    {
                        $this->db->query_write("UPDATE " . USERPREFIX . "_users SET pm_all=IF(pm_all <= $pm_all, 0, pm_all-$pm_all) WHERE user_id='{$user_id['user_id']}'");
                    }
                }
                
                $this->_db_disconnect();
            }
        }
    } 
    
    public function ReadPM()
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_pm'])
        {
            $pmid = $this->vbulletin->GPC['pmid'];
            $pm_info = $this->vbulletin->db->query_first("SELECT pt.*, p.messageread FROM " . TABLE_PREFIX . "pmtext AS pt 
                                                    LEFT JOIN " . TABLE_PREFIX ."pm AS p
                                                    ON pt.pmtextid=p.pmtextid
                                                    WHERE p.pmid='$pmid' AND p.userid=" . $this->vbulletin->userinfo['userid'] . " LIMIT 1");
            if ($pm_info && !$pm_info['messageread'])
            {
                $this->_db_connect();
                
                $user_name = $this->db->escape_string($this->vbulletin->userinfo['username']);
                
                if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                {
                    $pm_info['fromusername'] = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $pm_info['fromusername']);
                }
                            
                $fromusername = $this->db->escape_string($pm_info['fromusername']);
                
                $user = $this->db->query_first("SELECT user_id FROM " . USERPREFIX . "_users WHERE name='$user_name' LIMIT 1");
                
                if ($user)
                {
                    $this->db->query_write("UPDATE " . USERPREFIX . "_users SET pm_unread=IF(pm_unread <= 1, 0, pm_unread-1) WHERE user_id='{$user['user_id']}'");
                    $this->db->query_write("UPDATE " . USERPREFIX . "_pm SET pm_read=1 WHERE user_from='$fromusername' AND date='{$pm_info['dateline']}' AND pm_read=0 AND folder='inbox' AND user={$user['user_id']}");
                }
                
                $this->_db_disconnect();
            }
        }
    }
    
    public function ReadUnreadPackPM(array $messageids, $set = 'yes')
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_pm'])
        {
            $pm_result = $this->vbulletin->db->query_read("SELECT pt.*, p.messageread, p.folderid FROM " . TABLE_PREFIX . "pmtext AS pt 
                                                    LEFT JOIN " . TABLE_PREFIX ."pm AS p
                                                    ON pt.pmtextid=p.pmtextid
                                                    WHERE p.pmid IN (" . implode(", ", $messageids) . ") AND p.userid=" . $this->vbulletin->userinfo['userid'] . " LIMIT 1");
            
            if ($this->vbulletin->db->num_rows($pm_result))
            {
                $this->_db_connect();
                
                $user_name = $this->db->escape_string($this->vbulletin->userinfo['username']);
                $user = $this->db->query_first("SELECT user_id FROM " . USERPREFIX . "_users WHERE name='$user_name' LIMIT 1");
                
                if ($user)
                {
                    $pm_unread = 0;
                    while ($pm_info = $this->vbulletin->db->fetch_array($pm_result))
                    {
                        if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
                        {
                            $pm_info['fromusername'] = iconv($this->vbulletin->userinfo['lang_charset'], DLE_CHARSET, $pm_info['fromusername']);
                        }
                        
                        $fromusername = $this->db->escape_string($pm_info['fromusername']);

                        if ($pm_info['messageread'])
                        {
                            $pm_unread++;
                        }
                        
                        if ($pm_info['folderid'] == -1)
                        {
                            $folder = "outbox";
                        }
                        else
                        {
                            $folder = "inbox";
                        }
                        
                        $this->db->query_write("UPDATE " . USERPREFIX . "_pm SET pm_read='$set' WHERE user_from='$fromusername' AND date='{$pm_info['dateline']}' AND folder='$folder' AND user={$user['user_id']}");
                    }
                    
                    if ($pm_unread)
                    {
                        $this->db->query_write("UPDATE " . USERPREFIX . "_users SET pm_unread=IF(pm_unread <= $pm_unread, 0, pm_unread-$pm_unread) WHERE user_id='{$user['user_id']}'");
                    }
                }
                
                $this->_db_disconnect();
            }
        }
    }
    
    public function ClearThreadId(array $threadarray)
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_discussion'])
        {
            $this->_db_connect();
        
            $this->db->query_write("UPDATE " . PREFIX . "_post SET vb_threadid=0 WHERE vb_threadid IN (" . implode(", ", array_keys($threadarray)) . ")");
        
            $this->_db_disconnect();
        }
    }
    
    public function SetThreadId($threadid)
    {
        if ($this->vbulletin->options['dle_onoff'] && $this->vbulletin->options['dle_discussion'])
        {
            $this->vbulletin->input->clean_gpc('p', 'news_id', TYPE_UINT);
            
            if ($this->vbulletin->GPC['news_id'])
            {
                $this->_db_connect();
            
                $this->db->query_write("UPDATE " . PREFIX . "_post SET vb_threadid=$threadid WHERE id=" . $this->vbulletin->GPC['news_id']);
            
                $this->_db_disconnect();
            }
        }
    }
    
    public function GetFieldsSettings(array &$vbphrase, array $setting)
    {
        $setting['value'] = unserialize($setting['value']);
        
        $fields = array(
                        'homepage' => $vbphrase['visit_homepage'],
                        'aim' => $vbphrase['aim_screen_name'],
                        'msn' => $vbphrase['msn_messenger_handle'],
                        'skype' => $vbphrase['skype_name'],
                        'yahoo' => $vbphrase['yahoo_messenger_handle'],
                        );
                        
        if (empty($fields['homepage']))
        {
            $fields['homepage'] = $vbphrase['home_page_url'];
        }
                
        $resource =  $this->vbulletin->db->query_read("SELECT r.text, f.profilefieldid FROM " . TABLE_PREFIX . "profilefield AS f
                                                  LEFT JOIN " . TABLE_PREFIX . "phrase AS r
                                                  ON r.varname = CONCAT('field', f.profilefieldid, '_title')
        ");
        
        while ($row =  $this->vbulletin->db->fetch_array($resource))
        {
            $fields['field' . $row['profilefieldid']] = $row['text'];
        }
        
        $text = "<table><tr><td align='right'><b>vB fields</b></td><td><b>DLE fields</b></td></tr>";
        
        foreach ($fields as $field => $title)
        {
        	$text .= "<tr><td align='right'>$title</td><td style='padding:1px;'><input style='text-align:center;' class='bginput' type='text' name='setting[{$setting['varname']}][$field]' value='{$setting['value'][$field]}' size='10' /></td></tr>\n";
        }
        
        $text .= "</table>";
        
        return $text;
    }
    
    public function GetGroupsSettings(array &$vbphrase, array $setting)
    {
        $setting['value'] = unserialize($setting['value']);
    
        $resource =  $this->vbulletin->db->query_read("SELECT usergroupid, title FROM " . TABLE_PREFIX . "usergroup");
        
        $text = "<table><tr><td align='right'><b>vB Groups</b></td><td><b>DLE GroupID</b></td></tr>";
        
        while ($row = $this->vbulletin->db->fetch_array($resource))
        {
        	$text .= "<tr><td align='right'>{$row['title']}</td><td style='padding:1px;'><input style='text-align:center;' class='bginput' type='text' name='setting[{$setting['varname']}][{$row['usergroupid']}]' value='{$setting['value'][$row['usergroupid']]}' size='10' /></td></tr>\n";
        }
        
        $text .= "</table>";
        
        $descr = '';
        $this->_db_connect();
        
        $resource =  $this->db->query_read("SELECT group_name, id FROM " . USERPREFIX . "_usergroups");
        
        while ($row = $this->vbulletin->db->fetch_array($resource))
        {
            if (DLE_CHARSET && DLE_CHARSET != $this->vbulletin->userinfo['lang_charset'])
            {
                $row['group_name'] = iconv(DLE_CHARSET, $this->vbulletin->userinfo['lang_charset'], $row['group_name']);
            }
        
        	$descr .= $row['id'] . " - " . $row['group_name'] . "<br />";
        }
        
        $this->_db_disconnect();
        
        return array(
                    'text' => $text,
                    'description' => $descr
                    );
    }
    
    public function ExternalAuthorization($userid)
    {
        $this->vbulletin->userinfo = $this->vbulletin->db->query_first_slave("SELECT userid, password, username FROM " . TABLE_PREFIX . "user WHERE userid='$userid'");
        
        if ($this->vbulletin->userinfo)
        {
            require_once(DIR . '/includes/functions_login.php');
            
            vbsetcookie('userid', $this->vbulletin->userinfo['userid'], true, true, true);
            vbsetcookie('password', md5($this->vbulletin->userinfo['password'] . COOKIE_SALT), true, true, true);
            
            exec_unstrike_user($this->vbulletin->userinfo['username']);
    
            define('EXTERNAL_AUTH', true);
    
            // create new session
            process_new_login('', 0, '');
        }
        
        if (!empty($_SERVER['HTTP_REFERER']))
        {
            $url = $_SERVER['HTTP_REFERER'];
        }
        else 
        {
            $url = $this->vbulletin->options['homeurl'];
        }
        
        if (strpos($url, "?"))
        {
            $url .= "&vbsession=" . $this->vbulletin->session->vars['sessionhash'];
        }
        else 
        {
            $url .= "?vbsession=" . $this->vbulletin->session->vars['sessionhash'];
        }
        
        header('Location:' . $url);
        echo "Вы были перенаправлены сюда <a href='" . $url . "'>" . $url . "</a>";
        
        exit();
    }
    
    private function _parseBB($text, $type = 'pm')
    {
        include_once DIR . '/includes/class_dle_parse.php';
        $parse = new DLE_ParseFilter();
        $parse->safe_mode = true;
//        $parse->allow_url = false;
//        $parse->allow_image = false;
        
        $bbparser = new vB_BbCodeParser($this->vbulletin, fetch_tag_list());
            
        $this->_fetch_home_url();
        
        $text = $bbparser->parse_smilies($text);
        $text = preg_replace('#<img .*?src=[\'"]([^h].+?)[\'"] .*?/>#si', "[img]{$this->vbulletin->basepath}\\1[/img]", $text);
        
        $text = $parse->process($text);
        
        if ($type == 'pm')
        {
            $quote = 'Цитата';
            if (strtolower($this->vbulletin->userinfo['lang_charset']) != 'utf-8') {
                $quote = iconv('utf-8', $this->vbulletin->userinfo['lang_charset'], $quote);
            }

            $text = preg_replace('#\[QUOTE\]#si', "<!--QuoteBegin--><div class=\"quote\"><!--QuoteEBegin-->", $text);
            $text = preg_replace('#\[quote=(.+?)\]#si', "<!--QuoteBegin \\1 --><div class=\"title_quote\">$quote: \\1</div><div class=\"quote\"><!--QuoteEBegin-->", $text);
            $text = preg_replace('#\[/quote\]#si', "<!--QuoteEnd--></div><!--QuoteEEnd-->", $text);
            
            $text = preg_replace('#\[post\]([0-9]+)\[/post\]#si', "<a href='" . $this->vbulletin->basepath . "showthread.php?p=\\1#post\\1'>"  . $this->vbulletin->basepath . "showthread.php?p=\\1</a>", $text);
        }   
        else if ($type == 'signature')
        {
            $text = strip_tags($text);
            
            if ($this->vbulletin->options['usefileavatar'])
            {
                $sigpic_url = $this->vbulletin->options['sigpicurl'] . '/sigpic' . $this->vbulletin->userinfo['userid'] . '_' . $this->vbulletin->userinfo['sigpicrevision'] . '.gif';
            }
            else
            {
                $sigpic_url = 'image.php?u=' . $this->vbulletin->userinfo['userid'] . "&amp;type=sigpic&amp;dateline=" . $userinfo_sigpic['sigpicdateline'];
            }
            
            $text = preg_replace('#\[SIGPIC\]\[/SIGPIC\]#si', "[img]{$this->vbulletin->basepath}{$sigpic_url}[/img]", $text);
            $text = preg_replace('#\[SIGPIC\](.+?)\[/SIGPIC\]#si', "[img=|\\1]{$this->vbulletin->basepath}{$sigpic_url}[/img]", $text);
        }
        
        $text = preg_replace( "#\[size=&quot;([^&]+)&quot;\]#is", "[size=\\1]", $text);
        $text = preg_replace( "#\[font=&quot;([^&]+)&quot;\]#is", "[font=\\1]", $text);
        
        $text = $parse->BB_Parse($text, false);
        
//        $text = preg_replace('#\[.+?\]#si', "", $text);
        
        return $text;
    }
    
    private function setcookie($name, $value, $expires = 0, $use_domain = true)
    {
        static $phpversion = 0;
        
        if (!$phpversion)
        {
            $phpversion = phpversion();
        }
        
        if($expires)
        {
            $expires = time() + ($expires * 86400);
        }
        else
        {
            $expires = FALSE;
        }
        
        if (!$use_domain)
        {
            setcookie( $name, $value, $expires, "/");
        }
        else if(version_compare($phpversion, 5.2, "<"))
        {
            setcookie( $name, $value, $expires, "/", '.' . $this->cookie_domain . "; HttpOnly" );
        }
        else
        {
            setcookie( $name, $value, $expires, "/", '.' . $this->cookie_domain, NULL, TRUE );
        }
    }
    
    public function __destruct() 
    {
    	if ($this->connected)
    	{
    	    $this->_db_disconnect();
    	}
    	
    	unset($this->db);
    }
}

?>