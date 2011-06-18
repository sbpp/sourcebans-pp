<?php
class CommentsController extends BaseController
{
  public function index() {}
  
  
  public function add()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->is_admin())
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        $comment          = SB_API::createComment();
        $comment->ban_id  = $_POST['ban_id'];
        $comment->type    = $_POST['type'];
        $comment->message = $_POST['message'];
        $comment->save();
        
        exit(json_encode(array(
          'redirect' => $uri
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $template->action_title = ucwords($language->add_comment);
    $template->display('comments_add');
  }
  
  
  public function edit()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        $comment          = SB_API::getComment($_POST['id']);
        $comment->message = $_POST['message'];
        $comment->save();
        
        switch($_POST['type'])
        {
          case BAN_COMMENTS:
            $redirect = new SBUri('bans');
            break;
          case PROTEST_COMMENTS:
            $redirect = new SBUri('admin', 'bans') . '#protests/current';
            break;
          case SUBMISSION_COMMENTS:
            $redirect = new SBUri('admin', 'bans') . '#submissions/current';
            break;
          default:
            $redirect = $uri;
        }
        
        exit(json_encode(array(
          'redirect' => $redirect
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $template->action_title = ucwords($language->edit_comment);
    $template->comment      = SB_API::getComment($uri->id);
    $template->comments     = $comments;
    $template->display('comments_edit');
  }
}