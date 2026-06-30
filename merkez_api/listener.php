<?php
// Bu dosya Merkez Sunucuda çalışır ve acentelerden gelen JSON eventlerini işler.
include 'connecting.php';

$raw_data = file_get_contents("php://input");
$data = json_decode($raw_data, true);

if ($data && isset($data['type'])) {
    if ($data['type'] === 'FLIGHT_DELAY') {
        $flightID = $data['flight_id'];
        $newTime = $data['new_departure'];
        
        $sql = "UPDATE Flights_Table SET Status = 'Delayed', DepartureTime = ? WHERE FlightID = ?";
        sqlsrv_query($conn, $sql, array($newTime, $flightID));
        
        error_log("Merkez: Uçuş $flightID güncellendi.");
    }
}
?>