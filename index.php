<?php
// assuming the user selects the date and time in CEST itself
// It shows data for last 7 days subtracting 7 day intervals(including the time) as selected
require('connect.php');

try {
    // Get the selected date and time in CEST from the request, or use a default value
    $selectedDateTimeCEST = isset($_GET['selected_date_time']) ? $_GET['selected_date_time'] : '2020-02-18 02:55:18'; // can change the static value used for the example to the current
    $selectedDateTimeGMT3 = convertToGMT3($selectedDateTimeCEST);


    // Define the SQL query to fetch shipment data for the last 7 days, inlcuding the day selected.
    $sql = "SELECT type, last_update, status FROM shipments
        WHERE `last_update` BETWEEN :sevenDaysAgo AND :selectedDateTime AND `agent`='NPSC'
        ORDER BY `last_update` ASC";


    // Format the dates as string
    $selectedDateTimeGMT3Str = $selectedDateTimeGMT3->format('Y-m-d H:i:s');
    $sevenDaysAgo = (new DateTime($selectedDateTimeCEST))->sub(new DateInterval('P6D'))->setTime(0, 0, 0)->format('Y-m-d H:i:s');


    // Prepare and execute the query
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':selectedDateTime', $selectedDateTimeGMT3Str, PDO::PARAM_STR);
    $stmt->bindParam(':sevenDaysAgo', $sevenDaysAgo, PDO::PARAM_STR);
    $stmt->execute();

    // Fetch all rows from the query result
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // generate the needed stats for the response
    $statistics = generateStats($data);


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


function generateStats($data)
{
    $statistics = [];

    foreach ($data as $shipment) {
        $shipmentDate = (new DateTime($shipment['last_update'], new DateTimeZone('CEST')))->format('Y-m-d');
        $dayOfWeek = (new DateTime($shipment['last_update'], new DateTimeZone('CEST')))->format('l');
        $status = mapStatus($shipment['status']);
        // echo gettype($shipmentDate);    

        if (!isset($statistics[$shipmentDate])) {
            $statistics[$shipmentDate] = [
                'date' => $shipmentDate,
                'dayOfWeek' => $dayOfWeek,
                'statuses' => [],

            ];
        }

        if (!isset($statistics[$shipmentDate]['statuses'][$status])) {
            $statistics[$shipmentDate]['statuses'][$status] = 0;
        }

        $statistics[$shipmentDate]['statuses'][$status]++;
    }
    return $statistics;
}


// Function to map status values
function mapStatus($originalStatus)
{
    switch (strtoupper($originalStatus)) {
        case 'DELIVERED':
            return 'Delivered';
        case 'IN FACILITY':
        case 'IN TRANSIT':
        case 'SCHEDULED':
            return 'Ongoing';
        case 'CANCELLED':
            return 'Cancelled';
        default:
            return 'Unknown';
    }
}