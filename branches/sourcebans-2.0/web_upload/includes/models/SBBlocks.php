<?php
/**
 * SourceBans blocks model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Blocks
 * @version    $Id$
 */
class SBBlocks extends BaseTableModel
{
  protected $_order = SORT_DESC;
  protected $_sort  = 'insert_time';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'blocks';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_minute * 5);
    
    $blocks = array();
    foreach($this->_data as $row)
    {
      $block         = new SBBlock();
      $block->ban    = $this->_registry->bans[$row['ban_id']];
      $block->server = $this->_registry->servers[$row['server_id']];
      Util::object_set_values($block, $row);
      
      $blocks[] = $block;
    }
    
    $this->_data = $blocks;
  }
}