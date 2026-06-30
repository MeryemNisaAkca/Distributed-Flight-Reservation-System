<?php
header("Content-Type: application/json; charset=UTF-8");
include '../connecting.php';

$userID = $_GET['user_id'] ?? 0;

$sql = "SELECT LoyaltyPoints FROM Users_Table WHERE UserID = ?";
$stmt = sqlsrv_query($conn, $sql, array($userID));

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo json_encode(["status" => "success", "points" => $row['LoyaltyPoints']]);
} else {
    echo json_encode(["status" => "success", "points" => 0]);
}
?>