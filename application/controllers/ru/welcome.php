<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

/**
 * Description of index
 *
 * @author Administrator
 */
class Welcome extends XpController {

    public function __construct() {
        parent::__construct();
        //$this->helper('html.helper');
    }

    static function check($val){
        var_dump($val);
        return false;
    }
    public function index($p = 1,$name = '')
    {
        //$this->redirect("http://www.163.com","测试",'message',5);
        //$this->message("提示信息",'message',"http://www.163.com",5);
        //var_dump($this->input->server('http_host',2222));exit;
        //echo $this->page(100, $p, 10, '/welcome.index/{page}');
		
		$this->model('user');
        $result = $this->model->user->getInfoById('uc_user',array('autoid'=>100));
        print_r($result);exit;

        $this->helper('config');
        $this->view("welcome", array('msg' => $name, 'ver' => $this->config('myconfig', 'app')));

		
        $css = $this->config_item('css','demo');
        $initialize = array('title'=>'众智云集','css'=>$css);
        $this->lib->template->set_arr($initialize);
        //$this->template->set('title','124');
        $data['author'] = array('author'=>'xp');
        $data['js'] = $this->config_item('js','demo');
        $this->lib->template->load('user/about',$data);
		
    }

    public function a__output($html) {
        echo '__output' . $html;
    }

    public function doAjax() {
        $this->ajax_echo(200, 'tip', array('a', 'b'),false);
    }

}

