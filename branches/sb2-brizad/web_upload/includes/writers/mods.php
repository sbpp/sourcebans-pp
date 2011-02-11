<?php
require_once READERS_DIR . 'mods.php';

class ModsWriter
{
  /**
   * Adds a mod
   *
   * @param  string  $name    The name of the mod
   * @param  string  $folder  The folder of the mod
   * @param  string  $icon    The icon of the mod
   * @return integer The id of the added mod
   */
  public static function add($name, $folder, $icon)
  {
    $db      = SBConfig::getEnv('db');
    $phrases = SBConfig::getEnv('phrases');
    
    if(empty($name)   || !is_string($name))
      throw new Exception($phrases['invalid_name']);
    if(empty($folder) || !is_string($folder))
      throw new Exception($phrases['invalid_folder']);
    if(empty($icon)   || !is_string($icon))
      throw new Exception($phrases['invalid_filename']);
    
    $db->Execute('INSERT INTO ' . SBConfig::getEnv('prefix') . '_mods (name, folder, icon)
                  VALUES      (?, ?, ?)',
                  array($name, $folder, $icon));
    
    $id          = $db->Insert_ID();
    $mods_reader = new ModsReader();
    $mods_reader->removeCacheFile();
    
    SBPlugins::call('OnAddMod', $id, $name, $folder, $icon);
    
    return $id;
  }
  
  
  /**
   * Deletes a mod
   *
   * @param integer $id The id of the mod to delete
   * @noreturn
   */
  public static function delete($id)
  {
    $db      = SBConfig::getEnv('db');
    $phrases = SBConfig::getEnv('phrases');
    
    if(empty($id) || !is_numeric($id))
      throw new Exception($phrases['invalid_id']);
    
    $db->Execute('DELETE FROM ' . SBConfig::getEnv('prefix') . '_mods
                  WHERE       id = ?',
                  array($id));
    
    $mods_reader = new ModsReader();
    $mods_reader->removeCacheFile();
    
    SBPlugins::call('OnDeleteMod', $id);
  }
  
  
  /**
   * Edits a mod
   *
   * @param integer $id     The id of the mod to edit
   * @param string  $name   The name of the mod
   * @param string  $folder The folder of the mod
   * @param string  $icon   The icon of the mod
   * @noreturn
   */
  public static function edit($id, $name = null, $folder = null, $icon = null)
  {
    $db      = SBConfig::getEnv('db');
    $phrases = SBConfig::getEnv('phrases');
    
    $mod     = array();
    
    if(empty($id)         || !is_numeric($id))
      throw new Exception($phrases['invalid_id']);
    if(!is_null($name)   && is_string($name))
      $mod['name']   = $name;
    if(!is_null($folder) && is_string($folder))
      $mod['folder'] = $folder;
    if(!is_null($icon)   && is_string($icon))
      $mod['icon']   = $icon;
    
    $db->AutoExecute(SBConfig::getEnv('prefix') . '_mods', $mod, 'UPDATE', 'id = ' . $id);
    
    $mods_reader = new ModsReader();
    $mods_reader->removeCacheFile();
    
    SBPlugins::call('OnEditMod', $id, $name, $folder, $icon);
  }
}
?>