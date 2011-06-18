<?php
/**
 * SourceBans comments model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Comments
 * @version    $Id$
 */
class SBComments extends BaseTableModel
{
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'comments';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_day);
    
    $comments = array();
    foreach($this->_data as $row)
    {
      $comment             = new SBComment();
      $comment->admin      = $this->_registry->admins[$row['admin_id']];
      $comment->ban        = $this->_registry->bans[$row['ban_id']];
      $comment->edit_admin = $this->_registry->admins[$row['edit_admin_id']];
      Util::object_set_values($comment, $row);
      
      $comments[$row['id']] = $comment;
    }
    
    $this->_data = $comments;
  }
}