<?php include("get_emission.php"); ?>
<?php 
	header('Access-Control-Allow-Origin: *');
	
	$request1 = file_get_contents("php://input"); // get the requests content from js file -- it's a tring
	$request = json_decode($request1);
	
	// contatempi
	//$t= microtime(true);
	//print " tempo0;".$t ."<br>";
	
	
	
	
	/* I have to clean up request to avoid injection, wrong inputs or similar */
	
	/*	*/
	//data from Google Maps
	$segment_time[]= $request->reqObj->mapData->allDuration ;// array of values, for every segment is time in seconds
	$segment_distance[]= $request->reqObj->mapData->allSteps ; // array of values, for each segment is lenght in metres
	//$segment_elevation[]= $request->reqObj->mapData->allElevation ; // array of values, for each segment a value from -10 to +10, devo calcolarla in base alle elevazioni
	$route_start_point_name=$request->reqObj->mapData->start_address ;  // lat and long of the starting location of the main route
	$route_start_point_lat_long[]=$request->reqObj->mapData->start_location ; // lat and long of the starting location of the main route
	$route_end_point_name=$request->reqObj->mapData->end_address ;  // lat and long of the starting location of the main route
	$route_end_point_lat_long[]=$request->reqObj->mapData->end_location ; // lat and long of the starting location of the main route
	// find the numbers of segments in the route
	$segment_numbers=count($segment_time[0]); // number of segments of the route	
	
	
	// order the elevations
	
	$unordered_elevation=$request->reqObj->mapData->allElevation ;
	$unordered_ele_order=$request->reqObj->mapData->elev_Order_Num ;
	for ($lp1=0;$lp1<$segment_numbers; $lp1++) {
		for ($lp2=0;$lp2<$segment_numbers; $lp2++) {
			if ($unordered_ele_order[$lp2]==$lp1) {
			$segment_elevation[$lp1]= $unordered_elevation[$lp2]; // array of values, for each segment a value from -10 to +10, devo calcolarla in base alle elevazioni
		}
	}
	}
	
	
	//print_r ($segment_elevation); die();
	
	
	
	$total_route_distance=0;
	$total_route_time=0;	
	$slope_per_segment=[];	
	// find medium speed for each segment		
	for ($lp1=0;$lp1<$segment_numbers; $lp1++) {	
		if ($segment_time[0][$lp1]<=1) { $segment_time[0][$lp1]=1; }
		$medium_speed_GM_real[$lp1]=$segment_distance[0][$lp1]/$segment_time[0][$lp1]; // speed is mt/sec
		$medium_speed_GM_real[$lp1]=($medium_speed_GM_real[$lp1]*18)/5; // convert speed to km/h
		
		$medium_speed_GM[$lp1]=round($medium_speed_GM_real[$lp1],-1);// round speed values to phases speed (0km/h to 130km/h)
		if ($medium_speed_GM[$lp1]>130) {$medium_speed_GM[$lp1]=130;} // check if some values are above the standard phases speeds
		if ($medium_speed_GM[$lp1]<0) {$medium_speed_GM[$lp1]=0;}// check if some values are under the standard phases speeds
		// find total distance of the route in Km
		$total_route_distance=$total_route_distance+$segment_distance[0][$lp1];
		//print "segment distance: ".$segment_distance[0][$lp1]." of segment: ". $lp1; print "<BR>";
	// find average speed of entire route in km/h
	$total_route_time=$total_route_time+$segment_time[0][$lp1];
	
	// find delta height for each segment
	$segment_delta_y[$lp1]= $segment_elevation[0][$lp1+1]-$segment_elevation[0][$lp1];		
	// find the height divided by the lenght
	$h_on_distance=$segment_delta_y[$lp1]/$segment_distance[0][$lp1];
	//  $h_on_distance must never outrange +1 or -1
	if ($h_on_distance>1) {$h_on_distance=0.9999;}
	if ($h_on_distance<-1) {$h_on_distance=-0.9999;}
	// calculate the slope
	$asin_per_segment[$lp1]=asin ($h_on_distance);
	$slope_per_segment[$lp1]=100*tan(asin ($h_on_distance));			
	// if slope out of range set it to max +10 or -10
	if ($slope_per_segment[$lp1]>10 ) { $slope_per_segment[$lp1]=10;}
	if ($slope_per_segment[$lp1]<-10 ) {$slope_per_segment[$lp1]=-10;}		
	}
	
	$total_route_distance=$total_route_distance/1000;
	$total_route_time=$total_route_time/3600; // transform time from seconds to hrs
	$average_route_speed=$total_route_distance/$total_route_time; // route average speed in km/h
	
	// find segment number that has InitT at 85 degrees C (after 200 seconds from start)
	$cold_start_segment=0;
	for ($lp2=0;$lp2<$segment_numbers; $lp2++) {			
		$cold_start_segment=$cold_start_segment+$segment_time[0][$lp2];
		//print $cold_start_segment;
		//print "<BR>";
		if ($cold_start_segment>=200) {
			$segmento_hot=$lp2;
			break;
		}			
	}
	if ($cold_start_segment=0) {
		$segmento_hot=0;			
	}	
	
	// contatempi
	//$t= microtime(true);
	//print " tempo1;".$t ."<br>";
	
	// default settings of User Interface
	$default_air_conditioning_UI=0; // Air conditioning OFF
	$default_roofbox_UI=1; // Roof box not present
	$default_gearbox_UI=1; // Gearbox set to automatic
	$default_car_traction_UI = 1; // 2WD (0), 4WD (1)
	$default_start_stop_UI = 0; // No (0) or YES (1)
	$default_brake_recuperation_UI = 0; // No (0) or YES (1)
	
	
	//$car_segment_UI read from cookie
	$car_segment_UI="A";
	if(isset($_COOKIE["CarSegmentCK"])) {    
		$car_segment_UI=$_COOKIE["CarSegmentCK"];
	}
	
	
	
	//print $car_segment_UI; die();
	/* -------- car optional settings -------- */
	$euro_standard_UI_modifier=1.00;
	$euro_standard_UI= $request->reqObj->carData->car_optional_settings->euroStd ;
	if ($euro_standard_UI=="4") {$euro_standard_UI_modifier=1.06; }
	if ($euro_standard_UI=="5") {$euro_standard_UI_modifier=1.02; }
	if ($euro_standard_UI=="6") {$euro_standard_UI_modifier=1.00; }
	
	
	$fuel_type_UI= $request->reqObj->carData->car_optional_settings->fuleType ;
	$engine_capacity_UI= $request->reqObj->carData->car_optional_settings->engineCap ;
	$engine_power_UI= $request->reqObj->carData->car_optional_settings->enginePow ;
	$fuel_price_UI= $request->reqObj->carData->car_optional_settings->fuelEnPrice ;
	
	/* ------ car advanced settings  -------- */
	$energy_price_UI=0.20; // Euro/kWh this one maybe we should keep it fixed, just change the fuel price	
	$energy_price_UI=$request->reqObj->carData->car_optional_settings->enrgEnPrice ;
	
	
	
	
	// find the gearbox type [transmission], manual (0) or automatic (1)
	if ($request->reqObj->carData->car_advance_settings->gearBoxType=="manual" ) {
		$gearbox_UI= 0;	
	}  
	elseif ($request->reqObj->carData->car_advance_settings->gearBoxType=="automatic") {
		$gearbox_UI=1 ;
		} else {
		$gearbox_UI=$default_gearbox_UI ;
	}	
	
	//print " --". $gearbox_UI;
	
	
	$car_weight_UI  = $request->reqObj->carData->car_advance_settings->carWeight ;
	// set the correct Traction 2WD (0) or 4WD (1)
	if ($request->reqObj->carData->car_advance_settings->traction=="2WD" ) {
		$car_traction_UI= 0;
	}  // ->isActive ; //
	elseif ($request->reqObj->carData->car_advance_settings->traction=="4WD") {
		$car_traction_UI=1 ;
		} else {
		$car_traction_UI=$default_car_traction_UI ;
	}
	
	
	// set the correct Start Stop  No (0) or YES (1)
	if ($request->reqObj->carData->car_advance_settings->startStop=="Yes" ) {		 
		$start_stop_UI= 1;
	}  // ->isActive ; //
	
	if ($request->reqObj->carData->car_advance_settings->startStop=="No") {	
		$start_stop_UI=0 ;
	} 
	
	
	//else {
	//$start_stop_UI=$default_start_stop_UI ;
	//}
	// set the correct BERS Brake energy recuperation  No (0) or YES (1)
	$brake_recuperation_UI= $default_brake_recuperation_UI;
	if ($request->reqObj->carData->car_advance_settings->breakEnergy=="Yes" ) {
		$brake_recuperation_UI= 1;
	}  // ->isActive ; //
	if ($request->reqObj->carData->car_advance_settings->breakEnergy=="No") {
		$brake_recuperation_UI=0 ;
	} 
	
	
	$intake_air_system_UI=0; // set to zero, changed by conditions later
	
	
	$tyres_class_UI=0; // prenderlo dalla user interface, sostituire Intake air system con Tyres class  TODO!!!
	$tyres_class_UI=$request->reqObj->carData->car_advance_settings->tyresClass ; //TO DO aggiungere nella UI!!!!	
	if ($tyres_class_UI=="A") { $tyres_class_UI=0.0065;}
	if ($tyres_class_UI=="B") { $tyres_class_UI=0.0077;}
	if ($tyres_class_UI=="C") { $tyres_class_UI=0.0088;}
	if ($tyres_class_UI=="D") { $tyres_class_UI=0.0065;} // la classe D andrebbe tolta?
	if ($tyres_class_UI=="E") { $tyres_class_UI=0.0095;}
	if ($tyres_class_UI=="F") { $tyres_class_UI=0.0111;}
	if ($tyres_class_UI=="G") { $tyres_class_UI=0.0124;}
	
	
	
	
	
	
	/* -------- Journey Options -------- */
	$passengers_number_UI= $request->reqObj->carData->journey_options->no_of_passanges ;	
	// modify the total weight of the car
	$car_weight_UI=$car_weight_UI+(70*$passengers_number_UI);
	
	
	// add driving style in interface   TO DO!!
	//$driving_style_UI= $request->reqObj->journey_options->no_of_passanges ;
	$driving_style_UI=1; // Driving style gentle, set to 1 for Aggressive driving style	
	/*
		$driving_style_UI=$request->reqObj->carData->journey_options->driving_style ;
		//print $driving_style_UI; die();
		if ($driving_style_UI=="Aggressive") {$driving_style_UI=1;}
		if ($driving_style_UI=="Normal") {$driving_style_UI=0;}
	*/
	
	
	$internal_luggage_UI= $request->reqObj->carData->journey_options->internal_luggage ;
	// modify the total weight of the car 
	$car_weight_UI=$car_weight_UI+$internal_luggage_UI;
	
	
	//$external_temperature_UI=$request->reqObj->journey_options->exernal_temprature ;
	$external_temperature_UI=22;	
	// calculate the Roof Box air resistance (ON = 1.20)
	if ($request->reqObj->carData->journey_options->roofbox=="Yes"  ) {
		$roofbox_UI= 1.25;
	}  // ->isActive ; //
	elseif ($request->reqObj->carData->journey_options->roofbox=="No") {
		$roofbox_UI=1 ;
		} else {
		$roofbox_UI=$default_roofbox_UI ;
	}		
	// calculate the Air Conditioning energy consumption in kW (ON = 0.8)
	$air_conditioning_UI= $default_air_conditioning_UI;
	if ($request->reqObj->carData->journey_options->air_condition=="on" ) {
		$air_conditioning_UI= 1;
	}  // ->isActive ; //
	if ($request->reqObj->carData->journey_options->air_condition=="off") {
		$air_conditioning_UI=0 ;
	} 
	
	// another value to set in kriging
	$P_C=$engine_power_UI/$engine_capacity_UI;
	
	
	
	
	/*
		if ($request->reqObj->journey_options->air_condition->isActive<>"true") {
		$air_conditioning_UI= $request->reqObj->journey_options->air_condition ;}
	*/
	
	
	$inflated_tyres_UI= 0; //  pneumatici gonfiati meno di tre mesi fa, aggiungere un tasto alla user interface, questo e' un moltiplicatore della $tyres_class_UI
	$inflated_tyres_UI= 1; //  aggiungere un tasto alla user interface, questo e' un moltiplicatore della $tyres_class_UI
	// the modifier inflated tyres changes the $tyres_class_UI factor adding a 15%
	if ($inflated_tyres_UI==1) {$tyres_class_UI=$tyres_class_UI+($tyres_class_UI/100*15); }
	
	
	// fixed values
	$external_temperature_UI=22;
	
	
	// calculate roof box air resistance
	//$roofbox_UI=1.0;
	
	//$max_power_required+???
	
	
	/*
		$route_frequency_UI
		$times_route_taken_UI
		$times_week_month_year_UI
		
		
		$route_steps_CO2; 	// array of values, contqains the CO2 emission of each segment, the number of elements represent the number of steps (Biagio output)
		$fuel_consumption; // array of values  (Biagio output)
		
		$medium_speed_pos; // array of values (Biagio output)
		$medium_power_pos; // array of values (Biagio output)
		$medium_power_neg; // array of values (Biagio output)
		$medium_power_ice; // array of values (Biagio output)
	*/
	
	/* check if car is turbo or aspirated (Gasoline fuel based cars, all Dielsels are Turbo) */
	If ($fuel_type_UI=="Gasoline" OR $fuel_type_UI=="Hybrid Gasoline" OR $fuel_type_UI=="PHEV Gasoline (Plugin Hybrid Electric Vehicle)" OR $fuel_type_UI=="GPL" OR $fuel_type_UI=="CNG" OR $fuel_type_UI=="Flexfuel (E85)") {		
		$engine_power_UI_Kw=$engine_power_UI*1.36;
		if (($engine_power_UI_Kw)>0.0699*$engine_capacity_UI - 13.282)  {
			$intake_air_system_UI=1;	// car is turbo
		}
	}
	/* if car is diesel then it is also turbo */
	If ($fuel_type_UI=="Diesel" OR $fuel_type_UI=="Hybrid Diesel" OR $fuel_type_UI=="PHEV Diesel (Plugin Hybrid Electric Vehicle)" OR $fuel_type_UI=="B100" ) {		
		$intake_air_system_UI=1;	// car is turbo/aspirated
	}
	
	// select the segment/fuel type/intake air system	
	if ($fuel_type_UI=="Diesel" OR $fuel_type_UI=="B100" OR $fuel_type_UI=="Hybrid Diesel" OR $fuel_type_UI=="PHEV Diesel (Plugin Hybrid Electric Vehicle)") {$TypeOfVehicle='D';}
	
	if (($fuel_type_UI=="Gasoline" OR $fuel_type_UI=="Hybrid Gasoline" OR $fuel_type_UI=="PHEV Gasoline (Plugin Hybrid Electric Vehicle)"  OR $fuel_type_UI=="GPL" OR $fuel_type_UI=="CNG" OR $fuel_type_UI=="Flexfuel (E85)" OR $fuel_type_UI=="B100") AND $intake_air_system_UI==0) {$TypeOfVehicle='GN';} // naturally aspirated
	
	if (($fuel_type_UI=="Gasoline" OR $fuel_type_UI=="Hybrid Gasoline" OR $fuel_type_UI=="PHEV Gasoline (Plugin Hybrid Electric Vehicle)"  OR $fuel_type_UI=="GPL" OR $fuel_type_UI=="CNG" OR $fuel_type_UI=="Flexfuel (E85)" OR $fuel_type_UI=="B100") AND $intake_air_system_UI==1) {$TypeOfVehicle='GT';} // turbo
	
	if ($fuel_type_UI=="BEV (Battery Electric Vehicle)") {$TypeOfVehicle='EV';}
	
	// define the exact vehicle type to select the correct table from Db
	if ($TypeOfVehicle<>"GN") {		
		$TypeOfVehicle=$car_segment_UI.$TypeOfVehicle;
	}
	if ($TypeOfVehicle=="GN") {
		$TypeOfVehicle=$TypeOfVehicle;
	}
	
	/*
		print "LOCALHOST<BR>";
		print   "car segment (A,B...): ".$car_segment_UI;
		print "<BR>";
		print   "Fuel/type : ". $fuel_type_UI;
		print "<BR>";
		print"Optional ------------<br>";
		print   "euro: ".$euro_standard_UI;
		print "<BR>";
		print   "engine capacity: " .$engine_capacity_UI;
		print "<BR>";
		print   "engine power: ".$engine_power_UI;
		print "<BR>";
		print   "fuel energy price: ".$fuel_price_UI;
		print "<BR>";
		print"Advanced ------------<br>";
		print   "gearbox (Man-Auto): ".$gearbox_UI;
		print "<BR>";
		print   "car weight: " .$car_weight_UI;
		print "<BR>";
		print   "car traction(0=2WD,1=4WD): ".$car_traction_UI;
		print "<BR>";
		print   "Tyres class(A to F): ".$tyres_class_UI;
		print "<BR>";	
		print   "start stop (yes, no): ".$start_stop_UI;
		print "<BR>";
		print   "BERS brake energy (yes,no): ".$brake_recuperation_UI;
		print "<BR>";
		print"Journey ------------<br>";
		print   "passengers: ".$passengers_number_UI;
		print "<BR>";
		print   "internal luggage: ".$internal_luggage_UI;
		print "<BR>";
		print   "roofbox: ".$roofbox_UI;
		print "<BR>";
		print   "driving style: ".$driving_style_UI;
		print "<BR>";
		print   "air conditioning: ".$air_conditioning_UI;
		print "<BR>";
		print   "TypeOfVehicle: ".$TypeOfVehicle;
		print "<BR>";
	*/
	//die();
	
	// contatempi
	//$t= microtime(true);
	//print " tempo2;".$t ."<br>";
	
	
	$CO2_emission_tot_gr=0;
	
	//##################### ICE MAIN LOOP #########################
	if ($fuel_type_UI=="Gasoline" OR $fuel_type_UI=="Diesel" OR $fuel_type_UI=="GPL" OR $fuel_type_UI=="CNG" OR $fuel_type_UI=="Flexfuel (E85)" OR $fuel_type_UI=="B100") {
		
		// contatempi
		//$t= microtime(true);
		//print " tempoICE1;".$t ."<br>";
		
		
		
		// select the tables name to load, the ICE engine cars will load only co2_emission tables
		$TableToRead=$TypeOfVehicle."_co2_emission";
		//print "table: " .$TableToRead; print "<BR>";
		$TableToRead=strtolower($TableToRead);
		// load all relevant tables from MySQL Db
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		
		// contatempi
		//$t= microtime(true);
		//print " tempoICE2;".$t ."<br>";
		
		
		
		
		// start loop for all segments
		for ($lp=0;$lp<$segment_numbers; $lp++) {
			
			
			// contatempi
			//$t= microtime(true);
			//print " tempoICEInizioLoop;".$t ."<br>";
			
			// call kriging to get co2 consumption for each segment
			// parameters to pass to Kriging: Capacity	Mass	Driving	Transmission	Traction	SS	BERS	MechLoad	AR	RR	Slope	T	P_C	AvgV	InitT
			// if the segment is above the 200 seconds the temperature is raised to 85 degrees
			$InitT=$external_temperature_UI;
			if ($lp>=$segmento_hot) {$InitT=85;}
			
			$start_stop_UI_kr=0;
			$air_conditioning_UI_kr=0;
			$brake_recuperation_UI_kr=0;
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI_kr,6=>$brake_recuperation_UI_kr,7=>$air_conditioning_UI_kr,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$lp],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$lp],14=>$InitT );
			
			
			// this value represent the Co2 emission per km for that segment 
			$CO2_emission_final=KrigingCo2mpas($kriging_input_values);
			if ($CO2_emission_final<0) { $CO2_emission_final=0; }
			//print "kriging input values:"; print_r($kriging_input_values); print "<br>";
			//print_r ($segment_distance[0][$lp]); 
			//print "<br>".$CO2_emission_final."<BR>" ; 
			
			
			// contatempi
			//$t= microtime(true);
			//print " tempoICEDopoKriging;".$t ."<br>";
			
			
			
			/**/
			// start and stop
			$Y=0;			
			if ($start_stop_UI==1 AND $InitT==85) {			
				$Y=1.066*exp(-0.08003*$medium_speed_GM[$lp]);			
			}	
			$Y=$Y*$segment_time[0][$lp]*0.315;
			$Y=$Y/($segment_distance[0][$lp]/1000);
			$CO2_emission_final=$CO2_emission_final-$Y;
			
			
			
			if ($fuel_type_UI=="Diesel" or $fuel_type_UI=="B100") {$carbon_content=3.153; $fuel_heating_value=43600;	$fuel_energy_density=35800;	}
			if ($fuel_type_UI=="Gasoline" or $fuel_type_UI=="GPL" or $fuel_type_UI=="CNG" or $fuel_type_UI=="Flexfuel (E85)") {$carbon_content=3.153; $fuel_heating_value=43600;	$fuel_energy_density=34200;	}
			
			
			$extraco2=0;
			/**/
			// aircondition ON
			if ($air_conditioning_UI==1) {
				if ($gearbox_UI==0) {
					$extraco2=max((-0.003*$medium_speed_GM[$lp] +0.51 )/100,0)*$fuel_energy_density/$fuel_heating_value*$carbon_content*1000;
				}
				if ($gearbox_UI==1) {
					$extraco2=max((-0.0006*$medium_speed_GM[$lp]+0.31 )/100,0)*$fuel_energy_density/$fuel_heating_value*$carbon_content*1000;
				}                                              
			}
			$CO2_emission_final=$CO2_emission_final+$extraco2; 
			
			// BERS
			$extraco2_bers=1;
			// BERS ON/OFF
			//PRINT "--".$brake_recuperation_UI;
			if ($brake_recuperation_UI==1) {
				$extraco2_bers=0.98;        
			}
			$CO2_emission_final=$CO2_emission_final*$extraco2_bers; 
			
			
			
			//$CO2_per_km=$liters_fuel_per_100km*100*$fuel_energy_density/$fuel_heating_value;
			//$CO2_per_km=round ($CO2_per_km,2);
			
			
			
			
			//
			
			$distan=$segment_distance[0][$lp]/1000;
			$CO2_emission_tot_gr=$CO2_emission_tot_gr+($CO2_emission_final*$distan);
			
			/*
				//print "Co2 emission:"; 
				print_r($CO2_emission_final); 
				print "<BR>";
				//print "segment distance:"; 
				print_r($distan); 
				print "<BR>";
				//print "Co2 emission sum:"; 
				print_r($CO2_emission_tot_gr); 
				print "<BR>";print "<BR>";
			*/
			
		}
		
		
		//die();
		
		//print "<BR>Route total distance:".$total_route_distance;
		$CO2_emission_gr_km=$CO2_emission_tot_gr/$total_route_distance;
		//print "Total CO2 per Km: ".$CO2_emission_gr_km;
		
		
		
		
		// end loop for all segment ----------------------------------------------------
		// $CO2_emission_all_segments represents the CO2 emission for all the route
		//print $CO2_emission_all_segments;
		$CO2_per_km= round(($CO2_emission_gr_km*$euro_standard_UI_modifier),0);
		
		
		
		//if ($fuel_type_UI=="Diesel" or $fuel_type_UI=="B100") {$carbon_content=3.153; $fuel_heating_value=43600;	$fuel_energy_density=35800;	}
		//if ($fuel_type_UI=="Gasoline" or $fuel_type_UI=="GPL" or $fuel_type_UI=="CNG" or $fuel_type_UI=="Flexfuel (E85)") {$carbon_content=3.153; $fuel_heating_value=43600;	$fuel_energy_density=34200;	}
		if ($fuel_type_UI=="Diesel" or $fuel_type_UI=="B100") {$carbon_content=3.16; $fuel_heating_value=43100;	$fuel_energy_density=35900;	}
		if ($fuel_type_UI=="Gasoline" or $fuel_type_UI=="GPL" or $fuel_type_UI=="CNG" or $fuel_type_UI=="Flexfuel (E85)") {$carbon_content=3.17; $fuel_heating_value=43200;	$fuel_energy_density=32200;	}
		
		$grams_fuel=$CO2_per_km/$carbon_content;	
		$fuel_energy=$grams_fuel*$fuel_heating_value;
		
		if ($fuel_type_UI=="GPL") {$carbon_content=3.014; $fuel_heating_value=46000;	$fuel_energy_density=26000;	}
		//if ($fuel_type_UI=="CNG") {$carbon_content=2.75; $fuel_heating_value=46000;	$fuel_energy_density=9000;	}
		if ($fuel_type_UI=="CNG") {$carbon_content=2.54; $fuel_heating_value=45100;	$fuel_energy_density=9000;	}
		if ($fuel_type_UI=="Flexfuel (E85)") {$carbon_content=2.093; $fuel_heating_value=29230;	$fuel_energy_density=22895;	}
		if ($fuel_type_UI=="B100") {$carbon_content=2.82; $fuel_heating_value=38000;	$fuel_energy_density=33400;	}	
		
		
		if ($fuel_type_UI=="GPL" OR $fuel_type_UI=="CNG" OR $fuel_type_UI=="Flexfuel (E85)" OR $fuel_type_UI=="B100") {
			$grams_new_fuel=$fuel_energy/$fuel_heating_value;
			$new_gr_CO2=$grams_new_fuel*$carbon_content;		
			$CO2_per_km=$new_gr_CO2;
			$CO2_per_km=round ($CO2_per_km,0);
		}
		$CO2_per_km=$CO2_per_km." g/km";
		
		// based upon the fuel type calculate the fuel consumption		
		if ($fuel_type_UI=="Diesel" or $fuel_type_UI=="Gasoline") {
			$grams_fuel=($fuel_energy/$fuel_energy_density)/1000;		
			$liters_fuel_per_100km=round (($grams_fuel*100),2);	
		}
		if ($fuel_type_UI=="CNG") {
			$grams_fuel_CNG=((43.2*$grams_fuel)/45.1);
			$liters_fuel_per_100km=round (($grams_fuel_CNG/10),2);	
		}
		if ($fuel_type_UI=="GPL") {
			$grams_fuel_GPL=((43.2*$grams_fuel)/46.0);
			$liters_fuel_per_100km=round (($grams_fuel_GPL/10),2);	
		}		
		if ($fuel_type_UI=="Flexfuel (E85)") { 
			$grams_fuel_E85=((43.2*$grams_fuel)/29.23);
			$liters_fuel_per_100km=round (($grams_fuel_E85/10),2);	
		}	
		if ($fuel_type_UI=="B100") {
			$grams_fuel_B100=((43.1*$grams_fuel)/37.2);
			$liters_fuel_per_100km=round (($grams_fuel_B100/10),2);	
		}	
		
		
		
		
		// based upon fuel consumption calculate total cost
		$liters_used_in_route=($liters_fuel_per_100km/100 )*$total_route_distance;
		
		$total_route_fuel_cost= round(($liters_used_in_route*$fuel_price_UI),2); // cost for 100km
		$total_route_fuel_cost=$total_route_fuel_cost. " Euro";
		
		if ($fuel_type_UI=="CNG") {
			$liters_fuel_per_100km=$liters_fuel_per_100km. " kg/100km";
			} else {
			$liters_fuel_per_100km=$liters_fuel_per_100km. " L/100km";
		}
		//print "total_route_distance:".$total_route_distance;
		
		// contatempi
		//$t= microtime(true);
		//print " tempoICEFineLoop;".$t ."<br>";
		
		
		
	}
	
	
	
	/* ############################ PURE ELECTRIC VEHICLES #########################################*/	
	if ($fuel_type_UI=="BEV (Battery Electric Vehicle)" ) {
		
		// contatempi
		//$t= microtime(true);
		//print " tempoBEVInizio;".$t ."<br>";
		
		
		
		/*	
			//data from Google Maps
			$segment_time // array of values, for every segment is time in seconds
			$segment_distance // array of values, for each segment is lenght in metres
			$segment_elevation // array of values, for each segment a value from -10 to +10, devo calcolarla in base alle elevazioni
			$route_start_point // lat and long of the starting location of the main route
			$route_end_point // lat e long of the destination location of the main route
			
			
			$medium_speed_GM; // array of values, devo calcolarla dai tempi diviso gli spazi per ogni segmento
			
			
			// data from User Interface UI
			$electric_load_UI
			$car_segment_UI
			$euro_standar_UI
			$fuel_type_UI
			$engine_capacity_UI
			$engine_power_UI
			$fuel_price_UI
			$energy_price_UI // this one maybe we should keep it fixed, just change the fuel price
			$gearbox_UI
			$car_weight_UI  // unique value in Kg
			$car_traction_UI
			$intake_air_system_UI // calcolato internamente
			$start_stop_UI
			$brake_recuperation_UI
			$passengers_number_UI
			$driving_style_UI
			$internal_luggage_UI
			$roofbox_UI
			$air_conditioning_UI
			$route_frequency_UI
			$times_route_taken_UI
			$times_week_month_year_UI
		*/
		
		//$route_steps_CO2; 	// array of values, contqains the CO2 emission of each segment, the number of elements represent the number of steps (Biagio output)
		//$fuel_consumption; // array of values  (Biagio output)
		
		//$medium_speed_pos; // array of values (Biagio output)
		//$medium_power_pos; // array of values (Biagio output)
		//$medium_power_neg; // array of values (Biagio output)
		//$medium_power_ice; // array of values (Biagio output)
		
		$TypeOfVehicle=$car_segment_UI."gt";
		
		// find the KRIGING values for all the necessaty variables
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on time_percentage_pos_mov_pow				
		$TableToRead=$TypeOfVehicle."_time_percentage_pos_mov_pow";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];			
		
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;			
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		// contatempi
		//$t= microtime(true);
		//print " tempoBEV1Krig;".$t ."<br>";
		
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_time_percentage_neg_mov_pow";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		// contatempi
		//$t= microtime(true);
		//print " tempoBEV2Krig;".$t ."<br>";
		
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_pos_motive_powers";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$medium_power_pos[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$medium_power_pos[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$medium_power_pos[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		
		// contatempi
		//$t= microtime(true);
		//print " tempoBEV3Krig;".$t ."<br>";		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_neg_motive_powers";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$medium_power_neg[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$medium_power_neg[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		
		
		
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$medium_power_neg[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		
		// contatempi
		//$t= microtime(true);
		//print " tempoBEV4Krig;".$t ."<br>";		
		
		
		
		// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		$el_load = $default_air_conditioning_UI;
		if ($air_conditioning_UI=="on") { $el_load = 1; }
		if ($air_conditioning_UI=="off") { $el_load = 0; }
		
		
		
		// el_load= $air_conditioning_UI; // array of values (Biagio output)
		// loading arrays from the request
		$sim_1 = $car_weight_UI;  // unique value in Kg.
		//$sim_2 = $CO2_emission_final; 	// CO2 emission value gr/Km of each segment
		//$sim_3 = $fuel_consumption; // fuel consumption per segment, to modify in the co2 loop code TODO
		//$sim_4 = $medium_speed_GM; // array of values, gia in codice
		
		//$sim_5 = $medium_speed_pos; // array of values (Biagio output) non usato in simulazione
		
		
		$sim_6 = $medium_power_pos; // array of values (Biagio output) av_pos_motive_powers calcolare con il kriging
		$sim_7 = $medium_power_neg; // array of values (Biagio output) av_neg_motive_powers calcolare con il kriging
		//$sim_8 = $medium_power_ice; // array of values (Biagio output) av_pos_engine_powers_out calcolare con il kriging
		
		$sim_9 = $air_conditioning_UI; // can be 0 or 1 .. TO CHECK WITH CLAUDIO!!!
		/*
			print_r ($sim_1); 
			print "<br>";
			print_r ($sim_2); 
			print "<br>";
			print_r ($sim_3); 
			print "<br>";
			print_r ($sim_4); 
			print "<br>";
			
			print_r ($sim_6); 
			print "<br>";
			
			print_r ($sim_7); 
			*//*
			print "<br>";
			print_r ($sim_8); 
			print "<br>";
			print_r ($sim_9); 
			print "<br>";
			die();
		*/
		
		
		
		//%% Travelled distance		
		// trasformati in km
		
		for ($rt=0;$rt<$segment_numbers; $rt++) {
			$dist[$rt]=$segment_distance[0][$rt]/1000;	// array of values in [km] of distance for each segment	
		}
		
		
		$soc_max = 90;
		$soc_min = 25;
		
		// Battery Definition	
		
		$n_module_serie= round(($sim_1*0.0031-2.7031)/10)*10; //calcolo il nr di celle in serie
		if ( $n_module_serie > 6) {$n_module_serie = 6;}
		if ( $n_module_serie < 1) {$n_module_serie = 1;}
		
		
		// $n_module_parallel = max([min([round(($sim_1(ii,2)*0.0063-7.4063)/10)*10;6]);1]);
		$n_module_parallel= round(($sim_1*0.0063-7.4063)/10)*10;// Number of modules in parallel
		if ( $n_module_parallel > 6) {$n_module_parallel = 6;}
		if ( $n_module_parallel < 1) {$n_module_parallel = 1;}
		
		
		//$E_batt_nom = max([min([round(($sim_1(ii,2)*0.0712-69.886)/10)*10;85]);14.5]); // 
		$E_batt_nom= round(($sim_1*0.0712-69.886)/10)*10;//Battery Energy [kWh]
		if ( $E_batt_nom > 85) {$E_batt_nom = 85;}
		if ( $E_batt_nom < 14.5) {$E_batt_nom = 14.5;}
		//print "-E_batt_nom-".$E_batt_nom;
		
		
		
		
		$E_batt_max = $soc_max/100*$E_batt_nom;	
		
		$E_batt_min = $soc_min/100*$E_batt_nom;
		
		$E_batt = $E_batt_max; // Starting Condition: Max Battery Charge
		//print "-E_batt_max-".$E_batt_max;
		//print "-E_batt_min-".$E_batt_min;
		
		// Electric Machine definition
		
		// $em_max = max([round(($sim_1(ii,2)*0.6493-897.13)/10)*10;47]);// Electric Machine Max Power
		$em_max= round(($sim_1*0.6493-897.13)/10)*10;//Battery Energy [kWh]
		if ( $em_max > 47) {$em_max = 47;}
		if ( $em_max < 10) {$em_max = 10;}
		//print "em_max ".$em_max;
		
		$em_min = -$em_max; // Electric Machine Min Power 
		
		//// Battery
		
		$N_cell_module = 48; // Number of cells per module
		$V_nom = 3.7; // Battery Nominal Voltage [V]
		
		//// Vehicle Efficiencies
		
		$eta_gb = 0.95; // Gear Box Efficiency
		$eta_em = 0.9; // Electric Motor Efficiency
		$eta_inv = 0.95; // Inverter Efficiency
		$eta_batt_inv = 0.95; // Efficiency between Battery and Inverter
		$eta_tot = $eta_gb*$eta_em*$eta_inv*$eta_batt_inv; // EV Global efficiency
		
		//// Regenerative Braking
		
		$brake_split = 50; // [//]
		$p_pos =array();
		$p_neg =array();
		
		
		// contatempi
		//$t= microtime(true);
		//print " tempoBEVpreloop;".$t ."<br>";
		
		
		
		for ($jj=0; $jj<$segment_numbers; $jj++) {
			
			// contatempi
			//$t= microtime(true);
			//print " tempoBEVinizioLoop;".$t ."<br>";			
			
			//print $sim_6[$jj]; 
			$p_pos[$jj] = $sim_6[$jj];
			$p_neg[$jj] = $sim_7[$jj];	
			
			
			$ac = $el_load;
			$time_acc[$jj]=($time_percentage_pos_mov_pow[$jj])*$segment_time[0][$jj]; //
			$time_dec[$jj]=($time_percentage_neg_mov_pow[$jj])*$segment_time[0][$jj]; //
			/*
				print "time_percentage_neg_mov_pow[$jj]".$time_percentage_neg_mov_pow[$jj]; print "<BR>";
				print "time_percentage_pos_mov_pow[$jj]".$time_percentage_pos_mov_pow[$jj]; print "<BR>";
				print "time_acc[$jj]".$time_acc[$jj]; print "<BR>";
				print "time_dec[$jj]".$time_dec[$jj]; print "<BR>";
				print "segment_time[]".$segment_time[0][$jj]; print "<BR>";
			*/
			if ($E_batt>$E_batt_min) {
				
				$pbatt_tr = $p_pos[$jj]/$eta_tot; // [kW]	
				//print "pbatt_tr".$pbatt_tr; print "<BR>";
				if ( $pbatt_tr > $em_max) {$pbatt_tr = $em_max;}
				
				
				$delta_E_batt_ev = $pbatt_tr*$time_acc[$jj]/3600; // Battery Energy Variation [kWh]
				//print "delta_E_batt_ev: ".$delta_E_batt_ev ; print "<BR>";
				//// Regenerative braking event 
				$pbatt_reg = $brake_split/100*$p_neg[$jj]*$eta_tot; // [kW]						
				if ( $pbatt_reg < $em_min) {$pbatt_reg = $em_min;}
				$delta_E_batt_reg = $pbatt_reg*$time_dec[$jj]/3600; // Battery Energy Variation [kWh]
				//print "p_neg: ".$p_neg[$jj];print "<BR>";
				//print "p_pos: ".$p_pos[$jj];print "<BR>";	
				//print "delta_E_batt_reg: ".$delta_E_batt_reg;print "<BR>";
				//// Air Conditioning				
				$delta_E_batt_ac = $ac*($segment_time[0][$jj])/3600; // Battery Energy Variation [kWh]
				//print "delta_E_batt_ac: ".$delta_E_batt_ac ; print "<BR>";
				//// Battery Energy Computation				
				$E_batt = $E_batt-($delta_E_batt_ev+$delta_E_batt_reg+$delta_E_batt_ac);
				//print "($delta_E_batt_ev+$delta_E_batt_reg+$delta_E_batt_ac):". ; 
				
				//print " E_batt: ".$E_batt ; print "<BR>";
				if ($E_batt>$E_batt_min ) { 
					$dist_ev[$jj] = $dist[$jj];
					$vehicle_stop[$jj] = 0;
					$E_batt_phase[$jj] = ($delta_E_batt_ev+$delta_E_batt_reg+$delta_E_batt_ac);					
				}   
				else {					
					$dist_ev[$jj] = 0;
					$vehicle_stop[$jj]  = 1;
					$E_batt_phase[$jj]  = 0;					
				} 	
			}
			else {				
				$dist_ev[$jj] = 0;
				$vehicle_stop[$jj]  = 1;
				$E_batt_phase[$jj]  = 0;		
			} 
		}
		// chiusura loop dei segmenti
		
		
		// contatempi
		//$t= microtime(true);
		//print " tempoBEVuscitaloop;".$t ."<br>";
		
		
		
		
		
		// $CO2_emission_all_segments represents the CO2 emission for all the route NOT IN BEV
		//print $CO2_emission_all_segments;
		//$CO2_per_km= round(($CO2_emission_gr_km*$euro_standard_UI_modifier),0);
		
		
		
		
		//$CO2_per_km, 'fuel_consumption' => $liters_fuel_per_100km, 'total_cost' => $total_route_fuel_cost))
		//$CO2_per_km, 'fuel_consumption' => $liters_fuel_per_100km, 'total_cost' => $total_route_fuel_cost))
		$Sum_E_batt_phase=0;
		$Sum_dist_ev=0;
		$Sum_vehicle_stop=0;
		for ($fg=0;$fg<$segment_numbers;$fg++) {
			$Sum_E_batt_phase=$Sum_E_batt_phase+$E_batt_phase[$fg];
			$Sum_dist_ev=$Sum_dist_ev+$dist_ev[$fg];
			$Sum_vehicle_stop=$Sum_vehicle_stop+$vehicle_stop[$fg];
		}
		
		// contatempi
		//$t= microtime(true);
		//print " tempoBEVMiddleLoop;".$t ."<br>";		
		
		/*
			print_r($E_batt_phase);
			print $Sum_E_batt_phase;
			
			print "Sum_dist_ev:".$Sum_dist_ev;
			print "total_route_distance".$total_route_distance;
		*/
		
		
		
		
		
		$CO2_per_km=0;	
		if ($Sum_vehicle_stop>=1) {
			$CO2_per_km="You have to stop the vehicle to recharge at ".round ($Sum_dist_ev,2)." km!";
		}
		if ($Sum_vehicle_stop=0) {
			$CO2_per_km="You don't emit CO2!" ;
		}
		
		
		
		$liters_fuel_per_100km=round(($Sum_E_batt_phase*1000/$Sum_dist_ev),2)." Wh/km";  // consumo specifico batteria
		//$liters_fuel_per_100km=($Sum_E_batt_phase*1000/$Sum_dist_ev)." Wh/km";
		$total_route_fuel_cost=round(($Sum_E_batt_phase*$energy_price_UI),2);// costo del viaggio per energia = 0.20 Euro/kWh
		//$total_route_fuel_cost=($Sum_E_batt_phase*$energy_price_UI);// costo del viaggio per energia = 0.20 Euro/kWh
		$total_route_fuel_cost=$total_route_fuel_cost. " Euro";
	}  // chiusura if per gli EV
	
	
	
	/* ############################ HYBRID VEHICLES #########################################*/	
	if ($fuel_type_UI=="Hybrid Gasoline" or $fuel_type_UI=="Hybrid Diesel") {
		/*	
			//data from Google Maps
			$segment_time // array of values, for every segment is time in seconds
			$segment_distance // array of values, for each segment is lenght in metres
			$segment_elevation // array of values, for each segment a value from -10 to +10, devo calcolarla in base alle elevazioni
			$route_start_point // lat and long of the starting location of the main route
			$route_end_point // lat e long of the destination location of the main route
			
			
			$medium_speed_GM; // array of values, devo calcolarla dai tempi diviso gli spazi per ogni segmento
			
			
			// data from User Interface UI
			$electric_load_UI
			$car_segment_UI
			$euro_standar_UI
			$fuel_type_UI
			$engine_capacity_UI
			$engine_power_UI
			$fuel_price_UI
			$energy_price_UI // this one maybe we should keep it fixed, just change the fuel price
			$gearbox_UI
			$car_weight_UI  // unique value in Kg
			$car_traction_UI
			$intake_air_system_UI // calcolato internamente
			$start_stop_UI
			$brake_recuperation_UI
			$passengers_number_UI
			$driving_style_UI
			$internal_luggage_UI
			$roofbox_UI
			$air_conditioning_UI
			$route_frequency_UI
			$times_route_taken_UI
			$times_week_month_year_UI
		*/
		
		//$route_steps_CO2; 	// array of values, contqains the CO2 emission of each segment, the number of elements represent the number of steps (Biagio output)
		//$fuel_consumption; // array of values  (Biagio output)
		
		//$medium_speed_pos; // array of values (Biagio output)
		//$medium_power_pos; // array of values (Biagio output)
		//$medium_power_neg; // array of values (Biagio output)
		//$medium_power_ice; // array of values (Biagio output)
		
		
		//echo($t . "<br>");
		//echo(date("Y-m-d",$t));
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBprekrig;".$t ."<br>";		
		
		
		
		// find the KRIGING values for all the necessaty variables
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on time_percentage_pos_mov_pow				
		$TableToRead=$TypeOfVehicle."_time_percentage_pos_mov_pow";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		
		
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBLoadData1;".$t ."<br>";				
		
		
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;			
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		//print "Airco : ".$air_conditioning_UI; print "<BR>";
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );			
			// this value represent the Co2 emission per km for that segment 
			$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);			
			}
		*/
		/**/
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 					
			$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);			
			
			}
		*/
		
		
		
		
		
		
		
		//print_r($time_percentage_pos_mov_pow);
		
		
		//print_r ($temp_dieci);
		
		
		//$t2= microtime(true);
		//print " tempo2: ".$t2 ."<br>";
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB1krig;".$t ."<br>";			
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_time_percentage_neg_mov_pow";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBLoadData2;".$t ."<br>";			
		
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			//$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			//$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
			/**/
			if ($gj==0) {
				$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
			}
			if ($gj<>0) {
				$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpasSmall($kriging_input_values);
			}		
			
		}
		
		//$t= microtime(true);
		//print " tempo3;".$t ."<br>";
		//$t3= microtime(true);
		//print " tempo3: ".$t3 ."<br>";
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB2krig;".$t ."<br>";	
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_willans_a";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBLoadData3;".$t ."<br>";			
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			//$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );	
			
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];
			// this value represent the Co2 emission per km for that segment 
			//$willans_a[$gj]=KrigingCo2mpas($kriging_input_values);
			/**/
			if ($gj==0) {
				$willans_a[$gj]=KrigingCo2mpas($kriging_input_values);
			}
			if ($gj<>0) {
				$willans_a[$gj]=KrigingCo2mpasSmall($kriging_input_values);
			}
			
		}
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB3krig;".$t ."<br>";	
		
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_willans_b";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBLoadData4;".$t ."<br>";			
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			//$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );			
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];
			// this value represent the Co2 emission per km for that segment 
			//$willans_b[$gj]=KrigingCo2mpas($kriging_input_values);
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			/**/
			if ($gj==0) {
				$willans_b[$gj]=KrigingCo2mpas($kriging_input_values);
			}
			if ($gj<>0) {
				$willans_b[$gj]=KrigingCo2mpasSmall($kriging_input_values);
			}	
			
		}
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB4krig;".$t ."<br>";			
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		$TableToRead=$TypeOfVehicle."_co2_emission";
		//print "table: " .$TableToRead; print "<BR>";
		$TableToRead=strtolower($TableToRead);
		// load all relevant tables from MySQL Db
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		// call kriging to get co2 consumption for each segment
		// parameters to pass to Kriging: Capacity	Mass	Driving	Transmission	Traction	SS	BERS	MechLoad	AR	RR	Slope	T	P_C	AvgV	InitT
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		$CO2_emission_tot_gr=0;
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBLoadData5;".$t ."<br>";			
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {
			if ($gj>=$segmento_hot) {$InitT=85;}
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];
			
			//$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );			
			// this value represent the Co2 emission per km for that segment 
			//$CO2_emission_final[$gj]=KrigingCo2mpas($kriging_input_values);
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			/**/
			if ($gj==0) {
				$CO2_emission_final[$gj]=KrigingCo2mpas($kriging_input_values);
			}
			if ($gj<>0) {
				$CO2_emission_final[$gj]=KrigingCo2mpasSmall($kriging_input_values);
			}		
			
			
			if ($CO2_emission_final[$gj]<0) { $CO2_emission_final[$gj]=0; }
			$distan=$segment_distance[0][$gj]/1000;
			$CO2_emission_tot_gr=$CO2_emission_tot_gr+($CO2_emission_final[$gj]*$distan);
		}
		$CO2_emission_gr_km=$CO2_emission_tot_gr/$total_route_distance;
		
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB5krig;".$t ."<br>";			
		
		
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_pos_motive_powers";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBLoadData6;".$t ."<br>";			
		
		
		
		
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			//$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );		
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];
			// this value represent the Co2 emission per km for that segment 
			//$medium_power_pos[$gj]=KrigingCo2mpas($kriging_input_values);
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			/**/
			if ($gj==0) {
				$medium_power_pos[$gj]=KrigingCo2mpas($kriging_input_values);
			}
			if ($gj<>0) {
				$medium_power_pos[$gj]=KrigingCo2mpasSmall($kriging_input_values);
			}		
			
			
			
		}
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB6krig;".$t ."<br>";	
		
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_neg_motive_powers";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBLoadData7;".$t ."<br>";			
		
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			//$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );		
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];
			// this value represent the Co2 emission per km for that segment 
			//$medium_power_neg[$gj]=KrigingCo2mpas($kriging_input_values);			
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			/**/
			if ($gj==0) {
				$medium_power_neg[$gj]=KrigingCo2mpas($kriging_input_values);
			}
			if ($gj<>0) {
				$medium_power_neg[$gj]=KrigingCo2mpasSmall($kriging_input_values);
			}	
			
		}
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB7krig;".$t ."<br>";			
		
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_pos_engine_powers_out";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBLoadData8;".$t ."<br>";			
		
		
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			//$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];
			// this value represent the Co2 emission per km for that segment 
			//$medium_power_ice[$gj]=KrigingCo2mpas($kriging_input_values);
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			/**/
			if ($gj==0) {
				$medium_power_ice[$gj]=KrigingCo2mpas($kriging_input_values);
			}
			if ($gj<>0) {
				$medium_power_ice[$gj]=KrigingCo2mpasSmall($kriging_input_values);
			}		
			
			
		}
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB8krig;".$t ."<br>";			
		
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_fuel_consumption";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		unset($temp_dieci);
		unset($temp_tredici);
		unset($temp_corrFunFict);
		
		$temp_dieci=array();
		$temp_tredici=array();
		$temp_corrFunFict=array();
		
		// call kriging to get co2 consumption for each segment			
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );
		
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			//$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );			
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];
			// this value represent the Co2 emission per km for that segment 
			//$fuel_consumption[$gj]=KrigingCo2mpas($kriging_input_values);
			
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			/**/
			if ($gj==0) {
				$fuel_consumption[$gj]=KrigingCo2mpas($kriging_input_values);
			}
			if ($gj<>0) {
				$fuel_consumption[$gj]=KrigingCo2mpasSmall($kriging_input_values);
			}		
			
		}
		
		
		//$t= microtime(true);
		//print " tempo4;".$t ."<br>";
		
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYB9krig;".$t ."<br>";			
		
		
		
		
		
		// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		$el_load = $default_air_conditioning_UI;
		if ($air_conditioning_UI=="1") { $el_load = 1; }
		if ($air_conditioning_UI=="0") { $el_load = 0; }
		//print "electric load: ".$el_load; print "<BR>";
		
		
		// el_load= $air_conditioning_UI; // array of values (Biagio output)
		// loading arrays from the request
		$sim_1 = $car_weight_UI;  // unique value in Kg.
		$sim_2 = $CO2_emission_final; 	// CO2 emission value gr/Km of each segment
		$sim_3 = $fuel_consumption; // fuel consumption per segment, to modify in the co2 loop code TODO
		$sim_4 = $medium_speed_GM; // array of values, gia in codice
		
		//$sim_5 = $medium_speed_pos; // array of values (Biagio output) non usato in simulazione
		
		
		$sim_6 = $medium_power_pos; // array of values (Biagio output) av_pos_motive_powers calcolare con il kriging
		$sim_7 = $medium_power_neg; // array of values (Biagio output) av_neg_motive_powers calcolare con il kriging
		$sim_8 = $medium_power_ice; // array of values (Biagio output) av_pos_engine_powers_out calcolare con il kriging
		
		$sim_9 = $air_conditioning_UI; // can be 0 or 1 .. TO CHECK WITH CLAUDIO!!!
		/*
			print "car_weight_UI: ";print_r ($sim_1); 
			print "<br>";
			//print "car_weight_UI SUM: ";print_r (array_sum($sim_1)); 
			print "<br>";
			print "CO2_emission_final: ";print_r ($sim_2); 
			print "<br>";
			print "CO2_emission_final SUM: ";print_r (array_sum($sim_2)); 
			print "<br>";
			print "fuel_consumption: ";print_r ($sim_3); 
			print "<br>";
			print "fuel_consumption SUM: ";print_r (array_sum($sim_3));
			print "<br>";
			print "medium_speed_GM: ";print_r ($sim_4); 
			print "<br>";
			print "medium_speed_GM SUM: ";print_r (array_sum($sim_4));
			print "<br>";
			
			print "medium_power_pos: ";print_r ($sim_6); 
			print "<br>";
			print "medium_power_pos SUM: ";print_r (array_sum($sim_6));
			print "<br>";
			print "medium_power_neg: ";print_r ($sim_7); 
			print "<br>";
			print "medium_power_neg SUM: ";print_r (array_sum($sim_7));
			print "<br>";
			print "medium_power_ice: ";print_r ($sim_8); 
			print "<br>";
			print "medium_power_ice SUM: ";print_r (array_sum($sim_8));
			print "<br>";
			print "air_conditioning_UI: ";print_r ($sim_9); 
			print "<br>";
		*/	
		
		// mostrare somma dei contenuti degli array!!!
		
		
		//die();
		
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBlooppettino0;".$t ."<br>";	
		
		//%% Travelled distance		
		// trasformati in km
		for ($rt=0;$rt<$segment_numbers; $rt++) {
			//if ($segment_distance[0][$lp1]<=1) { $segment_distance[0][$lp1]=1; }
			$dist[$rt]=$segment_distance[0][$rt]/1000;	// array of values in [km] of distance for each segment	
		}
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBlooppettino1;".$t ."<br>";	
		
		//%% Battery		
		$C = 6.5; //% Battery Capacity [Ah]
		
		//%% Regenerative Braking		
		$brake_split = 50; //% [%]
		
		//$willans_efficiency non lo carico nemmeno
		//%% fine variabili funzione BIAGIO
		
		//$ice = $fc_ice = $co2_ice = $soc_phase = $delta_soc = $co2_batt = $co2_ice_x = $fc_ice_x = 0;
		
		//% stato di carica max e min batteria
		$soc_high = 70;
		$soc_low = 40;
		$delta_soc_max = 25;
		$delta_soc_min = -25;
		$soc_th = $soc_high;
		//%% questa e' la densita' del fuel benzina, dovremo avere la formula corrispondente per il diesel
		if ($fuel_type_UI == "Hybrid Gasoline" ) { $ro_fuel = 0.740; }// [kg/l] benzina  
		if ($fuel_type_UI == "Hybrid Diesel" ) { $ro_fuel = 0.830;}  // [kg/l] diesel
		
		
		
		// find the cell number, based upon car wheight, and sets cell number not bigger that 240 or smaller than 120
		// $n_cell = max([min([round((0.1224*$sim_1(ii,2)-42.245)/10)*10;240]);120]); //%% calcolo il nr di celle
		$n_cell= round((0.1224*$sim_1-42.245)/10)*10;
		if ( $n_cell > 240) {$n_cell = 240;}
		if ( $n_cell < 120) {$n_cell = 120;}
		
		for ($jj=0; $jj<$segment_numbers; $jj++) {
			//%% il loop 
			// -- .. -- for $ii = 1:size($sim,1)
			
			// contatempi
			//$t= microtime(true);
			//print " tempoHYBInizioLoop;".$t ."<br>";				
			
			
			
			
			$soc = $soc_high; // Battery Fully Charged [%]
			$soc_th = $soc_low;
			
			
			
			//%% per il cold start, se siamo sotto i 300 secondi di tragitto, invece di fare il loop uso l'output di biagio (convenzionale)
			//%% loop principale    
			
			// -- .. --    for $jj = 1:size($sim_2,2)
			// main loop that checks all segments, one by one
			/* ***************************************************************/
			/* *********************** MAIN LOOP *****************************/
			/* ***************************************************************/
			//$segment_numbers= count($sim_2);
			
			
			//%% carica i dati per fase/segmento 
			
			$co2 = $sim_2[$jj];
			$fc = $sim_3[$jj];
			$v_avg = $sim_4[$jj];
			//$v_avg_p = $sim_5[$jj]; non usato 
			$p_pos = $sim_6[$jj];
			$p_neg = $sim_7[$jj];
			$pice = $sim_8[$jj];
			$ac = $el_load;
			
			$time_acc=($time_percentage_pos_mov_pow[$jj])*$segment_time[0][$jj]; //
			$time_dec=($time_percentage_neg_mov_pow[$jj])*$segment_time[0][$jj]; //
			
			//%% Filtering (is not a number)
			/* posso saltarlo credo e' solo un controllo di sicurezza per excel
				if isnan($p_neg)>0.5            
				$p_neg = 0;            
				else            
				end
			*/
			
			
			// Battery
			/* 
				$r0 = $n_cell*interp1(R0(:,1),R0(:,2),$soc)/1000; // [Ohm] 
				$ocv = $n_cell*interp1(OCV(:,1),OCV(:,2),$soc); // [V]  
			*/
			// arrotondo il valore per trovare la corrispondenza corretta come da excel NiMh_Cell
			// controllare soc, puo essere da 0 a 100...
			$soc_round=(round($soc,1))/100;			
			switch ($soc_round) {
				case (0):
				$r0 = ($n_cell*4.166666667)/1000;
				$ocv = $n_cell*1.20238095238095;
				break;
				case (0.1):
				$r0 = ($n_cell*3.68452380952381)/1000;
				$ocv = $n_cell*1.24630952380952;
				break;
				case (0.2):
				$r0 = ($n_cell*2.63690476190476)/1000;
				$ocv = $n_cell*1.27065476190476;
				break;
				case (0.3):
				$r0 = ($n_cell*2.36309523809524)/1000;
				$ocv = $n_cell*1.28732142857143;
				break;
				case (0.4):
				$r0 = ($n_cell*2.20238095238095)/1000;
				$ocv = $n_cell*1.30297619047619;
				break;
				case (0.5):
				$r0 = ($n_cell*2.14285714285714)/1000;
				$ocv = $n_cell*1.31244047619048;
				break;
				case (0.6):
				$r0 = ($n_cell*2.16666666666667)/1000;
				$ocv = $n_cell*1.31916666666667;
				break;	
				case (0.7):
				$r0 = ($n_cell*2.125)/1000;
				$ocv = $n_cell*1.32357142857143;
				break;
				case (0.8):
				$r0 = ($n_cell*2.16071428571429)/1000;
				$ocv = $n_cell*1.33482142857143;
				break;
				case (0.9):
				$r0 = ($n_cell*2.30357142857143)/1000;
				$ocv = $n_cell*1.3560119047619;
				break;
				case (1):
				$r0 = ($n_cell*2.38095238095238)/1000;
				$ocv = $n_cell*1.41244047619048;
				break;	
			}
			
			// contatempi
			//$t= microtime(true);
			//print " tempoHYBpostswitch;".$t ."<br>";	
			
			if ($soc>$soc_th) {
				//%% seleziono il file toyota auris data high
				
				// ICE ON/OFF 
				/*
					$index_v = find($v_avg>=$ice_on_th_high(:,3) & $v_avg<$ice_on_th_high(:,4));
					$index_soc = find($soc>=$ice_on_th_high(:,1) & $soc<$ice_on_th_high(:,2));
					$index = intersect($index_v,$index_soc);
					$p_on = $ice_on_th_high($index,6);
				*/
				// farlo in MYSQL DA TABELLA tOYOTA aURIS HIGH - ICE_ON_TRESHOLD
				// questo sara' un valore unico, non un array.. controllare l'output
				// old query $sql_query_index_v_soc="SELECT Avg_traction_power FROM auris_high_ice_on_threshold WHERE ".$v_avg.">=Speed_Min AND ".$v_avg."<Speed_Max AND ".$soc.">=SOC_Min AND ".$soc."<SOC_Max" ;
				$sql_query_index_v_soc="SELECT Avg_Traction_Power_Norm FROM auris_high_ice_on_threshold WHERE ".$v_avg." >= Speed_Min AND ".$v_avg." < Speed_Max AND ".$soc." >= SOC_Min AND ".$soc." < SOC_Max" ;
				$query = mysql_query($sql_query_index_v_soc);
				$s = array();
				while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
					$p_on1 = $line;
				}
				//$p_on = $p_on1['Avg_traction_power'];
				$p_on = $p_on1['Avg_Traction_Power_Norm']*$sim_1;
				//%% EV/Regenerative Braking Efficiencies
				//print "p_on da database: ". $p_on; print " quando SOC>SOC_TH <BR>";
				
				
				
				$eff_ev = 0.7;
				$eff_reg = 0.7;
				
				//%% Boost/Smart Charge
				/*
					$index_p = find($soc>=$hybrid_high(:,1) & $soc<$hybrid_high(:,2) &  $v_avg>=$hybrid_high(:,3) &...
					$v_avg<$hybrid_high(:,4) & $p_pos>=$hybrid_high(:,5) & $p_pos<$hybrid_high(:,6));		
					$boost_w = $hybrid_high($index_p,7);
					$smart_cgh_w = $hybrid_high($index_p,8);
				*/
				/*
					print "soc: ".$soc; 
					print "<BR>";
					print "v_avg: ".$v_avg; 
					print "<BR>";
					print "p_pos: ".$p_pos; 
					print "<BR>";
				*/
				// mi da come output 2 valori ditinti, controllare gli output
				$sql_query_index_b="SELECT Boost_Weight,Smart_Charge_Weight FROM auris_high_hybrid WHERE ".$v_avg.">=Speed_Min AND ".$v_avg." < Speed_Max AND ".$soc." >= SOC_Min AND ".$soc." < SOC_Max AND ".$p_pos." >= Min_Power AND ".$p_pos." < Max_Power " ;				
				//print $sql_query_index_b; die();
				$query = mysql_query($sql_query_index_b);
				$s = array();
				while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
					$output_query1 = $line;
				}
				
				$boost_w = $output_query1['Boost_Weight'];
				$smart_cgh_w = $output_query1['Smart_Charge_Weight'];
				
				$soc_th = $soc_low;
			}
			else {
				//%% seleziono il file toyota auris data low
				
				// ICE ON/OFF 
				/*
					$index_v = find($v_avg>=$ice_on_th_low(:,3) & $v_avg<$ice_on_th_low(:,4));
					$index_soc = find($soc>=$ice_on_th_low(:,1) & $soc<$ice_on_th_low(:,2));            
					$index = intersect($index_v,$index_soc);
					$p_on = $ice_on_th_low($index,6);
				*/
				// farlo in MYSQL DA TABELLA tOYOTA aURIS LOW - ICE_ON_TRESHOLD
				// questo sara' un valore unico, non un array.. controllare l'output
				// old query $sql_query_index_v_soc="SELECT Avg_traction_power FROM auris_low_ice_on_threshold WHERE ".$v_avg.">=Speed_Min AND ".$v_avg."<Speed_Max AND ".$soc.">=SOC_Min AND ".$soc."<SOC_Max" ;
				$sql_query_index_v_soc="SELECT Avg_Traction_Power_Norm FROM auris_low_ice_on_threshold WHERE ".$v_avg." >= Speed_Min AND ".$v_avg." < Speed_Max AND ".$soc." >= SOC_Min AND ".$soc." < SOC_Max" ;
				$query = mysql_query($sql_query_index_v_soc);
				$s = array();
				while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
					$p_on1 = $line;
				}
				//$p_on = $p_on1['Avg_traction_power'];
				$p_on = $p_on1['Avg_Traction_Power_Norm']*$sim_1;
				//%% EV/Regenerative Braking Efficiencies   
				
				//print "p_on da database: ". $p_on; print " quando SOC<SOC_TH <BR>";
				
				
				$eff_ev = 0.7;
				$eff_reg = 0.7;
				
				//%% Boost/Smart Charge
				/*
					$index_p = find($soc>=$hybrid_low(:,1) & $soc<$hybrid_low(:,2) &  $v_avg>=$hybrid_low(:,3) &...
					$v_avg<$hybrid_low(:,4) & $p_pos>=$hybrid_low(:,5) & $p_pos<$hybrid_low(:,6));
					
					$boost_w = $hybrid_low($index_p,7);
					$smart_cgh_w = $hybrid_low($index_p,8);
				*/
				// mi da come output 2 valori ditinti, controllare gli output
				$sql_query_index_b="SELECT Boost_Weight,Smart_Charge_Weight FROM auris_low_hybrid WHERE ".$v_avg." >= Speed_Min AND ".$v_avg." < Speed_Max AND ".$soc." >= SOC_Min AND ".$soc." < SOC_Max AND ".$p_pos." >= Min_Power AND ".$p_pos." < Max_Power " ;
				$query = mysql_query($sql_query_index_b);
				$s = array();
				while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
					$output_query2 = $line;
				}
				$boost_w = $output_query2['Boost_Weight'];
				$smart_cgh_w = $output_query2['Smart_Charge_Weight'];
				
				$soc_th = $soc_high;
				
			}
			
			//%% Traction
			
			if ($p_pos<=$p_on) {
				//%% motore combustione spento
				//$ice[$jj] = 0;   // non serve
				$fc_ice[$jj] = 0;
				$co2_ice[$jj] = 0;
				
				$pbatt_tr = $p_pos/$eff_ev+$ac; // [kW]   controllare se l'output del metamodel e' in KW con biagio        
				
				$v_batt_tr = ($ocv+sqrt(pow($ocv,2)-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
				
				$i_batt_tr = $pbatt_tr*1000/$v_batt_tr; // Battery Current [A]
				
				$delta_soc_ev = $i_batt_tr*$time_acc/(3600*$C)*100;
				$delta_soc_p = 0;
				
				$delta_soc_top=15;
				
				if ($delta_soc_ev>$delta_soc_top) {
					$ice[$jj] = 1;
					$dist_ev[$jj] = 0;                
					if ($boost_w == $smart_cgh_w) {                    
						$boost_w = 100;
						$smart_cgh = 200;
					}
					
					if ($boost_w>$smart_cgh_w) {
						//%% boost_Map_Simp in excel data_high
						/*
							$pbatt_tr = interp1($boost_high(:,1),$boost_high(:,2),$soc); //%% SQL cerco nella colonna 1 il valore soc ed estraggo il valore della colonna 2
							$v_batt_tr = ($ocv+sqrt($ocv^2-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
						*/
						
						$soc_rounded1=round($soc,1); // controllare se il rounding e' corretto
						if ($soc_rounded1<55.6) {$soc_rounded1=55.6;}
						if ($soc_rounded1>65.4) {$soc_rounded1=65.4;}
						$soc_rounded1_plus=$soc_rounded1+0.01;
						$soc_rounded1_minus=$soc_rounded1-0.01;
						$sql_query_index_boost_m="SELECT Battery_Power FROM auris_high_boost_map_simp WHERE SOC>=".$soc_rounded1_minus." AND SOC<=".$soc_rounded1_plus; // qui controllare che mi sembra strano che cerchi il valore 
						
						
						
						
						
						//print $sql_query_index_boost_m; die();
						$query = mysql_query($sql_query_index_boost_m);
						$s = array();
						while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
							$pbatt_tr1 = $line;
						}
						$pbatt_tr=$pbatt_tr1['Battery_Power'];
						
						
						$v_batt_tr = ($ocv+sqrt(pow($ocv,2)-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
						
					}
					
					if ($boost_w<$smart_cgh_w) {
						// usare Smart charge map simp da excel
						/*
							$pbatt_tr = interp1($smart_cgh_high(:,1),$smart_cgh_high(:,2),soc);
							$v_batt_tr = ($ocv+sqrt($ocv^2+4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
						*/
						$soc_rounded2=round($soc,1); // controllare se il rounding e' corretto
						if ($soc_rounded2<54.5) {$soc_rounded2=54.5;}
						if ($soc_rounded2>68.2) {$soc_rounded2=68.2;}
						$soc_rounded2_minus=$soc_rounded2-0.01;
						$soc_rounded2_plus=$soc_rounded2+0.01;
						$sql_query_index_smart_m="SELECT Battery_Power FROM auris_high_smart_charge_map_simp WHERE SOC>=".$soc_rounded2_minus." AND SOC<=".$soc_rounded2_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
						$query = mysql_query($sql_query_index_smart_m);
						$s = array();
						while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
							$pbatt_tr1 = $line;
						}
						$pbatt_tr=$pbatt_tr1['Battery_Power'];
						//print_r ($pbatt_tr); print "staminchia";
						$v_batt_tr = ($ocv+sqrt(pow($ocv,2)+4*$r0*$pbatt_tr*1000))/2;
						
						
					}
					
					/*
						print "willans_a[jj]: ".$willans_a[$jj]; print "<BR>";
						print "willans_b[jj]: ".$willans_b[$jj]; print "<BR>";
						print "pbatt_tr: ".$pbatt_tr; print "<BR>";
						print "time_acc: ".$time_acc; print "<BR>";
						print "dist: ".$dist[$jj]; print "<BR>";
					*/
					/*
						print_r ($co2_ice); 
						print "<br>";
						print "fc ice: ";
						print_r ($fc_ice); 
						die();
					*/
					
					
					
					
					$co2_batt = ($willans_a[$jj]+$willans_b[$jj]*(1/$pbatt_tr))*$pbatt_tr/3600*$time_acc/$dist[$jj]; // CO2 battery [g/km] 
					
					
					$co2_ice_temp[$jj] = $co2-$co2_batt; // [g/km]
					$co2_ice[$jj] = ($co2-$co2_batt)*$dist[$jj]; // [g per tutto il segmento]	
					
					
					
					
					//$fc_ice[$jj] = $fc-$co2_batt[$jj]*$dist[$jj]*0.0315/$ro_fuel; 
					$fc_ice[$jj] = $co2_ice[$jj]*0.315/($ro_fuel*1000); // lt 
					/*
						print "co2 ice: ";
						print_r ($co2_ice); 
						print "<br>";
						print "fc ice: ";
						print_r ($fc_ice); 
						die();
					*/
					$i_batt_tr = $pbatt_tr*1000/$v_batt_tr; // Battery Current [A]
					
					
					
					$delta_soc_p = $i_batt_tr*$time_acc/(3600*$C)*100;
					$delta_soc_ev = 0; 	
					
				}
				
				
				
				/* debug */
				//print "p_pos: ".$p_pos." in segmento ".$jj. " mentre p_on: ".$p_on." dove p_pos<= p_on"; print"<BR>";
				
				
			}
			if ($p_pos>$p_on) {
				//%% motore combustione acceso
				//$ice[$jj] = 1; // non serve
				
				/* debug */
				//print "p_pos: ".$p_pos." in segmento ".$jj. " mentre p_on: ".$p_on." dove p_pos> p_on"; print"<BR>";
				
				
				if ($boost_w == $smart_cgh_w){				
					$boost_w = 113 ; //%%mean(hybrid_high(:,7)); //%% sostituire con valore 113
					$smart_cgh = 122 ;	//%%mean(hybrid_high(:,8)); //%% sostituire con valore 122
					
					//print "boost_w ==  smart_cgh_w";print "<BR>";
				}
				
				
				if ($boost_w>$smart_cgh_w) {
					//%% boost_Map_Simp in excel data_high
					/*
						$pbatt_tr = interp1($boost_high(:,1),$boost_high(:,2),$soc); //%% SQL cerco nella colonna 1 il valore soc ed estraggo il valore della colonna 2
						$v_batt_tr = ($ocv+sqrt($ocv^2-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
					*/
					$soc_rounded1=round($soc,1); // controllare se il rounding e' corretto
					
					if ($soc_rounded1<55.6) {$soc_rounded1=55.6;}
					if ($soc_rounded1>65.4) {$soc_rounded1=65.4;}
					$soc_rounded1_plus=$soc_rounded1+0.01;
					$soc_rounded1_minus=$soc_rounded1-0.01;
					$sql_query_index_boost_m="SELECT Battery_Power FROM auris_high_boost_map_simp WHERE SOC>=".$soc_rounded1_minus." AND SOC<=".$soc_rounded1_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
					//print $sql_query_index_boost_m; die();
					$query = mysql_query($sql_query_index_boost_m);
					$s = array();
					while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
						$pbatt_tr1 = $line;
					}
					$pbatt_tr=$pbatt_tr1['Battery_Power'];
					
					
					$v_batt_tr = ($ocv+sqrt(pow($ocv,2)-4*$r0*($pbatt_tr+$ac)*1000))/2; // Battery voltage [V]
					
					//print "boost_w>smart_cgh_w";print "<BR>";
					
				}
				
				if ($boost_w<$smart_cgh_w) {
					// usare Smart charge map simp da excel
					/*
						$pbatt_tr = interp1($smart_cgh_high(:,1),$smart_cgh_high(:,2),soc);
						$v_batt_tr = ($ocv+sqrt($ocv^2+4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
					*/
					$soc_rounded2=round($soc,1); // controllare se il rounding e' corretto
					if ($soc_rounded2<54.5) {$soc_rounded2=54.5;}
					if ($soc_rounded2>68.2) {$soc_rounded2=68.2;}
					$soc_rounded2_minus=$soc_rounded2-0.01;
					$soc_rounded2_plus=$soc_rounded2+0.01;
					$sql_query_index_smart_m="SELECT Battery_Power FROM auris_high_smart_charge_map_simp WHERE SOC>=".$soc_rounded2_minus." AND SOC<=".$soc_rounded2_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
					$query = mysql_query($sql_query_index_smart_m);
					$s = array();
					while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
						$pbatt_tr = $line;
					}
					$v_batt_tr = ($ocv+sqrt(pow($ocv,2)+4*$r0*($pbatt_tr+$ac)*1000))/2;
					//print "boost_w<smart_cgh_w";print "<BR>";
					
				}
				/*
					print "willans_a[jj]: ".$willans_a[$jj]; print "<BR>";
					print "willans_b[jj]: ".$willans_b[$jj]; print "<BR>";
					print "pbatt_tr: ".$pbatt_tr; print "<BR>";
					print "time_acc: ".$time_acc; print "<BR>";
					print "dist: ".$dist[$jj]; print "<BR>";
				*/
				
				
				$co2_batt = ($willans_a[$jj]+$willans_b[$jj]*(1/$pbatt_tr))*$pbatt_tr/3600*$time_acc/$dist[$jj]; // CO2 battery [g/km] 
				
				//$co2_batt[$jj] = $willans[$jj]*$pbatt_tr/3600*$time_acc[$jj]/$dist[$jj]; // CO2 battery [g/km]
				
				
				$co2_ice_temp[$jj] = $co2-$co2_batt; // [g/km]
				$co2_ice[$jj] = ($co2-$co2_batt)*$dist[$jj]; // [g per tutto il segmento]	
				
				
				
				
				//$fc_ice[$jj] = $fc-$co2_batt*$dist[$jj]*0.0315/$ro_fuel; 
				$fc_ice[$jj] = $co2_ice[$jj]*0.315/($ro_fuel*1000); // lt 
				/*
					print "co2 ice: ";
					print_r ($co2_ice); 
					print "<br>";
					print "fc ice: ";
					print_r ($fc_ice); 
					die();
				*/
				$i_batt_tr = $pbatt_tr*1000/$v_batt_tr; // Battery Current [A]
				
				$delta_soc_p = $i_batt_tr*$time_acc/(3600*$C)*100;
				$delta_soc_ev = 0;   
				//$pice = $pice-$pbatt_tr; // [kW]
				
				
			}
			//%% Regenerative braking event
			
			// contatempi
			//$t= microtime(true);
			//print " tempoHYBpostcalculus;".$t ."<br>";			
			
			
			$pbatt_reg = ($brake_split/100)*$p_neg*$eff_reg;     
			
			$v_batt_reg = ($ocv+sqrt(pow($ocv,2)+4*$r0*($pbatt_tr+$ac)*1000))/2; // Battery voltage [V]
			
			$i_batt_reg = $pbatt_reg*1000/$v_batt_reg; // Battery Current [A]
			
			$delta_soc_reg = $i_batt_reg*$time_dec[$jj]/(3600*$C)*100;
			
			
			//%% Air Conditioning
			
			//$v_batt_ac = ($ocv+sqrt(pow($ocv,2)-4*$r0*$ac*1000))/2; // Battery voltage [V]
			
			//$i_batt_ac = $ac*1000/$v_batt_ac; // Battery Current [A]
			
			//$delta_soc_ac = $i_batt_ac*($segment_time[0][$jj])/(3600*$C)*100;
			//print "Delta suca ac: ".$delta_soc_ac; print "<BR>";
			
			
			//%% SOC Computation
			
			// $delta_soc[$jj] = max([min([$delta_soc_max;$delta_soc_reg+$delta_soc_p+$delta_soc_ev+$delta_soc_ac]);$delta_soc_min]);
			
			//$delta_soc[$jj]= $delta_soc_reg+$delta_soc_p+$delta_soc_ev+$delta_soc_ac;		
			$delta_soc[$jj]= $delta_soc_reg+$delta_soc_p+$delta_soc_ev;
			//print "delta_soc: ". $delta_soc[$jj]. "  delta_soc_reg:". $delta_soc_reg. "  delta_soc_p:". $delta_soc_p."  delta_soc_ev:". $delta_soc_ev; print "<BR>";
			
			if ( $delta_soc[$jj] > $delta_soc_max) {$delta_soc[$jj] = $delta_soc_max;}
			if ( $delta_soc[$jj] < $delta_soc_min) {$delta_soc[$jj] = $delta_soc_min;}
			
			
			//print "delta_soc post: ". $delta_soc[$jj];print "<BR>";
			
			//  $soc = max([$soc_low;min([$soc-delta_soc($ii,$jj);$soc_high])]);
			$soc= $soc-$delta_soc[$jj];
			if ( $soc > $soc_high) {$soc = $soc_high;}
			if ( $soc < $soc_low) {$soc = $soc_low;}
			
			
			
			$soc_phase[$jj] = $soc;
			//print "--soc_phase: ". $soc_phase[$jj];print "<BR>";
			
			// contatempi
			//$t= microtime(true);
			//print " tempoHYBfineLoop;".$t ."<br>";				
			
		}
		/* ***************************************************************/
		/* ********************END MAIN LOOP *****************************/
		/* ***************************************************************/		
		$co2_ice_sum=0;
		$fc_ice_sum=0;
		for ($tg=0; $tg<$segment_numbers;$tg++) {
			$co2_ice_sum=$co2_ice_sum+$co2_ice[$tg];
			$fc_ice_sum=$fc_ice_sum+$fc_ice[$tg];	
			/**/
			//print_r($co2_ice[$tg]); 
			//Print "<BR>";
			//print_r($fc_ice[$tg]);
			//Print "<BR>";
			
		}
		//print "segment_numbers: ".$segment_numbers;
		
		
		
		
		
		$co2_km=$co2_ice_sum/$total_route_distance;
		$fc_100km=$fc_ice_sum/$total_route_distance*100;
		
		//sets the difference of consumption and emission based upon Euro standards 6 - 5 - 4
		$co2_km= $co2_km*$euro_standard_UI_modifier;
		$fc_100km=$fc_100km*$euro_standard_UI_modifier;
		
		
		
		
		//%% fine loop - risultato CO2 per segmento co2_ice(ii,jj)  , sommo tutti i valori e divido per la distanza totale della route per trovare la CO2 totale per km
		// --------------- da scrivere il risultato: co2_ice(jj)
		//end
		

		
		
		
		$CO2_per_km= round($co2_km,0);
		$liters_fuel_per_100km=round ($fc_100km,2);
		//print "total_route_distance:".$total_route_distance;
		$liters_used_in_route=($liters_fuel_per_100km/100 )*$total_route_distance;	
		$total_route_fuel_cost= round(($liters_used_in_route*$fuel_price_UI),2);
		$total_route_fuel_cost=$total_route_fuel_cost. " Euro";
		$liters_fuel_per_100km=$liters_fuel_per_100km. " L/100km";
		$CO2_per_km=$CO2_per_km. " g/km";
		/*
			print " liters_used_in_route: ".$liters_used_in_route. "<BR>";
			print " liters_fuel_per_100km: ".$liters_fuel_per_100km. "<BR>";
			print " total_route_distance: ".$total_route_distance. "<BR>";
		*/
		
		// contatempi
		//$t= microtime(true);
		//print " tempoHYBFineHEV;".$t ."<br>";	
		
		/* ############################ END ELECTRIC VEHICLES #########################################*/	
	} // End HEV calculations
	
	
	
	
	/* ############################ PLUGIN VEHICLES #########################################*/	
	if ($fuel_type_UI=="PHEV Gasoline (Plugin Hybrid Electric Vehicle)" or $fuel_type_UI=="PHEV Diesel (Plugin Hybrid Electric Vehicle)") {
		
		
		
		
		// find the KRIGING values for all the necessaty variables
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on time_percentage_pos_mov_pow				
		$TableToRead=$TypeOfVehicle."_time_percentage_pos_mov_pow";
		$TableToRead=strtolower($TableToRead);			
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];			
		
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;			
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$time_percentage_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_time_percentage_neg_mov_pow";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$time_percentage_neg_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_willans_a";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$willans_a[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$willans_a[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$willans_a[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_willans_b";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$willans_b[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$willans_b[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$willans_b[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		$TableToRead=$TypeOfVehicle."_co2_emission";
		//print "table: " .$TableToRead; print "<BR>";
		$TableToRead=strtolower($TableToRead);
		// load all relevant tables from MySQL Db
		$combo_multi=LoadDataForKriging($TableToRead);			
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
		// call kriging to get co2 consumption for each segment
		// parameters to pass to Kriging: Capacity	Mass	Driving	Transmission	Traction	SS	BERS	MechLoad	AR	RR	Slope	T	P_C	AvgV	InitT
		// if the segment is above the 200 seconds the temperature is raised to 85 degrees
		$InitT=$external_temperature_UI;
		$CO2_emission_tot_gr=0;
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$CO2_emission_final[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$CO2_emission_final[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}	
			
			if ($CO2_emission_final[$gj]<0) { $CO2_emission_final[$gj]=0; }
			$distan=$segment_distance[0][$gj]/1000;
			$CO2_emission_tot_gr=$CO2_emission_tot_gr+($CO2_emission_final[$gj]*$distan);
			
		}
		
		
		
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {
			if ($gj>=$segmento_hot) {$InitT=85;}
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );			
			// this value represent the Co2 emission per km for that segment 
			$CO2_emission_final[$gj]=KrigingCo2mpas($kriging_input_values);
			if ($CO2_emission_final[$gj]<0) { $CO2_emission_final[$gj]=0; }
			$distan=$segment_distance[0][$gj]/1000;
			$CO2_emission_tot_gr=$CO2_emission_tot_gr+($CO2_emission_final[$gj]*$distan);
			}
		*/
		
		
		$CO2_emission_gr_km=$CO2_emission_tot_gr/$total_route_distance;
		
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_pos_motive_powers";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$medium_power_pos[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$medium_power_pos[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		
		
		
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$medium_power_pos[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_neg_motive_powers";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$medium_power_neg[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$medium_power_neg[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$medium_power_neg[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_pos_engine_powers_out";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$medium_power_ice[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$medium_power_ice[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$medium_power_ice[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_fuel_consumption";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$fuel_consumption[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$fuel_consumption[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$fuel_consumption[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_pos_accelerations";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$av_pos_accelerations[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$av_pos_accelerations[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$av_pos_accelerations[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		// clear arrays
		$s=array();
		$beta=array();
		$gamma=array();
		$ssc=array();
		$theta=array();
		$ysc=array();
		// load tabels data for kriging on $time_percentage_neg_mov_pow				
		$TableToRead=$TypeOfVehicle."_av_vel_pos_mov_pow";
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
		$InitT=$external_temperature_UI;
		//if ($gj>=$segmento_hot) {$InitT=85;}		
		
		$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>'100',11=>$external_temperature_UI,12=>$P_C,13=>'100',14=>$InitT );	
		
		for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values[10]=$slope_per_segment[$gj];
			$kriging_input_values[13]=$medium_speed_GM_real[$gj];						
			// this value represent the Co2 emission per km for that segment 
			// run only the first kriging with all parameters
			if ($gj==0) {
				$av_vel_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
				//print "loop: ".$gj;print_r ($temp_corrFunFict);
			}
			if ($gj<>0) {
				$av_vel_pos_mov_pow[$gj]=KrigingCo2mpasSmall($kriging_input_values);
				//print "loop: ".$gj; print_r ($temp_corrFunFict);
			}			
		}
		
		/*
			for ($gj=0;$gj<$segment_numbers; $gj++) {	
			$kriging_input_values = array(0=>$engine_capacity_UI,1=>$car_weight_UI,2=>$driving_style_UI,3=>$gearbox_UI,4=>$car_traction_UI,5=>$start_stop_UI,6=>$brake_recuperation_UI,7=>$air_conditioning_UI,8=>$roofbox_UI,9=>$tyres_class_UI,10=>$slope_per_segment[$gj],11=>$external_temperature_UI,12=>$P_C,13=>$medium_speed_GM_real[$gj],14=>$InitT );						
			// this value represent the Co2 emission per km for that segment 
			$av_vel_pos_mov_pow[$gj]=KrigingCo2mpas($kriging_input_values);
			}
		*/
		
		
		
		
		// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		$el_load = $default_air_conditioning_UI;
		if ($air_conditioning_UI=="1") { $el_load = 1; }
		if ($air_conditioning_UI=="0") { $el_load = 0; }
		
		
		
		// el_load= $air_conditioning_UI; // array of values (Biagio output)
		// loading arrays from the request
		$sim_1 = $car_weight_UI;  // unique value in Kg.
		$sim_2 = $CO2_emission_final; 	// CO2 emission value gr/Km of each segment
		$sim_3 = $fuel_consumption; // fuel consumption per segment, to modify in the co2 loop code TODO
		$sim_4 = $medium_speed_GM; // array of values, gia in codice
		
		//$sim_5 = $medium_speed_pos; // array of values (Biagio output) non usato in simulazione
		
		
		$sim_6 = $medium_power_pos; // array of values (Biagio output) av_pos_motive_powers calcolare con il kriging
		$sim_7 = $medium_power_neg; // array of values (Biagio output) av_neg_motive_powers calcolare con il kriging
		$sim_8 = $medium_power_ice; // array of values (Biagio output) av_pos_engine_powers_out calcolare con il kriging
		
		$sim_9 = $air_conditioning_UI; // can be 0 or 1 .. TO CHECK WITH CLAUDIO!!!
		$sim_10 = $av_pos_accelerations;
		
		/*
			print_r ($sim_1); 
			print "<br>";
			print_r ($sim_2); 
			print "<br>";
			print_r ($sim_3); 
			print "<br>";
			print_r ($sim_4); 
			print "<br>";
			
			print_r ($sim_6); 
			print "<br>";
			print_r ($sim_7); 
			print "<br>";
			print_r ($sim_8); 
			print "<br>";
			print_r ($sim_9); 
			print "<br>";
			die();
		*/
		
		
		
		//%% Travelled distance		
		// trasformati in km
		for ($rt=0;$rt<$segment_numbers; $rt++) {
			$dist[$rt]=$segment_distance[0][$rt]/1000;	// array of values in [km] of distance for each segment	
		}
		
		
		//%% Battery		
		$C = 22; //% Battery Capacity [Ah]
		
		//%% Regenerative Braking		
		$brake_split = 50; //% [%]
		
		//$willans_efficiency non lo carico nemmeno
		//%% fine variabili funzione BIAGIO
		
		//$ice = $fc_ice = $co2_ice = $soc_phase = $delta_soc = $co2_batt = $co2_ice_x = $fc_ice_x = 0;
		
		//% stato di carica max e min batteria
		$soc_cd = 85.0;
		$soc_cs = 30.0;
		$delta_soc_max = 40.0;
		$delta_soc_min = -20.0;
		
		//%% questa e' la densita' del fuel benzina, dovremo avere la formula corrispondente per il diesel
		if ($fuel_type_UI == "PHEV Gasoline (Plugin Hybrid Electric Vehicle)" ) { $ro_fuel = 0.740; }// [kg/l] benzina  
		if ($fuel_type_UI == "PHEV Diesel (Plugin Hybrid Electric Vehicle)" ) { $ro_fuel = 0.830;}  // [kg/l] diesel
		//if ($fuel_type_UI == "Flexi" ) { $ro_fuel = 0.0000;}  // [g/l] flexi fuel --- da settare!!!
		
		$soc = $soc_cd; // Battery Fully Charged [%]
		$soc_th = $soc_cs;
		
		
		$n_cell= round(((0.0562*$sim_1-25.884)/10)*10);
		if ( $n_cell > 120) {$n_cell = 120;}
		if ( $n_cell < 56) {$n_cell = 56;}
		
		
		for ($jj=0; $jj<$segment_numbers; $jj++) {
			//%% il primo loop non si fa', si conta solo il secondo
			// -- .. -- for $ii = 1:size($sim,1)
			
			//%% per il cold start, se siamo sotto i 300 secondi di tragitto, invece di fare il loop uso l'output di biagio (convenzionale)
			//%% loop principale    
			
			// -- .. --    for $jj = 1:size($sim_2,2)
			// main loop that checks all segments, one by one
			/* ***************************************************************/
			/* *********************** MAIN LOOP *****************************/
			/* ***************************************************************/
			//$segment_numbers= count($sim_2);
			//print  "soc:".$soc ."  --  "; print $jj."<br>medium_power_pos:";
			//print_r ($medium_power_pos);
			//%% carica i dati per fase/segmento 
			
			$co2 = $sim_2[$jj];
			$fc = $sim_3[$jj];
			$v_avg = $sim_4[$jj];
			//$v_avg_p = $sim_5[$jj]; non usato 
			$p_pos= $sim_6[$jj];
			$p_neg = $sim_7[$jj];
			$pice = $sim_8[$jj];
			$ac = $el_load;
			$acc_vel = $sim_10[$jj];
			
			$time_acc=($time_percentage_pos_mov_pow[$jj])*$segment_time[0][$jj]; //
			$time_dec=($time_percentage_neg_mov_pow[$jj])*$segment_time[0][$jj]; //
			/*
				print_r($time_percentage_pos_mov_pow);
				print "yo";
				print_r($time_percentage_neg_mov_pow);
			*/
			// arrotondo il valore per trovare la corrispondenza corretta come da excel NiMh_Cell
			// controllare soc, puo essere da 0 a 100...
			$soc_round1=$soc/100;
			$soc_round=(round($soc_round1,1));	
			// print ($soc_round); 
			//Print "<BR> ---";
			
			switch ($soc_round) {
				case (0):
				$r0 = ($n_cell*1.76)/1000;
				$ocv = $n_cell*3.2026;
				break;
				case (0.1):
				$r0 = ($n_cell*1.76)/1000;
				$ocv = $n_cell*3.2026;
				break;
				case (0.2):
				$r0 = ($n_cell*1.5290)/1000;
				$ocv = $n_cell*3.2549;
				break;
				case (0.3):
				$r0 = ($n_cell*1.4116)/1000;
				$ocv = $n_cell*3.2970;
				break;
				case (0.4):
				$r0 = ($n_cell*1.2941)/1000;
				$ocv = $n_cell*3.3113;
				break;
				case (0.5):
				$r0 = ($n_cell*1.2470)/1000;
				$ocv = $n_cell*3.3116;
				break;
				case (0.6):
				$r0 = ($n_cell*1.2)/1000;
				$ocv = $n_cell*3.3143;
				break;	
				case (0.7):
				$r0 = ($n_cell*1.1529)/1000;
				$ocv = $n_cell*3.3234;
				break;
				case (0.8):
				$r0 = ($n_cell*1.1059)/1000;
				$ocv = $n_cell*3.3454;
				break;
				case (0.9):
				$r0 = ($n_cell*1.0588)/1000;
				$ocv = $n_cell*3.3489;
				break;
				case (1):
				$r0 = ($n_cell*1.0588)/1000;
				$ocv = $n_cell*3.36;
				break;	
			}
			
			if ($soc>$soc_th) {
				//%% seleziono il file toyota auris data high
				//print "soc_th 1 if: ". $soc_th; print "<BR>";
				// ICE ON/OFF 
				/*
					$index_v = find($v_avg>=$ice_on_th_high(:,3) & $v_avg<$ice_on_th_high(:,4));
					$index_soc = find($soc>=$ice_on_th_high(:,1) & $soc<$ice_on_th_high(:,2));
					$index = intersect($index_v,$index_soc);
					$p_on = $ice_on_th_high($index,6);
				*/
				// farlo in MYSQL DA TABELLA tOYOTA aURIS HIGH - ICE_ON_TRESHOLD
				// questo sara' un valore unico, non un array.. controllare l'output
				$sql_query_index_v_soc="SELECT Avg_traction_power FROM s500_high_ice_on_threshold_cd WHERE ". $acc_vel.">=Acc_Min AND ". $acc_vel."<Acc_Max AND ".$soc.">=SOC_Min AND ".$soc."<SOC_Max" ;
				$query = mysql_query($sql_query_index_v_soc);
				$s = array();
				while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
					$p_on1 = $line;
				}
				$p_on = $p_on1['Avg_traction_power'];
				
				//%% EV/Regenerative Braking Efficiencies
				
				$eff_ev = 0.84;
				$eff_reg = 0.88;
				
				//%% Boost/Smart Charge
				
				/*
					print "soc: ".$soc; 
					print "<BR>";
					print "v_avg: ".$v_avg; 
					print "<BR>";
					print "p_pos: ".$p_pos; 
					print "<BR>";
				*/
				// mi da come output 2 valori ditinti, controllare gli output
				$sql_query_index_b="SELECT Boost_Weight,Smart_Charge_Weight FROM s500_high_hybrid_cd WHERE ".$v_avg.">=Speed_Min AND ".$v_avg." < Speed_Max AND ".$soc.">=SOC_Min AND ".$soc." < SOC_Max AND ".$p_pos.">=Min_Power AND ".$p_pos."<Max_Power " ;				
				
				$query = mysql_query($sql_query_index_b);
				$s = array();
				while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
					$output_query1 = $line;
				}
				
				$boost_w = $output_query1['Boost_Weight'];
				$smart_cgh_w = $output_query1['Smart_Charge_Weight'];
				
				$soc_th = $soc_cs;
				//print $segment_numbers; print " --- --- <br>";
				/*
					print "p_on:".$p_on; print " --- --- <br>";
					print "p_posn:".$p_pos; print " --- <br>";
					print $acc_vel;print " - <br>";
				*/
				if ($p_pos<=$p_on) {
					
					$ice[$jj] = 0;
					$fc_ice[$jj] = 0;
					$co2_ice[$jj] = 0;
					$dist_ev[$jj] = $dist[$jj];//// distana in elettrico
					
					$pbatt_tr = $p_pos/$eff_ev; // [kW]
					
					$v_batt_tr = ($ocv+sqrt(pow($ocv,2)-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
					
					$i_batt_tr = $pbatt_tr*1000/$v_batt_tr; // Battery Current [A]
					
					$delta_soc_ev = $i_batt_tr*$time_acc/(3600*$C)*100;
					
					$delta_soc_p = 0;
					/*
						print "iterazione p_pos<=p_on: ".$jj." ";
						print " V batt tr: ".$v_batt_tr." ";
						print " P batt tr: ".$pbatt_tr." ";
						
						print " delta_soc_ev: ".$delta_soc_ev." ";
						print " i_batt_tr: ".$i_batt_tr." ";
						print " time_acc: ".$time_acc." ";
						print " p_pos: ".$p_pos." ";
					*/
					$delta_soc_top=50;
					
					
					if ($delta_soc_ev>$delta_soc_top) {
						$ice[$jj] = 1;
						$dist_ev[$jj] = 0;                
						if ($boost_w == $smart_cgh_w) {                    
							$boost_w = 100;
							$smart_cgh = 200;
						}
						
						if ($boost_w>$smart_cgh_w) {
							//%% boost_Map_Simp in excel data_high
							/*
								$pbatt_tr = interp1($boost_high(:,1),$boost_high(:,2),$soc); //%% SQL cerco nella colonna 1 il valore soc ed estraggo il valore della colonna 2
								$v_batt_tr = ($ocv+sqrt($ocv^2-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
							*/
							$soc_rounded1=round($soc,1); // controllare se il rounding e' corretto
							
							if ($soc_rounded1<39.56) {$soc_rounded1=39.56;}
							if ($soc_rounded1>48.46) {$soc_rounded1=48.46;}
							$soc_rounded1_plus=$soc_rounded1+0.01;
							$soc_rounded1_minus=$soc_rounded1-0.01;
							$sql_query_index_boost_m="SELECT Battery_Power FROM s500_high_boost_map_simp_cd WHERE SOC>=".$soc_rounded1_minus." AND SOC<=".$soc_rounded1_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
							//print $sql_query_index_boost_m; die();
							$query = mysql_query($sql_query_index_boost_m);
							$s = array();
							while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
								$pbatt_tr1 = $line;
							}
							$pbatt_tr=$pbatt_tr1['Battery_Power'];
							
							
							$v_batt_tr = ($ocv+sqrt(pow($ocv,2)-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
							
						}
						
						if ($boost_w<$smart_cgh_w) {
							// usare Smart charge map simp da excel
							/*
								$pbatt_tr = interp1($smart_cgh_high(:,1),$smart_cgh_high(:,2),soc);
								$v_batt_tr = ($ocv+sqrt($ocv^2+4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
							*/
							$soc_rounded2=round($soc,1); // controllare se il rounding e' corretto
							if ($soc_rounded2<38.91) {$soc_rounded2=38.91;}
							if ($soc_rounded2>50.36) {$soc_rounded2=50.36;}
							$soc_rounded2_minus=$soc_rounded2-0.02;
							$soc_rounded2_plus=$soc_rounded2+0.02;
							$sql_query_index_smart_m="SELECT Battery_Power FROM s500_high_smart_charge_map_simp_cd WHERE SOC>=".$soc_rounded2_minus." AND SOC<=".$soc_rounded2_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
							$query = mysql_query($sql_query_index_smart_m);
							$s = array();
							while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
								$pbatt_tr1 = $line;
							}
							$pbatt_tr=$pbatt_tr1['Battery_Power'];
							//print_r ($pbatt_tr); 
							$v_batt_tr = ($ocv+sqrt(pow($ocv,2)+4*$r0*$pbatt_tr*1000))/2;
							
							
						}
						
						$co2_batt = ($willans_a[$jj]+$willans_b[$jj]*(1/$pbatt_tr))*$pbatt_tr/3600*$time_acc/$dist[$jj]; // CO2 battery [g/km] 
						
						
						$co2_ice_temp[$jj] = $co2-$co2_batt; // [g/km]
						$co2_ice[$jj] = ($co2-$co2_batt)*$dist[$jj]; // [g per tutto il segmento]	
						
						
						
						
						//$fc_ice[$jj] = $fc-$co2_batt[$jj]*$dist[$jj]*0.0315/$ro_fuel; 
						$fc_ice[$jj] = $co2_ice[$jj]*0.315/($ro_fuel*1000); // lt 
						/*
							print "co2 ice: ";
							print_r ($co2_ice); 
							print "<br>";
							print "fc ice: ";
							print_r ($fc_ice); 
							die();
						*/
						$i_batt_tr = $pbatt_tr*1000/$v_batt_tr; // Battery Current [A]
						
						
						
						$delta_soc_p = $i_batt_tr*$time_acc/(3600*$C)*100;
						$delta_soc_ev = 0; 	
						
					}
					
					
					
					
				}
				elseif ($p_pos>$p_on) {                
					$ice[$jj] = 1;
					$dist_ev[$jj] = 0;                
					if ($boost_w == $smart_cgh_w) {                    
						$boost_w = 100;
						$smart_cgh = 200;
					}
					
					if ($boost_w>$smart_cgh_w) {
						//%% boost_Map_Simp in excel data_high
						/*
							$pbatt_tr = interp1($boost_high(:,1),$boost_high(:,2),$soc); //%% SQL cerco nella colonna 1 il valore soc ed estraggo il valore della colonna 2
							$v_batt_tr = ($ocv+sqrt($ocv^2-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
						*/
						$soc_rounded1=round($soc,1); // controllare se il rounding e' corretto
						
						if ($soc_rounded1<39.56) {$soc_rounded1=39.56;}
						if ($soc_rounded1>48.46) {$soc_rounded1=48.46;}
						$soc_rounded1_plus=$soc_rounded1+0.01;
						$soc_rounded1_minus=$soc_rounded1-0.01;
						$sql_query_index_boost_m="SELECT Battery_Power FROM s500_high_boost_map_simp_cd WHERE SOC>=".$soc_rounded1_minus." AND SOC<=".$soc_rounded1_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
						//print $sql_query_index_boost_m; die();
						$query = mysql_query($sql_query_index_boost_m);
						$s = array();
						while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
							$pbatt_tr1 = $line;
						}
						$pbatt_tr=$pbatt_tr1['Battery_Power'];
						
						
						$v_batt_tr = ($ocv+sqrt(pow($ocv,2)-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
						
					}
					
					if ($boost_w<$smart_cgh_w) {
						// usare Smart charge map simp da excel
						/*
							$pbatt_tr = interp1($smart_cgh_high(:,1),$smart_cgh_high(:,2),soc);
							$v_batt_tr = ($ocv+sqrt($ocv^2+4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
						*/
						$soc_rounded2=round($soc,1); // controllare se il rounding e' corretto
						if ($soc_rounded2<38.91) {$soc_rounded2=38.91;}
						if ($soc_rounded2>50.36) {$soc_rounded2=50.36;}
						$soc_rounded2_minus=$soc_rounded2-0.02;
						$soc_rounded2_plus=$soc_rounded2+0.02;
						$sql_query_index_smart_m="SELECT Battery_Power FROM s500_high_smart_charge_map_simp_cd WHERE SOC>=".$soc_rounded2_minus." AND SOC<=".$soc_rounded2_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
						$query = mysql_query($sql_query_index_smart_m);
						$s = array();
						while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
							$pbatt_tr1 = $line;
						}
						$pbatt_tr=$pbatt_tr1['Battery_Power'];
						//print_r ($pbatt_tr); 
						$v_batt_tr = ($ocv+sqrt(pow($ocv,2)+4*$r0*$pbatt_tr*1000))/2;
						
						
					}
					
					$co2_batt = ($willans_a[$jj]+$willans_b[$jj]*(1/$pbatt_tr))*$pbatt_tr/3600*$time_acc/$dist[$jj]; // CO2 battery [g/km] 
					
					
					$co2_ice_temp[$jj] = $co2-$co2_batt; // [g/km]
					$co2_ice[$jj] = ($co2-$co2_batt)*$dist[$jj]; // [g per tutto il segmento]	
					
					
					
					
					//$fc_ice[$jj] = $fc-$co2_batt[$jj]*$dist[$jj]*0.0315/$ro_fuel; 
					$fc_ice[$jj] = $co2_ice[$jj]*0.315/($ro_fuel*1000); // lt 
					/*
						print "co2 ice: ";
						print_r ($co2_ice); 
						print "<br>";
						print "fc ice: ";
						print_r ($fc_ice); 
						die();
					*/
					$i_batt_tr = $pbatt_tr*1000/$v_batt_tr; // Battery Current [A]
					
					
					
					$delta_soc_p = $i_batt_tr*$time_acc/(3600*$C)*100;
					$delta_soc_ev = 0; 	
					
					
				}    
			}
			
			//$pice = $pice-$pbatt_tr; // [kW]
			
			if ($soc<=$soc_th){
				
				$soc_th = $soc_cd;
				
				//print "soc_th 2A if: ". $soc_th; print "<BR>";
				//%% seleziono il file toyota auris data high
				
				// ICE ON/OFF 
				/*
					$index_v = find($v_avg>=$ice_on_th_high(:,3) & $v_avg<$ice_on_th_high(:,4));
					$index_soc = find($soc>=$ice_on_th_high(:,1) & $soc<$ice_on_th_high(:,2));
					$index = intersect($index_v,$index_soc);
					$p_on = $ice_on_th_high($index,6);
				*/
				// farlo in MYSQL DA TABELLA tOYOTA aURIS HIGH - ICE_ON_TRESHOLD
				// questo sara' un valore unico, non un array.. controllare l'output
				
				$sql_query_index_v_soc="SELECT Avg_traction_power FROM s500_high_ice_on_threshold_cs WHERE ". $acc_vel.">=Acc_Min AND ". $acc_vel."<Acc_Max AND ".$soc." >= SOC_Min AND ".$soc." < SOC_Max" ;
				
				$query = mysql_query($sql_query_index_v_soc);
				$s = array();
				while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
					$p_on1 = $line;
				}
				
				$p_on = $p_on1['Avg_traction_power'];
				
				//print "p_on da database: ". $p_on; print "<BR>";
				//%% EV/Regenerative Braking Efficiencies
				
				$eff_ev = 0.84;
				$eff_reg = 0.88;
				
				//%% Boost/Smart Charge
				
				/*
					print "soc: ".$soc; 
					print "<BR>";
					print "v_avg: ".$v_avg; 
					print "<BR>";
					print "p_pos: ".$p_pos; 
					print "<BR>";
				*/
				// mi da come output 2 valori ditinti, controllare gli output
				$sql_query_index_b="SELECT Boost_Weight,Smart_Charge_Weight FROM s500_high_hybrid_cs WHERE ".$v_avg.">=Speed_Min AND ".$v_avg." < Speed_Max AND ".$soc.">=SOC_Min AND ".$soc." < SOC_Max AND ".$p_pos.">=Min_Power AND ".$p_pos."<Max_Power " ;				
				
				$query = mysql_query($sql_query_index_b);
				$s = array();
				while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
					$output_query1 = $line;
				}
				
				$boost_w = $output_query1['Boost_Weight'];
				$smart_cgh_w = $output_query1['Smart_Charge_Weight'];
				
				//$soc_th = $soc_cs;
				
				/*
					if ($p_pos<=$p_on) {
					
					$ice[$jj] = 0;
					$fc_ice[$jj] = 0;
					$co2_ice[$jj] = 0;
					$dist_ev[$jj] = $dist[$jj];//// distana in elettrico
					
					$pbatt_tr = $p_pos/$eff_ev; // [kW]
					
					$v_batt_tr = ($ocv+sqrt(pow($ocv,2)-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
					
					$i_batt_tr = $pbatt_tr*1000/$v_batt_tr; // Battery Current [A]
					
					$delta_soc_ev = $i_batt_tr*$time_acc/(3600*$C)*100;
					
					print "iterazione 2";
					$delta_soc_p = 0;
					
				}*/
				// elseif ($p_pos>$p_on) {                
				$ice[$jj] = 1;
				$dist_ev[$jj] = 0;                
				if ($boost_w == $smart_cgh_w) {                    
					$boost_w = 100;
					$smart_cgh = 200;
				}
				
				if ($boost_w>$smart_cgh_w) {
					//%% boost_Map_Simp in excel data_high
					/*
						$pbatt_tr = interp1($boost_high(:,1),$boost_high(:,2),$soc); //%% SQL cerco nella colonna 1 il valore soc ed estraggo il valore della colonna 2
						$v_batt_tr = ($ocv+sqrt($ocv^2-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
					*/
					$soc_rounded1=round($soc,1); // controllare se il rounding e' corretto
					
					if ($soc_rounded1<39.56) {$soc_rounded1=39.56;}
					if ($soc_rounded1>48.46) {$soc_rounded1=48.46;}
					$soc_rounded1_plus=$soc_rounded1+0.01;
					$soc_rounded1_minus=$soc_rounded1-0.01;
					$sql_query_index_boost_m="SELECT Battery_Power FROM s500_high_boost_map_simp_cs WHERE SOC>=".$soc_rounded1_minus." AND SOC<=".$soc_rounded1_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
					
					$query = mysql_query($sql_query_index_boost_m);
					$s = array();
					while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
						$pbatt_tr1 = $line;
					}
					$pbatt_tr=$pbatt_tr1['Battery_Power'];
					
					
					$v_batt_tr = ($ocv+sqrt(pow($ocv,2)-4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
					
				}
				
				if ($boost_w<$smart_cgh_w) {
					// usare Smart charge map simp da excel
					/*
						$pbatt_tr = interp1($smart_cgh_high(:,1),$smart_cgh_high(:,2),soc);
						$v_batt_tr = ($ocv+sqrt($ocv^2+4*$r0*$pbatt_tr*1000))/2; // Battery voltage [V]
					*/
					$soc_rounded2=round($soc,1); // controllare se il rounding e' corretto
					if ($soc_rounded2<38.91) {$soc_rounded2=38.91;}
					if ($soc_rounded2>50.36) {$soc_rounded2=50.36;}
					$soc_rounded2_minus=$soc_rounded2-0.02;
					$soc_rounded2_plus=$soc_rounded2+0.02;
					$sql_query_index_smart_m="SELECT Battery_Power FROM s500_high_smart_charge_map_simp_cs WHERE SOC>=".$soc_rounded2_minus." AND SOC<=".$soc_rounded2_plus; // qui controllare che mi sembra strano che cerchi il valore esatto..
					$query = mysql_query($sql_query_index_smart_m);
					$s = array();
					while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
						$pbatt_tr2 = $line;
					}
					$pbatt_tr=$pbatt_tr2['Battery_Power'];
					
					
					/*
						print " loop: ".$jj."<BR>";
						print " ocv:".$ocv."<BR>";
						print " r0:".$r0."<BR>"." pbatt_tr:";
						print_r ($pbatt_tr);
					*/
					
					
					$v_batt_tr = ($ocv+sqrt(pow($ocv,2)+4*$r0*$pbatt_tr*1000))/2;
					
					
				}
				
				$co2_batt = ($willans_a[$jj]+$willans_b[$jj]*(1/$pbatt_tr))*$pbatt_tr/3600*$time_acc/$dist[$jj]; // CO2 battery [g/km] 
				
				
				$co2_ice_temp[$jj] = $co2-$co2_batt; // [g/km]
				$co2_ice[$jj] = ($co2-$co2_batt)*$dist[$jj]; // [g per tutto il segmento]	
				
				
				
				
				//$fc_ice[$jj] = $fc-$co2_batt[$jj]*$dist[$jj]*0.0315/$ro_fuel; 
				$fc_ice[$jj] = $co2_ice[$jj]*0.315/($ro_fuel*1000); // lt 
				/*
					print "co2 ice: ";
					print_r ($co2_ice); 
					print "<br>";
					print "fc ice: ";
					print_r ($fc_ice); 
					die();
				*/
				$i_batt_tr = $pbatt_tr*1000/$v_batt_tr; // Battery Current [A]
				
				$delta_soc_p = $i_batt_tr*$time_acc/(3600*$C)*100;
				$delta_soc_ev = 0; 	
				
				
				//}    
				
				//print "soc_th 2B if: ". $soc_th; print "<BR>";
				
			}
			//%% Regenerative braking event
			
			$pbatt_reg = ($brake_split/100)*$p_neg*$eff_reg;     
			
			$v_batt_reg = ($ocv+sqrt(pow($ocv,2)+4*$r0*$pbatt_reg*1000))/2; // Battery voltage [V]
			
			$i_batt_reg = $pbatt_reg*1000/$v_batt_reg; // Battery Current [A]
			
			$delta_soc_reg = $i_batt_reg*$time_dec/(3600*$C)*100;
			
			//%% Air Conditioning
			
			$v_batt_ac = ($ocv+sqrt(pow($ocv,2)-4*$r0*$ac*1000))/2; // Battery voltage [V]
			
			$i_batt_ac = $ac*1000/$v_batt_ac; // Battery Current [A]
			
			$delta_soc_ac = $i_batt_ac*($segment_time[0][$jj])/(3600*$C)*100;
			
			//%% SOC Computation
			
			// $delta_soc[$jj] = max([min([$delta_soc_max;$delta_soc_reg+$delta_soc_p+$delta_soc_ev+$delta_soc_ac]);$delta_soc_min]);
			
			
			$delta_soc= $delta_soc_reg+$delta_soc_p+$delta_soc_ev+$delta_soc_ac;
			
			
			if ( $delta_soc > $delta_soc_max) {$delta_soc = $delta_soc_max;}
			if ( $delta_soc < $delta_soc_min) {$delta_soc = $delta_soc_min;}
			/*
				print " segment time: "; print_r ($segment_time);
				print " time acc: ".$time_acc. "<BR>";
				print " delta_soc_reg: ".$delta_soc_reg. "<BR>";
				print " delta_soc_p: ".$delta_soc_p. "<BR>";
				print " delta_soc_ev: ".$delta_soc_ev. "<BR>";
				print " delta_soc_ac: ".$delta_soc_ac. "<BR>";
				print " delta soc:".$delta_soc."<BR>";
			*/
			//  $soc = max([$soc_low;min([$soc-delta_soc($ii,$jj);$soc_high])]);
			$soc= $soc-$delta_soc;
			if ( $soc > $soc_cd) {$soc = $soc_cd;}
			if ( $soc < $soc_cs) {$soc = $soc_cs;}
			
			$soc_phase[$jj] = $soc;
			
			
			//print " soc:".$soc."<BR>";
			
		}
		
		
		/* ***************************************************************/
		/* ********************END MAIN LOOP *****************************/
		/* ***************************************************************/		
		$co2_ice_sum=0;
		$fc_ice_sum=0;
		$dist_ev_sum=0;
		for ($tg=0; $tg<$segment_numbers;$tg++) {
			$co2_ice_sum=$co2_ice_sum+$co2_ice[$tg];
			$fc_ice_sum=$fc_ice_sum+$fc_ice[$tg];
			$dist_ev_sum=$dist_ev_sum+$dist_ev[$tg];
			/*
				print_r($co2_ice[$tg]); 
				Print "<BR>";
				print_r($fc_ice[$tg]);
				Print "<BR>";
			*/
		}
		
		$energy_nominal= (3.7*$C*$n_cell)/1000; // Nominal energy in kWh
		$energy_consumption_in_kWh=$energy_nominal*($soc_cd/100)*($soc/100);
		
		
		
		
		
		
		
		
		
		
		
		//print "segment_numbers: ".$segment_numbers;
		
		
		
		$co2_km=$co2_ice_sum/$total_route_distance;
		$fc_100km=$fc_ice_sum/$total_route_distance*100;
		
		// sets the consumptiona and emission based upon the Euro standard
		$co2_km= $co2_km*$euro_standard_UI_modifier;
		$fc_100km=$fc_100km*$euro_standard_UI_modifier;
		
		//%% fine loop - risultato CO2 per segmento co2_ice(ii,jj)  , sommo tutti i valori e divido per la distanza totale della route per trovare la CO2 totale per km
		// --------------- da scrivere il risultato: co2_ice(jj)
		//end
		$CO2_per_km= round($co2_km,0);
		$liters_fuel_per_100km=round ($fc_100km,2);
		//print "total_route_distance:".$total_route_distance;
		$liters_used_in_route=($liters_fuel_per_100km/100 )*$total_route_distance;	
		$total_route_fuel_costICE= round(($liters_used_in_route*$fuel_price_UI),2);
		$total_route_fuel_costPHEV= round(($energy_consumption_in_kWh*$energy_price_UI),2);
		
		
		/**/
		$liters_fuel_per_100km=$liters_fuel_per_100km." L/100km";  // consumo specifico batteria
		//$liters_fuel_per_100km=($Sum_E_batt_phase*1000/$Sum_dist_ev)." Wh/km";
		$total_route_fuel_cost=$total_route_fuel_costICE. " Euro for fuel / ". $total_route_fuel_costPHEV." Euro for energy";// costo del viaggio per energia = 0.20 Euro/kWh
		//$total_route_fuel_cost=($Sum_E_batt_phase*$energy_price_UI);// costo del viaggio per energia = 0.20 Euro/kWh
		if ($energy_consumption_in_kWh==0) {
			$total_route_fuel_cost=$total_route_fuel_costICE. " Euro for fuel ";
		}
		
		
		$CO2_per_km=$CO2_per_km. " g/km";
		
		
		
		
		
		
		
		
		
		/*print_r($dist_ev);
			print " dist_ev_sum: ".$dist_ev_sum. "<BR>";
			print " liters_used_in_route: ".$liters_used_in_route. "<BR>";
			print " liters_fuel_per_100km: ".$liters_fuel_per_100km. "<BR>";
		print " total_route_distance: ".$total_route_distance. "<BR>";*/
		
		/* ############################ END ELECTRIC VEHICLES #########################################*/	
	} // End PHEV calculations
	
	
	// output results:
	// contatempi
	//$t= microtime(true);
	//print " tempoRisposta0;".$t ."<br>";	
	
	$fuelData =	array('status' => '200', 'message' => 'SUCCESS', 'data' => array('co2' => $CO2_per_km, 'fuel_consumption' => $liters_fuel_per_100km, 'total_cost' => $total_route_fuel_cost));
	
	//print $fuelData; die();
	
	/* SEGMENT A - DIESEL - NOT ELECTRIC */	
	/*
		if ($TypeOfVehicle='AD' AND $fuel_type_UI<>"Diesel") { // 
		// set all values to load the corresponding tables
		$TableToRead=$TypeOfVehicle."_co2_emission";
		$TableToRead=strtolower($TableToRead);
		// load all related db
		// example ad_co2_emission_ysc
		$combo_multi=LoadDataForKriging($TableToRead);
		// call function only for fuel consumption	
		$s=$combo_multi[0];
		$beta=$combo_multi[1];
		$gamma=$combo_multi[2];
		$ssc=$combo_multi[3];
		$theta=$combo_multi[4];
		$ysc=$combo_multi[5];
	*/	
	
	/*
		print_r($s);print "<br><br>";
		print_r($beta);print "<br><br>";
		print_r($gamma);print "<br><br>";
		print_r($ssc);print "<br><br>";
		print_r($theta);print "<br><br>";
		print_r($ysc);print "<br><br>";
	*/
	/* TEST CODE */
	/*
		// query that loads all test values to use as input for the kriging function 
		$query = mysql_query("SELECT * FROM test_values");
		$test_values = array();
		while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
		$test_values[] = $line;
		}
		
		$query = mysql_query("SELECT * FROM test_values_results");
		$test_results = array();
		while($line = mysql_fetch_array($query, MYSQL_ASSOC)){
		$test_results[] = $line;
		}
	*/
	/* END TEST CODE */
	/*
		for ($z = 0; $z < 256; $z++) {
		$outcomez = KrigingCo2mpas($test_values[$z]);
		//print (krigingCo2mpas($test_values[$z]));
		print $outcomez . " = ";
		print_r($test_results[$z][1]);
		print " -- SOURCE call: ";
		print_r($test_values[$z]);
		print "<br>";
		}
		
		$CO2_emission_final=KrigingCo2mpas($x);
		print " CO2 emessa: ".$CO2_emission_final;
	*/	
	
	
	
	
	
	
	
	/*
		print " ++ ".$euro_standar_UI." ++ ".
		$fuel_type_UI." ++ ".
		$engine_capacity_UI." ++ ".
		$engine_power_UI." ++ ".
		$fuel_price_UI." ++ ".
		
		
		$energy_price_UI." ++ ". // this one maybe we should keep it fixed, just change the fuel price
		$gearbox_UI." ++ ". // controllare segna quello attivo...
		$car_weight_UI ." ++ ".
		$car_traction_UI." ++ ".
		$intake_air_system_UI." ++ ". // calcolato internamente, da cancellare come user interface
		$tyres_class_UI." ++ ".
		$start_stop_UI." ++ ".
		$brake_recuperation_UI." ++ ".// da controllare, il parametro arrivera' internamente
		
		
		$passengers_number_UI." ++ ".
		$driving_style_UI." ++ ".
		$internal_luggage_UI." ++ ".
		$roofbox_UI." ++ ".
		$air_conditioning_UI." ++ ".
		$inflated_tyres_UI." ++ ";
	*/
	
	
	/* reply the co2 value etc.*/
	/*
		$fuelData = array('status' => '200', 'message' => 'SUCCESS', 'data' => array('co2' => rand(10,200), 'fuel_consumption' => rand(10,50), 'total_cost' => rand(1000,10000)));
	*/
	// write the data to the db
	/*
		$segment_time[]= $request->reqObj->mapData->allDuration ;// array of values, for every segment is time in seconds
		$segment_distance[]= $request->reqObj->mapData->allSteps ; // array of values, for each segment is lenght in metres
		$segment_elevation[]= $request->reqObj->mapData->allElevation ; // array of values, for each segment a value from -10 to +10, devo calcolarla in base alle elevazioni
		$route_start_point_name=$request->reqObj->mapData->start_address ;  // lat and long of the starting location of the main route
		$route_start_point_lat_long[]=$request->reqObj->mapData->start_location ; // lat and long of the starting location of the main route
		$route_end_point_name=$request->reqObj->mapData->end_address ;  // lat and long of the starting location of the main route
		$route_end_point_lat_long[]=$request->reqObj->mapData->end_location ; // lat and long of the starting location of the main route
	*/
	
	
	// clean up $request1 for database
	//print $request1; print " <BR> <BR>";
	//$columns = preg_replace('/[^a-z0-9_]+/i','',array_keys($request));
	$sent_from_UI = strtolower($request1);
	$NotAllowedChars = array("*", "drop", "concat", "select", ";--", ";#", "admin", " if ", " ascii ", " or ");
	$sent_from_UI=str_replace($NotAllowedChars, "", $sent_from_UI);
	//print " str replace: "; print $columns; print " <BR> <BR>";
	$sent_from_UI = mysql_real_escape_string($sent_from_UI);
	//print " real escape: ";print $columns; print " <BR> <BR>";
	// da eliminare nel testo " DROP "   " CONCAT "   " SELECT " ";--" ";#"  "admin" "*"
	
	$sql_query_insert="INSERT INTO user_data (total_route_fuel_cost, liters_fuel_per_100km, CO2_per_km,input_data,car_segment,type_vehicle)
	VALUES ('".$total_route_fuel_cost."', '".$liters_fuel_per_100km."', '".$CO2_per_km."','".$sent_from_UI."','".$car_segment_UI."','".$TypeOfVehicle."')" ;
	//print $sql_query_insert;
	$query = mysql_query($sql_query_insert);
	
	// contatempi
	//$t= microtime(true);
	//print " tempoRisposta1;".$t ."<br>";	
	
	echo json_encode($fuelData);
	
	
?>			
