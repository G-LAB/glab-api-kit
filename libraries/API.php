<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
* G LAB API Library for Code Igniter v2
* Written by Ryan Brodkin
* Copyright 2011 G LAB
*/

class API {
	
	private $api_error;
		
	function request ($version,$method,$resource,$params=array(),$format=false)
	{
		$CI =& get_instance();
		$CI->load->library('rest');
		
		$rest = new Rest(array(
			'server' => 'https://api.glabstudios.com/v'.$version.'/',
			'http_auth' => 'digest',
			'http_user' => 'glab_frontend',
			'http_pass' => 'TDyM8zYVtEwfqJTsL5qEt8XLoinvVHLrao9WkK48gT3mmRqIcPcEOo6A42OXoSR'
		));
		
		if ($format != false){
			$rest->format($format);
		}
		
		
		// Reformat Paramaters as Array
		if (is_numeric($params)) $params = array('id'=>$params);
		elseif (is_string($params)) {
			parse_str($params,$params_a);
			$params = $params_a;
		}
		
		if (method_exists($CI->rest,$method)) {
			$result = $rest->{$method}($resource,$params);
			if (isset($result->error)) {
				$this->api_error = $result->error;
				return FALSE;
			} else return $result;
		} else return FALSE;
	}
	
	function request_v1 ($resource,$params=array(),$method='get',$format='serialized',$version='v1')
	{
		$CI =& get_instance();
		$CI->load->library('rest', array(
			'server' => 'https://api.glabstudios.com/'.$version.'/',
			'http_auth' => 'digest',
			'http_user' => 'glab_frontend',
			'http_pass' => 'TDyM8zYVtEwfqJTsL5qEt8XLoinvVHLrao9WkK48gT3mmRqIcPcEOo6A42OXoSR'
		));
		
		$CI->rest->format($format);
		
		
		// Reformat Paramaters as Array
		if (is_numeric($params)) $params = array('id'=>$params);
		elseif (is_string($params)) {
			parse_str($params,$params_a);
			$params = $params_a;
		}
		
		if (method_exists($CI->rest,$method)) {
			$result = $CI->rest->{$method}($resource,$params);
			if (isset($result->error)) {
				$this->api_error = $result->error;
				return FALSE;
			} else return $result;
		} else return FALSE;
	}
	
}