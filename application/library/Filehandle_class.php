<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

class XP_Filehandle
{
    private $filename;
    private $newname;
    private $flag;
    private $filePath;
    private $upData;

    public function upload_file($filename,$flag = false)
    {
        $this->filename = $filename;
        $this->newname = uuid(FALSE);
        $this->flag = $flag;
        $ret = $this->do_upload_file();
        return $ret;
    }

    private function do_upload_file()
    {
        $this->filePath = $this->getFilePath();
        $config['upload_path'] = $this->filePath;
        $config['allowed_types'] = 'gif|jpg|png';
        $config['max_size'] = '10240';
        $config['file_name'] = $this->newname;
        $upload = new XpUpload($config );
        if ( $upload->do_upload($this->filename) )
        {
            $this->upData = $upload->data();
            if ( $this->upData['is_image'] == 1 && $this->flag )
            {
                $this->crop();
                $ret['client_name'] = $this->upData['client_name'];
                $ret['path'] = $this->filePath.$this->upData['orig_name'];
                return $ret;
            }
            elseif ( !empty($this->upData) )
            {
                $ret['client_name'] = $this->upData['client_name'];
                $ret['path'] = $this->filePath.$this->upData['orig_name'];
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

    private function crop()
    {
        $image_type = strtolower( $this->upData['image_type'] );
        $img_path = $this->upData['full_path'];

        if ( $image_type == "png" )
        {
            $image = imagecreatefrompng( $img_path );
            imagejpeg( $image, $img_path, 100 );
            imagedestroy( $image );
        }
        else if ( $image_type == "gif" )
        {
            $image = imagecreatefromgif( $img_path );
            imagejpeg( $image, $img_path, 100 );
            imagedestroy( $image );
        }
        else if ( $image_type == "jpeg" )
        {
            $image = imagecreatefromjpeg( $img_path );
            imagejpeg( $image, $img_path, 100 );
            imagedestroy( $image );
        }

        $size = explode('x',$this->flag);
        $dst_width = $size[0];
        $dst_height = $size[1];
        $dst_file = $this->filePath.$this->upData['orig_name'];

        list($src_width, $src_height) = getimagesize($img_path);

        $dImage   = imagecreatetruecolor($dst_width, $dst_height);
        $sImage = imagecreatefromjpeg($img_path);

        $bg = imagecolorallocatealpha($dImage, 255, 255, 255, 127);
        imagefill($dImage, 0, 0, $bg);
        imagecolortransparent($dImage, $bg);

        $ratio_w = 1.0 * $dst_width / $src_width;
        $ratio_h = 1.0 * $dst_height / $src_height;
        $ratio   = 1.0;

        if (($ratio_w < 1 && $ratio_h < 1) || ($ratio_w > 1 && $ratio_h > 1))
        {
            $ratio   = $ratio_w < $ratio_h ? $ratio_h : $ratio_w;
            $tmp_w   = (int) ($dst_width / $ratio);
            $tmp_h   = (int) ($dst_height / $ratio);
            $tmp_img = imagecreatetruecolor($tmp_w, $tmp_h);
            $src_x   = (int) (($src_width - $tmp_w) / 2);
            $src_y   = (int) (($src_height - $tmp_h) / 2);
            imagecopy($tmp_img, $sImage, 0, 0, $src_x, $src_y, $tmp_w, $tmp_h);
            imagecopyresampled($dImage, $tmp_img, 0, 0, 0, 0, $dst_width, $dst_height, $tmp_w, $tmp_h);
            imagedestroy($tmp_img);
        }
        else
        {
            $ratio   = $ratio_w < $ratio_h ? $ratio_h : $ratio_w;
            $tmp_w   = (int) ($src_width * $ratio);
            $tmp_h   = (int) ($src_height * $ratio);
            $tmp_img = imagecreatetruecolor($tmp_w, $tmp_h);
            imagecopyresampled($tmp_img, $sImage, 0, 0, 0, 0, $tmp_w, $tmp_h, $src_width, $src_height);
            $src_x   = (int) ($tmp_w - $dst_width) / 2;
            $src_y   = (int) ($tmp_h - $dst_height) / 2;
            imagecopy($dImage, $tmp_img, 0, 0, $src_x, $src_y, $dst_width, $dst_height);
            imagedestroy($tmp_img);
        }

        imagejpeg($dImage, $dst_file, 100);

        imagedestroy($sImage);
        imagedestroy($dImage);
    }
}
