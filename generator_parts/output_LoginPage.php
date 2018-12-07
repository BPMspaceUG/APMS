<?php
    // Includes
    require_once(__DIR__.'/src/DatabaseHandler.inc.php'); // For Config (AuthKey)
    require_once(__DIR__.'/src/AuthHandler.inc.php');

    function getPOSTParamSecure($param) {
        if (isset($_POST[$param])) return $_POST[$param];
        return null;
    }

    // Parameters
    $user = getPOSTParamSecure('usr');
    $pass = getPOSTParamSecure('pwd');
    $logout = isset($_GET['logout']) ? true : false;

    if ($logout) {
        // Delete Cookies
        unset($_COOKIE['token']);
        setcookie("token", "", time()-3600);
        // redirect to Login Page
        header('Location: index.php');
        exit();
    }

    $login_successful = false;
    // TODO: Select Login from Database
    if (!is_null($user) && !is_null($pass)) {
        /* 
        // Select Login from Database
        $login_successful = false;    
        $sql = "SELECT userid, vorname, nachname FROM users WHERE username = '$user' AND password = '$pass' LIMIT 1;";
        $res = DB::getInstance()->getConnection()->query($sql);

        if ($res->num_rows > 0) {
            $row = $res->fetch_array();
            // Set Data
            $user_id = $row[0];
            $firstname = $row[1];
            $lastname = $row[2];
            $login_successful = true;
        }
        */
        // For Testing
        if ($user == 'root' && $pass == 'toor') {
            $user_id = 23;
            $firstname = 'Admin';
            $lastname = 'Admin';
            $login_successful = true;
        }

        // For Testing
        if ($user == 'guest' && $pass == 'guest') {
            $user_id = 42;
            $firstname = 'Guest';
            $lastname = 'Guest';
            $login_successful = true;
        }
        
        // Set Token when Login was successful
        if ($login_successful) {
            // Generate Token
            $token_data = array();
            $token_data['uid'] = $user_id;
            $token_data['firstname'] = $firstname;
            $token_data['lastname'] = $lastname;
            // Token vaild for 60min
            $token_data['exp'] = time() + 60 /*sec*/ * 60 /*min*/ * 24 /*hours*/;
            $token = JWT::encode($token_data, AUTH_KEY);

            // Set Cookie which holds Token (for the next 30 days)
            setcookie("token", $token, time()+(3600 * 24 * 30), '', '', false, true);
            echo json_encode(array('success' => 1));

            // Redirect
            //header("Location: index.php");
            exit();
        } else {
            echo json_encode(array('success' => 0, 'errormsg' => "Login was not successful!"));
            exit();
        }
    }
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css">
  </head>
  <body>
    <div class="container">
        <div class="row mt-5">
            <div class="col-xs-1 col-md-3 col-lg-4"></div>
            <form class="form col-xs-10 col-md-6 col-lg-4" method="post" action="login.php">
                <h2 class="display-4 mb-4">Sign in</h2>
                <div class="alert alert-danger errormsg collapse" role="alert"></div>
                <label for="inputEmail" class="sr-only">Email address</label>
                <input type="text" id="inputEmail" name="usr" class="form-control" placeholder="Email address" required autofocus>
                <br>
                <label for="inputPassword" class="sr-only">Password</label>
                <input type="password" id="inputPassword" name="pwd" class="form-control" placeholder="Password" required>
                <br>
                <button class="btn btn-lg btn-primary btn-block login" type="submit">Sign in</button>
            </form>
            <div class="col-xs-1 col-md-3 col-lg-4"></div>
        </div>
    </div>
    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script>
        // Login Button Click
        $('.login').click(function(e){
            e.preventDefault();
            var u = $('#inputEmail').val();
            var p = $('#inputPassword').val();
            // Request
            $.ajax({
                method: "POST",
                url: 'login.php',
                data: {usr: u, pwd: p}
            }).done(function(r) {
                var data = JSON.parse(r);
                if (data.success === 0) {
                    console.log("Error", data.errormsg);
                    $('.errormsg').text(data.errormsg);
                    $('.errormsg').show();
                } else {
                    // Success
                    console.log("Success")
                    document.location.assign('index.php')
                }
            });
        })
    </script>
  </body>
</html>