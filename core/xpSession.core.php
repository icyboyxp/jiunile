<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

class XpSession
{
    //类成员属性定义
    static  $mSessSavePath;
    static  $mSessName;
    static  $mMemcacheObj;
    static  $config;
    static  $system;
    static  $checked;

    /*
     * 构造函数
     *
     * @param string $login_user    登录用户
     * @param int $login_type       用户类型
     * @param string $login_sess    登录Session值
     * @return Esession
     */
    public function __construct()
    {
        global $system;
        global $config;

        self::$config = $config;
        self::$system = $system;
        //我的memcache是以php模块的方式编译进去的，可以直接调用
        //如果没有，就请自己包含 Memcache-client.php 文件
        if (!class_exists('Memcache') || !function_exists('memcache_connect'))
        {
            die('Fatal Error:Can not load Memcache extension!');
        }

        if (!empty(self::$mMemcacheObj) && is_object(self::$mMemcacheObj))
        {
            return false;
        }

        self::$mMemcacheObj = new Memcache();
        self::$checked['memcache'] = array();
        foreach(self::$config['memcache'] as $server)
        {
            $name = isset($server['hostname']) ? $server['hostname'] : "127.0.0.1";
            $port = isset($server['port']) ? $server['port'] : 11211;
            $weight = isset($server['weight']) ? $server['weight'] : 1;
            if(!in_array($name, self::$checked['memcache']) && $name !="")
            {
                self::$mMemcacheObj->addServer($name,$port,$weight);
                self::$checked['memcache'][] = $name;
            }
        }

        return TRUE;
    }

    /*
     * sessOpen($pSavePath, $name)
     * @param   String  $pSavePath
     * @param   String  $pSessName
     * @return  Bool    TRUE/FALSE
     */
    public function sessOpen($pSavePath = '', $pSessName = '')
    {
        self::$mSessSavePath    = $pSavePath;
        self::$mSessName        = $pSessName;
        return TRUE;
    }

    /*
     * sessClose()
     * @param   NULL
     * @return  Bool    TRUE/FALSE
     */
    public function sessClose()
    {
        return TRUE;
    }

    /*
     * sessRead($wSessId)
     * @param   String  $wSessId
     * @return  Bool    TRUE/FALSE
     */
    public function sessRead($wSessId = '')
    {
        $wData = self::$mMemcacheObj->get($wSessId);

        //先读数据，如果没有，就初始化一个
        if (!empty($wData))
        {
            return $wData;
        }
        else
        {
            //初始化一条空记录
            $ret = self::$mMemcacheObj->set($wSessId, '', 0, self::$system['session_lifetime']);
            if (TRUE != $ret)
            {
                die("Fatal Error: Session ID $wSessId init failed!");
                return FALSE;
            }
            return TRUE;
        }
    }

    /*
     * sessWrite($wSessId, $wData)
     * @param   String  $wSessId
     * @param   String  $wData
     * @return  Bool    TRUE/FALSE
     */
    public function sessWrite($wSessId = '', $wData = '')
    {
        $ret = self::$mMemcacheObj->replace($wSessId, $wData, 0, self::$system['session_lifetime']);
        if (TRUE != $ret)
        {
            die("Fatal Error: SessionID $wSessId Save data failed!");
            return FALSE;
        }
        return TRUE;
    }

    /*
     * sessDestroy($wSessId)
     * @param   String  $wSessId
     * @return  Bool    TRUE/FALSE
     */
    public function sessDestroy($wSessId = '')
    {
        self::sessWrite($wSessId);
        return FALSE;
    }

    /*
     * sessGc()
     * @param   NULL
     * @return  Bool    TRUE/FALSE
     */
    public function sessGc()
    {
        //无需额外回收,memcache有自己的过期回收机制
        return TRUE;
    }

    /*
     * initSess()
     * @param   NULL
     * @return  Bool    TRUE/FALSE
     */
    public function initSess()
    {
        //不使用 GET/POST 变量方式
        ini_set('session.use_trans_sid',    0);

        //设置垃圾回收最大生存时间
        ini_set('session.gc_maxlifetime',   self::$system['session_lifetime']);

        //使用 COOKIE 保存 SESSION ID 的方式
        ini_set('session.use_cookies',      1);
        ini_set('session.cookie_path',      '/');

        session_name(self::$system['session_name']);

        //多主机共享保存 SESSION ID 的 COOKIE
        ini_set('session.cookie_domain', self::$system['session_domain']);

        //将 session.save_handler 设置为 user，而不是默认的 files
        session_module_name('user');

        //定义 SESSION 各项操作所对应的方法名：
        session_set_save_handler(
            array('XpSession', 'sessOpen'),   //对应于静态方法 My_Sess::open()，下同。
            array('XpSession', 'sessClose'),
            array('XpSession', 'sessRead'),
            array('XpSession', 'sessWrite'),
            array('XpSession', 'sessDestroy'),
            array('XpSession', 'sessGc')
        );

        session_start();
        return TRUE;
    }
}

$memSess = new XpSession;
$memSess->initSess();
?>