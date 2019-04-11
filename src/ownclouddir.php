<?php
  #############################################################################
  # ownclouddir.php V1.0 - 03.03.2014                                         #
  # Display a user's owncloud address book on Cisco 7940/60 IP phones         #
  # (C) 2014 by Stefan Gofferje                                               #
  # License: GPLV3                                                            #
  #############################################################################


  # -- Configuration -------------------------------------------------------------------

  $db_hostname = "localhost";	#Database host where owncloud database sits
  $db_user = "user";		#Database user with permissions to read from database
  $db_password = "password";	#Database user's password
  $db_database = "owncloud";	#Database in which owncloud stores it's records
  $oc_user = "username";	#Owncloud user who's addressbook should be displayed
  $paging=50;			#How many entries to show per page in the list

  # ------------------------------------------------------------------------------------

  header("Content-type: text/xml; charset=iso-8859-1");
  header("Connection: Keep-Alive");
  header("Cache-Control: private");

  $ME="http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];

  $link = mysql_connect($db_hostname,$db_user,$db_password);

  if (isset($_REQUEST['searchform'])) {
    print "<CiscoIPPhoneInput>\n";
    print "    <Title>Directory search</Title>\n";
    print "    <Prompt>Please choose</Prompt>\n";
    print "    <URL>".$ME."</URL>\n";
    print "    <InputItem>\n";
    print "      <DisplayName>Last name:</DisplayName>\n";
    print "      <QueryStringParam>searchname</QueryStringParam>\n";
    print "      <InputFlags>A</InputFlags>\n";
    print "      <DefaultValue></DefaultValue>\n";
    print "    </InputItem>\n";
    print "  </CiscoIPPhoneInput>\n";
  } elseif (!isset($_REQUEST['details'])) {

    $query_ent = "SELECT SQL_CALC_FOUND_ROWS * FROM oc_contacts_cards_properties WHERE userid='$oc_user' AND name='N'";
    if (isset($_REQUEST['searchname'])) $query_ent.=" AND value LIKE '%".$_REQUEST['searchname']."%'";
    $query_ent.=";";
    $entries = mysql_db_query($db_database,$query_ent,$link);

    $total_r = mysql_fetch_array(mysql_db_query($db_database,"SELECT FOUND_ROWS()",$link));
    $total_rows = $total_r[0];

    if (!isset($start)) $start=0;
    else $start=$_REQUEST['start'];
    $end=$start+$paging;

    $query_ent = "SELECT * FROM oc_contacts_cards_properties WHERE userid='$oc_user' AND name='N'";
    if (isset($_REQUEST['searchname'])) $query_ent.=" AND value LIKE '%".$_REQUEST['searchname']."%'";
    $query_ent.=" ORDER BY value LIMIT ".$start.",".$paging.";";

    $entries = mysql_db_query($db_database,$query_ent,$link);

    print"<CiscoIPPhoneMenu>\n";
    print"<Title>Central directory</Title>\n";
    print"<Prompt>Please choose:</Prompt>\n";

    while ($row = @mysql_fetch_array($entries)) {
      print "<MenuItem>\n";
      preg_match("/([[:alnum:]äöüßÄÖÜ-]*);([[:alnum:]äöüßÄÖÜ-]*)[;]*/",$row['value'],$matches);
      $name=$matches[1].", ".$matches[2];
      print "<Name>".utf8_decode($name)."</Name>\n";
      print "<URL>".$ME."?details=".$row['contactid']."</URL>\n";
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
    $query_ent = "select carddata from oc_contacts_cards where id='".$_REQUEST['details']."';";
    $entries = mysql_db_query($db_database,$query_ent,$link);
    $row = @mysql_fetch_array($entries);
    print "<CiscoIPPhoneDirectory>\n";
    print "<Prompt>Please choose:</Prompt>\n";
    preg_match("/^N:([[:alnum:]äöüßÄÖÜ-]*);([[:alnum:]äöüßÄÖÜ-]*)[;]*/m",$row['carddata'],$matches);
    $name=$matches[1].", ".$matches[2];
    print "<Title>".utf8_decode($name)."</Title>\n";
    preg_match_all("/^TEL;TYPE=.+:.+/m",$row['carddata'],$matches);
    foreach($matches[0] as $entry) {
      preg_match("/^TEL;TYPE=(\V+):(\V+)/m",$entry,$fields);
      if (strtoupper($fields[1])=="HOME") {
	print "<DirectoryEntry>\n";
	print "<Name>Home</Name>\n";
	print "<Telephone>".$fields[2]."</Telephone>\n";
	print "</DirectoryEntry>\n";
      }
      if (strtoupper($fields[1])=="CELL") {
	print "<DirectoryEntry>\n";
	print "<Name>Mobile</Name>\n";
	print "<Telephone>".$fields[2]."</Telephone>\n";
	print "</DirectoryEntry>\n";
      }
      if (strtoupper($fields[1])=="WORK") {
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

  mysql_close($link);

?>
