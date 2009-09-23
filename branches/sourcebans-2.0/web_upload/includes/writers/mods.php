<?php
require_once READERS_DIR . 'mods.php';

class ModsWriter
{
  /**
   * Adds a mod
   *
   * @param  string $name    The name of the mod
   * @param  string $folder  The folder of the mod
   * @param  string $icon    The icon of the mod
   * @return The id of the added mod
   */
  public static function add($name, $folder, $icon)
  {
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    if(empty($name)   || !is_string($name))
      throw new Exception('Invalid name supplied.');
    if(empty($folder) || !is_string($folder))
      throw new Exception('Invalid mod folder supplied.');
    if(empty($icon)   || !is_string($icon))
      throw new Exception('Invalid icon filename supplied.');
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_mods (name, folder, icon)
                  VALUES      (?, ?, ?)',
                  array($name, $folder, $icon));
    
    $id            = $db->Insert_ID();
    $mods_reader   = new ModsReader();
    $mods_reader->removeCacheFile();
    
    SBPlugins::call('OnAddMod', $id, $name, $folder, $icon);
    
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
    
    if(empty($id) || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    
    $db->Execute('DELETE FROM ' . Env::get('prefix') . '_mods
                  WHERE       id = ?',
                  array($id));
    
    $mods_reader   = new ModsReader();
    $mods_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteMod', $id);
  }
  
  
  /**
   * Edits a mod
   *
   * @param integer $id      The id of the mod to edit
   * @param string  $name    The name of the mod
   * @param string  $folder  The folder of the mod
   * @param string  $icon    The icon of the mod
   */
  public static function edit($id, $name = null, $folder = null, $icon = null)
  {
    $db      = Env::get('db');
    $phrases = Env::get('phrases');
    
    $mod     = array();
    
    if(empty($id)         || !is_numeric($id))
      throw new Exception('Invalid ID supplied.');
    if(!is_null($name)   && is_string($name))
      $mod['name']   = $name;
    if(!is_null($folder) && is_string($folder))
      $mod['folder'] = $folder;
    if(!is_null($icon)   && is_string($icon))
      $mod['icon']   = $icon;
    
    $db->AutoExecute(Env::get('prefix') . '_mods', $mod, 'UPDATE', 'id = ' . $id);
    
    $mods_reader = new ModsReader();
    $mods_reader->removeCacheFile();
    
    SBPlugins::call('OnEditMod', $id, $name, $folder, $icon);
  }
}
?>