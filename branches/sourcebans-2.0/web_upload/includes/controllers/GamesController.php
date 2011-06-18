<?php
class GamesController extends BaseController
{
  protected function _title()
  {
    return $this->_registry->user->language->games;
  }
  
  
  public function index() {}
  
  
  public function edit()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'EDIT_GAMES')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        $game         = SB_API::getGame($_POST['id']);
        $game->name   = $_POST['name'];
        $game->folder = $_POST['folder'];
        $game->icon   = $_POST['icon'];
        $game->save();
        
        exit(json_encode(array(
          'redirect' => new SBUri('admin', 'games')
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $template->action_title = ucwords($language->edit_game);
    $template->game         = SB_API::getGame($uri->id);
    $template->display('admin_games_edit');
  }
}