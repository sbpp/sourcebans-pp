<?php
require_once 'init.php';
require_once READERS_DIR . 'counts.php';
require_once READERS_DIR . 'protests.php';
require_once READERS_DIR . 'submissions.php';
require_once WRITERS_DIR . 'bans.php';

$phrases  = Env::get('phrases');
$userbank = Env::get('userbank');
$page     = new Page($phrases['bans']);

try
{
  if(!$userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_BANS', 'ADMIN_EDIT_ALL_BANS', 'ADMIN_EDIT_GROUP_BANS', 'ADMIN_EDIT_OWN_BANS', 'ADMIN_BAN_PROTESTS', 'ADMIN_BAN_SUBMISSIONS')))
    throw new Exception('Access Denied');
  if($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    switch($_POST['action'])
    {
      case 'add':
        BansWriter::add($_POST['name'], $_POST['type'], $_POST['steam'], $_POST['ip'], $_POST['length'], $_POST['reason'] == 'other' ? $_POST['reason_other'] : $_POST['reason']);
        break;
      case 'import':
        BansWriter::import($_FILES['file']['name'], $_FILES['file']['tmp_name']);
    }
    
    Util::redirect();
  }
  
  $counts_reader               = new CountsReader();
  $protests_reader             = new ProtestsReader();
  $submissions_reader          = new SubmissionsReader();
  
  $counts                      = $counts_reader->executeCached(ONE_MINUTE      * 5);
  $protests                    = $protests_reader->executeCached(ONE_MINUTE    * 5);
  $submissions                 = $submissions_reader->executeCached(ONE_MINUTE * 5);
  
  $protests_reader->archive    = true;
  $submissions_reader->archive = true;
  $archived_protests           = $protests_reader->executeCached(ONE_MINUTE    * 5);
  $archived_submissions        = $submissions_reader->executeCached(ONE_MINUTE * 5);
  
  $page->assign('permission_add_bans',        $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_ADD_BANS')));
  $page->assign('permission_edit_bans',       $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_EDIT_ALL_BANS', 'ADMIN_EDIT_GROUP_BANS', 'ADMIN_EDIT_OWN_BANS')));
  $page->assign('permission_import_bans',     $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_IMPORT_BANS')));
  $page->assign('permission_list_comments',   $userbank->is_admin());
  $page->assign('permission_protests',        $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_PROTESTS')));
  $page->assign('permission_submissions',     $userbank->HasAccess(array('ADMIN_OWNER', 'ADMIN_BAN_SUBMISSIONS')));
  $page->assign('protests',                   $protests);
  $page->assign('submissions',                $submissions);
  $page->assign('archived_protests',          $archived_protests);
  $page->assign('archived_submissions',       $archived_submissions);
  $page->assign('total_protests',             $counts['protests']);
  $page->assign('total_submissions',          $counts['submissions']);
  $page->assign('total_archived_protests',    $counts['archived_protests']);
  $page->assign('total_archived_submissions', $counts['archived_submissions']);
  $page->display('page_admin_bans');
}
catch(Exception $e)
{
  $page->assign('error', $e->getMessage());
  $page->display('page_error');
}
?>
