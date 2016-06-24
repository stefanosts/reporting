<?php include("db.php"); ?>
<?php
	/* -------------- calculate the CO2 emission ------------*/
	/* 
		Steps:
		1 Get the values of the interface settings: Capacity/mass/Fuel/Turbo/Transission/Traction/Start&Stop/Brake energy recuperation/Air conditioning/Roof box/Tyres=0.00925/Slope (Tyres have a fixed value for now)
		2 Get the values of each road segment: lenght/slope	 (time??)
		3 Load the databases with the data to use for the CO2 emission calculation
		4 Call the function KrigingCo2mpas(x) for each slope value and store the result (in CO2 emission per Km)
		5 Calculate the emission per segment and sum all results 
		6 Output the result to the interface
	*/
	// fixed variables
	$table_name1="co2_emission";
	$table_name2="fuel_consumption";
	$table_name3="sufficient_power";
	$table_name4="av_vel_pos_mov_pow";
	$table_name5="av_pos_motive_powers";
	$table_name6="sec_pos_mov_pow";
	$table_name7="av_neg_motive_powers";
	$table_name8="sec_neg_mov_pow";
	$table_name9="av_pos_accelerations";
	$table_name10="av_engine_speeds_out_pos_pow";
	$table_name11="av_pos_engine_powers_out";
	$table_name12="willans_a";
	$table_name13="willans_b";
	$table_name14="specific_fuel_consumption";
	$table_name15="willans_efficiency";
	
	
	
	
	
	/* ------------------------------------------------------*/
	// ---------------------- STEP 2 ------------------------
	// use SESSION? COOKIES (no). USE JSON call?
	// to calculate slope 
	
	
	// i can sanitize the sql here...!!!
	
	
	
	
	// load data from the necessary tables in the database
	function LoadDataForKriging($TableToRead_f) {
		/* ------------------------------------------------------*/
		// ---------------------- STEP 3 ------------------------
		//load all variables from db 
		// it's fast, no time problems.
		$table_nameYSC= "_ysc";
		$table_nameSSC= "_ssc";
		$table_nameTHETA= "_theta";
		$table_nameBETA= "_beta";
		$table_nameGAMMA="_gamma";
		$table_nameS= "_s";	
		
		// sanitize input of the query
		$TableToRead_f = mysql_real_escape_string($TableToRead_f);
		
		// contatempi
		//$t= microtime(true);
		//print " tempoFuncLoadDATAStart;".$t ."<br>";		
		
		$query = mysql_query("SELECT * FROM ".$TableToRead_f.$table_nameS."");
		$s_f = array();
		while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
			$s_f[] = $line;
		}
		
		$query = mysql_query("SELECT * FROM ".$TableToRead_f.$table_nameBETA."");
		$beta_f = array();
		while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
			$beta_f[] = $line;
		}
		
		$query = mysql_query("SELECT * FROM ".$TableToRead_f.$table_nameGAMMA."");
		$gamma_f = array();
		while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
			$gamma_f[] = $line;
		}
		
		$query = mysql_query("SELECT * FROM ".$TableToRead_f.$table_nameSSC."");
		$ssc_f = array();
		while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
			$ssc_f[] = $line;
		}
		
		$query = mysql_query("SELECT * FROM ".$TableToRead_f.$table_nameTHETA."");
		$theta_f = array();
		while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
			$theta_f[] = $line;
		}
		
		$query = mysql_query("SELECT * FROM ".$TableToRead_f.$table_nameYSC."");
		$ysc_f = array();
		while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
			$ysc_f[] = $line;
		}
		
		// contatempi
		//$t= microtime(true);
		//print " tempoFuncLoadDATAEnd;".$t ."<br>";	
		
		
		return array($s_f,$beta_f,$gamma_f,$ssc_f,$theta_f,$ysc_f);
		
		/* ------------------------------------------------------*/
		// ---------------------- STEP 4 ------------------------
	}
	
	
	
	
	// x is the variable containing 14 inputs to be used when evaluating the model
	//Capacity/Mass/Driving/Transmission/Traction/SS/BERS/MechLoad/AR/RR/Slope/T/P_C  ??
	function KrigingCo2mpas($x) {
		// start the kriging built on CO2MPAS
		// variable in function
		/**/
		$regrFun = array();
		$corrFun = array();
		$scalLoss=0;
		$scalLossL=0;
		$scalLossR=0;
		$loss=0;
		$corrFunFict=0;
		
		global $ysc; // 2 righe, 1 colonna, 2 elementi
		global $theta; // 15 righe, 1 colonna, 15 elementi
		global $ssc; // 15 righe, 2 colonne, 30 elementi
		global $gamma; // 1280 righe, 1 colonna, 1280 elementi
		global $beta; // 16 righe, 1 colonna, 16 elementi
		global $s; // 1280 righe, 15 colonne, 19200 elementi
		
		global $temp_dieci;
		global $temp_tredici;
		global $temp_corrFunFict;
		//global $temp_1;
		
		
		/*			
			tentare di usare array_map() per fare i calcoli sugli array... per velocizzare il kriging
			abbassando a 12 gli input (nuove tabelle) si abbassa di 1 secondo (da 4.5 a 3.5 il tempo di kriging)
		*/
		//15
		// begin of the calculations! 
		for ($a = 0; $a < 15; ++$a) {
			$x[$a] = ($x[$a]-$ssc[$a][1]) / ($ssc[$a][2]);			
			$temp_1[$a]=-1*$theta[$a][1];
		} 
		
		//16
		$regrFun[0]=1;		
		/* $regrFun deve avere 16 elementi  */
		for ($b = 1; $b < 16; ++$b) { 
			$regrFun[$b] = $x[$b-1];	
		}	
		
		//1280 15
		for ($c = 0; $c < 1280; ++$c) { 
			for ($d = 0; $d < 15; ++$d) {					
				//$temp_1=-1*$theta[$d][1];					
				$temp_2=($s[$c][$d+1]-$x[$d]);				
				$corrFunFict = $corrFunFict+($temp_1[$d]*$temp_2*$temp_2);
				// aggiunta per salvare i dati del 10 e 13 e mandarli nel prossimo loop
				if ($d==10) {
					$temp_dieci[$c]=$temp_1[$d]*$temp_2*$temp_2;
				}
				if ($d==13) {
					$temp_tredici[$c]=$temp_1[$d]*$temp_2*$temp_2;
				}
				
				//$corrFunFict = $corrFunFict+($temp_1*$temp_2*$temp_2);
				// this original code was slower!!
				//$corrFunFict = $corrFunFict+(-1*$theta[$d][1] * pow(($s[$c][$d+1]-$x[$d]),2) );	
			}	
			$corrFun[$c] = exp($corrFunFict);
			$temp_corrFunFict[$c]=$corrFunFict;
			$corrFunFict =0;
		}
		//1280
		for ($e = 0; $e < 1280; ++$e) {			
			$temp_scalR=$gamma[$e][1] * $corrFun[$e];			
			$scalLossR = $scalLossR +$temp_scalR;			
			// this original code was slower!!
			//$scalLossR = $scalLossR + $gamma[$e][1] * $corrFun[$e];
		}
		//16
		for ($f = 0; $f < 16; ++$f) {  
			$temp_scalL=$beta[$f][1] * $regrFun[$f];
			$scalLossL = $scalLossL +$temp_scalL;
			// this original code was slower!!
			//$scalLossL = $scalLossL + $beta[$f][1] * $regrFun[$f];
		}			
		$scalLoss= $scalLossR + $scalLossL;
		$loss=$ysc[0][1]+$ysc[1][1]*$scalLoss;
		
		
		// return $loss as result
		return $loss;
		
	}
	
	
	function KrigingCo2mpasSmall($x) {
		// start the kriging built on CO2MPAS
		// variable in function
		/**/
		$regrFun = array();
		$corrFun = array();
		$scalLoss=0;
		$scalLossL=0;
		$scalLossR=0;
		$loss=0;
		$corrFunFict=0;
		
		global $ysc; // 2 righe, 1 colonna, 2 elementi
		global $theta; // 15 righe, 1 colonna, 15 elementi
		global $ssc; // 15 righe, 2 colonne, 30 elementi
		global $gamma; // 1280 righe, 1 colonna, 1280 elementi
		global $beta; // 16 righe, 1 colonna, 16 elementi
		global $s; // 1280 righe, 15 colonne, 19200 elementi
		
		global $temp_dieci;
		global $temp_tredici;
		global $temp_corrFunFict;
		
		
		/*			
			tentare di usare array_map() per fare i calcoli sugli array... per velocizzare il kriging
			abbassando a 12 gli input (nuove tabelle) si abbassa di 1 secondo (da 4.5 a 3.5 il tempo di kriging)
		*/
		//15
		// begin of the calculations! 
		
		for ($a = 0; $a < 15; ++$a) {
			$x[$a] = ($x[$a]-$ssc[$a][1]) / ($ssc[$a][2]);
			$temp_1[$a]=-1*$theta[$a][1];
		}
		
		
		/*
		$a=10;		
		$x[$a] = ($x[$a]-$ssc[$a][1]) / ($ssc[$a][2]);
		$temp_1[$a]=-1*$theta[$a][1];
		$a=13;		
		$x[$a] = ($x[$a]-$ssc[$a][1]) / ($ssc[$a][2]);
		$temp_1[$a]=-1*$theta[$a][1];
		*/
		
		
		
		
		//16
		$regrFun[0]=1;		
		/* $regrFun deve avere 16 elementi  */
		for ($b = 1; $b < 16; ++$b) { 			
			$regrFun[$b] = $x[$b-1];			
		}	
		
		
		//1280 15
		for ($c = 0; $c < 1280; ++$c) { 
			
			$d=10;
			//$temp_1=-1*$theta[$d][1];					
			$temp_d=($s[$c][$d+1]-$x[$d]);	
			$temp_dieci_actual=$temp_1[$d]*$temp_d*$temp_d;				
			
			$d=13;
			//$temp_1=-1*$theta[$d][1];					
			$temp_t=($s[$c][$d+1]-$x[$d]);	
			$temp_tredici_actual=$temp_1[$d]*$temp_t*$temp_t;
			
			$temp_corrFunFict1[$c]=$temp_corrFunFict[$c]+($temp_dieci_actual-$temp_dieci[$c])+($temp_tredici_actual-$temp_tredici[$c]);		
			//$corrFunFict = $corrFunFict+($temp_1[$d]*$temp_2*$temp_2);
			$corrFun[$c] = exp($temp_corrFunFict1[$c]);
			
			//$corrFunFict =0;
			//$temp_scalR=$gamma[$c][1] * $corrFun[$c];
			//$scalLossR = $scalLossR +$temp_scalR;
			
		}
		
		
		/**/
		//1280
		for ($e = 0; $e < 1280; ++$e) {		
			
			
			$temp_scalR=$gamma[$e][1] * $corrFun[$e];
			$scalLossR = $scalLossR +$temp_scalR;
			// this original code was slower!!
			//$scalLossR = $scalLossR + $gamma[$e][1] * $corrFun[$e];
			
			
		}
		
		
		
		
		//16
		for ($f = 0; $f < 16; ++$f) {  
			$temp_scalL=$beta[$f][1] * $regrFun[$f];
			$scalLossL = $scalLossL +$temp_scalL;
			// this original code was slower!!
			//$scalLossL = $scalLossL + $beta[$f][1] * $regrFun[$f];
		}			
		$scalLoss= $scalLossR + $scalLossL;
		$loss=$ysc[0][1]+$ysc[1][1]*$scalLoss;
		
		
		// return $loss as result
		return $loss;
		
	}
	
	
	
	
	
	
	
	
	
	
	/* opzioni per velocizzare il codice: 
		1 - salvare tutti i valori di kriging nei loop in variabili di sessione SESSION (20527 valori per segmento) e calcolare solo un valore invece di 15 per ogni loop (bisogna creare una nuova funzione di kriging
		2 - cambiare tutto il codice di kriging usando il calcolo matriciale con (forse) la libreria di php https://mnshankar.wordpress.com/2011/05/01/regression-analysis-using-php/
		3 - Usare R come servizio esterno per il kriging
	*/
?>