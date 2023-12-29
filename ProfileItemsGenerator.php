<?php

$months=array();

$months[1]='JAN';
$months[2]='FEB';
$months[3]='MAR';
$months[4]='APR';
$months[5]='MAY';
$months[6]='JUN';
$months[7]='JUL';
$months[8]='AUG';
$months[9]='SEP';
$months[10]='OCT';
$months[11]='NOV';
$months[12]='DEC';


$flipped_months = array_flip($months);

$output="";
$output2="";


$holiday_list = array();
// Holiday list will include all bank holidays and weekend dates. All dates where a DD cannot be pulled.

if(isset($_POST['start_date'])){

    // Get the bank holidays from the government website and put them into an array.

    $start_date=$_POST['start_date'];

    $end_date=$_POST['end_date'];

    $financial_admin_unit = $_POST['AdminUnit'];
    $debit_periods = $_POST['debit_periods'];

    $json = file_get_contents('https://www.gov.uk/bank-holidays.json');
    $obj = json_decode($json,true);

    $array_count = 0;
    while(isset($obj[$_POST['region']]['events'][$array_count])){
        foreach($obj[$_POST['region']]['events'][$array_count] as $key => $value) {
            $holiday_list[$array_count] = $obj[$_POST['region']]['events'][$array_count]['date'];
            
        }
        $array_count++;
    }
}

// Work out the weekend dates between the start and end date and add them to the array
$we_start_date = strtotime($start_date);
$we_end_date = strtotime($end_date);

while (date("Y-m-d", $we_start_date) != date("Y-m-d", $we_end_date)) {
    $day_index = date("w", $we_start_date);
    if ($day_index == 0 || $day_index == 6) {
        
        $holiday_list[$array_count] = date("Y-m-d",$we_start_date);

        $array_count++;
    }
    $we_start_date = strtotime(date("Y-m-d", $we_start_date) . "+1 day");
}

$start_date_bits = explode('-',$start_date);

//check if first profile date will be before the start date. Add them to Holiday List for exclusions

if(intval($start_date_bits[2])>1){
    $gap_filler = 1;
    while($gap_filler < intval($start_date_bits[2])){
        
        $gap_day = $start_date_bits[0].'-'.$start_date_bits[1].'-'.str_pad($gap_filler,2,'0',STR_PAD_LEFT);;

        $holiday_list[$array_count]=$gap_day;
        $array_count++;
        $gap_filler++;

    }
}
// Sort the array into order(Not really required).
usort($holiday_list, "date_sort");

// Flip key vals of array of ease of use when date checking.
$holiday_list = array_flip($holiday_list);

// Create the variables needed

$this_profile_year = $start_date_bits[0];

$st_date = $start_date;
$ed_date = $end_date;

$start_month = intval($start_date_bits[1]);

$start_year = $start_date_bits[0];

$DaysInYear = dateDifference($st_date,$ed_date) +1;
$WeeksInYear = intval($DaysInYear / 7);
$MonthsInYear = intval($DaysInYear / 30);  // Very Rough Calc should be sufficient.

$debit_periods = DebitPeriodsCalc ($debit_periods,$MonthsInYear);

$dayofweek = date('w', strtotime($st_date)); // (Mon =1, Sun =7)


// MAIN LOOP for Any Day Direct Debits.
if (isset($_POST['anyday'])){

$profile_day = 1;

while($profile_day <= 28){  // Go through the 28 Days

    $month_count = 1;
    $profile_month = $start_month;
    $profile_year = $start_year;

   while($month_count <= $MonthsInYear){

    $thisday = str_pad($profile_day,2,'0',STR_PAD_LEFT);
    $thismonth = str_pad($profile_month,2,'0',STR_PAD_LEFT);

    $standard_date = trim($profile_year.'-'.$thismonth.'-'.$thisday);

    $date_checked = HolidayCheck($standard_date,$holiday_list);

    $BalanceDate = GetBalanceDate($standard_date);

    $Northgate_date = NorthgateDate($date_checked,$months);

    $output.='"'.$financial_admin_unit.'","'.
                $this_profile_year.'","DD'.$profile_day.'","'.
                $Northgate_date.'","N","'.
                $debit_periods[$month_count].'","'.$BalanceDate.'"'."\n";


    $profile_month++;

    if($profile_month == 13){
        $profile_month = 1;
        $profile_year ++;
    }

  
    $month_count++;
    
   }
   $profile_day++;
}

// Create Profiles File.
$profile_day = 1;

while($profile_day <= 28){  // Go through the 28 Days
    $output2.='"'.$financial_admin_unit.'","'.
    $this_profile_year.'","DD'.$profile_day.'","'.
    $_POST['debit_periods'].'"'."\n";

    $profile_day++;
}
}

//DDM Profiles
if(isset($_POST['ddm'])){
$output2.='"'.$financial_admin_unit.'","'.
    $this_profile_year.'","DDM","'.
    $_POST['debit_periods'].'"'."\n";

    $output2.='"'.$financial_admin_unit.'","'.
    $this_profile_year.'","DDM2","'.
    $_POST['debit_periods'].'"'."\n";

    $output2.='"'.$financial_admin_unit.'","'.
    $this_profile_year.'","DDM3","'.
    $_POST['debit_periods'].'"'."\n";

    $output2.='"'.$financial_admin_unit.'","'.
    $this_profile_year.'","DDM4","'.
    $_POST['debit_periods'].'"'."\n";

// End of Any Day Direct Debits.



// DDMS

$month_count = 1;
$profile_month = $start_month;
$profile_year = $start_year;

while($month_count <= $MonthsInYear){
$DDM = HolidayCheck(date('Y-m-d',strtotime("First Monday of $profile_year-$profile_month")),$holiday_list);
$DDM2 = HolidayCheck(date('Y-m-d',strtotime("Second Monday of $profile_year-$profile_month")),$holiday_list);
$DDM3 = HolidayCheck(date('Y-m-d',strtotime("Third Monday of $profile_year-$profile_month")),$holiday_list);
$DDM4 = HolidayCheck(date('Y-m-d',strtotime("Fourth Monday of $profile_year-$profile_month")),$holiday_list);

$DDM_BalanceDate = date('Y-m-d',strtotime("First Monday of +27 day $profile_year-$profile_month"));
$DDM2_BalanceDate = date('Y-m-d',strtotime("Second Monday of +27 day $profile_year-$profile_month"));
$DDM3_BalanceDate = date('Y-m-d',strtotime("Third Monday of +27 day $profile_year-$profile_month"));
$DDM4_BalanceDate = date('Y-m-d',strtotime("Fourth Monday of +27 day $profile_year-$profile_month"));



$output.='"'.$financial_admin_unit.'","'.
                $this_profile_year.'","DDM","'.
                NorthgateDate($DDM,$months).'","N","'.
                $debit_periods[$month_count].'","'.NorthgateDate($DDM_BalanceDate,$months).'"'."\n";

$output.='"'.$financial_admin_unit.'","'.
                $this_profile_year.'","DDM2","'.
                NorthgateDate($DDM2,$months).'","N","'.
                $debit_periods[$month_count].'","'.NorthgateDate($DDM2_BalanceDate,$months).'"'."\n";

$output.='"'.$financial_admin_unit.'","'.
                $this_profile_year.'","DDM3","'.
                NorthgateDate($DDM3,$months).'","N","'.
                $debit_periods[$month_count].'","'.NorthgateDate($DDM3_BalanceDate,$months).'"'."\n";

$output.='"'.$financial_admin_unit.'","'.
                $this_profile_year.'","DDM4","'.
                NorthgateDate($DDM4,$months).'","N","'.
                $debit_periods[$month_count].'","'.NorthgateDate($DDM4_BalanceDate,$months).'"'."\n";




    $profile_month++;

    if($profile_month == 13){
        $profile_month = 1;
        $profile_year ++;
    }

  
    $month_count++;
}
}

if(isset($_POST['DDW'])){

    // Put the rent free weeks into an array.
    $free_weeks = array();
    $free_week_bits = explode(',',$_POST['FreeWeeks']);

    $monday_date = $start_date;

    foreach($free_week_bits as $val){
        $free_weeks[$val] = $val;
    }

    // Loop Through Weeks
    $this_week = 1;
    while($this_week <= $WeeksInYear){
        //Process if its not a rent free week.    
        if(!isset($free_weeks[$this_week])){

            $this_date = $monday_date;
            $effective_date = date('Y-m-d',strtotime("+6 day", strtotime($monday_date)));

            $payment_date = HolidayCheck($this_date,$holiday_list);
           
            $output.='"'.$financial_admin_unit.'","'.
                $this_profile_year.'","DDW","'.
                NorthgateDate($payment_date,$months).'","N","1","'.NorthgateDate($effective_date,$months).'"'."\n";

        }else{
            $this_date = $monday_date;
            $effective_date = date('Y-m-d',strtotime("+6 day", strtotime($monday_date)));

            $payment_date = HolidayCheck($this_date,$holiday_list);
           
            $output.='"'.$financial_admin_unit.'","'.
                $this_profile_year.'","DDW","'.
                NorthgateDate($payment_date,$months).'","N","0","'.NorthgateDate($effective_date,$months).'"'."\n";
        }
        $date = strtotime($monday_date);
            $monday_date = date('Y-m-d',strtotime("+7 day", $date));

        $this_week++;
    }

    $output2.='"'.$financial_admin_unit.'","'.
    $this_profile_year.'","DDW","'.
    $_POST['debit_periods'].'"'."\n";


}

if(isset($_POST['DDWF'])){

// Put the rent free weeks into an array.
$free_weeks = array();
$free_week_bits = explode(',',$_POST['FreeWeeks']);

$friday_date = $start_date;
$friday_date = date('Y-m-d',strtotime("+4 day", strtotime($start_date)));

foreach($free_week_bits as $val){
    $free_weeks[$val] = $val;
}

// Loop Through Weeks
$this_week = 1;
while($this_week <= $WeeksInYear){
    //Process if its not a rent free week.    
    if(!isset($free_weeks[$this_week])){

        $this_date = $friday_date;
        $effective_date = date('Y-m-d',strtotime("+6 day", strtotime($friday_date)));

        $payment_date = HolidayCheck($this_date,$holiday_list);
        


        $output.='"'.$financial_admin_unit.'","'.
            $this_profile_year.'","DDWF","'.
            NorthgateDate($payment_date,$months).'","N","1","'.NorthgateDate($effective_date,$months).'"'."\n";

    }else{
        $this_date = $friday_date;
        $effective_date = date('Y-m-d',strtotime("+6 day", strtotime($friday_date)));

        $payment_date = HolidayCheck($this_date,$holiday_list);
        


        $output.='"'.$financial_admin_unit.'","'.
            $this_profile_year.'","DDWF","'.
            NorthgateDate($payment_date,$months).'","N","0","'.NorthgateDate($effective_date,$months).'"'."\n";
    }
    $date = strtotime($friday_date);
        $friday_date = date('Y-m-d',strtotime("+7 day", $date));

    $this_week++;
}

$output2.='"'.$financial_admin_unit.'","'.
$this_profile_year.'","DDWF","'.
$_POST['debit_periods'].'"'."\n";

$output = rtrim($output);
$output2 = rtrim($output2);



}

file_put_contents('GeneratedProfiles/PI_'.$financial_admin_unit.'_'.$this_profile_year.'.dat',$output);

file_put_contents('GeneratedProfiles/P_'.$financial_admin_unit.'_'.$this_profile_year.'.dat',$output2);


echo('<a href="GeneratedProfiles/P_'.$financial_admin_unit.'_'.$this_profile_year.'.dat">Profiles</a><br />');

echo('<a href="GeneratedProfiles/PI_'.$financial_admin_unit.'_'.$this_profile_year.'.dat">Profile Items</a><br />');





function dateDifference($date_1 , $date_2 , $differenceFormat = '%a' )
{
    $datetime1 = date_create($date_1);
    $datetime2 = date_create($date_2);
   
    $interval = date_diff($datetime1, $datetime2);
   
    return $interval->format($differenceFormat);
   
}

function ddformat_date($date){
    $date_bits = explode('-',$date);

    return($date_bits[2].'-'.$date_bits[1].'-'.$date_bits[0]);
}

function DebitPeriodsCalc($debit_periods,$months){

    $calc1 = $debit_periods / $months;
    $calc2 = number_format($calc1,2,'.','');

    if($calc1 != $calc2){
        $month2 = substr($calc1,0,4);
        $month1 = $debit_periods - ( ($months-1) * $month2);
    }else{
        $month1 = $calc1;
        $month2 = $calc1;
    }

    $DebitPeriod = array();

    $DebitPeriod[1] = $month1;
    
    $this_period = 2;

    while($this_period <= $months){
        $DebitPeriod[$this_period] = $month2;
        $this_period++;
    }

    return $DebitPeriod;
}

function HolidayCheck($date,$holiday_list){


    if(!Isset($holiday_list[$date])){
        return $date;
    }else{
       
        while(Isset($holiday_list[$date])){
            $datetime = new DateTime($date);
            $datetime->modify('+1 Day');
            $date = strtoupper($datetime->format('Y-m-d'));
        }
        return $date;

    }
}



function date_sort($a, $b) {
    return strtotime($a) - strtotime($b);
}

function GetBalanceDate($date){
    
    $test_date = ddformat_date($date);
    $datetime = new DateTime($test_date);
    $datetime->modify('+28 Days');
    $date = strtoupper($datetime->format('d-M-Y'));

    return $date;
}

function NorthgateDate($date,$months){
    
    $date_bits = explode('-',$date);

    return $date_bits[2].'-'.$months[intval($date_bits[1])].'-'.$date_bits[0];

}