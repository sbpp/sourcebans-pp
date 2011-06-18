<?php
/**
 * Listener to use when parsing a SourceMod Config file
 *
 * @author     SteamFriends, InterWave Studios, GameConnect
 * @copyright  (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link       http://www.sourcebans.net
 * @package    SourceBans
 * @subpackage SMC
 * @version    $Id$
 */
interface ISMCListener
{
  /**
   * Called when a section ends
   *
   * @return bool Whether or not to continue parsing
   */
  public function EndSection();
  
  /**
   * Called when a key value pair is parsed
   *
   * @param  string $key   Key
   * @param  string $value Value
   * @return bool          Whether or not to continue parsing
   */
  public function KeyValue($key, $value);
  
  /**
   * Called when a new section begins
   *
   * @param  string $name Section name
   * @return bool         Whether or not to continue parsing
   */
  public function NewSection($name);
}