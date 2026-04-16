<?php
session_start();

if (isset($_SESSION['user'])) {
    echo "Already logged in <br>";
    echo "<a href='logout_manual.php'>Logout</a>";
    exit();
}

$email = "";
if (isset($_COOKIE['email'])) {
    $email = $_COOKIE['email'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Bloodlines</title>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'DM Sans', sans-serif;
            background: #FAF7F4;
            color: #1A0A0D;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .box {
            background: white;
            padding: 30px;
            border: 1px solid #ddd;
            width: 300px;
        }

        h2 {
            font-family: 'Cormorant Garamond', serif;
            margin-bottom: 20px;
        }

        input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        button {
            width: 100%;
            padding: 10px;
            background: #B5121F;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #7A0A13;
        }
    </style>
</head>

<body>

<div class="box">
    <h2>Login</h2>

    <form action="login_handler_manual.php" method="POST">
        Email:<br>
        <input type="text" name="email" value="<?php echo $email; ?>">

        Password:<br>
        <input type="password" name="password">

        <label>
            <input type="checkbox" name="remember"> Remember Me
        </label><br><br>

        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>