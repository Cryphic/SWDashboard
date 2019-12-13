<?php

error_reporting(0); //turhaa logia. 

///käyttäjänimi API Avain, URL muoto, URL osoite.
    $username = "USERNAME";
    $key = "API KEY";
    $auth = "username=$username&apiKey=$key";
    $instance = "HELPDESK LINK";
	$helpdeskwebsite = "WEBSITE";

function curl_error_test($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $responseBody = curl_exec($ch);
    if ($responseBody === false) {
        return "CURL Error: " . curl_error($ch);
    }

    $responseCode =
        curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($responseCode >= 400) {
        return "HTTP Error: " . $responseCode;
    }

    return "No CURL or HTTP Error";
}


/**
 * @param $url
 * @param null $opt
 * @return bool|string
 */
function curl_get($url, $opt = null)
{
    global $instance, $auth;
    $curl_url = $instance . $url;
    $curl_url .= (preg_match("/\\?/", $curl_url)) ? "&" : "?";
    $curl_url .= $auth;
    $opts = array(
        CURLOPT_URL => $curl_url,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1
    );
    if (!is_null($opt) && is_array($opt)) {
        $opts = $opt + $opts;
    }
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}


/*Virhe tarkistus: älä näytä sisältöä jos yhteyttä ei voida muodostaa. */
$error_chk = curl_error_test($helpdeskwebsite);
if (!($error_chk == "No CURL or HTTP Error")) {
    echo "<h1 style='text-align: center;'>" . $error_chk . "</h1>";
    return;
}


switch (htmlspecialchars($_GET["show"])) {
    case "chart":
        echo '<br><div id="chart" style="height: 1000px; overflow: hidden; width: 80%; margin: 0px auto; margin-top: 150px; margin-right: 250px;"><h1 style="color: black; text-align: center;">Ticket status</h1> <canvas id="myChart"></canvas></div>';
        break;
    case  "table":
        echo '<h1 class="display-1" style="margin-top: 10px; text-align: center;">'.$_GET["title"].'</h1>';
        echo '<br><div style="width: 80%; margin: 0px auto;" id="alerts"><h2><div style="padding: 0; width: 50%; height: 80px; display: inline; " class="alert alert-danger">Very High</div> <div style="padding: 0; width: 50%; height: 80px; display: inline;" class="alert alert-warning">High</div>   <div style="width: 50%; padding: 0;  height: 80px; display:inline;" class="alert alert-success">Normal</div></h2></div>';
        echo '<table style="width: 80%; margin: 0px auto; font-size: 30px !important; text-align: center !important;" class="table table-dark table-striped table-sm"><thead><tr><th>Customer</th><th>Name</th><th>Subject</th><th>Report date</th><th>Last updated</th> <th>Tech</th></tr></thead><tbody>';
        break;
    default:
        return;
}

$status_json = curl_get("/ra/StatusTypes");
$status = json_decode($status_json);

if(isset($_GET['data'])){
$data_url = $_GET["data"];
}
if (isset($data_url) && !empty($data_url)) {
    $data_curl = "/ra/Tickets?qualifier=".$data_url."and(statusTypeId!=4)and(statusTypeId!=3)and(statusTypeId!=6)and(statusTypeId!=2))&limit=250&style=long";
    $temp_json = curl_get($data_curl);
    $temp = json_decode($temp_json);
} else {

    $temp_json = curl_get("/ra/Tickets?qualifier=((priorityTypeId=1)and(statusTypeId!=4)and(statusTypeId!=3)and(statusTypeId!=6)and(statusTypeId!=2))&limit=250&style=long");
    $temp = json_decode($temp_json);

    $temp_json = curl_get("/ra/Tickets?qualifier=((priorityTypeId=1)and(statusTypeId!=4)and(statusTypeId!=3)and(statusTypeId!=6)and(statusTypeId!=2))&limit=250&style=long");
    $temp = json_decode($temp_json);

    if (empty(sizeof($temp))) {
        $temp_json = curl_get("/ra/Tickets?qualifier=((priorityTypeId=2)and(statusTypeId!=4)and(statusTypeId!=3)and(statusTypeId!=6)and(statusTypeId!=2))&limit=250&style=long");
        $temp = json_decode($temp_json);

        if (empty(sizeof($temp))) {
            $temp_json = curl_get("/ra/Tickets?qualifier=((priorityTypeId=3)and(statusTypeId!=4)and(statusTypeId!=3)and(statusTypeId!=6)and(statusTypeId!=2))&limit=250&style=long");
            $temp = json_decode($temp_json);
        }
    }
}


/*Last Updated sort */
usort($temp, function ($a, $b) {
    return strtotime($a->lastUpdated) - strtotime($b->lastUpdated);
});
foreach ($temp as $temptickets) {


    if (htmlspecialchars($_GET["show"] == "chart")) {
        break;
    }

    /* UTC > Europe/Helsinki */
    $dt = new DateTime($temptickets->lastUpdated);
    $dt_r = new DateTime($temptickets->reportDateUtc);
    $tz = new DateTimeZone('Europe/Helsinki');


    $dt_r->setTimezone($tz);
	if($temptickets == NULL) {return;}

    switch ($temptickets->priorityTypeId) {
        case 1: /*Very high */
            echo "<tr class='table-danger text-body'><td><strong>{$temptickets->location->locationName}</strong></td><td>{$temptickets->displayClient}</td><td>{$temptickets->subject}</td><td>{$dt_r->format('d-m-Y H:i:s')}</td><td>{$dt->format('d-m-Y H:i:s')}</td><td>{$temptickets->clientTech->displayName}</td></tr>";
            break;

        case 2: /* High */
            echo "<tr class='table-warning text-body'><td><strong>{$temptickets->location->locationName}</strong></td><td>{$temptickets->displayClient}</td><td>{$temptickets->subject}</td><td>{$dt_r->format('d-m-Y H:i:s')}</td><td>{$dt->format('d-m-Y H:i:s')}</td><td>{$temptickets->clientTech->displayName}</td></tr>";
            break; 

        case 3; /* Normal */
            echo "<tr class='table-success text-body'><td><strong>{$temptickets->location->locationName}</strong></td><td>{$temptickets->displayClient}</td><td>{$temptickets->subject}</td><td>{$dt_r->format('d-m-Y H:i:s')}</td><td>{$dt->format('d-m-Y H:i:s')}</td><td>{$temptickets->clientTech->displayName}</td></tr>";
            break;
    }
}
echo "</tbody></table>"; /*Table*/

/*Status foreach */
foreach ($status as $stat) {
    $temp_json = curl_get("/ra/Tickets?qualifier=(statusTypeId=" . $stat->id . ")&limit=250");
    $temp = json_decode($temp_json);


    if (!($stat->id == 3 OR $stat->id == 4)) {


        $dataCount[] = array(count($temp));
        $dataStatus[] = array($stat->statusTypeName);
    }


}
?>

<!DOCTYPE HTML>
<html>
<head>

    <style type="text/css">
        body {
            background: white !important;
        } </style>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css"
          integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
    <script>
        window.onload = function () {
            var ctx = document.getElementById("myChart");
            var myChart = new Chart(ctx, {
                type: 'horizontalBar',
                data: {
                    labels: <?php echo json_encode($dataStatus, JSON_NUMERIC_CHECK); ?>,
                    datasets: [{
                        label: "dataset",
                        data: <?php echo json_encode($dataCount, JSON_NUMERIC_CHECK); ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 255)',
                            'rgba(54, 162, 235, 255)',
                            'rgba(255, 206, 86, 255)',
                            'rgba(75, 192, 192, 255)',
                            'rgba(153, 102,255, 255)',
                            'rgba(255, 159, 64, 255)',
                            'rgba(255, 99, 132, 255)',
                            'rgba(54, 162, 235, 255)',
                            'rgba(255, 206, 86, 255)',
                            'rgba(75, 192, 192, 255)',
                            'rgba(153, 102,255, 255)',
                            'rgba(255, 159, 64, 255)'

                        ],
                        borderColor: [
                            'rgba(255, 99,	132, 1)',
                            'rgba(54,  162, 235, 1)',
                            'rgba(255, 206, 86,  1)',
                            'rgba(75,  192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64,  1)',
                            'rgba(255, 99,  132  1)',
                            'rgba(54,  162, 235, 1)',
                            'rgba(255, 206, 86,  1)',
                            'rgba(75,  192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64,  1)'

                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    title: {display: false, text: 'Ticket status', fontColor: "black", fontSize: 20,},
                    scales: {
                        yAxes: [{
                            gridLines: {color: "#7a7a7a"},
                            ticks: {
                                fontStyle: "bold",
                                fontColor: "black",
                                fontSize: "25",
                                beginAtZero: true
                            }
                        }],
                        xAxes: [{
                            gridLines: {color: "#939393"},
                            ticks: {
                                fontStyle: "bold",
                                fontSize: "25",
                                fontColor: "black",
                                lineHeight: "2",
                                beginAtZero: true
                            }
                        }]
                    }
                }
            });
        }
    </script>
</head>
<body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.3/Chart.bundle.js"></script>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
        integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
        crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js"
        integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut"
        crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js"
        integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k"
        crossorigin="anonymous"></script>
</body>
</html>