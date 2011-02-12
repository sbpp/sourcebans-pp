<?php
/**
 * css.php
 * 
 * This file contains all of our styles :D
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SteamFriends (www.steamfriends.com)
 * @package SourceBans
 * @link http://www.sourcebans.net
 */

if(strstr($_SERVER['HTTP_USER_AGENT'], "MSIE 6.0"))
	$agent = "IE6";
elseif(strstr($_SERVER['HTTP_USER_AGENT'], "MSIE 7.0"))
	$agent = "IE7";
elseif(strstr($_SERVER['HTTP_USER_AGENT'], "Firefox/2"))
	$agent = "FF2";
elseif(strstr($_SERVER['HTTP_USER_AGENT'], "Firefox/1"))
	$agent = "FF1";
else 
	$agent = "other";
	
$css = 
"body {
	background: url(../images/bk.gif);
	background-color: #FFF;
	background-repeat: repeat-x;
	color: #444;
	font-family: Verdana, Arial, Tahoma, Trebuchet MS, Sans-Serif, Georgia, Courier, Times New Roman, Serif;
	font-size: 11px;
	line-height: 135%;
	margin: 0px;
	padding: 0px; /* required for Opera to have 0 margin */
   text-align: center /* centers board in MSIE */
}

#install-progress{
	height: 90px;
	width:  175px;
	padding: 5px;
	border: #dedede solid 1px;
	float: left;
	font-size: 10px;
	margin: 5px;
}

a:link {
  text-decoration: none;
  color : #2f4075;
}

a:active {
  color: #FF0000;
  text-decoration: underline;
}

a:visited {
  text-decoration: none;
  color : #2f4075;
}

a:hover {
  color: #000;
  border-bottom: #000 dotted 1px;
}

.inputbox {
	border: 1px solid #000000;
	width: 105px; 
	font-size: 14px; 
	background-color: rgb(215, 215, 215);
	width: 200px;
	padding-left: 2px;
}

.dbg.b {
	font-size: 12px;
	font-weight: bold;
}
/** ================ Permissions ================ **/	
	
.tablerow1 {
background-color:#EAEDF0;
border-color:#FFFFFF rgb(193, 190, 190) rgb(193, 190, 190) rgb(255, 255, 255);
border-style:solid;
border-width:1px;
padding:6px;
}

.tablerow2 {
background-color: rgb(236, 213, 216);
 background-image: url(../images/table_highlight_red.gif);
border-color:#FFFFFF rgb(193, 190, 190) rgb(193, 190, 190) rgb(255, 255, 255);
border-style:solid;
border-width:1px;
padding:6px;
}

.tablerow4 {
background-color:#C0CBDA;
 background-image: url(../images/table_highlight.gif);
border-color:#FFFFFF rgb(209, 220, 235) rgb(209, 220, 235) rgb(255, 255, 255);
border-style:solid;
border-width:1px;
padding:6px;
color:#fff;
}

/* ================ STRUCTURE ================ */

#mainwrapper {
	width: 922px;
	margin: 0 auto 0 auto;  /*centers the box, no matter the overall width */
	text-align: left; /* re_aligns text to left second part of two part MSIE centering workaround */
	height: 100%;
}

#header {
	width: 900px;
	margin: 0 auto 0 auto;  /*centers the box, no matter the overall width */
	height: 64px;
	border: 0;
}
	
#tabsWrapper {
  width: 100%;
  margin: 5px auto 0 auto;
  text-align: left;
  height: 18px;
}

#innerwrapper {
	margin: 0 14px;
	text-align: left; /* re_aligns text to left, second part of two part MSIE centering workaround */
	}
#navigation {
	width: 100%;
	height: 22px;
	}
	
#breadcrumb {
	width: 100%;
	height: 22px;
	margin-bottom: -5px;
}
#content_title {
	font-size: 22px;
	color: #b80202;
	padding: 3px;
	margin-top:10px;
	}
	
#content {
	background-color: #FFF;
	padding: 8px;
	border-top: 2px solid #aaa9a9;
	overflow:hidden;
	}
	
#footer {
	clear:both;
	color: #000;
	width: 892px;
	margin-left:15px;
	background-image: url(../images/footerrepeatbg.png);
	background-repeat: repeat-x;
	
	height: 68px;
}

/** ================ Header ================ **/

#head-logo {
	float: left;
	margin-top: 12px;
}

#head-userbox {
	border:1px dotted #cccecd;
	float: right;
	width: 273px;
	height: 35px;
	margin-top: 12px;
	padding: 3px;
	line-height:18px;
	color: #666666;
}

/** ================ Tabs ================ **/

#tabs {
  float: left;
  margin-left: 0;
}

#tabs ul {
  margin: 0;
  padding: 0 10px;
  list-style: none;
}

#tabs ul li {
  margin-right: 2px;
  float: left;
  background: url('../images/nav/tab_right.jpg') no-repeat top right;
}

#tabs ul li a {
  border: 0;
  display: block;
  padding: 0 10px;
  background: url('../images/nav/tab_left.jpg') no-repeat top left;
  text-align: center;
  color: #333;
  font-size: 10px;
  font-weight: bolder;
  line-height: 18px;
  text-decoration: none;
}

#tabs ul li a:hover {
  border: 0;
  color: #333;
  text-decoration: underline;
}

#tabs ul li.active {
  background: url('../images/nav/tab_active_right.jpg') no-repeat top right;
}

#tabs ul li.active a {
  /* padding: 0.1em 0.6em; */
  padding: 0 10px;
  background: url('../images/nav/tab_active_left.jpg') no-repeat top left;
  color: white;
}

#tabs ul li.active a:hover {
  color: white;
  text-decoration: none;
}

h4 {
	margin:0px;
}

h3 {
	margin-right:5px;
	margin-top:5px;
	margin-bottom:5px;
	font-size: 12px;
	border-bottom:1px dotted #000;
	padding:5px;
	font-weight: bold;
	background-color: #eaeaea;
}
/** ================ ToolTips ================ **/
.tool-tip {
	color: #fff;
	width: 139px;
	z-index: 13000;
	text-align:left;
}
 
.tool-title {
	font-weight: bold;
	font-size: 14px;
	margin: 0;
	color: #b80202;
	/*text-decoration:underline;*/
	border-bottom: #b80202 dotted 1px;
	padding: 8px 8px 4px;
	background: url(../images/bubble.png) top left;
}
 
.tool-text {
	font-size: 11px;
	padding: 4px 8px 8px;
	background: url(../images/bubble.png) bottom right;
}
.perm-tip {
	color: #fff;
	width: 210px;
	z-index: 13000;
	text-align:left;
}
 
.perm-title {
	font-weight: bold;
	font-size: 14px;
	margin: 0;
	color: #b80202;
	/*text-decoration:underline;*/
	border-bottom: #b80202 dotted 1px;
	padding: 8px 8px 4px;
	background: url(../images/bubble2.png) top left;
}
 
.perm-text {
	font-size: 11px;
	padding: 4px 8px 8px;
	background: url(../images/bubble2.png) bottom right;
}
/** ================ Navigation ================ **/
#nav {
	color: #FFF;
	padding: 3px 0 0 3px;
	float: left;
	width: 580px;
	font-weight: 600;
	font-size: 10px;
	}

a.nav_link:link,
a.nav_link:visited {
	color: #FFF;
	text-decoration: none;
	padding: 0 5px;
	}

a.nav_link:hover {
	text-decoration: underline;
	}

#search {
	width: 300px;
	float: right;
	padding: 2px 0 0 0;
	text-align: right;
	}

.button {
	background-image: url(../images/searchbutton.jpg);
	border: 0;
	width: 18px;
	height: 18px;
	}

/** ================ Content ================ **/

/* ================ Popup Boxes ============== */
.dialog-holder{
	border-collapse:collapse;
	margin:auto;
	table-layout:fixed;
	width:465px;
}

td.dialog-topleft{
	background-image:url(../images/dialog/dialog_topleft.png) !important;
}
td.dialog-border{
	background-image:url(../images/dialog/dialog_border.png) !important;
}
td.dialog-topright{
	background-image:url(../images/dialog/dialog_topright.png) !important;
}
td.dialog-bottomright{
	background-image:url(../images/dialog/dialog_bottomright.png) !important;
}
td.dialog-bottomleft{
	background-image:url(../images/dialog/dialog_bottomleft.png) !important;
}

td.dialog-topleft, td.dialog-topright, td.dialog-bottomright, td.dialog-bottomleft {
	height:10px;
	overflow:hidden;
	padding:0px !important;
	width:10px !important;
}

h2{
	color:white;
	font-size:14px;
	font-weight:bold;
	margin:0px;
	display:block;
	padding:4px 10px 5px;
}

h2.error{
	background:#b46d6d none repeat scroll 0%;
	border:1px solid #983b3b;
}

h2.info{
	background:#6d8bb4 none repeat scroll 0%;
	border:1px solid #3b6298;
}

h2.warning{
	background:#b4ae6d none repeat scroll 0%;
	border:1px solid #887a2c;
}

h2.ok{
	background:#75b46d none repeat scroll 0%;
	border:1px solid #46983b;
}


.icon-ok{
	background-image:url(../images/ok.png);
	float:left;
	height:48px;
	overflow:hidden;
	padding:0px !important;
	width:48px !important;
}
.icon-error{
	background-image:url(../images/warning.png);
	float:left;
	height:48px;
	overflow:hidden;
	padding:0px !important;
	width:48px !important;
}
.icon-warning{
	background-image:url(../images/warning.png);
	float:left;
	height:48px;
	overflow:hidden;
	padding:0px !important;
	width:48px !important;
}
.icon-info{
	background-image:url(../images/info.png);
	float:left;
	height:48px;
	overflow:hidden;
	padding:0px !important;
	width:48px !important;
}


.dialog-content{
	background:#FFFFFF none repeat scroll 0%;
	border-color:#555555;
	border-style:solid;
	border-width:0px 0px 1px 0px;
}
div.dialog-body{
	border-bottom:1px solid #CCCCCC;
	padding:10px;
}
div.dialog-control{
	background:#F2F2F2 none repeat scroll 0%;
	padding:8px;
	text-align:right;
	vertical-align:bottom
}

.clearfix:after {
    content: "."; 
    display: block; 
    height: 0; 
    clear: both; 
    visibility: hidden;
}

.clearfix {display: inline-block;}

/* Buttons */
.btn{
   color:#444444;
   font-family:'trebuchet ms',helvetica,sans-serif;
   font-weight:bold;
   background-color:#eaeaea;
   border:1px solid;
   border-top-color:#d5d4d4;
   border-left-color:#d5d4d4;
   border-right-color:#c8c8c8;
   border-bottom-color:#c8c8c8;
   background-repeat: no-repeat;
   background-position: 2px 50%;
   padding:1px 1px 1px 20px;
   margin: 0 0.5em;
}
.btnhvr{
   color:#444444;
   font-family:'trebuchet ms',helvetica,sans-serif;
   font-weight:bold;
   background-color:#eaeaea;
   border:1px solid;
   border-top-color:#c24733;
   border-left-color:#c24733;
   border-right-color:#a33c2b;
   border-bottom-color:#a33c2b;
   background-repeat: no-repeat;
   background-position: 2px 50%;
   padding:1px 1px 1px 20px;
   margin: 0 0.5em;
}

.game{
	background-image: url(../images/connect.gif);
}
.ok{
	background-image: url(../images/admin/ok.gif);
}
.save{
	background-image: url(../images/admin/save.gif);
}
.cancel{
	background-image: url(../images/admin/cancel.gif);
}
.login{
	background-image: url(../images/login.gif);
}
.refresh{
	background-image: url(../images/refresh.png);
}

.msg-button {
	float:right;
	position:absolute;
	top:85px;
	left:480px;
}
.msgbox-border {
	position:fixed !important;
	position:absolute;
	overflow:hidden;
	top:250px;
";
	if($agent != "IE6")
		$css .= "background: url(../images/msg-bubble.png) no-repeat center;";
$css .="
	padding:15px;
	margin: auto 210px;
	width: 520px;
}
#msg-red {
	background-color:#fefad3;
	border:#E80909 1px solid;
	color:#E80909;
	width: 500px;
	padding: 8px;
	height: 75px;
	overflow:hidden;
}

#msg-red-debug {
	background-color:#fcf7c9;
	border:#E80909 1px dotted;
	color:#E80909;
	width: 75%;
	padding: 8px;
	margin: 10px auto;
	overflow:hidden;
}

#msg-red b, #msg-green b, #msg-blue b, #msg-red-debug b, #msg-green-debug b {
	font-size: 16px;
}
#msg-red i, #msg-blue i, #msg-green i, #msg-red-debug i, #msg-green-dbg i {
	float:left;
	margin-right: 7px;
}
#msg-green {
	background-color:#fcf7c9;
	border:#339933 1px solid;
	color:#339933;
	width: 500px;
	padding: 8px;
	height: 75px;
	overflow:hidden;
}

#msg-green-dbg {
	background-color:#fcf7c9;
	border:#339933 1px dotted;
	color:#339933;
	width: 75%;
	padding: 8px;
	margin: 10px auto;
	overflow:hidden;
}

#msg-blue {
	background-color:#fcf7c9;
	border:#0066FF 1px solid;
	color:#0066FF;
	width: 500px;
	padding: 8px;
	height: 75px;
	overflow:hidden;
}
#log_res {
	overflow: auto;
}
 
#log_res.ajax-loading {
	padding: 20px 0;
	background: url(../images/spinner.gif) no-repeat center;
}

.front-module-line {
border-bottom-color:#CCCCCC;
border-bottom-style:solid;
border-bottom-width:1px;
background: url(../images/detail_head.gif);
color: #fff;
}
.admin-row {
border-bottom-color:#CCCCCC;
border-bottom-style:solid;
border-bottom-width:1px;

}
/** ================ Footer ================ **/

#gc {
	height: 68px;
	width: 25%;
	float: left;
	color: #FFF;
	padding: 3px 0 0 5px;
	background-image: url(../images/gcbg.png);
	background-position: top left;
	background-repeat: no-repeat;
	}
	
	
#sb {
	height: 68px;
	width: 50%;
	float: left;
	text-align: center;
	}
	
#sm {
	height: 68px;
	width: 20%;
	float: right;
	color: #FFF;
	text-align: right;
	padding: 3px 5px 0 0;
	background-image: url(../images/smbg.png);
	background-position: top right;
	background-repeat: no-repeat;
	}
	
a.footer_link:link,
a.footer_link:visited {
	color: #FFF;
	text-decoration: none;
	}
	
a.footer_link:hover {
	text-decoration: underline;
	}


/** ================ Login ================ **/

#login {
	width: 400px;
	height: 260px;
	border: 1px solid #aaa9a9;
    margin: 30px auto;
	padding: 12px;
}

#lostpassword {
	width: 400px;
	height: 260px;
    margin: 30px auto;
	padding: 12px;
}

	
#loginLogo {
	text-align: center;
	height: 60px;
}
	
#loginUsernameDiv,
#loginPasswordDiv,
#loginRememberMe,
#loginSubmit {
	padding: 6px 0;
}


.loginmedium {
	width: 394px;
	height: 22px;
	font-size: 18px;
	}

	
#loginSubmit {
	text-align: right;
}
	
#loginbutton {
	padding: 5px 10px;
	font-size: 14px;
	background-color: #000;
	border: 2px outset #999;
	color: #FFF;
	font-weight: 700;
}
	
#loginOtherlinks {
	border-top: 1px solid #aaa9a9;
	text-align: center;
	padding: 8px 0;
	margin-top: 26px;
}

/** ================ Admin ================ **/
/* Admin table */
.rowdesc {
	color:#0B1B51;
	font-weight:bold;
}

/* CPanel */
#cpanel {
	width: 100%;
	height: 120px;
	}

#cpanel ul {
  margin: 0;
  padding: 10px;
  list-style: none;
}

#cpanel ul li {
  margin-right: 2px;
  float: left;
  text-align: center;
}

#cpanel ul li a {
	display: block;
	height: 97px !important;
	height: 100px; 
	width: 108px !important;
	width: 110px; 
	vertical-align: middle; 
	text-decoration : none;
	border: 1px solid #DDD;
	padding: 2px 5px 1px 5px;
	margin-right: 20px;
}

#cpanel ul li a:hover {
	color : #333; 
	background-color: #f1e8e6;  
	border: 1px solid #c24733;
	padding: 3px 4px 0px 6px; 
}

#cpanel ul li.active {
}

#cpanel ul li img {
	margin-top: 13px;
	}

/* Admin Page Menu */

#admin-page-menu {
	width: 20%;
	float: left;
	padding: 5px;
	}
	
#admin-page-menu ul {
  margin: 0;
  padding: 0 10px;
  list-style: none;
}

#admin-page-menu ul li {
  text-align: left;
}

#admin-page-menu ul .active
{
	color : #333; 
	background-color: #f1e8e6;
	font-weight: bold;
	background-image: url(../images/admin/rightarrow.png);
	background-position: center right;
	background-repeat: no-repeat;
}

#admin-page-menu ul li a {
	display: block;
	height: 20px;
	text-decoration : none;
	border-bottom: 1px solid #DDD;
	border-left: 5px solid #DDD;
	padding: 2px 5px 1px 5px;
}

#admin-page-menu ul li a .tab-img {
	align:baseline;
	border:none;
}

#admin-page-menu ul li a:hover {
	color : #333; 
	background-color: #f1e8e6;  
	border: 1px solid #c24733;
	border-left: 5px solid #c24733;
	background-image: url(../images/admin/rightarrow.png);
	background-position: center right;
	background-repeat: no-repeat;
	padding: 2px 5px 1px 5px;
}

#admin-page-menu ul li.active {
}

/* Admin Page Content */

#admin-page-content {
	width: 76%;
	float: right;
	border: 1px solid #DDD;
	padding: 5px;
	}
	
/** ================ Permissions ================ **/
#permis-drop{
	width: 676px;
	float: right;
	height: 100%;
	border: 1px solid #DDD;
	overflow:hidden;
	padding:7px;
	margin: 0 auto 0 auto;
}
.permis-container {
	width: 690px;
	float: none;
	
	height: 100%;
	overflow:hidden;
}

.permis-container ul {
  margin: 0;
  padding: 10px;
  list-style: none;
}

.permis-container ul li {
  margin-right: 2px;
  float: left;
  text-align: center;
}

.permis-container ul li a {

	display: block;
	height: 37px; 
	width: 100px; 
	vertical-align: middle; 
	text-decoration: none;
	border: 1px solid #DDD;
	padding: 7px 5px 1px 5px;
	margin-right: 20px;
	margin-bottom:20px;
}
.permis-container ul li a:hover {
	color : #333; 
	background-color: #f1e8e6;  
	border: 1px solid #c24733;
	padding: 8px 4px 0px 6px; 
	cursor:move;
}

/** ================ Frontpage ================ **/

#front-introduction {
	padding: 5px;
	}

#front-servers {
	margin: 13px;
}
	
#front-servers hr {
	border: 1px solid #DDD;
	margin-bottom: 5px;
	}
	
.front-module {
	width: 410px;	
	margin: 13px;
}
.front-module-intro {
	margin: 13px;
	width: 98%;
}

.front-module-header {
	padding: 5px;
	border-bottom: 1px dotted #000;
	padding: 3px;
	margin-bottom: 3px;
	font-size: 12px;
	font-weight: 600;
	background-color: #eaeaea;
}
	
.fmsd {
	font-size: 10px;
	}

/** ================ Submit ================ **/	

#submit-introduction {
	padding: 5px;
	}

#submit-main {
	border: 1px solid #DDD;
	padding: 5px;
	float: right;
}	
#submit-main-full {
	border: 1px solid #DDD;
	padding: 5px;
	float: left;
	width: 98%;
}	
.faux-button {
	padding: 2px 10px;
	font-size: 11px;
	background-color: #d7d8d8;
	border: 2px outset #999;
	color: #b80202;
	border: 1px solid #aaa9a9;
	font-weight: 600;
	letter-spacing: 1px;
	}
	
.submit-fields {
	border: 1px solid #000000;
	font-size: 14px;
	background-color: rgb(215, 215, 215);
	}
	
.mandatory {
	color:#FF0000;
	}
	
/** ================ Servers ================ **/

#servers {
	width: 850px;
	padding: 5px;
	border: 1px solid #DDD;
	border-top: 2px solid #aaa9a9;
	}
	
#singleserver {
	width: 500px;
	padding: 5px;
	border: 1px solid #DDD;
	border-top: 2px solid #aaa9a9;
	float: left;
	}
#singleoverview {
	width: 350px;
	padding: 5px;
	border: 1px solid #DDD;
	border-top: 2px solid #aaa9a9;
	float: right;
	}

.activeplayer {
	border-bottom: 1px solid #DDD;
	border-top: 1px solid #DDD;
	background-color: #eaebeb;
	}
	
/** ================ Banlist ================ **/
	
table.listtable {
 font-family: verdana, tahoma, arial;
 font-size: 10px;
 background-color: #fff;
 border: 1px #DDD solid;
 border-collapse:collapse;
}

table.listtable2 {
 font-family: verdana, tahoma, arial;
 font-size: 10px;
 background-color: #898989;
 color: #000000;
 border: #c5c5c5 solid;
 border-width : 1px 1px 1px 1px;
}

table.listtable3 {
 font-family: verdana, tahoma, arial;
 font-size: 11px;
 background-color: #898989;
 color: #000000;
 border: #c5c5c5 solid;
 border-width : 0px 0px 0px 0px;
}

td.listtable_top {
 font-family: verdana, tahoma, arial;
 font-size: 10px;
 background-color: #DFE3E9;
 border: #fff solid;
 border-width: 1px 1px 0px 0px;
 padding-top: 2px;
 padding-right: 4px;
 padding-bottom: 2px;
 padding-left: 4px;
 color: #fff;
 background-image: url(../images/detail_head.gif);
}


td.listtable_1 {
 font-family: verdana, tahoma, arial;
 font-size: 10px;
 border: #DDD solid;
 border-width: 0px 0px 1px 1px;
 padding-top: 2px;
 padding-right: 4px;
 padding-bottom: 2px;
 padding-left: 4px;
}
.green {
 font-family: verdana, tahoma, arial;
 font-size: 10px;
 border: #DDD solid;
 background-color: #8bd659;
 border-width: 0px 0px 1px 1px;
 padding-top: 2px;
 padding-right: 4px;
 padding-bottom: 2px;
 padding-left: 4px;
}

.yellow {
 font-family: verdana, tahoma, arial;
 font-size: 10px;
 border: #DDD solid;
 background-color: #d6cc59;
 border-width: 0px 0px 1px 1px;
 padding-top: 2px;
 padding-right: 4px;
 padding-bottom: 2px;
 padding-left: 4px;
}

.red {
 font-family: verdana, tahoma, arial;
 font-size: 10px;
 border: #DDD solid;
 
 border-width: 0px 0px 1px 1px;
 padding-top: 2px;
 padding-right: 4px;
 padding-bottom: 2px;
 padding-left: 4px;
 background-color: #d65959;
}

td.listtable_2 {
 font-family: verdana, tahoma, arial;
 font-size: 10px;
 border: #DDD solid;
 border-width: 0px 0px 1px 1px;
 padding-top: 2px;
 padding-right: 4px;
 padding-bottom: 2px;
 padding-left: 4px;
 background-color: #d65959;
}



.ban-edit {
	padding: 5px;
	}
	
.ban-edit ul {
  margin: 0;
  padding: 0 10px;
  list-style: none;
}

.ban-edit ul li {
  text-align: left;
}

.ban-edit li a {
	display: block;
	height: 20px;
	text-decoration : none;
	border-bottom: 1px solid #DDD;
	padding: 2px 5px 1px 5px;
}

.ban-edit ul li a:hover {
	color : #333; 
	background-color: #f1e8e6;  
	border: 1px solid #c24733;
	padding: 3px 4px 0px 6px; 
}

.ban-edit ul li.active {
}

#banlisttitle {
	width: 50%;
	float: left;
	}
	
#banlist-nav {
	width: 50%;
	float:right;
	text-align: right;
	}
	
#banlist {
	width: 100%;
	float:left;
	margin-top: 2px;
	border-top: 2px solid #aaa9a9;
	}
";
 header('Content-type: text/css');
echo $css;
?>