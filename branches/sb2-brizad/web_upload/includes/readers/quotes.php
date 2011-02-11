<?php
require_once READER;
  
class QuotesReader extends SBReader
{
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db     = SBConfig::getEnv('db');
    
    // Fetch quotes
    $quotes = $db->GetAll('SELECT name, text
                           FROM   ' . SBConfig::getEnv('prefix') . '_quotes');
    
    list($quotes) = SBPlugins::call('OnGetQuotes', $quotes);
    
    return $quotes;
  }
}
?>