<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
* G LAB API Library for Code Igniter v2
* Written by Ryan Brodkin
* Copyright 2011 G LAB
*/

class Api extends CI_Model {

	private $restclient;

	function __construct ()
	{
		parent::__construct();

		$this->load->config('api');
		$this->load->library('rest');

		$this->restclient = new Rest(array(
			'server' => $this->config->item('api_url'),
			'http_auth' => 'digest',
			'http_user' => $this->config->item('api_user'),
			'http_pass' => $this->config->item('api_pass')
		));
	}

	function request ($method, $resource, $params=array(), $format=false)
	{
		if ($format != false)
		{
			$this->restclient->format($format);
		}

		// Reformat Paramaters as Array
		if (is_string($params))
		{
			parse_str($params,$params_a);
			$params = $params_a;
		}

		if (method_exists($this->restclient,$method) === true)
		{
			$result = $this->restclient->{$method}($resource,$params);

			if (isset($result->error) === true)
			{
				User_Notice::error($result->error);
				return false;
			}
			elseif (empty($result) === true)
			{
				User_Notice::error('The API server responded with an empty result.');
				return false;
			}
			else {
				return $result;
			}
		}
		else
		{
			return false;
		}
	}

	function get ($resource, $params=array(), $format=false)
	{
		$this->request('get', $resource, $params, $format);
	}

	function post ($resource, $params=array(), $format=false)
	{
		$this->request('post', $resource, $params, $format);
	}

	function put ($resource, $params=array(), $format=false)
	{
		$this->request('put', $resource, $params, $format);
	}

	function delete ($resource, $params=array(), $format=false)
	{
		$this->request('delete', $resource, $params, $format);
	}

	function debug ()
	{
		$this->restclient->debug();
	}

}