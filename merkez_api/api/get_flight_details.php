<?php
header("Content-Type: application/json; charset=UTF-8");
include '../connecting.php';

$outboundID = $_GET['outbound_id'] ?? 0;

$sql = "SELECT F.FlightNo, D.City as DepCity, A.City as ArrCity 
        FROM Flights_Table F
        JOIN Airports_Table D ON F.DepartureAirportID = D.AirportID
        JOIN Airports_Table A ON F.ArrivalAirportID = A.AirportID
        WHERE F.FlightID = ?";
$stmt = sqlsrv_query($conn, $sql, array($outboundID));

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo json_encode([
        "status" => "success",
        "outbound" => $row,
        "total_price_calculated" => 1500 
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Uçuş bulunamadı."]);
}
?>