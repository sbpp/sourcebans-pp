<?php
header('Content-Type: application/rss+xml; charset=UTF-8');
require_once 'api.php';

$bans  = SB_API::getBans(SB_API::getSetting('banlist.bansperpage'));
$host  = $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$title = SB_API::getSetting('template.title');
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?php echo $title; ?></title>
    <link>http://<?php echo $host;?></link>
    <description><?php echo $title; ?></description>
    <language>en-us</language>
    <pubDate><?php echo date('r'); ?></pubDate>
    <atom:link href="http://<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']; ?>" rel="self" type="application/rss+xml" />
<?php
foreach($bans['list'] as $id => $ban)
{
  if($ban['type'] == IP_BAN_TYPE)
    $title = $ban['ip'];
  else if(empty($ban['name']))
    $title = $ban['steam'];
  else
    $title = $ban['name'];
?>
    <item>
      <title><?php echo $title; ?></title>
      <link>http://<?php echo $host; ?>/banlist.php?search=<?php echo $id; ?>&amp;type=banid</link>
      <description><?php echo $ban['reason']; ?></description>
      <guid>http://<?php echo $host; ?>/banlist.php?search=<?php echo $id; ?>&amp;type=banid</guid>
    </item>
<?php
}
?>
  </channel>
</rss>