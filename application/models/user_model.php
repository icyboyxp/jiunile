<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

class User_model extends XpModel
{
	
    public function is_existed( $table, $data )
    {
        $this->db->where($data);
        $query = $this->db->get($table, 1);
        if ( $query->num_rows() == 1 )
        {
            return $query->row_array();
        }
        else
        {
            return FALSE;
        }
    }

    public function getInfoById( $table, $where )
    { 
        $query = $this->db->get_where($table, $where, 1);
        if ( $query->num_rows() == 1 )
        {
            return $query->row_array();
        }
        else
        {
            return FALSE;
        }
    }
}
