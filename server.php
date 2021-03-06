<?php
header('Access-Control-Allow-Origin: *');
error_reporting(0);

//cpu info (lscpu)
$lscpu = explode("\n", shell_exec("export LC_ALL=C | lscpu")); //split output in lines
for ($i=0; $i<sizeof($lscpu); $i++){
    $lscpu_c = preg_split("/:\s\s*/",$lscpu[$i]); //split in columns
    $lscpu[$lscpu_c[0]] = $lscpu_c[1]; //associative array
    unset($lscpu[$i]);
}

//memory info (free)
$free_l = explode("\n", shell_exec("export LC_ALL=C |  free"));
for ($i=0; $i<sizeof($free_l); $i++){
    $free_c = preg_split("/\s\s*/", $free_l[$i]);
    if ($i==0){
        $free_t = $free_c; //get titles from first line
    }else{
        for ($j=1; $j<sizeof($free_c); $j++){
            $free[ $free_c[0] ][ $free_t[$j] ] = $free_c[$j];
        }
    }
}

//disk info (df)
$df_l = explode("\n", shell_exec("export LC_ALL=C | df"));
for ($i=0; $i<sizeof($df_l); $i++){
    $df_c = preg_split("/\s\s*/", $df_l[$i]);
    if ($i==0){
        $df_t = $df_c;
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

//server uptime
$uptime[up] = substr(shell_exec("export LC_ALL=C | uptime -p"), 3);
$uptime[loadaverage] = explode(", ",trim ( substr(strrchr(shell_exec("export LC_ALL=C | uptime"), "load average:"), 14) ) ); 

//number of bytes received
function getTx(){
    foreach( glob("/sys/class/net/*") as $path ) {
        if( $path != "/sys/class/net/lo" AND file_exists( "$path/statistics/rx_bytes" ) AND trim( file_get_contents("$path/statistics/rx_bytes") ) != '0' ){
            $interface = array_pop( explode( '/', $path ) );
        }
    }
    return(trim(file_get_contents("/sys/class/net/$interface/statistics/tx_bytes"))); 
}

//number of bytes transmitted
function getRx(){
    foreach( glob("/sys/class/net/*") as $path ) {
        if( $path != "/sys/class/net/lo" AND file_exists( "$path/statistics/rx_bytes" ) AND trim( file_get_contents("$path/statistics/rx_bytes") ) != '0' ){
            $interface = array_pop( explode( '/', $path ) );
        }
    }
    return(trim(file_get_contents("/sys/class/net/$interface/statistics/rx_bytes"))); 
}

//get server info
$uname[name] = shell_exec("uname");
$uname[hostname] = shell_exec("uname -n");
$uname[release] = shell_exec("uname -r");
$uname[version] = shell_exec("uname -v");
$uname[machine] = shell_exec("uname -m");
$uname[type] = shell_exec("uname -o");

if ($_GET["format"]=="json"){
    header('Content-Type: application/json');

    $json = array(
        "cpu"=>(array(
            "architecture"=>$lscpu["Architecture"],
            "cpus"=>$lscpu["CPU(s)"],
            "cpumhz"=>$lscpu["CPU MHz"]
        )),
        "memory"=>(array(
            "total"=>round($free["Mem:"]["total"]/1024/1024,2),
            "used"=>round($free["Mem:"]["used"]/1024/1024,2),
            "free"=>round($free["Mem:"]["free"]/1024/1024,2),
            "use"=>round(($free["Mem:"]["used"])*100/($free["Mem:"]["total"]))
        )),
        "disk"=>(array(
            "fileSystem"=>$dfname,
            "total"=>round($df[$dfname]["1K-blocks"]/1024/1024,2),
            "used"=>round($df[$dfname]["Used"]/1024/1024,2),
            "available"=>round($df[$dfname]["Available"]/1024/1024,2),
            "use"=>(int)substr($df[$dfname]["Use%"],0,-1)
        )),
        "net"=>array(
            "down"=>round((getRx()/1024),2),
            "up"=>round((getTx()/1024),2)
        ),
        "uptime"=>$uptime,
        "pc"=>$uname,
        "version"=>"2.1"
    );
    echo json_encode($json);
} 

else if ($_GET["format"]=="full"){
    $full = array(
        "lscpu"=>$lscpu,
        "free"=>$free,
        "df"=>$df,
        "uptime"=>$uptime,
        "net"=>array(
            "Rx"=>getRx(),
            "Tx"=>getTx()
        ),
        "uname"=>$uname
    );
    echo json_encode($full);
}

else {
    function httpPOST($params, $url){
        $query = http_build_query ($params);
        $contextData = array ( 
                        'method' => 'POST',
                        'header' => "Connection: close\r\n".
                                    "Content-Length: ".strlen($query)."\r\n",
                        'content'=> $query );
        $context = stream_context_create (array ( 'http' => $contextData ));
        $result =  file_get_contents (
                        $url,
                        false,
                        $context);
        return $result;
    };

    $host = $_SERVER[REQUEST_SCHEME] . '://' . $_SERVER[SERVER_NAME] . $_SERVER[REQUEST_URI];

    //post host to server to get key
    $key =  httpPost(array('url' => $host), "http://wip.ng-enious.com:5000/new");
    //html stuff we need
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"><head><body><div>';

    if(!preg_match('/wrong/', $key )){

        echo "<p>$key</p>";
        echo "<img src=http://api.qrserver.com/v1/create-qr-code/?size=150x150&data=$key>";

        echo "<p><a src='$host'>$host</a></p>";
        echo "<p>Please scan the QR code above using Monitorize App.</p>";

    } else {
        
        echo "<a href='https://github.com/ng-enious/monitorize-server/blob/master/server.php' target='_blank'><p>Please update the php file.</p></a>";

    }

    echo '</div>';
    //Styles
    echo '<style>body{background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAJ0lEQVQYV2NcvHjxf0VFRQYYYDxy5Mh/OI+BgQEucP/+fQaQSgwVAFNyEDEEJVYGAAAAAElFTkSuQmCC);font-family:monospace;color:#333;font-size:18px}div{margin:4em auto auto;display:block;text-align:center;padding:1em;max-width:40em;border-radius:5px;background:#fff;box-shadow:rgba(0,0,0,.25) 0 5px 10px}</style>';
    echo '</body></html>';
}
?>
