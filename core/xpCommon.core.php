<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

if ( !function_exists('trigger404') )
{
    function trigger404($msg = '<h1>404 Page Not Found</h1>')
    {
        global $system;
        header('HTTP/1.1 404 NotFound');
        $filePath =  dirname( __DIR__ ). DIRECTORY_SEPARATOR . APP_FOLDER . DIRECTORY_SEPARATOR . $system['error_page_404'];
        if ( !empty($system['error_page_404']) && is_file($filePath) )
        {
            include "$filePath";
        }
        else
        {
            echo $msg;
        }
        exit;
    }
}

if ( !function_exists('trigger500') )
{
    function trigger500($msg = '<h1>500 Server Error</h1>')
    {
        global $system;
        header('HTTP/1.1 500 Server Error');
        $filePath = dirname( __DIR__ ). DIRECTORY_SEPARATOR . APP_FOLDER . DIRECTORY_SEPARATOR . $system['error_page_50x'];
        if ( !empty($system['error_page_50x']) && is_file($filePath) )
        {
            include $filePath;
        }
        else
        {
            echo $msg;
        }
        die;
    }
}

if ( !function_exists('xpException') )
{
    function xpException($exception)
    {
        $errno = $exception->getCode();
        $errfile = pathinfo($exception->getFile(), PATHINFO_FILENAME);
        $errline = $exception->getLine();
        $errstr = $exception->getMessage();
        @ob_clean();
        trigger500(format_error($errno, $errstr, $errfile, $errline));
    }
}

if ( !function_exists('fatal_handler') )
{
    function fatal_handler()
    {
        $errfile = 'unknown file';
        $errstr = 'shutdown';
        $errno = E_CORE_ERROR;
        $errline = 0;
        $error = error_get_last();
        if (($error !== NULL && isset($error['type'])) && ($error['type'] === E_ERROR || $error['type'] === E_USER_ERROR))
        {
            $errno = $error['type'];
            $errfile = pathinfo($error['file'], PATHINFO_FILENAME);
            $errline = $error['line'];
            $errstr = $error['message'];
            @ob_clean();
            trigger500(format_error($errno, $errstr, $errfile, $errline));
        }
    }
}

if ( !function_exists('format_error') )
{
    function format_error($errno, $errstr, $errfile, $errline)
    {
        $content = '<table><tbody>';
        $content .= ('<tr valign=\'top\'><td><b>Error</b></td><td>:' . nl2br($errstr)) . '</td></tr>';
        $content .= "<tr valign='top'><td><b>Errno</b></td><td>:{$errno}</td></tr>";
        $content .= "<tr valign='top'><td><b>File</b></td><td>:{$errfile}</td></tr>";
        $content .= "<tr valign='top'><td><b>Line</b></td><td>:{$errline}</td></tr>";
        $content .= '</tbody></table>';
        return $content;
    }
}

if ( !function_exists('stripslashes_all') )
{
    function stripslashes_all()
    {
        if ( !get_magic_quotes_gpc() ) return;
        $strip_list = array('_GET', '_POST', '_COOKIE');
        foreach ($strip_list as $val) {
            global ${$val};
            ${$val} = stripslashes2(${$val});
        }
    }
}

if ( !function_exists('stripslashes2') )
{
    function stripslashes2($var)
    {
        if (!get_magic_quotes_gpc()) {
            return $var;
        }
        if (is_array($var)) {
            foreach ($var as $key => $val) {
                if (is_array($val)) {
                    $var[$key] = stripslashes2($val);
                } else {
                    $var[$key] = stripslashes($val);
                }
            }
        } elseif (is_string($var)) {
            $var = stripslashes($var);
        }
        return $var;
    }
}

if ( !function_exists('log_message') )
{
    function log_message($level = 'error', $msg)
    {
        global $system;
        $level = strtoupper($level);
        $levels	= array('ERROR' => '1', 'DEBUG' => '2',  'INFO' => '3', 'ALL' => '4');

        if ( !isset($levels[$level]) || !$system['debug'] )
        {
            return FALSE;
        }

        $log_path = ($system['log_path'] != '') ? $system['log_path'] : APP_FOLDER.DIRECTORY_SEPARATOR.'logs/';
        if ( !is_dir($log_path) OR !is_really_writable($log_path))
        {
            return FALSE;
        }

        $filePath = $log_path.'log-'.date('Y-m-d').'.php';
        $message  = '';

        if ( ! is_file($filePath))
        {
            $message .= "<"."?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?".">\n\n";
        }

        if ( ! $fp = @fopen($filePath, 'ab'))
        {
            return FALSE;
        }

        $message .= $level.' '.(($level == 'INFO') ? ' -' : '-').' '.date('Y-m-d H:i:s'). ' --> '.$msg."\n";

        flock($fp, LOCK_EX);
        fwrite($fp, $message);
        flock($fp, LOCK_UN);
        fclose($fp);

        chmod($filePath, '0666');
        return TRUE;
    }
}

if ( !function_exists('is_really_writable') )
{
    function is_really_writable($file)
    {
        if ( @ini_get("safe_mode") == FALSE)
        {
            return is_writable($file);
        }

        if (is_dir($file))
        {
            $file = rtrim($file, '/').'/'.md5(mt_rand(1,100).mt_rand(1,100));

            if (($fp = @fopen($file, 'ab')) === FALSE)
            {
                return FALSE;
            }

            fclose($fp);
            @chmod($file, '0777');
            @unlink($file);
            return TRUE;
        }
        elseif ( ! is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE)
        {
            return FALSE;
        }

        fclose($fp);
        return TRUE;
    }
}

if ( !function_exists('site_url') )
{
    function site_url($uri = '')
    {
        global $system;
        if ($system['base_url'] == '')
        {
            if (isset($_SERVER['HTTP_HOST']))
            {
                $base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
                $base_url .= '://'. $_SERVER['HTTP_HOST'];
                $base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
            }

            else
            {
                $base_url = 'http://localhost/';
            }
        }
        else
        {
            $base_url = trim($system['base_url']);
            $lastWord = substr($base_url,-1);
            if( $lastWord != '/' ) $base_url .= '/';
        }

        if (is_array($uri))
        {
            $uri = implode('/', $uri);
        }
        $uri = trim($uri, '/');

        return $base_url.$uri;
    }
}

if ( !function_exists('redirect') )
{
    function redirect($url, $msg = null, $view = null, $time = 3)
    {
        if (empty($msg))
        {
            header('Location:' . $url);
        }
        else
        {
            header("refresh:{$time};url={$url}");
            header('Content-type: text/html; charset=utf-8');
            if (empty($view))
            {
                echo $msg;
            }
            else
            {
                $xp = & get_instance();
                $xp->view($view, array('msg' => $msg, 'url' => $url, 'time' => $time));
            }
        }
        die;
    }
}

if ( !function_exists('is_php') )
{
    function is_php($version = '5.0.0')
    {
        static $_is_php;
        $version = (string)$version;

        if ( ! isset($_is_php[$version]))
        {
            $_is_php[$version] = (version_compare(PHP_VERSION, $version) < 0) ? FALSE : TRUE;
        }

        return $_is_php[$version];
    }
}

if ( !function_exists('start_session') )
{
    function start_session($expire = 0)
    {
        if ($expire == 0) {
            $expire = ini_get('session.gc_maxlifetime');
        } else {
            ini_set('session.gc_maxlifetime', $expire);
        }

        if (empty($_COOKIE['PHPSESSID'])) {
            session_set_cookie_params($expire);
            session_start();
        } else {
            session_start();
            setcookie('PHPSESSID', session_id(), time() + $expire);
        }
    }
}