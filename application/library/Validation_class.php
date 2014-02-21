<?php  if ( ! defined('IN_XP_APP')) exit('No direct script access allowed');

class XP_Validation
{
    public function check( $data )
    {
        if ( !is_array($data) || empty($data) )
            throw new ErrorException('检验的表单数据异常', 99, 0, 'global');

        $code = 0;
        foreach( $data as $k => $v )
        {
            $code++;
            if( isset($v['reg']) )
            {
                if ( !preg_match($v['reg'],$v['value']) )
                    throw new ErrorException($v['msg'],$code,0,$k);
            }
            elseif( isset($v['default']) )
            {
                if ( $v['default'] == 'required' )
                {
                    if ( empty($v['value']) )
                        throw new ErrorException("{$v['msg']}", $code,0,$k);
                }
                elseif ( stristr($v['default'], 'length') )
                {
                    preg_match('/(\d+|\*)-(\d+|\*)/', $v['value'], $matches);
                    if ( !@$matches[0] )
                        throw new ErrorException("长度取值参数不正确", 99,0,$k);

                    $size = explode('-', $matches[0]);
                    if ( isset($size[0]) && isset($size[1]) )
                    {
                        $min = $size[0];
                        $max = $size[1];
                    }
                    else
                    {
                        throw new ErrorException("长度取值参数不正确", 99,0,$k);
                    }

                    $length  = mb_strlen($v['value'], 'UTF-8');
                    if($min != '*' && $length < $min)
                    {
                        throw new ErrorException("{$v['msg']}长度需大于{$min}个字符",$code,0,$k);
                    }
                    if($max != '*' && $length > $max)
                    {
                        throw new ErrorException("{$v['msg']}长度需小于{$max}个字符",$code,0,$k);
                    }
                }
            }
        }
    }
}