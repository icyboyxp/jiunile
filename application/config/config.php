<?php  if ( ! defined('IN_XP_APP')) exit('No direct script access allowed');

$config['memcache'] = array(
    array(
        'hostname'  =>  '127.0.0.1',
        'port'      =>  10000,
        'weight'    =>  100,
    )
);

$config['cacheDirName'] = 'cache';

$config['email'] = array(
    'protocol'  =>  'smtp',
    'smtp_from' =>  '',
    'smtp_host' =>  '',
    'smtp_user' =>  '',
    'smtp_pass' =>  '',
    'smtp_port' =>  '25',
    'smtp_timeout'  =>  '5',
    'charset'   =>  'utf-8',
    'mailtype'  =>  'html',
    'newline'   =>  "\r\n",
    'crlf'      =>  "\r\n",
);
