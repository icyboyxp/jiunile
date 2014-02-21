<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

/**
 * @name    输出js
 * @param   Array     $js
 * @return  String
 */
if ( !function_exists( 'output_js' ) )
{
    function output_js($js)
    {
        $output = '';
        if(!empty($js) && is_array($js))
        {
            foreach($js as $v)
            {
                $output .= "<script type=\"text/javascript\" src=\"".site_url()."assets/js/{$v}\"></script>\n\t    ";
            }
        }
        return $output;
    }
}


if ( ! function_exists('random_string'))
{
    function random_string($type = 'alnum', $len = 8)
    {
        switch($type)
        {
            case 'basic'	: return mt_rand();
                break;
            case 'alnum'	:
            case 'numeric'	:
            case 'nozero'	:
            case 'alpha'	:

                switch ($type)
                {
                    case 'alpha'	:	$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        break;
                    case 'alnum'	:	$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        break;
                    case 'numeric'	:	$pool = '0123456789';
                        break;
                    case 'nozero'	:	$pool = '123456789';
                        break;
                }

                $str = '';
                for ($i=0; $i < $len; $i++)
                {
                    $str .= substr($pool, mt_rand(0, strlen($pool) -1), 1);
                }
                return $str;
                break;
            case 'unique'	:
            case 'md5'		:

                return md5(uniqid(mt_rand()));
                break;
        }
    }
}


if ( ! function_exists('uuid') )
{
    function uuid( $more = true , $len = 3 )
    {
        if ( $more )
        {
            return uniqid( md5(mt_rand() ), true );
        }
        else
        {
            return uniqid( create_guid_section( $len ) , false );
        }
    }

}

function create_guid_section( $characters )
{
    $return  = '';
    for ( $i = 0; $i < $characters; $i++ )
    {
        $return .= dechex( mt_rand( 0, 15 ) );
    }
    return $return;
}