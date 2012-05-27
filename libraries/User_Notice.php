<?php

class User_Notice
{
  static private $data = array();

  static function error($title, $msg=null)
  {
    self::$data[] = new User_Notice_Entry($title, $msg,'error',debug_backtrace(false));
  }

  static function warning($title, $msg=null)
  {
    self::$data[] = new User_Notice_Entry($title, $msg,'warning',debug_backtrace(false));
  }

  static function success($title, $msg=null)
  {
    self::$data[] = new User_Notice_Entry($title, $msg,'success',debug_backtrace(false));
  }

  static function fetch_array()
  {
    $CI = &get_instance();

    if (isset($CI->form_validation) === true)
    {
      $errors = explode("||",validation_errors("\t",'||'));

      foreach ($errors as $msg)
      {
        if (strlen(trim($msg)) > 0)
        {
          self::$data[] = new User_Notice_Entry($title, $msg,'error');
        }
      }
    }

    return self::$data;
  }
}

class User_Notice_Entry
{
  private $data;

  function __construct($title, $msg, $type, $backtrace=false)
  {
    $this->data['title'] = $title;
    $this->data['msg'] = $msg;
    $this->data['type'] = $type;

    if ($backtrace !== false)
    {
      $this->data['backtrace'] = $backtrace[1];
    }
  }

  public function __get($key)
  {
    $CI =& get_instance();
    $CI->load->helper('array');
    return element($key,$this->data);
  }

  function __toString()
  {
    return (string) $this->data['msg'];
  }
}