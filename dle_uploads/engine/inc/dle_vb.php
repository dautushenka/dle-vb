<?php


if(!defined('DATALIFEENGINE'))
{
  die("Hacking attempt!");
}
$licence = /*lic*/'dom2.tv'/*/lic*/;

if (!function_exists('array_map_recursive'))
{
    function array_map_recursive($function,$data)
        {
            foreach ($data as $i=>$item)
                    $data[$i]=is_array($item) ? array_map_recursive($function,$item) : $function($item);
            return $data ;
        }
}
if (get_magic_quotes_gpc())
{
    $_GET=array_map_recursive('stripslashes',$_GET);
    $_POST=array_map_recursive('stripslashes',$_POST);
    $_COOKIE=array_map_recursive('stripslashes',$_COOKIE);
    $_REQUEST=array_map_recursive('stripslashes',$_REQUEST);
}

$save_con = $_POST['save_con'];

if (empty($dle_vb_conf) && file_exists(ENGINE_DIR.'/data/dle_vb_conf.php'))
{
    require_once (ENGINE_DIR.'/data/dle_vb_conf.php');
}
else if (empty($dle_vb_conf))
{
    $dle_vb_conf = array() ;
}

if ($save_con)
{
    $dle_vb_conf = array_merge($dle_vb_conf, $save_con);
}

require_once(ROOT_DIR.'/language/'.$config['langs'].'/dle_vb.lng');


function DisplayCategories($parentid = 0, $sublevelmarker = '')
{
    global $db, $config, $link, $dle_vb_conf;
    
    if ($parentid != 0)
    {
        $sublevelmarker .= '--';
    }
    
    $getcategories = $db->query("SELECT * FROM " . PREFIX . "_category WHERE parentid = '$parentid' ORDER BY posi ASC");
    
    while ($row = $db->get_row($getcategories))
    {
        
        $link .= "<tr><td style=\"padding-right:3px;\">" . $sublevelmarker . "<a class=\"list\" href=\"{$config['http_home_url']}index.php?do=cat&category=" . $row['alt_name'] . "\" target=\"_blank\">" . stripslashes($row['name']) . "</a></td><td><input class=edit type=text style=\"text-align: center;\" name='save_con[vb_link_forumid][{$row['id']}]' value='{$dle_vb_conf['vb_link_forumid'][$row['id']]}' size=10></td></tr><tr><td background=\"engine/skins/images/mline.gif\" height=1 colspan=2></td></tr>";

       DisplayCategories($row['id'], $sublevelmarker);
  }
  
}
$link ="<table><tr><td>{$dle_vb_lang['category']}</td><td>{$dle_vb_lang['forums']}</td></tr>";
DisplayCategories();
$link .= "</table>";


$settings_array = array(
                        'block_last' => array (
                                       array(
                                            "title" => $dle_vb_lang['allow_forum_block'], 
                                            "descr" => $dle_vb_lang['allow_forum_block_desc'], 
                                            "setting" => YesNo('vb_lastpost_onoff'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['count_post'], 
                                            "descr" => $dle_vb_lang['count_post_desc'], 
                                            "setting" => Input('vb_block_new_count_post'), 
                                            "regexp" => '#^[0-9]+$#', 
                                            "name" => 'vb_block_new_count_post'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['leght_name'], 
                                            "descr" => $dle_vb_lang['leght_name_desc'], 
                                            "setting" => Input('vb_block_new_leght_name'), 
                                            "regexp" => '#^[0-9]*$#', 
                                            "name" => 'vb_block_new_leght_name'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['cache_time'], 
                                            "descr" => $dle_vb_lang['cache_time_desc'], 
                                            "setting" => Input('vb_block_new_cache_time'), 
                                            "regexp" => '#^[0-9]*$#', 
                                            "name" => 'vb_block_new_cache_time'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['bad_forum_for_block'], 
                                            "descr" => $dle_vb_lang['bad_forum_for_block_desc'], 
                                            "setting" => Input('vb_block_new_badf'), 
                                            "regexp" => '#^[0-9,]*$#', 
                                            "name" => 'vb_block_new_badf'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['good_forum_for_block'], 
                                            "descr" => $dle_vb_lang['good_forum_for_block_desc'], 
                                            "setting" => Input('vb_block_new_goodf'), 
                                            "regexp" => '#^[0-9,]*$#', 
                                            "name" => 'vb_block_new_goodf'
                                             )
                                    ),
                            'block_birthday' => array (
                                       array(
                                            "title" => $dle_vb_lang['allow_birthday_block'], 
                                            "descr" => $dle_vb_lang['allow_birthday_block_desc'], 
                                            "setting" => YesNo('vb_birthday_onoff'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['cache_time'], 
                                            "descr" => $dle_vb_lang['cache_time_desc'], 
                                            "setting" => Input('vb_block_birthday_cache_time'), 
                                            "regexp" => '#^[0-9]*$#', 
                                            "name" => 'vb_block_birthday_cache_time'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['count_birthday'], 
                                            "descr" => $dle_vb_lang['count_birthday_desc'], 
                                            "setting" => Input('count_birthday'), 
                                            "regexp" => '#^[0-9]+$#', 
                                            "name" => 'count_birthday'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['no_user_birthday'], 
                                            "descr" => $dle_vb_lang['no_user_birthday_desc'], 
                                            "setting" => Input('no_user_birthday', 35), 
                                            "regexp" => false, 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['spacer'], 
                                            "descr" => $dle_vb_lang['spacer_desc'], 
                                            "setting" => Input('vb_block_birthday_spacer'), 
                                            "regexp" => false, 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['birthday_block'], 
                                            "descr" => $dle_vb_lang['birthday_block_desc'], 
                                            "setting" => TextArea('birthday_block'), 
                                            "regexp" => '#^.+$#si', 
                                            "name" => 'birthday_block'
                                             )
                                    ),
                            'block_online' => array (
                                       array(
                                            "title" => $dle_vb_lang['allow_online_block'], 
                                            "descr" => $dle_vb_lang['allow_online_block_desc'], 
                                            "setting" => YesNo('vb_online_onoff'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['cache_time'], 
                                            "descr" => $dle_vb_lang['cache_time_desc'], 
                                            "setting" => Input('vb_block_online_cache_time'), 
                                            "regexp" => '#^[0-9]*$#', 
                                            "name" => 'vb_block_online_cache_time'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['separator'], 
                                            "descr" => $dle_vb_lang['separator_desc'], 
                                            "setting" => Input('separator'), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['vb_block_online_user_link_forum'], 
                                            "descr" => $dle_vb_lang['vb_block_online_user_link_forum_desc'], 
                                            "setting" => YesNo('vb_block_online_user_link_forum'), 
                                            "regexp" => false 
                                             )
                                    ),
                            'links' => array (
                                       array(
                                            "title" => $dle_vb_lang['goforum'], 
                                            "descr" => $dle_vb_lang['goforum_desc'], 
                                            "setting" => YesNo('vb_goforum'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['link_title'], 
                                            "descr" => $dle_vb_lang['link_title_desc'], 
                                            "setting" => makeDropDown(array("old"=>$dle_vb_lang['old_title'],
                                                                            "title"=>$dle_vb_lang['title']), 
                                                                      "save_con[link_title]", 
                                                                      "{$dle_vb_conf['link_title']}"), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['link_text'], 
                                            "descr" => $dle_vb_lang['link_text_desc'], 
                                            "setting" => makeDropDown(array("full"=>$dle_vb_lang['full_text'],
                                                                            "short"=>$dle_vb_lang['short_text'],
                                                                            "old"=>$dle_vb_lang['old_text']),
                                                                      "save_con[link_text]",
                                                                      "{$dle_vb_conf['link_text']}"), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['vb_link_show_no_register'], 
                                            "descr" => $dle_vb_lang['vb_link_show_no_register_desc'], 
                                            "setting" => YesNo('vb_link_show_no_register'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['link_on_news'], 
                                            "descr" => $dle_vb_lang['link_on_news_desc'], 
                                            "setting" => YesNo('link_on_news'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['show_count'], 
                                            "descr" => $dle_vb_lang['show_count_desc'], 
                                            "setting" => YesNo('vb_link_show_count'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['show_count_full'], 
                                            "descr" => $dle_vb_lang['show_count_full_desc'], 
                                            "setting" => YesNo('vb_link_show_count_full'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['link_user'], 
                                            "descr" => $dle_vb_lang['link_user_desc'], 
                                            "setting" => makeDropDown(array("old"=>$dle_vb_lang['old_user'],
                                                                            "author"=>$dle_vb_lang['author'],
                                                                            "cur_user"=>$dle_vb_lang['cur_user']),
                                                                      "save_con[link_user]",
                                                                      "{$dle_vb_conf['link_user']}"), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['name_post_on_forum'], 
                                            "descr" => $dle_vb_lang['name_post_on_forum_desc'], 
                                            "setting" => TextArea('vb_link_name_post_on_forum'), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['text_post_on_forum'], 
                                            "descr" => $dle_vb_lang['text_post_on_forum_desc'], 
                                            "setting" => TextArea('text_post_on_forum'), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['link_on_forum'], 
                                            "descr" => $dle_vb_lang['link_on_forum_desc'], 
                                            "setting" => TextArea('vb_link_link_on_forum'), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['postusername'], 
                                            "descr" => $dle_vb_lang['postusername_desc'], 
                                            "setting" => Input('postusername', 35), 
                                            "regexp" => '#^.+$#i',
                                            "name" => 'postusername'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['postuserid'], 
                                            "descr" => $dle_vb_lang['postuserid_desc'], 
                                            "setting" => Input('postuserid'), 
                                            "regexp" => '#^[0-9]+$#',
                                            "name" => 'postuserid'
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['forumid'], 
                                            "descr" => $dle_vb_lang['forumid_desc'], 
                                            "setting" => $link, 
                                            "regexp" => false
                                             )
                                    ),
                            'settings' => array (
                                       array(
                                            "title" => $dle_vb_lang['vb_content_charset'], 
                                            "descr" => $dle_vb_lang['vb_content_charset_desc'], 
                                            "setting" => Input('vb_content_charset'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['allow_module'], 
                                            "descr" => $dle_vb_lang['allow_module_desc'], 
                                            "setting" => YesNo('vb_onoff'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['allow_reg'], 
                                            "descr" => $dle_vb_lang['allow_reg_desc'], 
                                            "setting" => YesNo('vb_reg'),
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['allow_profile'], 
                                            "descr" => $dle_vb_lang['allow_profile_desc'], 
                                            "setting" => YesNo('vb_profile'),
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['allow_lostpass'], 
                                            "descr" => $dle_vb_lang['allow_lostpass_desc'], 
                                            "setting" => YesNo('vb_lost'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['allow_pm'], 
                                            "descr" => $dle_vb_lang['allow_pm_desc'], 
                                            "setting" => YesNo('vb_pm'), 
                                            "regexp" => false
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['allow_login'], 
                                            "descr" => $dle_vb_lang['allow_login_desc'], 
                                            "setting" => YesNo('vb_login'), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['allow_logout'], 
                                            "descr" => $dle_vb_lang['allow_logout_desc'], 
                                            "setting" => YesNo('vb_logout'), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['allow_admin'], 
                                            "descr" => $dle_vb_lang['allow_admin_desc'], 
                                            "setting" => YesNo('vb_admin'), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['vb_login_create_account'], 
                                            "descr" => $dle_vb_lang['vb_login_create_account_desc'], 
                                            "setting" => YesNo('vb_login_create_account'), 
                                            "regexp" => false 
                                             ),
                                       array(
                                            "title" => $dle_vb_lang['vb_login_create_dle_account'], 
                                            "descr" => $dle_vb_lang['vb_login_create_dle_account_desc'], 
                                            "setting" => YesNo('vb_login_create_dle_account'), 
                                            "regexp" => false 
                                             )
                                    )
                        );


if (defined('INSTALL'))
{
    return false;
}

require ENGINE_DIR . '/modules/dle_vs_vb.php';

class vBIntegration_admin extends vBIntegration
{
    public $vBfields = array();
    
    public $vBGroups = array();
    
    public function __construct(db &$db)
    {
        parent::__construct($db);
        
        $this->_db_connect();
        
        $this->_initvBField();
        $this->_initvBGroups();
        
        $this->_db_disconnect();
    }
    
    private function _initvBField()
    {
        if ($this->vBfields)
        {
            return ;
        }
        
        foreach ($this->user_vb_field as $alies_id => $field_name)
        {
            $this->vBfields[$alies_id] = $this->lang['vb_fields_' . $field_name];
        }
        
        $this->db->query("SELECT r.text, f.profilefieldid FROM " . VB_PREFIX . "profilefield AS f
                                    LEFT JOIN " . VB_PREFIX . "phrase AS r
                                    ON r.varname = CONCAT('field', f.profilefieldid, '_title')
        ");
        
        while ($row = $this->db->get_row())
        {
            if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
            {
                $row['text'] = iconv(VB_CHARSET, DLE_CHARSET, $row['text']);
            }
            $this->vBfields[$row['profilefieldid']] = $row['text'];
        }
    }
    
    public function _initvBGroups()
    {
        $this->db->query("SELECT usergroupid, title FROM " . VB_PREFIX . "usergroup");
        
        while ($row = $this->db->get_row())
        {
            if (VB_CHARSET && VB_CHARSET != DLE_CHARSET)
            {
                $row['title'] = iconv(VB_CHARSET, DLE_CHARSET, $row['title']);
            }
            $this->vBGroups[$row['usergroupid']] = $row['title'];
        }
    
    }
    
    public function GetFieldSettings()
    {
        if (!file_exists(ENGINE_DIR . "/data/xprofile.txt"))
        {
            return '';
        }
        
        $fields_array = file(ENGINE_DIR . "/data/xprofile.txt");
        $fields_array[] = 'info|' . $this->lang['extra_minfo'];
        $fields_array[] = 'land|' . $this->lang['opt_land'];
        $fields_array[] = 'fullname|' . $this->lang['opt_fullname'];
        
        $fields = array();
        
        foreach ($fields_array as $filed)
        {
            $part = explode("|", $filed);
            
            $fields[] = "<td align='right'>" . $part[1] . "</td><td style='padding:2px'><input class='edit' type='text' style='text-align:center' size=7 name='save_con[fields][{$part[0]}]' value='{$this->config['fields'][$part[0]]}' /></td>";
        }
        
        return "<table>
                <tr><td><b>DLE field</b></td><td><b>field ID vB</b></td></tr>
                <tr>" . implode("</tr><tr>" , $fields) . "</tr></table>";
    }
    
    public function GetGroupsSettings()
    {
        $this->db->query('SELECT id, group_name FROM ' . USERPREFIX . '_usergroups ORDER BY id');
        
        while ($row = $this->db->get_row())
        {
            $fields[] = "<td align='right'>" . $row['group_name'] . "</td><td style='padding:2px'><input class='edit' type='text' style='text-align:center' size=7 name='save_con[groups][{$row['id']}]' value='{$this->config['groups'][$row['id']]}' /></td>";
        }
        
        return "<table>
                <tr><td><b>DLE groups</b></td><td><b>GroupID vB</b></td></tr>
                <tr>" . implode("</tr><tr>" , $fields) . "</tr></table>";
    }
    
    public function GetvBFields()
    {
        $this->_initvBField();
        
        if (!$this->vBfields)
        {
            return '';
        }
        
        $table = "<table><tr><td style='padding:2px;'><b>ID</b></td><td><b>Name</b></td></tr>";
        
        foreach ($this->vBfields as $id => $name)
        {
        	$table .= "<tr><td align='center'><b>$id</b></td><td>$name</td></tr>";
        }
        
        return $table . "</table>";
    }
    
    public function GetvBGroups()
    {
        if (!$this->vBGroups)
        {
            return '';
        }
        
        $table = "<table><tr><td style='padding:2px;'><b>ID</b></td><td><b>Name</b></td></tr>";
        
        foreach ($this->vBGroups as $id => $name)
        {
        	$table .= "<tr><td align='center'><b>$id</b></td><td>$name</td></tr>";
        }
        
        return $table . "</table>";
    }
    
    public function __destruct()
    {
        
    }
}

$vb = new vBIntegration_admin($db);


$settings_array['settings'][] = array(
                                      "title" => $dle_vb_lang['vb_fields'], 
                                      "descr" => $dle_vb_lang['vb_fields_desc'], 
                                      "setting" => $vb->GetFieldSettings() . $vb->GetvBFields(), 
                                      "regexp" => false
                                        );

$settings_array['settings'][] = array(
                                      "title" => $dle_vb_lang['vb_groups'], 
                                      "descr" => $dle_vb_lang['vb_groups_desc'], 
                                      "setting" => $vb->GetGroupsSettings() . $vb->GetvBGroups(), 
                                      "regexp" => false
                                        );


if ($config['version_id'] < 7.5)
{
    if($member_db[1] != 1)
    {
        dle_vb_msg("error", $lang['opt_denied'], $lang['opt_denied']);
    }
}
else 
{
    if ($member_id['user_group'] != 1)
    {
        dle_vb_msg("error", $lang['opt_denied'], $lang['opt_denied']);
    }
}


if (!preg_match("#" . $licence . "#i", $_SERVER['HTTP_HOST']) && 
    !preg_match('#localhost#', $_SERVER['HTTP_HOST']) &&
    strpos($_SERVER['HTTP_HOST'], $_SERVER['SERVER_ADDR']) === false) 
{

	dle_vb_msg("error", $dle_vb_lang['error_lic'], $dle_vb_lang['lic_text']);
	exit; 
}
 
function makeDropDown($options, $name, $selected)
{
	$output = "<select name=\"$name\">\r\n";
	
    foreach($options as $value=>$description)
    {
    	$output .= "<option value=\"$value\"";
        if($selected == $value)
        {
            $output .= " selected ";
        }
        $output .= ">$description</option>\n";
	}
	
    $output .= "</select>";
    
    return $output;
}

function YesNo($var)
{
    global $dle_vb_conf, $dle_vb_lang;
    
    return makeDropDown(array("1"=>$dle_vb_lang['yes'], "0"=>$dle_vb_lang['no']), "save_con[$var]", $dle_vb_conf[$var]);
}

function Input($var_name, $size = '10')
{
    global $dle_vb_conf;
    
    return "<input class=edit type=text style=\"text-align: center;\" name='save_con[$var_name]' value='{$dle_vb_conf[$var_name]}' size=$size>";
}

function TextArea($var_name, $size = array(50, 4))
{
    global $dle_vb_conf;
    
    return "<textarea cols=\"{$size[0]}\" rows=\"{$size[1]}\" name='save_con[$var_name]'>" . stripslashes($dle_vb_conf[$var_name]) . "</textarea>";
}

function showRow($title="", $description="", $field="")
{
    echo"<tr>
    <td style=\"padding:4px\" class=\"option\">
    <b>$title</b><br /><span class=small>$description</span>
    <td width=394 align=middle >
    $field
    </tr><tr><td background=\"engine/skins/images/mline.gif\" height=1 colspan=2></td></tr>";
    $bg = ""; $i++;
}
    
    
function echomenu ($image, $header_text, $p = 0)
{
    global $dle_vb_lang;

    echoheader ($image, $header_text, $p);
	
echo <<<HTML
<script  language='JavaScript' type="text/javascript">
function showmenu(obj)
{ 
	document.getElementById('settings').style.display = "none";
	document.getElementById('block_last').style.display = "none";
	document.getElementById('block_birthday').style.display = "none";
	document.getElementById('block_online').style.display = "none";
	document.getElementById('stats').style.display = "none";
	document.getElementById('links').style.display = "none";
	document.getElementById(obj).style.display=''; 
} 
</script>
<div style="padding-top:5px;padding-bottom:2px;">
<table width="100%" style="text-align:center">
    <tr>
        <td width="4"><img src="engine/skins/images/tl_lo.gif" width="4" height="4" border="0"></td>
        <td background="engine/skins/images/tl_oo.gif"><img src="engine/skins/images/tl_oo.gif" width="1" height="4" border="0"></td>
        <td width="6"><img src="engine/skins/images/tl_ro.gif" width="6" height="4" border="0"></td>
    </tr>
    <tr>
        <td background="engine/skins/images/tl_lb.gif"><img src="engine/skins/images/tl_lb.gif" width="4" height="1" border="0"></td>
        <td style="padding:5px;" bgcolor="#FFFFFF">
<table width="100%">
	<tr>
		<td style="text-align:center"><a href="javascript:showmenu('block_last');" title='{$dle_vb_lang['block_new']}'><img src="engine/skins/images/block_new.jpg" border="0" /></a></td>
		<td style="text-align:center"><a href="javascript:showmenu('block_birthday');" title='{$dle_vb_lang['block_birth']}'><img src="engine/skins/images/block_birth.jpg" border="0" /></a></td>
		<td style="text-align:center"><a href="javascript:showmenu('block_online');" title='{$dle_vb_lang['block_online']}'><img src="engine/skins/images/block_online.jpg" border="0" /></a></td>
		<td style="text-align:center"><a href="javascript:showmenu('links');" title='{$dle_vb_lang['link']}'><img src="engine/skins/images/link.jpg" border="0" /></td>
		<td style="text-align:center"><a class=main href="javascript:showmenu('settings');" title='{$dle_vb_lang['settings']}' ><img src="engine/skins/images/settings.jpg" border="0" /></td>
	</tr>
</table>
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


function footer_dle_vb () {

	global $dle_vb_conf;
	
	$year = date("Y");
	
	echo <<<HTML
<table width="100%">
    <tr>
        <td bgcolor="#EFEFEF" height="29" style="padding-left:10px; text-align:center"><div class="navigation">Copyright © 2007 - $year created by <a href="http://kaliostro.net/" style="text-decoration:underline;color:green">kaliostro</a></div></td>
    </tr>
</table>
HTML;
	
	echofooter();
}


function dle_vb_msg($type, $title, $text, $back=FALSE)
{
    global $lang, $dle_vb_lang;

  if($back){
        $back = "<br /><br> <a class=main href=\"$back\">$lang[func_msg]</a>";
  }

  echoheader ($image, $header_text);

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
<table width="100%">
    <tr>
        <td bgcolor="#EFEFEF" height="29" style="padding-left:10px;"><div class="navigation">{$title}</div></td>
    </tr>
</table>
<div class="unterline"></div>
<table width="100%">
    <tr>
        <td height="100" align="center">{$text} {$back}</td>
    </tr>
</table>
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
    
    footer_dle_vb();
    exit();
}


if ($action == "save")
{
    $errors = array();
    foreach ($settings_array as $settings)
    {
        foreach ($settings as $setting)
        {
            if ($setting['regexp'])
            {
                if (is_array($save_con[$setting['name']]))
                {
                    foreach ($save_con[$setting['name']] as $value)
                    {
                        if (!preg_match($setting['regexp'], $value))
                            $errors[] = $setting['title'];
                    }
                }
                elseif (!preg_match($setting['regexp'], $save_con[$setting['name']]))
                    $errors[] = $setting['title'];
            }
        }
    }
    
    if (file_exists(ENGINE_DIR.'/data/dle_vb_conf.php') && !is_writable(ENGINE_DIR.'/data/dle_vb_conf.php'))
    {
        $errors[] = $dle_vb_lang['settings_file_not_writable'];
        
    }
    elseif (!file_exists(ENGINE_DIR.'/data/dle_vb_conf.php') && !is_writable(ENGINE_DIR.'/data'))
    {
        $errors[] = $dle_vb_lang['settings_dir_not_writable'];
        
    }
    
    if (!$errors)
    {
        $save_con['version_id'] = "2.1.0";
    
        $handler = fopen(ENGINE_DIR.'/data/dle_vb_conf.php', "w");
        fwrite($handler, "<?PHP \n\n//DLE + vBulletin Configurations\n\n\$dle_vb_conf = array (\n\n");
        
        function save_conf($save_con, $array=false)
        {
            global $handler, $find, $replace;
            
            foreach($save_con as $name => $value)
            {
                if (is_array($value))
                {
                    fwrite($handler, "'{$name}' => array (\n\n");
                    save_conf($value, true);
                }
                else
                {
                    $value = str_replace('"', '\"', $value);
                    fwrite($handler, "'{$name}' => \"" . $value . "\",\n\n");
                }
            }
            
            if ($array)
            {
                fwrite($handler, "),\n\n");
            }
         }
        
        save_conf($save_con);
        fwrite($handler, ");\n\n?>");
        fclose($handler);
        dle_vb_msg("info", $lang['opt_sysok'], $lang['opt_sysok_1'], $PHP_SELF."?mod=dle_vb");
    }
}
	
echomenu("options", $dle_vb_lang['settings'], '');


if ($errors)
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
<table width="100%"><tr><td>
HTML;
    
    echo "  <font color=\"red\" >" . $dle_vb_lang['setting_error'] . "</font><ol>";
    foreach ($errors as $error)
    {
        echo "<li>" . $error . "</li>";
    }
    echo "</ol>";
    
echo <<<HTML
</td>
    </tr>
</table>
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
	
echo <<<HTML
<form action="" method="post" name="form">
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
<table width="100%">
HTML;


	
echo <<<HTML
<tr id="stats" style=''><td>
<table width="100%">
    <tr>
        <td bgcolor="#EFEFEF" height="29" style="padding-left:10px;"><div class="navigation">{$dle_vb_lang['stat_all']}</div></td>
    </tr>
</table>
<div class="unterline"></div><table width="100%">
    <tr>
        <td width="265" style="padding:2px;">{$dle_vb_lang['version']}</td>
        <td>{$dle_vb_conf['version_id']}</td>
    </tr>
    <tr>
        <td style="padding:2px;">{$dle_vb_lang['module_reg']}</td>
        <td><b>{$licence}</b></td>
    </tr>
</table></td></tr>
HTML;
	

foreach ($settings_array as $table=>$settings)
{
    echo "<tr id=\"$table\" style='display:none'><td>";
    echo <<<HTML
<table width="100%">
    <tr>
        <td bgcolor="#EFEFEF" height="29" style="padding-left:10px;"><div class="navigation">{$dle_vb_lang[$table . '_title']}</div></td>
    </tr>
</table>
<div class="unterline"></div><table width="100%">
HTML;
    foreach ($settings as $setting)
    {
        showRow($setting['title'], $setting['descr'], $setting['setting']);
    }
    echo "</table>";
    //$tpl->CloseSubtable();
    echo "</td></tr>";
}

echo <<<HTML
    <tr>
        <td style="padding-top:10px; padding-bottom:10px;padding-right:10px;">
    <input type=hidden name=action value=save><input type="submit" class="buttons" value="{$lang['user_save']}"></td>
    </tr>
</table>
</td>
        <td background="engine/skins/images/tl_rb.gif"><img src="engine/skins/images/tl_rb.gif" width="6" height="1" border="0"></td>
    </tr>
    <tr>
        <td><img src="engine/skins/images/tl_lu.gif" width="4" height="6" border="0"></td>
        <td background="engine/skins/images/tl_ub.gif"><img src="engine/skins/images/tl_ub.gif" width="1" height="6" border="0"></td>
        <td><img src="engine/skins/images/tl_ru.gif" width="6" height="6" border="0"></td>
    </tr>
</table>
</div></form>
HTML;

footer_dle_vb();

?>
