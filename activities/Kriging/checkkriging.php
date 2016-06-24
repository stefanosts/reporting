<?php include("get_emission.php"); ?>
<?php

// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead="ad_av_pos_accelerations";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees

$kriging_input_values1 = array(0=>1500,1=>1000,2=>1,3=>1,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values2 = array(0=>2500,1=>2000,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.25,10=>0.56,11=>23,12=>0.1,13=>40,14=>23);
$kriging_input_values3 = array(0=>1300,1=>1900,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values4 = array(0=>900,1=>900,2=>0,3=>0,4=>1,5=>1,6=>0,7=>0,8=>0,9=>1.25,10=>0.56,11=>23,12=>0.1,13=>40,14=>23);
$t= microtime(true);
	print " tempo0;".$t ."<br>";

for ($d=0;$d<138;$d++) {
$av_pos_accelerations1=KrigingCo2mpas($kriging_input_values1);

}

// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead="ad_fuel_consumption";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees

$kriging_input_values1 = array(0=>1500,1=>1000,2=>1,3=>1,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values2 = array(0=>2500,1=>2000,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.25,10=>0.56,11=>23,12=>0.1,13=>40,14=>23);
$kriging_input_values3 = array(0=>1300,1=>1900,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values4 = array(0=>900,1=>900,2=>0,3=>0,4=>1,5=>1,6=>0,7=>0,8=>0,9=>1.25,10=>0.56,11=>23,12=>0.1,13=>40,14=>23);
$t= microtime(true);
	print " tempo0;".$t ."<br>";

for ($d=0;$d<138;$d++) {
$av_pos_accelerations1=KrigingCo2mpas($kriging_input_values1);

}

// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead="ad_av_vel_pos_mov_pow";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees

$kriging_input_values1 = array(0=>1500,1=>1000,2=>1,3=>1,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values2 = array(0=>2500,1=>2000,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.25,10=>0.56,11=>23,12=>0.1,13=>40,14=>23);
$kriging_input_values3 = array(0=>1300,1=>1900,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values4 = array(0=>900,1=>900,2=>0,3=>0,4=>1,5=>1,6=>0,7=>0,8=>0,9=>1.25,10=>0.56,11=>23,12=>0.1,13=>40,14=>23);
$t= microtime(true);
	print " tempo0;".$t ."<br>";

for ($d=0;$d<138;$d++) {
$av_pos_accelerations1=KrigingCo2mpas($kriging_input_values1);

}
// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead="ad_av_pos_engine_powers_out";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees

$kriging_input_values1 = array(0=>1500,1=>1000,2=>1,3=>1,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values2 = array(0=>2500,1=>2000,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.25,10=>0.56,11=>23,12=>0.1,13=>40,14=>23);
$kriging_input_values3 = array(0=>1300,1=>1900,2=>0,3=>0,4=>1,5=>1,6=>1,7=>0,8=>0,9=>1.15,10=>0.56,11=>23,12=>0.1,13=>40,14=>23,15=>0);
$kriging_input_values4 = array(0=>900,1=>900,2=>0,3=>0,4=>1,5=>1,6=>0,7=>0,8=>0,9=>1.25,10=>0.56,11=>23,12=>0.1,13=>40,14=>23);
$t= microtime(true);
	print " tempo0;".$t ."<br>";

for ($d=0;$d<138;$d++) {
$av_pos_accelerations1=KrigingCo2mpas($kriging_input_values1);
}


$t= microtime(true);
	print " tempo1;".$t ."<br>";
print $av_pos_accelerations1." 1 ";


?>