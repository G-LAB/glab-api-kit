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
	}
	
	public function get($str) 
	{
		
		/* 
			Identify ID Format and Process
		*/
		
		$pid = false;
		
		// EID
		if (is_numeric($str) === true && strlen($str) <= 2) {
			$q = $this->db->select('acctnum')->where('eid',$str);
			$r = $q->get('entities')->row_array();
			$pid = element('acctnum',$r);
		}
		// Account Number as Integer
		elseif (is_numeric($str) === true  && strlen($str) >= 7)
		{
			$pid = $str;
		} 
		// Account Number as Fromatted String
		elseif (is_account_number($str))
		{
			$pid = preg_replace('/\D/','', $str);
		}
		// Account Number as Hexadecimal
		elseif (is_hex($str) === true)
		{
			$pid = hexdec($str);
		}
		elseif (is_email($str))
		{
			$q = $this->db	->select('pid')
							->limit(1)
							->where('email',$str)
							->get('profiles_email')
							->row();
			
			if (isset($q->pid))
			{
				$pid = $q->pid;
			}
			else
			{
				$pid = false;
			}
		}
		elseif (is_tel($str))
		{
			$q = $this->db	->select('pid')
							->limit(1)
							->where('tel',tel_dialtring($str))
							->get('profiles_tel')
							->row();
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
		if (!isset($this->profiles[$pid]) == true)
		{
			$this->profiles[$pid] = new Profile_Base($pid);
		}
		
		// Store Reference
		$profile = &$this->profiles[$pid];
		
		// Return Reference to Profile in Class
		return $profile;

	}
	
	public function current()
	{
		$pid = $this->acl->get_pid();
		return $this->get($pid);
	}
	
}

class Profile_Base 
{
	
	private $pid;
	private $dead = false;
	private $data = false;
	
	public $address;
	public $email;
	public $meta;
	public $name;
	public $tel;
	
	public function __construct($pid) 
	{
		$this->pid = $pid;
		
		$this->address = new Profile_Address(&$this);
		$this->email = new Profile_Email(&$this);
		$this->meta = new Profile_Meta(&$this);
		$this->name = new Profile_Name(&$this);
		$this->tel = new Profile_Tel(&$this);
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
		if (is_array($this->data) != true)
		{
			$this->_get_data();
		}
		
		// Check Again for Data Array
		if (is_array($this->data) == true)
		{
			// Prepare Key
			$key = trim($key);
			
			// Check If Requested Data Exists
			if (array_key_exists($key, $this->data)) 
			{
				return (string) $this->data[$key];
			} 
			elseif ($key == 'pid_hex')
			{
				return dechex($this->pid);
			}
			
			// If we haven't returned something, property is undefined.
			$trace = debug_backtrace();
			trigger_error(
				'Undefined property via __get(): ' . $key .
				' in ' . $trace[0]['file'] .
				' on line ' . $trace[0]['line'],
				E_USER_NOTICE);
		}
		
		return null;
	}
	
	public function __isset($key) 
	{
		// Check If Data Available
		if (is_array($this->data) != true)
		{
			$this->_get_data();
		}
		
		return isset($this->data[$key]);
	}
	
	public function __unset($key) 
	{
		trigger_error('Unsetting "'.$key.'" will only remove key from local object, not the database.', E_USER_NOTICE);
		unset($this->data[$key]);
	}
	
	public function __toString() 
	{
		// Return full name when class is echoed.
		return (string) $this->name;
	}
	
	public function exists()
	{
		// Check If Data Available
		if (is_array($this->data) != true)
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
	
	public function is_employee()
	{
		return (bool) $this->meta->is_employee;
	}
	
	private function _get_data()
	{
		if ($this->dead !== true)
		{
			$CI =& get_instance();
			$q = $CI->db->where('pid',$this->pid);
			$r = $q->limit(1)->get('profiles')->row_array();
			
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

/**
 * COMPONENT CLASSES
 */


class Profile_Address
{
	private $base;
	private $data;
	
	public function __construct($base)
	{
		$this->base = $base;
	}
}

class Profile_Address_Entry
{
	private $base;
	private $data;
	
	public function __construct($base)
	{
		$this->base = $base;
	}
}

class Profile_Email
{
	private $base;
	private $data;
	
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
		
		$CI->load->helpers('array');
		
		$q = $CI->db	->where('pid',$this->base->pid)
						->order_by('is_primary','DESC');
		$r = $q->limit(10)->get('profiles_email')->result_array();
		
		if (count($r) > 0)
		{
			foreach ($r as $email)
			{
				$data[] = new Profile_Email_Entry(&$this->base,&$email);
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
			trigger_error('Could not add email address "'.$str.'" to user, format is invalid.', E_USER_NOTICE);
		}
	}
	
	public function fetch_array()
	{
		// Get Data On Every Call
		$this->_get_data();
		
		$data = &$this->data;
		
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
	private $data;
	
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
			trigger_error('Cannot perform set on fields other than "is_primary."', E_USER_NOTICE);
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

class Profile_Meta
{
	private $base;
	private $data;
	private $meta_keys;
	
	function __construct($base)
	{
		$this->base = $base;
		
		$this->meta_keys = array(
			'dt_date_format',
			'dt_time_format',
			'dt_zone',
			'pbx_ext',
			'pbx_ext_mbox'
		);
	}
	
	public function __set($key, $value) 
	{	
		if (in_array($key, $this->meta_keys))
		{
			trigger_error('Meta keys are currently read-only.');
		}
		else
		{
			trigger_error("Meta key $key is invalid.", E_USER_NOTICE);
		}
	}
	
	public function __get($key) 
	{
		$CI =& get_instance();
		$CI->load->helper('array');
		
		if (is_array($this->data) !== true)
		{
			$this->_get_data();
		}
		
		return element($key,$this->data);
	}
	
	public function __toString() 
	{
		// Return full name when class is echoed.
		return '';
	}
	
	private function _get_data()
	{
		$CI =& get_instance();
		
		$CI->load->helpers('glib_array');
		
		$q = $CI->db	->where('pid',$this->base->pid)
						->limit(100)
						->get('profiles_meta')
						->result_array();
		
		$this->data = array_flatten($q, 'meta_key', 'meta_value');
	}
	
}

class Profile_Name
{
	private $base;
	private $data;
	
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
			trigger_error('Cannot write to variable "'.$key.'."', E_USER_NOTICE);
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
		if ($this->base->__get('is_company') == true) {
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

class Profile_Tel
{
	private $base;
	private $data;
	
	public function __construct($base)
	{
		$this->base = $base;
	}
	
	private function _get_data()
	{
		$CI =& get_instance();
		
		$CI->load->helpers('array');
		
		$q = $CI->db	->where('pid',$this->base->pid);
		$r = $q->limit(10)->get('profiles_tel')->result_array();
		
		if (count($r) > 0)
		{
			foreach ($r as $tel)
			{
				$data[] = new Profile_Tel_Entry(&$this->base,&$tel);
			}
			$this->data = $data;
			return true;
		}
		 
	}
	
	public function add ($tel_number, $type='voice', $label=false)
	{
		$CI =& get_instance();
		
		if (is_phone($tel_number) === true)
		{
			$q = $CI->db	
					->set('pid',$this->base->pid)
					->set('tel',$tel_number)
					->set('type',$type)
					->insert('profiles_tel');
			if (empty($label) !== true)
			{
				$q->set('label',$label);
			}
			$this->_get_data();
		}
		else
		{
			trigger_error('Could not add telephone number "'.$tel_number.'" to user, format is invalid.', E_USER_NOTICE);
		}
	}
	
	public function fetch_array()
	{
		// Get Data On Every Call
		$this->_get_data();
		
		$data = &$this->data;
		
		return $data;
	}
	
}

class Profile_Tel_Entry
{
	private $base;
	private $data;
	
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

// End of File