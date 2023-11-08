<?php
// assuming the user selects the date and time in CEST itself
// It shows data for last 7 days subtracting 7 day intervals(including the time) as selected
require('connect.php');

try {
    // Get the selected date and time in CEST from the request, or use a default value
    $selectedDateTimeCEST = isset($_GET['selected_date_time']) ? $_GET['selected_date_time'] : '2020-02-18 02:55:18'; // can change the static value used for the example to the current
    $selectedDateTimeGMT3 = convertToGMT3($selectedDateTimeCEST);
    $sevenDaysAgo = (new DateTime($selectedDateTimeCEST))->sub(new DateInterval('P6D'))->setTime(0, 0, 0);
    echo gettype($sevenDaysAgo);

    // Define the SQL query to fetch shipment data for the last 7 days, inlcuding the day selected.
    $sql = "SELECT last_update, status FROM shipments
        WHERE `last_update` BETWEEN :sevenDaysAgo AND :selectedDateTime AND `agent`='NPSC'
        ORDER BY `last_update` ASC";


    // Format the dates as string
    $selectedDateTimeGMT3Str = $selectedDateTimeGMT3->format('Y-m-d H:i:s');
    $sevenDaysAgoStr = $sevenDaysAgo->format('Y-m-d H:i:s');
    echo "  " . $sevenDaysAgoStr . " this is 7 days ago  ";

    // Prepare and execute the query
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':selectedDateTime', $selectedDateTimeGMT3Str, PDO::PARAM_STR);
    $stmt->bindParam(':sevenDaysAgo', $sevenDaysAgoStr, PDO::PARAM_STR);
    $stmt->execute();


    // Fetch all rows from the query result
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
    $statistics = generateStats($data, $sevenDaysAgo, $ongoingOnFirstDay);


    // Convert statistics to the desired format
    $result = array_values($statistics);

    // Return as JSON response
    header('Content-Type: application/json');
    echo json_encode($result);
} catch (Exception $e) {
    // Handle exceptions, log errors, or return an error response
    echo json_encode(['error' => 'An error occurred' . $e]);
}


function convertToGMT3($dateTimeCEST)
{
    $dateTime = new DateTime($dateTimeCEST, new DateTimeZone('Europe/Belgrade'));
    $dateTime->setTimezone(new DateTimeZone('Asia/Bahrain'));
    return $dateTime;
}


function generateStats($data, $sevenDaysAgo, $ongoingOnFirstDay)
{
    $statistics = [];
    $currentOngoing = $ongoingOnFirstDay;
    foreach ($data as $shipment) {
        $shipmentDate = (new DateTime($shipment['last_update'], new DateTimeZone('CEST')))->format('Y-m-d');
        $dayOfWeek = (new DateTime($shipment['last_update'], new DateTimeZone('CEST')))->format('l');
        $status = mapStatus($shipment['status']);

        // echo gettype($shipmentDate);    

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
        // $currentOngoing =
        echo "\ncurrent ongoing : " . $currentOngoing . " for date  " . $shipmentDate;
    }
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