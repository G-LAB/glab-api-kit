<?php

class Profile extends CI_Model
{

  private $profiles;

  public function __construct ()
  {
    $this->load->helper('glab_validation');
    $this->load->helper('glib_validation');
    $this->load->helper('glib_number');
    $this->load->helper('glib_string');
    $this->load->library('User_Notice');
    $this->load->model('api');
  }

  public function get($str)
  {

    /*
      Identify ID Format and Process
    */

    $pid = false;

    // EID
    if (ctype_digit($str) === true && strlen($str) <= 2) {
      $q = $this->db->select('acctnum')->where('eid',$str);
      $r = $q->get('entities')->row_array();
      $pid = element('acctnum',$r);
    }
    // Account Number as Integer
    elseif (ctype_digit($str) === true  && strlen($str) >= 7)
    {
      $pid = $str;
    }
    // Account Number as Hexadecimal
    elseif (is_hex($str) === true)
    {
      $pid = hexdec($str);
    }
    // Account Number as Fromatted String
    elseif (is_account_number($str))
    {
      $pid = preg_replace('/\D/','', $str);
    }
    // Email Address
    elseif (is_email($str))
    {
      $q = $this->api->request('get', 'profile/pid', array('email'=>$str));

      if (isset($q->pid))
      {
        $pid = $q->pid;
      }
      else
      {
        $pid = false;
      }
    }
    // Telephone Number
    elseif (is_tel($str))
    {
      $q = $this->api->request('get', 'profile/pid',array('tel'=>$str));

      if (isset($q->pid))
      {
        $pid = $q->pid;
      }
      else
      {
        $pid = false;
      }
    }

    // Create Profile in Class If Needed
    if (isset($this->profiles[$pid]) !== true)
    {
      $this->profiles[$pid] = new Profile_Base($pid);
    }

    // Store Reference
    $profile = $this->profiles[$pid];

    // Return Reference to Profile in Class
    return $profile;

  }

  public function current()
  {
    $pid = $this->acl->get_pid();
    return $this->get($pid);
  }

  public function add($name_first,$name_last=false)
  {
    $pid = substr(hexdec(uniqid()),-16);

    $q = $this->db->set('pid',$pid);

    if (empty($name_last) === true)
    {
      $q->set('is_company',true);
      $q->set('name_company',$name_first);
    }
    else
    {
      $q->set('name_first',$name_first);
      $q->set('name_last',$name_last);
    }

    $q->insert('profiles');

    return $this->get($pid);
  }

}

class Profile_Base
{

  private $pid;
  private $dead = false;
  private $data = false;

  public $address;
  public $delegate;
  public $email;
  public $manager;
  public $meta;
  public $name;
  public $security;
  public $tel;

  public function __construct($pid)
  {
    $this->pid = $pid;

    $this->address = new Profile_Address($this);
    $this->delegate = new Profile_Delegate($this);
    $this->email = new Profile_Email($this);
    $this->manager = new Profile_Manager($this);
    $this->meta = new Profile_Meta($this);
    $this->name = new Profile_Name($this);
    $this->security = new Profile_Security($this);
    $this->tel = new Profile_Tel($this);
  }

  public function __set($key, $value)
  {
    $CI =& get_instance();

    $this->_get_data();

    $CI->db->set($key,$value)->where('pid', $this->pid)->update('profiles');

    $this->data[$key] = $value;
  }

  public function __get($key)
  {
    // Check If Data Available
    if (is_array($this->data) !== true)
    {
      $this->_get_data();
    }

    // Check Again for Data Array
    if ($this->data !== false)
    {
      // Check If Requested Data Exists
      if ($key == 'pid_hex')
      {
        return dechex($this->pid);
      }
      else
      {
        return element($key, $this->data);
      }
    }
  }

  public function __isset($key)
  {
    // Check If Data Available
    if (is_array($this->data) !== true)
    {
      $this->_get_data();
    }

    return isset($this->data[$key]);
  }

  public function __toString()
  {
    // Return full name when class is echoed.
    return (string) $this->name;
  }

  public function exists()
  {
    // Check If Data Available
    if (is_array($this->data) !== true)
    {
      $this->_get_data();
    }

    if ($this->data !== false)
    {
      return true;
    }
    else
    {
      return false;
    }
  }

  public function is_company()
  {
    return (bool) $this->__get('is_company');
  }

  public function is_employee()
  {
    return (bool) $this->meta->is_employee;
  }

  private function _get_data()
  {
    // Mark known dead pids
    if ($this->pid == false)
    {
      $this->dead = true;
      return false;
    }

    if ($this->dead !== true)
    {
      $CI =& get_instance();
      $r = $CI->api->request('get','profile/base',array('pid'=>$this->pid));

      if (count($r) > 0)
      {
        $this->data = $r;
        return true;
      }
      else
      {
        $this->dead = true;
        return false;
      }
    }
  }

}

abstract class Profile_Prototype
{
  protected $base;
  protected $data = array();
  protected $fields;
  protected $table_name;

  public function __construct($base)
  {
    $this->base = $base;
  }

  public function __get($key)
  {
    $CI =& get_instance();
    $CI->load->helper('array');
    return element($key,$this->data);
  }

  public function __set($key,$value)
  {
    $this->_get_data();

    if (in_array($key,$this->fields) === true)
    {
      if ($this->validate($key,$value))
      {
        $this->data[$key] = $value;
      }
    }
    else
    {
      show_error('Key "'.$key.'" is not a valid column in '.$this->table_name);
    }
  }

  private function _get_data()
  {
    if (empty($this->fields) === true)
    {
      $CI =& get_instance();
      $this->fields = $CI->api->request('get', 'profile/prototype_fields', array('table'=>$this->table_name));
    }
    return true;
  }

  public function save()
  {
    if ($this->callback())
    {
      $this->data['pid'] = $this->base->pid;

      $CI =& get_instance();
      $CI->db->insert($this->table_name,$this->data); // @todo INSERT IGNORE

      if ($CI->db->affected_rows() > 0)
      {
        return true;
      }
      else
      {
        User_Notice::error('A database error prevented the profile record from being saved.');
      }
    }

    return false;
  }

  protected function callback()
  {
    return true;
  }

  abstract protected function validate($key,$value);
}

/**
 * COMPONENT CLASSES
 */


class Profile_Address
{
  private $base;
  private $data = array();

  public function __construct($base)
  {
    $this->base = $base;
  }

  private function _get_data()
  {
    $CI =& get_instance();

    $r = $CI->api->request('get', 'profile/addresses', array('pid'=>$this->base->pid));

    if (count($r) > 0)
    {
      foreach ($r as $addr)
      {
        $data[] = new Profile_Address_Entry($this->base,$addr);
      }
      $this->data = $data;
      return true;
    }

  }

  public function fetch_array()
  {
    $this->_get_data();

    return $this->data;
  }

  public function prototype()
  {
    $prototype = new Profile_Address_Prototype($this->base);
    return $prototype;
  }
}

class Profile_Address_Entry
{
  private $base;
  private $data = array();

  public function __construct($base,$data)
  {
    $this->base = $base;
    $this->data = $data;
  }

  public function formatted ()
  {
    $str = $this->__get('street_address_1')."\n";
    $str.= $this->__get('street_address_2')."\n";
    $str.= $this->__get('locality').', '.$this->__get('region').'  '.$this->__get('postal_code')."\n";
    $str.= $this->__get('country_id')."\n";

    return $str;
  }

  public function string ()
  {
    $str = $this->__get('street_address_1').' ';
    $str.= $this->__get('street_address_2').' ';
    $str.= $this->__get('locality').', '.$this->__get('region').'  '.$this->__get('postal_code').' ';
    $str.= $this->__get('country_id');

    return $str;
  }

  public function __get($key)
  {
    if ($key == 'country_name')
    {
      return 'country name';
    }
    else
    {
      return element($key,$this->data);
    }
  }

  public function __isset($key)
  {
    return isset($this->data[$key]);
  }

  public function __toString()
  {
    return (string) $this->formatted();
  }
}

class Profile_Address_Prototype extends Profile_Prototype
{
  protected $table_name = 'profiles_address';

  protected function callback()
  {
    if(isset($this->data['street_address_1'],$this->data['locality'],$this->data['region'],$this->data['postal_code'],$this->data['country_id']))
    {
      return true;
    }
    else
    {
      User_Notice::error('Street, City, State, Postal Code, and Country are required fields.');
    }
  }

  protected function validate($key,$value)
  {
    switch($key)
    {
        case 'type':
          return true;
        break;
        case 'label':
          return true;
        break;
        case 'street_address_1':
        case 'street_address_2':
            if(preg_match('/^[a-z\d\-\.\s#]*$/i',$value))
            {
              return true;
            }
            else
            {
              User_Notice::error('Street must contain alphanumeric characters, dashes, periods, and pound signs only.');
            }
        break;
        case 'locality':
          return true;
        break;
        case 'region':
          return true;
        break;
        case 'postal_code':
          return true;
        break;
        case 'country_id':
          return true;
        break;
        default:
            return false;
        break;
    }
  }
}

class Profile_Delegate
{
  private $base;
  private $data = array();

  public function __construct($base)
  {
    $this->base = $base;
  }

  public function _get_data()
  {
    $CI =& get_instance();

    $r = $CI->api->request('get', 'profile/delegates', array('pid'=>$this->base->pid));

    if (count($r) > 0)
    {
      foreach ($r as $delegate)
      {
        $data[] = new Profile_Delegate_Entry($this->base,$delegate);
      }
      $this->data = $data;
      return true;
    }

  }

  public function fetch_array()
  {
    $this->_get_data();

    return $this->data;
  }
}

class Profile_Delegate_Entry
{
  private $base;
  private $data = array();
  public $profile;

  public function __construct($base,$data)
  {
    $CI =& get_instance();
    $this->base = $base;
    $this->data = $data;
    $this->profile = $CI->profile->get(element('pid_p',$data));
  }

  public function __get($key)
  {
    $CI =& get_instance();
    $CI->load->helper('array');
    return element($key,$this->data);
  }

  public function __isset($key)
  {
    return isset($this->data[$key]);
  }
}

class Profile_Email
{
  private $base;
  private $data = array();

  public function __construct($base)
  {
    $this->base = $base;
  }

  public function __toString()
  {
    // Get Data On Every Call
    $this->_get_data();

    return (string) array_shift(array_values($this->data));
  }

  private function _get_data()
  {
    $CI =& get_instance();

    $r = $CI->api->request('get', 'profile/emails', array('pid'=>$this->base->pid));

    if (count($r) > 0)
    {
      foreach ($r as $email)
      {
        $data[] = new Profile_Email_Entry($this->base,$email);
      }
      $this->data = $data;
      return true;
    }

  }

  public function add ($str)
  {
    $CI =& get_instance();
    $CI->load->helper('glib_validation');

    if (is_email($str) === true)
    {
      $q = $CI->db
          ->set('pid',$this->base->pid)
          ->set('email',$str)
          ->insert('profiles_email');

      $this->_get_data();
    }
    else
    {
      User_Notice::error('Could not add email address "'.$str.'" to user, format is invalid.');
    }
  }

  public function fetch_array()
  {
    // Get Data On Every Call
    $this->_get_data();

    $data = $this->data;

    return $data;
  }

  public function set_primary($emid)
  {
    $CI =& get_instance();

    $CI->db->trans_start();

    $q = $CI->db
          ->set('is_primary',false)
          ->where('pid',$this->base->pid)
          ->update('profiles_email');

    $q = $CI->db
          ->set('is_primary',true)
          ->where('pid',$this->base->pid)
          ->where('emid',$emid)
          ->update('profiles_email');

    if ($CI->db->trans_complete())
    {
      // Set Other Emails' is_primary To False
      foreach ($this->data as &$email)
      {
        $email->is_primary = false;
      }
      $this->data[$emid]->is_primary = true;
      return true;
    }
  }

}

class Profile_Email_Entry
{
  private $base;
  private $data = array();

  public function __construct($base,$data)
  {
    $this->base = $base;
    $this->data = $data;
  }

  public function __set ($key,$value)
  {
    if ($key == 'is_primary')
    {
      $this->data['is_primary'] = $value;
    }
    else
    {
      show_error('Cannot perform set on fields other than "is_primary."');
    }
  }

  public function is_primary() {
    return (bool) $this->data['is_primary'];
  }

  public function set_primary()
  {
    return $this->base->email->set_primary($this->data['emid']);
  }

  public function delete()
  {
    return $CI->db
          ->set('is_primary',true)
          ->where('pid',$this->base->pid)
          ->where('email',$this->data['email'])
          ->delete('profiles_email');
  }

  public function __toString()
  {
    return $this->data['email'];
  }
}

class Profile_Manager
{
  private $base;
  private $data = array();

  public function __construct($base)
  {
    $this->base = $base;
  }

  public function _get_data()
  {
    $CI =& get_instance();

    $r = $CI->api->request('get', 'profile/managers', array('pid'=>$this->base->pid));

    if (count($r) > 0)
    {
      foreach ($r as $manager)
      {
        $data[] = new Profile_Manager_Entry($this->base,$manager);
      }
      $this->data = $data;
      return true;
    }

  }

  public function add($pid,$job_title=null)
  {
    $CI =& get_instance();

    if ($CI->profile->get($pid)->exists() === true)
    {
      $q = $CI->db
        ->set('pid_p',$this->base->pid)
        ->set('pid_c',$pid)
        ->set('job_title',$job_title)
        ->insert('profiles_manager'); // @todo INSERT IGNORE

      if ($CI->db->affected_rows() > 0)
      {
        return true;
      }
    }

    return false;
  }

  public function fetch_array()
  {
    $this->_get_data();

    return $this->data;
  }
}

class Profile_Manager_Entry
{
  private $base;
  private $data = array();
  public $profile;

  public function __construct($base,$data)
  {
    $CI =& get_instance();
    $this->base = $base;
    $this->data = $data;
    $this->profile = $CI->profile->get(element('pid_c',$data));
  }

  public function __get($key)
  {
    $CI =& get_instance();
    $CI->load->helper('array');
    return element($key,$this->data);
  }

  public function __isset($key)
  {
    return isset($this->data[$key]);
  }
}

class Profile_Meta
{
  private $base;
  private $data = array();
  private $meta_keys;

  function __construct($base)
  {
    $this->base = $base;

    $this->meta_keys = array(
      'is_employee',
      'time_format',
      'time_zone',
      'pbx_callback',
      'pbx_ext',
      'pbx_ext_mbox',
      'portal_acl_groups'
    );
  }

  public function __set($key, $value)
  {
    if (in_array($key, $this->meta_keys))
    {
      $CI =& get_instance();

      if (is_bool($value) === true)
      {
        $value = (int) $value;
      }

      $success = $CI->api->request('post', 'profile/meta', array(
        'pid'=>$this->base->pid,
        'key'=>$key,
        'value'=>$value
      ));

      if ($success == true)
      {
        return true;
      }
    }
    else
    {
      show_error("Meta key $key is invalid.");
    }
  }

  public function __get($key)
  {
    $CI =& get_instance();
    $CI->load->helper('array');
    $this->_get_data();

    if (element($key,$this->data) !== false)
    {
      return element($key,$this->data);
    }
    else
    {
      return $this->default_value($key);
    }
  }

  public function __isset($key)
  {
    $this->_get_data();
    return isset($this->data[$key]);
  }

  private function _get_data($refresh=false)
  {
    if (empty($this->data) === true OR $refresh === true)
    {
      $CI =& get_instance();
      $this->data = $CI->api->request('get', 'profile/meta', array('pid'=>$this->base->pid));
    }
  }

  private function default_value($key)
  {
    $default_values = array(
      'time_zone'=>'UM8',
      'time_format'=>12
    );

    return element($key,$default_values);
  }

}

class Profile_Name
{
  private $base;
  private $data = array();

  function __construct($base)
  {
    $this->base = $base;
  }

  public function __set($key, $value)
  {
    $writable = array('first','last','company');

    if (in_array($key, $writable))
    {
      return $this->base->__set('name_'.$key,$value);
    }
    else
    {
      show_error('Cannot write to variable "'.$key.'."');
    }
  }

  public function __get($key)
  {
    if ($key == 'full')
    {
      return $this->_get_name(true,false);
    }
    elseif ($key == 'friendly')
    {
      return $this->_get_name(false,false);
    }
    elseif ($key == 'full_posessive')
    {
      return $this->_get_name(true,true);
    }
    elseif ($key == 'friendly_posessive')
    {
      return $this->_get_name(false,true);
    }
    else
    {
      return $this->base->__get('name_'.$key);
    }
  }

  public function __toString()
  {
    return $this->__get('friendly');
  }

  public function __isset($key)
  {
    return $this->base->__isset('name_'.$key);
  }

  private function _get_name($full=false,$posessive=false)
  {
    // Is it a company?
    if ($this->base->is_company() === true) {
      $name = $this->base->__get('name_company');

    // No?  It must be a person.
    } else {
      $name = $this->base->__get('name_first');
      if ($full == true)
      {
        $name.= ' '.$this->base->__get('name_last');
      }
    }

    // Posessive or Common
    if ($posessive == true)
    {
      // Check if last character is an S
      if (strtolower(substr($name,-1)) == 's')
      {
        return trim($name)."'";
      }
      else
      {
        return trim($name)."'s";
      }
    }
    else
    {
      return $name;
    }
  }
}

class Profile_Security
{
  private $base;
  public $multifactor;

  public function __construct($base)
  {
    $this->base = $base;

    $this->multifactor->yubikey = new Profile_Security_Yubikey($base);
  }

  public function validate_password($str)
  {

  }

}

abstract class Profile_Security_Multifactor
{
  protected $base;

  public function __construct($base)
  {
    $this->base = $base;
  }

  abstract public function credentials();
  abstract public function register($arg1);
}

class Profile_Security_Multifactor_Credential
{
  public $base;
  public $data;
  private $table_name;

  public function __construct($base,$data,$table_name)
  {
    $this->base = &$base;
    $this->data = &$data;
    $this->table_name = $table_name;
  }

  public function __get($key)
  {
    return element($key,$this->data);
  }

  public function revoke()
  {
    $CI =& get_instance();

    $q = $CI->db  ->where($this->data)
            ->limit(1)
            ->delete($this->table_name);

    if ($CI->db->affected_rows() > 0)
    {
      User_Notice::success('Security credential revoked successfully.');
    }
    else
    {
      User_Notice::error('Security credential could not be revoked.');
    }
  }
}

class Profile_Security_Yubikey extends Profile_Security_Multifactor
{
  public function credentials($offset=0,$limit=10)
  {
    $CI =& get_instance();

    $q = $CI->db->  where('pid',$this->base->pid)
            ->limit($limit,$offset)
            ->get('auth_mf_yubikey')
            ->result_array();

    $data = array();

    foreach ($q as $credential)
    {
      $data[] = new Profile_Security_Multifactor_Credential($this->base,$credential,'auth_mf_yubikey');
    }

    return $data;
  }

  public function register($ykid)
  {
    $CI =& get_instance();

    $q = $CI->db  ->set('pid',$this->base->pid)
            ->set('ykid',$ykid)
            ->insert('auth_mf_yubikey'); // @todo Needs INSERT IGNORE when added by EllisLab

    if ($CI->db->affected_rows() > 0)
    {
      return true;
    }
    else
    {
      return false;
    }
  }
}

class Profile_Tel
{
  private $base;
  private $data = array();

  public function __construct($base)
  {
    $this->base = $base;
  }

  private function _get_data()
  {
    $CI =& get_instance();

    $r = $CI->api->request('get', 'profile/tels', array('pid'=>$this->base->pid));

    if (count($r) > 0)
    {
      foreach ($r as $tel)
      {
        $data[] = new Profile_Tel_Entry($this->base,$tel);
      }
      $this->data = $data;
      return true;
    }

  }

  public function fetch_array()
  {
    // Get Data On Every Call
    $this->_get_data();

    $data = $this->data;

    return $data;
  }

  public function prototype()
  {
    $prototype = new Profile_Tel_Prototype($this->base);
    return $prototype;
  }

}

class Profile_Tel_Entry
{
  private $base;
  private $data = array();

  public function __construct($base,$data)
  {
    $this->base = $base;
    $this->data = $data;
  }

  public function __set ($key,$value)
  {

  }

  public function __get ($key)
  {
    return element($key,$this->data);
  }

  public function __isset($key)
  {
    return isset($this->data[$key]);
  }

  public function delete()
  {
    return $CI->db
          ->where('pid',$this->base->pid)
          ->where('tel',$this->data['tel'])
          ->delete('profiles_tel');
  }

  public function __toString()
  {
    return $this->data['tel'];
  }
}

class Profile_Tel_Prototype extends Profile_Prototype
{
  protected $table_name = 'profiles_tel';

  protected function validate($key,$value)
  {
    switch($key)
    {
        case 'type':
          return true;
        break;
        case 'label':
          return true;
        break;
        case 'tel':
            if(is_tel($value))
            {
              return true;
            }
            else
            {
              User_Notice::error($value.' is not a valid US or international phone number.');
            }
        break;
        default:
            return false;
        break;
    }
  }
}

// End of File