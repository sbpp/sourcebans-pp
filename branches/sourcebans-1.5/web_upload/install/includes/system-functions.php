<?php
/**
 * Main functions for system
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
function HelpIcon($title, $text)
{
  return '<img align="absbottom" class="tip" src="../themes/default/images/admin/help.png" title="' .  $title . ' :: ' .  $text . '" />&nbsp;&nbsp;';
}

function Quote()
{
  static $quotes = array(
    array('Buy a new PC!', 'Viper'),
    array('I\'m not lazy! I just utilize technical resources!', 'Brizad'),
    array('I need to mow the lawn', 'sslice'),
    array('Like A Glove!', 'Viper'),
    array('You\'re a Noob and You Know It!', 'Viper'),
    array('Get your ass ingame', 'Viper'),
    array('Mother F***ing Pieces of Sh**', 'Viper'),
    array('Shut up Bam', '[Everyone]'),
    array('Hi OllyBunch', 'Viper'),
    array('Procrastination is like masturbation. Sure it feels good, but in the end you\'re only F***ing yourself!', '[Unknown]'),
    array('Rave\'s momma so fat she sat on the beach and Greenpeace threw her in', 'SteamFriend'),
    array('I\'m just getting a beer', 'Faith'),
    array('To be honest' . (isset($_SESSION['user']['user']) ? ' ' . $_SESSION['user']['user'] : '...') . ', I DON\'T CARE!', 'Viper'),
    array('Yams', 'teame06'),
    array('built in cheat 1.6 - my friend told me theres a cheat where u can buy a car door and run around and it makes u invincible....', 'gdogg'),
    array('I just join conversation when I see a chance to tell people they might be wrong, then I quickly leave, LIKE A BAT', 'BAILOPAN'),
    array('Let\'s just blame it on FlyingMongoose', '[Everyone]'),
    array('I wish my lawn was emo, so it would cut itself', 'SirTiger'),
  );
  list($text, $name) = $quotes[array_rand($quotes)];
  
  return '"' . $text . '" - <i>' . $name . '</i>';
}