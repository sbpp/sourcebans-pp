<?php
$comments = $GLOBALS['db']->GetAll('SELECT cid, added, edittime
                                    FROM   ' . DB_PREFIX . '_comments');

$res = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_comments
                                DROP        added,
                                DROP        edittime');
if(!$res)
  return false;

$res = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_comments
                                ADD         added int(11) NOT NULL DEFAULT "0" AFTER commenttxt,
                                ADD         edittime int(11) NOT NULL DEFAULT "0" AFTER editaid');
if(!$res)
  return false;

$res = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_comments
                                CHANGE      added added int(11) NOT NULL');
if(!$res)
  return false;

$res = $GLOBALS['db']->Execute('ALTER TABLE ' . DB_PREFIX . '_comments
                                CHANGE      edittime edittime int(11) NULL DEFAULT NULL');
if(!$res)
  return false;

foreach($comments AS $comment)
{
  if(empty($comment['edittime']))
  {
    $GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_comments
                             SET    added = ?
                             WHERE  cid   = ?',
                            array(strtotime($comment['added']), $comment['cid']));
  }
  else
  {
    $GLOBALS['db']->Execute('UPDATE ' . DB_PREFIX . '_comments
                             SET    added    = ?,
                                    edittime = ?
                             WHERE  cid      = ?',
                            array(strtotime($comment['added']), strtotime($comment['edittime']), $comment['cid']);
  }
}

return true;