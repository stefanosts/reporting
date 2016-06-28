<?php
//$kriging_input_values = 10;

// need to translate kriging input in multiple kriging for Slope variable and Average speed and InitT(hot-cold start)
$k = array(0=>1500,1=>1000,2=>1,3=>1,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>1,13=>4);
$avg_speed= array(0=>90,1=>90,2=>10,3=>20,4=>40,5=>40,6=>50,7=>70,8=>80,9=>80); // esempio di 10 segmenti, velocita' media per segmento
$seg_slope= array(0=>3.0,1=>2.0,2=>1.0,3=>0,4=>-1.2,5=>-1.5,6=>-2.0,7=>0,8=>1.6,9=>0.8); // esempio di 10 segmenti, slope per segmento
$seg_init= array(0=>23,1=>23,2=>23,3=>23,4=>85,5=>85,6=>85,7=>85,8=>85,9=>85); // esempio di 10 segmenti, InitT per segmento



$kriging_input_values = array($k, $avg_speed, $seg_slope, $seg_init);


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
//print $resultData;
?>