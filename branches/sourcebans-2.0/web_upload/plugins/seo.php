<?php
require_once BASE_PATH . 'api.php';

class SB_SEO extends SBPlugin
{
  public static function OnBuildQuery(&$url)
  {
    // Converts page_mode.php?name1=value1&name2=value2 to /page/mode/name1=value1/name2=value2
    list($file, $query) = explode('.php?', $url);
    $url                = str_replace('_', '/', $file) . '/' . str_replace('&amp;', '/', $query);
  }
  
  public static function OnBuildUrl(&$url)
  {
    // Converts page_mode.php?name1=value1&name2=value2 to /page/mode/name1=value1/name2=value2
    list($file, $query) = explode('.php',  $url);
    $url                = str_replace('_', '/', $file);
    
    if(!empty($query))
      $url .= '/' . str_replace('&amp;', '/', substr($query, 1));
  }
}

new SB_SEO(
  'Search Engine Optimization',
  'Tsunami',
  'Converts URLs into search engine optimized URLs.',
  SB_VERSION,
  'http://www.sourcebans.net'
);
?>