<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

class XP_Filehandle
{
    private $filename;
    private $newname;

    public function upload_file($filename = 'userfile')
    {
        $this->filename = $filename;
        $this->newname = uuid(FALSE);
        $ret = $this->do_upload_file();
        return $ret;
    }

    private function do_upload_file()
    {
        $filePath = $this->getFilePath();
        $config['upload_path'] = $filePath;
        $config['allowed_types'] = 'gif|jpg|png|zip|pdf|rar';
        $config['max_size'] = '10240';
        $config['file_name'] = $this->newname;
        $upload = new XpUpload($config );
        if ( $upload->do_upload($this->filename) )
        {
            $upData = $upload->data();
            if ( !empty($upData) )
            {
                $ret['client_name'] = $upData['client_name'];
                $ret['path'] = $filePath.$upData['orig_name'];
                return $ret;
            }
            else
            {
                return '图片处理异常';
            }
        }
        else
        {
            return $upload->display_errors();
        }
    }

    private function getFilePath()
    {
        $firstFolder = substr($this->newname,0,1);
        $secondFolder = substr($this->newname,1,2);
        $lastFolder = substr($this->newname,3,2);
        $path = FILE_PATH . $firstFolder . '/' . $secondFolder . '/' . $lastFolder. '/';

        if ( !file_exists($path) )
        {
            mkdir($path,0755,true);
        }
        return $path;
    }
}
