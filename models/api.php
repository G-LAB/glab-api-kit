<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
* G LAB API Library for Code Igniter v2
* Written by Ryan Brodkin
* Copyright 2011 G LAB
*/

class Api extends CI_Model {

  function __construct ()
  {
    parent::__construct();

    $this->load->config('api');
    $this->load->library('rest');
    $this->load->library('curl');

    $this->rest->initialize(array(
      'server' => $this->config->item('api_url'),
      'http_auth' => 'basic',
      'http_user' => $this->config->item('api_user'),
      'http_pass' => $this->config->item('api_pass')
    ));
  }

  function request ($method, $resource, $params=array(), $format=false)
  {
    if ($format != false)
    {
      $this->rest->format($format);
    }

    // Reformat Paramaters as Array
    if (is_string($params))
    {
      parse_str($params,$params_a);
      $params = $params_a;
    }

    if (method_exists($this->rest,$method) === true)
    {
      $result = $this->rest->{$method}($resource,$params);

      if (isset($result->error) === true)
      {
        User_Notice::error($result->error);
        return false;
      }
      elseif ($this->rest->status() >= 500)
      {
        User_Notice::error('The API server responded with an error code. ('.$this->rest->status().')');
      }
      elseif (empty($result) === true  AND $this->rest->status() != 200)
      {
        User_Notice::error('The API server responded with an empty result.');
        return false;
      }

      return $result;
    }
    else
    {
      return false;
    }
  }

  function get ($resource, $params=array(), $format=false)
  {
    return $this->request('get', $resource, $params, $format);
  }

  function post ($resource, $params=array(), $format=false)
  {
    return $this->request('post', $resource, $params, $format);
  }

  function put ($resource, $params=array(), $format=false)
  {
    return $this->request('put', $resource, $params, $format);
  }

  function delete ($resource, $params=array(), $format=false)
  {
    return $this->request('delete', $resource, $params, $format);
  }

  function debug ()
  {
    return $this->rest->debug();
  }

  function status ()
  {
    return $this->rest->status();
  }

}