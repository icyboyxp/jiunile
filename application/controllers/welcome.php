<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

class Welcome extends XpController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index($p = 1,$name = '')
    {
        /*
        $this->lang('form_validation_lang');
		$data = array(
            'register_fund' => array(
                'value' => $register_fund,
                'reg'   => '/^\d+$/',
                'default' => 'length[2-20]',
                'msg'   => $this->language['error_float']
            ),
        );
		try{
            $this->library('Validation');
            $this->validation->check($data);
        } catch (ErrorException $e){
            echo json_encode(array('error'=>'1','msg'=>$e->getMessage(),'name'=>$e->getFile()));
            exit;
        }
        */

        //$this->redirect("http://www.163.com","测试",'message',5);
        //$this->message("提示信息",'message',"http://www.163.com",5);
        //var_dump($this->input->server('http_host',2222));exit;
        //echo $this->page(100, $p, 10, '/welcome.index/{page}');

        /*
        $this->library('Email');
        $this->email->from($this->config_item('email','smtp_from'), 'XP框架');
        $this->email->to(array('xupeng.js@gmail.com','xp-roc@163.com'));
        $this->email->subject('通知邮件');
        $this->email->message('这是一封通知邮件');
        var_dump($this->email->send());exit;
        */

        //$cache = XpCache::instance('memcached');
        //$cache->set('test','测试下',10);
        //$value = $cache->get('test');
        //echo $value;exit;
        //print_r($cache->systemInfo());

		//$this->model('User_model');
        //$result = $this->User_model->getInfoById('uc_users',array('id'=>1));
        //print_r($result);exit;

        //$this->view("welcome", array('msg' => $name, 'ver' => '版本：V1.0'));
        /*
        if (!isset($_SESSION['admin'])) {
            $_SESSION['admin'] = array('2','3');
        }
        print_r($_SESSION['admin']);
        print "<br/>";
        print session_id();
        exit;
        */

        /*
        //验证码
        $this->helper('captcha');
        $vals = array (
            'word' => random_string('alnum', 6),
            'img_path' => './captcha/',
            'img_url' => site_url('captcha').'/',
            'img_width' => 100,
            'img_height' => 23,
            'expiration' => 3600
        );
        $cap = create_captcha( $vals );
        echo $cap['image'];
        */

        $css = $this->config_item('css','demo');
        $initialize = array('title'=>'众智云集','css'=>$css);
        $this->template->set_arr($initialize);
        $this->template->set('title','124');
        $data['author'] = array('author'=>'xp');
        $data['js'] = $this->config_item('js','demo');
        $this->template->load('user/about',$data);
    }

}

