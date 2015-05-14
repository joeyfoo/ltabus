<?php

//Get bus stop number input
if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
	$id = $_GET["id"];
} else {
	$id = "07379";
}

//Set up source URLs
$creds = parse_ini_file(".creds.ini");
$url = "http://datamall2.mytransport.sg/ltaodataservice/BusArrival?BusStopID=$id";
$busstops = file_get_contents("bus-stops.json");

//Get bus arrival info
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL,$url);
$headers = array(
	'UniqueUserId:' . $creds["uniqueuserid"],
	'AccountKey:' . $creds["accountkey"],
);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result=curl_exec($ch);
curl_close($ch);

//Decode bus arrival results
$j = json_decode($result, true);
if (isset($j["odata.error"])) { die ($j["odata.error"]["message"]["value"]); }

//Sorting by arrival time
function my_sort($a, $b) {
    if ($a["NextBus"]["EstimatedArrival"] < $b["NextBus"]["EstimatedArrival"]) {
        return -1;
    } else if ($a["NextBus"]["EstimatedArrival"] > $b["NextBus"]["EstimatedArrival"]) {
        return 1;
    } else {
        return 0;
    }
}

usort($j["Services"], 'my_sort');

//Parse and get bus stop information
function get_stop_name($number, $data) {
    
    $stops = json_decode($data);
    
    foreach ($stops->features as $stop) {
        if ($number == $stop->properties->BUS_STOP_N)
        {
            return $stop->properties->LOC_DESC;
        }
    }
    
    return '(Bus stop)';
}

$name = get_stop_name($id, $busstops)
?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo $j["BusStopID"]; ?></title>
<meta name=viewport content="width=device-width, initial-scale=1">
<style>
ul#buses li { list-style: none; }
ul#buses li:before {
  content: "\1F68C";
}

#busstopid {
  transform: rotate(-90deg);
  -webkit-transform: rotate(-90deg);
  width: 5em;
  position: absolute;
  left: -2em;
  top: -1em;
}
    
#busstopname {
    margin-left: 36px;
}
.wab:after {
    content: " \267F";
    color: #00F;
}
</style>
</head>
<body>
<h1 id=busstopid><?php echo $j["BusStopID"]; ?></h1>
<h2 id="busstopname"><?php echo $name; ?></h2>
<ul id=buses>
<?php

function tmark($s) {
	// var_dump(trim($s["Load"]));

	switch (trim($s["Load"])) {
	case "Seats Available":
		$color = "green";
		break;
	case "Standing Available":
		$color = "orange";
		break;
	case "Limited Standing":
		$color = "red";
		break;
	default:
		$color = "black";
		break;
	}
    
    $details = "";
    
    if ($s["Feature"] == "WAB") {
        $details = "wab";
    }
    
	return '<time class="' . $details . '" style="color: ' . $color . '" dateTime="' . $s["EstimatedArrival"] . '">' . $s["EstimatedArrival"] . '</time>';
}

foreach ($j["Services"] as $service) {
	if (empty($service["NextBus"]["EstimatedArrival"])) { continue; }
	echo "<li>";
	echo "<strong>" . $service["ServiceNo"] . "</strong> ";
	echo tmark($service["NextBus"]) . ", ";
	if (isset($service["SubsequentBus"]["EstimatedArrival"])) {
		echo tmark($service["SubsequentBus"]);
	}
	echo "</li>\n";
}
?>
</ul>
<h4>Last updated: <span id=lastupdated></span> ago</h4>
<form>
<input required type=number value=<?php echo $id;?> name=id>
<input type=submit>
</form>
<ol id=stations></ol>

<p><a href=/map.html>Experimental map of bus stops</a></p>

<script>
function countdown(id, time) {
	// console.log(id,time);
	var seconds =  time / 1000;
	if (Math.abs(seconds) > 60) {
		id.innerHTML = parseInt(seconds / 60) + "m";
	} else {
		id.innerHTML = parseInt(seconds) + "s";
	}
	setTimeout(countdown,1000, id, time - 1000);
}

window.addEventListener('load', function() {
	var timings = document.getElementsByTagName("time");
	var now = new Date();
	for (var i = 0; i < timings.length; i++) {
		var arr = new Date(timings[i].getAttribute("datetime"));
		var elapsed = arr.getTime() - now.getTime();
		countdown(timings[i], elapsed);
	}
	var lastupdated = document.getElementById("lastupdated");
	countdown(lastupdated, Date.now() - now);


	slog = (JSON.parse(localStorage.getItem("history")) || {});
	slog['<?php echo $id;?>'] = 1 + (slog['<?php echo $id;?>'] || 0);
	localStorage.setItem('history', JSON.stringify(slog));
	console.debug(localStorage['history']);

	var sortable = [];
	for (var station in slog) {
		sortable.push([station, slog[station]])
	}
	sortable.sort(function(a, b) {return a[1] - b[1]})
	// console.debug(sortable);
	var ul = document.getElementById("stations");
	for (var i = sortable.length - 1; i >= 0; i--) {
		var key = sortable[i][0];
		var value = sortable[i][1];
		// console.log(key, value);

		var li = document.createElement("li");
		var link = document.createElement('a');
		link.setAttribute('href', '/?id=' + key);
		link.appendChild(document.createTextNode(key));
		li.appendChild(link);
		ul.appendChild(li);
	}

}, false);
</script>
<footer><a href=https://github.com/kaihendry/ltabus>Source code</a>&diam;<a href="mailto:hendry+bus@iki.fi">Email feedback</a></footer>
</body>
</html>
