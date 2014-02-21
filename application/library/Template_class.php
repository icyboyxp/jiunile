<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

class XP_Template
{
    public $template_data = array();
    public $view_vars = array();

    function set($name, $value)
    {
        $this->template_data[$name] = $value;
    }

    function set_arr($data)
    {
        foreach ($data as $key => $value)
        {
            $this->template_data[$key] = $value;
        }
    }

    function load($view = '', $view_data = array(), $template = 'template', $return = FALSE)
    {
        $xp = & get_instance();
        $this->set('contents', $xp->view($view, $view_data, TRUE));
        return $xp->view($template, $this->template_data, $return);
    }

}