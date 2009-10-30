<?php
require_once LIB_DIR . 'geoip/geoip.inc.php';

class CountriesWriter
{
  /**
   * Store the country code and name of an IP address
   *
   * @param  string $ip The IP address to store the country code and name for
   */
  public static function store($ip)
  {
    if(empty($ip) || !preg_match(IP_FORMAT, $ip))
      throw new Exception($phrases['invalid_ip']);
    
    $db    = Env::get('db');
    
    $geoip = geoip_open(LIB_DIR . 'geoip/GeoIP.dat', GEOIP_STANDARD);
    $code  = geoip_country_code_by_addr($geoip, $ip);
    $name  = geoip_country_name_by_addr($geoip, $ip);
    
    geoip_close($geoip);
    
    $db->Execute('INSERT INTO             ' . Env::get('prefix') . '_countries (ip, code, name)
                  VALUES                  (?, ?, ?)
                  ON DUPLICATE KEY UPDATE code = VALUES(code),
                                          name = VALUES(name)',
                  array($ip, $code, $name));
    
    SBPlugins::call('OnStoreCountry', $ip, $code, $name);
  }
}
?>