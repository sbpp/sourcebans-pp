<?php
/**
 * Installer stylesheet
 *
 * @author     InterWave Studios
 * @copyright  SourceBans (C)2007-2011 InterWaveStudios.com.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage Installer
 * @version    $Id$
 */
header('Content-Type: text/css');

$agent  = null;
$agents = array(
  'MSIE 7.0'  => 'IE7',
  'MSIE 6.0'  => 'IE6',
  'Firefox/2' => 'FF2',
  'Firefox/1' => 'FF1',
);

foreach($agents as $k => $v)
{
  if(strpos($_SERVER['HTTP_USER_AGENT'], $k) === false)
    continue;
  
  $agent = $v;
  break;
}
?>
#install-progress {
  border: 1px solid #dedede;
  float: left;
  font-size: 10px;
  margin: 5px;
  padding: 5px;
  width: 175px;
}
#install-progress strong {
  text-decoration: underline;
}
#install-progress .current {
  font-weight: bold;
}
#install-progress .done {
  text-decoration: line-through;
}
#submit-main {
  clear: both;
}

.green {
  background-color: #8bd659;
}
.yellow {
  background-color: #d6cc59;
}
.red {
  background-color: #d65959;
}