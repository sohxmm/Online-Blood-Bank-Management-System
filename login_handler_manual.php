<?php
session_start();

// simple DB connection
$conn = mysqli_connect("localhost", "root", "", "bloodline_db");

if (!$conn) {
    die("Connection failed");
}

// get form data
$email = $_POST['email'];
$password = md5($_POST['password']); // basic hashing

// simple query (no prepared statement)
$sql = "SELECT * FROM Donor WHERE Email='$email' AND PasswordHash='$password'";

$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 1) {

    // login success
    $_SESSION['user'] = $email;

    // remember me cookie
    if (isset($_POST['remember'])) {
        setcookie("email", $email, time() + (86400 * 7)); // 7 days
    }

    echo "Login successful <br>";
    echo "<a href='dashboard.php'>Go to Dashboard</a>";

} else {
    echo "Invalid email or password <br>";
    echo "<a href='login_manual.php'>Try again</a>";
}
?>