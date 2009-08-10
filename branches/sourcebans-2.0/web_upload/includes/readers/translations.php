<?php
require_once READER;

class TranslationsReader extends SBReader
{
  public $language;
  
  public function prepare()
  {  }
  
  public function &execute()
  {
    $db           = Env::get('db');
    
    /**
     * If specified language does not exist, fall back to English
     */
    if(!file_exists(LANGUAGES_DIR . $this->language . '.lang'))
      $this->language = 'en';
    
    /**
     * Fetch translations
     */
    $translations = Util::parse_ini_file(LANGUAGES_DIR . $this->language . '.lang');
    
    SBPlugins::call('OnGetTranslations', &$translations, $this->language);
    
    return $translations;
  }
}
?>