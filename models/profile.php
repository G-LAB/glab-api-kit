<?php

class Profile extends CI_Model 
{
	
	private $profiles;
	
	public function __construct ()
	{
		$this->load->helper('glab_validation');
		$this->load->helper('glib_number');
	}
	
	public function get($str) 
	{
		
		// Identify ID Format and Process
		if (is_numeric($str) === true)
		{
			$pid = $str;
		} 
		elseif (is_account_number($str))
		{
			$pid = preg_replace('/\D/','', $str);
		}
		elseif (is_hex($str) === true)
		{
			$pid = hexdec($str);
		} 
		else
		{
			$pid = $str;
			trigger_error('Profile must be accessed via decimal or hexadecimal account number.',E_USER_NOTICE);
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
	private $data = false;
	
	public $email;
	public $meta;
	public $name;
	
	
	public function __construct($pid) 
	{
		$this->pid = $pid;
		$this->email = new Profile_Email(&$this);
		$this->meta = new Profile_Meta(&$this);
		$this->name = new Profile_Name(&$this);
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
	
	private function _get_data()
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
			trigger_error('Profile with account number "'.$this->pid.'" does not exist.', E_USER_NOTICE);
			return false;
		}
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
			'date_format',
			'extension_vm',
			'time_format',
			'time_zone'
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
		return element($key,$this->data);
	}
	
	public function __toString() 
	{
		// Return full name when class is echoed.
		return '';
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

class Profile_Address
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
						->order_by('is_primary','DESC')
						->order_by('emid','DESC');
		$r = $q->limit(10)->get('profiles_emails')->result_array();
		
		if (count($r) > 0)
		{
			foreach ($r as $email)
			{
				$data[element('emid',$email)] = new Profile_Email_Entry(&$this->base,&$email);
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
					->insert('profiles_emails');
			
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
					->update('profiles_emails');
		
		$q = $CI->db	
					->set('is_primary',true)
					->where('pid',$this->base->pid)
					->where('emid',$emid)
					->update('profiles_emails');
		
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
					->where('emid',$this->data['emid'])
					->delete('profiles_emails');
	}
	
	public function __toString() 
	{
		return $this->data['email'];
	}
}

// End of File