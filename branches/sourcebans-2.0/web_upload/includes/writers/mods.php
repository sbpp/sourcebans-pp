<?php
require_once READERS_DIR . 'mods.php';
require_once READERS_DIR . 'counts.php';

class ModsWriter
{
  /**
   * Adds a mod
   *
   * @param  string $name    The name of the mod
   * @param  string $folder  The folder of the mod
   * @param  string $icon    The icon of the mod
   * @param  bool   $enabled Whether or not the mod is enabled
   * @return The id of the added mod
   */
  public static function add($name, $folder, $icon, $enabled = true)
  {
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    if(empty($name)   || !is_string($name))
      throw new Exception('Invalid name supplied.');
    if(empty($folder) || !is_string($folder))
      throw new Exception('Invalid mod folder supplied.');
    if(empty($icon)   || !is_string($icon))
      throw new Exception('Invalid icon filename supplied.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_mods (name, folder, icon, enabled)
                  VALUES      (?, ?, ?, ?)',
                  array($name, $folder, $icon, $enabled ? 1 : 0));
    
    $id          = $db->Insert_ID();
    $mods_reader = new ModsReader();
    $mods_reader->removeCacheFile();
    
    $counts_reader   = new CountsReader();
    $counts_reader->removeCacheFile(true);
    
    SBPlugins::call('OnAddMod', $id, $name, $folder, $icon, $enabled);
    
    return $id;
  }
  
  
  /**
   * Deletes a mod
   *
   * @param integer $id The id of the mod to delete
   */
  public static function delete($id)
  {
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    if(empty($id)     || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    
    $db->Execute('DELETE FROM ' . Env::get('prefix') . '_mods
                  WHERE       id = ?',
                  array($id));
    
    $mods_reader = new ModsReader();
    $mods_reader->removeCacheFile();
    
    $counts_reader   = new CountsReader();
    $counts_reader->removeCacheFile(true);
    
    SBPlugins::call('OnDeleteMod', $id);
  }
  
  
  /**
   * Edits a mod
   *
   * @param integer $id      The id of the mod to edit
   * @param string  $name    The name of the mod
   * @param string  $folder  The folder of the mod
   * @param string  $icon    The icon of the mod
   * @param bool    $enabled Whether or not the mod is enabled
   */
  public static function edit($id, $name, $folder, $icon, $enabled)
  {
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    if(empty($id)     || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    if(empty($name)   || !is_string($name))
      throw new Exception('Invalid name supplied.');
    if(empty($folder) || !is_string($folder))
      throw new Exception('Invalid mod folder supplied.');
    if(empty($icon)   || !is_string($icon))
      throw new Exception('Invalid icon filename supplied.');
    
    $db->Execute('UPDATE ' . Env::get('prefix') . '_mods
                  SET    name    = ?,
                         folder  = ?,
                         icon    = ?,
                         enabled = ?
                  WHERE  id      = ?',
                  array($name, $folder, $icon, $enabled ? 1 : 0, $id));
    
    $mods_reader = new ModsReader();
    $mods_reader->removeCacheFile();
    
    $counts_reader   = new CountsReader();
    $counts_reader->removeCacheFile(true);
    
    SBPlugins::call('OnEditMod', $id, $name, $folder, $icon, $enabled);
  }
}
?>