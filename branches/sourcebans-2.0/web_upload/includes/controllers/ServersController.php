<?php
class ServersController extends BaseController
{
  protected function _title()
  {
    return $this->_registry->user->language->servers;
  }
  
  
  public function index()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    
    $servers  = $this->_registry->servers;
    
    $order    = isset($uri->order) ? $uri->order : 'asc';
    $sort     = isset($uri->sort)  ? $uri->sort  : 'game';
    
    //Util::object_qsort($servers, $sort, $order == 'desc' ? SORT_DESC : SORT_ASC);
    
    foreach($servers as $server)
    {
      $server_query    = new ServerQuery($server->ip, $server->port);
      $players         = $server_query->getPlayers();
      
      Util::array_qsort($players, 'score', SORT_DESC);
      $server->players = $players;
    }
    
    $template->servers = $servers;
    $template->order   = $order;
    $template->sort    = $sort;
    $template->display('servers');
  }
  
  
  public function admins()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'LIST_SERVERS')))
      throw new SBException($language->access_denied);
    if(!isset($uri->id) || !is_numeric($uri->id))
      throw new SBException($language->invalid_id);
    
    $admins = $this->_registry->admins;
    
    $template->action_title = ucwords($language->server_admins);
    $template->admins       = Util::object_filter($admins, 'server', $uri->id);
    $template->display('admin_servers_admins');
  }
  
  
  public function config()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER')))
      throw new SBException($language->access_denied);
    
    // TODO: Translate "Server Config"
    $template->action_title = 'Server Config';
    $template->display('admin_servers_config');
  }
  
  
  public function edit()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $uri      = $this->_registry->uri;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(array('OWNER', 'EDIT_SERVERS')))
      throw new SBException($language->access_denied);
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      try
      {
        if($_POST['rcon'] != $_POST['rcon_confirm'])
          throw new SBException($language->passwords_do_not_match);
        
        $server          = $this->_registry->servers[$_POST['id']];
        $server->enabled = isset($_POST['enabled']);
        $server->groups  = $_POST['groups'];
        $server->ip      = $_POST['ip'];
        $server->game    = $_POST['game'];
        $server->port    = $_POST['port'];
        
        if($_POST['rcon'] != 'xxxxxxxxxx')
          $server->rcon = $_POST['rcon'];
        
        $server->save();
        
        exit(json_encode(array(
          'redirect' => new SBUri('admin', 'servers')
        )));
      }
      catch(Exception $e)
      {
        exit(json_encode(array(
          'error' => $e->getMessage()
        )));
      }
    }
    
    $template->action_title = ucwords($language->edit_server);
    $template->groups       = $this->_registry->server_groups;
    $template->games        = $this->_registry->games;
    $template->server       = $this->_registry->servers[$uri->id];
    $template->display('admin_servers_edit');
  }
  
  
  public function rcon()
  {
    $language = $this->_registry->user->language;
    $template = $this->_registry->template;
    $user     = $this->_registry->user;
    
    if(!$user->hasAccess(SM_RCON . SM_ROOT))
      throw new SBException($language->access_denied);
    
    // TODO: Translate "Server RCON"
    $template->action_title = 'Server RCON';
    $template->display('admin_servers_rcon');
  }
}