<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
error_reporting(0);

$lscpu = explode("\n", shell_exec("export LC_ALL=C | lscpu")); //split output in lines
for ($i=0; $i<sizeof($lscpu); $i++){
	$lscpu_c = preg_split("/:\s\s*/",$lscpu[$i]); //split in columns
	$lscpu[$lscpu_c[0]] = $lscpu_c[1]; //associative array
	unset($lscpu[$i]);
}

$free_l = explode("\n", shell_exec("export LC_ALL=C |  free")); //split output in lines
for ($i=0; $i<sizeof($free_l); $i++){
	$free_c = preg_split("/\s\s*/", $free_l[$i]); //split in columns
	if ($i==0){
		$free_t = $free_c; //get titles from first line
	}else{
		for ($j=1; $j<sizeof($free_c); $j++){
			$free[ $free_c[0] ][ $free_t[$j] ] = $free_c[$j];
		}
	}
}

$df_l = explode("\n", shell_exec("export LC_ALL=C | df")); //split in lines
for ($i=0; $i<sizeof($df_l); $i++){
	$df_c = preg_split("/\s\s*/", $df_l[$i]); //split in columns
	if ($i==0){
		$df_t = $df_c; //get titles from first line
	}else{
		for ($j=1; $j<sizeof($df_c); $j++){
			$df[ $df_c[0] ][ $df_t[$j] ] = $df_c[$j];
		}
	}
}

foreach ($df as $key => $value) {
	if($df[$key]['Mounted']=='/'){
		$dfname = $key; //get name of disk mounted in '/'
			break;
	}
}

$uptime[up] = substr(shell_exec("export LC_ALL=C | uptime -p"), 3);
$uptime[loadaverage] = explode(", ",trim ( substr(strrchr(shell_exec("export LC_ALL=C | uptime"), "load average:"), 14) ) ); 

function getTx(){
	foreach( glob("/sys/class/net/*") as $path ) {
	    if( $path != "/sys/class/net/lo" AND file_exists( "$path/statistics/rx_bytes" ) AND trim( file_get_contents("$path/statistics/rx_bytes") ) != '0' ){
	        $interface = array_pop( explode( '/', $path ) );
	    }
	}
	return(trim(file_get_contents("/sys/class/net/$interface/statistics/tx_bytes"))); 
}

function getRx(){
	foreach( glob("/sys/class/net/*") as $path ) {
	    if( $path != "/sys/class/net/lo" AND file_exists( "$path/statistics/rx_bytes" ) AND trim( file_get_contents("$path/statistics/rx_bytes") ) != '0' ){
	        $interface = array_pop( explode( '/', $path ) );
	    }
	}
	return(trim(file_get_contents("/sys/class/net/$interface/statistics/rx_bytes"))); 
}

$uname[name] = shell_exec("uname");
$uname[hostname] = shell_exec("uname -n");
$uname[release] = shell_exec("uname -r");
$uname[version] = shell_exec("uname -v");
$uname[machine] = shell_exec("uname -m");
$uname[type] = shell_exec("uname -o");

if (($_GET["format"]=="max")||(!$_GET["format"])){
	$max = array(
		"CPU"=>(array(
			"architecture"=>$lscpu["Architecture"],
			"cpus"=>$lscpu["CPU(s)"],
			"cpumhz"=>$lscpu["CPU MHz"]
		)),
		"Memory"=>(array(
			"total"=>round($free["Mem:"]["total"]/1024/1024,2),
			"used"=>round($free["Mem:"]["used"]/1024/1024,2),
			"free"=>round($free["Mem:"]["free"]/1024/1024,2),
			"use"=>round(($free["Mem:"]["used"])*100/($free["Mem:"]["total"]))
		)),
		"Disk"=>(array(
			"fileSystem"=>$dfname,
			"total"=>round($df[$dfname]["1K-blocks"]/1024/1024,2),
			"used"=>round($df[$dfname]["Used"]/1024/1024,2),
			"available"=>round($df[$dfname]["Available"]/1024/1024,2),
			"use"=>(int)substr($df[$dfname]["Use%"],0,-1)
		)),
		"Uptime"=>$uptime,
		"PC"=>$uname,
		"Version"=>"1.0"
	);
	echo json_encode($max);
}

else if ($_GET["format"]=="net"){
	$rx1 = getRx();
	$tx1 = getTx();
	if ($_GET["time"]){
		$time = $_GET["time"];
	}else{
		$time = 4;
	}
	sleep($time);
	$rx2 = getRx();
	$tx2 = getTx();
	$net = array(
		"down"=>round(((($rx2-$rx1)/$time)/1024),2),
		"up"=>round(((($tx2-$tx1)/$time)/1024),2)
	);
	echo json_encode($net);
}

else if ($_GET["format"]=="full"){
	$full = array(
		"lscpu"=>$lscpu, //CPU
		"free"=>$free, //memory
		"df"=>$df, //disk
		"uptime"=>$uptime, //uptime
		"net"=>array(
			"Rx"=>getRx(),
			"Tx"=>getTx()
		), //network
		"uname"=>$uname, //OS info
		"version"=>"1.0"
	);
	echo json_encode($full);
}

?>