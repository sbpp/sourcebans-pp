<?php
/**
 * SourceBans submissions model
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Submissions
 * @version    $Id$
 */
class SBSubmissions extends BaseTableModel
{
  protected $_order = SORT_DESC;
  protected $_sort  = 'insert_time';
  
  
  function __construct()
  {
    parent::__construct();
    
    $this->_table = $this->_registry->db_prefix . 'submissions';
  }
  
  
  protected function _fetch()
  {
    if(!empty($this->_data))
      return;
    
    parent::_fetch($this->_registry->one_minute * 5);
    
    $submissions = array();
    foreach($this->_data as $row)
    {
      $submission         = new SBSubmission();
      $submission->server = $this->_registry->servers[$row['server_id']];
      Util::object_set_values($submission, $row);
      
      $submissions[$row['id']] = $submission;
    }
    
    $this->_data = $submissions;
  }
}