<?php
// This test will run specific translation calls through the API
class CApiTranslations extends CTest implements ITest
{
  public function runTest()
  {
    // Fetch translations
    $translations = array(
      'en' => SB_API::getTranslations('en'),
      'nl' => SB_API::getTranslations('nl'),
      'de' => SB_API::getTranslations('de')
    );
    $total_count  = count($translations['en']['phrases']);
    
    foreach($translations as $lang => $translation)
    {
      $count = count($translation['phrases']);
      fwrite(STDOUT, '  - Phrase count for "' . $lang . '": ' . $count . PHP_EOL);
      
      if($count == $total_count)
        continue;
      
      foreach($translations['en']['phrases'] as $name => $value)
      {
        if(!isset($translation['phrases'][$name]))
          fwrite(STDOUT, '    - Missing phrase "' . $name . '".' . PHP_EOL);
      }
    }
  }
}

return new CApiTranslations('API Translations (api.translations.php)');
?>