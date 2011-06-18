<?php
class IndexController extends BaseController
{
  public function index()
  {
    $router   = $this->_registry->router;
    $settings = $this->_registry->settings;
    
    $router->route(new SBUri(empty($settings->default_page) ?
      'dashboard' : $settings->default_page));
  }
}