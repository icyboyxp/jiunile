<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

$system['controller_folder']	=	APP_FOLDER . DIRECTORY_SEPARATOR . 'controllers';
$system['model_folder'] 		= 	APP_FOLDER . DIRECTORY_SEPARATOR . 'models';
$system['view_folder'] 			= 	APP_FOLDER . DIRECTORY_SEPARATOR . 'views';
$system['library_folder'] 		= 	APP_FOLDER . DIRECTORY_SEPARATOR . 'library';
$system['helper_folder'] 		= 	APP_FOLDER . DIRECTORY_SEPARATOR . 'helper';
$system['language_folder'] 		= 	APP_FOLDER . DIRECTORY_SEPARATOR . 'language';

$system['error_page_404']	= 	'error/error_404.php';
$system['error_page_50x'] 	= 	'error/error_50x.php';
$system['error_page_db'] 	= 	'error/error_db.php';

$system['controller_method_prefix'] 	= 	'';
$system['controller_file_subfix'] 		= 	'.php';
$system['model_file_subfix'] 			= 	'.php';
$system['helper_file_subfix'] 			= 	'_helper.php';
$system['view_file_subfix'] 			= 	'_view.php';
$system['library_file_subfix'] 			= 	'_class.php';
$system['library_class_prefix']         =   'XP_';

$system['autoload_db'] 					= 	true;
$system['default_controller'] 			=	'welcome';
$system['default_controller_method'] 	=	'index';
$system['default_timezone'] 			= 	'PRC';
$system['default_language']             =   'chinese';
$system['controller_method_ucfirst'] 	= 	true;
$system['base_url'] 					= 	'';   //基本URL 默认为空即可
$system['log_path'] 					= 	'';   //日志路径 默认为空即可
$system['second_directory_name']        =   '';   //如用二级目录，这里需指定二级目录名,必须一致！

$system['debug'] 					= 	true;
$system['helper_file_autoload'] 	= 	array('my');
$system['library_file_autoload'] 	= 	array('Template');
$system['models_file_autoload']		=	array();

//配置加载核心库
$system['autoIncludeCore'] = array('xpDB','xpUpload',/*'xpSession','xpCache'*/);

//session是否存入memcache配置
$system['session_lifetime'] 	= 	7200;
$system['session_name'] 		= 	'xupeng';
$system['session_domain'] 		= 	'.xp.com';