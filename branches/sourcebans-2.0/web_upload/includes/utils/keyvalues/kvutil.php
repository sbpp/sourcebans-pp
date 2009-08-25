<?php
/**
 * Namespace: KVUtil
 * @author Bradley Brizad Worrell-Smith
 * @todo Add response codes to file error exception
 * @todo Make accessors to parse and wrtie via strings and not just files.
 * Constant: KVREADER_DEBUG	bool	Used to turn on debuging
 * <code>define("KVREADER_DEBUG", true);</code>
 * 
 * To use KVReader to read a config:
 * <code>
 * $reader = new KVReader("config.cfg");
 * echo var_dump($reader->Values);
 * </code>
 * 
 * To write a new config from a confile file that may or may not exist:
 * <code>
 * $reader = new KVReader("config.cfg", true);
 * // make changes to $reader->Values
 * $reader->saveConfig();
 * </code>
 */

/**
 * Class: KVReader
 * Used to read Valve Config key files for parsing
 * and write new config file based on an associative array
 * @property mixed  $Values  Array of recursive keys, values, and sections in the config
 */
class KVReader
{
  public $Values = array();
  private $fs;
  private $file;
  const SEG_ROOT = 0;
  const SEG_TEIR = 1;
  const SEG_STRING = 2;
  const SEG_COMMENT = 3;
  const SEG_ENTITY = 4;

  /**
   * Function: __construct
   * Constructor for KVReader to initilaize and/or read Valve Config Keys
   * @param String   $iFile    File to read and/or write to
   * @param Bool     $iNoErr   Ignore Exception error on reading file in case file doesn't exist (default = false)
   * @return void
   */
  public function __construct($iFile, $iNoErr = false)
  {
    $this->file = $iFile;

    try
    {
      if (!$this->fs = @fopen($this->file, "r"))
        throw new Exception('File could not be found and/or opened!');
    } 

    catch (Exception $err)
    {
      if ($iNoErr)
        return;
      
      echo '<h3>Exception: </h3>', '<b>Class Name:</b>' . get_class($this) . '<br />', '<b>Error message:</b> ' . $err->getMessage() . ' <b>Code:</b> ' . $err->getCode() . '<br />', '<b>File and line:</b> ' . $err->getFile() . '(' . $err->getLine() . ')<br />';
    }

    if ($this->fs)
    {
      $this->Values = $this->readSegment();

      fclose($this->fs);
    }
  }

  /**
   * function: saveConfig
   * @return Bool	True if sucessful, false if failed
   */
  public function saveConfig()
  {
    try
    {
      if (!$this->fs = fopen($this->file, "w+"))
        throw new Exception('File could not be found and/or opened for writing!');
    } 

    catch (Exception $err)
    {
      echo '<h3>Exception: </h3>', '<b>Class Name:</b>' . get_class($this) . '<br />', '<b>Error message:</b> ' . $err->getMessage() . ' <b>Code:</b> ' . $err->getCode() . '<br />', '<b>File and line:</b> ' . $err->getFile() . '(' . $err->getLine() . ')<br />';
      return false;
    }

    return $this->writeSegment($this->Values, 0);
  }
  
  /**
   * Private function: WriteSegment
   * Used to recusivly take the array values and export to the file stream
   * @param Array   $iArray     Array to process, if array contains sub arrarys, it will call itself
   * @param Int     $iTeirNum   Teir number to indicate indentation from the root. Start at 0
   * @return bool               True if sucsessful, false if failed
   */
  private function writeSegment($iArray, $iTeirNum)
  {
    $indent = str_repeat(chr(9), $iTeirNum);

    foreach ($iArray as $key => $value)
    {
      if (is_string($value))
      {
        if (defined('KVREADER_DEBUG'))
          echo "Teir:$iTeirNum  Key:$key  Value:$value  <br />";

        $data = $indent . '"' . $key . '"' . chr(9) . '"' . $value . "\"\n";
        fwrite($this->fs, $data);
      }

      if (is_array($value))
      {
        if (defined('KVREADER_DEBUG'))
          echo "Teir:$iTeirNum  Section:$key  <br />";

        if ($iTeirNum > 0)
          $key = '"' . $key . '"';

        $data = ($iTeirNum > 0 ? "\n" : "") . $indent . "$key\n$indent{\n";
        fwrite($this->fs, $data);

        $this->writeSegment($value, ($iTeirNum + 1));

        fwrite($this->fs, $indent . "}\n");
      }
    }

    return true;
  }

  /**
   * Private function: readSegment
   * Recursivly parses text in the file stream and stores into an associative array
   * @param Integer $iSegType    ENum: Segment Type, detmines how the segment is processes/parsed
   * @param String  $iQualifier  Ending character or double character that ends a segment
   * @return Mixed               Array or String depending on the Segment Type
   */
  private function readSegment($iSegType = self::SEG_ROOT, $iQualifier = null)
  {
    if (!$this->fs)
      return null;

    $byte = null;
    $ret = null;
    $segArray = array();

    if ($iSegType == self::SEG_STRING)
      $ret = "";

    while (!feof($this->fs))
    {
      if ($iSegType == self::SEG_ROOT && empty($segArray))
        $iSegType = self::SEG_ENTITY;

      $last = $byte;
      $byte = fread($this->fs, 1);

      if ($iSegType == self::SEG_ENTITY && $byte == "{")
      {
        $segArray[] = $ret;
        $iSegType = self::SEG_ROOT;
      }

      if ($iSegType != self::SEG_ENTITY && $iSegType != self::SEG_STRING && $iSegType != self::SEG_COMMENT)
      {
        if ($byte == "{")
        {
          $ret = $this->readSegment(self::SEG_TEIR, "}");
          $segArray[] = $ret;
        }

        if ($byte == "/" && $last == "/")
          $this->readSegment(self::SEG_COMMENT, chr(13));

        if ($byte == "*" && $last == "/")
          $this->readSegment(self::SEG_COMMENT, "*/");

        if ($byte == '"' || $byte == "'")
        {
          $ret = $this->readSegment(self::SEG_STRING, $byte);
          $segArray = array_merge($segArray, array($ret));
        }
      }

      if ($byte == $iQualifier || ($last . $byte) == $iQualifier)
      {
        if ($iSegType == self::SEG_TEIR || $iSegType == self::SEG_ROOT)
        {
          $ret = null;
          $keyArray = array();

          for ($lp = 0; $lp < count($segArray); $lp++)
          {
            if (($lp + 1) < count($segArray) && is_string($segArray[$lp]))
            {
              $keyArray[$segArray[$lp]] = $segArray[$lp + 1];

              $lp++;
              continue;
            }

            $keyArray[] = $segArray[$lp];
          }

          $segArray = $keyArray;
        }

        break;
      }

      if ($iSegType == self::SEG_STRING || $iSegType == self::SEG_ENTITY)
        $ret .= $byte;
    }

    if (defined('KVREADER_DEBUG'))
    {
      echo (ftell($this->fs) . " - Type:$iSegType Qual:$iQualifier - ");

      if ($iSegType == 0 OR $iSegType == 1)
        echo var_dump($segArray);
      else
        echo var_dump($ret);

      echo "<br />";
    }

    switch ($iSegType)
    {
      case self::SEG_TEIR:
      case self::SEG_ROOT:
        return $segArray;
      default:
        return $ret;
    }
  }
}
?>