<?php
  #############################################################################
  # ownclouddir.php V1.0 - 03.03.2014                                         #
  # Display a user's owncloud address book on Cisco 7940/60 IP phones         #
  # Modified in 11/2021 by franky-b-ne to work with PHP7, Nextcloud,          #
  # Vcards created by MacOS, and added Reverse Lookup                         #
  # (C) 2014 by Stefan Gofferje                                               #
  # License: GPLV3                                                            #
  #############################################################################


  # -- Configuration -------------------------------------------------------------------

  $db_hostname = "localhost";	#Database host where owncloud database sits
  $db_user = "user";		#Database user with permissions to read from database
  $db_password = "password";	#Database user's password
  $db_database = "nextcloud";	#Database in which owncloud stores it's records
  $oc_user = "id";	#Nextcloud Address Book ID
  $paging=50;			#How many entries to show per page in the list

  # ------------------------------------------------------------------------------------

  header("Content-type: text/xml; charset=utf-8");
  header("Connection: Keep-Alive");
  header("Cache-Control: private");

  $ME="http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];

  $link = new MySQLi($db_hostname,$db_user,$db_password,$db_database);

  if (isset($_REQUEST['n'])) {
   
   $query_ent = "SELECT id FROM oc_cards WHERE (REPLACE (carddata, \" \",\"\") LIKE '%".$_REQUEST['n']."%')";
   if (substr($_REQUEST['n'],0,2) == "00") {
     $alt_num = "+". substr($_REQUEST['n'],2); 
    $query_ent.=" OR (REPLACE (carddata, \" \",\"\") LIKE '%".$alt_num."%')";
    }
   elseif (substr($_REQUEST['n'],0,1) == "0") { 
    $alt_num = "+49". substr($_REQUEST['n'],1); 
    $query_ent.=" OR (REPLACE (carddata, \" \",\"\") LIKE '%".$alt_num."%')";
    $alt_num = "0049". substr($_REQUEST['n'],1); 
    $query_ent.=" OR (REPLACE (carddata, \" \",\"\") LIKE '%".$alt_num."%')";    
    }
   $query_ent.=" LIMIT 1;";

    $entries = $link->query($query_ent);
    $row = @mysqli_fetch_array($entries);
    $cardid= $row['id'];
        
    if (isset($cardid)) {
     
    $query_ent = "select carddata from oc_cards where id='".$cardid."';";
    $entries = $link->query($query_ent);
    $row = @mysqli_fetch_array($entries);
     
    preg_match("/^N:([^;]*);([^;]*)[;]*/m",$row['carddata'],$matches);
    $name=$matches[2]." ".$matches[1]; 
    
    preg_match_all("/^TEL;type=.+:.+/m",$row['carddata'],$matches);
    foreach($matches[0] as $entry) {
      preg_match("/^TEL;type=(\V+):(\V+)/m",$entry,$fields);      
      $card_number = str_replace(" ", "", $fields[2]);
     if (substr($card_number,0,3) == "+49") {
        $alt_num = "0". substr($card_number,3);  
        }
      elseif (substr($card_number,0,1) == "+") {  
       $alt_num = "00". substr($card_number,1);  
        }
      if (strpos(strtoupper($fields[1]), "HOME")!== false AND ($_REQUEST['n']==$alt_num)) {
	$name.=" (Home)";
      }
      if (strpos(strtoupper($fields[1]), "CELL;TYPE=VOICE")!== false AND ($_REQUEST['n']==$alt_num)) {
	$name.=" (Mobile)";
      }
      if (strpos(strtoupper($fields[1]), "WORK;TYPE=VOICE")!== false AND ($_REQUEST['n']==$alt_num)) {
	$name.=" (Work)";
      }
     }
           print "<CiscoIPPhoneDirectory>\n";
          print "<Title>Lookup</Title>\n";
          print "<DirectoryEntry>\n";
	        print "<Name>".$name."</Name>\n";
	        print "<Telephone>".$_REQUEST['n']."</Telephone>\n";
	      print "</DirectoryEntry>\n";
    print " </CiscoIPPhoneDirectory>\n"; 
     }

      
  } elseif (isset($_REQUEST['searchform'])) {
    print "<CiscoIPPhoneInput>\n";
    print "    <Title>Directory search</Title>\n";
    print "    <Prompt>Please choose</Prompt>\n";
    print "    <URL>".$ME."</URL>\n";
    print "    <InputItem>\n";
    print "      <DisplayName>Name:</DisplayName>\n";
    print "      <QueryStringParam>searchname</QueryStringParam>\n";
    print "      <InputFlags>A</InputFlags>\n";
    print "      <DefaultValue></DefaultValue>\n";
    print "    </InputItem>\n";
    print "  </CiscoIPPhoneInput>\n";
  } elseif (!isset($_REQUEST['details'])) {

    $query_ent = "SELECT SQL_CALC_FOUND_ROWS * FROM oc_cards_properties WHERE addressbookid='$oc_user' AND name='N'";
    if (isset($_REQUEST['searchname'])) $query_ent.=" AND LOWER (value) LIKE '%".strtolower($_REQUEST['searchname'])."%'";
    $query_ent.=";";

    $entries = $link->query($query_ent);

    $total_r = mysqli_fetch_array($link->query("SELECT FOUND_ROWS()"));
    $total_rows = $total_r[0];

    if (!isset($_REQUEST['start'])) {
      $start=0;
      }
    else { 
      $start=$_REQUEST['start'];
      }
    $end=$start+$paging;

    $query_ent = "SELECT * FROM oc_cards_properties WHERE addressbookid='$oc_user' AND name='N'";
    if (isset($_REQUEST['searchname'])) $query_ent.=" AND LOWER (value) LIKE '%".strtolower($_REQUEST['searchname'])."%'";
    $query_ent.=" ORDER BY value LIMIT ".$start.",".$paging.";";

    $entries = $link->query($query_ent);

    print"<CiscoIPPhoneMenu>\n";
    print"<Title>Central directory</Title>\n";
    print"<Prompt>Please choose:</Prompt>\n";

    while ($row = @mysqli_fetch_array($entries)) {
      print "<MenuItem>\n";
      preg_match("/([^;]*);([^;]*)[;]*/",$row['value'],$matches);
    
      #preg_match("/([[:alnum:]äöüßÄÖÜ-]*);([[:alnum:]äöüßÄÖÜ-]*)[;]*/",$row['value'],$matches);
      $name=$matches[1].", ".$matches[2];
      print "<Name>".$name."</Name>\n";
      print "<URL>".$ME."?details=".$row['cardid']."</URL>\n";
      print "</MenuItem>\n";
    }

    print"<SoftKeyItem>\n";
    print"  <Name>Select</Name>\n";
    print"  <URL>SoftKey:Select</URL>\n";
    print"  <Position>1</Position>\n";
    print"</SoftKeyItem>\n";
    if ($start < $paging) {
      print"<SoftKeyItem>\n";
      print"  <Name>Exit</Name>\n";
      print"  <URL>SoftKey:Exit</URL>\n";
      print"  <Position>2</Position>\n";
      print"</SoftKeyItem>\n";
    } else {
      print"<SoftKeyItem>\n";
      print"  <Name>Back</Name>\n";
      print"  <URL>SoftKey:Exit</URL>\n";
      print"  <Position>2</Position>\n";
      print"</SoftKeyItem>\n";
    }
    print"<SoftKeyItem>\n";
    print"  <Name>Search</Name>\n";
    print"  <URL>".$ME."?searchform</URL>\n";
    print"  <Position>3</Position>\n";
    print"</SoftKeyItem>\n";
    if ($end < $total_rows) {
      print"<SoftKeyItem>\n";
      print"  <Name>Next</Name>\n";
      print"  <URL>".$ME."?start=$end</URL>\n";
      print"  <Position>4</Position>\n";
      print"</SoftKeyItem>\n";
    }
    print"</CiscoIPPhoneMenu>\n";
  } elseif (isset($_REQUEST['details'])) {
    $query_ent = "select carddata from oc_cards where id='".$_REQUEST['details']."';";
    $entries = $link->query($query_ent);
    $row = @mysqli_fetch_array($entries);
        
    print "<CiscoIPPhoneDirectory>\n";
    print "<Prompt>Please choose:</Prompt>\n";
    
    preg_match("/^N:([^;]*);([^;]*)[;]*/m",$row['carddata'],$matches);
    $name=$matches[1].", ".$matches[2];
    print "<Title>".$name."</Title>\n";
    preg_match_all("/^TEL;type=.+:.+/m",$row['carddata'],$matches);
    foreach($matches[0] as $entry) {
      preg_match("/^TEL;type=(\V+):(\V+)/m",$entry,$fields);
            
      if (strpos(strtoupper($fields[1]), "HOME")!== false ) {
	print "<DirectoryEntry>\n";
	print "<Name>Home</Name>\n";
	print "<Telephone>".$fields[2]."</Telephone>\n";
	print "</DirectoryEntry>\n";
      }
      if (strpos(strtoupper($fields[1]), "CELL;TYPE=VOICE")!== false ) {
	print "<DirectoryEntry>\n";
	print "<Name>Mobile</Name>\n";
	print "<Telephone>".$fields[2]."</Telephone>\n";
	print "</DirectoryEntry>\n";
      }
      if (strpos(strtoupper($fields[1]), "WORK;TYPE=VOICE")!== false ) {
	print "<DirectoryEntry>\n";
	print "<Name>Work</Name>\n";
	print "<Telephone>".$fields[2]."</Telephone>\n";
	print "</DirectoryEntry>\n";
      }
    }
    print"<SoftKeyItem>\n";
    print"  <Name>Dial</Name>\n";
    print"  <URL>SoftKey:Dial</URL>\n";
    print"  <Position>1</Position>\n";
    print"</SoftKeyItem>\n";
    print"<SoftKeyItem>\n";
    print"  <Name>Exit</Name>\n";
    print"  <URL>SoftKey:Exit</URL>\n";
    print"  <Position>2</Position>\n";
    print"</SoftKeyItem>\n";
    print "</CiscoIPPhoneDirectory>\n";
  }

  $link->close();

?>
