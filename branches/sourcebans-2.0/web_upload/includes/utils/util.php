<?php
/**
 * This file contains general Utils
 * 
 * @author $LastChangedBy$
 * @version $LastChangedRevision$
 * @copyright http://www.SteamFriends.com
 * @package SourceBans
 * $Id$
 */

/**
 * This class contains general Utils for SourceBans
 * 
 */
class Util
{
  /**
   * Checks the input variable to see if it is a valid Integer
   *
   * @param mixed $str This is the variable to check
   * @param bool $allowNull Should we allow the int to be null
   * @param bool $allowNegative Allow the integer to be negative?
   * @param bool $allowZero Allow the integer to be zero?
   * @param bool $allowPositive Allow the int to be positive
   * @throws SBValidationException when the given string isnt a valid integer
   * @static 
   */
  public static function validateInt(&$str, $allowNull = false, $allowNegative = false, $allowZero = false, $allowPositive = true)
  {
    /*require_once SHARE_DIR . 'exceptions/exception.sbvalidation.php';
  
    if(is_null($str)) {
      if(!$allowNull) {
        throw new SBValidationException('Integer is null when not allowed to be');
      }
    } else {
      $str = (int)$str;
      if($str < 0) {
        if(!$allowNegative) {
          throw new SBValidationException('Integer is negative when not allowed to be');
        }
      } else if(!$str) {
        if(!$allowZero) {
          throw new SBValidationException('Integer is zero when not allowed to be');
        }
      } else {
        if(!$allowPositive) {
          throw new SBValidationException('Integer is positive when not allowed to be');
        }
      }
    }*/
  }
  
  
  /**
   * Searches an array recursively
   *
   * @param mixed $needle The value to find
   * @param array $haystack The array to search in
   */
  public static function array_search_recursive($needle, $haystack)
  {
    if(!is_array($haystack))
      return false;
    
    $path = array();
    foreach($haystack as $key => $val)
    {
      if(is_array($val) && $subPath = array_search_recursive($needle, $val))
        return array_merge($path, array($key), $subPath);
      elseif($val == $needle)
      {
        $path[] = $key;
        return $path;
      }
    }
    return false;
  }
  
  
  /**
   * This will sort a collection based on the collection(array()) values
   *
   * @param array $array The array to sort
   * @param integer $column the column to sort by
   * @param integer $order The order to sort the array
   * @param unknown_type $first
   * @param unknown_type $last
   * @static 
   * @author Luman (http://snipplr.com/users/luman)
   */
  public static function array_qsort(&$array, $column=0, $order=SORT_ASC, $first=0, $last= -2)
  {   
    $keys = array_keys($array);
    if($last == -2) $last = count($array) - 1;
    if($last > $first)
    {
      $alpha     = $first;
      $omega     = $last;
      $key_alpha = $keys[$alpha];
      $key_omega = $keys[$omega];
      $guess     = $array[$key_alpha][$column];
      while($omega >= $alpha)
      {
        if($order == SORT_ASC)
        {
          while($array[$key_alpha][$column] < $guess) { $key_alpha = $keys[++$alpha]; }
          while($array[$key_omega][$column] > $guess) { $key_omega = $keys[--$omega]; }
        }
        else
        {
          while($array[$key_alpha][$column] > $guess) { $key_alpha = $keys[++$alpha]; }
          while($array[$key_omega][$column] < $guess) { $key_omega = $keys[--$omega]; }
        }
        if($alpha > $omega) break;
        $temporary = $array[$key_alpha];
        $array[$key_alpha] = $array[$key_omega];
        $key_alpha = $keys[++$alpha];
        $array[$key_omega] = $temporary;
        if(--$omega > 0)
          $key_omega = $keys[$omega];
      }
      self::array_qsort($array, $column, $order, $first, $omega);
      self::array_qsort($array, $column, $order, $alpha, $last);
    }
  }
  
  
  /**
   * Converts seconds into string format
   *
   * @param integer $sec the amount of seconds
   * @param bool $textual Should we show Mo, Wk, etc or just 00:00:00
   * @return string
   */
  public static function SecondsToString($sec, $textual = true)
  {
    if($textual)
    {
      $desc = array('mo', 'wk', 'd', 'hr', 'min', 'sec');
      $div  = array(2592000, 604800, 86400, 3600, 60, 1);
      $ret  = '';
      for($i = 0; $i < count($div); $i++)
      {
        if(($cou = round($sec / $div[$i])) > 0)
        {
          $ret .= $cou . ' ' . $desc[$i] . ', ';
          $sec %= $div[$i];
        }
      }
      $ret  = substr($ret, 0, strlen($ret) - 2);
    }
    else
    {
      $hours = floor($sec / 60 / 60);
      $sec  -= $hours * 60 * 60;
      $mins  = floor($sec / 60);
      $secs %= 60;
      $ret   = $hours . ':' . $mins . ':' . $secs;
    }
    return $ret;
  }
  
  
  /**
   * Truncate string if too long
   *
   * @param string $text The string to truncate
   * @param integer $len The maximum length before truncating
   * @param bool $byword Truncate to the last space
   * @return string
   */
  public static function trunc($string, $length = 80, $etc = '...', $break_words = false, $middle = false) 
  {
    if(!$length)
      return '';
    
    if(utf8_strlen($string) <= $length)
      return $string;
    
    $length -= min($length, utf8_strlen($etc));
    
    if(!$break_words && !$middle)
      $string = preg_replace('/\s+?(\S+)?$/', '', utf8_substr($string, 0, $length + 1));
    if(!$middle)
      return utf8_substr($string, 0, $length)     . $etc;
    else
      return utf8_substr($string, 0, $length / 2) . $etc . utf8_substr($string, -$length / 2);
  }
  
  
  /**
   * Get the size of a directory
   *
   * @param string $path The path to the directory
   * @return array
   */
  public static function getDirectorySize($path)
  {
    $size   = 0;
    $count  = 0;
    $dirs   = 0;
    if($dir = opendir($path))
    {
      while(($file = readdir($dir)) !== false)
      {
        $path     .= '/' . $file;
        if($file != '.' && $file != '..' && !is_link($path))
        {
          if(is_dir($path))
          {
            $dirsize = self::getDirectorySize($path);
            $size   += $dirsize['size'];
            $count  += $dirsize['count'];
            $dirs   += $dirsize['dirs'] + 1;
          }
          else if(is_file($path))
          {
            $size   += filesize($path);
            $count++;
          }
        }
      }
      closedir($dir);
    }
    
    return array('size'  => $size,
                 'count' => $count,
                 'dirs'  => $dirs);
  }
  
  
  /**
   * Formats a size in English units
   *
   * @param integer $size The size in bytes
   * @param integer $round The amount of decimals to round to
   * @return string
   */
  public static function formatSize($size, $round = 2)
  {
    $sizes = array('bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    for($i = 0; $size > 1024 && $i < count($sizes) - 1; $i++) $size /= 1024;
    return round($size, $round) . ' ' . $sizes[$i];
  }
  
  
  /**
   * Sends an e-mail using PHPMailer
   *
   * @param mixed  $to      The e-mail address(es) to send the e-mail to. A string to specify a single address, or an array for multiple addresses
   * @param string $from    The e-mail address to send the e-mail from
   * @param string $subject The e-mail subject
   * @param string $message The e-mail message
   */
  public static function mail($to, $from, $subject, $message)
  {
    $config = Env::get('config');
    $mail   = new PHPMailer(true);
    
    // If SMTP is enabled
    if($config['email.smtp'] == 1)
    {
      $mail->IsSMTP();
      $mail->Host = $config['email.host'];
      $mail->Port = $config['email.port'];
      
      // If SMTP username and password have been filled in
      if(!empty($config['email.username']) && !empty($config['email.password']))
      {
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['email.username'];
        $mail->Password   = $config['email.password'];
      }
      // If SMTP secure option has been chosen
      if(!empty($config['email.secure']))
        $mail->SMTPSecure = $config['email.secure'];
    }
    // If $to is an array, add all addresses
    if(is_array($to))
    {
      foreach($to as $address)
        $mail->AddAddress($address);
    }
    else
      $mail->AddAddress($to);
    
    $mail->Subject = $subject;
    $mail->MsgHTML($message);
    $mail->SetFrom($from);
    $mail->Send();
  }
  
  
  /**
   * Parses an INI file with no interpretation of value content
   * 
   * @param  string $file The INI file to parse
   * @author Jean-Jacques Guegan (http://mach13.com/loose-and-multiline-parse_ini_file-function-in-php)
   */
  public static function parse_ini_file($file)
  {
    $matches =
    $result  = array();
    
    $a       = &$result;
    $s       = '\s*([[:alnum:]_\- \*]+?)\s*';
    
    preg_match_all('#^\s*((\[' . $s . '\])|(("?)' . $s . '\\5\s*=\s*("?)(.*?)\\7))\s*(;[^\n]*?)?$#ms', @file_get_contents($file), $matches, PREG_SET_ORDER);
    
    foreach($matches as $match)
    {
      if(empty($match[2]))
        $a[$match[6]] = $match[8];
      else
        $a            = &$result[$match[3]];
    }
    
    return $result;
  }
  
  
  /**
   * Redirects to the given page
   */
  public static function redirect($url = '')
  {
    header('Location: ' . empty($url) ? $_SERVER['HTTP_REFERER'] : $url);
  }
  
  
  /**
   * Clears the database and template caches
   */
  public static function clearCache()
  {
    $page = new Page();
    
    foreach(scandir(CACHE_DIR) as $file)
      if(is_file(CACHE_DIR . $file))
        unlink(CACHE_DIR . $file);
    
    foreach(scandir($page->compile_dir) as $file)
      if(is_file($page->compile_dir . '/' . $file))
        unlink($page->compile_dir . '/' . $file);
  }
}
?>