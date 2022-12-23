<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector 
//
//  util.php - Front module. Server side. Common generic functions
//------------------------------------------------------------------------------
//  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
//------------------------------------------------------------------------------

// ###################################
// ## TimeZone processing start
// ###################################
$configFolderPath = "/home/pi/pialert/config/";
$config_file = "pialert.conf";
$logFolderPath = "/home/pi/pialert/front/log/";
$log_file = "pialert_front.log";


$fullConfPath = $configFolderPath.$config_file;

chmod($fullConfPath, 0777);

$config_file_lines = file($fullConfPath);
$config_file_lines_timezone = array_values(preg_grep('/^TIMEZONE\s.*/', $config_file_lines));
$timezone_line = explode("'", $config_file_lines_timezone[0]);
$Pia_TimeZone = $timezone_line[1];

$timeZone = "";

foreach ($config_file_lines as $line)
{    
  if( preg_match('/TIMEZONE(.*?)/', $line, $match) == 1 )
  {        
      if (preg_match('/\'(.*?)\'/', $line, $match) == 1) {          
        $timeZone = $match[1];
      }
  }
}

if($timeZone == "")
{
  $timeZone = "Europe/Berlin";
}

date_default_timezone_set($timeZone);

$date = new DateTime("now", new DateTimeZone($timeZone) );
$timestamp = $date->format('Y-m-d_H-i-s');


// ###################################
// ## TimeZone processing end
// ###################################


$FUNCTION = $_REQUEST['function'];
$SETTINGS = $_REQUEST['settings'];


if ($FUNCTION  == 'savesettings') {
  saveSettings();
}

//------------------------------------------------------------------------------
// Formatting data functions
//------------------------------------------------------------------------------
// Creates a PHP array from a string representing a python array (input format ['...','...'])
function createArray($input){

  // regex patterns
  $patternBrackets = '/(^\s*\[)|(\]\s*$)/';
  $patternQuotes = '/(^\s*\')|(\'\s*$)/';
  $replacement = '';

  // remove brackets
  $noBrackets = preg_replace($patternBrackets, $replacement, $input); 
  
  $options = array(); 

  // create array
  $optionsTmp = explode(",", $noBrackets);

  // remove quotes
  foreach ($optionsTmp as $item)
  {
    array_push($options, preg_replace($patternQuotes, $replacement, $item) );
  }
  
  return $options;
}

function formatDate ($date1) {
  return date_format (new DateTime ($date1) , 'Y-m-d   H:i');
}

function formatDateDiff ($date1, $date2) {
  return date_diff (new DateTime ($date1), new DateTime ($date2 ) )-> format ('%ad   %H:%I');
}

function formatDateISO ($date1) {
  return date_format (new DateTime ($date1),'c');
}

function formatEventDate ($date1, $eventType) {
  if (!empty ($date1) ) {
    $ret = formatDate ($date1);
  } elseif ($eventType == '<missing event>') {
    $ret = '<missing event>';
  } else {
    $ret = '<Still Connected>';
  }

  return $ret;
}

function formatIPlong ($IP) {
  return sprintf('%u', ip2long($IP) );
}


//------------------------------------------------------------------------------
// Others functions
//------------------------------------------------------------------------------
function checkPermissions($files)
{
  foreach ($files as $file)
  {
    // check access to database
    if(file_exists($file) != 1)
    {
      $message = "File '".$file."' not found or inaccessible. Correct file permissions, create one yourself or generate a new one in 'Settings' by clicking the 'Save' button.";
      displayMessage($message, TRUE);      
    }
  } 
}


function displayMessage($message, $logAlert = FALSE, $logConsole = TRUE, $logFile = TRUE, $logEcho = FALSE)
{
  global $logFolderPath, $log_file, $timestamp;

  // sanitize
  $message = str_replace(array("\n", "\r", PHP_EOL), '', $message);

  echo "<script>function escape(html, encode) {
    return html.replace(!encode ? /&(?!#?\w+;)/g : /&/g, '&amp;')  
      .replace(/\t/g, '')    
  }</script>";

  // Javascript Alert pop-up
  if($logAlert)
  {
    echo '<script>alert(escape("'.$message.'"));</script>';
  }

  // F12 Browser console
  if($logConsole)
  {
    echo '<script>console.log(escape("'.$message.'"));</script>';
  }

  //File
  if($logFile)
  {
    if(file_exists($logFolderPath.$log_file) != 1) // file doesn't exist, create one
    {
      $log = fopen($logFolderPath.$log_file, "w") or die("Unable to open file!");
    }else // file exists, append
    {
      $log = fopen($logFolderPath.$log_file, "a") or die("Unable to open file!");
    }

    fwrite($log, "[".$timestamp. "] " . $message.PHP_EOL."" );
    fclose($log);
  }

  //echo
  if($logEcho)
  {
    echo $message;
  }

}


function saveSettings()
{
  global $SETTINGS, $FUNCTION, $config_file, $fullConfPath, $configFolderPath, $timestamp;  



  // save in the file
  $new_name = $config_file.'_'.$timestamp.'.backup';
  $new_location = $configFolderPath.$new_name;

  // chmod($fullConfPath, 0755);

  if(file_exists( $fullConfPath) != 1)
  {    
    displayMessage('File "'.$fullConfPath.'" not found or missing read permissions. Creating a new <code>'.$config_file.'</code> file.', FALSE, TRUE, TRUE, TRUE);      
  }
  // create a backup copy    
  elseif (!copy($fullConfPath, $new_location))
  {      
    displayMessage("Failed to copy file ".$fullConfPath." to ".$new_location." <br/> Check your permissions to allow read/write access to the /config folder.", FALSE, TRUE, TRUE, TRUE);      
  }
  
       
  // generate a clean pialert.conf file
  $groups = [];

  $txt = $txt."#-----------------AUTOGENERATED FILE-----------------#\n";
  $txt = $txt."#                                                    #\n";
  $txt = $txt."#         Generated:  ".$timestamp."            #\n";
  $txt = $txt."#                                                    #\n";
  $txt = $txt."#   Config file for the LAN intruder detection app:  #\n";
  $txt = $txt."#      https://github.com/jokob-sk/Pi.Alert          #\n";
  $txt = $txt."#                                                    #\n";
  $txt = $txt."#-----------------AUTOGENERATED FILE-----------------#\n";

  // collect all groups
  foreach ($SETTINGS as $setting) { 
    if( in_array($setting[0] , $groups) == false) {
      array_push($groups ,$setting[0]);
    }
  }

  // go thru the groups and prepare settings to write to file
  foreach($groups as $group)
  {
    $txt = $txt."\n\n# ".$group;
    $txt = $txt."\n#---------------------------\n" ;
    foreach($SETTINGS as $setting)
    {
      if($group == $setting[0])
      {            
        if($setting[3] == 'text' or $setting[3] == 'password' or $setting[3] == 'readonly' or $setting[3] == 'selecttext')
        {
          $txt = $txt.$setting[1]."='".$setting[2]."'\n" ; 
        } elseif($setting[3] == 'integer' or $setting[3] == 'selectinteger')
        {
          $txt = $txt.$setting[1]."=".$setting[2]."\n" ; 
        } elseif($setting[3] == 'boolean')
        {
          $val = "False";
          if($setting[2] == 'true')
          {
            $val = "True";
          }
          $txt = $txt.$setting[1]."=".$val."\n" ; 
        }elseif($setting[3] == 'multiselect' or $setting[3] == 'subnets')
        {
          $temp = '[';
          foreach($setting[2] as $val)
          {
            $temp = $temp."'". $val."',";
          }
          $temp = substr_replace($temp, "", -1).']';  // close brackets and remove last comma ','
          $txt = $txt.$setting[1]."=".$temp."\n" ; 
        }            
      }
    }
  }

  $txt = $txt."\n\n";
  $txt = $txt."#-------------------IMPORTANT INFO-------------------#\n";
  $txt = $txt."#   This file is ingested by a python script, so if  #\n";      
  $txt = $txt."#        modified it needs to use python syntax      #\n";      
  $txt = $txt."#-------------------IMPORTANT INFO-------------------#\n";

  // open new file and write the new configuration      
  $newConfig = fopen($fullConfPath, "w") or die("Unable to open file!");
  fwrite($newConfig, $txt);
  fclose($newConfig);

  displayMessage("<br/>Settings saved to the <code>".$config_file."</code> file. 
    Backup of ".$config_file." created: <code>".$new_name."</code>.<br/>
    <b>Restart the container for the chanegs to take effect.</b>", 
    FALSE, TRUE, TRUE, TRUE);    

}

function getString ($codeName, $default, $pia_lang) {

  $result = $pia_lang[$codeName];

  if ($result )
  {
    return $result;
  }   

  return $default;
}

function getDateFromPeriod () {
  $period = $_REQUEST['period'];    
  return '"'. date ('Y-m-d', strtotime ('+1 day -'. $period) ) .'"';
}

function quotes ($text) {
  return str_replace ('"','""',$text);
}
    
function logServerConsole ($text) {
  $x = array();
  $y = $x['__________'. $text .'__________'];
}

function getNetworkTypes(){

  $array = array(
    "AP", "Gateway", "Powerline", "Switch", "WLAN", "PLC", "Router","USB LAN Adapter", "USB WIFI Adapter"
  );

  return $array;
}

function getDevicesColumns(){

  $columns = ["dev_MAC", 
              "dev_Name",
              "dev_Owner",
              "dev_DeviceType",
              "dev_Vendor",
              "dev_Favorite",
              "dev_Group",
              "dev_Comments", 
              "dev_FirstConnection",
              "dev_LastConnection",
              "dev_LastIP",
              "dev_StaticIP",
              "dev_ScanCycle",
              "dev_LogEvents",
              "dev_AlertEvents",
              "dev_AlertDeviceDown",
              "dev_SkipRepeated",
              "dev_LastNotification",
              "dev_PresentLastScan",
              "dev_NewDevice",
              "dev_Location",
              "dev_Archived",
              "dev_Network_Node_port",
              "dev_Network_Node_MAC_ADDR"]; 
              
  return $columns;
}

//------------------------------------------------------------------------------
//  Simple cookie cache
//------------------------------------------------------------------------------
function getCache($key) {
  if( isset($_COOKIE[$key]))
  {
    return $_COOKIE[$key];
  }else
  {
    return "";
  }
}

function setCache($key, $value) {
  setcookie($key,  $value, time()+300, "/","", 0); // 5min cache
}


?>


