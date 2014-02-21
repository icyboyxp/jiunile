<?php
$configFiles = __DIR__.DIRECTORY_SEPARATOR.APP_FOLDER.DIRECTORY_SEPARATOR.'config/*';
foreach( glob($configFiles) as $file)
{
    include "$file";
}

foreach($system['autoIncludeCore'] as $v)
{
    if ( !class_exists(ucfirst($v)) ) include( __DIR__ . '/core/' . $v .'.core.php' );
}

include( __DIR__ . '/core/xpCommon.core.php' );

if( !class_exists('XpSession') ) start_session($system['session_lifetime']);

class XpRouter
{
    public static function loadClass()
    {
        global $system;
        $methodInfo = self::parseURI();
        XpLoader::classAutoloadRegister();
        if ( is_file($methodInfo['file']) )
        {
            include "{$methodInfo['file']}";
            XpInput::$router = $methodInfo;
            $class = new $methodInfo['class']();
            if ( method_exists($class, $methodInfo['method']) )
            {
                $methodInfo['parameters'] = is_array($methodInfo['parameters']) ? $methodInfo['parameters'] : array();
                if (method_exists($class, '__output'))
                {
                    ob_start();
                    call_user_func_array(array($class, $methodInfo['method']), $methodInfo['parameters']);
                    $buffer = ob_get_contents();
                    @ob_end_clean();
                    call_user_func_array(array($class, '__output'), array($buffer));
                }
                else
                {
                    call_user_func_array(array($class, $methodInfo['method']), $methodInfo['parameters']);
                }
            }
            else
            {
                trigger404((($methodInfo['class'] . ':') . $methodInfo['method']) . ' not found.');
            }
        }
        else
        {
            if ($system['debug'])
                trigger404(('file:' . $methodInfo['file']) . ' not found.');
            else
                trigger404();
        }
    }

    private static function parseURI()
    {
        global $system;
        if (XpInput::isCli())
        {
            global $argv;
            $pathinfo_query = isset($argv[1]) ? $argv[1] : '';
        }
        else
        {
            $pathinfo = @parse_url($_SERVER['REQUEST_URI']);
            if (empty($pathinfo))
            {
                if ($system['debug'])
                    trigger404('request parse error:' . $_SERVER['REQUEST_URI']);
                else
                    trigger404();
            }
            $pathinfo_query = !empty($pathinfo['path']) ? $pathinfo['path'] : (!empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '');
        }

        if($system['second_directory_name'])
            $pathinfo_query = str_replace($system['second_directory_name'].'/', '', $pathinfo_query);

        $class_method = ($system['default_controller'] . '.') . $system['default_controller_method'];
        if (!empty($pathinfo_query))
        {
            $pathinfo_query[0] === '/' ? ($pathinfo_query = substr($pathinfo_query, 1)) : null;
            $requests = explode('/', $pathinfo_query);
            preg_match('/[^&]+(?:\\.[^&]+)+/', $requests[0]) ? ($class_method = $requests[0]) : null;
            if (strstr($class_method, '&') !== false)
            {
                $cm = explode('&', $class_method);
                $class_method = $cm[0];
            }
        }
        $pathinfo_query = str_replace($class_method, '', $pathinfo_query);
        $pathinfo_query_parameters = explode('&', $pathinfo_query);
        $pathinfo_query_parameters_str = !empty($pathinfo_query_parameters[0]) ? $pathinfo_query_parameters[0] : '';
        $pathinfo_query_parameters_str && $pathinfo_query_parameters_str[0] === '/' ? ($pathinfo_query_parameters_str = substr($pathinfo_query_parameters_str, 1)) : '';
        $origin_class_method = $class_method;
        $class_method = explode('.', $class_method);
        $method = end($class_method);
        $method = $system['controller_method_prefix'] . ($system['controller_method_ucfirst'] ? ucfirst($method) : $method);
        unset($class_method[count($class_method) - 1]);
        $file = (($system['controller_folder'] . DIRECTORY_SEPARATOR) . implode(DIRECTORY_SEPARATOR, $class_method)) . $system['controller_file_subfix'];
        $class = $class_method[count($class_method) - 1];
        $parameters = explode('/', $pathinfo_query_parameters_str);
        foreach ($parameters as $key => $value)
        {
            $parameters[$key] = urldecode($value);
        }
        if (count($parameters) === 1 && empty($parameters[0]))
            $parameters = array();
        $info = array('file' => $file, 'class' => ucfirst($class), 'method' => str_replace('.', '/', $method), 'parameters' => $parameters);
        $path = explode('.', $origin_class_method);
        $router['mpath'] = $origin_class_method;
        $router['m'] = $path[count($path) - 1];
        if (count($path) > 1)
            $router['c'] = $path[count($path) - 2];
        $router['prefix'] = $system['controller_method_prefix'];
        unset($path[count($path) - 1]);
        $router['capth'] = implode('.', $path);
        if (count($path) > 1)
        {
            unset($path[count($path) - 1]);
            $router['folder'] = implode('.', $path);
        }
        $defaultRoute = ($system['default_controller'] . '.') . $system['default_controller_method'];
        if($router['mpath'] == $defaultRoute)
        {
            if(!empty($info['parameters']))
            {
                if(!is_numeric($info['parameters'][0]))
                    trigger404("The page you requested was not found.");
            }
        }
        return $router + $info;
    }
}

class XpLoader
{
    public $router, $db, $input, $view_vars = array(), $language = array();
    private $helper_files = array();
    private $is_loaded	= array();
    private static $instance;

    public function __construct()
    {
        date_default_timezone_set($this->config('system', 'default_timezone'));
        $this->registerErrorHandle();
        $this->router = XpInput::$router;
        $this->input = new XpInput();
        if ($this->config('system', 'autoload_db'))
            $this->database();
        stripslashes_all();
    }

    public function registerErrorHandle()
    {
        if (!$this->config('system', 'debug'))
        {
            error_reporting(0);
            set_exception_handler('xpException');
            register_shutdown_function('fatal_handler');
        }
        else
        {
            error_reporting(E_ALL);
        }
    }

    public function config_item($key, $parameter = '', $config_group = 'config')
    {
        global ${$config_group};
        if ($key)
        {
            $config_group = ${$config_group};
            return isset($config_group[$key]) ? !empty($parameter) ? $config_group[$key][$parameter] : $config_group[$key] : null;
        }
        else
        {
            return isset(${$config_group}) ? ${$config_group} : null;
        }
    }

    public function config($config_group, $key = '')
    {
        global ${$config_group};
        if ($key)
        {
            $config_group = ${$config_group};
            return isset($config_group[$key]) ? $config_group[$key] : null;
        }
        else
        {
            return isset(${$config_group}) ? ${$config_group} : null;
        }
    }

    public function database($config = NULL, $is_return = false)
    {
        if ($is_return)
        {
            $db = null;
            if (!is_array($config))
            {
                global $db;
                $db = XpDB::getInstance($db[$db['active_group']]);
            }
            else
            {
                $db = XpDB::getInstance($config);
            }
            return $db;
        }
        else
        {
            if (!is_array($config))
            {
                if (!is_object($this->db))
                {
                    global $db;
                    $this->db = XpDB::getInstance($db[$db['active_group']]);
                }
            }
            else
            {
                $this->db = XpDB::getInstance($config);
            }
        }
    }

    public function helper($fileName)
    {
        global $system;
        $fileName = (($system['helper_folder'] . DIRECTORY_SEPARATOR) . $fileName) . $system['helper_file_subfix'];

        if (in_array($fileName, $this->helper_files))
            return;

        if ( is_file($fileName) )
        {
            $this->helper_files[] = $fileName;
            $before_vars = array_keys(get_defined_vars());
            include "$fileName";
            $vars = get_defined_vars();
            $all_vars = array_keys($vars);
            foreach ($all_vars as $key)
            {
                if (!in_array($key, $before_vars) && isset($vars[$key]))
                    $GLOBALS[$key] = $vars[$key];
            }
        }
        else
        {
            trigger404($fileName . ' not found.');
        }
    }

    public function library($fileName)
    {
        global $system;
        $className = $fileName;
        if (strstr($fileName, '/') !== false || strstr($fileName, '\\') !== false)
            $className = basename($fileName);

        $aliasName = strtolower($className);
        $className = $system['library_class_prefix'] . $className;

        if( isset($this->$aliasName) ) return $this->$aliasName;

        $filePath = (($system['library_folder'] . DIRECTORY_SEPARATOR) . $fileName) . $system['library_file_subfix'];

        if ( is_file($filePath) )
        {
            include "$filePath";

            if ( class_exists($className) )
            {
                return $this->$aliasName = new $className();
            }
            else
            {
                trigger404(('Library Class:' . $className) . ' not found.');
            }
        }
        else
        {
            trigger404($filePath . ' not found.');
        }
    }

    public function lang($langFile, $idiom = '', $add_suffix = false)
    {
        global $system;
        $langFile = str_replace('.php', '', $langFile);

        if ($add_suffix == true)
        {
            $langFile = str_replace('_lang', '', $langFile);
        }

        $langFile .= '.php';

        if (in_array($langFile, $this->is_loaded, TRUE))
        {
            return;
        }

        if ($idiom == '')
        {
            $deft_lang = !isset($system['language']) ? 'chinese' : $system['language'];
            $idiom = ($deft_lang == '') ? 'chinese' : $deft_lang;
        }

        $langFilePath = $system['language_folder'].DIRECTORY_SEPARATOR.$idiom.DIRECTORY_SEPARATOR.$langFile;
        if ( is_file($langFilePath) )
            include "$langFilePath";

        if ( !isset($lang) )
        {
            log_message('error', '无法加载语言包：'.$langFilePath);
            return;
        }

        $this->is_loaded[] = $langFile;
        $this->language = array_merge($this->language, $lang);
        unset($lang);

        return TRUE;
    }

    public function model($fileName)
    {
        global $system;
        $className = $fileName;
        if (strstr($fileName, '/') !== false || strstr($fileName, '\\') !== false)
            $className = basename($fileName);

        if( isset($this->$className) ) return $this->$className;

        $filePath = (($system['model_folder'] . DIRECTORY_SEPARATOR) . strtolower($fileName)) . $system['model_file_subfix'];

        if ( is_file($filePath) )
        {
            include "$filePath";
            if (class_exists($className))
            {
                return $this->$className = new $className();
            }
            else
            {
                trigger404(('Model Class:' . $className) . ' not found.');
            }
        }
        else
        {
            trigger404($filePath . ' not found.');
        }
    }

    public function view($view_name, $data = null, $return = false)
    {
        if (is_array($data))
        {
            $this->view_vars = array_merge($this->view_vars, $data);
            extract($this->view_vars);
        }
        elseif (is_array($this->view_vars) && !empty($this->view_vars))
        {
            extract($this->view_vars);
        }
        global $system;
        $view_path = (($system['view_folder'] . DIRECTORY_SEPARATOR) . $view_name) . $system['view_file_subfix'];
        if (is_file($view_path))
        {
            if ($return)
            {
                @ob_end_clean();
                ob_start();
                include "$view_path";
                $html = ob_get_contents();
                @ob_end_clean();
                return $html;
            }
            else
            {
                include "$view_path";
            }
        }
        else
        {
            trigger404(('View:' . $view_path) . ' not found');
        }
    }

    public static function classAutoloadRegister()
    {
        $found = false;
        $__autoload_found = false;
        $auto_functions = spl_autoload_functions();
        if (is_array($auto_functions))
        {
            foreach ($auto_functions as $func)
            {
                if ((is_array($func) && $func[0] == 'XpLoader') && $func[1] == 'classAutoloader')
                {
                    $found = TRUE;
                    break;
                }
            }
            foreach ($auto_functions as $func)
            {
                if (!is_array($func) && $func == '__autoload')
                {
                    $__autoload_found = TRUE;
                    break;
                }
            }
        }
        if (function_exists('__autoload') && !$__autoload_found)
            spl_autoload_register('__autoload');
        if (!$found)
            spl_autoload_register(array('XpLoader', 'classAutoloader'));
    }

    public static function classAutoloader($clazzName)
    {
        global $system;
        $library = (($system['library_folder'] . DIRECTORY_SEPARATOR) . $clazzName) . $system['library_file_subfix'];
        if ( is_file($library) )
            include "$library";
    }

    public static function instance()
    {
        self::classAutoloadRegister();
        return empty(self::$instance) ? (self::$instance = new self()) : self::$instance;
    }

    public function view_path($view_name)
    {
        global $system;
        $view_path = (($system['view_folder'] . DIRECTORY_SEPARATOR) . $view_name) . $system['view_file_subfix'];
        return $view_path;
    }
}

class XpController extends XpLoader
{
    private static $xp;
    private static $instance;

    public function __construct()
    {
        $this->autoLoad();
        parent::__construct();
        self::$xp =& $this;
    }

    private function autoLoad()
    {
        $autoLoad_helper = $this->config('system', 'helper_file_autoload');
        $autoLoad_library = $this->config('system', 'library_file_autoload');
        $autoLoad_models = $this->config('system', 'models_file_autoload');
        foreach ($autoLoad_helper as $file_name)
        {
            $this->helper($file_name);
        }
        foreach ($autoLoad_library as $val)
        {
            $this->library($val);
        }
        foreach ($autoLoad_models as $val)
        {
            $this->model($val);
        }
    }

    public static function &getInstance()
    {
        return self::$xp;
    }

    public static function instance($classNamePath = null)
    {
        if (empty($classNamePath))
            return empty(self::$instance) ? (self::$instance = new self()) : self::$instance;

        global $system;
        $classNamePath = str_replace('.', DIRECTORY_SEPARATOR, $classNamePath);
        $className = basename($classNamePath);

        if( isset(self::$xp->$className) ) return self::$xp->$className;

        $filePath = (($system['controller_folder'] . DIRECTORY_SEPARATOR) . $classNamePath) . $system['controller_file_subfix'];
        if ( is_file($filePath) )
        {
            XpLoader::classAutoloadRegister();
            include "$filePath";
            if ( class_exists($className) )
            {
                return self::$xp->$className = new $className();
            }
            else
            {
                trigger404(('Controller Class:' . $className) . ' not found.');
            }
        }
        else
        {
            trigger404($filePath . ' not found.');
        }
    }
}

class XpModel extends XpLoader
{
}

class XpInput
{
    public static $router;

    public static function get_post($key = null, $default = null)
    {
        $get = self::gpcs('_GET', $key, $default);
        return $get === null ? self::gpcs('_POST', $key, $default) : $get;
    }

    public static function get($key = null, $default = null)
    {
        return self::gpcs('_GET', $key, $default);
    }

    public static function post($key = null, $default = null)
    {
        return self::gpcs('_POST', $key, $default);
    }

    public static function cookie($key = null, $default = null)
    {
        return self::gpcs('_COOKIE', $key, $default);
    }

    public static function session($key = null, $default = null)
    {
        return self::gpcs('_SESSION', $key, $default);
    }

    public static function server($key = null, $default = null)
    {
        $key = strtoupper($key);
        return self::gpcs('_SERVER', $key, $default);
    }

    private static function gpcs($range, $key, $default)
    {
        global ${$range};
        if ($key === null) {
            return ${$range};
        } else {
            $range = ${$range};
            return isset($range[$key]) ? trim($range[$key]) : ($default !== null ? $default : null);
        }
    }

    public static function isCli()
    {
        return php_sapi_name() == 'cli';
    }

}

XpRouter::loadClass();

function &get_instance()
{
    return XpController::getInstance();
}