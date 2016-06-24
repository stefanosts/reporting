<?php
//$kriging_input_values = 10;
$k = array(0=>1500,1=>1000,2=>1,3=>1,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values = array($k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k, $k);


$t= microtime(true);
	print " tempo0;".$t ."<br>";
//$kriging_input_values = array(x=>'[1500, 1000, 1, 2]', t=>'test');
//$kriging_input_values = array(0=>2500,1=>2000,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23, 15=>0);
$hello = "ad_av_pos_accelerations";
$kriging_input_values=escapeshellarg(json_encode($kriging_input_values));
$result = shell_exec('C:\Users\mainelo\Downloads\WinPython-64bit-3.5.1.3\python-3.5.1.amd64\python C:\Users\mainelo\Downloads\WinPython-64bit-3.5.1.3\test\krig.py "'.$kriging_input_values.'" 2>&1');

//print_r ($kriging_input_values );
$resultData = json_decode($result, true);











$t= microtime(true);
	print " tempo1;".$t ."<br>";
//$result = shell_exec('C:\Users\mainelo\Downloads\WinPython-64bit-3.5.1.3\python-3.5.1.amd64\python C:\Users\mainelo\Downloads\WinPython-64bit-3.5.1.3\test\krig.py "'.$kriging_input_values.'" "'.$hello.'"');
print_r ($result);
print "<BR><BR>";
print_r ($resultData);
?>