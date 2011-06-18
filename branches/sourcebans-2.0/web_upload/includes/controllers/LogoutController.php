<?php
class LogoutController extends BaseController
{
  public function index()
  {
    $this->_registry->user->logout();
    
    Util::redirect(new SBUri());
  }
}