<?php
// Define the endpoint path
$endpoint = '/ubex/api/npsc';

// Check if the request matches the endpoint path and is a GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], $endpoint) === 0) {
    // Retrieve the result from a variable
    $result = getStatsForAWeek();
    echo $result;
}else{
    echo 'Error: Only get method allowed, please try again';
}



function getStatsForAWeek()
{
    require('../connection.php');
    try {
        // Get the selected date and time in CEST from the request, or use a default value
        $selectedDateTimeCEST = isset($_GET['selected_date_time']) ? $_GET['selected_date_time'] : '2020-02-18 02:55:18'; // can change the static value used for the example to the current
        $selectedDateTimeGMT3 = convertToGMT3($selectedDateTimeCEST);
        $sevenDaysAgo = (new DateTime($selectedDateTimeCEST))->sub(new DateInterval('P6D'))->setTime(0, 0, 0);

        // Define the SQL query to fetch shipment data for the last 7 days, inlcuding the day selected.
        $sql = "SELECT last_update, status FROM shipments
        WHERE `last_update` BETWEEN :sevenDaysAgo AND :selectedDateTime AND `agent`='NPSC'
        ORDER BY `last_update` ASC";

        // Format the dates as string
        $selectedDateTimeGMT3Str = $selectedDateTimeGMT3->format('Y-m-d H:i:s');
        $sevenDaysAgoStr = $sevenDaysAgo->format('Y-m-d H:i:s');

        // Prepare and execute the query
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':selectedDateTime', $selectedDateTimeGMT3Str, PDO::PARAM_STR);
        $stmt->bindParam(':sevenDaysAgo', $sevenDaysAgoStr, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        //get the number of ongoing shipments before the start date.
        $sql2 = "SELECT COUNT(*) AS shipment_count
                 FROM shipments
                 WHERE 
                 `last_update` < :sevenDaysAgo
                 AND `status` NOT IN ('DELIVERED', 'CANCELLED', 'UNKNOWN', 'DESTROYED')";


        // Prepare and execute the query
        $stmt2 = $db->prepare($sql2);
        $stmt2->bindParam(':sevenDaysAgo', $sevenDaysAgoStr, PDO::PARAM_STR);
        $stmt2->execute();

        $ongoingOnFirstDay = $stmt2->fetchColumn();


        // generate the needed stats for the response
        $statistics = generateStats($data, $ongoingOnFirstDay);


        // Convert statistics to the desired format
        $result = array_values($statistics);

        // Return as JSON response
        return json_encode($result);
    } catch (Exception $e) {
        // Handle exceptions, log errors, or return an error response
        return json_encode(['error' => 'An error occurred' . $e]);
    }
}

function convertToGMT3($dateTimeCEST)
{
    $dateTime = new DateTime($dateTimeCEST, new DateTimeZone('Europe/Belgrade'));
    $dateTime->setTimezone(new DateTimeZone('Asia/Bahrain'));
    return $dateTime;
}


function generateStats($data, $ongoingOnFirstDay)
{
    $statistics = [];
    $currentOngoing = $ongoingOnFirstDay;
    foreach ($data as $shipment) {
        $shipmentDate = (new DateTime($shipment['last_update'], new DateTimeZone('CEST')))->format('Y-m-d');
        $dayOfWeek = (new DateTime($shipment['last_update'], new DateTimeZone('CEST')))->format('l');
        $status = mapStatus($shipment['status']);  

        if (!isset($statistics[$shipmentDate])) {
            $statistics[$shipmentDate] = [
                'date' => $shipmentDate,
                'dayOfWeek' => $dayOfWeek,
                'statuses' => [
                    'DELIVERED' => 0,
                    'ONGOING' => $currentOngoing,
                    'CANCELLED' => 0,
                ]
            ];
        }

        if (!isset($statistics[$shipmentDate]['statuses'][$status])) {
            $statistics[$shipmentDate]['statuses'][$status] = 0;
        }
        if ($status == 'DELIVERED' || $status == 'CANCELLED') {
            $statistics[$shipmentDate]['statuses']['ONGOING']--;
            $currentOngoing--;
        } else {
            $currentOngoing++;
        }
        $statistics[$shipmentDate]['statuses'][$status]++;
    }
    header('Content-Type: application/json');
    return $statistics;
}


// Function to map status values
function mapStatus($originalStatus)
{
    switch (strtoupper($originalStatus)) {
        case 'DELIVERED':
            return 'DELIVERED';
        case 'IN FACILITY':
        case 'IN TRANSIT':
        case 'SCHEDULED':
            return 'ONGOING';
        case 'CANCELLED':
            return 'CANCELLED';
        default:
            return 'UNKNOWN';
    }
}
?>