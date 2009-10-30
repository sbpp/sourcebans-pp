<?php
require_once 'init.php';
require_once READERS_DIR . 'protests.php';
require_once READERS_DIR . 'submissions.php';
require_once WRITERS_DIR . 'bans.php';
require_once WRITERS_DIR . 'demos.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['bans'], !isset($_GET['nofullpage']));

try
{
  if(!$userbank->HasAccess(array('OWNER', 'ADD_BANS', 'EDIT_ALL_BANS', 'EDIT_GROUP_BANS', 'EDIT_OWN_BANS', 'BAN_PROTESTS', 'BAN_SUBMISSIONS')))
    throw new Exception($phrases['access_denied']);
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    try
    {
      switch($_POST['action'])
      {
        case 'add':
          if(!$userbank->HasAccess(array('OWNER', 'ADD_BANS')))
            throw new Exception($phrases['access_denied']);
          
          $id = BansWriter::add($_POST['type'], strotupper($_POST['steam']), $_POST['ip'], $_POST['name'], $_POST['reason'] == 'other' ? $_POST['reason_other'] : $_POST['reason'], $_POST['length']);
          
          // If one or more demos were uploaded, add them
          foreach($_FILES['demo'] as $demo)
            DemosWriter::add($id, BAN_TYPE, $demo['name'], $demo['tmp_name']);
          
          break;
        case 'import':
          if(!$userbank->HasAccess(array('OWNER', 'IMPORT_BANS')))
            throw new Exception($phrases['access_denied']);
          
          BansWriter::import($_FILES['file']['name'], $_FILES['file']['tmp_name']);
          break;
        default:
          throw new Exception($phrases['invalid_action']);
      }
      
      exit(json_encode(array(
        'redirect' => Env::get('active')
      )));
    }
    catch(Exception $e)
    {
      exit(json_encode(array(
        'error' => $e->getMessage()
      )));
    }
  }
  
  $protests_reader             = new ProtestsReader();
  $submissions_reader          = new SubmissionsReader();
  
  $protests                    = $protests_reader->executeCached(ONE_MINUTE    * 5);
  $submissions                 = $submissions_reader->executeCached(ONE_MINUTE * 5);
  
  $protests_reader->archive    = true;
  $submissions_reader->archive = true;
  $archived_protests           = $protests_reader->executeCached(ONE_MINUTE    * 5);
  $archived_submissions        = $submissions_reader->executeCached(ONE_MINUTE * 5);
  
  $page->assign('permission_add_bans',        $userbank->HasAccess(array('OWNER', 'ADD_BANS')));
  $page->assign('permission_edit_bans',       $userbank->HasAccess(array('OWNER', 'EDIT_ALL_BANS', 'EDIT_GROUP_BANS', 'EDIT_OWN_BANS')));
  $page->assign('permission_import_bans',     $userbank->HasAccess(array('OWNER', 'IMPORT_BANS')));
  $page->assign('permission_list_comments',   $userbank->is_admin());
  $page->assign('permission_protests',        $userbank->HasAccess(array('OWNER', 'BAN_PROTESTS')));
  $page->assign('permission_submissions',     $userbank->HasAccess(array('OWNER', 'BAN_SUBMISSIONS')));
  $page->assign('protests',                   $protests['list']);
  $page->assign('submissions',                $submissions['list']);
  $page->assign('archived_protests',          $archived_protests['list']);
  $page->assign('archived_submissions',       $archived_submissions['list']);
  $page->assign('total_protests',             $protests['count']);
  $page->assign('total_submissions',          $submissions['count']);
  $page->assign('total_archived_protests',    $archived_protests['count']);
  $page->assign('total_archived_submissions', $archived_submissions['count']);
  $page->display('page_admin_bans');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>
