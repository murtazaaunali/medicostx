<?php
// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$name = $_POST['first_user'];
$phone = $_POST['phone_user'];
$message = $_POST['message_user'];
$adress = $_POST['adress_user'];
$email = $_POST['email_user'];
//$file_user = $_POST['file_user'];

$subject = "Apply Form";

$message = '<html><body>';
$message .= '<div style="text-align:center;"><img style="margin-bottom: 20px;" src="http://medicostx.accunity.com/media/logo/stores/1/logo.jpg"></div>';
$message .= '<table border="1" rules="all" style="border: 1px solid black; border-color: #666;margin:0px auto;width: 65%;" cellpadding="10">';
$message .= "<tr><td><strong>First Name</strong> </td><td>" . $name . "</td></tr>";
$message .= "<tr><td><strong>Phone</strong> </td><td>" . $phone . "</td></tr>";
$message .= "<tr><td><strong>Message</strong> </td><td>" . $message . "</td></tr>";
$message .= "<tr><td><strong>Adress</strong> </td><td>" . $adress . "</td></tr>"; 
$message .= "<tr><td><strong>Email</strong> </td><td>" . $email . "</td></tr>";
$message .= "</table>";
$message .= "</body></html>"; 

$mail = new PHPMailer(true);                              // Passing `true` enables exceptions
try {
    //Server settings
    $mail->SMTPDebug = 2;                                 // Enable verbose debug output
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = 'mail.geeksroot.com;webs02.futuresouls.com';  // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = 'umar.shaikh@geeksroot.com';                 // SMTP username
    $mail->Password = '?Ibl23I3DkbI';                           // SMTP password
    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = 26;                                    // TCP port to connect to

    //Recipients
    $mail->setFrom('umar.shaikh@geeksroot.com', 'Mailer');
    //$mail->addAddress('umar.shaikh@geeksroot.com', 'Joe User');     // Add a recipient
    $mail->addAddress('muzammil@geeksroot.com', 'Joe User');     // Add a recipient
    $mail->addReplyTo('umar.shaikh@geeksroot.com', 'Information');

    //Content
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = $subject;
    $mail->Body    = $message;

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
}

?>
